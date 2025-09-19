<?php
require_once 'config/database.php';
require_once 'config/auth.php';

// Cek autentikasi
if (!AuthStatic::isLoggedIn()) {
    header('Location: login.php');
    exit;
}

if (!AuthStatic::hasRole(['admin', 'manager'])) {
    header('Location: index.php?error=' . urlencode('Anda tidak memiliki akses ke halaman ini.'));
    exit;
}

$user = AuthStatic::getCurrentUser();
$db = getDatabase();
/** @var Database $db */

// Filter parameters
$produk_filter = $_GET['produk_id'] ?? '';
$tanggal_dari = $_GET['tanggal_dari'] ?? date('Y-m-01'); // Awal bulan ini
$tanggal_sampai = $_GET['tanggal_sampai'] ?? date('Y-m-d'); // Hari ini
$jenis_movement = $_GET['jenis_movement'] ?? '';
$search = $_GET['search'] ?? '';

// Build query conditions
$where_conditions = [];
$params = [];

if (!empty($produk_filter)) {
    $where_conditions[] = "sm.produk_id = ?";
    $params[] = $produk_filter;
}

if (!empty($tanggal_dari)) {
    $where_conditions[] = "DATE(sm.created_at) >= ?";
    $params[] = $tanggal_dari;
}

if (!empty($tanggal_sampai)) {
    $where_conditions[] = "DATE(sm.created_at) <= ?";
    $params[] = $tanggal_sampai;
}

if (!empty($jenis_movement)) {
    $where_conditions[] = "sm.jenis_movement = ?";
    $params[] = $jenis_movement;
}

