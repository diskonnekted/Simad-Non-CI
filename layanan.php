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
/** @var Database $db */

// Function to format currency
function formatRupiah($angka) {
    return 'Rp ' . number_format($angka, 0, ',', '.');
}

// Handle delete action
if (isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['id'])) {
    if (AuthStatic::hasRole(['admin'])) {
        $layanan_id = intval($_POST['id']);
        
        // Cek apakah layanan pernah digunakan dalam transaksi
        $used_in_transaction = $db->select(
            "SELECT COUNT(*) as count FROM transaksi_detail WHERE layanan_id = ?",
            [$layanan_id]
        );
        
        if ($used_in_transaction[0]['count'] > 0) {
            // Jika pernah digunakan, ubah status menjadi nonaktif
            $db->update('layanan', ['status' => 'nonaktif'], ['id' => $layanan_id]);
            $message = 'Layanan berhasil dinonaktifkan karena pernah digunakan dalam transaksi.';
        } else {
            // Jika belum pernah digunakan, hapus permanen
            $db->update('layanan', ['status' => 'deleted'], ['id' => $layanan_id]);
            $message = 'Layanan berhasil dihapus.';
        }
        
        header('Location: layanan.php?success=' . urlencode($message));
        exit;
    }
}

// Get filter parameters
$search = $_GET['search'] ?? '';
$kategori_filter = $_GET['kategori'] ?? '';
$status_filter = $_GET['status'] ?? 'aktif';
$sort = $_GET['sort'] ?? 'nama_layanan';
$order = $_GET['order'] ?? 'ASC';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// Build WHERE clause
$where_conditions = ["l.status != 'deleted'"];
$params = [];

if ($search) {
    $where_conditions[] = "(l.nama_layanan LIKE ? OR l.kode_layanan LIKE ? OR l.deskripsi LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if ($kategori_filter) {
    $where_conditions[] = "l.jenis_layanan = ?";
    $params[] = $kategori_filter;
}

if ($status_filter && $status_filter !== 'semua') {
    $where_conditions[] = "l.status = ?";
    $params[] = $status_filter;
}

$where_clause = implode(' AND ', $where_conditions);

// Get total count
$total_query = "SELECT COUNT(*) as total FROM layanan l WHERE $where_clause";
$total_result = $db->select($total_query, $params);
$total_records = $total_result[0]['total'];
$total_pages = ceil($total_records / $limit);

// Get layanan data with statistics
$layanan_query = "
    SELECT l.id,
           l.kode_layanan,
           l.nama_layanan,
           l.jenis_layanan,
           l.harga,
           l.durasi_hari as durasi,
           l.deskripsi,
           l.status,
           l.created_at,
           l.updated_at,
           COALESCE(stats.total_transaksi, 0) as total_transaksi,
           COALESCE(stats.total_pendapatan, 0) as total_pendapatan
    FROM layanan l
    LEFT JOIN (
        SELECT td.layanan_id,
               COUNT(DISTINCT td.transaksi_id) as total_transaksi,
               SUM(td.subtotal) as total_pendapatan
        FROM transaksi_detail td
        JOIN transaksi t ON td.transaksi_id = t.id
        WHERE td.layanan_id IS NOT NULL
        GROUP BY td.layanan_id
    ) stats ON l.id = stats.layanan_id
    WHERE $where_clause
    ORDER BY l.$sort $order
    LIMIT $limit OFFSET $offset
";

$layanan_list = $db->select($layanan_query, $params);

// Get categories for filter (using jenis_layanan instead)
$categories = $db->select("SELECT DISTINCT jenis_layanan as id, jenis_layanan as nama_kategori FROM layanan WHERE status = 'aktif' ORDER BY jenis_layanan");

