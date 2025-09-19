<?php
require_once 'config/database.php';
require_once 'config/auth.php';

// Cek autentikasi dan role
if (!AuthStatic::isLoggedIn()) {
    header('Location: login.php');
    exit;
}

if (!AuthStatic::hasRole(['admin', 'supervisor', 'teknisi', 'programmer'])) {
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
$status_filter = $_GET['status'] ?? 'baik';
$sort = $_GET['sort'] ?? 'nama_peralatan';
$order = $_GET['order'] ?? 'ASC';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// Build query untuk peralatan
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(nama_peralatan LIKE ? OR kode_peralatan LIKE ? OR deskripsi LIKE ?)";
    $search_param = "%{$search}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if (!empty($kategori_filter)) {
    $where_conditions[] = "kategori = ?";
    $params[] = $kategori_filter;
}

if (!empty($status_filter)) {
    $where_conditions[] = "kondisi = ?";
    $params[] = $status_filter;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Validasi sort column
$sort_mapping = [
    'nama_peralatan' => 'nama_peralatan',
    'kode_peralatan' => 'kode_peralatan', 
    'kategori' => 'kategori',
    'kondisi' => 'kondisi',
    'lokasi_penyimpanan' => 'lokasi_penyimpanan',
    'created_at' => 'created_at'
];

if (!array_key_exists($sort, $sort_mapping)) {
    $sort = 'nama_peralatan';
}

$sort_column = $sort_mapping[$sort];
$order = strtoupper($order) === 'DESC' ? 'DESC' : 'ASC';

// Query untuk menghitung total
$count_query = "SELECT COUNT(*) as total FROM peralatan {$where_clause}";
$total_result = $db->select($count_query, $params);
$total_records = $total_result[0]['total'];
$total_pages = ceil($total_records / $limit);

// Query untuk mengambil data peralatan
$query = "SELECT * FROM peralatan {$where_clause} ORDER BY {$sort_column} {$order} LIMIT {$limit} OFFSET {$offset}";
$peralatan_list = $db->select($query, $params);

// Statistik peralatan
$stats = $db->select("
    SELECT 
        COUNT(*) as total_peralatan,
        COUNT(CASE WHEN kondisi = 'baik' THEN 1 END) as kondisi_baik,
        COUNT(CASE WHEN kondisi = 'rusak' THEN 1 END) as kondisi_rusak,
        COUNT(CASE WHEN status = 'tersedia' THEN 1 END) as tersedia
    FROM peralatan
")[0];

// Handle delete action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    if (!AuthStatic::hasRole(['admin'])) {
        $error = 'access_denied';
    } else {
        $delete_id = intval($_POST['delete_id']);
        
        try {
            // Cek apakah peralatan sedang digunakan
            $usage_check = $db->select(
                "SELECT COUNT(*) as count FROM jadwal_peralatan WHERE peralatan_id = ?",
                [$delete_id]
            );
            
            if ($usage_check[0]['count'] > 0) {
                // Jika sedang digunakan, ubah status menjadi tidak tersedia
                $db->execute(
                    "UPDATE peralatan SET status = 'tidak_tersedia', updated_at = NOW() WHERE id = ?",
                    [$delete_id]
                );
                $success = 'deactivated';
            } else {
                // Jika tidak digunakan, hapus permanen
                $db->execute("DELETE FROM peralatan WHERE id = ?", [$delete_id]);
                $success = 'deleted';
            }
            
            header("Location: peralatan.php?success={$success}");
            exit;
        } catch (Exception $e) {
            $error = 'delete_failed';
        }
    }
}

// Helper functions
function getKondisiBadge($kondisi) {
    $badges = [
        'baik' => 'success',
        'rusak' => 'danger',
        'maintenance' => 'warning'
    ];
    return $badges[$kondisi] ?? 'default';
}

function getStatusBadge($status) {
    $badges = [
        'tersedia' => 'success',
        'digunakan' => 'info',
        'tidak_tersedia' => 'danger'
    ];
    return $badges[$status] ?? 'default';
}

function getKondisiBadgeClass($kondisi) {
    $badges = [
        'baik' => 'bg-green-100 text-green-800',
        'rusak_ringan' => 'bg-yellow-100 text-yellow-800',
        'rusak_berat' => 'bg-red-100 text-red-800',
        'maintenance' => 'bg-orange-100 text-orange-800'
    ];
    return $badges[$kondisi] ?? 'bg-gray-100 text-gray-800';
}

