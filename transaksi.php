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

// Fungsi helper untuk format tanggal Indonesia
function formatTanggalIndonesia($tanggal) {
    if (!$tanggal) return '-';

    $bulan = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
    ];

    $timestamp = strtotime($tanggal);
    $hari = date('d', $timestamp);
    $bulan_num = (int)date('m', $timestamp);
    $tahun = date('Y', $timestamp);

    return $hari . ' ' . $bulan[$bulan_num] . ' ' . $tahun;
}

// Fungsi helper untuk format rupiah
function formatRupiah($angka) {
    return 'Rp ' . number_format($angka, 0, ',', '.');
}

// Parameter pencarian dan filter
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Build query conditions
$conditions = ["1=1"];
$params = [];

// Filter berdasarkan role
if ($user['role'] === 'sales') {
    $conditions[] = "t.user_id = ?";
    $params[] = $user['id'];
}

if (!empty($search)) {
    $conditions[] = "(t.nomor_invoice LIKE ? OR d.nama_desa LIKE ? OR t.catatan LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($status_filter)) {
    $conditions[] = "t.status_transaksi = ?";
    $params[] = $status_filter;
}

if (!empty($date_from)) {
    $conditions[] = "DATE(t.tanggal_transaksi) >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $conditions[] = "DATE(t.tanggal_transaksi) <= ?";
    $params[] = $date_to;
}

$where_clause = implode(' AND ', $conditions);

// Pagination
$page = (int)($_GET['page'] ?? 1);
$limit = 20;
$offset = ($page - 1) * $limit;

// Query untuk menghitung total
$count_query = "SELECT COUNT(*) as total 
    FROM transaksi t 
    LEFT JOIN desa d ON t.desa_id = d.id
    WHERE $where_clause";
$count_result = $db->select($count_query, $params);
$total_records = $count_result[0]['total'];
$total_pages = ceil($total_records / $limit);

// Query untuk data transaksi
$query = "SELECT t.*, d.nama_desa, d.kecamatan, u.nama_lengkap as sales_name
    FROM transaksi t 
    LEFT JOIN desa d ON t.desa_id = d.id
    LEFT JOIN users u ON t.user_id = u.id
    WHERE $where_clause
    ORDER BY t.tanggal_transaksi DESC, t.id DESC
    LIMIT $limit OFFSET $offset";

$transaksi_list = $db->select($query, $params);

// Query untuk statistik
$stats_query = "SELECT 
        COUNT(*) as total_transaksi,
        COALESCE(SUM(t.total_amount), 0) as total_pendapatan,
        COUNT(CASE WHEN t.status_transaksi IN ('draft', 'diproses', 'dikirim') THEN 1 END) as pending_count,
        COUNT(CASE WHEN t.status_transaksi = 'selesai' THEN 1 END) as completed_count
    FROM transaksi t 
    LEFT JOIN desa d ON t.desa_id = d.id
    WHERE $where_clause";
$stats_result = $db->select($stats_query, $params);
$stats = $stats_result[0] ?? ['total_transaksi' => 0, 'total_pendapatan' => 0, 'pending_count' => 0, 'completed_count' => 0];    

// Query untuk daftar desa
$desa_list = $db->select("SELECT id, nama_desa, kecamatan FROM desa ORDER BY nama_desa");

$page_title = 'Daftar Penjualan';
require_once 'layouts/header.php';
?>

