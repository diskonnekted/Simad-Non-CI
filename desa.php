<?php
require_once 'config/database.php';
require_once 'config/auth.php';

// Cek autentikasi dan role
if (!AuthStatic::isLoggedIn()) {
    header('Location: login.php');
    exit;
}

if (!AuthStatic::hasRole(['admin', 'sales', 'programmer'])) {
    header('Location: index.php?error=access_denied');
    exit;
}

$user = AuthStatic::getCurrentUser();
$db = getDatabase();
/** @var Database $db */

// Handle delete action
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    try {
        $db->execute("UPDATE desa SET status = 'nonaktif' WHERE id = ?", [$id]);
        $success = 'Desa berhasil dinonaktifkan';
    } catch (Exception $e) {
        $error = 'Gagal menonaktifkan desa: ' . $e->getMessage();
    }
}

// Handle search and filter
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? 'aktif';
$limit = 20;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

// Build query
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(nama_desa LIKE ? OR kecamatan LIKE ? OR kabupaten LIKE ? OR nama_kepala_desa LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
}

if ($status_filter !== 'semua') {
    $where_conditions[] = "d.status = ?";
    $params[] = $status_filter;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count
$count_query = "SELECT COUNT(*) as total FROM desa d $where_clause";
$total_records = $db->select($count_query, $params)[0]['total'] ?? 0;
$total_pages = ceil($total_records / $limit);

// Get desa data
$query = "
    SELECT d.*, 
           COUNT(t.id) as total_transaksi,
           COALESCE(SUM(CASE WHEN t.tanggal_transaksi >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN t.total_amount END), 0) as transaksi_30_hari,
           COALESCE(SUM(p.jumlah_piutang), 0) as total_piutang
    FROM desa d
    LEFT JOIN transaksi t ON d.id = t.desa_id
    LEFT JOIN piutang p ON t.id = p.transaksi_id AND p.status = 'belum_jatuh_tempo'
    $where_clause
    GROUP BY d.id
    ORDER BY d.nama_desa ASC
    LIMIT $limit OFFSET $offset
";

$desa_list = $db->select($query, $params);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Sistem Manajemen Desa - Kelola data desa dan informasi kontak">
    <meta name="keywords" content="desa, manajemen, sistem, transaksi">
    <title>Manajemen Desa - Sistem Manajemen Desa</title>
    
    <!-- Tailwind CSS -->
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
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-50 font-sans">
    <!-- Loading Overlay -->
    <div id="loading" class="fixed inset-0 bg-white bg-opacity-75 flex items-center justify-center z-50 hidden">
        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-primary-600"></div>
    </div>

    <!-- Main Container -->
    <div class="min-h-screen flex flex-col">
        <?php include 'layouts/header.php'; ?>

        <!-- Main Content -->
        <div class="flex-1 flex">

            <!-- Content Area -->
            <main class="flex-1">
                <div class="max-w-7xl ml-0 px-4 sm:px-6 lg:px-8 py-8">
                <!-- Page Header -->
                <div class="mb-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900 flex items-center">
                                <i class="fa fa-map-marker text-primary-600 mr-3"></i>
                                Manajemen Desa
                            </h1>
                        </div>
                    </div>
                </div>

                <!-- Alert Messages -->
                <?php if (isset($success)): ?>
                    <div data-alert class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg mb-6 flex items-center">
                        <i class="fa fa-check-circle text-green-600 mr-3"></i>
                        <span><?= htmlspecialchars($success) ?></span>
                        <button type="button" class="ml-auto text-green-600 hover:text-green-800" onclick="this.parentElement.style.display='none'">
                            <i class="fa fa-times"></i>
                        </button>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($error)): ?>
                    <div data-alert class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg mb-6 flex items-center">
                        <i class="fa fa-exclamation-triangle text-red-600 mr-3"></i>
                        <span><?= htmlspecialchars($error) ?></span>
                        <button type="button" class="ml-auto text-red-600 hover:text-red-800" onclick="this.parentElement.style.display='none'">
                            <i class="fa fa-times"></i>
                        </button>
                    </div>
                <?php endif; ?>

                <!-- Main Content Card -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h2 class="text-lg font-semibold text-gray-900">Kelola data desa dan informasi kontak</h2>
                    </div>
                    <div class="p-6">
                        <!-- Search and Filter Form -->
                        <div class="mb-6">
                            <form method="GET" action="desa.php" class="space-y-4 lg:space-y-0 lg:flex lg:items-end lg:space-x-4">
                                <div class="flex-1">
                                    <label for="search" class="block text-sm font-medium text-gray-700 mb-2">Pencarian</label>
                                    <div class="relative">
                                        <input type="text" id="search" name="search" 
                                               class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500" 
                                               placeholder="Cari desa, kecamatan, atau kontak..." 
                                               value="<?= htmlspecialchars($search) ?>">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <i class="fa fa-search text-gray-400"></i>
                                        </div>
                                    </div>
                                </div>
                                <div class="lg:w-48">
                                    <label for="status_filter" class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                                    <select id="status_filter" name="status" 
                                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                                        <option value="semua" <?= $status_filter === 'semua' ? 'selected' : '' ?>>Semua Status</option>
                                        <option value="aktif" <?= $status_filter === 'aktif' ? 'selected' : '' ?>>Aktif</option>
                                        <option value="nonaktif" <?= $status_filter === 'nonaktif' ? 'selected' : '' ?>>Non-aktif</option>
                                    </select>
                                </div>
                                <div class="flex space-x-3">
                                    <button type="submit" class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 flex items-center">
                                        <i class="fa fa-search mr-2"></i>Cari
                                    </button>
                                    <a href="desa-add.php" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 focus:ring-2 focus:ring-green-500 focus:ring-offset-2 flex items-center">
                                        <i class="fa fa-plus mr-2"></i>Tambah Desa
                                    </a>
                                </div>
                            </form>
                        </div>

                        <!-- Results Info -->
                        <div class="mb-4">
                            <p class="text-sm text-gray-600">
                                Menampilkan <span class="font-medium"><?= count($desa_list) ?></span> dari <span class="font-medium"><?= $total_records ?></span> desa
                                <?php if (!empty($search)): ?>
                                    untuk pencarian "<span class="font-medium"><?= htmlspecialchars($search) ?></span>"
                                <?php endif; ?>
                            </p>
                        </div>

                        <!-- Data Table -->
                        <?php if (empty($desa_list)): ?>
                            <div class="text-center py-12">
                                <i class="fa fa-map-marker text-4xl text-gray-400 mb-4"></i>
                                <h3 class="text-lg font-medium text-gray-900 mb-2">Tidak ada desa ditemukan</h3>
                                <p class="text-gray-600 mb-6">Silakan ubah kriteria pencarian atau tambah desa baru.</p>
                                <a href="desa-add.php" class="inline-flex items-center px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700">
                                    <i class="fa fa-plus mr-2"></i>Tambah Desa Pertama
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="overflow-x-auto">
                                <table id="desaTable" class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">No</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100">
                                                Nama Desa <i class="fa fa-sort text-gray-400 ml-1"></i>
                                            </th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100">
                                                Kecamatan <i class="fa fa-sort text-gray-400 ml-1"></i>
                                            </th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kontak Person</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Telepon</th>

                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Transaksi</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Piutang</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php 
                                        $no = $offset + 1;
                                        foreach ($desa_list as $desa): 
                                        ?>
                                            <tr class="hover:bg-gray-50 transition-colors duration-200">
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?= $no++ ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($desa['nama_desa']) ?></div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?= htmlspecialchars($desa['kecamatan']) ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?= htmlspecialchars($desa['nama_sekdes'] ?? $desa['nama_kepala_desa'] ?? '-') ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?= htmlspecialchars($desa['no_hp_sekdes'] ?? $desa['no_hp_kepala_desa'] ?? '-') ?></td>

                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <?php if ($desa['status'] === 'aktif'): ?>
                                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">Aktif</span>
                                                    <?php else: ?>
                                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">Tidak Aktif</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <span class="inline-flex px-2 py-1 text-xs font-medium bg-blue-100 text-blue-800 rounded-full"><?= $desa['total_transaksi'] ?></span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">Rp <?= number_format($desa['total_piutang'], 0, ',', '.') ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                                                    <a href="desa-view.php?id=<?= $desa['id'] ?>" class="inline-flex items-center px-3 py-1 bg-blue-600 text-white text-xs font-medium rounded-md hover:bg-blue-700 transition-colors duration-200" title="Lihat Detail">
                                                        <i class="fa fa-eye mr-1"></i> Detail
                                                    </a>
                                                    <a href="desa-edit.php?id=<?= $desa['id'] ?>" class="inline-flex items-center px-3 py-1 bg-yellow-600 text-white text-xs font-medium rounded-md hover:bg-yellow-700 transition-colors duration-200" title="Edit">
                                                        <i class="fa fa-edit mr-1"></i> Edit
                                                    </a>
                                                    <button type="button" class="inline-flex items-center px-3 py-1 bg-red-600 text-white text-xs font-medium rounded-md hover:bg-red-700 transition-colors duration-200" title="Hapus"
                                                            onclick="confirmDelete(<?= $desa['id'] ?>, '<?= htmlspecialchars($desa['nama_desa'], ENT_QUOTES) ?>')">
                                                        <i class="fa fa-trash mr-1"></i> Hapus
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php endif; ?>

                            <!-- Pagination -->
                            <?php if ($total_pages > 1): ?>
                                <div class="mt-6">
                                    <div class="flex items-center justify-between">
                                        <div class="flex-1 flex justify-between sm:hidden">
                                            <?php if ($page > 1): ?>
                                                <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status_filter) ?>" class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                                    Sebelumnya
                                                </a>
                                            <?php endif; ?>
                                            <?php if ($page < $total_pages): ?>
                                                <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status_filter) ?>" class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                                    Selanjutnya
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                        <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                                            <div>
                                                <p class="text-sm text-gray-700">
                                                    Menampilkan <span class="font-medium"><?= $offset + 1 ?></span> sampai <span class="font-medium"><?= min($offset + $limit, $total_records) ?></span> dari <span class="font-medium"><?= $total_records ?></span> hasil
                                                </p>
                                            </div>
                                            <div>
                                                <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                                                    <?php if ($page > 1): ?>
                                                        <a href="?page=1&search=<?= urlencode($search) ?>&status=<?= urlencode($status_filter) ?>" class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                                            <span class="sr-only">First</span>
                                                            <i class="fa fa-angle-double-left"></i>
                                                        </a>
                                                        <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status_filter) ?>" class="relative inline-flex items-center px-2 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                                            <span class="sr-only">Previous</span>
                                                            <i class="fa fa-chevron-left"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                    
                                                    <?php
                                                    $start_page = max(1, $page - 2);
                                                    $end_page = min($total_pages, $page + 2);
                                                    
                                                    for ($i = $start_page; $i <= $end_page; $i++):
                                                    ?>
                                                        <?php if ($i == $page): ?>
                                                            <span class="relative inline-flex items-center px-4 py-2 border border-primary-500 bg-primary-50 text-sm font-medium text-primary-600">
                                                                <?= $i ?>
                                                            </span>
                                                        <?php else: ?>
                                                            <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status_filter) ?>" class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">
                                                                <?= $i ?>
                                                            </a>
                                                        <?php endif; ?>
                                                    <?php endfor; ?>
                                                    
                                                    <?php if ($page < $total_pages): ?>
                                                        <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status_filter) ?>" class="relative inline-flex items-center px-2 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                                            <span class="sr-only">Next</span>
                                                            <i class="fa fa-chevron-right"></i>
                                                        </a>
                                                        <a href="?page=<?= $total_pages ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status_filter) ?>" class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                                            <span class="sr-only">Last</span>
                                                            <i class="fa fa-angle-double-right"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                </nav>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>

                        </div>
                    </div>

                </div>
                </div>
            </main>
        </div>

        <?php include 'layouts/footer.php'; ?>
    </div>

    <script>
        // Auto hide alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('[data-alert]');
            alerts.forEach(function(alert) {
                setTimeout(function() {
                    alert.style.transition = 'opacity 0.5s';
                    alert.style.opacity = '0';
                    setTimeout(function() {
                        alert.remove();
                    }, 500);
                }, 5000);
            });
        });

        function confirmDelete(id, nama) {
            if (confirm('Apakah Anda yakin ingin menonaktifkan desa "' + nama + '"?')) {
                window.location.href = 'desa.php?action=delete&id=' + id;
            }
        }
    </script>

</body>
</html>
