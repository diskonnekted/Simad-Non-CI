<?php
require_once 'config/database.php';
require_once 'config/auth.php';

// Cek autentikasi
if (!AuthStatic::isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Get database connection
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

// === STATISTIK PEMBELIAN ===

// Total Pembelian
$total_stats = $db->select("
    SELECT 
        COUNT(*) as total_pembelian,
        COALESCE(SUM(total_amount), 0) as total_nilai,
        COALESCE(SUM(CASE WHEN status_pembelian = 'diterima_lengkap' THEN total_amount ELSE 0 END), 0) as nilai_selesai,
        COALESCE(SUM(CASE WHEN (status_pembayaran IS NULL OR status_pembayaran = '' OR status_pembayaran != 'lunas') THEN total_amount ELSE 0 END), 0) as total_hutang
    FROM pembelian
");
$stats = $total_stats[0];

// Pembelian Bulan Ini
$month_stats = $db->select("
    SELECT 
        COUNT(*) as total_pembelian,
        COALESCE(SUM(total_amount), 0) as total_nilai
    FROM pembelian
    WHERE MONTH(tanggal_pembelian) = MONTH(CURDATE()) 
    AND YEAR(tanggal_pembelian) = YEAR(CURDATE())
");
$stats['month'] = $month_stats[0];

// Top 5 Vendor
$top_vendors = $db->select("
    SELECT 
        v.nama_vendor,
        COUNT(p.id) as total_pembelian,
        COALESCE(SUM(p.total_amount), 0) as total_nilai
    FROM vendor v
    LEFT JOIN pembelian p ON v.id = p.vendor_id
    WHERE p.id IS NOT NULL
    GROUP BY v.id, v.nama_vendor
    ORDER BY total_nilai DESC
    LIMIT 5
");

// Pembelian 6 bulan terakhir untuk chart
$monthly_stats = $db->select("
    SELECT 
        DATE_FORMAT(tanggal_pembelian, '%Y-%m') as bulan,
        COUNT(*) as total_pembelian,
        COALESCE(SUM(total_amount), 0) as total_nilai
    FROM pembelian
    WHERE tanggal_pembelian >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(tanggal_pembelian, '%Y-%m')
    ORDER BY bulan ASC
");

// Status Pembelian untuk pie chart
$status_stats = $db->select("
    SELECT 
        status_pembelian,
        COUNT(*) as jumlah,
        COALESCE(SUM(total_amount), 0) as total_nilai
    FROM pembelian
    GROUP BY status_pembelian
    ORDER BY jumlah DESC
");

// Pembelian terbaru
$recent_purchases = $db->select("
    SELECT 
        p.nomor_po,
        p.tanggal_pembelian,
        v.nama_vendor,
        p.total_amount as total_nilai,
        p.status_pembelian,
        p.status_pembayaran
    FROM pembelian p
    LEFT JOIN vendor v ON p.vendor_id = v.id
    ORDER BY p.tanggal_pembelian DESC
    LIMIT 10
");

$page_title = 'Statistik Pembelian';
require_once 'layouts/header.php';
?>

<!-- Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- Statistik Pembelian Content -->
<div class="ml-0 px-2 sm:px-4 lg:px-6 py-8 max-w-screen-xl">
    <!-- Page Header -->
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Statistik Pembelian</h1>
                <p class="text-gray-600 mt-2">Analisis dan laporan pembelian secara mendetail</p>
            </div>
            <div class="text-right">
                <p class="text-sm text-gray-500"><?= formatTanggalIndonesia(date('Y-m-d')) ?></p>
                <p class="text-lg font-semibold text-gray-900"><?= date('H:i') ?> WIB</p>
            </div>
        </div>
    </div>

    <!-- Statistik Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <!-- Total Pembelian -->
        <div class="bg-gradient-to-r from-blue-500 to-blue-600 rounded-2xl shadow-xl p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-blue-100 text-sm font-medium">Total Pembelian</p>
                    <p class="text-3xl font-bold text-white mt-2"><?= number_format($stats['total_pembelian']) ?></p>
                    <p class="text-blue-100 text-xs mt-1">Purchase Orders</p>
                </div>
                <div class="bg-blue-400 bg-opacity-30 rounded-full w-16 h-16 flex items-center justify-center">
                    <i class="fas fa-shopping-cart text-2xl text-white"></i>
                </div>
            </div>
        </div>

        <!-- Total Nilai -->
        <div class="bg-gradient-to-r from-green-500 to-green-600 rounded-2xl shadow-xl p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-green-100 text-sm font-medium">Total Nilai</p>
                    <p class="text-2xl font-bold text-white mt-2"><?= formatRupiah($stats['total_nilai']) ?></p>
                    <p class="text-green-100 text-xs mt-1">Semua Pembelian</p>
                </div>
                <div class="bg-green-400 bg-opacity-30 rounded-full w-16 h-16 flex items-center justify-center">
                    <i class="fas fa-money-bill-wave text-2xl text-white"></i>
                </div>
            </div>
        </div>

        <!-- Pembelian Selesai -->
        <div class="bg-gradient-to-r from-purple-500 to-purple-600 rounded-2xl shadow-xl p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-purple-100 text-sm font-medium">Nilai Selesai</p>
                    <p class="text-2xl font-bold text-white mt-2"><?= formatRupiah($stats['nilai_selesai']) ?></p>
                    <p class="text-purple-100 text-xs mt-1">Pembelian Completed</p>
                </div>
                <div class="bg-purple-400 bg-opacity-30 rounded-full w-16 h-16 flex items-center justify-center">
                    <i class="fas fa-check-circle text-2xl text-white"></i>
                </div>
            </div>
        </div>

        <!-- Total Hutang -->
        <div class="bg-gradient-to-r from-red-500 to-red-600 rounded-2xl shadow-xl p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-red-100 text-sm font-medium">Total Hutang</p>
                    <p class="text-2xl font-bold text-white mt-2"><?= formatRupiah($stats['total_hutang']) ?></p>
                    <p class="text-red-100 text-xs mt-1">Outstanding</p>
                </div>
                <div class="bg-red-400 bg-opacity-30 rounded-full w-16 h-16 flex items-center justify-center">
                    <i class="fas fa-exclamation-triangle text-2xl text-white"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Section -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
        <!-- Monthly Trend Chart -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Trend Pembelian 6 Bulan Terakhir</h3>
            <div style="position: relative; height: 300px; width: 100%;">
                <canvas id="monthlyChart"></canvas>
            </div>
        </div>

        <!-- Status Distribution Chart -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Distribusi Status Pembelian</h3>
            <div style="position: relative; height: 300px; width: 100%;">
                <canvas id="statusChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Top Vendors & Recent Purchases -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <!-- Top Vendors -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Top 5 Vendor</h3>
            <div class="space-y-4">
                <?php foreach ($top_vendors as $index => $vendor): ?>
                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                    <div class="flex items-center">
                        <div class="w-8 h-8 bg-blue-500 text-white rounded-full flex items-center justify-center text-sm font-bold mr-3">
                            <?= $index + 1 ?>
                        </div>
                        <div>
                            <p class="font-medium text-gray-900"><?= htmlspecialchars($vendor['nama_vendor']) ?></p>
                            <p class="text-sm text-gray-500"><?= $vendor['total_pembelian'] ?> pembelian</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="font-semibold text-gray-900"><?= formatRupiah($vendor['total_nilai']) ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Recent Purchases -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Pembelian Terbaru</h3>
            <div class="space-y-3">
                <?php foreach ($recent_purchases as $purchase): ?>
                <div class="flex items-center justify-between p-3 border-b border-gray-100 last:border-b-0">
                    <div>
                        <p class="font-medium text-gray-900"><?= htmlspecialchars($purchase['nomor_po']) ?></p>
                        <p class="text-sm text-gray-500"><?= htmlspecialchars($purchase['nama_vendor']) ?></p>
                        <p class="text-xs text-gray-400"><?= formatTanggalIndonesia($purchase['tanggal_pembelian']) ?></p>
                    </div>
                    <div class="text-right">
                        <p class="font-semibold text-gray-900"><?= formatRupiah($purchase['total_nilai']) ?></p>
                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium
                            <?php 
                            switch($purchase['status_pembelian']) {
                                case 'pending': echo 'bg-yellow-100 text-yellow-800'; break;
                                case 'diproses': echo 'bg-blue-100 text-blue-800'; break;
                                case 'selesai': echo 'bg-green-100 text-green-800'; break;
                                default: echo 'bg-gray-100 text-gray-800';
                            }
                            ?>">
                            <?= ucfirst($purchase['status_pembelian']) ?>
                        </span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<script>
// Monthly Trend Chart
const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
const monthlyData = <?= json_encode($monthly_stats) ?>;

// Generate last 6 months labels if no data
function generateMonthLabels() {
    const labels = [];
    const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    const now = new Date();
    
    for (let i = 5; i >= 0; i--) {
        const date = new Date(now.getFullYear(), now.getMonth() - i, 1);
        labels.push(monthNames[date.getMonth()] + ' ' + date.getFullYear());
    }
    return labels;
}

// Prepare chart data
let chartLabels, pembelianData, nilaiData;

if (monthlyData && monthlyData.length > 0) {
    chartLabels = monthlyData.map(item => {
        const [year, month] = item.bulan.split('-');
        const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
        return monthNames[parseInt(month) - 1] + ' ' + year;
    });
    pembelianData = monthlyData.map(item => item.total_pembelian);
    nilaiData = monthlyData.map(item => Math.round(item.total_nilai / 1000000));
} else {
    // No data, show empty chart with proper labels
    chartLabels = generateMonthLabels();
    pembelianData = new Array(6).fill(0);
    nilaiData = new Array(6).fill(0);
}

new Chart(monthlyCtx, {
    type: 'line',
    data: {
        labels: chartLabels,
        datasets: [{
            label: 'Jumlah Pembelian',
            data: pembelianData,
            borderColor: 'rgb(59, 130, 246)',
            backgroundColor: 'rgba(59, 130, 246, 0.1)',
            tension: 0.4,
            fill: false,
            pointRadius: 4,
            pointHoverRadius: 6
        }, {
            label: 'Total Nilai (Juta)',
            data: nilaiData,
            borderColor: 'rgb(16, 185, 129)',
            backgroundColor: 'rgba(16, 185, 129, 0.1)',
            tension: 0.4,
            fill: false,
            pointRadius: 4,
            pointHoverRadius: 6,
            yAxisID: 'y1'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        animation: {
            duration: 0
        },
        hover: {
            animationDuration: 0
        },
        responsiveAnimationDuration: 0,
        interaction: {
            intersect: false,
            mode: 'index'
        },
        scales: {
            x: {
                display: true,
                title: {
                    display: true,
                    text: 'Bulan'
                }
            },
            y: {
                type: 'linear',
                display: true,
                position: 'left',
                beginAtZero: true,
                title: {
                    display: true,
                    text: 'Jumlah Pembelian'
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
                            return context.dataset.label + ': ' + context.parsed.y + ' pembelian';
                        } else {
                            return context.dataset.label + ': Rp ' + (context.parsed.y * 1000000).toLocaleString('id-ID');
                        }
                    }
                }
            }
        }
    }
});

// Status Distribution Chart
const statusCtx = document.getElementById('statusChart').getContext('2d');
const statusData = <?= json_encode($status_stats) ?>;

new Chart(statusCtx, {
    type: 'doughnut',
    data: {
        labels: statusData.map(item => {
            switch(item.status_pembelian) {
                case 'pending': return 'Pending';
                case 'diproses': return 'Diproses';
                case 'selesai': return 'Selesai';
                default: return item.status_pembelian;
            }
        }),
        datasets: [{
            data: statusData.map(item => item.jumlah),
            backgroundColor: [
                'rgb(251, 191, 36)',
                'rgb(59, 130, 246)',
                'rgb(16, 185, 129)',
                'rgb(239, 68, 68)'
            ]
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        animation: {
            duration: 0
        },
        hover: {
            animationDuration: 0
        },
        responsiveAnimationDuration: 0,
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
});
</script>

<?php require_once 'layouts/footer.php'; ?>