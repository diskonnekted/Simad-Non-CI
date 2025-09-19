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

$error = $_GET['error'] ?? '';
$success = $_GET['success'] ?? '';

// Parameter pencarian dan filter
$search = $_GET['search'] ?? '';
$kategori_filter = $_GET['kategori'] ?? '';
$status_filter = $_GET['status'] ?? '';
$tanggal_dari = $_GET['tanggal_dari'] ?? '';
$tanggal_sampai = $_GET['tanggal_sampai'] ?? '';
$sort = $_GET['sort'] ?? 'nama_produk';
$order = $_GET['order'] ?? 'ASC';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// Ambil daftar kategori untuk filter
$kategori_list = $db->select("
    SELECT id, nama_kategori 
    FROM kategori_produk 
    ORDER BY nama_kategori
");

// Build query untuk produk
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(p.nama_produk LIKE ? OR p.kode_produk LIKE ? OR p.deskripsi LIKE ?)";
    $search_param = "%{$search}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if (!empty($kategori_filter)) {
    $where_conditions[] = "p.kategori_id = ?";
    $params[] = $kategori_filter;
}

if (!empty($status_filter)) {
    $where_conditions[] = "p.status = ?";
    $params[] = $status_filter;
}

if (!empty($tanggal_dari)) {
    $where_conditions[] = "DATE(p.created_at) >= ?";
    $params[] = $tanggal_dari;
}

if (!empty($tanggal_sampai)) {
    $where_conditions[] = "DATE(p.created_at) <= ?";
    $params[] = $tanggal_sampai;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Validasi sort column dan mapping ke kolom database
$sort_mapping = [
    'nama_produk' => 'p.nama_produk',
    'kode_produk' => 'p.kode_produk', 
    'kategori_nama' => 'k.nama_kategori',
    'harga_satuan' => 'p.harga_satuan',
    'stok_tersedia' => 'p.stok_tersedia',
    'created_at' => 'p.created_at',
    'tanggal_masuk' => 'p.created_at'
];

if (!array_key_exists($sort, $sort_mapping)) {
    $sort = 'nama_produk';
}

$sort_column = $sort_mapping[$sort];
$order = strtoupper($order) === 'DESC' ? 'DESC' : 'ASC';

// Query untuk menghitung total
$count_query = "
    SELECT COUNT(*) as total
    FROM produk p
    LEFT JOIN kategori_produk k ON p.kategori_id = k.id
    {$where_clause}
";

$total_result = $db->select($count_query, $params);
$total_records = $total_result[0]['total'];
$total_pages = ceil($total_records / $limit);

// Query untuk mengambil data produk
$query = "
    SELECT p.*, k.nama_kategori as kategori_nama,
           (SELECT COALESCE(SUM(td.quantity), 0) FROM transaksi_detail td WHERE td.produk_id = p.id) as total_terjual
    FROM produk p
    LEFT JOIN kategori_produk k ON p.kategori_id = k.id
    {$where_clause}
    ORDER BY {$sort_column} {$order}
    LIMIT {$limit} OFFSET {$offset}
";

$produk_list = $db->select($query, $params);

// Statistik produk
$stats = $db->select("
    SELECT 
        COUNT(*) as total_produk,
        COUNT(CASE WHEN p.status = 'aktif' THEN 1 END) as produk_aktif,
        COUNT(CASE WHEN p.stok_tersedia <= 10 THEN 1 END) as stok_rendah,
        SUM(CASE WHEN p.status = 'aktif' THEN p.stok_tersedia * p.harga_satuan ELSE 0 END) as nilai_inventori
    FROM produk p
")[0];

// Handle delete action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    if (!AuthStatic::hasRole(['admin'])) {
        $error = 'access_denied';
    } else {
        $delete_id = intval($_POST['delete_id']);
        
        try {
            // Cek apakah produk pernah digunakan dalam transaksi
            $usage_check = $db->select(
                "SELECT COUNT(*) as count FROM transaksi_detail WHERE produk_id = ?",
                [$delete_id]
            );
            
            if ($usage_check[0]['count'] > 0) {
                // Jika pernah digunakan, ubah status menjadi nonaktif
                $db->execute(
                    "UPDATE produk SET status = 'nonaktif', updated_at = NOW() WHERE id = ?",
                    [$delete_id]
                );
                $success = 'deactivated';
            } else {
                // Jika belum pernah digunakan, hapus permanen
                $db->execute("DELETE FROM produk WHERE id = ?", [$delete_id]);
                $success = 'deleted';
            }
            
            header("Location: produk.php?success={$success}");
            exit;
        } catch (Exception $e) {
            $error = 'delete_failed';
        }
    }
}

