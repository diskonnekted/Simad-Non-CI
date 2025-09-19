<?php
require_once 'config/database.php';
require_once 'config/auth.php';

// Helper functions
function formatRupiah($angka) {
    if ($angka === null || $angka === '') {
        return 'Rp 0';
    }
    return 'Rp ' . number_format($angka, 0, ',', '.');
}

function getStatusText($status) {
    switch ($status) {
        case 'aktif':
            return 'Aktif';
        case 'nonaktif':
            return 'Non-aktif';
        case 'habis':
            return 'Habis';
        default:
            return 'Tidak Diketahui';
    }
}

// Cek autentikasi
if (!AuthStatic::isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$user = AuthStatic::getCurrentUser();
$db = getDatabase();
/** @var Database $db */

$produk_id = intval($_GET['id'] ?? 0);

if (!$produk_id) {
    header('Location: produk.php?error=invalid_id');
    exit;
}

// Ambil data produk dengan informasi kategori dan vendor
$produk = $db->select("
    SELECT p.*, k.nama_kategori, v.nama_vendor, v.kode_vendor, v.jenis_vendor
    FROM produk p
    LEFT JOIN kategori_produk k ON p.kategori_id = k.id
    LEFT JOIN vendor v ON p.vendor_id = v.id
    WHERE p.id = ? AND p.status != 'deleted'
", [$produk_id]);

if (empty($produk)) {
    header('Location: produk.php?error=not_found');
    exit;
}

$produk = $produk[0];

// Ambil statistik transaksi produk
$stats = $db->select("
    SELECT 
        COUNT(DISTINCT td.transaksi_id) as total_transaksi,
        SUM(td.quantity) as total_terjual,
        SUM(td.subtotal) as total_pendapatan,
        AVG(td.harga_satuan) as harga_rata_rata,
        MIN(t.tanggal_transaksi) as transaksi_pertama,
        MAX(t.tanggal_transaksi) as transaksi_terakhir
    FROM transaksi_detail td
    JOIN transaksi t ON td.transaksi_id = t.id
    WHERE td.produk_id = ? 
", [$produk_id]);

$stats = $stats[0] ?? [
    'total_transaksi' => 0,
    'total_terjual' => 0,
    'total_pendapatan' => 0,
    'harga_rata_rata' => 0,
    'transaksi_pertama' => null,
    'transaksi_terakhir' => null
];

// Ambil transaksi terbaru yang menggunakan produk ini
$transaksi_terbaru = $db->select("
    SELECT t.id, t.nomor_invoice, t.tanggal_transaksi, t.total_amount,
           d.nama_desa, td.quantity, td.harga_satuan, td.subtotal,
           u.nama_lengkap as sales_name
    FROM transaksi_detail td
    JOIN transaksi t ON td.transaksi_id = t.id
    JOIN desa d ON t.desa_id = d.id
    JOIN users u ON t.user_id = u.id
    WHERE td.produk_id = ?
    ORDER BY t.tanggal_transaksi DESC
    LIMIT 10
", [$produk_id]);

// Ambil riwayat perubahan stok (dari transaksi keluar)
$riwayat_keluar = $db->select("
    SELECT t.id, t.tanggal_transaksi, td.quantity, t.nomor_invoice, d.nama_desa,
           'keluar' as jenis, u.nama_lengkap as user_name
    FROM transaksi_detail td
    JOIN transaksi t ON td.transaksi_id = t.id
    JOIN desa d ON t.desa_id = d.id
    JOIN users u ON t.user_id = u.id
    WHERE td.produk_id = ?
    ORDER BY t.tanggal_transaksi DESC
    LIMIT 10
", [$produk_id]);

// Ambil riwayat perubahan stok (dari pembelian masuk)
$riwayat_masuk = $db->select("
    SELECT p.id, p.tanggal_pembelian as tanggal_transaksi, pd.quantity_terima as quantity, 
           p.nomor_po as nomor_invoice, v.nama_vendor as nama_desa,
           'masuk' as jenis, u.nama_lengkap as user_name
    FROM pembelian_detail pd
    JOIN pembelian p ON pd.pembelian_id = p.id
    JOIN vendor v ON p.vendor_id = v.id
    JOIN users u ON p.user_id = u.id
    WHERE pd.produk_id = ? AND pd.quantity_terima > 0
    ORDER BY p.tanggal_pembelian DESC
    LIMIT 10
", [$produk_id]);

// Gabungkan dan urutkan berdasarkan tanggal
$riwayat_stok = array_merge($riwayat_keluar, $riwayat_masuk);
usort($riwayat_stok, function($a, $b) {
    return strtotime($b['tanggal_transaksi']) - strtotime($a['tanggal_transaksi']);
});
$riwayat_stok = array_slice($riwayat_stok, 0, 20);

// Helper functions untuk status stok

function getStokStatus($stok, $minimum = 10) {
    if ($stok <= 0) return ['danger', 'Habis', 'fa-times-circle'];
    if ($stok <= $minimum) return ['warning', 'Rendah', 'fa-exclamation-triangle'];
    if ($stok <= ($minimum * 3)) return ['info', 'Sedang', 'fa-info-circle'];
    return ['success', 'Aman', 'fa-check-circle'];
}
?>
<!DOCTYPE html>
<html lang="id" class="h-full bg-gray-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Produk - <?= htmlspecialchars($produk['nama_produk']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#eff6ff',
                            500: '#3b82f6',
                            600: '#2563eb',
                            700: '#1d4ed8'
                        }
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="h-full">
    <?php require_once 'layouts/header.php'; ?>

            <!-- Page Header -->
            <div class="bg-white shadow-sm border-b border-gray-200 mb-6">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
                    <div class="flex items-center justify-start">
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900 flex items-center">
                                <i class="fas fa-box text-primary-500 mr-3"></i>
                                Detail Produk
                            </h1>
                            <p class="text-gray-600 mt-1">Informasi lengkap produk</p>
                        </div>
                        <nav class="flex" aria-label="Breadcrumb">
                            <ol class="flex items-center space-x-2 text-sm">
                                <li><a href="index.php" class="text-primary-600 hover:text-primary-700">Dashboard</a></li>
                                <li class="text-gray-400">/</li>
                                <li><a href="produk.php" class="text-primary-600 hover:text-primary-700">Produk</a></li>
                                <li class="text-gray-400">/</li>
                                <li class="text-gray-600">Detail</li>
                            </ol>
                        </nav>
                    </div>
                </div>
            </div>

    <!-- Main Content -->
    <div class="max-w-7xl px-4 sm:px-6 lg:px-8 py-8">
            <div class="bg-white shadow-sm border-b border-gray-200 mb-6 rounded-lg">
                <div class="px-6 py-4">
                    <h1 class="text-2xl font-bold text-gray-900"><?= htmlspecialchars($produk['nama_produk'] ?? '') ?></h1>
                    <p class="text-sm text-gray-600 mt-1"><?= htmlspecialchars($produk['kode_produk'] ?? '') ?></p>
                </div>
            </div>

            <!-- Product Details -->
            <div class="bg-white shadow-lg rounded-lg mb-6 border border-gray-100">
                <div class="p-6">
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 text-left">
                        <!-- Product Image -->
                        <div class="lg:col-span-1">
                            <?php if (!empty($produk['gambar'])): ?>
                                <img src="uploads/produk/<?= htmlspecialchars($produk['gambar'] ?? '') ?>" 
                                     alt="<?= htmlspecialchars($produk['nama_produk'] ?? '') ?>" 
                                     class="w-full h-64 object-cover rounded-lg border border-gray-200 shadow-sm">
                            <?php else: ?>
                                <div class="w-full h-64 bg-gray-100 rounded-lg border border-gray-200 flex items-center justify-center">
                                    <i class="fas fa-image text-4xl text-gray-400"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Product Info -->
                        <div class="lg:col-span-2">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="space-y-4">
                                    <div class="py-2 border-b border-gray-100 text-left">
                                        <span class="font-medium text-gray-600">Kategori:</span>
                                        <div class="mt-1">
                                            <?php if (!empty($produk['nama_kategori'])): ?>
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800"><?= htmlspecialchars($produk['nama_kategori'] ?? '') ?></span>
                                            <?php else: ?>
                                                <span class="text-gray-500">Tanpa Kategori</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="py-2 border-b border-gray-100 text-left">
                                        <span class="font-medium text-gray-600">Vendor:</span>
                                        <div class="mt-1">
                                            <?php if (!empty($produk['nama_vendor'])): ?>
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-primary-100 text-primary-800"><?= htmlspecialchars($produk['nama_vendor'] ?? '') ?></span>
                                                <small class="text-gray-500 ml-1">(<?= htmlspecialchars($produk['kode_vendor'] ?? '') ?>)</small>
                                            <?php else: ?>
                                                <span class="text-gray-500">Tanpa Vendor</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="py-2 border-b border-gray-100 text-left">
                                        <span class="font-medium text-gray-600">Harga:</span>
                                        <div class="mt-1 text-green-600 font-semibold">
                                            <?= formatRupiah($produk['harga'] ?? 0) ?>
                                            <?php if (!empty($produk['satuan'])): ?>
                                                <small class="text-gray-500">/ <?= htmlspecialchars($produk['satuan'] ?? '') ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="py-2 border-b border-gray-100 text-left">
                                        <span class="font-medium text-gray-600">Status:</span>
                                        <div class="mt-1">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= ($produk['status'] ?? '') === 'aktif' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                                <?= getStatusText($produk['status'] ?? '') ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div class="space-y-4">
                                    <?php 
                        $stok_tersedia = $produk['stok_tersedia'] ?? 0;
                        $stok_minimal = $produk['stok_minimal'] ?? 0;
                        $stok_color = 'bg-green-100 text-green-800';
                        $stok_icon = 'fas fa-check-circle';
                        if ($stok_tersedia <= 0) {
                            $stok_color = 'bg-red-100 text-red-800';
                            $stok_icon = 'fas fa-times-circle';
                        } elseif ($stok_tersedia <= $stok_minimal) {
                            $stok_color = 'bg-yellow-100 text-yellow-800';
                            $stok_icon = 'fas fa-exclamation-triangle';
                        }
                    ?>
                                    <div class="py-2 border-b border-gray-100 text-left">
                                        <span class="font-medium text-gray-600">Stok:</span>
                                        <div class="mt-1">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $stok_color ?>">
                                                <i class="<?= $stok_icon ?> mr-1"></i>
                                                <?= number_format($stok_tersedia) ?> <?= htmlspecialchars($produk['satuan'] ?? 'unit') ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="py-2 border-b border-gray-100 text-left">
                                        <span class="font-medium text-gray-600">Stok Minimum:</span>
                                        <div class="mt-1 text-gray-900"><?= number_format($stok_minimal) ?> <?= htmlspecialchars($produk['satuan'] ?? 'unit') ?></div>
                                    </div>
                                    <div class="py-2 border-b border-gray-100 text-left">
                                        <span class="font-medium text-gray-600">Total Terjual:</span>
                                        <div class="mt-1 font-semibold text-gray-900"><?= number_format($stats['total_terjual'] ?? 0) ?> <?= htmlspecialchars($produk['satuan'] ?? 'unit') ?></div>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if (!empty($produk['deskripsi'])): ?>
                            <div class="mt-6 pt-6 border-t border-gray-200">
                                <h3 class="text-lg font-medium text-gray-900 mb-3">Deskripsi</h3>
                                <p class="text-gray-700 leading-relaxed"><?= nl2br(htmlspecialchars($produk['deskripsi'] ?? '')) ?></p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Statistics -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6 text-left">
                <div class="bg-white rounded-lg shadow-lg p-6 border border-gray-100 hover:shadow-xl transition-shadow duration-200">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                            <i class="fas fa-shopping-cart text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Total Transaksi</p>
                            <p class="text-2xl font-semibold text-gray-900"><?= number_format($stats['total_transaksi'] ?? 0) ?></p>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-lg shadow-lg p-6 border border-gray-100 hover:shadow-xl transition-shadow duration-200">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-green-100 text-green-600">
                            <i class="fas fa-box text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Unit Terjual</p>
                            <p class="text-2xl font-semibold text-gray-900"><?= number_format($stats['total_terjual'] ?? 0) ?></p>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-lg shadow-lg p-6 border border-gray-100 hover:shadow-xl transition-shadow duration-200">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-yellow-100 text-yellow-600">
                            <i class="fas fa-dollar-sign text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Total Pendapatan</p>
                            <p class="text-2xl font-semibold text-green-600"><?= formatRupiah($stats['total_pendapatan'] ?? 0) ?></p>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-lg shadow-lg p-6 border border-gray-100 hover:shadow-xl transition-shadow duration-200">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                            <i class="fas fa-chart-line text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Harga Rata-rata</p>
                            <p class="text-2xl font-semibold text-blue-600"><?= $stats['harga_rata_rata'] ? formatRupiah($stats['harga_rata_rata']) : '-' ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Additional Information -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6 text-left">
                <!-- Product Details -->
                <div class="bg-white rounded-lg shadow-lg p-6 border border-gray-100">
                    <div class="flex items-center mb-4">
                        <i class="fas fa-info-circle text-blue-600 mr-2"></i>
                        <h3 class="text-lg font-semibold text-gray-900">Informasi Detail</h3>
                    </div>
                    
                    <div class="space-y-4">
                        <?php if (!empty($produk['spesifikasi'])): ?>
                        <div>
                            <span class="font-medium text-gray-600">Spesifikasi:</span>
                            <div class="mt-1 text-gray-900"><?= nl2br(htmlspecialchars($produk['spesifikasi'] ?? '')) ?></div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($produk['garansi'])): ?>
                        <div class="py-2 border-b border-gray-100 text-left">
                            <span class="font-medium text-gray-600">Garansi:</span>
                            <div class="mt-1 text-gray-900"><?= htmlspecialchars($produk['garansi'] ?? '') ?></div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($produk['supplier'])): ?>
                        <div class="py-2 border-b border-gray-100 text-left">
                            <span class="font-medium text-gray-600">Supplier:</span>
                            <div class="mt-1 text-gray-900"><?= htmlspecialchars($produk['supplier'] ?? '') ?></div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="py-2 border-b border-gray-100">
                            <span class="font-medium text-gray-600">Dibuat:</span>
                            <div class="text-gray-900">
                                <?= date('d/m/Y H:i', strtotime($produk['created_at'] ?? '')) ?>
                                <?php if (!empty($produk['created_by_name'])): ?>
                                    <br><small class="text-gray-500">oleh <?= htmlspecialchars($produk['created_by_name'] ?? '') ?></small>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <?php if (!empty($produk['updated_at'])): ?>
                        <div class="py-2 border-b border-gray-100 text-left">
                            <span class="font-medium text-gray-600">Diperbarui:</span>
                            <div class="mt-1 text-gray-900"><?= date('d/m/Y H:i', strtotime($produk['updated_at'] ?? '')) ?></div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($stats['transaksi_pertama']): ?>
                        <div class="py-2 border-b border-gray-100 text-left">
                            <span class="font-medium text-gray-600">Transaksi Pertama:</span>
                            <div class="mt-1 text-gray-900"><?= date('d/m/Y', strtotime($stats['transaksi_pertama'])) ?></div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($stats['transaksi_terakhir']): ?>
                        <div class="py-2 border-b border-gray-100 text-left">
                            <span class="font-medium text-gray-600">Transaksi Terakhir:</span>
                            <div class="mt-1 text-gray-900"><?= date('d/m/Y', strtotime($stats['transaksi_terakhir'])) ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Stock History -->
                <div class="bg-white rounded-lg shadow-lg p-6 border border-gray-100">
                    <div class="flex items-center mb-4">
                        <i class="fas fa-history text-green-600 mr-2"></i>
                        <h3 class="text-lg font-semibold text-gray-900">Riwayat Stok Terbaru</h3>
                    </div>
                    
                    <?php if (empty($riwayat_stok)): ?>
                        <div class="text-center py-8">
                            <i class="fas fa-inbox text-4xl text-gray-400 mb-3"></i>
                            <p class="text-gray-500">Belum ada riwayat transaksi</p>
                        </div>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal</th>
                                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Jenis</th>
                                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Qty</th>
                                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Vendor/Desa</th>
                                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">No. Dokumen</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($riwayat_stok as $riwayat): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-900"><?= date('d/m/y', strtotime($riwayat['tanggal_transaksi'])) ?></td>
                                        <td class="px-3 py-4 whitespace-nowrap text-sm">
                                            <?php if ($riwayat['jenis'] == 'masuk'): ?>
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                    <i class="fas fa-arrow-down mr-1"></i> Masuk
                                                </span>
                                            <?php else: ?>
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                    <i class="fas fa-arrow-up mr-1"></i> Keluar
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-3 py-4 whitespace-nowrap text-sm">
                                            <?php if ($riwayat['jenis'] == 'masuk'): ?>
                                                <span class="text-green-600 font-medium">+<?= number_format($riwayat['quantity']) ?></span>
                                            <?php else: ?>
                                                <span class="text-red-600 font-medium">-<?= number_format($riwayat['quantity']) ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-900"><?= htmlspecialchars($riwayat['nama_desa']) ?></td>
                                        <td class="px-3 py-4 whitespace-nowrap text-sm">
                                            <?php if ($riwayat['jenis'] == 'masuk'): ?>
                                                <a href="pembelian-view.php?id=<?= $riwayat['id'] ?>" class="text-blue-600 hover:text-blue-800">
                                                    <?= htmlspecialchars($riwayat['nomor_invoice']) ?>
                                                </a>
                                            <?php else: ?>
                                                <a href="transaksi-view.php?id=<?= $riwayat['id'] ?>" class="text-blue-600 hover:text-blue-800">
                                                    <?= htmlspecialchars($riwayat['nomor_invoice']) ?>
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Transactions -->
            <?php if (!empty($transaksi_terbaru)): ?>
            <div class="bg-white rounded-lg shadow-lg mb-6 border border-gray-100">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900">Transaksi Terbaru (<?= count($transaksi_terbaru) ?> transaksi)</h3>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Invoice</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Desa</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Qty</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Harga</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subtotal</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sales</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($transaksi_terbaru as $transaksi): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?= date('d/m/Y', strtotime($transaksi['tanggal_transaksi'])) ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <a href="transaksi-view.php?id=<?= $transaksi['id'] ?>" class="text-blue-600 hover:text-blue-800">
                                        <?= htmlspecialchars($transaksi['nomor_invoice']) ?>
                                    </a>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?= htmlspecialchars($transaksi['nama_desa']) ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?= number_format($transaksi['quantity']) ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?= formatRupiah($transaksi['harga_satuan']) ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900"><?= formatRupiah($transaksi['subtotal']) ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?= htmlspecialchars($transaksi['sales_name']) ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <a href="transaksi-view.php?id=<?= $transaksi['id'] ?>" 
                                       class="inline-flex items-center px-3 py-1 border border-transparent text-xs font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700" 
                                       title="Lihat Detail">
                                        <i class="fas fa-eye mr-1"></i> Detail
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <!-- Action Buttons -->
            <div class="bg-white rounded-lg shadow-lg p-6 border border-gray-100 print:hidden mb-6">
                <div class="flex flex-wrap gap-4 justify-start">
                    <?php if (AuthStatic::hasRole(['admin']) || ($user['role'] === 'sales' && ($produk['created_by'] ?? 0) == $user['id'])): ?>
                    <a href="produk-edit.php?id=<?= $produk_id ?>" class="inline-flex items-center px-6 py-3 border border-transparent text-sm font-medium rounded-lg text-white bg-yellow-600 hover:bg-yellow-700 shadow-md hover:shadow-lg transition duration-200">
                        <i class="fas fa-edit mr-2"></i> Edit Produk
                    </a>
                    <?php endif; ?>
                    
                    <a href="produk.php" class="inline-flex items-center px-6 py-3 border border-gray-300 text-sm font-medium rounded-lg text-gray-700 bg-white hover:bg-gray-50 shadow-md hover:shadow-lg transition duration-200">
                        <i class="fas fa-arrow-left mr-2"></i> Kembali ke Daftar
                    </a>
                    
                    <a href="transaksi-add.php?produk_id=<?= $produk_id ?>" class="inline-flex items-center px-6 py-3 border border-transparent text-sm font-medium rounded-lg text-white bg-green-600 hover:bg-green-700 shadow-md hover:shadow-lg transition duration-200">
                        <i class="fas fa-shopping-cart mr-2"></i> Buat Transaksi
                    </a>
                    
                    <button onclick="window.print()" class="inline-flex items-center px-6 py-3 border border-transparent text-sm font-medium rounded-lg text-white bg-blue-600 hover:bg-blue-700 shadow-md hover:shadow-lg transition duration-200">
                        <i class="fas fa-print mr-2"></i> Cetak
                    </button>
                </div>
            </div>
    </div>

    <script>
        // Print styles
        window.addEventListener('beforeprint', function() {
            document.body.classList.add('printing');
        });
        
        window.addEventListener('afterprint', function() {
            document.body.classList.remove('printing');
        });
    </script>
    
    <style media="print">
        .navbar, .sidebar, .action-buttons, .breadcrumb {
            display: none !important;
        }
        
        .main-content {
            margin-left: 0 !important;
            padding: 0 !important;
        }
        
        .content-header {
            margin-bottom: 20px;
        }
        
        table {
            font-size: 12px;
        }
        
        button, .inline-flex {
            display: none;
        }
    </style>

<?php require_once 'layouts/footer.php'; ?>
