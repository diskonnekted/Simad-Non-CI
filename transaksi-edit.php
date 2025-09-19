<?php
require_once 'config/database.php';
require_once 'config/auth.php';

// Cek autentikasi dan role
if (!AuthStatic::isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$user = AuthStatic::getCurrentUser();
$db = getDatabase();
/** @var Database $db */

$error = '';
$success = '';
$transaksi_id = $_GET['id'] ?? '';

if (empty($transaksi_id)) {
    header('Location: transaksi.php?error=invalid_id');
    exit;
}

// Ambil data transaksi
$transaksi = $db->select("
    SELECT t.*, t.nomor_invoice as invoice_number, t.metode_pembayaran as payment_type, 
           d.nama_desa, d.kecamatan, d.kabupaten, b.nama_bank
    FROM transaksi t
    JOIN desa d ON t.desa_id = d.id
    LEFT JOIN bank b ON t.bank_id = b.id
    WHERE t.id = ?
", [$transaksi_id]);

if (empty($transaksi)) {
    header('Location: transaksi.php?error=not_found');
    exit;
}

$transaksi = $transaksi[0];

// Cek akses berdasarkan role
// Cek akses
if (!AuthStatic::hasRole(['admin']) && $user['id'] != $transaksi['user_id']) {
    header('Location: transaksi.php?error=access_denied');
    exit;
}

// Cek apakah transaksi bisa diedit
if ($transaksi['status_transaksi'] === 'selesai') {
    header('Location: transaksi-view.php?id=' . $transaksi_id . '&error=cannot_edit');
    exit;
}

// Ambil detail transaksi
$detail_transaksi = $db->select("
    SELECT * FROM transaksi_detail 
    WHERE transaksi_id = ? 
    ORDER BY id
", [$transaksi_id]);

// Ambil daftar desa aktif
$desa_list = $db->select("
    SELECT id, nama_desa, kecamatan, kabupaten, nama_kepala_desa, no_hp_kepala_desa, nama_sekdes, no_hp_sekdes, nama_admin_it, no_hp_admin_it
    FROM desa 
    WHERE status = 'aktif' 
    ORDER BY nama_desa
");

// Ambil daftar produk dan layanan
$produk_list = $db->select("
    SELECT id, nama_produk, kategori_id, harga_satuan as harga, stok_tersedia as stok, satuan
    FROM produk 
    WHERE status = 'aktif' 
    ORDER BY nama_produk
");

$layanan_list = $db->select("
    SELECT id, nama_layanan, harga, deskripsi
    FROM layanan 
    WHERE status = 'aktif' 
    ORDER BY nama_layanan
");

// Ambil daftar bank aktif
$bank_list = $db->select("
    SELECT id, kode_bank, nama_bank, jenis_bank
    FROM bank 
    WHERE status = 'aktif' 
    ORDER BY jenis_bank, nama_bank
");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $desa_id = $_POST['desa_id'] ?? '';
    $payment_type = $_POST['payment_type'] ?? 'tunai';
    $bank_id = $_POST['bank_id'] ?? null;
    $dp_amount = floatval($_POST['dp_amount'] ?? 0);
    $tempo_days = intval($_POST['tempo_days'] ?? 0);
    $catatan = trim($_POST['catatan'] ?? '');
    $status = $_POST['status'] ?? 'draft';
    $items = $_POST['items'] ?? [];
    
    // Validasi
    if (empty($desa_id)) {
        $error = 'Desa harus dipilih';
    } elseif (empty($items)) {
        $error = 'Minimal harus ada 1 item transaksi';
    } elseif ($payment_type === 'dp' && $dp_amount <= 0) {
        $error = 'Jumlah DP harus lebih dari 0';
    } elseif ($payment_type === 'tempo' && $tempo_days <= 0) {
        $error = 'Jangka waktu tempo harus lebih dari 0 hari';
    } else {
        try {
            $db->beginTransaction();
            
            // Kembalikan stok produk dari transaksi lama
            foreach ($detail_transaksi as $old_detail) {
                if (!empty($old_detail['produk_id'])) {
                    $db->execute(
                        "UPDATE produk SET stok_tersedia = stok_tersedia + ? WHERE id = ?",
                        [$old_detail['quantity'], $old_detail['produk_id']]
                    );
                }
            }
            
            // Hapus detail transaksi lama
            $db->execute("DELETE FROM transaksi_detail WHERE transaksi_id = ?", [$transaksi_id]);
            
            // Hitung total baru
            $subtotal = 0;
            $valid_items = [];
            
            foreach ($items as $item) {
                $type = $item['type'] ?? '';
                $item_id = intval($item['item_id'] ?? 0);
                $quantity = floatval($item['quantity'] ?? 0);
                $price = floatval($item['price'] ?? 0);
                
                if ($type && $item_id && $quantity > 0 && $price > 0) {
                    // Validasi item exists
                    if ($type === 'produk') {
                        $check = $db->select("SELECT id, nama_produk, stok_tersedia as stok FROM produk WHERE id = ? AND status = 'aktif'", [$item_id]);
                        if (empty($check)) continue;
                        
                        // Cek stok
                        if ($check[0]['stok'] < $quantity) {
                            $error = "Stok {$check[0]['nama_produk']} tidak mencukupi (tersedia: {$check[0]['stok']})";
                            break;
                        }
                        
                        $item_name = $check[0]['nama_produk'];
                    } else {
                        $check = $db->select("SELECT id, nama_layanan FROM layanan WHERE id = ? AND status = 'aktif'", [$item_id]);
                        if (empty($check)) continue;
                        
                        $item_name = $check[0]['nama_layanan'];
                    }
                    
                    $total_price = $quantity * $price;
                    $subtotal += $total_price;
                    
                    $valid_items[] = [
                        'type' => $type,
                        'item_id' => $item_id,
                        'item_name' => $item_name,
                        'quantity' => $quantity,
                        'price' => $price,
                        'total_price' => $total_price
                    ];
                }
            }
            
            if ($error) {
                $db->rollback();
            } elseif (empty($valid_items)) {
                $error = 'Tidak ada item valid untuk diproses';
                $db->rollback();
            } else {
                // Hitung tanggal jatuh tempo
                $tempo_date = null;
                if ($payment_type === 'tempo' && $tempo_days > 0) {
                    $tempo_date = date('Y-m-d', strtotime("+{$tempo_days} days"));
                }
                
                // Update transaksi
                $query = "
                    UPDATE transaksi SET 
                        desa_id = ?, total_amount = ?, 
                        metode_pembayaran = ?, bank_id = ?, dp_amount = ?, tanggal_jatuh_tempo = ?, 
                        catatan = ?, status_transaksi = ?, updated_at = NOW()
                    WHERE id = ?
                ";
                
                $params = [
                    $desa_id, $subtotal, $payment_type, $bank_id,
                    $dp_amount, $tempo_date, $catatan, $status, $transaksi_id
                ];
                
                $db->execute($query, $params);
                
                // Insert detail transaksi baru
                foreach ($valid_items as $item) {
                    $produk_id = ($item['type'] === 'produk') ? $item['item_id'] : null;
                    $layanan_id = ($item['type'] === 'layanan') ? $item['item_id'] : null;
                    
                    $detail_query = "
                        INSERT INTO transaksi_detail (
                            transaksi_id, produk_id, layanan_id, nama_item, 
                            quantity, harga_satuan, subtotal
                        ) VALUES (?, ?, ?, ?, ?, ?, ?)
                    ";
                    
                    $detail_params = [
                        $transaksi_id, $produk_id, $layanan_id, $item['item_name'],
                        $item['quantity'], $item['price'], $item['total_price']
                    ];
                    
                    $db->execute($detail_query, $detail_params);
                    
                    // Update stok produk
                    if ($item['type'] === 'produk') {
                        $db->execute(
                            "UPDATE produk SET stok_tersedia = stok_tersedia - ? WHERE id = ?",
                            [$item['quantity'], $item['item_id']]
                        );
                    }
                }
                
                // Update piutang jika ada perubahan
                $piutang_existing = $db->select(
                    "SELECT * FROM piutang WHERE transaksi_id = ? AND status = 'aktif'",
                    [$transaksi_id]
                );
                
                if (!empty($piutang_existing)) {
                    $piutang = $piutang_existing[0];
                    
                    if ($payment_type === 'tunai') {
                        // Hapus piutang jika berubah ke tunai
                        $db->execute(
                            "UPDATE piutang SET status = 'lunas', sisa_piutang = 0 WHERE id = ?",
                            [$piutang['id']]
                        );
                    } else {
                        // Update piutang
                        $sisa_piutang = $payment_type === 'dp' ? ($subtotal - $dp_amount) : $subtotal;
                        $jatuh_tempo = $payment_type === 'tempo' ? date('Y-m-d', strtotime("+{$tempo_days} days")) : null;
                        
                        $db->execute("
                            UPDATE piutang SET 
                                jumlah_piutang = ?, sisa_piutang = ?, jatuh_tempo = ?, updated_at = NOW()
                            WHERE id = ?
                        ", [$sisa_piutang, $sisa_piutang, $jatuh_tempo, $piutang['id']]);
                    }
                } elseif ($payment_type === 'dp' || $payment_type === 'tempo') {
                    // Buat piutang baru
                    $sisa_piutang = $payment_type === 'dp' ? ($subtotal - $dp_amount) : $subtotal;
                    $jatuh_tempo = $payment_type === 'tempo' ? date('Y-m-d', strtotime("+{$tempo_days} days")) : null;
                    
                    $piutang_query = "
                        INSERT INTO piutang (
                            transaksi_id, desa_id, jumlah_piutang, sisa_piutang, 
                            jatuh_tempo, status
                        ) VALUES (?, ?, ?, ?, ?, 'aktif')
                    ";
                    
                    $piutang_params = [
                        $transaksi_id, $desa_id, $sisa_piutang, $sisa_piutang, $jatuh_tempo
                    ];
                    
                    $db->execute($piutang_query, $piutang_params);
                }
                
                $db->commit();
                
                header("Location: transaksi-view.php?id={$transaksi_id}&success=updated");
                exit;
            }
        } catch (Exception $e) {
            $db->rollback();
            $error = 'Gagal mengupdate transaksi: ' . $e->getMessage();
        }
    }
}
?>
<?php 
$page_title = 'Edit Transaksi';
require_once 'layouts/header.php'; 
?>

<!-- Loading Overlay -->
<div id="loading-overlay" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-lg p-6 flex flex-col items-center">
        <i class="fa fa-spinner fa-spin text-2xl text-primary-500 mb-2"></i>
        <p class="text-gray-600">Memuat...</p>
    </div>
</div>

    <!-- Page Header -->
    <div class="mb-8">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Edit Transaksi</h1>
                <p class="text-sm text-gray-600 mt-1">Ubah data transaksi <?= htmlspecialchars($transaksi['invoice_number']) ?></p>
            </div>
            <div class="mt-4 sm:mt-0 flex space-x-2">
                <a href="transaksi-view.php?id=<?= $transaksi_id ?>" class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-lg text-sm font-medium text-white hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition-colors">
                    <i class="fas fa-eye mr-2"></i>
                    Lihat Detail
                </a>
                <a href="transaksi.php" class="inline-flex items-center px-4 py-2 bg-primary-600 border border-transparent rounded-lg text-sm font-medium text-white hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 transition-colors">
                    <i class="fas fa-list mr-2"></i>
                    Daftar Transaksi
                </a>
            </div>
        </div>
    </div>

    <!-- Main Container -->
    <div class="max-w-7xl ml-0 px-4 sm:px-6 lg:px-8 py-8">
        <?php if ($error): ?>
            <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
                <div class="flex items-center">
                    <i class="fa fa-exclamation-triangle text-red-500 mr-2"></i>
                    <span class="text-red-700"><?= htmlspecialchars($error) ?></span>
                </div>
            </div>
        <?php endif; ?>

        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6">
            <h4 class="flex items-center text-yellow-800 font-semibold mb-2">
                <i class="fa fa-warning text-yellow-600 mr-2"></i>
                Peringatan
            </h4>
            <p class="text-yellow-700">Mengedit transaksi akan mempengaruhi stok produk dan data piutang. Pastikan perubahan yang Anda lakukan sudah benar.</p>
        </div>

        <form method="POST" id="transaksiForm">
            <!-- Informasi Transaksi -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
                <div class="flex items-center mb-4 pb-4 border-b border-gray-200">
                    <i class="fa fa-info-circle text-primary-500 mr-2"></i>
                    <h3 class="text-lg font-semibold text-gray-900">Informasi Transaksi</h3>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Invoice Number</label>
                        <input type="text" class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-50 text-gray-500" value="<?= htmlspecialchars($transaksi['invoice_number']) ?>" readonly>
                    </div>
                    <div>
                        <label for="status" class="block text-sm font-medium text-gray-700 mb-2">Status Transaksi</label>
                        <select id="status" name="status" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                            <option value="draft" <?= $transaksi['status_transaksi'] === 'draft' ? 'selected' : '' ?>>Draft</option>
                            <option value="diproses" <?= $transaksi['status_transaksi'] === 'diproses' ? 'selected' : '' ?>>Diproses</option>
                            <option value="dikirim" <?= $transaksi['status_transaksi'] === 'dikirim' ? 'selected' : '' ?>>Dikirim</option>
                            <option value="selesai" <?= $transaksi['status_transaksi'] === 'selesai' ? 'selected' : '' ?>>Selesai</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Informasi Desa -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
                <div class="flex items-center mb-4 pb-4 border-b border-gray-200">
                    <i class="fa fa-map-marker text-primary-500 mr-2"></i>
                    <h3 class="text-lg font-semibold text-gray-900">Informasi Desa</h3>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="desa_id" class="block text-sm font-medium text-gray-700 mb-2">Pilih Desa <span class="text-red-500">*</span></label>
                        <select id="desa_id" name="desa_id" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500" required>
                            <option value="">-- Pilih Desa --</option>
                            <?php foreach ($desa_list as $desa): ?>
                            <option value="<?= $desa['id'] ?>" 
                                    <?= $transaksi['desa_id'] == $desa['id'] ? 'selected' : '' ?>
                                    data-kontak="<?= htmlspecialchars($desa['nama_admin_it'] ?? '') ?>"
                                    data-telepon="<?= htmlspecialchars($desa['no_hp_admin_it'] ?? '') ?>">
                                <?= htmlspecialchars($desa['nama_desa']) ?> - <?= htmlspecialchars($desa['kecamatan']) ?>, <?= htmlspecialchars($desa['kabupaten']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <div id="desa-info" class="bg-gray-50 p-4 rounded-md">
                            <h5 class="font-semibold text-gray-900 mb-2">Informasi Kontak</h5>
                            <p class="text-sm text-gray-600 mb-1"><strong>Kontak Person:</strong> <span id="kontak-person">-</span></p>
                            <p class="text-sm text-gray-600"><strong>Telepon:</strong> <span id="telepon">-</span></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Item Transaksi -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
                <div class="flex items-center justify-between mb-4 pb-4 border-b border-gray-200">
                    <div class="flex items-center">
                        <i class="fa fa-shopping-cart text-primary-500 mr-2"></i>
                        <h3 class="text-lg font-semibold text-gray-900">Item Transaksi</h3>
                    </div>
                    <button type="button" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-md text-sm font-medium transition-colors" onclick="addItem()">
                        <i class="fa fa-plus mr-1"></i> Tambah Item
                    </button>
                </div>
                
                <div id="items-container" class="space-y-4">
                    <!-- Items will be loaded here -->
                </div>
                
                <div class="mt-6 pt-4 border-t border-gray-200">
                    <div class="flex justify-between items-center">
                        <div>
                            <h4 class="text-lg font-semibold text-gray-900">Total Items: <span id="total-items" class="text-primary-600">0</span></h4>
                        </div>
                        <div>
                            <h3 class="text-xl font-bold text-gray-900">Subtotal: <span id="subtotal" class="text-primary-600">Rp 0</span></h3>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Metode Pembayaran -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
                <div class="flex items-center mb-4 pb-4 border-b border-gray-200">
                    <i class="fa fa-credit-card text-primary-500 mr-2"></i>
                    <h3 class="text-lg font-semibold text-gray-900">Metode Pembayaran</h3>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                    <div>
                        <label class="flex items-start space-x-3 p-4 border border-gray-200 rounded-lg hover:border-primary-300 cursor-pointer">
                            <input type="radio" name="payment_type" value="tunai" 
                                   <?= $transaksi['payment_type'] === 'tunai' ? 'checked' : '' ?>
                                   onchange="togglePaymentOptions()" class="mt-1">
                            <div>
                                <div class="font-semibold text-gray-900">Tunai</div>
                                <p class="text-sm text-gray-500">Pembayaran langsung lunas</p>
                            </div>
                        </label>
                    </div>
                    <div>
                        <label class="flex items-start space-x-3 p-4 border border-gray-200 rounded-lg hover:border-primary-300 cursor-pointer">
                            <input type="radio" name="payment_type" value="dp" 
                                   <?= $transaksi['payment_type'] === 'dp_pelunasan' ? 'checked' : '' ?>
                                   onchange="togglePaymentOptions()" class="mt-1">
                            <div>
                                <div class="font-semibold text-gray-900">DP (Down Payment)</div>
                                <p class="text-sm text-gray-500">Bayar sebagian, sisanya piutang</p>
                            </div>
                        </label>
                    </div>
                    <div>
                        <label class="flex items-start space-x-3 p-4 border border-gray-200 rounded-lg hover:border-primary-300 cursor-pointer">
                            <input type="radio" name="payment_type" value="tempo" 
                                   <?= $transaksi['payment_type'] === 'tempo' ? 'checked' : '' ?>
                                   onchange="togglePaymentOptions()" class="mt-1">
                            <div>
                                <div class="font-semibold text-gray-900">Tempo</div>
                                <p class="text-sm text-gray-500">Bayar nanti dengan jatuh tempo</p>
                            </div>
                        </label>
                    </div>
                </div>
                
                <div class="payment-options">
                    <div id="dp-options" style="display: <?= $transaksi['payment_type'] === 'dp' ? 'block' : 'none' ?>;">
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                            <label for="dp_amount" class="block text-sm font-medium text-gray-700 mb-2">Jumlah DP <span class="text-red-500">*</span></label>
                            <input type="number" id="dp_amount" name="dp_amount" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500" 
                                   value="<?= $transaksi['dp_amount'] ?>"
                                   placeholder="Masukkan jumlah DP" min="0" step="1000">
                            <small class="text-gray-500 text-sm mt-1 block">Sisa akan menjadi piutang</small>
                        </div>
                    </div>
                    
                    <div id="tempo-options" style="display: <?= $transaksi['payment_type'] === 'tempo' ? 'block' : 'none' ?>;">
                        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                            <label for="tempo_days" class="block text-sm font-medium text-gray-700 mb-2">Jangka Waktu Tempo (Hari) <span class="text-red-500">*</span></label>
                            <select id="tempo_days" name="tempo_days" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                                <option value="">-- Pilih Jangka Waktu --</option>
                                <option value="7" <?= $transaksi['tempo_days'] == 7 ? 'selected' : '' ?>>7 Hari</option>
                                <option value="14" <?= $transaksi['tempo_days'] == 14 ? 'selected' : '' ?>>14 Hari</option>
                                <option value="30" <?= $transaksi['tempo_days'] == 30 ? 'selected' : '' ?>>30 Hari</option>
                                <option value="60" <?= $transaksi['tempo_days'] == 60 ? 'selected' : '' ?>>60 Hari</option>
                                <option value="90" <?= $transaksi['tempo_days'] == 90 ? 'selected' : '' ?>>90 Hari</option>
                            </select>
                            <small class="text-gray-500 text-sm mt-1 block">Jatuh tempo: <span id="jatuh-tempo">-</span></small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pilihan Bank -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
                <div class="flex items-center mb-4 pb-4 border-b border-gray-200">
                    <i class="fa fa-university text-primary-500 mr-2"></i>
                    <h3 class="text-lg font-semibold text-gray-900">Pilihan Bank</h3>
                </div>
                
                <div>
                    <label for="bank_id" class="block text-sm font-medium text-gray-700 mb-2">
                        Pilih Bank <span class="text-red-500">*</span>
                    </label>
                    <select id="bank_id" name="bank_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500" required>
                        <option value="">-- Pilih Bank --</option>
                        <?php foreach ($bank_list as $bank): ?>
                            <option value="<?= $bank['id'] ?>" <?= ($transaksi['bank_id'] == $bank['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($bank['nama_bank']) ?> 
                                <?php if ($bank['jenis_bank'] === 'cash'): ?>
                                    <span class="text-green-600">(Cash)</span>
                                <?php elseif ($bank['jenis_bank'] === 'bkk'): ?>
                                    <span class="text-blue-600">(BKK)</span>
                                <?php else: ?>
                                    <span class="text-purple-600">(Bank Umum)</span>
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="text-sm text-gray-500 mt-1">Pilih bank atau metode pembayaran yang akan digunakan</p>
                </div>
            </div>

            <!-- Catatan -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
                <div class="flex items-center mb-4 pb-4 border-b border-gray-200">
                    <i class="fa fa-sticky-note text-primary-500 mr-2"></i>
                    <h3 class="text-lg font-semibold text-gray-900">Catatan Transaksi</h3>
                </div>
                
                <div>
                    <label for="catatan" class="block text-sm font-medium text-gray-700 mb-2">Catatan</label>
                    <textarea id="catatan" name="catatan" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500" rows="4" 
                              placeholder="Catatan tambahan untuk transaksi ini (opsional)"><?= htmlspecialchars($transaksi['catatan'] ?? '') ?></textarea>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex flex-wrap gap-3 justify-end">
                <a href="transaksi-view.php?id=<?= $transaksi_id ?>" class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-3 rounded-md font-medium transition-colors">
                    <i class="fa fa-arrow-left mr-2"></i> Kembali ke Detail
                </a>
                <button type="button" class="bg-yellow-500 hover:bg-yellow-600 text-white px-6 py-3 rounded-md font-medium transition-colors" onclick="resetForm()">
                    <i class="fa fa-refresh mr-2"></i> Reset Form
                </button>
                <button type="submit" class="bg-primary-600 hover:bg-primary-700 text-white px-6 py-3 rounded-md font-medium transition-colors">
                    <i class="fa fa-save mr-2"></i> Update Transaksi
                </button>
            </div>
        </form>
            </div>
        </div>
    </div>

    <!-- Main Container End -->
    </div>

    <!-- JavaScript -->
    <script src="js/jquery.min.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script>
        let itemCounter = 0;
        
        // Data produk dan layanan
        const produkData = <?= json_encode($produk_list) ?>;
        const layananData = <?= json_encode($layanan_list) ?>;
        
        // Data detail transaksi existing
        const existingItems = <?= json_encode($detail_transaksi) ?>;
        
        // Handle desa selection
        $('#desa_id').change(function() {
            const selected = $(this).find(':selected');
            if (selected.val()) {
                const kontak = selected.data('kontak') || '-';
                const telepon = selected.data('telepon') || '-';
                $('#kontak-person').text(kontak);
                $('#telepon').text(telepon);
                $('#desa-info').show();
            } else {
                $('#desa-info').hide();
            }
        });
        
        // Add item function
        function addItem(existingData = null) {
            itemCounter++;
            
            // Determine type and item_id from existing data
            let selectedType = '';
            let selectedItemId = '';
            if (existingData) {
                if (existingData.produk_id) {
                    selectedType = 'produk';
                    selectedItemId = existingData.produk_id;
                } else if (existingData.layanan_id) {
                    selectedType = 'layanan';
                    selectedItemId = existingData.layanan_id;
                }
            }
            const quantity = existingData ? existingData.quantity : '';
            const price = existingData ? existingData.harga_satuan : '';
            
            const itemHtml = `
                <div class="item-row bg-gray-50 border border-gray-200 rounded-lg p-4" id="item-${itemCounter}">
                    <div class="grid grid-cols-1 md:grid-cols-6 gap-4 items-end">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Tipe Item</label>
                            <select name="items[${itemCounter}][type]" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500 item-type" onchange="loadItems(${itemCounter})" required>
                                <option value="">-- Pilih --</option>
                                <option value="produk" ${selectedType === 'produk' ? 'selected' : ''}>Produk</option>
                                <option value="layanan" ${selectedType === 'layanan' ? 'selected' : ''}>Layanan</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Item</label>
                            <select name="items[${itemCounter}][item_id]" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500 item-select" onchange="setPrice(${itemCounter})" required>
                                <option value="">-- Pilih Item --</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Quantity</label>
                            <input type="number" name="items[${itemCounter}][quantity]" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500 item-quantity" 
                                   value="${quantity}" min="1" step="0.01" onchange="calculateItemTotal(${itemCounter})" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Harga Satuan</label>
                            <input type="number" name="items[${itemCounter}][price]" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500 item-price" 
                                   value="${price}" min="0" step="1000" onchange="calculateItemTotal(${itemCounter})" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Total</label>
                            <div class="px-3 py-2 bg-white border border-gray-300 rounded-md text-gray-700 item-total" id="total-${itemCounter}">Rp 0</div>
                        </div>
                        <div class="flex justify-center">
                            <button type="button" class="bg-red-500 hover:bg-red-600 text-white p-2 rounded-md transition-colors" onclick="removeItem(${itemCounter})">
                                <i class="fa fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            `;
            
            $('#items-container').append(itemHtml);
            
            // Load items if type is selected
            if (selectedType) {
                loadItems(itemCounter);
                // Set selected item after loading
                setTimeout(() => {
                    $(`select[name="items[${itemCounter}][item_id]"]`).val(selectedItemId);
                    calculateItemTotal(itemCounter);
                }, 100);
            }
        }
        
        // Load items based on type
        function loadItems(counter) {
            const type = $(`select[name="items[${counter}][type]"]`).val();
            const itemSelect = $(`select[name="items[${counter}][item_id]"]`);
            
            itemSelect.empty().append('<option value="">-- Pilih Item --</option>');
            
            if (type === 'produk') {
                produkData.forEach(item => {
                    itemSelect.append(`<option value="${item.id}" data-price="${item.harga}" data-stok="${item.stok}">
                        ${item.nama_produk} (Stok: ${item.stok} ${item.satuan})
                    </option>`);
                });
            } else if (type === 'layanan') {
                layananData.forEach(item => {
                    itemSelect.append(`<option value="${item.id}" data-price="${item.harga_per_unit}">
                        ${item.nama_layanan} (${item.satuan})
                    </option>`);
                });
            }
        }
        
        // Set price when item selected
        function setPrice(counter) {
            const selected = $(`select[name="items[${counter}][item_id]"] :selected`);
            const price = selected.data('price') || 0;
            $(`input[name="items[${counter}][price]"]`).val(price);
            calculateItemTotal(counter);
        }
        
        // Calculate item total
        function calculateItemTotal(counter) {
            const quantity = parseFloat($(`input[name="items[${counter}][quantity]"]`).val()) || 0;
            const price = parseFloat($(`input[name="items[${counter}][price]"]`).val()) || 0;
            const total = quantity * price;
            
            $(`#total-${counter}`).text(formatRupiah(total));
            calculateSubtotal();
        }
        
        // Remove item
        function removeItem(counter) {
            $(`#item-${counter}`).remove();
            calculateSubtotal();
        }
        
        // Calculate subtotal
        function calculateSubtotal() {
            let subtotal = 0;
            let itemCount = 0;
            
            $('.item-row').each(function() {
                const quantity = parseFloat($(this).find('.item-quantity').val()) || 0;
                const price = parseFloat($(this).find('.item-price').val()) || 0;
                if (quantity > 0 && price > 0) {
                    subtotal += quantity * price;
                    itemCount++;
                }
            });
            
            $('#subtotal').text(formatRupiah(subtotal));
            $('#total-items').text(itemCount);
        }
        
        // Toggle payment options
        function togglePaymentOptions() {
            const paymentType = $('input[name="payment_type"]:checked').val();
            
            $('#dp-options, #tempo-options').hide();
            
            if (paymentType === 'dp') {
                $('#dp-options').show();
            } else if (paymentType === 'tempo') {
                $('#tempo-options').show();
            }
        }
        
        // Calculate jatuh tempo
        $('#tempo_days').change(function() {
            const days = parseInt($(this).val());
            if (days) {
                const jatuhTempo = new Date();
                jatuhTempo.setDate(jatuhTempo.getDate() + days);
                $('#jatuh-tempo').text(jatuhTempo.toLocaleDateString('id-ID'));
            } else {
                $('#jatuh-tempo').text('-');
            }
        });
        
        // Format rupiah
        function formatRupiah(amount) {
            return 'Rp ' + amount.toLocaleString('id-ID');
        }
        
        // Reset form
        function resetForm() {
            if (confirm('Apakah Anda yakin ingin mereset form ke data awal?')) {
                location.reload();
            }
        }
        
        // Initialize
        $(document).ready(function() {
            // Trigger desa info
            $('#desa_id').trigger('change');
            
            // Load existing items
            existingItems.forEach(item => {
                addItem(item);
            });
            
            // If no existing items, add one empty item
            if (existingItems.length === 0) {
                addItem();
            }
            
            // Calculate initial jatuh tempo
            $('#tempo_days').trigger('change');
        });
    </script>

<?php require_once 'layouts/footer.php'; ?>