// Helper functions
function getStatusBadge($status) {
    $badges = [
        'aktif' => 'success',
        'nonaktif' => 'warning',
        'deleted' => 'danger'
    ];
    return $badges[$status] ?? 'default';
}

function getStatusText($status) {
    $texts = [
        'aktif' => 'Aktif',
        'nonaktif' => 'Nonaktif',
        'deleted' => 'Dihapus'
    ];
    return $texts[$status] ?? $status;
}

function getStokStatus($stok) {
    if ($stok <= 0) return ['danger', 'Habis'];
    if ($stok <= 10) return ['warning', 'Rendah'];
    if ($stok <= 50) return ['info', 'Sedang'];
    return ['success', 'Aman'];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Produk - Sistem Manajemen Desa</title>
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
    <style>
        /* Sticky column styling */
        .sticky-action-column {
            position: sticky;
            right: 0;
            z-index: 10;
            box-shadow: -2px 0 4px rgba(0, 0, 0, 0.1);
        }
        
        /* Ensure table container allows horizontal scroll */
        .table-container {
            overflow-x: auto;
            position: relative;
        }
        
        /* Smooth scrolling */
        .table-container::-webkit-scrollbar {
            height: 8px;
        }
        
        .table-container::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }
        
        .table-container::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 4px;
        }
        
        .table-container::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }
    </style>
