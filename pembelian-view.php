<?php
require_once 'config/database.php';
require_once 'config/auth.php';

// Cek autentikasi
if (!AuthStatic::isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$user = AuthStatic::getCurrentUser();
$db = getDatabase();

$error = '';
$success = $_GET['success'] ?? '';
$pembelian_id = $_GET['id'] ?? '';

if (empty($pembelian_id)) {
    header('Location: pembelian.php?error=invalid_id');
    exit;
}

// Ambil data pembelian
$pembelian = $db->select("
    SELECT p.*, v.nama_vendor, v.alamat as vendor_alamat, v.no_hp as vendor_telepon, v.email as vendor_email,
           u.nama_lengkap as user_name, u.email as user_email,
           d.nama_desa
    FROM pembelian p
    JOIN vendor v ON p.vendor_id = v.id
    JOIN users u ON p.user_id = u.id
    LEFT JOIN desa d ON p.desa_id = d.id
    WHERE p.id = ?
", [$pembelian_id]);

// Set default jumlah_terbayar jika null
if (!empty($pembelian)) {
    $pembelian[0]['jumlah_terbayar'] = $pembelian[0]['jumlah_terbayar'] ?? 0;
}

if (empty($pembelian)) {
    header('Location: pembelian.php?error=not_found');
    exit;
}

$pembelian = $pembelian[0];

// Cek akses berdasarkan role
if (!AuthStatic::hasRole(['admin', 'finance']) && $user['id'] != $pembelian['user_id']) {
    header('Location: pembelian.php?error=access_denied');
    exit;
}

// Ambil detail pembelian
$detail_pembelian = $db->select("
    SELECT pd.*, p.nama_produk, p.kode_produk, pd.quantity_pesan as quantity
    FROM pembelian_detail pd
    LEFT JOIN produk p ON pd.produk_id = p.id
    WHERE pd.pembelian_id = ? 
    ORDER BY pd.id
", [$pembelian_id]);

// Ambil riwayat pembayaran
$riwayat_pembayaran = $db->select("
    SELECT pp.*, u.nama_lengkap as user_name
    FROM pembayaran_pembelian pp
    LEFT JOIN users u ON pp.user_id = u.id
    WHERE pp.pembelian_id = ?
    ORDER BY pp.tanggal_bayar DESC, pp.created_at DESC
", [$pembelian_id]);

// Helper functions
function formatRupiah($amount) {
    return 'Rp ' . number_format($amount, 0, ',', '.');
}

function getStatusBadge($status) {
    $badges = [
        'draft' => 'bg-gray-100 text-gray-800',
        'dikirim' => 'bg-yellow-100 text-yellow-800',
        'diterima_sebagian' => 'bg-blue-100 text-blue-800',
        'diterima_lengkap' => 'bg-green-100 text-green-800',
        'dibatalkan' => 'bg-red-100 text-red-800'
    ];
    
    $labels = [
        'draft' => 'Draft',
        'dikirim' => 'Dikirim',
        'diterima_sebagian' => 'Diterima Sebagian',
        'diterima_lengkap' => 'Diterima Lengkap',
        'dibatalkan' => 'Dibatalkan'
    ];
    
    $class = $badges[$status] ?? 'bg-gray-100 text-gray-800';
    $label = $labels[$status] ?? ucfirst($status ?? '');
    return "<span class='px-2 py-1 rounded-full text-xs font-medium {$class}'>" . $label . "</span>";
}

function getStatusPembayaranBadge($status) {
    $badges = [
        'belum_bayar' => 'bg-red-100 text-red-800',
        'dp' => 'bg-yellow-100 text-yellow-800',
        'lunas' => 'bg-green-100 text-green-800'
    ];
    
    $class = $badges[$status] ?? 'bg-gray-100 text-gray-800';
    $text = [
        'belum_bayar' => 'Belum Bayar',
        'dp' => 'DP',
        'lunas' => 'Lunas'
    ];
    
    return "<span class='px-2 py-1 rounded-full text-xs font-medium {$class}'>" . ($text[$status] ?? ucfirst($status)) . "</span>";
}

// Process update status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    if (!AuthStatic::hasRole(['admin', 'finance'])) {
        $error = 'Anda tidak memiliki akses untuk mengubah status';
    } else {
        $new_status = $_POST['status'] ?? '';
        $valid_statuses = ['draft', 'dikirim', 'diterima_sebagian', 'diterima_lengkap', 'dibatalkan'];
        
        if (!in_array($new_status, $valid_statuses)) {
            $error = 'Status tidak valid';
        } else {
            try {
                $db->execute("
                    UPDATE pembelian 
                    SET status_pembelian = ?, updated_at = NOW()
                    WHERE id = ?
                ", [$new_status, $pembelian_id]);
                
                header("Location: pembelian-view.php?id={$pembelian_id}&success=status_updated");
                exit;
            } catch (Exception $e) {
                $error = 'Gagal mengubah status: ' . $e->getMessage();
            }
        }
    }
}

// Process pembayaran
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bayar_pembelian'])) {
    if (!AuthStatic::hasRole(['admin', 'finance'])) {
        $error = 'Anda tidak memiliki akses untuk memproses pembayaran';
    } else {
        $jumlah_bayar = floatval($_POST['jumlah_bayar'] ?? 0);
        $metode_bayar = $_POST['metode_bayar'] ?? 'tunai';
        $keterangan = trim($_POST['keterangan'] ?? '');
        
        if ($jumlah_bayar <= 0) {
            $error = 'Jumlah pembayaran harus lebih dari 0';
        } elseif ($jumlah_bayar > ($pembelian['total_amount'] - $pembelian['jumlah_terbayar'])) {
            $error = 'Jumlah pembayaran tidak boleh melebihi sisa tagihan';
        } else {
            try {
                $db->beginTransaction();
                
                // Simpan data pembayaran ke tabel pembayaran_pembelian
                $db->execute("
                    INSERT INTO pembayaran_pembelian 
                    (pembelian_id, jumlah_bayar, tanggal_bayar, metode_bayar, catatan, user_id)
                    VALUES (?, ?, CURDATE(), ?, ?, ?)
                ", [$pembelian_id, $jumlah_bayar, $metode_bayar, $keterangan, $user['id']]);
                
                // Trigger akan otomatis mengupdate jumlah_terbayar dan status_pembayaran
                
                $db->commit();
                
                header("Location: pembelian-view.php?id={$pembelian_id}&success=payment_added");
                exit;
            } catch (Exception $e) {
                $db->rollback();
                $error = 'Gagal memproses pembayaran: ' . $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Pembelian - SMD</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        @media print {
            .no-print { display: none !important; }
            body { font-size: 12px; }
        }
    </style>
</head>
<body class="bg-gray-100">
    <?php include 'layouts/header.php'; ?>

    <div class="max-w-7xl ml-0 px-4 sm:px-6 lg:px-8 py-8">
        <!-- Breadcrumb -->
        <nav class="text-sm mb-6 no-print">
            <ol class="list-none p-0 inline-flex">
                <li class="flex items-center">
                    <a href="dashboard.php" class="text-blue-600 hover:text-blue-800">Dashboard</a>
                    <i class="fa fa-chevron-right mx-2 text-gray-400"></i>
                </li>
                <li class="flex items-center">
                    <a href="pembelian.php" class="text-blue-600 hover:text-blue-800">Pembelian</a>
                    <i class="fa fa-chevron-right mx-2 text-gray-400"></i>
                </li>
                <li class="text-gray-500">Detail Pembelian</li>
            </ol>
        </nav>

        <!-- Header -->
        <div class="bg-white p-6 rounded-lg shadow-sm mb-6">
            <div class="flex justify-between items-start">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800 mb-2">
                        <i class="fa fa-shopping-cart text-blue-500"></i>
                        Detail Pembelian
                    </h1>
                    <p class="text-gray-600">Informasi lengkap pembelian dari vendor</p>
                </div>
                <div class="flex space-x-2 no-print">
                    <button onclick="window.print()" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-md transition duration-200">
                        <i class="fa fa-print"></i> Print
                    </button>
                    <a href="pembelian.php" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-md transition duration-200">
                        <i class="fa fa-arrow-left"></i> Kembali
                    </a>
                </div>
            </div>
        </div>

        <!-- Alert Messages -->
        <?php if ($error): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6 no-print">
            <i class="fa fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <?php if ($success): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6 no-print">
            <i class="fa fa-check-circle"></i>
            <?php if ($success === 'status_updated'): ?>
                Status pembelian berhasil diperbarui!
            <?php elseif ($success === 'payment_added'): ?>
                Pembayaran berhasil diproses!
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Purchase Process Indicator -->
        <?php 
        require_once 'components/purchase_process_indicator.php';
        renderPurchaseProcessIndicator($pembelian);
        ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Informasi Pembelian -->
            <div class="lg:col-span-2">
                <div class="bg-white p-6 rounded-lg shadow-sm mb-6">
                    <div class="text-lg font-bold text-gray-800 mb-4 pb-3 border-b-2 border-gray-100">
                        <i class="fa fa-info-circle text-blue-500"></i> Informasi Pembelian
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <div class="mb-3">
                                <span class="font-semibold text-gray-600">Nomor PO:</span>
                                <span class="ml-2 font-mono"><?= htmlspecialchars($pembelian['nomor_po']) ?></span>
                            </div>
                            <div class="mb-3">
                                <span class="font-semibold text-gray-600">Tanggal:</span>
                                <span class="ml-2"><?= date('d/m/Y', strtotime($pembelian['tanggal_pembelian'])) ?></span>
                            </div>
                            <div class="mb-3">
                                <span class="font-semibold text-gray-600">Status:</span>
                                <span class="ml-2"><?= getStatusBadge($pembelian['status_pembelian']) ?></span>
                            </div>
                            <div class="mb-3">
                                <span class="font-semibold text-gray-600">Status Pembayaran:</span>
                                <span class="ml-2"><?= getStatusPembayaranBadge($pembelian['status_pembayaran']) ?></span>
                            </div>
                        </div>
                        <div>

                            <div class="mb-3">
                                <span class="font-semibold text-gray-600">Email:</span>
                                <span class="ml-2"><?= htmlspecialchars($pembelian['user_email']) ?></span>
                            </div>
                            <div class="mb-3">
                                <span class="font-semibold text-gray-600">Desa Pemesan:</span>
                                <span class="ml-2"><?= htmlspecialchars($pembelian['nama_desa'] ?? '-') ?></span>
                            </div>
                            <div class="mb-3">
                                <span class="font-semibold text-gray-600">Total Pembelian:</span>
                                <span class="ml-2 font-bold text-green-600"><?= formatRupiah($pembelian['total_amount']) ?></span>
                            </div>
                            <?php if ($pembelian['status_pembayaran'] === 'lunas'): ?>
                            <div class="mb-3">
                                <span class="font-semibold text-gray-600">Jumlah Terbayar:</span>
                                <span class="ml-2 font-bold text-green-600"><?= formatRupiah($pembelian['jumlah_terbayar']) ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if ($pembelian['status_pembayaran'] !== 'lunas' && $pembelian['jumlah_terbayar'] < $pembelian['total_amount']): ?>
                            <div class="mb-3">
                                <span class="font-semibold text-gray-600">Sisa Tagihan:</span>
                                <span class="ml-2 font-bold text-red-600"><?= formatRupiah($pembelian['total_amount'] - $pembelian['jumlah_terbayar']) ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Detail Items -->
                <div class="bg-white p-6 rounded-lg shadow-sm mb-6">
                    <div class="text-lg font-bold text-gray-800 mb-4 pb-3 border-b-2 border-gray-100">
                        <i class="fa fa-list text-green-500"></i> Detail Items
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full border-collapse">
                            <thead>
                                <tr class="bg-gray-50">
                                    <th class="p-3 text-left font-bold border-b border-gray-200">No</th>
                                    <th class="p-3 text-left font-bold border-b border-gray-200">Kode Produk</th>
                                    <th class="p-3 text-left font-bold border-b border-gray-200">Nama Produk</th>
                                    <th class="p-3 text-left font-bold border-b border-gray-200">Quantity</th>
                                    <th class="p-3 text-left font-bold border-b border-gray-200">Harga Satuan</th>
                                    <th class="p-3 text-left font-bold border-b border-gray-200">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $no = 1; foreach ($detail_pembelian as $detail): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="p-3 border-b border-gray-200"><?= $no++ ?></td>
                                    <td class="p-3 border-b border-gray-200"><?= htmlspecialchars($detail['kode_produk'] ?? '-') ?></td>
                                    <td class="p-3 border-b border-gray-200"><?= htmlspecialchars($detail['nama_produk'] ?? $detail['nama_item']) ?></td>
                                    <td class="p-3 border-b border-gray-200"><?= number_format($detail['quantity'], 2) ?></td>
                                    <td class="p-3 border-b border-gray-200"><?= formatRupiah($detail['harga_satuan']) ?></td>
                                    <td class="p-3 border-b border-gray-200"><?= formatRupiah($detail['subtotal']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr class="bg-gray-50 font-bold">
                                    <td colspan="5" class="p-3 text-right border-b border-gray-200">TOTAL:</td>
                                    <td class="p-3 border-b border-gray-200 text-green-600 font-bold"><?= formatRupiah($pembelian['total_amount']) ?></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                <!-- Catatan -->
                <?php if ($pembelian['catatan']): ?>
                <div class="bg-white p-6 rounded-lg shadow-sm mb-6">
                    <div class="text-lg font-bold text-gray-800 mb-4 pb-3 border-b-2 border-gray-100">
                        <i class="fa fa-sticky-note text-yellow-500"></i> Catatan
                    </div>
                    <p class="text-gray-700 leading-relaxed"><?= nl2br(htmlspecialchars($pembelian['catatan'])) ?></p>
                </div>
                <?php endif; ?>
            </div>

            <!-- Sidebar -->
            <div class="lg:col-span-1">
                <!-- Informasi Vendor -->
                <div class="bg-white p-6 rounded-lg shadow-sm mb-6">
                    <div class="text-lg font-bold text-gray-800 mb-4 pb-3 border-b-2 border-gray-100">
                        <i class="fa fa-building text-purple-500"></i> Informasi Vendor
                    </div>
                    <div class="space-y-3">
                        <div>
                            <span class="font-semibold text-gray-600">Nama Vendor:</span>
                            <p class="text-gray-800"><?= htmlspecialchars($pembelian['nama_vendor']) ?></p>
                        </div>
                        <div>
                            <span class="font-semibold text-gray-600">Alamat:</span>
                            <p class="text-gray-800"><?= htmlspecialchars($pembelian['vendor_alamat'] ?? '') ?></p>
                        </div>
                        <div>
                            <span class="font-semibold text-gray-600">Telepon:</span>
                            <p class="text-gray-800"><?= htmlspecialchars($pembelian['vendor_telepon'] ?? '') ?></p>
                        </div>
                        <div>
                            <span class="font-semibold text-gray-600">Email:</span>
                            <p class="text-gray-800"><?= htmlspecialchars($pembelian['vendor_email'] ?? '') ?></p>
                        </div>
                    </div>
                </div>

                <!-- Update Status -->
                <?php if (AuthStatic::hasRole(['admin', 'finance'])): ?>
                <div class="bg-white p-6 rounded-lg shadow-sm mb-6 no-print">
                    <div class="text-lg font-bold text-gray-800 mb-4 pb-3 border-b-2 border-gray-100">
                        <i class="fa fa-edit text-orange-500"></i> Update Status
                    </div>
                    <form method="POST">
                        <div class="mb-4">
                            <label for="status" class="block text-sm font-medium text-gray-700 mb-2">Status Pembelian</label>
                            <select id="status" name="status" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="draft" <?= $pembelian['status_pembelian'] === 'draft' ? 'selected' : '' ?>>Draft</option>
                                <option value="dikirim" <?= $pembelian['status_pembelian'] === 'dikirim' ? 'selected' : '' ?>>Dikirim</option>
                                <option value="diterima_sebagian" <?= $pembelian['status_pembelian'] === 'diterima_sebagian' ? 'selected' : '' ?>>Diterima Sebagian</option>
                                <option value="diterima_lengkap" <?= $pembelian['status_pembelian'] === 'diterima_lengkap' ? 'selected' : '' ?>>Diterima Lengkap</option>
                                <option value="dibatalkan" <?= $pembelian['status_pembelian'] === 'dibatalkan' ? 'selected' : '' ?>>Dibatalkan</option>
                            </select>
                        </div>
                        <button type="submit" name="update_status" class="w-full bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-md transition duration-200">
                            <i class="fa fa-save"></i> Update Status
                        </button>
                    </form>
                </div>
                <?php endif; ?>

                <!-- Form Pembayaran -->
                <?php if ($pembelian['status_pembayaran'] !== 'lunas' && AuthStatic::hasRole(['admin', 'finance'])): ?>
                <div class="bg-white p-6 rounded-lg shadow-sm mb-6 no-print">
                    <div class="text-lg font-bold text-gray-800 mb-4 pb-3 border-b-2 border-gray-100">
                        <i class="fa fa-money text-green-500"></i> Proses Pembayaran
                    </div>
                    <form method="POST">
                        <div class="mb-4">
                            <label for="jumlah_bayar" class="block text-sm font-medium text-gray-700 mb-2">Jumlah Bayar</label>
                            <input type="number" id="jumlah_bayar" name="jumlah_bayar" step="0.01" 
                                   max="<?= $pembelian['total_amount'] - $pembelian['jumlah_terbayar'] ?>"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" 
                                   placeholder="Masukkan jumlah pembayaran" required>
                        </div>
                        <div class="mb-4">
                            <label for="metode_bayar" class="block text-sm font-medium text-gray-700 mb-2">Metode Pembayaran</label>
                            <select id="metode_bayar" name="metode_bayar" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="tunai">Tunai</option>
                                <option value="transfer">Transfer Bank</option>
                                <option value="cek">Cek</option>
                                <option value="giro">Giro</option>
                            </select>
                        </div>
                        <div class="mb-4">
                            <label for="keterangan" class="block text-sm font-medium text-gray-700 mb-2">Keterangan</label>
                            <input type="text" id="keterangan" name="keterangan" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" 
                                   placeholder="Keterangan pembayaran">
                        </div>
                        <button type="submit" name="bayar_pembelian" class="w-full bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-md transition duration-200">
                            <i class="fa fa-money"></i> Proses Pembayaran
                        </button>
                    </form>
                </div>
                <?php endif; ?>

                <!-- Riwayat Pembayaran -->
                <?php if (!empty($riwayat_pembayaran)): ?>
                <div class="bg-white p-6 rounded-lg shadow-sm mb-6">
                    <div class="text-lg font-bold text-gray-800 mb-4 pb-3 border-b-2 border-gray-100">
                        <i class="fa fa-history text-blue-500"></i> Riwayat Pembayaran
                    </div>
                    <div class="space-y-3">
                        <?php foreach ($riwayat_pembayaran as $bayar): ?>
                        <div class="border border-gray-200 rounded-lg p-4">
                            <div class="flex justify-between items-start mb-2">
                                <div class="font-semibold text-green-600">
                                    <?= formatRupiah($bayar['jumlah_bayar']) ?>
                                </div>
                                <div class="text-sm text-gray-500">
                                    <?= date('d/m/Y', strtotime($bayar['tanggal_bayar'])) ?>
                                </div>
                            </div>
                            <div class="text-sm text-gray-600 mb-1">
                                <strong>Metode:</strong> <?= ucfirst($bayar['metode_bayar']) ?>
                            </div>
                            <?php if ($bayar['catatan']): ?>
                            <div class="text-sm text-gray-600 mb-1">
                                <strong>Keterangan:</strong> <?= htmlspecialchars($bayar['catatan']) ?>
                            </div>
                            <?php endif; ?>
                            <div class="text-xs text-gray-500">
                                Diproses oleh: <?= htmlspecialchars($bayar['user_name'] ?? 'Unknown') ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php include 'layouts/footer.php'; ?>
    
    <!-- Purchase Process JavaScript -->
    <?php renderPurchaseProcessJS(); ?>
</body>
</html>