<?php
require_once 'config/database.php';
require_once 'config/auth.php';

// Cek autentikasi dan otorisasi
if (!AuthStatic::isLoggedIn()) {
    header('Location: login.php');
    exit;
}

if (!AuthStatic::hasRole(['admin', 'finance', 'sales'])) {
    header('Location: index.php?error=' . urlencode('Anda tidak memiliki akses ke halaman ini.'));
    exit;
}

$user = AuthStatic::getCurrentUser();
$db = getDatabase();
/** @var Database $db */

// Get date range from URL parameters
$start_date = $_GET['start_date'] ?? date('Y-m-01'); // First day of current month
$end_date = $_GET['end_date'] ?? date('Y-m-d'); // Today
$report_type = $_GET['report_type'] ?? 'summary';

// Validate date range
if (strtotime($start_date) > strtotime($end_date)) {
    $temp = $start_date;
    $start_date = $end_date;
    $end_date = $temp;
}

// Get summary statistics
$stats = [];

// Total Pendapatan
$pendapatan = $db->select("
    SELECT 
        COUNT(*) as total_transaksi,
        SUM(total_amount) as total_pendapatan,
        SUM(CASE WHEN metode_pembayaran = 'tunai' THEN total_amount ELSE 0 END) as pendapatan_tunai,
        SUM(CASE WHEN metode_pembayaran = 'dp_pelunasan' THEN dp_amount ELSE 0 END) as pendapatan_dp,
        SUM(CASE WHEN metode_pembayaran = 'tempo' THEN dp_amount ELSE 0 END) as pendapatan_tempo
    FROM transaksi 
    WHERE DATE(tanggal_transaksi) BETWEEN ? AND ?
", [$start_date, $end_date]);

$stats['pendapatan'] = $pendapatan[0] ?? [
    'total_transaksi' => 0,
    'total_pendapatan' => 0,
    'pendapatan_tunai' => 0,
    'pendapatan_dp' => 0,
    'pendapatan_tempo' => 0
];

// Piutang
$piutang = $db->select("
    SELECT 
        COUNT(*) as total_piutang,
        SUM(p.jumlah_piutang - COALESCE(pembayaran.total_bayar, 0)) as total_sisa_piutang,
        SUM(CASE WHEN p.tanggal_jatuh_tempo < CURDATE() AND (p.jumlah_piutang - COALESCE(pembayaran.total_bayar, 0)) > 0 THEN (p.jumlah_piutang - COALESCE(pembayaran.total_bayar, 0)) ELSE 0 END) as piutang_overdue
    FROM piutang p
    LEFT JOIN (
        SELECT piutang_id, SUM(jumlah_bayar) as total_bayar
        FROM pembayaran
        GROUP BY piutang_id
    ) pembayaran ON p.id = pembayaran.piutang_id
    WHERE p.status != 'lunas' AND (p.jumlah_piutang - COALESCE(pembayaran.total_bayar, 0)) > 0
");

$stats['piutang'] = $piutang[0] ?? [
    'total_piutang' => 0,
    'total_sisa_piutang' => 0,
    'piutang_overdue' => 0
];

// Produk & Layanan
$produk_stats = $db->select("
    SELECT 
        COUNT(DISTINCT p.id) as total_produk_terjual,
        SUM(td.quantity) as total_unit_terjual,
        SUM(td.subtotal) as total_nilai_produk
    FROM transaksi_detail td
    JOIN produk p ON td.produk_id = p.id
    JOIN transaksi t ON td.transaksi_id = t.id
    WHERE td.produk_id IS NOT NULL AND DATE(t.tanggal_transaksi) BETWEEN ? AND ?
", [$start_date, $end_date]);

$layanan_stats = $db->select("
    SELECT 
        COUNT(DISTINCT l.id) as total_layanan_terjual,
        SUM(td.quantity) as total_unit_terjual,
        SUM(td.subtotal) as total_nilai_layanan
    FROM transaksi_detail td
    JOIN layanan l ON td.layanan_id = l.id
    JOIN transaksi t ON td.transaksi_id = t.id
    WHERE td.layanan_id IS NOT NULL AND DATE(t.tanggal_transaksi) BETWEEN ? AND ?
", [$start_date, $end_date]);

$stats['produk'] = $produk_stats[0] ?? [
    'total_produk_terjual' => 0,
    'total_unit_terjual' => 0,
    'total_nilai_produk' => 0
];

$stats['layanan'] = $layanan_stats[0] ?? [
    'total_layanan_terjual' => 0,
    'total_unit_terjual' => 0,
    'total_nilai_layanan' => 0
];

// Kinerja Sales
$sales_performance = $db->select("
    SELECT 
        u.nama_lengkap as sales_nama,
        COUNT(t.id) as total_transaksi,
        SUM(t.total_amount) as total_penjualan,
        AVG(t.total_amount) as rata_rata_transaksi
    FROM users u
    LEFT JOIN transaksi t ON u.id = t.user_id AND DATE(t.tanggal_transaksi) BETWEEN ? AND ?
    WHERE u.role = 'sales' AND u.status = 'aktif'
    GROUP BY u.id, u.nama_lengkap
    ORDER BY total_penjualan DESC
", [$start_date, $end_date]);

// Top Desa by Revenue
$top_desa = $db->select("
    SELECT 
        d.nama_desa,
        COUNT(t.id) as total_transaksi,
        SUM(t.total_amount) as total_pendapatan
    FROM desa d
    LEFT JOIN transaksi t ON d.id = t.desa_id AND DATE(t.tanggal_transaksi) BETWEEN ? AND ?
    GROUP BY d.id, d.nama_desa
    HAVING total_transaksi > 0
    ORDER BY total_pendapatan DESC
    LIMIT 10
", [$start_date, $end_date]);

// Monthly trend (last 12 months)
$monthly_trend = $db->select("
    SELECT 
        DATE_FORMAT(tanggal_transaksi, '%Y-%m') as bulan,
        COUNT(*) as total_transaksi,
        SUM(total_amount) as total_pendapatan
    FROM transaksi
    WHERE tanggal_transaksi >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(tanggal_transaksi, '%Y-%m')
    ORDER BY bulan
");

// Format currency function
function formatCurrency($amount) {
    return 'Rp ' . number_format($amount ?? 0, 0, ',', '.');
}

// Format number function
function formatNumber($number) {
    return number_format($number ?? 0, 0, ',', '.');
}

$page_title = 'Laporan & Analitik';
require_once 'layouts/header.php';
?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!-- Page Header -->
    <div class="bg-white shadow-sm border-b border-gray-200 mb-6">
        <div class="px-6 py-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Laporan & Analitik</h1>
                    <p class="text-sm text-gray-600 mt-1">Analisis kinerja dan laporan keuangan</p>
                </div>
                <div class="mt-4 sm:mt-0 flex space-x-2">
                    <a href="laporan-export.php?start_date=<?= $start_date ?>&end_date=<?= $end_date ?>" 
                       class="inline-flex items-center px-4 py-2 bg-primary-600 border border-transparent rounded-lg text-sm font-medium text-white hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 transition-colors" target="_blank">
                        <i class="fas fa-download mr-2"></i>
                        Export Excel
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Container -->
    <div class="max-w-7xl ml-0 px-4 sm:px-6 lg:px-8 py-8">
                <!-- Filter Panel -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-6">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-800">
                            <i class="fas fa-filter text-blue-600 mr-2"></i> Filter Laporan
                        </h3>
                    </div>
                    <div class="p-6">
                        <form method="GET" class="space-y-4">
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label for="start_date" class="block text-sm font-medium text-gray-700 mb-2">Dari Tanggal:</label>
                                    <input type="date" name="start_date" id="start_date" 
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" 
                                           value="<?= htmlspecialchars($start_date) ?>">
                                </div>
                                <div>
                                    <label for="end_date" class="block text-sm font-medium text-gray-700 mb-2">Sampai Tanggal:</label>
                                    <input type="date" name="end_date" id="end_date" 
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" 
                                           value="<?= htmlspecialchars($end_date) ?>">
                                </div>
                                <div class="flex items-end">
                                    <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-md transition duration-200">
                                        <i class="fas fa-filter mr-2"></i> Filter
                                    </button>
                                </div>
                            </div>
                        </form>
                        <div class="mt-4">
                            <p class="text-sm text-gray-600">
                                Periode: <?= date('d/m/Y', strtotime($start_date)) ?> - <?= date('d/m/Y', strtotime($end_date)) ?>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Statistics Overview -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-green-100 text-green-600 mr-4">
                                <i class="fas fa-dollar-sign text-xl"></i>
                            </div>
                            <div>
                                <p class="text-xl font-bold text-gray-900"><?= formatCurrency($stats['pendapatan']['total_pendapatan']) ?></p>
                                <p class="text-sm text-gray-600">Total Pendapatan</p>
                                <p class="text-xs text-green-600 mt-1">
                                    <?= formatNumber($stats['pendapatan']['total_transaksi']) ?> transaksi
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-blue-100 text-blue-600 mr-4">
                                <i class="fas fa-credit-card text-xl"></i>
                            </div>
                            <div>
                                <p class="text-xl font-bold text-gray-900"><?= formatCurrency($stats['pendapatan']['pendapatan_tunai']) ?></p>
                                <p class="text-sm text-gray-600">Pendapatan Tunai</p>
                                <p class="text-xs text-blue-600 mt-1">
                                    <?= $stats['pendapatan']['total_pendapatan'] > 0 ? 
                                        number_format(($stats['pendapatan']['pendapatan_tunai'] / $stats['pendapatan']['total_pendapatan']) * 100, 1) : 0 ?>% dari total
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-yellow-100 text-yellow-600 mr-4">
                                <i class="fas fa-clock text-xl"></i>
                            </div>
                            <div>
                                <p class="text-xl font-bold text-gray-900"><?= formatCurrency($stats['piutang']['total_sisa_piutang']) ?></p>
                                <p class="text-sm text-gray-600">Total Piutang</p>
                                <p class="text-xs text-yellow-600 mt-1">
                                    <?= formatNumber($stats['piutang']['total_piutang']) ?> transaksi tempo
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-red-100 text-red-600 mr-4">
                                <i class="fas fa-exclamation-triangle text-xl"></i>
                            </div>
                            <div>
                                <p class="text-xl font-bold text-gray-900"><?= formatCurrency($stats['piutang']['piutang_overdue']) ?></p>
                                <p class="text-sm text-gray-600">Piutang Terlambat</p>
                                <p class="text-xs text-red-600 mt-1">
                                    Perlu tindak lanjut
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Revenue Trend Chart -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-6">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-800">
                            <i class="fas fa-chart-line text-blue-600 mr-2"></i> Tren Pendapatan Bulanan
                        </h3>
                    </div>
                    <div class="p-6">
                        <div class="w-full h-96">
                            <canvas id="revenueChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Sales Performance -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-6">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-800">
                            <i class="fas fa-users text-blue-600 mr-2"></i> Kinerja Sales
                        </h3>
                    </div>
                    <div class="p-6">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200" id="salesTable">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama Sales</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Transaksi</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Penjualan</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rata-rata per Transaksi</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($sales_performance as $sales): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?= htmlspecialchars($sales['sales_nama']) ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= formatNumber($sales['total_transaksi'] ?: 0) ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= formatCurrency($sales['total_penjualan'] ?: 0) ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= formatCurrency($sales['rata_rata_transaksi'] ?: 0) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Top Desa -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-6">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-800">
                            <i class="fas fa-map-marker-alt text-blue-600 mr-2"></i> Top 10 Desa by Revenue
                        </h3>
                    </div>
                    <div class="p-6">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200" id="desaTable">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ranking</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama Desa</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Transaksi</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Pendapatan</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($top_desa as $index => $desa): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php if ($index < 3): ?>
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $index === 0 ? 'bg-yellow-100 text-yellow-800' : ($index === 1 ? 'bg-gray-100 text-gray-800' : 'bg-blue-100 text-blue-800') ?>">
                                                    #<?= $index + 1 ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-gray-500">#<?= $index + 1 ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?= htmlspecialchars($desa['nama_desa']) ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= formatNumber($desa['total_transaksi']) ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= formatCurrency($desa['total_pendapatan']) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Product vs Service Performance -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-6">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-800">
                            <i class="fas fa-chart-pie text-blue-600 mr-2"></i> Performa Produk vs Layanan
                        </h3>
                    </div>
                    <div class="p-6">
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                            <div>
                                <div class="h-80 flex items-center justify-center">
                                    <canvas id="productServiceChart"></canvas>
                                </div>
                            </div>
                            <div>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kategori</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Unit Terjual</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Nilai</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">Produk</td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= formatNumber($stats['produk']['total_unit_terjual']) ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= formatCurrency($stats['produk']['total_nilai_produk']) ?></td>
                                            </tr>
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">Layanan</td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= formatNumber($stats['layanan']['total_unit_terjual']) ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= formatCurrency($stats['layanan']['total_nilai_layanan']) ?></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php require_once 'layouts/footer.php'; ?>

    <!-- Additional JavaScript for Charts -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script>
        // Initialize DataTables
        $(document).ready(function() {
            $('#salesTable').DataTable({
                responsive: true,
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.10.24/i18n/Indonesian.json'
                }
            });
            
            $('#desaTable').DataTable({
                responsive: true,
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.10.24/i18n/Indonesian.json'
                }
            });
        });
        
        // Revenue Trend Chart
        const revenueCtx = document.getElementById('revenueChart').getContext('2d');
        const monthlyData = <?= json_encode($monthly_trend ?: []) ?>;
        
        const revenueChart = new Chart(revenueCtx, {
            type: 'line',
            data: {
                labels: monthlyData.map(item => {
                    const date = new Date(item.bulan + '-01');
                    return date.toLocaleDateString('id-ID', { month: 'short', year: 'numeric' });
                }),
                datasets: [{
                    label: 'Pendapatan',
                    data: monthlyData.map(item => item.total_pendapatan || 0),
                    borderColor: '#007bff',
                    backgroundColor: 'rgba(0, 123, 255, 0.1)',
                    tension: 0.4,
                    fill: true
                }, {
                    label: 'Jumlah Transaksi',
                    data: monthlyData.map(item => item.total_transaksi || 0),
                    borderColor: '#28a745',
                    backgroundColor: 'rgba(40, 167, 69, 0.1)',
                    tension: 0.4,
                    yAxisID: 'y1'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        ticks: {
                            callback: function(value) {
                                return 'Rp ' + value.toLocaleString('id-ID');
                            }
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        grid: {
                            drawOnChartArea: false,
                        },
                    }
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                if (context.datasetIndex === 0) {
                                    return 'Pendapatan: Rp ' + context.parsed.y.toLocaleString('id-ID');
                                } else {
                                    return 'Transaksi: ' + context.parsed.y + ' transaksi';
                                }
                            }
                        }
                    }
                }
            }
        });
        
        // Product vs Service Chart
        const productServiceCtx = document.getElementById('productServiceChart').getContext('2d');
        const productValue = <?= $stats['produk']['total_nilai_produk'] ?: 0 ?>;
        const serviceValue = <?= $stats['layanan']['total_nilai_layanan'] ?: 0 ?>;
        
        const productServiceChart = new Chart(productServiceCtx, {
            type: 'doughnut',
            data: {
                labels: ['Produk', 'Layanan'],
                datasets: [{
                    data: [productValue, serviceValue],
                    backgroundColor: ['#007bff', '#28a745'],
                    borderWidth: 2,
                    borderColor: '#fff'
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
                                const total = productValue + serviceValue;
                                const percentage = total > 0 ? ((context.parsed / total) * 100).toFixed(1) : 0;
                                return context.label + ': Rp ' + context.parsed.toLocaleString('id-ID') + ' (' + percentage + '%)';
                            }
                        }
                    }
                }
            }
        });
        
        // Auto-hide alerts
        setTimeout(function() {
            $('.alert').fadeOut();
        }, 5000);
    </script>
