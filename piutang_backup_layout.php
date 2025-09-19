<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$success_message = '';
$error_message = '';

// Handle payment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'bayar') {
    try {
        $piutang_id = $_POST['piutang_id'];
        $jumlah_bayar = floatval($_POST['jumlah_bayar']);
        
        if ($jumlah_bayar <= 0) {
            throw new Exception('Jumlah pembayaran harus lebih dari 0');
        }
        
        $pdo->beginTransaction();
        
        // Get current piutang data
        $stmt = $pdo->prepare("SELECT * FROM piutang WHERE id = ?");
        $stmt->execute([$piutang_id]);
        $piutang = $stmt->fetch();
        
        if (!$piutang) {
            throw new Exception('Data piutang tidak ditemukan');
        }
        
        $sisa_baru = $piutang['sisa_piutang'] - $jumlah_bayar;
        
        if ($sisa_baru < 0) {
            throw new Exception('Jumlah pembayaran melebihi sisa piutang');
        }
        
        $status_baru = $sisa_baru <= 0 ? 'lunas' : 'aktif';
        
        // Update piutang
        $stmt = $pdo->prepare("UPDATE piutang SET sisa_piutang = ?, status = ? WHERE id = ?");
        $stmt->execute([$sisa_baru, $status_baru, $piutang_id]);
        
        $pdo->commit();
        $success_message = 'Pembayaran berhasil dicatat!';
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error_message = 'Error: ' . $e->getMessage();
    }
}

// Filter parameters
$search = $_GET['search'] ?? '';
$desa_filter = $_GET['desa'] ?? '';
$status_filter = $_GET['status'] ?? '';
$tanggal_dari = $_GET['tanggal_dari'] ?? '';
$tanggal_sampai = $_GET['tanggal_sampai'] ?? '';
$sort = $_GET['sort'] ?? 'tanggal_jatuh_tempo';
$order = $_GET['order'] ?? 'asc';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

// Valid sort columns
$valid_sorts = ['invoice_number', 'nama_customer', 'tanggal_jatuh_tempo', 'jumlah_piutang', 'status'];
$sort_column = in_array($sort, $valid_sorts) ? $sort : 'tanggal_jatuh_tempo';

// Sort mapping
$sort_mapping = [
    'invoice_number' => 't.id',
    'nama_customer' => 'c.nama_desa',
    'tanggal_jatuh_tempo' => 'p.tanggal_jatuh_tempo',
    'jumlah_piutang' => 'p.jumlah_piutang',
    'status' => 'p.status'
];

$order_by = $sort_mapping[$sort_column] ?? 'p.tanggal_jatuh_tempo';

// Build WHERE conditions
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(t.id LIKE ? OR c.nama_desa LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($desa_filter)) {
    $where_conditions[] = "c.nama_desa = ?";
    $params[] = $desa_filter;
}

if (!empty($status_filter)) {
    $where_conditions[] = "p.status = ?";
    $params[] = $status_filter;
}

if (!empty($tanggal_dari)) {
    $where_conditions[] = "p.tanggal_jatuh_tempo >= ?";
    $params[] = $tanggal_dari;
}

if (!empty($tanggal_sampai)) {
    $where_conditions[] = "p.tanggal_jatuh_tempo <= ?";
    $params[] = $tanggal_sampai;
}

$where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count
$count_query = "
    SELECT COUNT(*) as total
    FROM piutang p
    JOIN transaksi t ON p.transaksi_id = t.id
    JOIN desa c ON t.desa_id = c.id
    JOIN users u ON t.user_id = u.id
    $where_clause
";

$stmt = $pdo->prepare($count_query);
$stmt->execute($params);
$total_records = $stmt->fetch()['total'];
$total_pages = ceil($total_records / $limit);

