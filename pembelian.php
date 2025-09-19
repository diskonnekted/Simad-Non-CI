<?php
require_once 'config/database.php';
require_once 'includes/auth.php';

// Check authentication
AuthStatic::checkAuth();

// Check if this is statistics view
$view = $_GET['view'] ?? '';
if ($view === 'statistik') {
    // Redirect to statistics page or include statistics content
    $page_title = 'Statistik Pembelian';
    require_once 'layouts/header.php';
    include 'pembelian-statistik.php';
    require_once 'layouts/footer.php';
    exit;
}

// Get parameters
$search = $_GET['search'] ?? '';
$vendor_filter = $_GET['vendor_id'] ?? '';
$status_filter = $_GET['status'] ?? '';
$payment_filter = $_GET['payment_status'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$sort_by = $_GET['sort_by'] ?? 'tanggal_pembelian';
$sort_order = $_GET['sort_order'] ?? 'DESC';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// Get success/error messages
$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';

// Build WHERE clause
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(p.nomor_po LIKE ? OR v.nama_vendor LIKE ? OR p.catatan LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if (!empty($vendor_filter)) {
    $where_conditions[] = "p.vendor_id = ?";
    $params[] = $vendor_filter;
}

if (!empty($status_filter)) {
    $where_conditions[] = "p.status_pembelian = ?";
    $params[] = $status_filter;
}

if (!empty($payment_filter)) {
    $where_conditions[] = "p.status_pembayaran = ?";
    $params[] = $payment_filter;
}

if (!empty($date_from)) {
    $where_conditions[] = "p.tanggal_pembelian >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $where_conditions[] = "p.tanggal_pembelian <= ?";
    $params[] = $date_to;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total records for pagination
$count_sql = "SELECT COUNT(*) as total FROM pembelian p 
              LEFT JOIN vendor v ON p.vendor_id = v.id 
              $where_clause";
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_records = $count_stmt->fetch()['total'];
$total_pages = ceil($total_records / $limit);

// Get pembelian data
$sql = "SELECT p.*, v.nama_vendor, v.kode_vendor, 
               COALESCE(p.total_amount, 0) - COALESCE(p.dp_amount, 0) - COALESCE(payments.total_paid, 0) as total_hutang
        FROM pembelian p
        LEFT JOIN vendor v ON p.vendor_id = v.id
        LEFT JOIN (
            SELECT pembelian_id, SUM(jumlah_bayar) as total_paid
            FROM pembayaran_pembelian
            GROUP BY pembelian_id
        ) payments ON p.id = payments.pembelian_id
        $where_clause
        ORDER BY $sort_by $sort_order
        LIMIT $limit OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$pembelian_list = $stmt->fetchAll();

// Get vendor list for filter
$vendor_sql = "SELECT id, nama_vendor FROM vendor ORDER BY nama_vendor";
$vendor_stmt = $pdo->query($vendor_sql);
$vendor_list = $vendor_stmt->fetchAll();

// Get statistics
$stats_sql = "SELECT 
    COUNT(*) as total_pembelian,
    COUNT(CASE WHEN status_pembelian = 'diterima_lengkap' THEN 1 END) as pembelian_selesai,
    COALESCE(SUM(total_amount), 0) as total_nilai,
    COALESCE(SUM(CASE WHEN status_pembayaran != 'lunas' THEN total_amount - COALESCE(dp_amount, 0) ELSE 0 END), 0) as total_hutang
    FROM pembelian p";
$stats_stmt = $pdo->query($stats_sql);
$stats = $stats_stmt->fetch();

// Get monthly statistics (last 6 months)
$monthly_sql = "SELECT 
    DATE_FORMAT(tanggal_pembelian, '%Y-%m') as bulan,
    DATE_FORMAT(tanggal_pembelian, '%M %Y') as bulan_nama,
    COUNT(*) as jumlah_pembelian,
    COALESCE(SUM(total_amount), 0) as total_nilai
    FROM pembelian 
    WHERE tanggal_pembelian >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(tanggal_pembelian, '%Y-%m')
    ORDER BY bulan DESC
    LIMIT 6";
$monthly_stmt = $pdo->query($monthly_sql);
$monthly_stats = $monthly_stmt->fetchAll();

// Get top vendors (last 6 months)
$vendor_stats_sql = "SELECT 
    v.nama_vendor,
    COUNT(p.id) as jumlah_pembelian,
    COALESCE(SUM(p.total_amount), 0) as total_nilai
    FROM pembelian p
    LEFT JOIN vendor v ON p.vendor_id = v.id
    WHERE p.tanggal_pembelian >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY p.vendor_id, v.nama_vendor
    ORDER BY total_nilai DESC
    LIMIT 5";
$vendor_stats_stmt = $pdo->query($vendor_stats_sql);
$top_vendors = $vendor_stats_stmt->fetchAll();

// Helper functions
function getStatusBadge($status) {
    switch ($status) {
        case 'draft': return 'bg-gray-100 text-gray-800';
        case 'dikirim': return 'bg-blue-100 text-blue-800';
        case 'diterima_sebagian': return 'bg-yellow-100 text-yellow-800';
        case 'diterima_lengkap': return 'bg-green-100 text-green-800';
        case 'dibatalkan': return 'bg-red-100 text-red-800';
        default: return 'bg-gray-100 text-gray-800';
    }
}

function getStatusText($status) {
    switch ($status) {
        case 'draft': return 'Draft';
        case 'dikirim': return 'Dikirim';
        case 'diterima_sebagian': return 'Diterima Sebagian';
        case 'diterima_lengkap': return 'Diterima Lengkap';
        case 'dibatalkan': return 'Dibatalkan';
        default: return ucfirst($status);
    }
}

function getPaymentBadge($status) {
    switch ($status) {
        case 'belum_bayar': return 'bg-red-100 text-red-800';
        case 'dp': return 'bg-yellow-100 text-yellow-800';
        case 'lunas': return 'bg-green-100 text-green-800';
        default: return 'bg-gray-100 text-gray-800';
    }
}

function getPaymentText($status) {
    switch ($status) {
        case 'belum_bayar': return 'Belum Bayar';
        case 'dp': return 'DP';
        case 'lunas': return 'Lunas';
        default: return ucfirst($status);
    }}

// Set page title
$page_title = 'Daftar Pembelian';

// Include header
require_once 'layouts/header.php';
?>

<!-- Pembelian Content -->
<div class="ml-0 px-2 sm:px-4 lg:px-6 py-8 max-w-screen-xl">
    <!-- Page Header -->
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Daftar Pembelian</h1>
                <p class="text-gray-600 mt-2">Kelola purchase order dan pembelian barang</p>
            </div>
            <div class="flex space-x-3">
                <a href="export-pembelian.php" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                    <i class="fas fa-download mr-2"></i>
                    Export
                </a>
                <a href="tambah-pembelian.php" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                    <i class="fas fa-plus mr-2"></i>
                    Tambah Pembelian
                </a>
            </div>
        </div>
    </div>
            
            <!-- Success/Error Messages -->
            <?php if (!empty($success)): ?>
                <div class="mb-6 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-check-circle text-green-400"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium">
                                <?php echo htmlspecialchars($success); ?>
                            </p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($error)): ?>
                <div class="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-exclamation-circle text-red-400"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium">
                                <?php echo htmlspecialchars($error); ?>
                            </p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <!-- Total Pembelian -->
            <div class="bg-gradient-to-r from-blue-500 to-blue-600 rounded-2xl shadow-xl p-6 min-h-[160px]">
                <div class="flex items-center justify-between h-full">
                    <div class="flex-1">
                        <dl>
                            <dt class="text-sm font-semibold text-blue-100 mb-2">Total Pembelian</dt>
                            <dd class="text-3xl font-bold text-white mb-2"><?= number_format($stats['total_pembelian']) ?></dd>
                        </dl>
                        <div class="flex items-center">
                            <i class="fas fa-chart-line text-blue-200 mr-2 text-sm"></i>
                            <span class="text-blue-200 text-sm font-medium">Purchase Orders</span>
                        </div>
                    </div>
                    <div class="flex-shrink-0 ml-4">
                        <i class="fas fa-shopping-cart text-white text-4xl opacity-80"></i>
                    </div>
                </div>
            </div>

            <!-- Pembelian Selesai -->
            <div class="bg-gradient-to-r from-green-500 to-green-600 rounded-2xl shadow-xl p-6 min-h-[160px]">
                <div class="flex items-center justify-between h-full">
                    <div class="flex-1">
                        <dl>
                            <dt class="text-sm font-semibold text-green-100 mb-2">Pembelian Selesai</dt>
                            <dd class="text-3xl font-bold text-white mb-2"><?= number_format($stats['pembelian_selesai']) ?></dd>
                        </dl>
                        <div class="flex items-center">
                            <i class="fas fa-truck text-green-200 mr-2 text-sm"></i>
                            <span class="text-green-200 text-sm font-medium">Diterima Lengkap</span>
                        </div>
                    </div>
                    <div class="flex-shrink-0 ml-4">
                        <i class="fas fa-check-circle text-white text-4xl opacity-80"></i>
                    </div>
                </div>
            </div>

            <!-- Total Nilai Pembelian -->
            <div class="bg-gradient-to-r from-purple-500 to-purple-600 rounded-2xl shadow-xl p-6 min-h-[160px]">
                <div class="flex items-center justify-between h-full">
                    <div class="flex-1">
                        <dl>
                            <dt class="text-sm font-semibold text-purple-100 mb-2">Total Nilai</dt>
                            <dd class="text-2xl font-bold text-white mb-2">Rp <?= number_format($stats['total_nilai'], 0, ',', '.') ?></dd>
                        </dl>
                        <div class="flex items-center">
                            <i class="fas fa-calculator text-purple-200 mr-2 text-sm"></i>
                            <span class="text-purple-200 text-sm font-medium">Total Pembelian</span>
                        </div>
                    </div>
                    <div class="flex-shrink-0 ml-4">
                        <i class="fas fa-money-bill-wave text-white text-4xl opacity-80"></i>
                    </div>
                </div>
            </div>

            <!-- Total Hutang -->
            <div class="bg-gradient-to-r from-red-500 to-red-600 rounded-2xl shadow-xl p-6 min-h-[160px]">
                <div class="flex items-center justify-between h-full">
                    <div class="flex-1">
                        <dl>
                            <dt class="text-sm font-semibold text-red-100 mb-2">Total Hutang</dt>
                            <dd class="text-2xl font-bold text-white mb-2">Rp <?= number_format($stats['total_hutang'], 0, ',', '.') ?></dd>
                        </dl>
                        <div class="flex items-center">
                            <i class="fas fa-clock text-red-200 mr-2 text-sm"></i>
                            <span class="text-red-200 text-sm font-medium">Outstanding</span>
                        </div>
                    </div>
                    <div class="flex-shrink-0 ml-4">
                        <i class="fas fa-exclamation-triangle text-white text-4xl opacity-80"></i>
                    </div>
                </div>
            </div>
        </div>



        <!-- Messages -->
        <?php if ($success): ?>
            <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
                <div class="flex items-center">
                    <i class="fa fa-check text-green-500 mr-3"></i>
                    <span class="text-green-800">
                        <?php if ($success === 'created'): ?>
                            Purchase Order berhasil dibuat!
                        <?php elseif ($success === 'updated'): ?>
                            Purchase Order berhasil diperbarui!
                        <?php elseif ($success === 'deleted'): ?>
                            Purchase Order berhasil dihapus!
                        <?php elseif ($success === 'received'): ?>
                            Penerimaan barang berhasil dicatat!
                        <?php elseif ($success === 'paid'): ?>
                            Pembayaran berhasil dicatat!
                        <?php endif; ?>
                    </span>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
                <div class="flex items-center">
                    <i class="fa fa-exclamation-triangle text-red-500 mr-3"></i>
                    <span class="text-red-800"><?= htmlspecialchars($error) ?></span>
                </div>
            </div>
        <?php endif; ?>

        <!-- Filter dan Pencarian -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
            <form method="GET" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Pencarian</label>
                        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
                               placeholder="Cari nomor PO, vendor, catatan..." 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Vendor</label>
                        <select name="vendor_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                            <option value="">Semua Vendor</option>
                            <?php foreach ($vendor_list as $vendor): ?>
                                <option value="<?= $vendor['id'] ?>" <?= $vendor_filter == $vendor['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($vendor['nama_vendor']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Status Pembelian</label>
                        <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                            <option value="">Semua Status</option>
                            <option value="draft" <?= $status_filter === 'draft' ? 'selected' : '' ?>>Draft</option>
                            <option value="dikirim" <?= $status_filter === 'dikirim' ? 'selected' : '' ?>>Dikirim</option>
                            <option value="diterima_sebagian" <?= $status_filter === 'diterima_sebagian' ? 'selected' : '' ?>>Diterima Sebagian</option>
                            <option value="diterima_lengkap" <?= $status_filter === 'diterima_lengkap' ? 'selected' : '' ?>>Diterima Lengkap</option>
                            <option value="dibatalkan" <?= $status_filter === 'dibatalkan' ? 'selected' : '' ?>>Dibatalkan</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Status Pembayaran</label>
                        <select name="payment_status" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                            <option value="">Semua Status</option>
                            <option value="belum_bayar" <?= $payment_filter === 'belum_bayar' ? 'selected' : '' ?>>Belum Bayar</option>
                            <option value="dp" <?= $payment_filter === 'dp' ? 'selected' : '' ?>>DP</option>
                            <option value="lunas" <?= $payment_filter === 'lunas' ? 'selected' : '' ?>>Lunas</option>
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Tanggal Dari</label>
                        <input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Tanggal Sampai</label>
                        <input type="date" name="date_to" value="<?= htmlspecialchars($date_to) ?>" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Urutkan</label>
                        <select name="sort_by" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                            <option value="tanggal_pembelian" <?= $sort_by === 'tanggal_pembelian' ? 'selected' : '' ?>>Tanggal</option>
                            <option value="nomor_po" <?= $sort_by === 'nomor_po' ? 'selected' : '' ?>>Nomor PO</option>
                            <option value="nama_vendor" <?= $sort_by === 'nama_vendor' ? 'selected' : '' ?>>Vendor</option>
                            <option value="total_amount" <?= $sort_by === 'total_amount' ? 'selected' : '' ?>>Total</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Urutan</label>
                        <select name="sort_order" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                            <option value="DESC" <?= $sort_order === 'DESC' ? 'selected' : '' ?>>Terbaru</option>
                            <option value="ASC" <?= $sort_order === 'ASC' ? 'selected' : '' ?>>Terlama</option>
                        </select>
                    </div>
                </div>
                <div class="flex flex-wrap gap-3 items-end">
                    <button type="submit" class="bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-lg flex items-center">
                        <i class="fa fa-search mr-2"></i> Cari
                    </button>
                    <a href="pembelian.php" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg flex items-center">
                        <i class="fa fa-refresh mr-2"></i> Reset
                    </a>
                    <?php if (AuthStatic::hasRole(['admin', 'akunting'])): ?>
                    <a href="pembelian-add.php" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg flex items-center" style="margin-left: 2px;">
                        <i class="fa fa-plus mr-2"></i> Buat PO Baru
                    </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Tabel Pembelian -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <a href="?<?= http_build_query(array_merge($_GET, ['sort_by' => 'nomor_po', 'sort_order' => $sort_by === 'nomor_po' && $sort_order === 'ASC' ? 'DESC' : 'ASC'])) ?>" class="flex items-center hover:text-gray-700">
                                    Nomor PO
                                    <i class="fa fa-sort ml-1 text-gray-400"></i>
                                </a>
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <a href="?<?= http_build_query(array_merge($_GET, ['sort_by' => 'tanggal_pembelian', 'sort_order' => $sort_by === 'tanggal_pembelian' && $sort_order === 'ASC' ? 'DESC' : 'ASC'])) ?>" class="flex items-center hover:text-gray-700">
                                    Tanggal
                                    <i class="fa fa-sort ml-1 text-gray-400"></i>
                                </a>
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <a href="?<?= http_build_query(array_merge($_GET, ['sort_by' => 'nama_vendor', 'sort_order' => $sort_by === 'nama_vendor' && $sort_order === 'ASC' ? 'DESC' : 'ASC'])) ?>" class="flex items-center hover:text-gray-700">
                                    Vendor
                                    <i class="fa fa-sort ml-1 text-gray-400"></i>
                                </a>
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <a href="?<?= http_build_query(array_merge($_GET, ['sort_by' => 'total_amount', 'sort_order' => $sort_by === 'total_amount' && $sort_order === 'ASC' ? 'DESC' : 'ASC'])) ?>" class="flex items-center hover:text-gray-700">
                                    Total
                                    <i class="fa fa-sort ml-1 text-gray-400"></i>
                                </a>
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Pembayaran
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Status
                            </th>

                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Hutang
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Aksi
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($pembelian_list)): ?>
                            <tr>
                                <td colspan="8" class="px-6 py-12 text-center text-gray-500">
                                    <i class="fas fa-shopping-cart text-4xl mb-4 text-gray-300"></i>
                                    <p class="text-lg font-medium">Belum ada data pembelian</p>
                                    <p class="text-sm">Klik "Buat Purchase Order" untuk menambah pembelian baru</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($pembelian_list as $p): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($p['nomor_po']) ?></div>
                                    <div class="text-sm text-gray-500"><?= htmlspecialchars($p['kode_vendor']) ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?= date('d/m/Y', strtotime($p['tanggal_pembelian'])) ?>
                                    <?php if ($p['tanggal_dibutuhkan']): ?>
                                        <div class="text-xs text-gray-500">Butuh: <?= date('d/m/Y', strtotime($p['tanggal_dibutuhkan'])) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($p['nama_vendor']) ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    Rp <?= number_format($p['total_amount'], 0, ',', '.') ?>
                                    <?php if ($p['dp_amount'] > 0): ?>
                                        <div class="text-xs text-gray-500">DP: Rp <?= number_format($p['dp_amount'], 0, ',', '.') ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 py-1 text-xs font-medium rounded-full <?= getPaymentBadge($p['status_pembayaran']) ?>">
                                        <?= getPaymentText($p['status_pembayaran']) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 py-1 text-xs font-medium rounded-full <?= getStatusBadge($p['status_pembelian']) ?>">
                                        <?= getStatusText($p['status_pembelian']) ?>
                                    </span>
                                </td>

                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php 
                                    // Jika status pembayaran lunas, hutang = 0
                                    $hutang_aktual = ($p['status_pembayaran'] === 'lunas') ? 0 : $p['total_hutang'];
                                    ?>
                                    <?php if ($hutang_aktual > 0): ?>
                                        <span class="text-red-600 font-medium">Rp <?= number_format($hutang_aktual, 0, ',', '.') ?></span>
                                    <?php else: ?>
                                        <span class="text-green-600 font-medium">Lunas</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex space-x-2">
                                        <a href="pembelian-view.php?id=<?= $p['id'] ?>" 
                                           class="bg-blue-500 hover:bg-blue-600 text-white p-2 rounded-lg" 
                                           title="Lihat Detail">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if (AuthStatic::hasRole(['admin', 'akunting']) && $p['status_pembelian'] === 'draft'): ?>
                                        <a href="pembelian-edit.php?id=<?= $p['id'] ?>" 
                                           class="bg-yellow-500 hover:bg-yellow-600 text-white p-2 rounded-lg" 
                                           title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <?php endif; ?>
                                        <?php if ($p['status_pembelian'] === 'dikirim' && AuthStatic::hasRole(['admin', 'akunting'])): ?>
                                        <button onclick="showKonfirmasiModal(<?= $p['id'] ?>, '<?= htmlspecialchars($p['nomor_po']) ?>', '<?= htmlspecialchars($p['nama_vendor']) ?>')" 
                                                class="bg-green-500 hover:bg-green-600 text-white p-2 rounded-lg" 
                                                title="Konfirmasi Penerimaan Cepat">
                                            <i class="fas fa-check-circle"></i>
                                        </button>
                                        <a href="penerimaan-add.php?pembelian_id=<?= $p['id'] ?>" 
                                           class="bg-blue-500 hover:bg-blue-600 text-white p-2 rounded-lg" 
                                           title="Terima Barang Detail">
                                            <i class="fas fa-truck"></i>
                                        </a>
                                        <?php endif; ?>
                                        <?php if ($p['total_hutang'] > 0 && $p['status_pembayaran'] !== 'lunas' && AuthStatic::hasRole(['admin', 'akunting'])): ?>
                                        <a href="pembayaran-pembelian-add.php?pembelian_id=<?= $p['id'] ?>" 
                                           class="bg-purple-500 hover:bg-purple-600 text-white p-2 rounded-lg" 
                                           title="Bayar Hutang">
                                            <i class="fas fa-money-bill-wave"></i>
                                        </a>
                                        <?php endif; ?>
                                        <?php if (AuthStatic::hasRole(['admin']) && $p['status_pembelian'] === 'draft'): ?>
                                        <button onclick="showDeleteModal(<?= $p['id'] ?>, '<?= htmlspecialchars($p['nomor_po']) ?>')" 
                                                class="bg-red-500 hover:bg-red-600 text-white p-2 rounded-lg" 
                                                title="Hapus PO">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="bg-white px-6 py-4 border-t border-gray-200 flex items-center justify-between">
                <div class="flex-1 flex justify-between sm:hidden">
                    <?php if ($page > 1): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" 
                       class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                        Sebelumnya
                    </a>
                    <?php endif; ?>
                    <?php if ($page < $total_pages): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" 
                       class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                        Selanjutnya
                    </a>
                    <?php endif; ?>
                </div>
                <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm text-gray-700">
                            Menampilkan <span class="font-medium"><?= number_format($offset + 1) ?></span> - 
                            <span class="font-medium"><?= number_format(min($offset + $limit, $total_records)) ?></span> 
                            dari <span class="font-medium"><?= number_format($total_records) ?></span> pembelian
                        </p>
                    </div>
                    <div>
                        <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                            <?php if ($page > 1): ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" 
                               class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                <i class="fa fa-chevron-left"></i>
                            </a>
                            <?php endif; ?>
                            
                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" 
                               class="relative inline-flex items-center px-4 py-2 border text-sm font-medium 
                                      <?= $i === $page ? 'z-10 bg-primary-50 border-primary-500 text-primary-600' : 'border-gray-300 bg-white text-gray-500 hover:bg-gray-50' ?>">
                                <?= $i ?>
                            </a>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" 
                               class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
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

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3 text-center">
            <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100">
                <i class="fa fa-exclamation-triangle text-red-600"></i>
            </div>
            <h3 class="text-lg font-medium text-gray-900 mt-2">Hapus Purchase Order</h3>
            <div class="mt-2 px-7 py-3">
                <p class="text-sm text-gray-500">
                    Apakah Anda yakin ingin menghapus Purchase Order <span id="deletePoNumber" class="font-medium"></span>?
                    Tindakan ini tidak dapat dibatalkan.
                </p>
            </div>
            <div class="items-center px-4 py-3">
                <form id="deleteForm" method="POST" action="pembelian-delete.php">
                    <input type="hidden" id="deletePoId" name="id">
                    <button type="submit" class="px-4 py-2 bg-red-600 text-white text-base font-medium rounded-md w-24 mr-2 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-300">
                        Hapus
                    </button>
                    <button type="button" onclick="hideDeleteModal()" class="px-4 py-2 bg-gray-300 text-gray-800 text-base font-medium rounded-md w-24 hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-300">
                        Batal
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal Konfirmasi Penerimaan Cepat -->
<div id="konfirmasiModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3 text-center">
            <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-green-100">
                <i class="fa fa-check-circle text-green-600"></i>
            </div>
            <h3 class="text-lg font-medium text-gray-900 mt-2">Konfirmasi Penerimaan Cepat</h3>
            <div class="mt-2 px-7 py-3">
                <p class="text-sm text-gray-500">
                    Konfirmasi bahwa semua barang dari PO <span id="konfirmasiPoNumber" class="font-medium"></span> 
                    dari vendor <span id="konfirmasiVendor" class="font-medium"></span> telah diterima dalam kondisi baik?
                </p>
                <div class="mt-3 p-3 bg-yellow-50 rounded-lg">
                    <p class="text-xs text-yellow-800">
                        <i class="fa fa-info-circle mr-1"></i>
                        Semua item akan dikonfirmasi diterima lengkap dan stok akan otomatis bertambah.
                    </p>
                </div>
            </div>
            <div class="items-center px-4 py-3">
                <button id="konfirmasiBtn" onclick="prosesKonfirmasi()" class="px-4 py-2 bg-green-600 text-white text-base font-medium rounded-md w-24 mr-2 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-300">
                    <span id="konfirmasiText">Konfirmasi</span>
                    <i id="konfirmasiSpinner" class="fa fa-spinner fa-spin hidden ml-1"></i>
                </button>
                <button type="button" onclick="hideKonfirmasiModal()" class="px-4 py-2 bg-gray-300 text-gray-800 text-base font-medium rounded-md w-24 hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-300">
                    Batal
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let currentPembelianId = null;

function showDeleteModal(id, poNumber) {
    document.getElementById('deletePoId').value = id;
    document.getElementById('deletePoNumber').textContent = poNumber;
    document.getElementById('deleteModal').classList.remove('hidden');
}

function hideDeleteModal() {
    document.getElementById('deleteModal').classList.add('hidden');
}

function showKonfirmasiModal(id, poNumber, vendor) {
    currentPembelianId = id;
    document.getElementById('konfirmasiPoNumber').textContent = poNumber;
    document.getElementById('konfirmasiVendor').textContent = vendor;
    document.getElementById('konfirmasiModal').classList.remove('hidden');
}

function hideKonfirmasiModal() {
    document.getElementById('konfirmasiModal').classList.add('hidden');
    currentPembelianId = null;
    // Reset button state
    document.getElementById('konfirmasiBtn').disabled = false;
    document.getElementById('konfirmasiText').textContent = 'Konfirmasi';
    document.getElementById('konfirmasiSpinner').classList.add('hidden');
}

function prosesKonfirmasi() {
    if (!currentPembelianId) return;
    
    // Disable button dan show loading
    const btn = document.getElementById('konfirmasiBtn');
    const text = document.getElementById('konfirmasiText');
    const spinner = document.getElementById('konfirmasiSpinner');
    
    btn.disabled = true;
    text.textContent = 'Memproses...';
    spinner.classList.remove('hidden');
    
    // Redirect to quick receive endpoint
    window.location.href = `penerimaan-quick.php?pembelian_id=${currentPembelianId}`;
}
</script>

</div>

<?php require_once 'layouts/footer.php'; ?>