<!-- Transaksi Content dengan Design Modern -->
<div class="ml-0 px-2 sm:px-4 lg:px-6 py-8 max-w-screen-xl">
    <!-- Page Header -->
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Daftar Penjualan</h1>
                <p class="text-gray-600 mt-2">Kelola dan pantau semua transaksi penjualan</p>
            </div>
            <div class="text-right">
                <p class="text-sm text-gray-500"><?= formatTanggalIndonesia(date('Y-m-d')) ?></p>
                <p class="text-lg font-semibold text-gray-900"><?= date('H:i') ?> WIB</p>
            </div>
        </div>
    </div>

    <!-- Statistik Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <!-- Total Penjualan -->
        <div class="bg-gradient-to-r from-blue-500 to-blue-600 rounded-2xl shadow-xl p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-blue-100 text-sm font-medium">Total Penjualan</p>
                    <p class="text-3xl font-bold text-white mt-2"><?= number_format($stats['total_transaksi']) ?></p>
                    <p class="text-blue-100 text-xs mt-1">Transaksi</p>
                </div>
                <div class="bg-blue-400 bg-opacity-30 rounded-full w-16 h-16 flex items-center justify-center">
                    <i class="fas fa-receipt text-2xl text-white"></i>
                </div>
            </div>
        </div>

        <!-- Total Nilai -->
        <div class="bg-gradient-to-r from-green-500 to-green-600 rounded-2xl shadow-xl p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-green-100 text-sm font-medium">Total Nilai</p>
                    <p class="text-2xl font-bold text-white mt-2"><?= formatRupiah($stats['total_pendapatan']) ?></p>
                    <p class="text-green-100 text-xs mt-1">Pendapatan</p>
                </div>
                <div class="bg-green-400 bg-opacity-30 rounded-full w-16 h-16 flex items-center justify-center">
                    <i class="fas fa-money-bill-wave text-2xl text-white"></i>
                </div>
            </div>
        </div>

        <!-- Dalam Proses -->
        <div class="bg-gradient-to-r from-yellow-500 to-yellow-600 rounded-2xl shadow-xl p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-yellow-100 text-sm font-medium">Dalam Proses</p>
                    <p class="text-3xl font-bold text-white mt-2"><?= number_format($stats['pending_count']) ?></p>
                    <p class="text-yellow-100 text-xs mt-1">Transaksi</p>
                </div>
                <div class="bg-yellow-400 bg-opacity-30 rounded-full w-16 h-16 flex items-center justify-center">
                    <i class="fas fa-clock text-2xl text-white"></i>
                </div>
            </div>
        </div>

        <!-- Selesai -->
        <div class="bg-gradient-to-r from-purple-500 to-purple-600 rounded-2xl shadow-xl p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-purple-100 text-sm font-medium">Selesai</p>
                    <p class="text-3xl font-bold text-white mt-2"><?= number_format($stats['completed_count']) ?></p>
                    <p class="text-purple-100 text-xs mt-1">Transaksi</p>
                </div>
                <div class="bg-purple-400 bg-opacity-30 rounded-full w-16 h-16 flex items-center justify-center">
                    <i class="fas fa-check-circle text-2xl text-white"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Action Buttons -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <div class="flex flex-wrap gap-3">
            <?php if (AuthStatic::hasRole(['admin', 'finance'])): ?>
            <a href="export-transaksi.php" class="inline-flex items-center px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors">
                <i class="fas fa-download mr-2"></i>
                Export Data
            </a>
            <?php endif; ?>
            <a href="transaksi-add.php" class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
                <i class="fas fa-plus mr-2"></i>
                Buat Penjualan
            </a>
        </div>
    </div>

    <!-- Filter -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-4">
            <input type="text" name="search" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" placeholder="Cari invoice, desa, catatan..." value="<?= htmlspecialchars($search) ?>">

            <select name="status" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                <option value="">Semua Status</option>
                <option value="draft" <?= $status_filter === 'draft' ? 'selected' : '' ?>>Draft</option>
                <option value="diproses" <?= $status_filter === 'diproses' ? 'selected' : '' ?>>Diproses</option>
                <option value="dikirim" <?= $status_filter === 'dikirim' ? 'selected' : '' ?>>Dikirim</option>
                <option value="selesai" <?= $status_filter === 'selesai' ? 'selected' : '' ?>>Selesai</option>
            </select>

            <input type="date" name="date_from" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" value="<?= htmlspecialchars($date_from) ?>">

            <input type="date" name="date_to" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" value="<?= htmlspecialchars($date_to) ?>">

            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                <i class="fas fa-search mr-2"></i>
                Filter
            </button>
        </form>
    </div>
    <!-- Tabel Data -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Invoice</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Desa</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sales</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($transaksi_list)): ?>
                    <tr>
                        <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                            <i class="fas fa-inbox text-4xl mb-4 text-gray-300"></i>
                            <p class="text-lg">Tidak ada data transaksi</p>
                            <p class="text-sm">Silakan buat transaksi baru atau ubah filter pencarian</p>
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($transaksi_list as $transaksi): ?>
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($transaksi['nomor_invoice']) ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900"><?= formatTanggalIndonesia($transaksi['tanggal_transaksi']) ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900"><?= htmlspecialchars($transaksi['nama_desa'] ?? '-') ?></div>
                                <div class="text-sm text-gray-500"><?= htmlspecialchars($transaksi['kecamatan'] ?? '') ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900"><?= htmlspecialchars($transaksi['sales_name'] ?? '-') ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900"><?= formatRupiah($transaksi['total_amount']) ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php
                                $status_colors = [
                                    'draft' => 'bg-gray-100 text-gray-800',
                                    'diproses' => 'bg-blue-100 text-blue-800',
                                    'dikirim' => 'bg-yellow-100 text-yellow-800',
                                    'selesai' => 'bg-green-100 text-green-800'
                                ];
                                $status_class = $status_colors[$transaksi['status_transaksi']] ?? 'bg-gray-100 text-gray-800';
                                ?>
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?= $status_class ?>">
                                    <?= ucfirst($transaksi['status_transaksi']) ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <div class="flex space-x-2">
                                    <a href="transaksi-detail.php?id=<?= $transaksi['id'] ?>" class="text-blue-600 hover:text-blue-900 transition-colors">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <?php if (AuthStatic::hasRole(['admin', 'sales']) && in_array($transaksi['status_transaksi'], ['draft', 'diproses'])): ?>
                                    <a href="transaksi-edit.php?id=<?= $transaksi['id'] ?>" class="text-green-600 hover:text-green-900 transition-colors">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <?php endif; ?>
                                    <?php if (AuthStatic::hasRole(['admin'])): ?>
                                    <a href="transaksi-delete.php?id=<?= $transaksi['id'] ?>" class="text-red-600 hover:text-red-900 transition-colors" onclick="return confirm('Yakin ingin menghapus transaksi ini?')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <div class="bg-white rounded-lg shadow-md p-6 mt-6">
        <div class="flex items-center justify-between">
            <div class="text-sm text-gray-700">
                Menampilkan <?= ($page - 1) * $limit + 1 ?> - <?= min($page * $limit, $total_records) ?> dari <?= $total_records ?> data
            </div>
            <div class="flex space-x-2">
                <?php if ($page > 1): ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" class="px-3 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">
                    <i class="fas fa-chevron-left"></i>
                </a>
                <?php endif; ?>

                <?php
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                
                for ($i = $start_page; $i <= $end_page; $i++):
                ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" class="px-3 py-2 <?= $i == $page ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' ?> rounded-lg transition-colors">
                    <?= $i ?>
                </a>
                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" class="px-3 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">
                    <i class="fas fa-chevron-right"></i>
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once 'layouts/footer.php'; ?>
