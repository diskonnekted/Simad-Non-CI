<?php
require_once 'config/database.php';
require_once 'config/auth.php';

// Cek autentikasi dan role
if (!AuthStatic::isLoggedIn()) {
    header('Location: login.php');
    exit;
}

if (!AuthStatic::hasRole(['admin', 'sales'])) {
    header('Location: index.php?error=access_denied');
    exit;
}

$user = AuthStatic::getCurrentUser();
/** @var Database $db */
$db = getDatabase();

$kategori_id = intval($_GET['id'] ?? 0);

if (!$kategori_id) {
    header('Location: kategori.php?error=invalid_id');
    exit;
}

// Ambil data kategori
$kategori = $db->select(
    "SELECT * FROM kategori_produk WHERE id = ?",
    [$kategori_id]
);

if (empty($kategori)) {
    header('Location: kategori.php?error=not_found');
    exit;
}

$kategori = $kategori[0];

// Ambil statistik produk dalam kategori ini
$produk_stats = $db->select(
    "SELECT 
        COUNT(*) as total_produk,
        COUNT(CASE WHEN status = 'aktif' THEN 1 END) as produk_aktif,
        COUNT(CASE WHEN status = 'nonaktif' THEN 1 END) as produk_nonaktif,
        SUM(CASE WHEN status = 'aktif' THEN stok ELSE 0 END) as total_stok
     FROM produk 
     WHERE kategori_id = ?",
    [$kategori_id]
)[0];

// Ambil daftar produk dalam kategori ini (5 terbaru)
$produk_list = $db->select(
    "SELECT id, nama_produk, harga, stok, status, created_at 
     FROM produk 
     WHERE kategori_id = ? 
     ORDER BY created_at DESC 
     LIMIT 5",
    [$kategori_id]
);

// Helper function untuk format harga
function formatRupiah($angka) {
    return 'Rp ' . number_format($angka, 0, ',', '.');
}