// Get statistics
$stats = $db->select("
    SELECT 
        COUNT(*) as total_layanan,
        SUM(CASE WHEN status = 'aktif' THEN 1 ELSE 0 END) as aktif,
        SUM(CASE WHEN status = 'nonaktif' THEN 1 ELSE 0 END) as nonaktif,
        AVG(harga) as harga_rata_rata
    FROM layanan 
    WHERE status != 'deleted'
")[0];

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

function getSortIcon($column, $current_sort, $current_order) {
    if ($column !== $current_sort) {
        return '<i class="fa fa-sort text-muted"></i>';
    }
    return $current_order === 'ASC' ? 
        '<i class="fa fa-sort-up text-primary"></i>' : 
        '<i class="fa fa-sort-down text-primary"></i>';
}

function getSortUrl($column, $current_sort, $current_order) {
    $new_order = ($column === $current_sort && $current_order === 'ASC') ? 'DESC' : 'ASC';
    $params = $_GET;
    $params['sort'] = $column;
    $params['order'] = $new_order;
    unset($params['page']);
    return '?' . http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Layanan - KODE</title>
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
    <!-- Loading Overlay -->
    <div id="loading-overlay" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-lg p-6 flex flex-col items-center">
            <i class="fa fa-spinner fa-spin text-2xl text-primary-500 mb-2"></i>
            <p class="text-gray-600">Memuat...</p>
        </div>
    </div>

    <?php include 'layouts/header.php'; ?>

    <!-- Page Header -->
    <div class="bg-white shadow-sm border-b border-gray-200 mb-6">
        <div class="px-6 py-4">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 flex items-center">
                        <i class="fa fa-cogs text-primary-500 mr-3"></i>
                        Manajemen Layanan
                    </h1>
                    <p class="text-gray-600 mt-1">Kelola layanan maintenance dan pelatihan</p>
                </div>
                <nav class="flex" aria-label="Breadcrumb">
                    <ol class="flex items-center space-x-2 text-sm">
                        <li><a href="index.php" class="text-primary-600 hover:text-primary-700">Dashboard</a></li>
                        <li class="text-gray-400">/</li>
                        <li><a href="#" class="text-primary-600 hover:text-primary-700">Produk & Layanan</a></li>
                        <li class="text-gray-400">/</li>
                        <li class="text-gray-600">Layanan</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="max-w-7xl ml-0 px-4 sm:px-6 lg:px-8 py-8 overflow-hidden">
        <!-- Success/Error Messages -->
        <?php if (isset($_GET['success'])): ?>
        <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6 flex items-center">
            <i class="fa fa-check-circle text-green-500 text-xl mr-3"></i>
            <div class="flex-1">
                <p class="text-green-800 font-medium">
                    <?= htmlspecialchars($_GET['success']) ?>
                </p>
            </div>
            <button onclick="this.parentElement.style.display='none'" class="text-green-500 hover:text-green-700">
                <i class="fa fa-times"></i>
            </button>
        </div>
        <?php endif; ?>

        <?php if (isset($_GET['error'])): ?>
        <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6 flex items-center">
            <i class="fa fa-exclamation-circle text-red-500 text-xl mr-3"></i>
            <div class="flex-1">
                <p class="text-red-800 font-medium">
                    <?= htmlspecialchars($_GET['error']) ?>
                </p>
            </div>
            <button onclick="this.parentElement.style.display='none'" class="text-red-500 hover:text-red-700">
                <i class="fa fa-times"></i>
            </button>
        </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <div class="flex items-center">
                    <div class="p-3 bg-blue-100 rounded-lg">
                        <i class="fa fa-cogs text-blue-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-2xl font-bold text-gray-900"><?= number_format($stats['total_layanan']) ?></p>
                        <p class="text-gray-600 text-sm">Total Layanan</p>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <div class="flex items-center">
                    <div class="p-3 bg-green-100 rounded-lg">
                        <i class="fa fa-check-circle text-green-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-2xl font-bold text-gray-900"><?= number_format($stats['aktif']) ?></p>
                        <p class="text-gray-600 text-sm">Layanan Aktif</p>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <div class="flex items-center">
                    <div class="p-3 bg-yellow-100 rounded-lg">
                        <i class="fa fa-times-circle text-yellow-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-2xl font-bold text-gray-900"><?= number_format($stats['nonaktif']) ?></p>
                        <p class="text-gray-600 text-sm">Layanan Nonaktif</p>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <div class="flex items-center">
                    <div class="p-3 bg-purple-100 rounded-lg">
                        <i class="fas fa-chart-line text-purple-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-2xl font-bold text-gray-900"><?= formatRupiah($stats['harga_rata_rata']) ?></p>
                        <p class="text-gray-600 text-sm">Harga Rata-rata</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
            <div class="flex flex-wrap gap-3">
                <?php if (AuthStatic::hasRole(['admin', 'sales'])): ?>
                <a href="layanan-add.php" class="inline-flex items-center px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors">
                    <i class="fa fa-plus mr-2"></i>
                    Tambah Layanan
                </a>
                <?php endif; ?>
                
                <a href="kategori.php" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                    <i class="fa fa-tags mr-2"></i>
                    Kelola Kategori
                </a>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
            <div class="flex items-center mb-4">
                <i class="fa fa-filter text-primary-500 mr-2"></i>
                <h3 class="text-lg font-semibold text-gray-900">Filter</h3>
            </div>
            <form method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                <div>
                    <label for="kategori" class="block text-sm font-medium text-gray-700 mb-2">Kategori</label>
                    <select name="kategori" id="kategori" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500" onchange="this.form.submit()">
                        <option value="">Semua Kategori</option>
                        <?php foreach ($categories as $category): ?>
                        <option value="<?= $category['id'] ?>" <?= $kategori_filter == $category['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($category['nama_kategori']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                    <select name="status" id="status" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500" onchange="this.form.submit()">
                        <option value="semua" <?= $status_filter === 'semua' ? 'selected' : '' ?>>Semua Status</option>
                        <option value="aktif" <?= $status_filter === 'aktif' ? 'selected' : '' ?>>Aktif</option>
                        <option value="nonaktif" <?= $status_filter === 'nonaktif' ? 'selected' : '' ?>>Nonaktif</option>
                    </select>
                </div>
                <div>
                    <label for="search" class="block text-sm font-medium text-gray-700 mb-2">Pencarian</label>
                    <input type="text" name="search" id="search" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500" 
                           placeholder="Cari layanan..." value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="flex items-end">
                    <button type="submit" class="w-full px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors flex items-center justify-center">
                        <i class="fa fa-search mr-2"></i>
                        Cari
                    </button>
                </div>
                <div class="flex items-end">
                    <a href="layanan.php" class="w-full px-4 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition-colors flex items-center justify-center">
                        <i class="fa fa-refresh mr-2"></i>
                        Reset
                    </a>
                </div>
            </form>
        </div>

        <!-- Layanan Table -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200">
            <div class="px-6 py-4 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                        <i class="fa fa-list text-primary-500 mr-2"></i>
                        Daftar Layanan (<?= number_format($total_records) ?> layanan)
                    </h3>
                </div>
            </div>
            <div class="p-6">
                <?php if (empty($layanan_list)): ?>
                <div class="text-center py-12">
                    <i class="fa fa-cogs text-6xl text-gray-400 mb-4"></i>
                    <h4 class="text-xl font-semibold text-gray-600 mb-2">Tidak ada layanan ditemukan</h4>
                    <p class="text-gray-500 mb-6">Silakan tambah layanan baru atau ubah filter pencarian.</p>
                    <?php if (AuthStatic::hasRole(['admin', 'sales'])): ?>
                    <a href="layanan-add.php" class="inline-flex items-center px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors">
                        <i class="fa fa-plus mr-2"></i> Tambah Layanan Pertama
                    </a>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100" onclick="location.href='<?= getSortUrl('nama_layanan', $sort, $order) ?>'">
                                    <div class="flex items-center">
                                        Nama Layanan <?= getSortIcon('nama_layanan', $sort, $order) ?>
                                    </div>
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kategori</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100" onclick="location.href='<?= getSortUrl('harga', $sort, $order) ?>'">
                                    <div class="flex items-center">
                                        Harga <?= getSortIcon('harga', $sort, $order) ?>
                                    </div>
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider hidden lg:table-cell">Transaksi</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider hidden lg:table-cell">Pendapatan</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($layanan_list as $layanan): ?>
                                <tr class="hover:bg-gray-50 transition-colors duration-200">
                                    <td class="px-6 py-4">
                                        <a href="layanan-view.php?id=<?= $layanan['id'] ?>" class="text-primary-600 hover:text-primary-700">
                                            <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($layanan['nama_layanan']) ?></div>
                                        </a>
                                        <?php if ($layanan['deskripsi']): ?>
                                            <div class="text-sm text-gray-500"><?= htmlspecialchars(substr($layanan['deskripsi'], 0, 100)) ?><?= strlen($layanan['deskripsi']) > 100 ? '...' : '' ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php if ($layanan['jenis_layanan']): ?>
                                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800"><?= htmlspecialchars($layanan['jenis_layanan']) ?></span>
                                        <?php else: ?>
                                            <span class="text-gray-500">Tanpa Kategori</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-green-600"><?= formatRupiah($layanan['harga']) ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?= $layanan['status'] === 'aktif' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                            <?= getStatusText($layanan['status']) ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-center hidden lg:table-cell">
                                        <?php if ($layanan['total_transaksi'] > 0): ?>
                                            <span class="inline-flex px-2 py-1 text-xs font-medium bg-blue-100 text-blue-800 rounded-full"><?= number_format($layanan['total_transaksi']) ?></span>
                                        <?php else: ?>
                                            <span class="text-gray-500">0</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap hidden lg:table-cell">
                                        <?php if ($layanan['total_pendapatan'] > 0): ?>
                                            <div class="text-sm font-medium text-green-600"><?= formatRupiah($layanan['total_pendapatan']) ?></div>
                                        <?php else: ?>
                                            <span class="text-gray-500">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <div class="flex space-x-2">
                                            <a href="layanan-view.php?id=<?= $layanan['id'] ?>" 
                                               class="inline-flex items-center px-3 py-1 bg-blue-600 text-white text-xs font-medium rounded-md hover:bg-blue-700 transition-colors duration-200" 
                                               title="Lihat Detail">
                                                <i class="fa fa-eye mr-1"></i> Detail
                                            </a>
                                            
                                            <?php if (AuthStatic::hasRole(['admin']) || ($user['role'] === 'sales' && $layanan['created_by'] == $user['id'])): ?>
                                            <a href="layanan-edit.php?id=<?= $layanan['id'] ?>" 
                                               class="inline-flex items-center px-3 py-1 bg-yellow-600 text-white text-xs font-medium rounded-md hover:bg-yellow-700 transition-colors duration-200" 
                                               title="Edit">
                                                <i class="fa fa-edit mr-1"></i> Edit
                                            </a>
                                            <?php endif; ?>
                                            
                                            <?php if (AuthStatic::hasRole(['admin'])): ?>
                                            <button type="button" 
                                                    class="inline-flex items-center px-3 py-1 bg-red-600 text-white text-xs font-medium rounded-md hover:bg-red-700 transition-colors duration-200" 
                                                    onclick="confirmDelete(<?= $layanan['id'] ?>, '<?= htmlspecialchars($layanan['nama_layanan']) ?>')" 
                                                    title="Hapus">
                                                <i class="fa fa-trash mr-1"></i> Hapus
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
                    
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                    <div class="bg-white px-6 py-4 border-t border-gray-200">
                        <div class="flex flex-col sm:flex-row justify-between items-center">
                            <div class="mb-4 sm:mb-0">
                                <p class="text-gray-600 text-sm">
                                    Menampilkan <?= number_format(($page - 1) * $limit + 1) ?> - 
                                    <?= number_format(min($page * $limit, $total_records)) ?> 
                                    dari <?= number_format($total_records) ?> layanan
                                </p>
                            </div>
                            <div>
                                <nav class="flex items-center space-x-1">
                                    <?php if ($page > 1): ?>
                                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" 
                                       class="px-3 py-2 text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-lg transition-colors">
                                        <i class="fa fa-chevron-left"></i>
                                    </a>
                                    <?php endif; ?>
                                    
                                    <?php
                                    $start_page = max(1, $page - 2);
                                    $end_page = min($total_pages, $page + 2);
                                    
                                    for ($i = $start_page; $i <= $end_page; $i++):
                                    ?>
                                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" 
                                       class="px-3 py-2 text-sm <?= $i === $page ? 'bg-primary-600 text-white' : 'text-gray-700 hover:bg-gray-100' ?> rounded-lg transition-colors">
                                        <?= $i ?>
                                    </a>
                                    <?php endfor; ?>
                                    
                                    <?php if ($page < $total_pages): ?>
                                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" 
                                       class="px-3 py-2 text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-lg transition-colors">
                                        <i class="fa fa-chevron-right"></i>
                                    </a>
                                    <?php endif; ?>
                                </nav>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" role="dialog" style="display: none;">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Konfirmasi Hapus</h4>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p>Apakah Anda yakin ingin menghapus layanan <strong id="layananName"></strong>?</p>
                    <div class="alert alert-warning mt-3" style="margin-bottom: 0;">
                        <i class="fa fa-warning mr-2"></i>
                        Jika layanan pernah digunakan dalam transaksi, status akan diubah menjadi nonaktif.
                    </div>
                </div>
                <div class="modal-footer">
                    <form method="POST" id="deleteForm">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" id="deleteId">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-danger ml-2">Hapus</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

<?php
$additional_scripts = '<script>
    function confirmDelete(id, name) {
        $(\'#deleteId\').val(id);
        $(\'#layananName\').text(name);
        $(\'#deleteModal\').modal(\'show\');
    }
    
    // Auto-hide alerts
    setTimeout(function() {
        $(\'.alert\').fadeOut();
    }, 5000);
</script>';
require_once 'layouts/footer.php';
?>
