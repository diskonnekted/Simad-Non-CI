<?php
require_once 'config/database.php';
require_once 'config/auth.php';

// Cek autentikasi dan role
if (!AuthStatic::isLoggedIn()) {
    header('Location: login.php');
    exit;
}

if (!AuthStatic::hasRole(['admin', 'akunting', 'supervisor'])) {
    header('Location: index.php?error=access_denied');
    exit;
}

$user = AuthStatic::getCurrentUser();
/** @var Database $db */
$db = getDatabase();

// Function to format currency
function formatRupiah($angka) {
    return 'Rp ' . number_format($angka, 0, ',', '.');
}

$error = $_GET['error'] ?? '';
$success = $_GET['success'] ?? '';

// Parameter pencarian dan filter
$search = $_GET['search'] ?? '';
$kategori_filter = $_GET['kategori'] ?? '';
$sort = $_GET['sort'] ?? 'nama_biaya';
$order = $_GET['order'] ?? 'ASC';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// Build query untuk biaya operasional
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(nama_biaya LIKE ? OR kode_biaya LIKE ? OR kategori LIKE ?)";
    $search_param = "%{$search}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if (!empty($kategori_filter)) {
    $where_conditions[] = "kategori = ?";
    $params[] = $kategori_filter;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Validasi sort column
$sort_mapping = [
    'nama_biaya' => 'nama_biaya',
    'kode_biaya' => 'kode_biaya', 
    'kategori' => 'kategori',
    'tarif_standar' => 'tarif_standar',
    'satuan' => 'satuan',
    'created_at' => 'created_at'
];

if (!array_key_exists($sort, $sort_mapping)) {
    $sort = 'nama_biaya';
}

$sort_column = $sort_mapping[$sort];
$order = strtoupper($order) === 'DESC' ? 'DESC' : 'ASC';

// Query untuk menghitung total
$count_query = "SELECT COUNT(*) as total FROM biaya_operasional {$where_clause}";
$total_result = $db->select($count_query, $params);
$total_records = $total_result[0]['total'];
$total_pages = ceil($total_records / $limit);

// Query untuk mengambil data biaya operasional
$query = "SELECT * FROM biaya_operasional {$where_clause} ORDER BY {$sort_column} {$order} LIMIT {$limit} OFFSET {$offset}";
$biaya_list = $db->select($query, $params);

// Statistik biaya operasional
$stats = $db->select("
    SELECT 
        COUNT(*) as total_biaya,
        COUNT(DISTINCT kategori) as total_kategori,
        AVG(tarif_standar) as rata_rata_tarif,
        SUM(tarif_standar) as total_tarif
    FROM biaya_operasional
")[0];

// Handle delete action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    if (!AuthStatic::hasRole(['admin'])) {
        $error = 'access_denied';
    } else {
        $delete_id = intval($_POST['delete_id']);
        
        try {
            // Cek apakah biaya sedang digunakan
            $usage_check = $db->select(
                "SELECT COUNT(*) as count FROM jadwal_biaya WHERE biaya_id = ?",
                [$delete_id]
            );
            
            if ($usage_check[0]['count'] > 0) {
                $error = 'delete_used';
            } else {
                // Hapus biaya operasional
                $db->execute("DELETE FROM biaya_operasional WHERE id = ?", [$delete_id]);
                $success = 'deleted';
            }
            
            if ($success) {
                header("Location: biaya.php?success={$success}");
                exit;
            }
        } catch (Exception $e) {
            $error = 'delete_failed';
        }
    }
}

// Helper functions

function getKategoriBadge($kategori) {
    $badges = [
        'transportasi' => 'primary',
        'konsumsi' => 'success',
        'peralatan' => 'info',
        'administrasi' => 'warning',
        'lainnya' => 'secondary'
    ];
    return $badges[$kategori] ?? 'secondary';
}

$page_title = 'Manajemen Biaya Operasional';
require_once 'layouts/header.php';
?>

