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
$sort = $_GET['sort'] ?? 'nama_kategori';
$order = $_GET['order'] ?? 'ASC';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// Build query untuk kategori
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(nama_kategori LIKE ? OR deskripsi LIKE ?)";
    $search_param = "%{$search}%";
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Validasi sort column
$sort_mapping = [
    'nama_kategori' => 'nama_kategori',
    'created_at' => 'created_at'
];

if (!array_key_exists($sort, $sort_mapping)) {
    $sort = 'nama_kategori';
}

$sort_column = $sort_mapping[$sort];
$order = strtoupper($order) === 'DESC' ? 'DESC' : 'ASC';

// Query untuk menghitung total
$count_query = "
    SELECT COUNT(*) as total
    FROM kategori_produk
    {$where_clause}
";

$total_result = $db->select($count_query, $params);
$total_records = $total_result[0]['total'];
$total_pages = ceil($total_records / $limit);

// Query untuk mengambil data kategori
$query = "
    SELECT k.*, 
           (SELECT COUNT(*) FROM produk p WHERE p.kategori_id = k.id) as total_produk
    FROM kategori_produk k
    {$where_clause}
    ORDER BY {$sort_column} {$order}
    LIMIT {$limit} OFFSET {$offset}
";

$kategori_list = $db->select($query, $params);