</head>
<body class="bg-gray-50 font-sans">
    <!-- Loading Overlay -->
    <div id="loading" class="fixed inset-0 bg-white bg-opacity-75 flex items-center justify-center z-50 hidden">
        <div class="text-center">
            <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-primary-600 mx-auto"></div>
            <p class="mt-4 text-gray-600">Memuat...</p>
        </div>
    </div>

    <?php include 'layouts/header.php'; ?>
    
    <!-- Page Header -->
    <div class="bg-white shadow-sm border-b border-gray-200 mb-6">
        <div class="px-6 py-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 flex items-center">
                        <i class="fa fa-cube text-primary-600 mr-3"></i>
                        Manajemen Produk
                    </h1>
                    <p class="text-sm text-gray-600 mt-1">Kelola barang IT & ATK dengan mudah dan efisien</p>
                </div>
                <nav class="mt-4 sm:mt-0 text-sm text-gray-500">
                    <a href="index.php" class="hover:text-primary-600">Dashboard</a>
                    <span class="mx-2">/</span>
                    <span class="text-gray-900">Manajemen Produk</span>
                </nav>
            </div>
        </div>
    </div>

    <!-- Main Container -->
    <div class="max-w-7xl ml-0 px-4 sm:px-6 lg:px-8 py-8">
            <!-- Alert Messages -->
            <?php if ($error): ?>
                <div class="mb-6" data-alert>
                    <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                        <div class="flex items-center">
                            <i class="fa fa-exclamation-triangle text-red-600 mr-3"></i>
                            <div class="text-red-800">
                                <?php if ($error === 'access_denied'): ?>
                                    Anda tidak memiliki akses untuk menghapus produk.
                                <?php elseif ($error === 'delete_failed'): ?>
                                    Gagal menghapus produk. Silakan coba lagi.
                                <?php else: ?>
                                    Terjadi kesalahan: <?= htmlspecialchars($error) ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="mb-6" data-alert>
                    <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                        <div class="flex items-center">
                            <i class="fa fa-check text-green-600 mr-3"></i>
                            <div class="text-green-800">
                                <?php if ($success === 'deleted'): ?>
                                    Produk berhasil dihapus.
                                <?php elseif ($success === 'deactivated'): ?>
                                    Produk berhasil dinonaktifkan karena pernah digunakan dalam transaksi.
                                <?php elseif ($success === 'created'): ?>
                                    Produk baru berhasil ditambahkan.
                                <?php elseif ($success === 'updated'): ?>
                                    Data produk berhasil diperbarui.
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                                <i class="fa fa-cube text-blue-600 text-xl"></i>
                            </div>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Total Produk</p>
                            <p class="text-2xl font-bold text-gray-900"><?= number_format($stats['total_produk']) ?></p>
                        </div>
                    </div>
                    <div class="mt-4 pt-4 border-t border-gray-100">
                        <div class="flex items-center text-sm text-gray-500">
                            <i class="fa fa-refresh mr-2"></i>
                            Update Now
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                                <i class="fa fa-check-circle text-green-600 text-xl"></i>
                            </div>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Produk Aktif</p>
                            <p class="text-2xl font-bold text-gray-900"><?= number_format($stats['produk_aktif']) ?></p>
                        </div>
                    </div>
                    <div class="mt-4 pt-4 border-t border-gray-100">
                        <div class="flex items-center text-sm text-gray-500">
                            <i class="fa fa-calendar-o mr-2"></i>
                            Last 24 Hours
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center">
                                <i class="fa fa-exclamation-triangle text-red-600 text-xl"></i>
                            </div>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Stok Rendah</p>
                            <p class="text-2xl font-bold text-gray-900"><?= number_format($stats['stok_rendah']) ?></p>
                        </div>
                    </div>
                    <div class="mt-4 pt-4 border-t border-gray-100">
                        <div class="flex items-center text-sm text-gray-500">
                            <i class="fa fa-clock-o mr-2"></i>
                            In the last hour
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="w-12 h-12 bg-indigo-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-dollar-sign text-indigo-600 text-xl"></i>
                            </div>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Nilai Inventori</p>
                            <p class="text-lg font-bold text-gray-900">Rp <?= number_format($stats['nilai_inventori'] ?? 0, 0, ',', '.') ?></p>
                        </div>
                    </div>
                    <div class="mt-4 pt-4 border-t border-gray-100">
                        <div class="flex items-center text-sm text-gray-500">
                            <i class="fa fa-refresh mr-2"></i>
                            Update Now
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filter Section -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-6">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900">Filter Produk</h3>
                </div>
                <div class="p-6">
                    <form method="GET" class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-8 gap-4">
                            <div class="lg:col-span-2">
                                <label for="search" class="block text-sm font-medium text-gray-700 mb-2">Pencarian</label>
                                <input type="text" id="search" name="search" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500" 
                                       value="<?= htmlspecialchars($search) ?>" 
                                       placeholder="Nama produk, kode, atau deskripsi">
                            </div>
                            
                            <div>
                                <label for="kategori" class="block text-sm font-medium text-gray-700 mb-2">Kategori</label>
                                <select id="kategori" name="kategori" 
                                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                                    <option value="">Semua Kategori</option>
                                    <?php foreach ($kategori_list as $kategori): ?>
                                    <option value="<?= $kategori['id'] ?>" <?= $kategori_filter == $kategori['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($kategori['nama_kategori']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div>
                                <label for="status" class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                                <select id="status" name="status" 
                                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                                    <option value="aktif" <?= $status_filter === 'aktif' ? 'selected' : '' ?>>Aktif</option>
                                    <option value="nonaktif" <?= $status_filter === 'nonaktif' ? 'selected' : '' ?>>Nonaktif</option>
                                    <option value="" <?= $status_filter === '' ? 'selected' : '' ?>>Semua Status</option>
                                </select>
                            </div>
                            
                            <div>
                                <label for="tanggal_dari" class="block text-sm font-medium text-gray-700 mb-2">Tanggal Dari</label>
                                <input type="date" id="tanggal_dari" name="tanggal_dari" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500" 
                                       value="<?= htmlspecialchars($tanggal_dari) ?>">
                            </div>
                            
                            <div>
                                <label for="tanggal_sampai" class="block text-sm font-medium text-gray-700 mb-2">Tanggal Sampai</label>
                                <input type="date" id="tanggal_sampai" name="tanggal_sampai" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500" 
                                       value="<?= htmlspecialchars($tanggal_sampai) ?>">
                            </div>
                            
                            <div>
                                <label for="sort" class="block text-sm font-medium text-gray-700 mb-2">Urutkan</label>
                                <select id="sort" name="sort" 
                                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                                    <option value="nama_produk" <?= $sort === 'nama_produk' ? 'selected' : '' ?>>Nama Produk</option>
                                    <option value="kode_produk" <?= $sort === 'kode_produk' ? 'selected' : '' ?>>Kode Produk</option>
                                    <option value="kategori_nama" <?= $sort === 'kategori_nama' ? 'selected' : '' ?>>Kategori</option>
                                    <option value="harga_satuan" <?= $sort === 'harga_satuan' ? 'selected' : '' ?>>Harga</option>
                                    <option value="stok_tersedia" <?= $sort === 'stok_tersedia' ? 'selected' : '' ?>>Stok</option>
                                    <option value="tanggal_masuk" <?= $sort === 'tanggal_masuk' ? 'selected' : '' ?>>Tanggal Masuk</option>
                                    <option value="created_at" <?= $sort === 'created_at' ? 'selected' : '' ?>>Tanggal Dibuat</option>
                                </select>
                            </div>
                            
                            <div>
                                <label for="order" class="block text-sm font-medium text-gray-700 mb-2">Urutan</label>
                                <select name="order" 
                                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                                    <option value="ASC" <?= $order === 'ASC' ? 'selected' : '' ?>>A-Z / Rendah-Tinggi</option>
                                    <option value="DESC" <?= $order === 'DESC' ? 'selected' : '' ?>>Z-A / Tinggi-Rendah</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="flex flex-wrap gap-3 pt-4">
                            <button type="submit" class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 transition-colors">
                                <i class="fa fa-search mr-2"></i>Filter
                            </button>
                            <a href="produk.php" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition-colors">
                                <i class="fa fa-refresh mr-2"></i>Reset
                            </a>
                            <div class="flex gap-3 ml-auto">
                                <a href="kategori.php" class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 focus:ring-2 focus:ring-purple-500 focus:ring-offset-2 transition-colors">
                                    <i class="fa fa-tags mr-2"></i>Kelola Kategori
                                </a>
                                <a href="produk-add.php" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition-colors">
                                    <i class="fa fa-plus mr-2"></i>Tambah Produk
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

                <!-- Products Table -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                    <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                        <h3 class="text-lg font-semibold text-gray-900">Daftar Produk</h3>
                        <span class="text-sm text-gray-500">Menampilkan <?= $offset + 1 ?>-<?= min($offset + $limit, $total_records) ?> dari <?= $total_records ?> produk</span>
                    </div>
                    
                    <?php if (empty($produk_list)): ?>
                        <div class="text-center py-12">
                            <i class="fa fa-cube text-6xl text-gray-400 mb-4"></i>
                            <h4 class="text-xl font-semibold text-gray-600 mb-2">Tidak ada produk ditemukan</h4>
                            <p class="text-gray-500 mb-6">Silakan ubah filter pencarian atau tambah produk baru.</p>
                            <a href="produk-add.php" class="inline-flex items-center px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 transition-colors">
                                <i class="fa fa-plus mr-2"></i> Tambah Produk Pertama
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="overflow-x-auto table-container">
                            <table class="min-w-full divide-y divide-gray-200" style="min-width: 1000px;">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" style="width: 80px;">Gambar</th>
                                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" style="width: 100px;">Kode</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" style="width: 200px;">Nama Produk</th>
                                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" style="width: 120px;">Kategori</th>
                                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" style="width: 100px;">Harga</th>
                                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" style="width: 80px;">Stok</th>
                                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" style="width: 110px;">Tanggal</th>
                                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" style="width: 90px;">Status</th>
                                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" style="width: 80px;">Terjual</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider sticky-action-column bg-gray-50 border-l border-gray-200" style="min-width: 120px;">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($produk_list as $produk): ?>
                                    <?php 
                                        $stok_status = getStokStatus($produk['stok_tersedia']);
                                    ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-3 py-3 whitespace-nowrap">
                                            <?php if ($produk['gambar']): ?>
                                                <img src="uploads/produk/<?= htmlspecialchars($produk['gambar']) ?>" 
                                                     class="w-10 h-10 rounded-lg object-cover" alt="Gambar Produk">
                                            <?php else: ?>
                                                <div class="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center">
                                                    <i class="fa fa-image text-gray-400 text-xs"></i>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-3 py-3 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($produk['kode_produk']) ?></div>
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($produk['nama_produk']) ?></div>
                                            <?php if ($produk['deskripsi']): ?>
                                                <div class="text-xs text-gray-500"><?= htmlspecialchars(substr($produk['deskripsi'], 0, 30)) ?>...</div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-3 py-3 whitespace-nowrap">
                                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                                                <?= htmlspecialchars(substr($produk['kategori_nama'] ?? 'Tanpa Kategori', 0, 10)) ?>
                                            </span>
                                        </td>
                                        <td class="px-3 py-3 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-900">Rp <?= number_format($produk['harga_satuan'], 0, ',', '.') ?></div>
                                            <?php if ($produk['satuan']): ?>
                                                <div class="text-xs text-gray-500"><?= htmlspecialchars($produk['satuan']) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-3 py-3 whitespace-nowrap">
                                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-<?= $stok_status[0] ?>-100 text-<?= $stok_status[0] ?>-800">
                                                <?= number_format($produk['stok_tersedia']) ?>
                                            </span>
                                            <div class="text-xs text-gray-500"><?= $stok_status[1] ?></div>
                                        </td>
                                        <td class="px-3 py-3 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-900"><?= date('d/m/y', strtotime($produk['created_at'])) ?></div>
                                            <div class="text-xs text-gray-500"><?= date('H:i', strtotime($produk['created_at'])) ?></div>
                                        </td>
                                        <td class="px-3 py-3 whitespace-nowrap">
                                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-<?= getStatusBadge($produk['status']) === 'success' ? 'green' : (getStatusBadge($produk['status']) === 'warning' ? 'yellow' : 'red') ?>-100 text-<?= getStatusBadge($produk['status']) === 'success' ? 'green' : (getStatusBadge($produk['status']) === 'warning' ? 'yellow' : 'red') ?>-800">
                                                <?= getStatusText($produk['status']) ?>
                                            </span>
                                        </td>
                                        <td class="px-3 py-3 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-900"><?= number_format($produk['total_terjual']) ?></div>
                                            <div class="text-xs text-gray-500">unit</div>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm font-medium sticky-action-column bg-white border-l border-gray-200" style="min-width: 120px;">
                                            <div class="flex space-x-3 items-center">
                                                <a href="produk-view.php?id=<?= $produk['id'] ?>" 
                                                   class="inline-flex items-center justify-center w-8 h-8 text-blue-600 hover:text-blue-900 hover:bg-blue-50 rounded-full transition-colors" 
                                                   title="Lihat Detail">
                                                    <i class="fa fa-eye"></i>
                                                </a>
                                                
                                                <?php if (AuthStatic::hasRole(['admin']) || $produk['status'] === 'aktif'): ?>
                                                <a href="produk-edit.php?id=<?= $produk['id'] ?>" 
                                                   class="inline-flex items-center justify-center w-8 h-8 text-yellow-600 hover:text-yellow-900 hover:bg-yellow-50 rounded-full transition-colors" 
                                                   title="Edit">
                                                    <i class="fa fa-edit"></i>
                                                </a>
                                                <?php endif; ?>
                                                
                                                <?php if (AuthStatic::hasRole(['admin'])): ?>
                                                <button type="button" 
                                                        class="inline-flex items-center justify-center w-8 h-8 text-red-600 hover:text-red-900 hover:bg-red-50 rounded-full transition-colors" 
                                                        onclick="confirmDelete(<?= $produk['id'] ?>, '<?= htmlspecialchars($produk['nama_produk']) ?>')" 
                                                        title="Hapus">
                                                    <i class="fa fa-trash"></i>
                                                </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                        <div class="px-6 py-4 border-t border-gray-200">
                            <div class="flex items-center justify-between">
                                <div class="flex-1 flex justify-between sm:hidden">
                                    <?php if ($page > 1): ?>
                                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                        Previous
                                    </a>
                                    <?php endif; ?>
                                    <?php if ($page < $total_pages): ?>
                                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                        Next
                                    </a>
                                    <?php endif; ?>
                                </div>
                                <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                                    <div>
                                        <p class="text-sm text-gray-700">
                                            Showing <span class="font-medium"><?= $offset + 1 ?></span> to <span class="font-medium"><?= min($offset + $limit, $total_records) ?></span> of <span class="font-medium"><?= $total_records ?></span> results
                                        </p>
                                    </div>
                                    <div>
                                        <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                                            <?php if ($page > 1): ?>
                                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                                <span class="sr-only">Previous</span>
                                                <i class="fa fa-chevron-left"></i>
                                            </a>
                                            <?php endif; ?>
                                            
                                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" class="relative inline-flex items-center px-4 py-2 border text-sm font-medium <?= $i === $page ? 'z-10 bg-primary-50 border-primary-500 text-primary-600' : 'border-gray-300 bg-white text-gray-500 hover:bg-gray-50' ?>">
                                                <?= $i ?>
                                            </a>
                                            <?php endfor; ?>
                                            
                                            <?php if ($page < $total_pages): ?>
                                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                                <span class="sr-only">Next</span>
                                                <i class="fa fa-chevron-right"></i>
                                            </a>
                                            <?php endif; ?>
                                        </nav>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
    </div>

     <?php include 'layouts/footer.php'; ?>

     <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Konfirmasi Hapus</h3>
                    <button type="button" class="text-gray-400 hover:text-gray-600" onclick="closeDeleteModal()">
                        <i class="fa fa-times"></i>
                    </button>
                </div>
                <div class="mt-2 px-7 py-3">
                    <p class="text-sm text-gray-500">Apakah Anda yakin ingin menghapus produk <strong id="delete-product-name"></strong>?</p>
                    <div class="mt-4 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                        <div class="flex">
                            <i class="fa fa-exclamation-triangle text-yellow-600 mr-2"></i>
                            <p class="text-sm text-yellow-800">Jika produk pernah digunakan dalam transaksi, produk akan dinonaktifkan. Jika belum pernah digunakan, produk akan dihapus permanen.</p>
                        </div>
                    </div>
                </div>
                <div class="flex items-center px-4 py-3 space-x-2">
                    <form method="POST" id="deleteForm" class="flex space-x-2">
                        <input type="hidden" name="delete_id" id="delete_id">
                        <button type="button" onclick="closeDeleteModal()" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400 focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition-colors">
                            Batal
                        </button>
                        <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition-colors">
                            Ya, Hapus
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        function confirmDelete(id, name) {
            document.getElementById('delete_id').value = id;
            document.getElementById('delete-product-name').textContent = name;
            document.getElementById('deleteModal').classList.remove('hidden');
        }
        
        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.add('hidden');
        }
        
        // Auto-submit form on filter change
        document.addEventListener('DOMContentLoaded', function() {
            const filterElements = document.querySelectorAll('#kategori, #status, #sort');
            filterElements.forEach(function(element) {
                element.addEventListener('change', function() {
                    this.closest('form').submit();
                });
            });
            
            // Auto-hide alerts after 5 seconds
            const alerts = document.querySelectorAll('[data-alert]');
            alerts.forEach(function(alert) {
                setTimeout(function() {
                    alert.style.opacity = '0';
                    setTimeout(function() {
                        alert.remove();
                    }, 300);
                }, 5000);
            });
        });
    </script>
<?php
require_once 'layouts/footer.php';
?>
