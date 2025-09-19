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

// Fungsi helper untuk format rupiah
function formatRupiah($angka) {
    return 'Rp ' . number_format($angka, 0, ',', '.');
}

// Fungsi helper untuk format tanggal Indonesia
function formatTanggalIndonesia($tanggal) {
    $bulan = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    $timestamp = strtotime($tanggal);
    return date('j', $timestamp) . ' ' . $bulan[date('n', $timestamp)] . ' ' . date('Y', $timestamp);
}

// Filter berdasarkan role
$role_condition = "";
$role_params = [];
if ($user['role'] === 'sales') {
    $role_condition = "AND t.user_id = ?";
    $role_params[] = $user['id'];
}

// === STATISTIK UTAMA ===
$stats = [];

// Total Transaksi Hari Ini
$today_stats = $db->select("
    SELECT 
        COUNT(*) as total_transaksi,
        COALESCE(SUM(total_amount), 0) as total_nilai,
        COALESCE(SUM(CASE WHEN status_transaksi = 'selesai' THEN total_amount ELSE 0 END), 0) as nilai_selesai
    FROM transaksi t
    WHERE DATE(t.created_at) = CURDATE() {$role_condition}
", $role_params);
$stats['today'] = $today_stats[0];

// Total Transaksi Bulan Ini
$month_stats = $db->select("
    SELECT 
        COUNT(*) as total_transaksi,
        COALESCE(SUM(total_amount), 0) as total_nilai,
        COALESCE(SUM(CASE WHEN status_transaksi = 'selesai' THEN total_amount ELSE 0 END), 0) as nilai_selesai
    FROM transaksi t
    WHERE MONTH(t.created_at) = MONTH(CURDATE()) 
    AND YEAR(t.created_at) = YEAR(CURDATE()) {$role_condition}
", $role_params);
$stats['month'] = $month_stats[0];

// Status Transaksi untuk Chart
$status_stats = $db->select("
    SELECT 
        status_transaksi,
        COUNT(*) as jumlah,
        COALESCE(SUM(total_amount), 0) as total_nilai
    FROM transaksi t
    WHERE MONTH(t.created_at) = MONTH(CURDATE()) 
    AND YEAR(t.created_at) = YEAR(CURDATE()) {$role_condition}
    GROUP BY status_transaksi
    ORDER BY jumlah DESC
", $role_params);

// Piutang
$piutang_stats = $db->select("
    SELECT 
        COUNT(*) as total_piutang,
        COALESCE(SUM(p.jumlah_piutang), 0) as total_nilai_piutang,
        COALESCE(SUM(p.jumlah_piutang - COALESCE(pembayaran.total_bayar, 0)), 0) as total_sisa_piutang
    FROM piutang p
    JOIN transaksi t ON p.transaksi_id = t.id
    LEFT JOIN (
        SELECT piutang_id, SUM(jumlah_bayar) as total_bayar
        FROM pembayaran
        WHERE piutang_id IS NOT NULL
        GROUP BY piutang_id
    ) pembayaran ON p.id = pembayaran.piutang_id
    WHERE p.status IN ('belum_jatuh_tempo', 'mendekati_jatuh_tempo') {$role_condition}
", $role_params);
$stats['piutang'] = $piutang_stats[0];

// === DATA UNTUK CHART ===

// Transaksi dari Januari tahun ini hingga hari ini untuk Line Chart
$monthly_stats = $db->select("
    SELECT 
        DATE_FORMAT(t.created_at, '%Y-%m') as bulan,
        COUNT(*) as jumlah_transaksi,
        COALESCE(SUM(total_amount), 0) as total_nilai
    FROM transaksi t
    WHERE t.created_at >= CONCAT(YEAR(CURDATE()), '-01-01') {$role_condition}
    GROUP BY DATE_FORMAT(t.created_at, '%Y-%m')
    ORDER BY bulan ASC
", $role_params);

// Data Pembelian bulanan untuk chart
$monthly_purchases = $db->select("
    SELECT 
        DATE_FORMAT(p.created_at, '%Y-%m') as bulan,
        COUNT(*) as jumlah_pembelian,
        COALESCE(SUM(p.total_amount), 0) as total_nilai_pembelian
    FROM pembelian p
    WHERE p.created_at >= CONCAT(YEAR(CURDATE()), '-01-01')
    GROUP BY DATE_FORMAT(p.created_at, '%Y-%m')
    ORDER BY bulan ASC
");



// Top 5 Desa dengan Transaksi Terbanyak
$top_desa = $db->select("
    SELECT 
        d.nama_desa,
        d.kecamatan,
        COUNT(t.id) as total_transaksi,
        COALESCE(SUM(t.total_amount), 0) as total_nilai
    FROM desa d
    LEFT JOIN transaksi t ON d.id = t.desa_id
    WHERE t.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) {$role_condition}
    GROUP BY d.id, d.nama_desa, d.kecamatan
    HAVING total_transaksi > 0
    ORDER BY total_transaksi DESC, total_nilai DESC
    LIMIT 5
", $role_params);

// Top 5 Produk Terlaris
$top_produk = $db->select("
    SELECT 
        p.nama_produk,
        k.nama_kategori as kategori,
        SUM(td.quantity) as total_terjual,
        COALESCE(SUM(td.subtotal), 0) as total_nilai
    FROM produk p
    LEFT JOIN kategori_produk k ON p.kategori_id = k.id
    JOIN transaksi_detail td ON p.id = td.produk_id
    JOIN transaksi t ON td.transaksi_id = t.id
    WHERE t.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) {$role_condition}
    GROUP BY p.id, p.nama_produk, k.nama_kategori
    ORDER BY total_terjual DESC, total_nilai DESC
    LIMIT 5
", $role_params);

// Transaksi Terbaru
$recent_transaksi = $db->select("
    SELECT 
        t.id,
        t.nomor_invoice,
        t.tanggal_transaksi,
        t.total_amount,
        t.status_transaksi,
        d.nama_desa,
        d.kecamatan,
        u.nama_lengkap as sales_name
    FROM transaksi t
    LEFT JOIN desa d ON t.desa_id = d.id
    LEFT JOIN users u ON t.user_id = u.id
    WHERE 1=1 {$role_condition}
    ORDER BY t.created_at DESC
    LIMIT 8
", $role_params);

// Notifikasi dan Alert
$alerts = [];

// Piutang yang akan jatuh tempo (7 hari ke depan)
$upcoming_piutang = $db->select("
    SELECT COUNT(*) as count
    FROM piutang p
    JOIN transaksi t ON p.transaksi_id = t.id
    WHERE p.tanggal_jatuh_tempo BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    AND p.status = 'belum_jatuh_tempo' {$role_condition}
", $role_params);

if ($upcoming_piutang[0]['count'] > 0) {
    $alerts[] = [
        'type' => 'warning',
        'icon' => 'fas fa-exclamation-triangle',
        'message' => $upcoming_piutang[0]['count'] . ' piutang akan jatuh tempo dalam 7 hari',
        'link' => 'piutang.php?filter=upcoming'
    ];
}

// Stok produk menipis (kurang dari 10)
$low_stock = $db->select("
    SELECT COUNT(*) as count
    FROM produk
    WHERE stok_tersedia < 10 AND stok_tersedia > 0
");

if ($low_stock[0]['count'] > 0) {
    $alerts[] = [
        'type' => 'danger',
        'icon' => 'fas fa-box-open',
        'message' => $low_stock[0]['count'] . ' produk dengan stok menipis',
        'link' => 'produk.php?filter=low_stock'
    ];
}

$page_title = 'Dashboard Admin';
require_once 'layouts/header.php';
?>

<!-- Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns/dist/chartjs-adapter-date-fns.bundle.min.js"></script>

<!-- Dashboard Content -->
<div class="ml-0 px-2 sm:px-4 lg:px-6 py-8 max-w-screen-xl">
    <!-- Page Header -->
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Dashboard Admin</h1>
                <p class="text-gray-600 mt-2">Selamat datang, <?= htmlspecialchars($user['nama_lengkap']) ?>! Berikut ringkasan sistem hari ini.</p>
            </div>
            <div class="text-right">
                <p class="text-sm text-gray-500"><?= formatTanggalIndonesia(date('Y-m-d')) ?></p>
                <p class="text-lg font-semibold text-gray-900"><?= date('H:i') ?> WIB</p>
            </div>
        </div>
    </div>

    <!-- Alerts Section -->
    <?php if (!empty($alerts)): ?>
    <div class="mb-8">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <?php foreach ($alerts as $alert): ?>
            <div class="bg-<?= $alert['type'] === 'warning' ? 'yellow' : 'red' ?>-50 border border-<?= $alert['type'] === 'warning' ? 'yellow' : 'red' ?>-200 rounded-lg p-4">
                <div class="flex items-center">
                    <i class="<?= $alert['icon'] ?> text-<?= $alert['type'] === 'warning' ? 'yellow' : 'red' ?>-600 mr-3"></i>
                    <div class="flex-1">
                        <p class="text-<?= $alert['type'] === 'warning' ? 'yellow' : 'red' ?>-800 font-medium"><?= $alert['message'] ?></p>
                    </div>
                    <a href="<?= $alert['link'] ?>" class="text-<?= $alert['type'] === 'warning' ? 'yellow' : 'red' ?>-600 hover:text-<?= $alert['type'] === 'warning' ? 'yellow' : 'red' ?>-800">
                        <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Statistik Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <!-- Transaksi Hari Ini -->
        <div class="bg-gradient-to-r from-blue-500 to-blue-600 rounded-lg shadow-md p-4 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-blue-100 text-xs font-medium">Transaksi Hari Ini</p>
                    <p class="text-2xl font-bold mt-1"><?= number_format($stats['today']['total_transaksi']) ?></p>
                    <p class="text-blue-100 text-xs mt-1"><?= formatRupiah($stats['today']['total_nilai']) ?></p>
                </div>
                <div class="bg-blue-400 bg-opacity-30 rounded-full w-12 h-12 flex items-center justify-center">
                    <i class="fas fa-calendar-day text-lg"></i>
                </div>
            </div>
            <div class="mt-3 flex items-center">
                <i class="fas fa-arrow-up text-green-300 mr-1"></i>
                <span class="text-green-300 text-xs font-medium">Live Update</span>
            </div>
        </div>

        <!-- Transaksi Bulan Ini -->
        <div class="bg-gradient-to-r from-green-500 to-green-600 rounded-lg shadow-md p-4 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-green-100 text-xs font-medium">Transaksi Bulan Ini</p>
                    <p class="text-2xl font-bold mt-1"><?= number_format($stats['month']['total_transaksi']) ?></p>
                    <p class="text-green-100 text-xs mt-1"><?= formatRupiah($stats['month']['total_nilai']) ?></p>
                </div>
                <div class="bg-green-400 bg-opacity-30 rounded-full w-12 h-12 flex items-center justify-center">
                    <i class="fas fa-calendar-alt text-lg"></i>
                </div>
            </div>
            <div class="mt-3 flex items-center">
                <i class="fas fa-chart-line text-green-300 mr-1"></i>
                <span class="text-green-300 text-xs font-medium">Trending</span>
            </div>
        </div>

        <!-- Transaksi Selesai -->
        <div class="bg-gradient-to-r from-yellow-500 to-yellow-600 rounded-lg shadow-md p-4 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-yellow-100 text-xs font-medium">Transaksi Selesai</p>
                    <p class="text-2xl font-bold mt-1"><?= formatRupiah($stats['month']['nilai_selesai']) ?></p>
                    <p class="text-yellow-100 text-xs mt-1">Bulan ini</p>
                </div>
                <div class="bg-yellow-400 bg-opacity-30 rounded-full w-12 h-12 flex items-center justify-center">
                    <i class="fas fa-money-bill-wave text-lg"></i>
                </div>
            </div>
            <div class="mt-3 flex items-center">
                <i class="fas fa-check-circle text-yellow-300 mr-1"></i>
                <span class="text-yellow-300 text-xs font-medium">Completed</span>
            </div>
        </div>

        <!-- Total Piutang -->
        <div class="bg-gradient-to-r from-red-500 to-red-600 rounded-lg shadow-md p-4 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-red-100 text-xs font-medium">Total Piutang</p>
                    <p class="text-2xl font-bold mt-1"><?= number_format($stats['piutang']['total_piutang']) ?></p>
                    <p class="text-red-100 text-xs mt-1"><?= formatRupiah($stats['piutang']['total_sisa_piutang']) ?></p>
                </div>
                <div class="bg-red-400 bg-opacity-30 rounded-full w-12 h-12 flex items-center justify-center">
                    <i class="fas fa-credit-card text-lg"></i>
                </div>
            </div>
            <div class="mt-3 flex items-center">
                <i class="fas fa-exclamation-triangle text-red-300 mr-1"></i>
                <span class="text-red-300 text-xs font-medium">Outstanding</span>
            </div>
        </div>
    </div>

    <!-- Charts Section -->
    <div class="mb-6">
        <!-- Transaksi 12 Bulan Terakhir -->
        <div class="bg-white rounded-lg shadow-md border border-gray-200 p-4">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-base font-semibold text-gray-900">
                        <i class="fas fa-chart-line mr-2 text-blue-600"></i>
                        Tren Transaksi Tahun <?= date('Y') ?>
                    </h3>
                    <div class="text-xs text-gray-500">
                        <i class="fas fa-calendar mr-1"></i>
                        Jan <?= date('Y') ?> - <?= date('M Y') ?>
                    </div>
            </div>
            <div class="h-80" style="position: relative; height: 320px; width: 100%;">
                <canvas id="monthlyChart" style="display: block; box-sizing: border-box; height: 320px; width: 100%;"></canvas>
            </div>
        </div>
    </div>

    <!-- Monthly Analytics Chart -->
    <div class="mb-6">
        <div class="bg-white rounded-lg shadow-md border border-gray-200 p-4">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-base font-semibold text-gray-900">
                    <i class="fas fa-chart-area mr-2 text-purple-600"></i>
                    Analisis Bulanan
                </h3>
                <div class="flex items-center space-x-3">
                    <select id="periodSelect" class="border border-gray-300 rounded-md px-2 py-1 text-xs focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="6">6 Bulan Terakhir</option>
                        <option value="3">3 Bulan Terakhir</option>
                        <option value="12">12 Bulan Terakhir</option>
                        <option value="24">24 Bulan Terakhir</option>
                    </select>
                    <button id="refreshChart" class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded-md text-xs transition duration-200">
                        <i class="fas fa-sync-alt mr-1"></i>
                        Refresh
                    </button>
                </div>
            </div>
            <div class="h-72" style="position: relative; height: 288px; width: 100%;">
                <canvas id="monthlyAnalyticsChart" style="display: block; box-sizing: border-box; height: 288px; width: 100%;"></canvas>
            </div>
            <div class="mt-4 grid grid-cols-3 gap-4 text-center">
                <div class="p-3 bg-blue-50 rounded-lg">
                    <p class="text-sm text-blue-600 font-medium">Nominal Penjualan</p>
                    <p class="text-lg font-bold text-blue-800" id="totalSales">-</p>
                </div>
                <div class="p-3 bg-red-50 rounded-lg">
                    <p class="text-sm text-red-600 font-medium">Nominal Pembelian</p>
                    <p class="text-lg font-bold text-red-800" id="totalPurchases">-</p>
                </div>
                <div class="p-3 bg-green-50 rounded-lg">
                    <p class="text-sm text-green-600 font-medium">Jumlah Transaksi</p>
                    <p class="text-lg font-bold text-green-800" id="totalTransactions">-</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Performance Widgets -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">
        <!-- Top Desa -->
        <div class="bg-white rounded-lg shadow-md border border-gray-200 p-4">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-base font-semibold text-gray-900">
                    <i class="fas fa-trophy mr-2 text-yellow-600"></i>
                    Top 5 Desa Terbaik
                </h3>
                <a href="desa.php" class="text-blue-600 hover:text-blue-800 text-xs font-medium">
                    Lihat Semua <i class="fas fa-arrow-right ml-1"></i>
                </a>
            </div>
            <div class="space-y-3">
                <?php foreach ($top_desa as $index => $desa): ?>
                <div class="flex items-center justify-between p-2 bg-gray-50 rounded-lg">
                    <div class="flex items-center">
                        <div class="w-6 h-6 rounded-full bg-gradient-to-r from-blue-500 to-purple-600 flex items-center justify-center text-white font-bold text-xs mr-2">
                            <?= $index + 1 ?>
                        </div>
                        <div>
                            <p class="font-medium text-gray-900 text-sm"><?= htmlspecialchars($desa['nama_desa']) ?></p>
                            <p class="text-xs text-gray-500"><?= htmlspecialchars($desa['kecamatan']) ?></p>
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="font-semibold text-gray-900 text-sm"><?= number_format($desa['total_transaksi']) ?> transaksi</p>
                        <p class="text-xs text-gray-500"><?= formatRupiah($desa['total_nilai']) ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if (empty($top_desa)): ?>
                <div class="text-center py-8">
                    <i class="fas fa-map-marker-alt text-gray-400 text-3xl mb-3"></i>
                    <p class="text-gray-600">Belum ada data transaksi desa</p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Top Produk -->
        <div class="bg-white rounded-lg shadow-md border border-gray-200 p-4">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-base font-semibold text-gray-900">
                    <i class="fas fa-star mr-2 text-yellow-600"></i>
                    Top 5 Produk Terlaris
                </h3>
                <a href="produk.php" class="text-blue-600 hover:text-blue-800 text-xs font-medium">
                    Lihat Semua <i class="fas fa-arrow-right ml-1"></i>
                </a>
            </div>
            <div class="space-y-3">
                <?php foreach ($top_produk as $index => $produk): ?>
                <div class="flex items-center justify-between p-2 bg-gray-50 rounded-lg">
                    <div class="flex items-center">
                        <div class="w-6 h-6 rounded-full bg-gradient-to-r from-green-500 to-teal-600 flex items-center justify-center text-white font-bold text-xs mr-2">
                            <?= $index + 1 ?>
                        </div>
                        <div>
                            <p class="font-medium text-gray-900 text-sm"><?= htmlspecialchars($produk['nama_produk'] ?? '') ?></p>
                            <p class="text-xs text-gray-500"><?= htmlspecialchars($produk['kategori'] ?? '') ?></p>
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="font-semibold text-gray-900 text-sm"><?= number_format($produk['total_terjual']) ?> terjual</p>
                        <p class="text-xs text-gray-500"><?= formatRupiah($produk['total_nilai']) ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if (empty($top_produk)): ?>
                <div class="text-center py-8">
                    <i class="fas fa-box text-gray-400 text-3xl mb-3"></i>
                    <p class="text-gray-600">Belum ada data penjualan produk</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="bg-white rounded-lg shadow-md border border-gray-200 p-4 mb-6">
        <h3 class="text-base font-semibold text-gray-900 mb-4">
            <i class="fas fa-bolt mr-2 text-yellow-600"></i>
            Aksi Cepat
        </h3>
        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3">
            <a href="transaksi-add.php" class="bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 p-3 rounded-lg text-center transition duration-200 text-white">
                <i class="fas fa-plus text-lg mb-1"></i>
                <p class="text-xs font-medium">Buat Transaksi</p>
            </a>
            <a href="produk-add.php" class="bg-gradient-to-r from-green-500 to-green-600 hover:from-green-600 hover:to-green-700 p-3 rounded-lg text-center transition duration-200 text-white">
                <i class="fas fa-box text-lg mb-1"></i>
                <p class="text-xs font-medium">Tambah Produk</p>
            </a>
            <a href="desa-add.php" class="bg-gradient-to-r from-purple-500 to-purple-600 hover:from-purple-600 hover:to-purple-700 p-3 rounded-lg text-center transition duration-200 text-white">
                <i class="fas fa-map-marker-alt text-lg mb-1"></i>
                <p class="text-xs font-medium">Daftar Desa</p>
            </a>
            <a href="laporan.php" class="bg-gradient-to-r from-yellow-500 to-yellow-600 hover:from-yellow-600 hover:to-yellow-700 p-3 rounded-lg text-center transition duration-200 text-white">
                <i class="fas fa-chart-bar text-lg mb-1"></i>
                <p class="text-xs font-medium">Laporan</p>
            </a>
            <a href="piutang.php" class="bg-gradient-to-r from-red-500 to-red-600 hover:from-red-600 hover:to-red-700 p-3 rounded-lg text-center transition duration-200 text-white">
                <i class="fas fa-credit-card text-lg mb-1"></i>
                <p class="text-xs font-medium">Kelola Piutang</p>
            </a>
            <a href="user.php" class="bg-gradient-to-r from-indigo-500 to-indigo-600 hover:from-indigo-600 hover:to-indigo-700 p-3 rounded-lg text-center transition duration-200 text-white">
                <i class="fas fa-users text-lg mb-1"></i>
                <p class="text-xs font-medium">Kelola User</p>
            </a>
        </div>
    </div>

    <!-- Transaksi Terbaru -->
    <div class="bg-white rounded-lg shadow-md border border-gray-200 p-4">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-base font-semibold text-gray-900">
                <i class="fas fa-clock mr-2 text-blue-600"></i>
                Transaksi Terbaru
            </h3>
            <a href="transaksi.php" class="text-blue-600 hover:text-blue-800 text-xs font-medium">
                Lihat Semua <i class="fas fa-arrow-right ml-1"></i>
            </a>
        </div>
        
        <?php if (empty($recent_transaksi)): ?>
            <div class="text-center py-12">
                <i class="fas fa-shopping-cart text-gray-400 text-5xl mb-4"></i>
                <p class="text-gray-600 text-lg">Belum ada transaksi</p>
                <p class="text-gray-500 text-sm mt-2">Transaksi baru akan muncul di sini</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Invoice</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Desa</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sales</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($recent_transaksi as $transaksi): ?>
                            <tr class="hover:bg-gray-50 transition duration-150">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    <a href="transaksi-view.php?id=<?= $transaksi['id'] ?>" class="text-blue-600 hover:text-blue-800 font-semibold">
                                        <?= htmlspecialchars($transaksi['nomor_invoice']) ?>
                                    </a>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?= htmlspecialchars($transaksi['nama_desa']) ?><br>
                                    <span class="text-xs text-gray-500"><?= htmlspecialchars($transaksi['kecamatan']) ?></span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?= htmlspecialchars($transaksi['sales_name']) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    <?= formatRupiah($transaksi['total_amount']) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-3 py-1 text-xs font-semibold rounded-full <?php 
                                        echo $transaksi['status_transaksi'] === 'selesai' ? 'bg-green-100 text-green-800' : 
                                            ($transaksi['status_transaksi'] === 'diproses' ? 'bg-blue-100 text-blue-800' : 
                                            ($transaksi['status_transaksi'] === 'dikirim' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-800'));
                                    ?>">
                                        <?= ucfirst($transaksi['status_transaksi']) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= date('d/m/Y H:i', strtotime($transaksi['tanggal_transaksi'])) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Chart Scripts -->
<script>
// Data untuk Chart
const monthlyData = <?= json_encode($monthly_stats) ?>;
const monthlyPurchases = <?= json_encode($monthly_purchases) ?>;
console.log('Monthly Data:', monthlyData);
console.log('Monthly Purchases:', monthlyPurchases);

// Generate all months from January 2025 to current month
function generateMonthLabels() {
    const labels = [];
    const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    const now = new Date();
    const currentYear = now.getFullYear();
    const currentMonth = now.getMonth();
    
    // Always start from January 2025
    const startYear = 2025;
    
    if (currentYear >= startYear) {
        // Add all months from January 2025 to current month
        for (let year = startYear; year <= currentYear; year++) {
            const endMonth = (year === currentYear) ? currentMonth : 11;
            const startMonth = (year === startYear) ? 0 : 0; // Always start from January
            
            for (let month = startMonth; month <= endMonth; month++) {
                labels.push(monthNames[month] + ' ' + year);
            }
        }
    }
    
    return labels;
}

// Prepare chart data - always show all months from January 2025
chartLabels = generateMonthLabels();
transaksiData = new Array(chartLabels.length).fill(0);
nilaiData = new Array(chartLabels.length).fill(0);
pembelianData = new Array(chartLabels.length).fill(0);

// Fill in actual data where available
if (monthlyData && monthlyData.length > 0) {
    const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    
    monthlyData.forEach(item => {
        const [year, month] = item.bulan.split('-');
        const monthLabel = monthNames[parseInt(month) - 1] + ' ' + year;
        const index = chartLabels.indexOf(monthLabel);
        
        if (index !== -1) {
            transaksiData[index] = parseInt(item.jumlah_transaksi);
            nilaiData[index] = Math.round(item.total_nilai / 1000000);
        }
    });
}

// Fill in purchase data
if (monthlyPurchases && monthlyPurchases.length > 0) {
    const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    
    monthlyPurchases.forEach(item => {
        const [year, month] = item.bulan.split('-');
        const monthLabel = monthNames[parseInt(month) - 1] + ' ' + year;
        const index = chartLabels.indexOf(monthLabel);
        
        if (index !== -1) {
            pembelianData[index] = Math.round(item.total_nilai_pembelian / 1000000);
        }
    });
}

console.log('Chart Labels:', chartLabels);
console.log('Transaksi Data:', transaksiData);
console.log('Nilai Data:', nilaiData);
console.log('Pembelian Data:', pembelianData);

// Wait for DOM to be ready
document.addEventListener('DOMContentLoaded', function() {
    // Monthly Transactions Chart
    const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
    console.log('Canvas element found:', monthlyCtx);
    
    const monthlyChart = new Chart(monthlyCtx, {
    type: 'line',
    data: {
        labels: chartLabels,
        datasets: [{
            label: 'Jumlah Transaksi',
            data: transaksiData,
            borderColor: 'rgb(59, 130, 246)',
            backgroundColor: 'rgba(59, 130, 246, 0.1)',
            borderWidth: 3,
            fill: false,
            tension: 0.4,
            pointBackgroundColor: 'rgb(59, 130, 246)',
            pointBorderColor: '#fff',
            pointBorderWidth: 2,
            pointRadius: 4,
            pointHoverRadius: 6
        }, {
            label: 'Penjualan (Juta)',
            data: nilaiData,
            borderColor: 'rgb(16, 185, 129)',
            backgroundColor: 'rgba(16, 185, 129, 0.1)',
            borderWidth: 3,
            fill: false,
            tension: 0.4,
            pointBackgroundColor: 'rgb(16, 185, 129)',
            pointBorderColor: '#fff',
            pointBorderWidth: 2,
            pointRadius: 4,
            pointHoverRadius: 6,
            yAxisID: 'y1'
        }, {
            label: 'Pembelian (Juta)',
            data: pembelianData,
            borderColor: 'rgb(239, 68, 68)',
            backgroundColor: 'rgba(239, 68, 68, 0.1)',
            borderWidth: 3,
            fill: false,
            tension: 0.4,
            pointBackgroundColor: 'rgb(239, 68, 68)',
            pointBorderColor: '#fff',
            pointBorderWidth: 2,
            pointRadius: 4,
            pointHoverRadius: 6,
            yAxisID: 'y1'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: {
            intersect: false,
            mode: 'index'
        },
        plugins: {
            legend: {
                display: true,
                position: 'top'
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        if (context.datasetIndex === 0) {
                            return context.dataset.label + ': ' + context.parsed.y + ' transaksi';
                        } else {
                            return context.dataset.label + ': Rp ' + context.parsed.y.toLocaleString('id-ID');
                        }
                    }
                }
            }
        },
        scales: {
            x: {
                display: true,
                title: {
                    display: true,
                    text: 'Bulan'
                },
                grid: {
                    display: false
                }
            },
            y: {
                type: 'linear',
                display: true,
                position: 'left',
                beginAtZero: true,
                title: {
                    display: true,
                    text: 'Jumlah Transaksi'
                },
                grid: {
                    color: 'rgba(0, 0, 0, 0.1)'
                },
                ticks: {
                    stepSize: 1
                }
            },
            y1: {
                type: 'linear',
                display: true,
                position: 'right',
                beginAtZero: true,
                title: {
                    display: true,
                    text: 'Total Nilai (Juta)'
                },
                grid: {
                    drawOnChartArea: false
                },
                ticks: {
                    stepSize: 1
                }
            }
        }
    }
});

    console.log('Chart initialized successfully');
    
    // Update summary cards with chart data
    updateSummaryCards(monthlyChart.data);
    
    // Initialize Monthly Analytics Chart
    let monthlyAnalyticsChart;
    
    function loadMonthlyAnalyticsChart(months = 6) {
        fetch(`api/chart-data.php?periode=${months}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateMonthlyAnalyticsChart(data.data);
                    updateSummaryCards(data.data);
                } else {
                    console.error('Error loading chart data:', data.message || data.error);
                    showChartError('monthlyAnalyticsChart', data.message || data.error);
                }
            })
            .catch(error => {
                console.error('Error fetching chart data:', error);
                showChartError('monthlyAnalyticsChart', 'Gagal memuat data chart');
            });
    }
    
    function updateMonthlyAnalyticsChart(chartData) {
        const ctx = document.getElementById('monthlyAnalyticsChart').getContext('2d');
        
        if (monthlyAnalyticsChart) {
            monthlyAnalyticsChart.destroy();
        }
        
        monthlyAnalyticsChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: chartData.labels,
                datasets: chartData.datasets
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            padding: 20,
                            usePointStyle: true,
                            pointStyle: 'circle'
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                if (context.dataset.yAxisID === 'y1') {
                                    label += context.parsed.y + ' transaksi';
                                } else {
                                    label += 'Rp ' + new Intl.NumberFormat('id-ID').format(context.parsed.y);
                                }
                                return label;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        display: true,
                        title: {
                            display: true,
                            text: 'Bulan'
                        },
                        grid: {
                            display: false
                        }
                    },
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Nominal (Rp)'
                        },
                        ticks: {
                            callback: function(value) {
                                return 'Rp ' + new Intl.NumberFormat('id-ID').format(value);
                            }
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Jumlah Transaksi'
                        },
                        grid: {
                            drawOnChartArea: false,
                        },
                        ticks: {
                            callback: function(value) {
                                return value + ' transaksi';
                            }
                        }
                    }
                }
            }
        });
    }
    
    function updateSummaryCards(chartData) {
        let totalSales = 0;
        let totalPurchases = 0;
        let totalTransactions = 0;
        
        if (chartData.datasets && chartData.datasets.length > 0) {
            // Nominal Penjualan (dataset 0)
            if (chartData.datasets[0] && chartData.datasets[0].data) {
                totalSales = chartData.datasets[0].data.reduce((sum, val) => sum + val, 0);
            }
            
            // Jumlah Transaksi (dataset 1)
            if (chartData.datasets[1] && chartData.datasets[1].data) {
                totalTransactions = chartData.datasets[1].data.reduce((sum, val) => sum + val, 0);
            }
            
            // Nominal Pembelian (dataset 2)
            if (chartData.datasets[2] && chartData.datasets[2].data) {
                totalPurchases = chartData.datasets[2].data.reduce((sum, val) => sum + val, 0);
            }
        }
        
        document.getElementById('totalSales').textContent = 'Rp ' + new Intl.NumberFormat('id-ID').format(totalSales); // Data sudah dalam rupiah
        document.getElementById('totalPurchases').textContent = 'Rp ' + new Intl.NumberFormat('id-ID').format(totalPurchases); // Data sudah dalam rupiah
        document.getElementById('totalTransactions').textContent = new Intl.NumberFormat('id-ID').format(totalTransactions);
    }
    
    function showChartError(chartId, message) {
        const chartElement = document.getElementById(chartId);
        chartElement.style.display = 'none';
        const chartContainer = chartElement.parentElement;
        chartContainer.innerHTML = `<div class="text-center py-8"><i class="fas fa-exclamation-triangle text-red-500 text-3xl mb-3"></i><p class="text-red-600">Error: ${message}</p></div>`;
    }
    
    // Event listeners for period selection and refresh
    document.getElementById('periodSelect').addEventListener('change', function() {
        const selectedPeriod = parseInt(this.value);
        loadMonthlyAnalyticsChart(selectedPeriod);
    });
    
    document.getElementById('refreshChart').addEventListener('click', function() {
        const selectedPeriod = parseInt(document.getElementById('periodSelect').value);
        loadMonthlyAnalyticsChart(selectedPeriod);
    });
    
    // Load initial chart data
    loadMonthlyAnalyticsChart(6);
});

// Auto refresh data setiap 5 menit (commented out for development)
// setInterval(function() {
//     location.reload();
// }, 300000);

// Real-time clock update
function updateClock() {
    const now = new Date();
    const timeString = now.toLocaleTimeString('id-ID', { 
        hour: '2-digit', 
        minute: '2-digit',
        timeZone: 'Asia/Jakarta'
    });
    const clockElements = document.querySelectorAll('.real-time-clock');
    clockElements.forEach(el => el.textContent = timeString + ' WIB');
}

// Update clock every second
setInterval(updateClock, 1000);
</script>

<?php require_once 'layouts/footer.php'; ?>