<?php
require_once 'config/database.php';
require_once 'config/auth.php';

// Cek autentikasi
if (!AuthStatic::isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Cek role finance (sekarang menggunakan akunting)
AuthStatic::requireRole(['akunting']);

$user = AuthStatic::getCurrentUser();
$db = getDatabase();
/** @var Database $db */

// Fungsi helper untuk format rupiah
function formatRupiah($angka) {
    return 'Rp ' . number_format($angka, 0, ',', '.');
}

// Fungsi helper untuk format tanggal
function formatTanggal($tanggal) {
    return date('d/m/Y', strtotime($tanggal));
}

// === STATISTIK KEUANGAN ===
$stats = [];

// Cash Flow Hari Ini
$today_cashflow = $db->select("
    SELECT 
        COALESCE(SUM(CASE WHEN jenis_mutasi = 'masuk' THEN jumlah ELSE 0 END), 0) as pemasukan,
        COALESCE(SUM(CASE WHEN jenis_mutasi = 'keluar' THEN jumlah ELSE 0 END), 0) as pengeluaran
    FROM mutasi_kas 
    WHERE DATE(tanggal_mutasi) = CURDATE()
");
$stats['today_cashflow'] = $today_cashflow[0];
$stats['today_cashflow']['net'] = $stats['today_cashflow']['pemasukan'] - $stats['today_cashflow']['pengeluaran'];

// Cash Flow Bulan Ini
$month_cashflow = $db->select("
    SELECT 
        COALESCE(SUM(CASE WHEN jenis_mutasi = 'masuk' THEN jumlah ELSE 0 END), 0) as pemasukan,
        COALESCE(SUM(CASE WHEN jenis_mutasi = 'keluar' THEN jumlah ELSE 0 END), 0) as pengeluaran
    FROM mutasi_kas 
    WHERE MONTH(tanggal_mutasi) = MONTH(CURDATE()) AND YEAR(tanggal_mutasi) = YEAR(CURDATE())
");
$stats['month_cashflow'] = $month_cashflow[0];
$stats['month_cashflow']['net'] = $stats['month_cashflow']['pemasukan'] - $stats['month_cashflow']['pengeluaran'];

// Total Piutang
$piutang_stats = $db->select("
    SELECT 
        COUNT(*) as total_piutang,
        COALESCE(SUM(p.jumlah_piutang), 0) as total_nilai_piutang,
        COALESCE(SUM(p.jumlah_piutang - COALESCE(pembayaran.total_bayar, 0)), 0) as total_sisa_piutang
    FROM piutang p
    LEFT JOIN (
        SELECT piutang_id, SUM(jumlah_bayar) as total_bayar
        FROM pembayaran
        WHERE piutang_id IS NOT NULL
        GROUP BY piutang_id
    ) pembayaran ON p.id = pembayaran.piutang_id
    WHERE p.status IN ('belum_jatuh_tempo', 'mendekati_jatuh_tempo', 'jatuh_tempo')
");
$stats['piutang'] = $piutang_stats[0];

// Pendapatan dari Transaksi Bulan Ini
$revenue_stats = $db->select("
    SELECT 
        COUNT(*) as total_transaksi,
        COALESCE(SUM(total_amount), 0) as total_pendapatan,
        COALESCE(SUM(CASE WHEN status_transaksi = 'selesai' THEN total_amount ELSE 0 END), 0) as pendapatan_selesai
    FROM transaksi 
    WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())
");
$stats['revenue'] = $revenue_stats[0];

// Biaya Operasional Bulan Ini
$expense_stats = $db->select("
    SELECT 
        COUNT(*) as total_biaya,
        COALESCE(SUM(tarif_standar), 0) as total_pengeluaran
    FROM biaya_operasional 
    WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())
");
$stats['expense'] = $expense_stats[0];

// === DATA UNTUK CHART ===

// Cash Flow 12 bulan terakhir
$cashflow_monthly = $db->select("
    SELECT 
        DATE_FORMAT(tanggal_mutasi, '%Y-%m') as bulan,
        COALESCE(SUM(CASE WHEN jenis_mutasi = 'masuk' THEN jumlah ELSE 0 END), 0) as pemasukan,
        COALESCE(SUM(CASE WHEN jenis_mutasi = 'keluar' THEN jumlah ELSE 0 END), 0) as pengeluaran
    FROM mutasi_kas 
    WHERE tanggal_mutasi >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(tanggal_mutasi, '%Y-%m')
    ORDER BY bulan ASC
");

// Profit/Loss 6 bulan terakhir
$profit_loss = $db->select("
    SELECT 
        DATE_FORMAT(t.created_at, '%Y-%m') as bulan,
        COALESCE(SUM(t.total_amount), 0) as pendapatan,
        COALESCE(biaya.total_biaya, 0) as biaya
    FROM transaksi t
    LEFT JOIN (
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as bulan,
            SUM(tarif_standar) as total_biaya
        FROM biaya_operasional
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ) biaya ON DATE_FORMAT(t.created_at, '%Y-%m') = biaya.bulan
    WHERE t.created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    AND t.status_transaksi = 'selesai'
    GROUP BY DATE_FORMAT(t.created_at, '%Y-%m')
    ORDER BY bulan ASC
");

// Aging Piutang
$aging_piutang = $db->select("
    SELECT 
        CASE 
            WHEN DATEDIFF(CURDATE(), p.tanggal_jatuh_tempo) <= 0 THEN 'Belum Jatuh Tempo'
            WHEN DATEDIFF(CURDATE(), p.tanggal_jatuh_tempo) BETWEEN 1 AND 30 THEN '1-30 Hari'
            WHEN DATEDIFF(CURDATE(), p.tanggal_jatuh_tempo) BETWEEN 31 AND 60 THEN '31-60 Hari'
            WHEN DATEDIFF(CURDATE(), p.tanggal_jatuh_tempo) BETWEEN 61 AND 90 THEN '61-90 Hari'
            ELSE '> 90 Hari'
        END as kategori_aging,
        COUNT(*) as jumlah,
        COALESCE(SUM(p.jumlah_piutang - COALESCE(pembayaran.total_bayar, 0)), 0) as total_sisa
    FROM piutang p
    LEFT JOIN (
        SELECT piutang_id, SUM(jumlah_bayar) as total_bayar
        FROM pembayaran
        WHERE piutang_id IS NOT NULL
        GROUP BY piutang_id
    ) pembayaran ON p.id = pembayaran.piutang_id
    WHERE p.status IN ('belum_jatuh_tempo', 'mendekati_jatuh_tempo', 'jatuh_tempo')
    AND (p.jumlah_piutang - COALESCE(pembayaran.total_bayar, 0)) > 0
    GROUP BY kategori_aging
    ORDER BY 
        CASE kategori_aging
            WHEN 'Belum Jatuh Tempo' THEN 1
            WHEN '1-30 Hari' THEN 2
            WHEN '31-60 Hari' THEN 3
            WHEN '61-90 Hari' THEN 4
            ELSE 5
        END
");

// Top 5 Kategori Biaya
$top_expenses = $db->select("
    SELECT 
        kategori,
        COUNT(*) as jumlah_transaksi,
        COALESCE(SUM(tarif_standar), 0) as total_biaya
    FROM biaya_operasional 
    WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())
    GROUP BY kategori
    ORDER BY total_biaya DESC
    LIMIT 5
");

// Piutang yang akan jatuh tempo (7 hari ke depan)
$upcoming_piutang = $db->select("
    SELECT 
        p.id,
        p.tanggal_jatuh_tempo,
        p.jumlah_piutang,
        COALESCE(pembayaran.total_bayar, 0) as total_bayar,
        (p.jumlah_piutang - COALESCE(pembayaran.total_bayar, 0)) as sisa_piutang,
        t.nomor_invoice,
        d.nama_desa
    FROM piutang p
    JOIN transaksi t ON p.transaksi_id = t.id
    JOIN desa d ON t.desa_id = d.id
    LEFT JOIN (
        SELECT piutang_id, SUM(jumlah_bayar) as total_bayar
        FROM pembayaran
        WHERE piutang_id IS NOT NULL
        GROUP BY piutang_id
    ) pembayaran ON p.id = pembayaran.piutang_id
    WHERE p.tanggal_jatuh_tempo BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    AND p.status IN ('belum_jatuh_tempo', 'mendekati_jatuh_tempo')
    AND (p.jumlah_piutang - COALESCE(pembayaran.total_bayar, 0)) > 0
    ORDER BY p.tanggal_jatuh_tempo ASC
    LIMIT 10
");

// Transaksi Keuangan Terbaru
$recent_transactions = $db->select("
    SELECT 
        'mutasi' as tipe,
        id,
        tanggal_mutasi as tanggal_transaksi,
        keterangan COLLATE utf8mb4_unicode_ci as deskripsi,
        jumlah,
        jenis_mutasi COLLATE utf8mb4_unicode_ci as jenis_transaksi,
        NULL as desa_nama
    FROM mutasi_kas 
    WHERE tanggal_mutasi >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    
    UNION ALL
    
    SELECT 
        'biaya' as tipe,
        b.id,
        b.created_at as tanggal_transaksi,
        CONCAT(b.kategori, ' - ', b.nama_biaya) COLLATE utf8mb4_unicode_ci as deskripsi,
        b.tarif_standar as jumlah,
        'keluar' COLLATE utf8mb4_unicode_ci as jenis_transaksi,
        NULL as desa_nama
    FROM biaya_operasional b
    WHERE b.created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    
    ORDER BY tanggal_transaksi DESC
    LIMIT 15
");

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Finance - Sistem Manajemen Desa</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/date-fns@2.29.3/index.min.js"></script>
</head>
<body class="bg-gray-50">
    <?php include 'layouts/header.php'; ?>

    <div class="container mx-auto px-4 py-6">
        <!-- Header Dashboard -->
        <div class="mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">
                        <i class="fas fa-chart-line mr-2 text-green-600"></i>
                        Dashboard Finance
                    </h1>
                    <p class="text-gray-600 mt-1">Monitoring dan analisis keuangan perusahaan</p>
                </div>
                <div class="flex space-x-2">
                    <button onclick="refreshData()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition duration-200">
                        <i class="fas fa-sync-alt mr-2"></i>Refresh
                    </button>
                    <a href="biaya-add.php" class="inline-flex items-center px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors">
                        <i class="fas fa-plus mr-2"></i>
                        Tambah Biaya
                    </a>
                    <a href="piutang.php" class="inline-flex items-center px-4 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition-colors">
                        <i class="fas fa-eye mr-2"></i>
                        Kelola Piutang
                    </a>
                    <a href="transaksi-add.php" class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
                        <i class="fas fa-plus mr-2"></i>
                        Buat Transaksi
                    </a>
                    <a href="laporan.php" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg transition duration-200">
                        <i class="fas fa-file-export mr-2"></i>Export Laporan
                    </a>
                </div>
            </div>
        </div>

        <!-- Alert untuk Piutang Jatuh Tempo -->
        <?php if (!empty($upcoming_piutang)): ?>
        <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-6">
            <div class="flex">
                <div class="flex-shrink-0">
                    <i class="fas fa-exclamation-triangle text-yellow-400"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-yellow-700">
                        <strong>Perhatian!</strong> Ada <?= count($upcoming_piutang) ?> piutang yang akan jatuh tempo dalam 7 hari ke depan.
                        <a href="piutang.php" class="underline font-medium">Lihat detail</a>
                    </p>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Statistik Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <!-- Cash Flow Hari Ini -->
            <div class="bg-gradient-to-r from-blue-500 to-blue-600 rounded-lg shadow-md p-6 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-blue-100 text-sm font-medium">Cash Flow Hari Ini</p>
                        <p class="text-2xl font-bold mt-1"><?= formatRupiah($stats['today_cashflow']['net']) ?></p>
                        <p class="text-blue-100 text-xs mt-1">
                            Masuk: <?= formatRupiah($stats['today_cashflow']['pemasukan']) ?> | 
                            Keluar: <?= formatRupiah($stats['today_cashflow']['pengeluaran']) ?>
                        </p>
                    </div>
                    <div class="bg-blue-400 bg-opacity-30 rounded-full w-12 h-12 flex items-center justify-center">
                        <i class="fas fa-exchange-alt text-lg"></i>
                    </div>
                </div>
                <div class="mt-3 flex items-center">
                    <i class="fas fa-calendar-day text-blue-300 mr-1"></i>
                    <span class="text-blue-300 text-xs font-medium">Today</span>
                </div>
            </div>

            <!-- Total Piutang -->
            <div class="bg-gradient-to-r from-red-500 to-red-600 rounded-lg shadow-md p-6 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-red-100 text-sm font-medium">Total Piutang</p>
                        <p class="text-2xl font-bold mt-1"><?= formatRupiah($stats['piutang']['total_sisa_piutang']) ?></p>
                        <p class="text-red-100 text-xs mt-1"><?= $stats['piutang']['total_piutang'] ?> transaksi</p>
                    </div>
                    <div class="bg-red-400 bg-opacity-30 rounded-full w-12 h-12 flex items-center justify-center">
                        <i class="fas fa-credit-card text-lg"></i>
                    </div>
                </div>
                <div class="mt-3 flex items-center">
                    <i class="fas fa-clock text-red-300 mr-1"></i>
                    <span class="text-red-300 text-xs font-medium">Outstanding</span>
                </div>
            </div>

            <!-- Pendapatan Bulan Ini -->
            <div class="bg-gradient-to-r from-green-500 to-green-600 rounded-lg shadow-md p-6 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-green-100 text-sm font-medium">Pendapatan Bulan Ini</p>
                        <p class="text-2xl font-bold mt-1"><?= formatRupiah($stats['revenue']['pendapatan_selesai']) ?></p>
                        <p class="text-green-100 text-xs mt-1"><?= $stats['revenue']['total_transaksi'] ?> transaksi</p>
                    </div>
                    <div class="bg-green-400 bg-opacity-30 rounded-full w-12 h-12 flex items-center justify-center">
                        <i class="fas fa-arrow-up text-lg"></i>
                    </div>
                </div>
                <div class="mt-3 flex items-center">
                    <i class="fas fa-chart-line text-green-300 mr-1"></i>
                    <span class="text-green-300 text-xs font-medium">Revenue</span>
                </div>
            </div>

            <!-- Biaya Operasional -->
            <div class="bg-gradient-to-r from-purple-500 to-purple-600 rounded-lg shadow-md p-6 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-purple-100 text-sm font-medium">Biaya Operasional</p>
                        <p class="text-2xl font-bold mt-1"><?= formatRupiah($stats['expense']['total_pengeluaran']) ?></p>
                        <p class="text-purple-100 text-xs mt-1"><?= $stats['expense']['total_biaya'] ?> transaksi</p>
                    </div>
                    <div class="bg-purple-400 bg-opacity-30 rounded-full w-12 h-12 flex items-center justify-center">
                        <i class="fas fa-arrow-down text-lg"></i>
                    </div>
                </div>
                <div class="mt-3 flex items-center">
                    <i class="fas fa-receipt text-purple-300 mr-1"></i>
                    <span class="text-purple-300 text-xs font-medium">Expenses</span>
                </div>
            </div>
        </div>

        <!-- Charts Section -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <!-- Cash Flow Chart -->
            <div class="bg-white rounded-lg shadow-md border border-gray-200 p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">
                        <i class="fas fa-chart-area mr-2 text-blue-600"></i>
                        Cash Flow 12 Bulan Terakhir
                    </h3>
                    <div class="flex space-x-2">
                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                            <span class="w-2 h-2 bg-green-500 rounded-full mr-1"></span>
                            Pemasukan
                        </span>
                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800">
                            <span class="w-2 h-2 bg-red-500 rounded-full mr-1"></span>
                            Pengeluaran
                        </span>
                    </div>
                </div>
                <div class="h-80">
                    <canvas id="cashflowChart"></canvas>
                </div>
            </div>

            <!-- Profit/Loss Chart -->
            <div class="bg-white rounded-lg shadow-md border border-gray-200 p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">
                        <i class="fas fa-chart-line mr-2 text-green-600"></i>
                        Profit/Loss 6 Bulan Terakhir
                    </h3>
                </div>
                <div class="h-80">
                    <canvas id="profitLossChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Second Row Charts -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <!-- Aging Piutang Chart -->
            <div class="bg-white rounded-lg shadow-md border border-gray-200 p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">
                        <i class="fas fa-chart-pie mr-2 text-red-600"></i>
                        Aging Piutang
                    </h3>
                </div>
                <div class="h-80">
                    <canvas id="agingChart"></canvas>
                </div>
            </div>

            <!-- Top Expenses Chart -->
            <div class="bg-white rounded-lg shadow-md border border-gray-200 p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">
                        <i class="fas fa-chart-bar mr-2 text-purple-600"></i>
                        Top 5 Kategori Biaya
                    </h3>
                </div>
                <div class="h-80">
                    <canvas id="expensesChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Tables Section -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Piutang Jatuh Tempo -->
            <div class="bg-white rounded-lg shadow-md border border-gray-200 p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">
                        <i class="fas fa-exclamation-triangle mr-2 text-yellow-600"></i>
                        Piutang Jatuh Tempo (7 Hari)
                    </h3>
                    <a href="piutang.php" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                        Lihat Semua <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>
                
                <?php if (empty($upcoming_piutang)): ?>
                    <div class="text-center py-8">
                        <i class="fas fa-check-circle text-green-400 text-4xl mb-3"></i>
                        <p class="text-gray-600">Tidak ada piutang yang akan jatuh tempo</p>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Invoice</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Desa</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Jatuh Tempo</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Sisa</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($upcoming_piutang as $piutang): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 text-sm font-medium text-gray-900">
                                        <?= htmlspecialchars($piutang['nomor_invoice']) ?>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-600">
                                        <?= htmlspecialchars($piutang['nama_desa']) ?>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-600">
                                        <?= formatTanggal($piutang['tanggal_jatuh_tempo']) ?>
                                    </td>
                                    <td class="px-4 py-3 text-sm font-medium text-red-600">
                                        <?= formatRupiah($piutang['sisa_piutang']) ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Transaksi Keuangan Terbaru -->
            <div class="bg-white rounded-lg shadow-md border border-gray-200 p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">
                        <i class="fas fa-clock mr-2 text-blue-600"></i>
                        Transaksi Keuangan Terbaru
                    </h3>
                    <a href="mutasi-kas-add.php" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                        Tambah Mutasi <i class="fas fa-plus ml-1"></i>
                    </a>
                </div>
                
                <?php if (empty($recent_transactions)): ?>
                    <div class="text-center py-8">
                        <i class="fas fa-file-invoice text-gray-400 text-4xl mb-3"></i>
                        <p class="text-gray-600">Belum ada transaksi keuangan</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-3 max-h-80 overflow-y-auto">
                        <?php foreach ($recent_transactions as $transaction): ?>
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                            <div class="flex items-center">
                                <div class="<?= $transaction['jenis_transaksi'] === 'masuk' ? 'bg-green-100 text-green-600' : 'bg-red-100 text-red-600' ?> rounded-full w-8 h-8 flex items-center justify-center mr-3">
                                    <i class="fas <?= $transaction['jenis_transaksi'] === 'masuk' ? 'fa-arrow-up' : 'fa-arrow-down' ?> text-xs"></i>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-900"><?= htmlspecialchars($transaction['deskripsi']) ?></p>
                                    <p class="text-xs text-gray-500"><?= formatTanggal($transaction['tanggal_transaksi']) ?></p>
                                </div>
                            </div>
                            <div class="text-right">
                                <p class="text-sm font-medium <?= $transaction['jenis_transaksi'] === 'masuk' ? 'text-green-600' : 'text-red-600' ?>">
                                    <?= $transaction['jenis_transaksi'] === 'masuk' ? '+' : '-' ?><?= formatRupiah($transaction['jumlah']) ?>
                                </p>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="mt-8">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">
                <i class="fas fa-bolt mr-2 text-yellow-600"></i>
                Quick Actions
            </h3>
            <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
                <a href="mutasi-kas-add.php" class="bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 p-4 rounded-lg text-center transition duration-200 text-white">
                    <i class="fas fa-plus text-lg mb-2"></i>
                    <p class="text-xs font-medium">Tambah Mutasi</p>
                </a>
                <a href="biaya-add.php" class="bg-gradient-to-r from-red-500 to-red-600 hover:from-red-600 hover:to-red-700 p-4 rounded-lg text-center transition duration-200 text-white">
                    <i class="fas fa-receipt text-lg mb-2"></i>
                    <p class="text-xs font-medium">Tambah Biaya</p>
                </a>
                <a href="piutang.php" class="bg-gradient-to-r from-yellow-500 to-yellow-600 hover:from-yellow-600 hover:to-yellow-700 p-4 rounded-lg text-center transition duration-200 text-white">
                    <i class="fas fa-credit-card text-lg mb-2"></i>
                    <p class="text-xs font-medium">Kelola Piutang</p>
                </a>
                <a href="laporan.php" class="bg-gradient-to-r from-green-500 to-green-600 hover:from-green-600 hover:to-green-700 p-4 rounded-lg text-center transition duration-200 text-white">
                    <i class="fas fa-chart-bar text-lg mb-2"></i>
                    <p class="text-xs font-medium">Laporan</p>
                </a>
                <a href="saldo-bank.php" class="bg-gradient-to-r from-purple-500 to-purple-600 hover:from-purple-600 hover:to-purple-700 p-4 rounded-lg text-center transition duration-200 text-white">
                    <i class="fas fa-university text-lg mb-2"></i>
                    <p class="text-xs font-medium">Saldo Bank</p>
                </a>
                <a href="bank.php" class="bg-gradient-to-r from-indigo-500 to-indigo-600 hover:from-indigo-600 hover:to-indigo-700 p-4 rounded-lg text-center transition duration-200 text-white">
                    <i class="fas fa-building text-lg mb-2"></i>
                    <p class="text-xs font-medium">Kelola Bank</p>
                </a>
            </div>
        </div>
    </div>

    <?php include 'layouts/footer.php'; ?>

    <script>
    // Data untuk charts
    const cashflowData = <?= json_encode($cashflow_monthly) ?>;
    const profitLossData = <?= json_encode($profit_loss) ?>;
    const agingData = <?= json_encode($aging_piutang) ?>;
    const expensesData = <?= json_encode($top_expenses) ?>;

    // Cash Flow Chart
    const cashflowCtx = document.getElementById('cashflowChart').getContext('2d');
    new Chart(cashflowCtx, {
        type: 'line',
        data: {
            labels: cashflowData.map(item => {
                const [year, month] = item.bulan.split('-');
                return new Date(year, month - 1).toLocaleDateString('id-ID', { month: 'short', year: 'numeric' });
            }),
            datasets: [{
                label: 'Pemasukan',
                data: cashflowData.map(item => item.pemasukan),
                borderColor: 'rgb(34, 197, 94)',
                backgroundColor: 'rgba(34, 197, 94, 0.1)',
                tension: 0.4,
                fill: true
            }, {
                label: 'Pengeluaran',
                data: cashflowData.map(item => item.pengeluaran),
                borderColor: 'rgb(239, 68, 68)',
                backgroundColor: 'rgba(239, 68, 68, 0.1)',
                tension: 0.4,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top'
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return 'Rp ' + new Intl.NumberFormat('id-ID').format(value);
                        }
                    }
                }
            }
        }
    });

    // Profit/Loss Chart
    const profitLossCtx = document.getElementById('profitLossChart').getContext('2d');
    new Chart(profitLossCtx, {
        type: 'bar',
        data: {
            labels: profitLossData.map(item => {
                const [year, month] = item.bulan.split('-');
                return new Date(year, month - 1).toLocaleDateString('id-ID', { month: 'short', year: 'numeric' });
            }),
            datasets: [{
                label: 'Pendapatan',
                data: profitLossData.map(item => item.pendapatan),
                backgroundColor: 'rgba(34, 197, 94, 0.8)'
            }, {
                label: 'Biaya',
                data: profitLossData.map(item => item.biaya),
                backgroundColor: 'rgba(239, 68, 68, 0.8)'
            }, {
                label: 'Profit',
                data: profitLossData.map(item => item.pendapatan - item.biaya),
                backgroundColor: 'rgba(59, 130, 246, 0.8)'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top'
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return 'Rp ' + new Intl.NumberFormat('id-ID').format(value);
                        }
                    }
                }
            }
        }
    });

    // Aging Piutang Chart
    const agingCtx = document.getElementById('agingChart').getContext('2d');
    new Chart(agingCtx, {
        type: 'doughnut',
        data: {
            labels: agingData.map(item => item.kategori_aging),
            datasets: [{
                data: agingData.map(item => item.total_sisa),
                backgroundColor: [
                    'rgba(34, 197, 94, 0.8)',
                    'rgba(251, 191, 36, 0.8)',
                    'rgba(249, 115, 22, 0.8)',
                    'rgba(239, 68, 68, 0.8)',
                    'rgba(127, 29, 29, 0.8)'
                ]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.label + ': Rp ' + new Intl.NumberFormat('id-ID').format(context.parsed);
                        }
                    }
                }
            }
        }
    });

    // Top Expenses Chart
    const expensesCtx = document.getElementById('expensesChart').getContext('2d');
    new Chart(expensesCtx, {
        type: 'bar',
        data: {
            labels: expensesData.map(item => item.kategori),
            datasets: [{
                label: 'Total Biaya',
                data: expensesData.map(item => item.total_biaya),
                backgroundColor: 'rgba(147, 51, 234, 0.8)'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return 'Rp ' + new Intl.NumberFormat('id-ID').format(value);
                        }
                    }
                }
            }
        }
    });

    // Refresh data function
    function refreshData() {
        location.reload();
    }

    // Auto refresh every 5 minutes
    setInterval(refreshData, 300000);
    </script>
</body>
</html>