// Main query
$query = "
    SELECT p.*, t.id as nomor_transaksi, t.tanggal_transaksi,
           c.nama_desa as nama_customer, c.nama_desa as desa, c.no_hp_kepala_desa as no_hp,
           u.nama_lengkap as sales_name,
           DATEDIFF(p.tanggal_jatuh_tempo, CURDATE()) as hari_tersisa
    FROM piutang p
    JOIN transaksi t ON p.transaksi_id = t.id
    JOIN desa c ON t.desa_id = c.id
    JOIN users u ON t.user_id = u.id
    $where_clause
    ORDER BY $order_by $order
    LIMIT $limit OFFSET $offset
";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$piutang_data = $stmt->fetchAll();

// Get statistics
$stats_query = "
    SELECT 
        COUNT(*) as total_piutang,
        SUM(CASE WHEN p.status = 'belum_jatuh_tempo' THEN 1 ELSE 0 END) as belum_jatuh_tempo,
        SUM(CASE WHEN p.status = 'lunas' THEN 1 ELSE 0 END) as lunas,
        SUM(CASE WHEN p.status = 'mendekati_jatuh_tempo' THEN 1 ELSE 0 END) as mendekati_jatuh_tempo,
        SUM(CASE WHEN p.status = 'terlambat' THEN 1 ELSE 0 END) as terlambat,
        SUM(p.jumlah_piutang) as total_nilai_piutang
    FROM piutang p
    JOIN transaksi t ON p.transaksi_id = t.id
    JOIN desa c ON t.desa_id = c.id
    JOIN users u ON t.user_id = u.id
    $where_clause
";

$stmt = $pdo->prepare($stats_query);
$stmt->execute($params);
$piutang_stats = $stmt->fetch();

// Helper functions
function formatRupiah($amount) {
    return 'Rp ' . number_format($amount, 0, ',', '.');
}

function getSortUrl($column, $current_sort, $current_order) {
    $new_order = ($current_sort === $column && $current_order === 'asc') ? 'desc' : 'asc';
    $params = $_GET;
    $params['sort'] = $column;
    $params['order'] = $new_order;
    return '?' . http_build_query($params);
}

function getSortIcon($column, $current_sort, $current_order) {
    if ($current_sort !== $column) {
        return '<i class="fas fa-sort text-gray-400 ml-1"></i>';
    }
    return $current_order === 'asc' 
        ? '<i class="fas fa-sort-up text-blue-500 ml-1"></i>'
        : '<i class="fas fa-sort-down text-blue-500 ml-1"></i>';
}

function getStatusBadge($status) {
    $badges = [
        'lunas' => 'bg-green-100 text-green-800',
        'aktif' => 'bg-blue-100 text-blue-800',
        'terlambat' => 'bg-red-100 text-red-800',
        'mendekati_jatuh_tempo' => 'bg-yellow-100 text-yellow-800'
    ];
    
    $class = $badges[$status] ?? 'bg-gray-100 text-gray-800';
    $text = ucfirst(str_replace('_', ' ', $status));
    
    return "<span class=\"px-2 py-1 text-xs font-medium rounded-full $class\">$text</span>";
}

