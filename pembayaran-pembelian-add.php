<?php
require_once 'config/auth.php';
require_once 'config/database.php';

// Check authentication and role
if (!AuthStatic::isLoggedIn()) {
    header('Location: login.php');
    exit;
}
if (!AuthStatic::hasRole(['admin', 'akunting'])) {
    header('Location: dashboard.php?error=access_denied');
    exit;
}

$user = AuthStatic::getCurrentUser();
$db = new Database();

$pembelian_id = $_GET['pembelian_id'] ?? 0;
$error = '';
$success = '';

// Get pembelian data
try {
    $pembelian = $db->select("
        SELECT p.*, v.nama_vendor, v.kode_vendor,
               (p.total_amount - p.jumlah_terbayar) as sisa_hutang
        FROM pembelian p
        LEFT JOIN vendor v ON p.vendor_id = v.id
        WHERE p.id = ?
    ", [$pembelian_id]);
    
    if (empty($pembelian)) {
        header('Location: pembelian.php?error=not_found');
        exit;
    }
    
    $pembelian = $pembelian[0];
    
    if ($pembelian['sisa_hutang'] <= 0) {
        header('Location: pembelian.php?error=already_paid');
        exit;
    }
} catch (Exception $e) {
    header('Location: pembelian.php?error=database_error');
    exit;
}

// Get bank list
$bank_list = $db->select("SELECT * FROM bank WHERE status = 'aktif' ORDER BY nama_bank");

// Process payment
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $jumlah_bayar = floatval($_POST['jumlah_bayar'] ?? 0);
        $metode_bayar = $_POST['metode_bayar'] ?? 'tunai';
        $bank_id = $_POST['bank_id'] ?? null;
        $tanggal_bayar = $_POST['tanggal_bayar'] ?? date('Y-m-d');
        
        // Validation
        if ($jumlah_bayar <= 0) {
            $error = 'Jumlah pembayaran harus lebih dari 0';
        } elseif ($jumlah_bayar > $pembelian['sisa_hutang']) {
            $error = 'Jumlah pembayaran tidak boleh melebihi sisa hutang';
        } else {
            $db->beginTransaction();
            
            // Insert payment record
            $db->execute("INSERT INTO pembayaran_pembelian (
                pembelian_id, jumlah_bayar, tanggal_bayar, metode_bayar, 
                bank_id, user_id
            ) VALUES (?, ?, ?, ?, ?, ?)", [
                $pembelian_id, $jumlah_bayar, $tanggal_bayar, $metode_bayar,
                $bank_id, $user['id']
            ]);
            
            // Update pembelian jumlah_terbayar and status
            $new_terbayar = $pembelian['jumlah_terbayar'] + $jumlah_bayar;
            $new_status = ($new_terbayar >= $pembelian['total_amount']) ? 'lunas' : 'sebagian';
            
            $db->execute("UPDATE pembelian SET 
                jumlah_terbayar = ?, 
                status_pembayaran = ?
                WHERE id = ?", [
                $new_terbayar, $new_status, $pembelian_id
            ]);
            
            // Add to mutasi kas (keluar)
            $db->execute("INSERT INTO mutasi_kas (
                bank_id, jenis_mutasi, jenis_transaksi, referensi_id, 
                referensi_tabel, jumlah, tanggal_mutasi, user_id
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)", [
                $bank_id, 'keluar', 'pembelian', $pembelian_id, 
                'pembelian', $jumlah_bayar, $tanggal_bayar, $user['id']
            ]);
            
            $db->commit();
            header("Location: pembelian-view.php?id={$pembelian_id}&success=payment_added");
            exit;
        }
    } catch (Exception $e) {
        $db->rollback();
        $error = 'Gagal memproses pembayaran: ' . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pembayaran Pembelian - SMD</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <?php include 'layouts/header.php'; ?>
    
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-2xl mx-auto">
            <!-- Header -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h1 class="text-2xl font-bold text-gray-800 mb-4">
                    <i class="fa fa-money mr-2 text-purple-600"></i>
                    Pembayaran Pembelian
                </h1>
                
                <!-- Pembelian Info -->
                <div class="bg-gray-50 rounded-lg p-4 mb-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <span class="font-semibold text-gray-600">Nomor PO:</span>
                            <span class="ml-2"><?= htmlspecialchars($pembelian['nomor_po']) ?></span>
                        </div>
                        <div>
                            <span class="font-semibold text-gray-600">Vendor:</span>
                            <span class="ml-2"><?= htmlspecialchars($pembelian['nama_vendor']) ?></span>
                        </div>
                        <div>
                            <span class="font-semibold text-gray-600">Total Amount:</span>
                            <span class="ml-2 font-bold text-blue-600">Rp <?= number_format($pembelian['total_amount'], 0, ',', '.') ?></span>
                        </div>
                        <div>
                            <span class="font-semibold text-gray-600">Sudah Terbayar:</span>
                            <span class="ml-2 text-green-600">Rp <?= number_format($pembelian['jumlah_terbayar'], 0, ',', '.') ?></span>
                        </div>
                        <div class="md:col-span-2">
                            <span class="font-semibold text-gray-600">Sisa Hutang:</span>
                            <span class="ml-2 font-bold text-red-600">Rp <?= number_format($pembelian['sisa_hutang'], 0, ',', '.') ?></span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Payment Form -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">Form Pembayaran</h2>
                
                <?php if ($error): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    <?= htmlspecialchars($error) ?>
                </div>
                <?php endif; ?>
                
                <form method="POST" class="space-y-4">
                    <!-- Jumlah Bayar -->
                    <div>
                        <label for="jumlah_bayar" class="block text-sm font-medium text-gray-700 mb-2">Jumlah Bayar</label>
                        <input type="number" id="jumlah_bayar" name="jumlah_bayar" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500"
                               step="0.01" min="0" max="<?= $pembelian['sisa_hutang'] ?>" required>
                        <p class="text-sm text-gray-500 mt-1">Maksimal: Rp <?= number_format($pembelian['sisa_hutang'], 0, ',', '.') ?></p>
                    </div>
                    
                    <!-- Tanggal Bayar -->
                    <div>
                        <label for="tanggal_bayar" class="block text-sm font-medium text-gray-700 mb-2">Tanggal Bayar</label>
                        <input type="date" id="tanggal_bayar" name="tanggal_bayar" 
                               value="<?= date('Y-m-d') ?>"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500" required>
                    </div>
                    
                    <!-- Metode Bayar -->
                    <div>
                        <label for="metode_bayar" class="block text-sm font-medium text-gray-700 mb-2">Metode Pembayaran</label>
                        <select id="metode_bayar" name="metode_bayar" 
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500" required>
                            <option value="tunai">Tunai</option>
                            <option value="transfer">Transfer</option>
                            <option value="cek">Cek</option>
                            <option value="giro">Giro</option>
                        </select>
                    </div>
                    
                    <!-- Bank -->
                    <div>
                        <label for="bank_id" class="block text-sm font-medium text-gray-700 mb-2">Bank/Akun</label>
                        <select id="bank_id" name="bank_id" 
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500">
                            <option value="">Pilih Bank/Akun</option>
                            <?php foreach ($bank_list as $bank): ?>
                            <option value="<?= $bank['id'] ?>"><?= htmlspecialchars($bank['nama_bank']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    

                    
                    <!-- Buttons -->
                    <div class="flex gap-4 pt-4">
                        <button type="submit" class="flex-1 bg-purple-500 hover:bg-purple-600 text-white px-4 py-2 rounded-md transition duration-200">
                            <i class="fas fa-credit-card mr-2"></i>Proses Pembayaran
                        </button>
                        <a href="pembelian.php" class="flex-1 bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-md text-center transition duration-200">
                            <i class="fas fa-times mr-2"></i>Kembali
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="js/jquery.min.js"></script>
    <script>
        // Auto-fill full payment amount
        $('#jumlah_bayar').focus(function() {
            if (!$(this).val()) {
                $(this).val(<?= $pembelian['sisa_hutang'] ?>);
            }
        });
    </script>
</body>
</html>