// Helper function untuk badge status
function getStatusBadge($status) {
    switch ($status) {
        case 'aktif':
            return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Aktif</span>';
        case 'nonaktif':
            return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">Nonaktif</span>';
        default:
            return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">Unknown</span>';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Kategori: <?= htmlspecialchars($kategori['nama_kategori']) ?> - Sistem Manajemen Desa</title>
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
<body class="bg-gray-50 font-sans">
    <?php include 'layouts/header.php'; ?>
    
    <!-- Page Header -->
    <div class="bg-white shadow-sm border-b border-gray-200 mb-6">
        <div class="px-6 py-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 flex items-center">
                        <i class="fa fa-tag text-primary-600 mr-3"></i>
                        <?= htmlspecialchars($kategori['nama_kategori']) ?>
                    </h1>
                    <p class="text-sm text-gray-600 mt-1">Detail informasi kategori produk</p>
                </div>
                <nav class="mt-4 sm:mt-0 text-sm text-gray-500">
                    <a href="index.php" class="hover:text-primary-600">Dashboard</a>
                    <span class="mx-2">/</span>
                    <a href="produk.php" class="hover:text-primary-600">Produk</a>
                    <span class="mx-2">/</span>
                    <a href="kategori.php" class="hover:text-primary-600">Kategori</a>
                    <span class="mx-2">/</span>
                    <span class="text-gray-900">Detail</span>
                </nav>
            </div>
        </div>
    </div>

    <!-- Main Container -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Action Buttons -->
        <div class="mb-6">
            <div class="flex flex-wrap gap-3">
                <a href="kategori.php" 
                   class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 focus:ring-2 focus:ring-primary-500 focus:ring-offset-2">
                    <i class="fa fa-arrow-left mr-2"></i>Kembali ke Daftar
                </a>
                <a href="kategori-edit.php?id=<?= $kategori['id'] ?>" 
                   class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700 focus:ring-2 focus:ring-primary-500 focus:ring-offset-2">
                    <i class="fa fa-edit mr-2"></i>Edit Kategori
                </a>
                <a href="produk-add.php?kategori=<?= $kategori['id'] ?>" 
                   class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-green-600 rounded-lg hover:bg-green-700 focus:ring-2 focus:ring-green-500 focus:ring-offset-2">
                    <i class="fa fa-plus mr-2"></i>Tambah Produk
                </a>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Main Content -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Category Info -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                            <i class="fa fa-info-circle text-blue-600 mr-2"></i>
                            Informasi Kategori
                        </h3>
                    </div>
                    <div class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <h4 class="text-sm font-medium text-gray-900 mb-2">Nama Kategori</h4>
                                <p class="text-lg text-gray-800"><?= htmlspecialchars($kategori['nama_kategori']) ?></p>
                            </div>
                            
                            <div>
                                <h4 class="text-sm font-medium text-gray-900 mb-2">Tanggal Dibuat</h4>
                                <p class="text-lg text-gray-800"><?= date('d/m/Y H:i', strtotime($kategori['created_at'])) ?></p>
                            </div>
                        </div>
                        
                        <?php if ($kategori['deskripsi']): ?>
                        <div class="mt-6">
                            <h4 class="text-sm font-medium text-gray-900 mb-2">Deskripsi</h4>
                            <p class="text-gray-700 leading-relaxed"><?= nl2br(htmlspecialchars($kategori['deskripsi'])) ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Products List -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <div class="flex items-center justify-between">
                            <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                                <i class="fa fa-box text-green-600 mr-2"></i>
                                Produk Terbaru
                            </h3>
                            <?php if ($produk_stats['total_produk'] > 5): ?>
                            <a href="produk.php?kategori=<?= $kategori['id'] ?>" 
                               class="text-sm text-primary-600 hover:text-primary-700">
                                Lihat Semua (<?= $produk_stats['total_produk'] ?>)
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="p-6">
                        <?php if (empty($produk_list)): ?>
                            <div class="text-center py-8">
                                <i class="fa fa-box-open text-gray-400 text-4xl mb-4"></i>
                                <p class="text-gray-500">Belum ada produk dalam kategori ini</p>
                                <a href="produk-add.php?kategori=<?= $kategori['id'] ?>" 
                                   class="inline-flex items-center mt-4 px-4 py-2 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700">
                                    <i class="fa fa-plus mr-2"></i>Tambah Produk Pertama
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="space-y-4">
                                <?php foreach ($produk_list as $produk): ?>
                                <div class="border border-gray-200 rounded-lg p-4 hover:bg-gray-50 transition-colors">
                                    <div class="flex items-center justify-between">
                                        <div class="flex-1">
                                            <div class="flex items-center">
                                                <h4 class="text-sm font-medium text-gray-900">
                                                    <a href="produk-view.php?id=<?= $produk['id'] ?>" class="hover:text-primary-600">
                                                        <?= htmlspecialchars($produk['nama_produk']) ?>
                                                    </a>
                                                </h4>
                                                <div class="ml-3">
                                                    <?= getStatusBadge($produk['status']) ?>
                                                </div>
                                            </div>
                                            <div class="mt-1 flex items-center text-sm text-gray-500">
                                                <span class="mr-4">
                                                    <i class="fa fa-tag mr-1"></i>
                                                    <?= formatRupiah($produk['harga']) ?>
                                                </span>
                                                <span class="mr-4">
                                                    <i class="fa fa-boxes mr-1"></i>
                                                    Stok: <?= number_format($produk['stok']) ?>
                                                </span>
                                                <span>
                                                    <i class="fa fa-calendar mr-1"></i>
                                                    <?= date('d/m/Y', strtotime($produk['created_at'])) ?>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="flex items-center space-x-2">
                                            <a href="produk-view.php?id=<?= $produk['id'] ?>" 
                                               class="text-blue-600 hover:text-blue-700" title="Lihat Detail">
                                                <i class="fa fa-eye"></i>
                                            </a>
                                            <a href="produk-edit.php?id=<?= $produk['id'] ?>" 
                                               class="text-green-600 hover:text-green-700" title="Edit">
                                                <i class="fa fa-edit"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="lg:col-span-1 space-y-6">
                <!-- Statistics -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                            <i class="fa fa-chart-bar text-purple-600 mr-2"></i>
                            Statistik
                        </h3>
                    </div>
                    <div class="p-6">
                        <div class="space-y-4">
                            <div class="flex items-center justify-between p-3 bg-blue-50 rounded-lg">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0">
                                        <i class="fa fa-box text-blue-600"></i>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm font-medium text-blue-900">Total Produk</p>
                                    </div>
                                </div>
                                <div class="text-lg font-bold text-blue-900">
                                    <?= number_format($produk_stats['total_produk']) ?>
                                </div>
                            </div>
                            
                            <div class="flex items-center justify-between p-3 bg-green-50 rounded-lg">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0">
                                        <i class="fa fa-check-circle text-green-600"></i>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm font-medium text-green-900">Produk Aktif</p>
                                    </div>
                                </div>
                                <div class="text-lg font-bold text-green-900">
                                    <?= number_format($produk_stats['produk_aktif']) ?>
                                </div>
                            </div>
                            
                            <div class="flex items-center justify-between p-3 bg-red-50 rounded-lg">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0">
                                        <i class="fa fa-times-circle text-red-600"></i>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm font-medium text-red-900">Produk Nonaktif</p>
                                    </div>
                                </div>
                                <div class="text-lg font-bold text-red-900">
                                    <?= number_format($produk_stats['produk_nonaktif']) ?>
                                </div>
                            </div>
                            
                            <div class="flex items-center justify-between p-3 bg-yellow-50 rounded-lg">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0">
                                        <i class="fa fa-boxes text-yellow-600"></i>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm font-medium text-yellow-900">Total Stok</p>
                                    </div>
                                </div>
                                <div class="text-lg font-bold text-yellow-900">
                                    <?= number_format($produk_stats['total_stok']) ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                            <i class="fa fa-bolt text-yellow-600 mr-2"></i>
                            Aksi Cepat
                        </h3>
                    </div>
                    <div class="p-6">
                        <div class="space-y-3">
                            <a href="produk-add.php?kategori=<?= $kategori['id'] ?>" 
                               class="w-full flex items-center px-4 py-3 text-sm font-medium text-white bg-green-600 rounded-lg hover:bg-green-700 transition-colors">
                                <i class="fa fa-plus mr-3"></i>
                                Tambah Produk Baru
                            </a>
                            
                            <a href="produk.php?kategori=<?= $kategori['id'] ?>" 
                               class="w-full flex items-center px-4 py-3 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors">
                                <i class="fa fa-list mr-3"></i>
                                Lihat Semua Produk
                            </a>
                            
                            <a href="kategori-edit.php?id=<?= $kategori['id'] ?>" 
                               class="w-full flex items-center px-4 py-3 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors">
                                <i class="fa fa-edit mr-3"></i>
                                Edit Kategori
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Category Info Card -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                            <i class="fa fa-info text-blue-600 mr-2"></i>
                            Informasi
                        </h3>
                    </div>
                    <div class="p-6">
                        <div class="space-y-4">
                            <div class="flex items-start">
                                <i class="fa fa-shield-alt text-blue-500 mt-1 mr-3"></i>
                                <div>
                                    <h4 class="text-sm font-medium text-gray-900 mb-1">Perlindungan Data</h4>
                                    <p class="text-sm text-gray-600">Kategori yang memiliki produk tidak dapat dihapus untuk menjaga integritas data.</p>
                                </div>
                            </div>
                            
                            <div class="flex items-start">
                                <i class="fa fa-sync-alt text-green-500 mt-1 mr-3"></i>
                                <div>
                                    <h4 class="text-sm font-medium text-gray-900 mb-1">Sinkronisasi</h4>
                                    <p class="text-sm text-gray-600">Perubahan pada kategori akan otomatis mempengaruhi semua produk terkait.</p>
                                </div>
                            </div>
                            
                            <div class="flex items-start">
                                <i class="fa fa-history text-purple-500 mt-1 mr-3"></i>
                                <div>
                                    <h4 class="text-sm font-medium text-gray-900 mb-1">Riwayat</h4>
                                    <p class="text-sm text-gray-600">Semua perubahan pada kategori akan tercatat dalam sistem.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include 'layouts/footer.php'; ?>
</body>
</html>