// Get unique desa for filter
$desa_query = "SELECT DISTINCT c.nama_desa FROM desa c JOIN transaksi t ON c.id = t.desa_id JOIN piutang p ON t.id = p.transaksi_id ORDER BY c.nama_desa";
$stmt = $pdo->prepare($desa_query);
$stmt->execute();
$desa_list = $stmt->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Piutang - SMD</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <!-- Navigation -->
    <nav class="bg-white shadow-sm border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <h1 class="text-xl font-semibold text-gray-900">Manajemen Piutang</h1>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="dashboard.php" class="text-gray-600 hover:text-gray-900">Dashboard</a>
                    <a href="logout.php" class="bg-red-600 text-white px-4 py-2 rounded-md hover:bg-red-700">Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        <!-- Alert Messages -->
        <?php if ($success_message): ?>
            <div id="success-alert" class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative">
                <span class="block sm:inline"><?= htmlspecialchars($success_message) ?></span>
                <span class="absolute top-0 bottom-0 right-0 px-4 py-3 cursor-pointer" onclick="this.parentElement.style.display='none'">
                    <i class="fas fa-times"></i>
                </span>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div id="error-alert" class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative">
                <span class="block sm:inline"><?= htmlspecialchars($error_message) ?></span>
                <span class="absolute top-0 bottom-0 right-0 px-4 py-3 cursor-pointer" onclick="this.parentElement.style.display='none'">
                    <i class="fas fa-times"></i>
                </span>
            </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-file-invoice-dollar text-blue-500 text-2xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Total Piutang</p>
                            <p class="text-2xl font-semibold text-gray-900"><?= number_format($piutang_stats['total_piutang'] ?? 0) ?></p>
                        </div>
                    </div>
                    <div class="mt-4 sm:mt-0">
                        <span class="text-sm text-gray-600">
                            Total Nilai: <span class="font-semibold text-blue-600"><?= formatRupiah($piutang_stats['total_nilai_piutang'] ?? 0) ?></span>
                        </span>
                    </div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-check-circle text-green-500 text-2xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Lunas</p>
                            <p class="text-2xl font-semibold text-green-600"><?= number_format($piutang_stats['lunas'] ?? 0) ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-exclamation-triangle text-yellow-500 text-2xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Mendekati Jatuh Tempo</p>
                            <p class="text-2xl font-semibold text-yellow-600"><?= number_format($piutang_stats['mendekati_jatuh_tempo'] ?? 0) ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-times-circle text-red-500 text-2xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Terlambat</p>
                            <p class="text-2xl font-semibold text-red-600"><?= number_format($piutang_stats['terlambat'] ?? 0) ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Filter Section -->
        <div class="bg-white shadow rounded-lg mb-6">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Filter Data</h3>
            </div>
            <div class="p-6">
                <form method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Pencarian</label>
                        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
                               placeholder="Cari invoice atau customer..." 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Desa</label>
                        <select name="desa" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Semua Desa</option>
                            <?php foreach ($desa_list as $desa): ?>
                                <option value="<?= htmlspecialchars($desa) ?>" <?= $desa_filter === $desa ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($desa) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Semua Status</option>
                            <option value="aktif" <?= $status_filter === 'aktif' ? 'selected' : '' ?>>Aktif</option>
                            <option value="lunas" <?= $status_filter === 'lunas' ? 'selected' : '' ?>>Lunas</option>
                            <option value="terlambat" <?= $status_filter === 'terlambat' ? 'selected' : '' ?>>Terlambat</option>
                            <option value="mendekati_jatuh_tempo" <?= $status_filter === 'mendekati_jatuh_tempo' ? 'selected' : '' ?>>Mendekati Jatuh Tempo</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Dari</label>
                        <input type="date" name="tanggal_dari" value="<?= htmlspecialchars($tanggal_dari) ?>" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Sampai</label>
                        <input type="date" name="tanggal_sampai" value="<?= htmlspecialchars($tanggal_sampai) ?>" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div class="lg:col-span-5 flex gap-2">
                        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition-colors">
                            <i class="fas fa-search mr-2"></i>Filter
                        </button>
                        <a href="piutang.php" class="bg-gray-600 text-white px-4 py-2 rounded-md hover:bg-gray-700 transition-colors">
                            <i class="fas fa-refresh mr-2"></i>Reset
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Data Table -->
        <div class="bg-white shadow rounded-lg overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Data Piutang</h3>
                <p class="text-sm text-gray-600 mt-1">Menampilkan <?= count($piutang_data) ?> dari <?= $total_records ?> data</p>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th onclick="location.href='<?= getSortUrl('invoice_number', $sort, $order) ?>'"
                                class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100 transition-colors">
                                <span>Invoice</span>
                                <?= getSortIcon('invoice_number', $sort, $order) ?>
                            </th>
                            <th onclick="location.href='<?= getSortUrl('nama_customer', $sort, $order) ?>'"
                                class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100 transition-colors">
                                <span>Customer</span>
                                <?= getSortIcon('nama_customer', $sort, $order) ?>
                            </th>
                            <th onclick="location.href='<?= getSortUrl('tanggal_jatuh_tempo', $sort, $order) ?>'"
                                class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100 transition-colors">
                                <span>Jatuh Tempo</span>
                                <?= getSortIcon('tanggal_jatuh_tempo', $sort, $order) ?>
                            </th>
                            <th onclick="location.href='<?= getSortUrl('jumlah_piutang', $sort, $order) ?>'"
                                class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100 transition-colors">
                                <span>Jumlah Piutang</span>
                                <?= getSortIcon('jumlah_piutang', $sort, $order) ?>
                            </th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sisa</th>
                            <th onclick="location.href='<?= getSortUrl('status', $sort, $order) ?>'"
                                class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100 transition-colors">
                                <span>Status</span>
                                <?= getSortIcon('status', $sort, $order) ?>
                            </th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($piutang_data)): ?>
                            <tr>
                                <td colspan="7" class="px-4 py-8 text-center text-gray-500">
                                    <i class="fas fa-inbox text-4xl mb-4"></i>
                                    <p>Tidak ada data piutang ditemukan</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($piutang_data as $piutang): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <div class="font-medium text-gray-900"><?= htmlspecialchars($piutang['nomor_transaksi']) ?></div>
                                        <div class="text-sm text-gray-500"><?= date('d/m/Y', strtotime($piutang['tanggal_transaksi'])) ?></div>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <div class="font-medium text-gray-900"><?= htmlspecialchars($piutang['nama_customer']) ?></div>
                                        <div class="text-sm text-gray-500"><?= htmlspecialchars($piutang['sales_name']) ?></div>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <div class="text-sm text-gray-900"><?= date('d/m/Y', strtotime($piutang['tanggal_jatuh_tempo'])) ?></div>
                                        <div class="text-xs <?= $piutang['hari_tersisa'] < 0 ? 'text-red-600' : ($piutang['hari_tersisa'] <= 7 ? 'text-yellow-600' : 'text-green-600') ?>">
                                            <?= $piutang['hari_tersisa'] < 0 ? abs($piutang['hari_tersisa']) . ' hari terlambat' : $piutang['hari_tersisa'] . ' hari lagi' ?>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <div class="font-semibold text-blue-600"><?= formatRupiah($piutang['jumlah_piutang']) ?></div>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <div class="font-semibold <?= $piutang['sisa_piutang'] > 0 ? 'text-red-600' : 'text-green-600' ?>">
                                            <?= formatRupiah($piutang['sisa_piutang']) ?>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <?= getStatusBadge($piutang['status']) ?>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm">
                                        <?php if ($piutang['status'] !== 'lunas'): ?>
                                            <button class="inline-flex items-center px-3 py-1 bg-green-100 text-green-700 rounded-md hover:bg-green-200 transition-colors"
                                                    onclick="showPaymentForm(<?= $piutang['id'] ?>, '<?= htmlspecialchars($piutang['nomor_transaksi']) ?>', <?= $piutang['sisa_piutang'] ?>)"
                                                    title="Catat Pembayaran">
                                                <i class="fas fa-money-bill-wave mr-1"></i>
                                                Bayar
                                            </button>
                                        <?php else: ?>
                                            <span class="text-green-600 font-medium">
                                                <i class="fas fa-check-circle mr-1"></i>
                                                Lunas
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
                    <div class="flex items-center justify-between">
                        <div class="flex-1 flex justify-between sm:hidden">
                            <?php if ($page > 1): ?>
                                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" 
                                   class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                    Previous
                                </a>
                            <?php endif; ?>
                            <?php if ($page < $total_pages): ?>
                                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" 
                                   class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                    Next
                                </a>
                            <?php endif; ?>
                        </div>
                        <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                            <div>
                                <p class="text-sm text-gray-700">
                                    Showing <span class="font-medium"><?= ($page - 1) * $limit + 1 ?></span> to 
                                    <span class="font-medium"><?= min($page * $limit, $total_records) ?></span> of 
                                    <span class="font-medium"><?= $total_records ?></span> results
                                </p>
                            </div>
                            <div>
                                <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
                                    <?php if ($page > 1): ?>
                                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" 
                                           class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                            <i class="fas fa-chevron-left"></i>
                                        </a>
                                    <?php endif; ?>
                                    
                                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" 
                                           class="relative inline-flex items-center px-4 py-2 border text-sm font-medium <?= $i === $page ? 'z-10 bg-blue-50 border-blue-500 text-blue-600' : 'border-gray-300 bg-white text-gray-500 hover:bg-gray-50' ?>">
                                            <?= $i ?>
                                        </a>
                                    <?php endfor; ?>
                                    
                                    <?php if ($page < $total_pages): ?>
                                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" 
                                           class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                            <i class="fas fa-chevron-right"></i>
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

    <!-- Payment Modal -->
    <div id="paymentModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Catat Pembayaran</h3>
                    <button onclick="closePaymentModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <form method="POST" onsubmit="return validatePayment()">
                    <input type="hidden" name="action" value="bayar">
                    <input type="hidden" name="piutang_id" id="piutang_id">
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Invoice</label>
                        <input type="text" id="invoice_display" readonly 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-50">
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Sisa Piutang</label>
                        <input type="text" id="sisa_display" readonly 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-50">
                    </div>
                    
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Jumlah Pembayaran</label>
                        <input type="number" name="jumlah_bayar" id="jumlah_bayar" step="0.01" min="0" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                               placeholder="Masukkan jumlah pembayaran">
                    </div>
                    
                    <div class="flex gap-3">
                        <button type="submit" 
                                class="flex-1 bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 transition-colors">
                            <i class="fas fa-save mr-2"></i>Simpan
                        </button>
                        <button type="button" onclick="closePaymentModal()" 
                                class="flex-1 bg-gray-600 text-white py-2 px-4 rounded-md hover:bg-gray-700 transition-colors">
                            Batal
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        let currentSisaPiutang = 0;

        function showPaymentForm(piutangId, invoice, sisaPiutang) {
            document.getElementById('piutang_id').value = piutangId;
            document.getElementById('invoice_display').value = invoice;
            document.getElementById('sisa_display').value = formatRupiah(sisaPiutang);
            document.getElementById('jumlah_bayar').value = '';
            currentSisaPiutang = sisaPiutang;
            document.getElementById('paymentModal').classList.remove('hidden');
        }

        function closePaymentModal() {
            document.getElementById('paymentModal').classList.add('hidden');
        }

        function validatePayment() {
            const jumlahBayar = parseFloat(document.getElementById('jumlah_bayar').value);
            
            if (isNaN(jumlahBayar) || jumlahBayar <= 0) {
                alert('Jumlah pembayaran harus lebih dari 0');
                return false;
            }
            
            if (jumlahBayar > currentSisaPiutang) {
                alert('Jumlah pembayaran tidak boleh melebihi sisa piutang');
                return false;
            }
            
            return confirm('Apakah Anda yakin ingin mencatat pembayaran ini?');
        }

        function formatRupiah(amount) {
            return 'Rp ' + new Intl.NumberFormat('id-ID').format(amount);
        }

        // Auto hide alerts after 5 seconds
        setTimeout(function() {
            const successAlert = document.getElementById('success-alert');
            const errorAlert = document.getElementById('error-alert');
            if (successAlert) successAlert.style.display = 'none';
            if (errorAlert) errorAlert.style.display = 'none';
        }, 5000);

        // Close modal when clicking outside
        document.getElementById('paymentModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closePaymentModal();
            }
        });
    </script>
</body>
</html>