function getStatusBadgeClass($status) {
    $badges = [
        'tersedia' => 'bg-green-100 text-green-800',
        'digunakan' => 'bg-blue-100 text-blue-800',
        'tidak_tersedia' => 'bg-red-100 text-red-800'
    ];
    return $badges[$status] ?? 'bg-gray-100 text-gray-800';
}

$page_title = 'Manajemen Peralatan';
require_once 'layouts/header.php';
?>

    <!-- Page Header -->
    <div class="bg-white shadow-sm border-b border-gray-200 mb-6">
        <div class="px-6 py-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Manajemen Peralatan</h1>
                    <p class="text-sm text-gray-600 mt-1">Kelola data peralatan kerja</p>
                </div>

            </div>
        </div>
    </div>

    <!-- Main Container -->
    <div class="max-w-7xl ml-0 px-4 sm:px-6 lg:px-8 py-8">

        <!-- Alert Messages -->
        <?php if ($error): ?>
        <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
            <div class="flex">
                <div class="flex-shrink-0">
                    <i class="fas fa-exclamation-circle text-red-400"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-red-800">
                        <?php
                        $error_messages = [
                            'access_denied' => 'Anda tidak memiliki akses untuk melakukan tindakan ini.',
                            'delete_failed' => 'Gagal menghapus peralatan. Silakan coba lagi.'
                        ];
                        echo $error_messages[$error] ?? 'Terjadi kesalahan.';
                        ?>
                    </p>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($success): ?>
        <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
            <div class="flex">
                <div class="flex-shrink-0">
                    <i class="fas fa-check-circle text-green-400"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-green-800">
                        <?php
                        $success_messages = [
                            'added' => 'Peralatan berhasil ditambahkan.',
                            'updated' => 'Peralatan berhasil diperbarui.',
                            'deleted' => 'Peralatan berhasil dihapus.',
                            'deactivated' => 'Peralatan berhasil dinonaktifkan.'
                        ];
                        echo $success_messages[$success] ?? 'Operasi berhasil.';
                        ?>
                    </p>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <!-- Total Peralatan Card -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-tools text-blue-600 text-xl"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Total Peralatan</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo number_format($stats['total_peralatan']); ?></p>
                        <p class="text-xs text-gray-500">Semua Peralatan</p>
                    </div>
                </div>
            </div>

            <!-- Kondisi Baik Card -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-check-circle text-green-600 text-xl"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Kondisi Baik</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo number_format($stats['kondisi_baik']); ?></p>
                        <p class="text-xs text-green-600 font-medium">Siap Digunakan</p>
                    </div>
                </div>
            </div>

            <!-- Tersedia Card -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-box text-yellow-600 text-xl"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Tersedia</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo number_format($stats['tersedia']); ?></p>
                        <p class="text-xs text-gray-500">Tidak Digunakan</p>
                    </div>
                </div>
            </div>

            <!-- Rusak Card -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Rusak</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo number_format($stats['kondisi_rusak']); ?></p>
                        <p class="text-xs text-red-600 font-medium">Perlu Perbaikan</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter and Search -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-6">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                    <i class="fas fa-filter text-primary-600 mr-2"></i>
                    Filter & Pencarian
                </h3>
            </div>
            <div class="p-6">
                <form method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                    <div>
                        <label for="search" class="block text-sm font-medium text-gray-700 mb-2">Pencarian</label>
                        <input type="text" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500" id="search" name="search" 
                               value="<?php echo htmlspecialchars($search); ?>" 
                               placeholder="Nama, kode, atau deskripsi...">
                    </div>
                    <div>
                        <label for="kategori" class="block text-sm font-medium text-gray-700 mb-2">Kategori</label>
                        <select class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500" id="kategori" name="kategori">
                            <option value="">Semua Kategori</option>
                            <option value="elektronik" <?php echo $kategori_filter === 'elektronik' ? 'selected' : ''; ?>>Elektronik</option>
                            <option value="tools" <?php echo $kategori_filter === 'tools' ? 'selected' : ''; ?>>Tools</option>
                            <option value="kendaraan" <?php echo $kategori_filter === 'kendaraan' ? 'selected' : ''; ?>>Kendaraan</option>
                            <option value="lainnya" <?php echo $kategori_filter === 'lainnya' ? 'selected' : ''; ?>>Lainnya</option>
                        </select>
                    </div>
                    <div>
                        <label for="status" class="block text-sm font-medium text-gray-700 mb-2">Kondisi</label>
                        <select class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500" id="status" name="status">
                            <option value="">Semua Kondisi</option>
                            <option value="baik" <?php echo $status_filter === 'baik' ? 'selected' : ''; ?>>Baik</option>
                            <option value="rusak_ringan" <?php echo $status_filter === 'rusak_ringan' ? 'selected' : ''; ?>>Rusak Ringan</option>
                            <option value="rusak_berat" <?php echo $status_filter === 'rusak_berat' ? 'selected' : ''; ?>>Rusak Berat</option>
                            <option value="maintenance" <?php echo $status_filter === 'maintenance' ? 'selected' : ''; ?>>Maintenance</option>
                        </select>
                    </div>
                    <div>
                        <label for="sort" class="block text-sm font-medium text-gray-700 mb-2">Urutkan</label>
                        <select class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500" id="sort" name="sort">
                            <option value="nama_peralatan" <?php echo $sort === 'nama_peralatan' ? 'selected' : ''; ?>>Nama</option>
                            <option value="kode_peralatan" <?php echo $sort === 'kode_peralatan' ? 'selected' : ''; ?>>Kode</option>
                            <option value="kategori" <?php echo $sort === 'kategori' ? 'selected' : ''; ?>>Kategori</option>
                            <option value="kondisi" <?php echo $sort === 'kondisi' ? 'selected' : ''; ?>>Kondisi</option>
                        <option value="created_at" <?php echo $sort === 'created_at' ? 'selected' : ''; ?>>Tanggal</option>
                        </select>
                    </div>
                    <div class="flex items-end space-x-2">
                        <button type="submit" class="bg-primary-600 hover:bg-primary-700 text-white font-medium py-2 px-4 rounded-lg transition duration-200 flex items-center justify-center">
                            <i class="fas fa-search mr-2"></i>Cari
                        </button>
                        <?php if (AuthStatic::hasRole(['admin', 'supervisor'])): ?>
                        <a href="peralatan-add.php" class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-lg font-medium text-white hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition-colors">
                            <i class="fas fa-plus mr-2"></i>Tambah
                        </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <!-- Data Table -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200">
            <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                    <i class="fas fa-tools text-primary-600 mr-2"></i>
                    Daftar Peralatan
                </h3>
                <span class="text-sm text-gray-500">Total: <?php echo number_format($total_records); ?> peralatan</span>
            </div>
            <div class="p-6">
                <?php if (empty($peralatan_list)): ?>
                <div class="text-center py-12">
                    <i class="fas fa-tools text-6xl text-gray-300 mb-4"></i>
                    <p class="text-gray-500 text-lg mb-4">Tidak ada data peralatan yang ditemukan.</p>
                    <?php if (AuthStatic::hasRole(['admin', 'supervisor'])): ?>
                    <a href="peralatan-add.php" class="bg-primary-600 hover:bg-primary-700 text-white font-medium py-2 px-4 rounded-lg transition duration-200 inline-flex items-center">
                        <i class="fas fa-plus mr-2"></i>Tambah Peralatan Pertama
                    </a>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kode</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama Peralatan</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kategori</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kondisi</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Lokasi</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($peralatan_list as $peralatan): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <code class="bg-gray-100 text-gray-800 px-2 py-1 rounded text-sm"><?php echo htmlspecialchars($peralatan['kode_peralatan']); ?></code>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="font-medium text-gray-900"><?php echo htmlspecialchars($peralatan['nama_peralatan']); ?></div>
                                    <?php if (!empty($peralatan['deskripsi'])): ?>
                                    <div class="text-sm text-gray-500"><?php echo htmlspecialchars(substr($peralatan['deskripsi'], 0, 50)) . (strlen($peralatan['deskripsi']) > 50 ? '...' : ''); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800"><?php echo htmlspecialchars($peralatan['kategori']); ?></span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?php echo getKondisiBadgeClass($peralatan['kondisi']); ?>">
                                        <?php echo ucfirst($peralatan['kondisi']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?php echo getStatusBadgeClass($peralatan['status']); ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $peralatan['status'])); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-900"><?php echo htmlspecialchars($peralatan['lokasi_penyimpanan']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex space-x-2">
                                        <a href="peralatan-view.php?id=<?php echo $peralatan['id']; ?>" 
                                           class="text-blue-600 hover:text-blue-900 p-1 rounded" title="Lihat Detail">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if (AuthStatic::hasRole(['admin', 'supervisor'])): ?>
                                        <a href="peralatan-edit.php?id=<?php echo $peralatan['id']; ?>" 
                                           class="text-yellow-600 hover:text-yellow-900 p-1 rounded" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <?php endif; ?>
                                        <?php if (AuthStatic::hasRole(['admin'])): ?>
                                        <button type="button" class="text-red-600 hover:text-red-900 p-1 rounded" 
                                                onclick="confirmDelete(<?php echo $peralatan['id']; ?>, '<?php echo htmlspecialchars($peralatan['nama_peralatan']); ?>')" 
                                                title="Hapus">
                                            <i class="fas fa-trash"></i>
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
                <div class="flex items-center justify-between border-t border-gray-200 bg-white px-4 py-3 sm:px-6 mt-6">
                    <div class="flex flex-1 justify-between sm:hidden">
                        <?php if ($page > 1): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" class="relative inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                            Previous
                        </a>
                        <?php endif; ?>
                        <?php if ($page < $total_pages): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" class="relative ml-3 inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                            Next
                        </a>
                        <?php endif; ?>
                    </div>
                    <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
                        <div>
                            <p class="text-sm text-gray-700">
                                Showing <span class="font-medium"><?php echo (($page - 1) * $per_page) + 1; ?></span> to 
                                <span class="font-medium"><?php echo min($page * $per_page, $total_records); ?></span> of 
                                <span class="font-medium"><?php echo number_format($total_records); ?></span> results
                            </p>
                        </div>
                        <div>
                            <nav class="isolate inline-flex -space-x-px rounded-md shadow-sm" aria-label="Pagination">
                                <?php if ($page > 1): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" class="relative inline-flex items-center rounded-l-md px-2 py-2 text-gray-400 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0">
                                    <i class="fas fa-chevron-left h-5 w-5"></i>
                                </a>
                                <?php endif; ?>
                                
                                <?php
                                $start_page = max(1, $page - 2);
                                $end_page = min($total_pages, $page + 2);
                                
                                for ($i = $start_page; $i <= $end_page; $i++):
                                ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
                                   class="relative inline-flex items-center px-4 py-2 text-sm font-semibold <?php echo $i === $page ? 'z-10 bg-primary-600 text-white focus:z-20 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600' : 'text-gray-900 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0'; ?>">
                                    <?php echo $i; ?>
                                </a>
                                <?php endfor; ?>
                                
                                <?php if ($page < $total_pages): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" class="relative inline-flex items-center rounded-r-md px-2 py-2 text-gray-400 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0">
                                    <i class="fas fa-chevron-right h-5 w-5"></i>
                                </a>
                                <?php endif; ?>
                            </nav>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-medium text-gray-900">Konfirmasi Hapus</h3>
                <button type="button" class="text-gray-400 hover:text-gray-600" onclick="closeDeleteModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="mt-2 px-7 py-3">
                <p class="text-sm text-gray-500">
                    Apakah Anda yakin ingin menghapus peralatan <strong id="deleteItemName" class="text-gray-900"></strong>?
                </p>
                <p class="text-xs text-gray-400 mt-2">Tindakan ini tidak dapat dibatalkan.</p>
            </div>
            <div class="flex items-center justify-end px-4 py-3 space-x-3">
                <button type="button" class="px-4 py-2 bg-gray-300 text-gray-700 text-base font-medium rounded-md shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-300" onclick="closeDeleteModal()">
                    Batal
                </button>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="delete_id" id="deleteItemId">
                    <button type="submit" class="px-4 py-2 bg-red-600 text-white text-base font-medium rounded-md shadow-sm hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500">
                        Hapus
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function confirmDelete(id, name) {
    document.getElementById('deleteItemId').value = id;
    document.getElementById('deleteItemName').textContent = name;
    document.getElementById('deleteModal').classList.remove('hidden');
}

function closeDeleteModal() {
    document.getElementById('deleteModal').classList.add('hidden');
}

// Close modal when clicking outside
document.getElementById('deleteModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeDeleteModal();
    }
});
</script>

<?php require_once 'layouts/footer.php'; ?>