if (!empty($search)) {
    $where_conditions[] = "(p.nama_produk LIKE ? OR sm.keterangan LIKE ? OR t.nomor_invoice LIKE ?)";
    $search_param = "%{$search}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Query stock movements dengan detail
$movements_query = "
    SELECT 
        sm.*,
        p.nama_produk,
        p.harga_jual,
        p.stok_tersedia,
        t.nomor_invoice,
        t.tanggal_transaksi,
        d.nama_desa,
        u.nama_lengkap as user_name,
        CASE 
            WHEN sm.jenis_movement = 'masuk' THEN sm.quantity
            ELSE 0
        END as stok_masuk,
        CASE 
            WHEN sm.jenis_movement = 'keluar' THEN sm.quantity
            ELSE 0
        END as stok_keluar
    FROM stock_movement sm
    JOIN produk p ON sm.produk_id = p.id
    LEFT JOIN transaksi t ON sm.transaksi_id = t.id
    LEFT JOIN desa d ON t.desa_id = d.id
    LEFT JOIN users u ON sm.user_id = u.id
    {$where_clause}
    ORDER BY sm.created_at DESC
    LIMIT 500
";

$movements_data = $db->select($movements_query, $params);

// Summary statistics
$stats_query = "
    SELECT 
        COUNT(*) as total_movements,
        SUM(CASE WHEN sm.jenis_movement = 'masuk' THEN sm.quantity ELSE 0 END) as total_masuk,
        SUM(CASE WHEN sm.jenis_movement = 'keluar' THEN sm.quantity ELSE 0 END) as total_keluar,
        COUNT(DISTINCT sm.produk_id) as produk_terlibat
    FROM stock_movement sm
    JOIN produk p ON sm.produk_id = p.id
    LEFT JOIN transaksi t ON sm.transaksi_id = t.id
    {$where_clause}
";

$stats = $db->select($stats_query, $params)[0];

// Top produk dengan movement terbanyak
$top_products_query = "
    SELECT 
        p.id,
        p.nama_produk,
        p.stok_tersedia,
        SUM(CASE WHEN sm.jenis_movement = 'masuk' THEN sm.quantity ELSE 0 END) as total_masuk,
        SUM(CASE WHEN sm.jenis_movement = 'keluar' THEN sm.quantity ELSE 0 END) as total_keluar,
        COUNT(*) as total_movements
    FROM stock_movement sm
    JOIN produk p ON sm.produk_id = p.id
    LEFT JOIN transaksi t ON sm.transaksi_id = t.id
    {$where_clause}
    GROUP BY p.id, p.nama_produk, p.stok_tersedia
    ORDER BY total_movements DESC
    LIMIT 10
";

$top_products = $db->select($top_products_query, $params);

// Data untuk dropdown
$produk_list = $db->select("SELECT id, nama_produk, stok_tersedia FROM produk WHERE status = 'aktif' ORDER BY nama_produk");

// Helper functions
function formatRupiah($amount) {
    return 'Rp ' . number_format($amount ?? 0, 0, ',', '.');
}

function formatTanggal($date) {
    return $date ? date('d/m/Y H:i', strtotime($date)) : '-';
}

function getMovementClass($jenis) {
    return $jenis === 'masuk' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800';
}

function getMovementIcon($jenis) {
    return $jenis === 'masuk' ? 'fas fa-arrow-up' : 'fas fa-arrow-down';
}

$page_title = 'Tracking Stock Movement';
require_once 'layouts/header.php';
?>

<div class="min-h-screen bg-gray-50 py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Page Header -->
        <div class="mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Tracking Stock Movement</h1>
                    <p class="mt-2 text-gray-600">Monitor pergerakan stok produk dari transaksi</p>
                </div>
                <div class="flex space-x-3">
                    <a href="produk.php" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition duration-200">
                        <i class="fas fa-box mr-2"></i>Kelola Produk
                    </a>
                    <a href="transaksi.php" class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 transition duration-200">
                        <i class="fas fa-list mr-2"></i>Transaksi
                    </a>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-exchange-alt text-blue-600"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Total Movement</p>
                        <p class="text-2xl font-semibold text-gray-900"><?= number_format($stats['total_movements']) ?></p>
                        <p class="text-sm text-gray-600">Pergerakan stok</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-arrow-up text-green-600"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Stok Masuk</p>
                        <p class="text-2xl font-semibold text-gray-900"><?= number_format($stats['total_masuk']) ?></p>
                        <p class="text-sm text-gray-600">Unit produk</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-red-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-arrow-down text-red-600"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Stok Keluar</p>
                        <p class="text-2xl font-semibold text-gray-900"><?= number_format($stats['total_keluar']) ?></p>
                        <p class="text-sm text-gray-600">Unit produk</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-purple-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-cubes text-purple-600"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Produk Terlibat</p>
                        <p class="text-2xl font-semibold text-gray-900"><?= number_format($stats['produk_terlibat']) ?></p>
                        <p class="text-sm text-gray-600">Jenis produk</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter Panel -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-8">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Filter Data</h3>
            <form method="GET" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <!-- Produk Filter -->
                    <div>
                        <label for="produk_id" class="block text-sm font-medium text-gray-700 mb-1">Produk</label>
                        <select id="produk_id" name="produk_id" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Semua Produk</option>
                            <?php foreach ($produk_list as $produk): ?>
                            <option value="<?= $produk['id'] ?>" <?= $produk_filter == $produk['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($produk['nama_produk']) ?> (Stok: <?= $produk['stok_tersedia'] ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Jenis Movement Filter -->
                    <div>
                        <label for="jenis_movement" class="block text-sm font-medium text-gray-700 mb-1">Jenis Movement</label>
                        <select id="jenis_movement" name="jenis_movement" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Semua Jenis</option>
                            <option value="masuk" <?= $jenis_movement === 'masuk' ? 'selected' : '' ?>>Stok Masuk</option>
                            <option value="keluar" <?= $jenis_movement === 'keluar' ? 'selected' : '' ?>>Stok Keluar</option>
                        </select>
                    </div>

                    <!-- Search -->
                    <div>
                        <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Pencarian</label>
                        <input type="text" id="search" name="search" value="<?= htmlspecialchars($search) ?>"
                               placeholder="Produk, invoice, keterangan..."
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Tanggal Dari -->
                    <div>
                        <label for="tanggal_dari" class="block text-sm font-medium text-gray-700 mb-1">Tanggal Dari</label>
                        <input type="date" id="tanggal_dari" name="tanggal_dari" value="<?= htmlspecialchars($tanggal_dari) ?>"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>

                    <!-- Tanggal Sampai -->
                    <div>
                        <label for="tanggal_sampai" class="block text-sm font-medium text-gray-700 mb-1">Tanggal Sampai</label>
                        <input type="date" id="tanggal_sampai" name="tanggal_sampai" value="<?= htmlspecialchars($tanggal_sampai) ?>"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>

                <div class="flex space-x-3">
                    <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition duration-200">
                        <i class="fas fa-search mr-2"></i>Filter
                    </button>
                    <a href="?" class="bg-gray-600 text-white px-6 py-2 rounded-lg hover:bg-gray-700 transition duration-200">
                        <i class="fas fa-refresh mr-2"></i>Reset
                    </a>
                    <button type="button" onclick="exportData()" class="bg-green-600 text-white px-6 py-2 rounded-lg hover:bg-green-700 transition duration-200">
                        <i class="fas fa-download mr-2"></i>Export CSV
                    </button>
                </div>
            </form>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Stock Movements Table -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900">
                            Riwayat Stock Movement (<?= number_format(count($movements_data)) ?> record)
                        </h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Waktu</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Produk</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Jenis</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quantity</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Invoice</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Keterangan</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (empty($movements_data)): ?>
                                <tr>
                                    <td colspan="6" class="px-6 py-12 text-center text-gray-500">
                                        <i class="fas fa-inbox text-4xl mb-4"></i>
                                        <p>Tidak ada data stock movement</p>
                                    </td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($movements_data as $row): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?= formatTanggal($row['created_at']) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <div class="font-medium text-gray-900"><?= htmlspecialchars($row['nama_produk']) ?></div>
                                        <div class="text-gray-500">Stok: <?= number_format($row['stok_tersedia']) ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= getMovementClass($row['jenis_movement']) ?>">
                                            <i class="<?= getMovementIcon($row['jenis_movement']) ?> mr-1"></i>
                                            <?= ucfirst($row['jenis_movement']) ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <span class="<?= $row['jenis_movement'] === 'masuk' ? 'text-green-600' : 'text-red-600' ?>">
                                            <?= $row['jenis_movement'] === 'masuk' ? '+' : '-' ?><?= number_format($row['quantity']) ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <?php if ($row['nomor_invoice']): ?>
                                        <a href="transaksi-view.php?id=<?= $row['transaksi_id'] ?>" class="text-blue-600 hover:text-blue-800">
                                            <?= htmlspecialchars($row['nomor_invoice']) ?>
                                        </a>
                                        <div class="text-gray-500"><?= htmlspecialchars($row['nama_desa']) ?></div>
                                        <?php else: ?>
                                        <span class="text-gray-500">Manual</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900">
                                        <?= htmlspecialchars($row['keterangan']) ?>
                                        <?php if ($row['user_name']): ?>
                                        <div class="text-xs text-gray-500">oleh: <?= htmlspecialchars($row['user_name']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Top Products Sidebar -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900">Top Produk Movement</h3>
                    </div>
                    <div class="p-6">
                        <?php if (empty($top_products)): ?>
                        <p class="text-gray-500 text-center">Tidak ada data</p>
                        <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($top_products as $index => $produk): ?>
                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                <div class="flex-1">
                                    <div class="flex items-center">
                                        <span class="w-6 h-6 bg-blue-100 text-blue-800 rounded-full flex items-center justify-center text-xs font-medium mr-3">
                                            <?= $index + 1 ?>
                                        </span>
                                        <div>
                                            <p class="text-sm font-medium text-gray-900"><?= htmlspecialchars($produk['nama_produk']) ?></p>
                                            <p class="text-xs text-gray-500">Stok: <?= number_format($produk['stok_tersedia']) ?></p>
                                        </div>
                                    </div>
                                    <div class="mt-2 flex space-x-4 text-xs">
                                        <span class="text-green-600">
                                            <i class="fas fa-arrow-up mr-1"></i><?= number_format($produk['total_masuk']) ?>
                                        </span>
                                        <span class="text-red-600">
                                            <i class="fas fa-arrow-down mr-1"></i><?= number_format($produk['total_keluar']) ?>
                                        </span>
                                        <span class="text-gray-600">
                                            <i class="fas fa-exchange-alt mr-1"></i><?= number_format($produk['total_movements']) ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Quick Stats -->
                <div class="mt-6 bg-white rounded-lg shadow-sm border border-gray-200">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900">Ringkasan</h3>
                    </div>
                    <div class="p-6">
                        <div class="space-y-4">
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-600">Net Movement:</span>
                                <span class="text-sm font-medium <?= ($stats['total_masuk'] - $stats['total_keluar']) >= 0 ? 'text-green-600' : 'text-red-600' ?>">
                                    <?= ($stats['total_masuk'] - $stats['total_keluar']) >= 0 ? '+' : '' ?><?= number_format($stats['total_masuk'] - $stats['total_keluar']) ?>
                                </span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-600">Turnover Rate:</span>
                                <span class="text-sm font-medium text-gray-900">
                                    <?= $stats['total_masuk'] > 0 ? number_format(($stats['total_keluar'] / $stats['total_masuk']) * 100, 1) : 0 ?>%
                                </span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-600">Avg per Movement:</span>
                                <span class="text-sm font-medium text-gray-900">
                                    <?= $stats['total_movements'] > 0 ? number_format(($stats['total_masuk'] + $stats['total_keluar']) / $stats['total_movements'], 1) : 0 ?> unit
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function exportData() {
    // Get current filter parameters
    const params = new URLSearchParams(window.location.search);
    params.set('export', 'csv');
    
    // Create export URL
    const exportUrl = 'stock-tracking-export.php?' + params.toString();
    
    // Open in new window
    window.open(exportUrl, '_blank');
}

// Auto-refresh every 30 seconds if no filters are applied
if (window.location.search === '' || window.location.search === '?') {
    setTimeout(function() {
        location.reload();
    }, 30000);
}
</script>

<?php require_once 'layouts/footer.php'; ?>