// Statistik kategori
$stats = $db->select("
    SELECT 
        COUNT(*) as total_kategori,
        (SELECT COUNT(*) FROM produk WHERE kategori_id IS NOT NULL) as produk_berkategori,
        (SELECT COUNT(*) FROM produk WHERE kategori_id IS NULL) as produk_tanpa_kategori
    FROM kategori_produk
")[0];

// Handle delete action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    if (!AuthStatic::hasRole(['admin'])) {
        $error = 'access_denied';
    } else {
        $delete_id = intval($_POST['delete_id']);
        
        try {
            // Cek apakah kategori digunakan oleh produk
            $usage_check = $db->select(
                "SELECT COUNT(*) as count FROM produk WHERE kategori_id = ?",
                [$delete_id]
            );
            
            if ($usage_check[0]['count'] > 0) {
                $error = 'category_in_use';
            } else {
                // Hapus kategori jika tidak digunakan
                $db->execute("DELETE FROM kategori_produk WHERE id = ?", [$delete_id]);
                $success = 'deleted';
            }
            
            if ($success) {
                header("Location: kategori.php?success={$success}");
                exit;
            }
        } catch (Exception $e) {
            $error = 'delete_failed';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Kategori Produk - Sistem Manajemen Desa</title>
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
                        <i class="fa fa-tags text-primary-600 mr-3"></i>
                        Manajemen Kategori Produk
                    </h1>
                    <p class="text-sm text-gray-600 mt-1">Kelola kategori produk untuk organisasi yang lebih baik</p>
                </div>
                <nav class="mt-4 sm:mt-0 text-sm text-gray-500">
                    <a href="index.php" class="hover:text-primary-600">Dashboard</a>
                    <span class="mx-2">/</span>
                    <a href="produk.php" class="hover:text-primary-600">Produk</a>
                    <span class="mx-2">/</span>
                    <span class="text-gray-900">Kategori</span>
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
                                Anda tidak memiliki akses untuk menghapus kategori.
                            <?php elseif ($error === 'delete_failed'): ?>
                                Gagal menghapus kategori. Silakan coba lagi.
                            <?php elseif ($error === 'category_in_use'): ?>
                                Kategori tidak dapat dihapus karena masih digunakan oleh produk.
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
                                Kategori berhasil dihapus.
                            <?php elseif ($success === 'created'): ?>
                                Kategori baru berhasil ditambahkan.
                            <?php elseif ($success === 'updated'): ?>
                                Data kategori berhasil diperbarui.
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                            <i class="fa fa-tags text-blue-600 text-xl"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Total Kategori</p>
                        <p class="text-2xl font-bold text-gray-900"><?= number_format($stats['total_kategori']) ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                            <i class="fa fa-cube text-green-600 text-xl"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Produk Berkategori</p>
                        <p class="text-2xl font-bold text-gray-900"><?= number_format($stats['produk_berkategori']) ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center">
                            <i class="fa fa-exclamation-triangle text-yellow-600 text-xl"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Tanpa Kategori</p>
                        <p class="text-2xl font-bold text-gray-900"><?= number_format($stats['produk_tanpa_kategori']) ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-6">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">Filter Kategori</h3>
            </div>
            <div class="p-6">
                <form method="GET" class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div class="md:col-span-2">
                            <label for="search" class="block text-sm font-medium text-gray-700 mb-2">Pencarian</label>
                            <input type="text" id="search" name="search" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500" 
                                   value="<?= htmlspecialchars($search) ?>" 
                                   placeholder="Nama kategori atau deskripsi">
                        </div>
                        
                        <div>
                            <label for="sort" class="block text-sm font-medium text-gray-700 mb-2">Urutkan</label>
                            <select id="sort" name="sort" 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                                <option value="nama_kategori" <?= $sort === 'nama_kategori' ? 'selected' : '' ?>>Nama Kategori</option>
                                <option value="created_at" <?= $sort === 'created_at' ? 'selected' : '' ?>>Tanggal Dibuat</option>
                            </select>
                        </div>
                        
                        <div>
                            <label for="order" class="block text-sm font-medium text-gray-700 mb-2">Urutan</label>
                            <select name="order" 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                                <option value="ASC" <?= $order === 'ASC' ? 'selected' : '' ?>>A-Z / Lama-Baru</option>
                                <option value="DESC" <?= $order === 'DESC' ? 'selected' : '' ?>>Z-A / Baru-Lama</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="flex flex-wrap gap-3 pt-4">
                        <button type="submit" class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 transition-colors">
                            <i class="fa fa-search mr-2"></i>Filter
                        </button>
                        <a href="kategori.php" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition-colors">
                            <i class="fa fa-refresh mr-2"></i>Reset
                        </a>
                        <a href="kategori-add.php" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition-colors ml-auto">
                            <i class="fa fa-plus mr-2"></i>Tambah Kategori
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Categories Table -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200">
            <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                <h3 class="text-lg font-semibold text-gray-900">Daftar Kategori</h3>
                <span class="text-sm text-gray-500">Menampilkan <?= $offset + 1 ?>-<?= min($offset + $limit, $total_records) ?> dari <?= $total_records ?> kategori</span>
            </div>
            
            <?php if (empty($kategori_list)): ?>
                <div class="text-center py-12">
                    <i class="fa fa-tags text-6xl text-gray-400 mb-4"></i>
                    <h4 class="text-xl font-semibold text-gray-600 mb-2">Tidak ada kategori ditemukan</h4>
                    <p class="text-gray-500 mb-6">Silakan ubah filter pencarian atau tambah kategori baru.</p>
                    <a href="kategori-add.php" class="inline-flex items-center px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 transition-colors">
                        <i class="fa fa-plus mr-2"></i> Tambah Kategori Pertama
                    </a>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kategori</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Deskripsi</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Jumlah Produk</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal Dibuat</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($kategori_list as $kategori): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-10 w-10">
                                            <div class="h-10 w-10 rounded-lg bg-primary-100 flex items-center justify-center">
                                                <i class="fa fa-tag text-primary-600"></i>
                                            </div>
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900">
                                                <?= htmlspecialchars($kategori['nama_kategori']) ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm text-gray-900">
                                        <?= htmlspecialchars($kategori['deskripsi'] ?: '-') ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                        <?= number_format($kategori['total_produk']) ?> produk
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= date('d/m/Y', strtotime($kategori['created_at'])) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex items-center justify-end space-x-2">
                                        <a href="kategori-view.php?id=<?= $kategori['id'] ?>" 
                                           class="text-blue-600 hover:text-blue-900 p-2 rounded-lg hover:bg-blue-50 transition-colors"
                                           title="Lihat Detail">
                                            <i class="fa fa-eye"></i>
                                        </a>
                                        <a href="kategori-edit.php?id=<?= $kategori['id'] ?>" 
                                           class="text-green-600 hover:text-green-900 p-2 rounded-lg hover:bg-green-50 transition-colors"
                                           title="Edit">
                                            <i class="fa fa-edit"></i>
                                        </a>
                                        <?php if (AuthStatic::hasRole(['admin'])): ?>
                                        <button onclick="confirmDelete(<?= $kategori['id'] ?>, '<?= htmlspecialchars($kategori['nama_kategori']) ?>')" 
                                                class="text-red-600 hover:text-red-900 p-2 rounded-lg hover:bg-red-50 transition-colors"
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
                        <div class="text-sm text-gray-700">
                            Menampilkan <?= $offset + 1 ?> sampai <?= min($offset + $limit, $total_records) ?> dari <?= $total_records ?> kategori
                        </div>
                        <div class="flex space-x-1">
                            <?php if ($page > 1): ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" 
                               class="px-3 py-2 text-sm bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                                <i class="fa fa-chevron-left"></i>
                            </a>
                            <?php endif; ?>
                            
                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" 
                               class="px-3 py-2 text-sm <?= $i === $page ? 'bg-primary-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50' ?> border border-gray-300 rounded-lg">
                                <?= $i ?>
                            </a>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" 
                               class="px-3 py-2 text-sm bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                                <i class="fa fa-chevron-right"></i>
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4">
            <div class="p-6">
                <div class="flex items-center mb-4">
                    <div class="flex-shrink-0">
                        <i class="fa fa-exclamation-triangle text-red-600 text-2xl"></i>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-lg font-medium text-gray-900">Konfirmasi Hapus</h3>
                    </div>
                </div>
                <p class="text-sm text-gray-500 mb-6">
                    Apakah Anda yakin ingin menghapus kategori <strong id="categoryName"></strong>? 
                    Tindakan ini tidak dapat dibatalkan.
                </p>
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeDeleteModal()" 
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 focus:ring-2 focus:ring-gray-500">
                        Batal
                    </button>
                    <form id="deleteForm" method="POST" class="inline">
                        <input type="hidden" name="delete_id" id="deleteId">
                        <button type="submit" 
                                class="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 focus:ring-2 focus:ring-red-500">
                            Hapus
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <?php include 'layouts/footer.php'; ?>

    <script>
        function confirmDelete(id, name) {
            document.getElementById('deleteId').value = id;
            document.getElementById('categoryName').textContent = name;
            document.getElementById('deleteModal').classList.remove('hidden');
            document.getElementById('deleteModal').classList.add('flex');
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.add('hidden');
            document.getElementById('deleteModal').classList.remove('flex');
        }

        // Auto hide alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('[data-alert]');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.transition = 'opacity 0.5s';
                    alert.style.opacity = '0';
                    setTimeout(() => alert.remove(), 500);
                }, 5000);
            });
        });
    </script>
</body>
</html>