<div class="min-h-screen bg-gray-50 py-8">
    <div class="max-w-7xl ml-0 px-4 sm:px-6 lg:px-8 py-8">
        <!-- Header Section -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-8">
            <div class="mb-4 sm:mb-0">
                <h1 class="text-3xl font-bold text-gray-900">Manajemen Biaya Operasional</h1>
                <p class="mt-1 text-sm text-gray-600">Kelola data biaya operasional</p>
            </div>
            <?php if (AuthStatic::hasRole(['admin', 'akunting'])): ?>
            <a href="biaya-add.php" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                <i class="fas fa-plus mr-2"></i>Tambah Biaya
            </a>
            <?php endif; ?>
        </div>

        <!-- Alert Messages -->
        <?php if ($error): ?>
        <div class="mb-6 bg-red-50 border border-red-200 rounded-md p-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <i class="fas fa-exclamation-circle text-red-400"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-red-800">
                        <?php
                        $error_messages = [
                            'access_denied' => 'Anda tidak memiliki akses untuk melakukan tindakan ini.',
                            'delete_failed' => 'Gagal menghapus biaya operasional. Silakan coba lagi.',
                            'delete_used' => 'Biaya operasional tidak dapat dihapus karena sedang digunakan dalam jadwal.',
                            'invalid_id' => 'ID biaya operasional tidak valid.',
                            'not_found' => 'Biaya operasional tidak ditemukan.',
                            'database_error' => 'Terjadi kesalahan pada database. Silakan coba lagi.'
                        ];
                        echo $error_messages[$error] ?? 'Terjadi kesalahan yang tidak diketahui. Silakan hubungi administrator.';
                        ?>
                    </p>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($success): ?>
        <div class="mb-6 bg-green-50 border border-green-200 rounded-md p-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <i class="fas fa-check-circle text-green-400"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-green-800">
                        <?php
                        $success_messages = [
                            'added' => 'Biaya operasional berhasil ditambahkan.',
                            'updated' => 'Biaya operasional berhasil diperbarui.',
                            'deleted' => 'Biaya operasional berhasil dihapus.'
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
            <div class="bg-white overflow-hidden shadow rounded-lg border-l-4 border-blue-500">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-money-bill-wave text-2xl text-gray-400"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate uppercase tracking-wider">Total Biaya</dt>
                                <dd class="text-lg font-semibold text-gray-900"><?php echo number_format($stats['total_biaya']); ?></dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="bg-white overflow-hidden shadow rounded-lg border-l-4 border-green-500">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-tags text-2xl text-gray-400"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate uppercase tracking-wider">Kategori</dt>
                                <dd class="text-lg font-semibold text-gray-900"><?php echo number_format($stats['total_kategori']); ?></dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="bg-white overflow-hidden shadow rounded-lg border-l-4 border-cyan-500">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-calculator text-2xl text-gray-400"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate uppercase tracking-wider">Rata-rata Tarif</dt>
                                <dd class="text-lg font-semibold text-gray-900"><?php echo formatRupiah($stats['rata_rata_tarif']); ?></dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="bg-white overflow-hidden shadow rounded-lg border-l-4 border-yellow-500">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-chart-line text-2xl text-gray-400"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate uppercase tracking-wider">Total Tarif</dt>
                                <dd class="text-lg font-semibold text-gray-900"><?php echo formatRupiah($stats['total_tarif']); ?></dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter and Search -->
        <div class="bg-white shadow rounded-lg mb-8">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Filter & Pencarian</h3>
            </div>
            <div class="p-6">
                <form method="GET" class="grid grid-cols-1 md:grid-cols-6 gap-4">
                    <div class="md:col-span-2">
                        <label for="search" class="block text-sm font-medium text-gray-700 mb-2">Pencarian</label>
                        <input type="text" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm" id="search" name="search" 
                               value="<?php echo htmlspecialchars($search); ?>" 
                               placeholder="Nama, kode, atau kategori...">
                    </div>
                    <div>
                        <label for="kategori" class="block text-sm font-medium text-gray-700 mb-2">Kategori</label>
                        <select class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm" id="kategori" name="kategori">
                            <option value="">Semua Kategori</option>
                            <option value="transportasi" <?php echo $kategori_filter === 'transportasi' ? 'selected' : ''; ?>>Transportasi</option>
                            <option value="konsumsi" <?php echo $kategori_filter === 'konsumsi' ? 'selected' : ''; ?>>Konsumsi</option>
                            <option value="peralatan" <?php echo $kategori_filter === 'peralatan' ? 'selected' : ''; ?>>Peralatan</option>
                            <option value="administrasi" <?php echo $kategori_filter === 'administrasi' ? 'selected' : ''; ?>>Administrasi</option>
                            <option value="lainnya" <?php echo $kategori_filter === 'lainnya' ? 'selected' : ''; ?>>Lainnya</option>
                        </select>
                    </div>
                    <div>
                        <label for="sort" class="block text-sm font-medium text-gray-700 mb-2">Urutkan</label>
                        <select class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm" id="sort" name="sort">
                            <option value="nama_biaya" <?php echo $sort === 'nama_biaya' ? 'selected' : ''; ?>>Nama</option>
                            <option value="kode_biaya" <?php echo $sort === 'kode_biaya' ? 'selected' : ''; ?>>Kode</option>
                            <option value="kategori" <?php echo $sort === 'kategori' ? 'selected' : ''; ?>>Kategori</option>
                            <option value="tarif_standar" <?php echo $sort === 'tarif_standar' ? 'selected' : ''; ?>>Tarif</option>
                            <option value="created_at" <?php echo $sort === 'created_at' ? 'selected' : ''; ?>>Tanggal</option>
                        </select>
                    </div>
                    <div>
                        <label for="order" class="block text-sm font-medium text-gray-700 mb-2">Arah</label>
                        <select class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm" id="order" name="order">
                            <option value="ASC" <?php echo $order === 'ASC' ? 'selected' : ''; ?>>A-Z</option>
                            <option value="DESC" <?php echo $order === 'DESC' ? 'selected' : ''; ?>>Z-A</option>
                        </select>
                    </div>
                    <div class="flex items-end">
                        <button type="submit" class="w-full inline-flex justify-center items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            <i class="fas fa-search mr-2"></i>Cari
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Data Table -->
        <div class="bg-white shadow rounded-lg mb-8">
            <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                <h3 class="text-lg font-medium text-gray-900">Daftar Biaya Operasional</h3>
                <span class="text-sm text-gray-500">Total: <?php echo number_format($total_records); ?> biaya</span>
            </div>
            <div class="p-6">
                <?php if (empty($biaya_list)): ?>
                <div class="text-center py-12">
                    <i class="fas fa-money-bill-wave text-6xl text-gray-300 mb-4"></i>
                    <p class="text-gray-500 text-lg mb-4">Tidak ada data biaya operasional yang ditemukan.</p>
                    <?php if (AuthStatic::hasRole(['admin', 'akunting'])): ?>
                    <a href="biaya-add.php" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <i class="fas fa-plus mr-2"></i>Tambah Biaya Pertama
                    </a>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-lg">
                    <table class="min-w-full divide-y divide-gray-300">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kode</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama Biaya</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kategori</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tarif Standar</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Satuan</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($biaya_list as $biaya): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <code class="text-sm bg-gray-100 px-2 py-1 rounded"><?php echo htmlspecialchars($biaya['kode_biaya']); ?></code>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($biaya['nama_biaya']); ?></div>
                                    <?php if (!empty($biaya['deskripsi'])): ?>
                                    <div class="text-sm text-gray-500"><?php echo htmlspecialchars(substr($biaya['deskripsi'], 0, 50)) . (strlen($biaya['deskripsi']) > 50 ? '...' : ''); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php
                                    $kategori_colors = [
                                        'transportasi' => 'bg-blue-100 text-blue-800',
                                        'konsumsi' => 'bg-green-100 text-green-800',
                                        'peralatan' => 'bg-cyan-100 text-cyan-800',
                                        'administrasi' => 'bg-yellow-100 text-yellow-800',
                                        'lainnya' => 'bg-gray-100 text-gray-800'
                                    ];
                                    $color_class = $kategori_colors[$biaya['kategori']] ?? 'bg-gray-100 text-gray-800';
                                    ?>
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?php echo $color_class; ?>">
                                        <?php echo ucfirst($biaya['kategori']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right">
                                    <div class="text-sm font-medium text-gray-900"><?php echo formatRupiah($biaya['tarif_standar']); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($biaya['satuan']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex space-x-2">
                                        <a href="biaya-view.php?id=<?php echo $biaya['id']; ?>" 
                                           class="text-cyan-600 hover:text-cyan-900" title="Lihat Detail">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if (AuthStatic::hasRole(['admin', 'akunting'])): ?>
                                        <a href="biaya-edit.php?id=<?php echo $biaya['id']; ?>" 
                                           class="text-yellow-600 hover:text-yellow-900" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <?php endif; ?>
                                        <?php if (AuthStatic::hasRole(['admin'])): ?>
                                        <button type="button" class="text-red-600 hover:text-red-900" 
                                                onclick="confirmDelete(<?php echo $biaya['id']; ?>, '<?php echo htmlspecialchars($biaya['nama_biaya']); ?>')" 
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
            <nav class="flex items-center justify-center mt-6" aria-label="Pagination">
                <div class="flex items-center space-x-1">
                    <?php if ($page > 1): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" 
                       class="inline-flex items-center px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-l-md hover:bg-gray-50 hover:text-gray-700">
                        <i class="fas fa-chevron-left mr-1"></i> Previous
                    </a>
                    <?php endif; ?>
                    
                    <?php
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    
                    for ($i = $start_page; $i <= $end_page; $i++):
                    ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
                       class="inline-flex items-center px-3 py-2 text-sm font-medium <?php echo $i === $page ? 'text-blue-600 bg-blue-50 border-blue-500' : 'text-gray-500 bg-white border-gray-300 hover:bg-gray-50 hover:text-gray-700'; ?> border">
                        <?php echo $i; ?>
                    </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" 
                       class="inline-flex items-center px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-r-md hover:bg-gray-50 hover:text-gray-700">
                        Next <i class="fas fa-chevron-right ml-1"></i>
                    </a>
                    <?php endif; ?>
                </div>
            </nav>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="fixed inset-0 z-50 overflow-y-auto hidden" aria-labelledby="deleteModalLabel" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"></div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                <div class="sm:flex sm:items-start">
                    <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                        <i class="fas fa-exclamation-triangle text-red-600"></i>
                    </div>
                    <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                        <h3 class="text-lg leading-6 font-medium text-gray-900" id="deleteModalLabel">Konfirmasi Hapus</h3>
                        <div class="mt-2">
                            <p class="text-sm text-gray-500">Apakah Anda yakin ingin menghapus biaya operasional <strong id="deleteItemName"></strong>?</p>
                            <p class="text-xs text-gray-400 mt-1">Tindakan ini tidak dapat dibatalkan.</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                <form method="POST" class="inline">
                    <input type="hidden" name="delete_id" id="deleteItemId">
                    <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:ml-3 sm:w-auto sm:text-sm">
                        Hapus
                    </button>
                </form>
                <button type="button" onclick="closeDeleteModal()" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                    Batal
                </button>
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