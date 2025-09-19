<?php
require_once 'config/database.php';
require_once 'config/auth.php';

// Cek autentikasi dan role
if (!AuthStatic::isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Cek role akses
if (!AuthStatic::hasRole(['admin', 'akunting'])) {
    header('Location: index.php?error=access_denied');
    exit;
}

$user = AuthStatic::getCurrentUser();
$db = getDatabase();
/** @var Database $db */

// Parameter pencarian dan filter
$search = trim($_GET['search'] ?? '');
$status_filter = $_GET['status'] ?? '';
$tanggal_dari = $_GET['tanggal_dari'] ?? '';
$tanggal_sampai = $_GET['tanggal_sampai'] ?? '';
$sort = $_GET['sort'] ?? 'tanggal_terima';
$order = $_GET['order'] ?? 'DESC';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// Build query
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(pb.nomor_penerimaan LIKE ? OR p.nomor_po LIKE ? OR v.nama_vendor LIKE ?)";
    $search_param = "%{$search}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if (!empty($tanggal_dari)) {
    $where_conditions[] = "pb.tanggal_terima >= ?";
    $params[] = $tanggal_dari;
}

if (!empty($tanggal_sampai)) {
    $where_conditions[] = "pb.tanggal_terima <= ?";
    $params[] = $tanggal_sampai;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Validasi sort column
$allowed_sorts = ['tanggal_terima', 'nomor_penerimaan', 'nomor_po', 'nama_vendor'];
if (!in_array($sort, $allowed_sorts)) {
    $sort = 'tanggal_terima';
}

$order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';

// Query untuk menghitung total
$count_query = "
    SELECT COUNT(*) as total
    FROM penerimaan_barang pb
    LEFT JOIN pembelian p ON pb.pembelian_id = p.id
    LEFT JOIN vendor v ON p.vendor_id = v.id
    {$where_clause}
";

$total_result = $db->select($count_query, $params);
$total_records = $total_result[0]['total'] ?? 0;
$total_pages = ceil($total_records / $limit);

// Query untuk mengambil data penerimaan
$penerimaan_query = "
    SELECT pb.*, p.nomor_po, v.nama_vendor, u.nama_lengkap as user_nama,
           COUNT(pd.id) as total_items,
           SUM(pd.quantity_terima) as total_quantity
    FROM penerimaan_barang pb
    LEFT JOIN pembelian p ON pb.pembelian_id = p.id
    LEFT JOIN vendor v ON p.vendor_id = v.id
    LEFT JOIN users u ON pb.user_id = u.id
    LEFT JOIN penerimaan_detail pd ON pb.id = pd.penerimaan_id
    {$where_clause}
    GROUP BY pb.id
    ORDER BY {$sort} {$order}
    LIMIT {$limit} OFFSET {$offset}
";

$penerimaan_list = $db->select($penerimaan_query, $params);

// Helper functions
function formatRupiah($amount) {
    return 'Rp ' . number_format($amount, 0, ',', '.');
}

function getStatusBadge($status) {
    switch ($status) {
        case 'diterima_lengkap':
            return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Lengkap</span>';
        case 'diterima_sebagian':
            return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">Sebagian</span>';
        default:
            return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">' . ucfirst($status) . '</span>';
    }
}

$page_title = 'Penerimaan Barang';
require_once 'layouts/header.php';
?>

<!-- Page Header -->
<div class="bg-white shadow-sm border-b border-gray-200 mb-6">
    <div class="px-6 py-4">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 flex items-center">
                    <i class="fa fa-truck text-primary-600 mr-3"></i>
                    Penerimaan Barang
                </h1>
                <p class="text-sm text-gray-600 mt-1">Kelola dan pantau penerimaan barang dari vendor</p>
            </div>
            <nav class="mt-4 sm:mt-0 text-sm text-gray-500">
                <a href="index.php" class="hover:text-primary-600">Dashboard</a>
                <span class="mx-2">/</span>
                <span class="text-gray-900">Penerimaan Barang</span>
            </nav>
        </div>
    </div>
</div>

<!-- Main Container -->
<div class="max-w-7xl ml-0 px-4 sm:px-6 lg:px-8 py-8">
    <!-- Filter dan Search -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
        <form method="GET" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label for="search" class="block text-sm font-medium text-gray-700 mb-2">Pencarian</label>
                    <input type="text" name="search" id="search" 
                           value="<?= htmlspecialchars($search) ?>"
                           placeholder="Nomor penerimaan, PO, vendor..."
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                </div>
                <div>
                    <label for="tanggal_dari" class="block text-sm font-medium text-gray-700 mb-2">Tanggal Dari</label>
                    <input type="date" name="tanggal_dari" id="tanggal_dari" 
                           value="<?= htmlspecialchars($tanggal_dari) ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                </div>
                <div>
                    <label for="tanggal_sampai" class="block text-sm font-medium text-gray-700 mb-2">Tanggal Sampai</label>
                    <input type="date" name="tanggal_sampai" id="tanggal_sampai" 
                           value="<?= htmlspecialchars($tanggal_sampai) ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                </div>
                <div class="flex items-end">
                    <button type="submit" class="w-full bg-primary-600 text-white px-4 py-2 rounded-lg hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 transition-colors">
                        <i class="fas fa-search mr-2"></i>Cari
                    </button>
                </div>
            </div>
            <input type="hidden" name="sort" value="<?= htmlspecialchars($sort) ?>">
            <input type="hidden" name="order" value="<?= htmlspecialchars($order) ?>">
        </form>
    </div>

    <!-- Statistik -->
    <?php
    $stats_query = "
        SELECT 
            COUNT(*) as total_penerimaan,
            COUNT(CASE WHEN DATE(pb.tanggal_terima) = CURDATE() THEN 1 END) as hari_ini,
            COUNT(CASE WHEN WEEK(pb.tanggal_terima) = WEEK(CURDATE()) AND YEAR(pb.tanggal_terima) = YEAR(CURDATE()) THEN 1 END) as minggu_ini,
            COUNT(CASE WHEN MONTH(pb.tanggal_terima) = MONTH(CURDATE()) AND YEAR(pb.tanggal_terima) = YEAR(CURDATE()) THEN 1 END) as bulan_ini
        FROM penerimaan_barang pb
    ";
    $stats = $db->select($stats_query)[0];
    ?>
    
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-truck text-blue-600"></i>
                    </div>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500">Total Penerimaan</p>
                    <p class="text-2xl font-semibold text-gray-900"><?= number_format($stats['total_penerimaan']) ?></p>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-green-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-calendar-day text-green-600"></i>
                    </div>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500">Hari Ini</p>
                    <p class="text-2xl font-semibold text-gray-900"><?= number_format($stats['hari_ini']) ?></p>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-yellow-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-calendar-week text-yellow-600"></i>
                    </div>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500">Minggu Ini</p>
                    <p class="text-2xl font-semibold text-gray-900"><?= number_format($stats['minggu_ini']) ?></p>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-purple-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-calendar-alt text-purple-600"></i>
                    </div>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500">Bulan Ini</p>
                    <p class="text-2xl font-semibold text-gray-900"><?= number_format($stats['bulan_ini']) ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabel Penerimaan -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                <h3 class="text-lg font-semibold text-gray-800">Daftar Penerimaan Barang</h3>
                <div class="mt-4 sm:mt-0 text-sm text-gray-500">
                    Menampilkan <?= number_format(count($penerimaan_list)) ?> dari <?= number_format($total_records) ?> data
                </div>
            </div>
        </div>
        
        <?php if (empty($penerimaan_list)): ?>
            <div class="text-center py-12">
                <i class="fas fa-truck text-gray-400 text-4xl mb-4"></i>
                <h3 class="text-lg font-medium text-gray-900 mb-2">Belum ada penerimaan barang</h3>
                <p class="text-gray-500">Penerimaan barang akan muncul di sini setelah Anda mencatat penerimaan dari purchase order.</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <a href="?<?= http_build_query(array_merge($_GET, ['sort' => 'nomor_penerimaan', 'order' => $sort === 'nomor_penerimaan' && $order === 'ASC' ? 'DESC' : 'ASC'])) ?>" class="group inline-flex items-center">
                                    Nomor Penerimaan
                                    <?php if ($sort === 'nomor_penerimaan'): ?>
                                        <i class="fas fa-sort-<?= $order === 'ASC' ? 'up' : 'down' ?> ml-1 text-gray-400"></i>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <a href="?<?= http_build_query(array_merge($_GET, ['sort' => 'tanggal_terima', 'order' => $sort === 'tanggal_terima' && $order === 'ASC' ? 'DESC' : 'ASC'])) ?>" class="group inline-flex items-center">
                                    Tanggal Terima
                                    <?php if ($sort === 'tanggal_terima'): ?>
                                        <i class="fas fa-sort-<?= $order === 'ASC' ? 'up' : 'down' ?> ml-1 text-gray-400"></i>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <a href="?<?= http_build_query(array_merge($_GET, ['sort' => 'nomor_po', 'order' => $sort === 'nomor_po' && $order === 'ASC' ? 'DESC' : 'ASC'])) ?>" class="group inline-flex items-center">
                                    Purchase Order
                                    <?php if ($sort === 'nomor_po'): ?>
                                        <i class="fas fa-sort-<?= $order === 'ASC' ? 'up' : 'down' ?> ml-1 text-gray-400"></i>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <a href="?<?= http_build_query(array_merge($_GET, ['sort' => 'nama_vendor', 'order' => $sort === 'nama_vendor' && $order === 'ASC' ? 'DESC' : 'ASC'])) ?>" class="group inline-flex items-center">
                                    Vendor
                                    <?php if ($sort === 'nama_vendor'): ?>
                                        <i class="fas fa-sort-<?= $order === 'ASC' ? 'up' : 'down' ?> ml-1 text-gray-400"></i>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Items</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Diterima Oleh</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($penerimaan_list as $penerimaan): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($penerimaan['nomor_penerimaan']) ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900"><?= date('d/m/Y', strtotime($penerimaan['tanggal_terima'])) ?></div>
                                <div class="text-sm text-gray-500"><?= date('H:i', strtotime($penerimaan['created_at'] ?? $penerimaan['tanggal_terima'])) ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($penerimaan['nomor_po']) ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900"><?= htmlspecialchars($penerimaan['nama_vendor']) ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900"><?= $penerimaan['total_items'] ?> items</div>
                                <div class="text-sm text-gray-500"><?= $penerimaan['total_quantity'] ?> qty</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900"><?= htmlspecialchars($penerimaan['user_nama']) ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                                <a href="penerimaan-view.php?id=<?= $penerimaan['id'] ?>" 
                                   class="bg-blue-500 hover:bg-blue-600 text-white p-2 rounded-lg" 
                                   title="Lihat Detail">
                                    <i class="fa fa-eye"></i>
                                </a>
                                <?php if (!empty($penerimaan['catatan'])): ?>
                                <span class="bg-gray-500 text-white p-2 rounded-lg" title="<?= htmlspecialchars($penerimaan['catatan']) ?>">
                                    <i class="fa fa-comment"></i>
                                </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
                <div class="flex items-center justify-between">
                    <div class="flex-1 flex justify-between sm:hidden">
                        <?php if ($page > 1): ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">Previous</a>
                        <?php endif; ?>
                        <?php if ($page < $total_pages): ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">Next</a>
                        <?php endif; ?>
                    </div>
                    <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                        <div>
                            <p class="text-sm text-gray-700">
                                Menampilkan <span class="font-medium"><?= ($page - 1) * $limit + 1 ?></span> sampai <span class="font-medium"><?= min($page * $limit, $total_records) ?></span> dari <span class="font-medium"><?= $total_records ?></span> hasil
                            </p>
                        </div>
                        <div>
                            <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                                <?php if ($page > 1): ?>
                                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                <?php endif; ?>
                                
                                <?php
                                $start_page = max(1, $page - 2);
                                $end_page = min($total_pages, $page + 2);
                                
                                for ($i = $start_page; $i <= $end_page; $i++):
                                ?>
                                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium <?= $i === $page ? 'text-primary-600 bg-primary-50 border-primary-500' : 'text-gray-700 hover:bg-gray-50' ?>">
                                        <?= $i ?>
                                    </a>
                                <?php endfor; ?>
                                
                                <?php if ($page < $total_pages): ?>
                                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                        <i class="fas fa-chevron-right"></i>
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

<?php require_once 'layouts/footer.php'; ?>