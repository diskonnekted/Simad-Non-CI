<?php
require_once '../config/database.php';

// Mulai session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Cek login
if (!isset($_SESSION['desa_id'])) {
    header('Location: login.php');
    exit;
}

// Inisialisasi variabel statistik
$total_transaksi = 0;
$total_lunas = 0;
$total_dp = 0;
$total_belum_bayar = 0;
$total_jatuh_tempo = 0;
$transaksi_list = [];
$desa = null;

// Ambil data desa
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $stmt = $pdo->prepare("SELECT * FROM desa WHERE id = ?");
    $stmt->execute([$_SESSION['desa_id']]);
    $desa = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Ambil semua transaksi dengan detail
    $transaksi_stmt = $pdo->prepare("
        SELECT 
            t.*,
            GROUP_CONCAT(DISTINCT td.nama_item SEPARATOR ', ') as item_nama,
            CASE 
                WHEN t.metode_pembayaran = 'tempo' AND t.status_pembayaran = 'belum_bayar' AND t.tanggal_jatuh_tempo < CURDATE() THEN 'jatuh_tempo'
                WHEN t.metode_pembayaran = 'tempo' AND t.status_pembayaran = 'belum_bayar' AND t.tanggal_jatuh_tempo >= CURDATE() THEN 'belum_jatuh_tempo'
                ELSE t.status_pembayaran
            END as status_keuangan
        FROM transaksi t
        LEFT JOIN transaksi_detail td ON t.id = td.transaksi_id
        WHERE t.desa_id = ?
        GROUP BY t.id
        ORDER BY 
            CASE 
                WHEN t.metode_pembayaran = 'tempo' AND t.status_pembayaran = 'belum_bayar' AND t.tanggal_jatuh_tempo < CURDATE() THEN 1
                WHEN t.status_pembayaran = 'dp' THEN 2
                WHEN t.metode_pembayaran = 'tempo' AND t.status_pembayaran = 'belum_bayar' THEN 3
                ELSE 4
            END,
            t.created_at DESC
    ");
    $transaksi_stmt->execute([$_SESSION['desa_id']]);
    $transaksi_list = $transaksi_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Hitung statistik keuangan
    
    foreach ($transaksi_list as $transaksi) {
        $total_transaksi += $transaksi['total_amount'];
        
        switch ($transaksi['status_keuangan']) {
            case 'lunas':
                $total_lunas += $transaksi['total_amount'];
                break;
            case 'dp':
                $total_dp += $transaksi['total_amount'];
                break;
            case 'belum_bayar':
            case 'belum_jatuh_tempo':
                $total_belum_bayar += $transaksi['total_amount'];
                break;
            case 'jatuh_tempo':
                $total_jatuh_tempo += $transaksi['total_amount'];
                break;
        }
    }
    
} catch (Exception $e) {
    $error = 'Terjadi kesalahan sistem: ' . $e->getMessage();
    // Pastikan variabel tetap terdefinisi meskipun ada error
    $total_transaksi = $total_transaksi ?? 0;
    $total_lunas = $total_lunas ?? 0;
    $total_dp = $total_dp ?? 0;
    $total_belum_bayar = $total_belum_bayar ?? 0;
    $total_jatuh_tempo = $total_jatuh_tempo ?? 0;
}

// Fungsi untuk format tanggal Indonesia
function formatTanggalIndonesia($tanggal) {
    if (!$tanggal) return '-';
    
    $bulan = [
        1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr',
        5 => 'Mei', 6 => 'Jun', 7 => 'Jul', 8 => 'Ags',
        9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des'
    ];
    
    $timestamp = strtotime($tanggal);
    $hari = date('d', $timestamp);
    $bulan_num = (int)date('m', $timestamp);
    $tahun = date('Y', $timestamp);
    
    return $hari . ' ' . $bulan[$bulan_num] . ' ' . $tahun;
}

// Fungsi untuk mendapatkan status badge
function getStatusBadge($status) {
    switch ($status) {
        case 'lunas':
            return '<span class="px-3 py-1 text-xs font-medium bg-green-100 text-green-800 rounded-full"><i class="fas fa-check-circle mr-1"></i>Lunas</span>';
        case 'dp':
            return '<span class="px-3 py-1 text-xs font-medium bg-yellow-100 text-yellow-800 rounded-full"><i class="fas fa-clock mr-1"></i>DP</span>';
        case 'belum_bayar':
        case 'belum_jatuh_tempo':
            return '<span class="px-3 py-1 text-xs font-medium bg-blue-100 text-blue-800 rounded-full"><i class="fas fa-calendar mr-1"></i>Belum Bayar</span>';
        case 'jatuh_tempo':
            return '<span class="px-3 py-1 text-xs font-medium bg-red-100 text-red-800 rounded-full"><i class="fas fa-exclamation-triangle mr-1"></i>Jatuh Tempo</span>';
        case 'dibatalkan':
            return '<span class="px-3 py-1 text-xs font-medium bg-gray-100 text-gray-800 rounded-full"><i class="fas fa-times mr-1"></i>Dibatalkan</span>';
        default:
            return '<span class="px-3 py-1 text-xs font-medium bg-gray-100 text-gray-800 rounded-full">' . ucfirst($status) . '</span>';
    }
}

// Fungsi untuk mendapatkan prioritas warna
function getPriorityClass($status) {
    switch ($status) {
        case 'jatuh_tempo':
            return 'border-l-4 border-red-500 bg-red-50';
        case 'dp':
            return 'border-l-4 border-yellow-500 bg-yellow-50';
        case 'belum_bayar':
        case 'belum_jatuh_tempo':
            return 'border-l-4 border-blue-500 bg-blue-50';
        case 'lunas':
            return 'border-l-4 border-green-500 bg-green-50';
        default:
            return 'border-l-4 border-gray-500 bg-gray-50';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Status Keuangan - Portal Klien Desa</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .bg-gradient {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .stat-card-green {
            background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
        }
        .stat-card-yellow {
            background: linear-gradient(135deg, #ed8936 0%, #dd6b20 100%);
        }
        .stat-card-red {
            background: linear-gradient(135deg, #f56565 0%, #e53e3e 100%);
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <!-- Header -->
    <header class="bg-gradient shadow-lg">
        <div class="container mx-auto px-4 py-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <a href="dashboard.php" class="bg-white bg-opacity-20 rounded-full w-10 h-10 flex items-center justify-center text-white hover:bg-opacity-30 transition duration-200">
                        <i class="fas fa-arrow-left"></i>
                    </a>
                    <div>
                        <h1 class="text-xl font-bold text-white">Status Keuangan</h1>
                        <p class="text-blue-100 text-sm"><?= htmlspecialchars($desa['nama_desa'] ?? '') ?>, <?= htmlspecialchars($desa['kecamatan'] ?? '') ?></p>
                    </div>
                </div>
                
                <a href="dashboard.php" class="bg-white bg-opacity-20 text-white px-4 py-2 rounded-lg hover:bg-opacity-30 transition duration-200">
                    <i class="fas fa-home mr-1"></i>Dashboard
                </a>
            </div>
        </div>
    </header>

    <div class="max-w-6xl mx-auto px-6 py-8">
        <!-- Page Info -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <h2 class="text-2xl font-bold text-gray-800 mb-2">
                <i class="fas fa-chart-line mr-2 text-green-600"></i>
                Status Keuangan & Pembayaran
            </h2>
            <p class="text-gray-600">
                Pantau status pembayaran, jatuh tempo, dan riwayat transaksi keuangan Anda.
            </p>
        </div>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6 mb-8">
            <!-- Total Transaksi -->
            <div class="stat-card rounded-lg p-6 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-blue-100 text-sm font-medium">Total Transaksi</p>
                        <p class="text-2xl font-bold">Rp <?= number_format($total_transaksi, 0, ',', '.') ?></p>
                    </div>
                    <div class="bg-white bg-opacity-20 rounded-full w-12 h-12 flex items-center justify-center">
                        <i class="fas fa-chart-bar text-xl"></i>
                    </div>
                </div>
            </div>

            <!-- Lunas -->
            <div class="stat-card-green rounded-lg p-6 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-green-100 text-sm font-medium">Lunas</p>
                        <p class="text-2xl font-bold">Rp <?= number_format($total_lunas, 0, ',', '.') ?></p>
                    </div>
                    <div class="bg-white bg-opacity-20 rounded-full w-12 h-12 flex items-center justify-center">
                        <i class="fas fa-check-circle text-xl"></i>
                    </div>
                </div>
            </div>

            <!-- DP -->
            <div class="stat-card-yellow rounded-lg p-6 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-yellow-100 text-sm font-medium">DP</p>
                        <p class="text-2xl font-bold">Rp <?= number_format($total_dp, 0, ',', '.') ?></p>
                    </div>
                    <div class="bg-white bg-opacity-20 rounded-full w-12 h-12 flex items-center justify-center">
                        <i class="fas fa-clock text-xl"></i>
                    </div>
                </div>
            </div>

            <!-- Belum Bayar -->
            <div class="stat-card rounded-lg p-6 text-white" style="background: linear-gradient(135deg, #4299e1 0%, #3182ce 100%);">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-blue-100 text-sm font-medium">Belum Bayar</p>
                        <p class="text-2xl font-bold">Rp <?= number_format($total_belum_bayar, 0, ',', '.') ?></p>
                    </div>
                    <div class="bg-white bg-opacity-20 rounded-full w-12 h-12 flex items-center justify-center">
                        <i class="fas fa-calendar text-xl"></i>
                    </div>
                </div>
            </div>

            <!-- Jatuh Tempo -->
            <div class="stat-card-red rounded-lg p-6 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-red-100 text-sm font-medium">Jatuh Tempo</p>
                        <p class="text-2xl font-bold">Rp <?= number_format($total_jatuh_tempo, 0, ',', '.') ?></p>
                    </div>
                    <div class="bg-white bg-opacity-20 rounded-full w-12 h-12 flex items-center justify-center">
                        <i class="fas fa-exclamation-triangle text-xl"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Main Content -->
            <div class="lg:col-span-2">
                <!-- Filter Tabs -->
                <div class="bg-white rounded-lg shadow-md mb-6">
                    <div class="border-b border-gray-200">
                        <nav class="flex space-x-8 px-6" aria-label="Tabs">
                            <button onclick="filterTransactions('all')" class="filter-tab active border-b-2 border-blue-500 py-4 px-1 text-sm font-medium text-blue-600">
                                Semua
                            </button>
                            <button onclick="filterTransactions('jatuh_tempo')" class="filter-tab border-b-2 border-transparent py-4 px-1 text-sm font-medium text-gray-500 hover:text-gray-700 hover:border-gray-300">
                                Jatuh Tempo
                            </button>
                            <button onclick="filterTransactions('dp')" class="filter-tab border-b-2 border-transparent py-4 px-1 text-sm font-medium text-gray-500 hover:text-gray-700 hover:border-gray-300">
                                DP
                            </button>
                            <button onclick="filterTransactions('belum_bayar')" class="filter-tab border-b-2 border-transparent py-4 px-1 text-sm font-medium text-gray-500 hover:text-gray-700 hover:border-gray-300">
                                Belum Bayar
                            </button>
                            <button onclick="filterTransactions('lunas')" class="filter-tab border-b-2 border-transparent py-4 px-1 text-sm font-medium text-gray-500 hover:text-gray-700 hover:border-gray-300">
                                Lunas
                            </button>
                        </nav>
                    </div>
                </div>

                <!-- Transactions List -->
                <div class="bg-white rounded-lg shadow-md">
                    <div class="p-6 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-800">
                            <i class="fas fa-list mr-2 text-blue-600"></i>
                            Riwayat Transaksi
                        </h3>
                    </div>
                    
                    <div class="p-6">
                        <?php if (empty($transaksi_list)): ?>
                            <div class="text-center py-12">
                                <i class="fas fa-inbox text-gray-400 text-4xl mb-4"></i>
                                <p class="text-gray-600 mb-4">Belum ada transaksi</p>
                                <a href="order.php" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition duration-200">
                                    <i class="fas fa-plus mr-2"></i>Buat Pesanan
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="space-y-4" id="transactionsList">
                                <?php foreach ($transaksi_list as $transaksi): ?>
                                    <div class="transaction-item <?= getPriorityClass($transaksi['status_keuangan']) ?> rounded-lg p-4" data-status="<?= $transaksi['status_keuangan'] ?>">
                                        <div class="flex items-center justify-between mb-3">
                                            <div class="flex items-center space-x-3">
                                                <span class="text-sm font-medium text-gray-600">#<?= $transaksi['id'] ?></span>
                                                <?= getStatusBadge($transaksi['status_keuangan']) ?>
                                            </div>
                                            <span class="text-sm text-gray-500"><?= formatTanggalIndonesia($transaksi['created_at']) ?></span>
                                        </div>
                                        
                                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                            <div>
                                                <h4 class="font-semibold text-gray-800 mb-1"><?= htmlspecialchars($transaksi['item_nama'] ?? 'Item tidak diketahui') ?></h4>
                                                <p class="text-sm text-gray-600">
                                                    <?= ucfirst($transaksi['jenis_transaksi']) ?> • Invoice: <?= $transaksi['nomor_invoice'] ?>
                                                </p>
                                            </div>
                                            
                                            <div>
                                                <p class="text-lg font-bold text-gray-800">Rp <?= number_format($transaksi['total_amount'], 0, ',', '.') ?></p>
                                                <p class="text-sm text-gray-600">
                                                    <i class="fas fa-credit-card mr-1"></i>
                                                    <?= ucfirst($transaksi['metode_pembayaran']) ?>
                                                </p>
                                                <?php if ($transaksi['dp_amount'] > 0): ?>
                                                    <p class="text-sm text-blue-600">
                                                        DP: Rp <?= number_format($transaksi['dp_amount'], 0, ',', '.') ?>
                                                    </p>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <div>
                                                <?php if ($transaksi['tanggal_jatuh_tempo']): ?>
                                                    <p class="text-sm text-gray-600">
                                                        <i class="fas fa-calendar mr-1"></i>
                                                        Jatuh Tempo: <?= formatTanggalIndonesia($transaksi['tanggal_jatuh_tempo']) ?>
                                                    </p>
                                                <?php endif; ?>
                                                
                                                <p class="text-sm text-gray-600">
                                                    <i class="fas fa-info-circle mr-1"></i>
                                                    Status: <?= ucfirst($transaksi['status_transaksi']) ?>
                                                </p>
                                                
                                                <?php if ($transaksi['catatan']): ?>
                                                    <p class="text-sm text-gray-600 mt-1">
                                                        <i class="fas fa-sticky-note mr-1"></i>
                                                        <?= htmlspecialchars($transaksi['catatan']) ?>
                                                    </p>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <?php if ($transaksi['status_keuangan'] === 'jatuh_tempo'): ?>
                                            <div class="mt-3 p-3 bg-red-100 border border-red-200 rounded-lg">
                                                <div class="flex items-center text-red-800">
                                                    <i class="fas fa-exclamation-triangle mr-2"></i>
                                                    <span class="text-sm font-medium">Pembayaran sudah jatuh tempo! Segera lakukan pembayaran.</span>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="space-y-6">
                <!-- Chart -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">
                        <i class="fas fa-chart-pie mr-2 text-purple-600"></i>
                        Distribusi Status
                    </h3>
                    
                    <div class="relative h-64">
                        <canvas id="statusChart"></canvas>
                    </div>
                </div>

                <!-- Payment Info -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">
                        <i class="fas fa-info-circle mr-2 text-blue-600"></i>
                        Informasi Pembayaran
                    </h3>
                    
                    <div class="space-y-4 text-sm">
                        <div class="flex items-start space-x-3">
                            <i class="fas fa-check-circle text-green-600 mt-1"></i>
                            <div>
                                <div class="font-medium text-gray-800">Lunas</div>
                                <div class="text-gray-600">Pembayaran telah selesai</div>
                            </div>
                        </div>
                        
                        <div class="flex items-start space-x-3">
                            <i class="fas fa-clock text-yellow-600 mt-1"></i>
                            <div>
                                <div class="font-medium text-gray-800">Pending</div>
                                <div class="text-gray-600">Menunggu konfirmasi pembayaran</div>
                            </div>
                        </div>
                        
                        <div class="flex items-start space-x-3">
                            <i class="fas fa-calendar text-blue-600 mt-1"></i>
                            <div>
                                <div class="font-medium text-gray-800">Hutang</div>
                                <div class="text-gray-600">Pembayaran dengan sistem kredit</div>
                            </div>
                        </div>
                        
                        <div class="flex items-start space-x-3">
                            <i class="fas fa-exclamation-triangle text-red-600 mt-1"></i>
                            <div>
                                <div class="font-medium text-gray-800">Jatuh Tempo</div>
                                <div class="text-gray-600">Pembayaran sudah melewati batas waktu</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">
                        <i class="fas fa-bolt mr-2 text-yellow-600"></i>
                        Aksi Cepat
                    </h3>
                    
                    <div class="space-y-3">
                        <a href="order.php" class="block w-full bg-blue-600 text-white text-center py-2 rounded-lg hover:bg-blue-700 transition duration-200">
                            <i class="fas fa-plus mr-2"></i>Buat Pesanan Baru
                        </a>
                        
                        <a href="delivery.php" class="block w-full bg-purple-600 text-white text-center py-2 rounded-lg hover:bg-purple-700 transition duration-200">
                            <i class="fas fa-truck mr-2"></i>Cek Pengiriman
                        </a>
                        
                        <a href="consultation.php" class="block w-full bg-green-600 text-white text-center py-2 rounded-lg hover:bg-green-700 transition duration-200">
                            <i class="fas fa-comments mr-2"></i>Konsultasi
                        </a>
                    </div>
                </div>

                <!-- Summary -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">
                        <i class="fas fa-calculator mr-2 text-teal-600"></i>
                        Ringkasan
                    </h3>
                    
                    <div class="space-y-3 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Total Belum Bayar:</span>
                            <span class="font-medium text-blue-600">Rp <?= number_format($total_belum_bayar, 0, ',', '.') ?></span>
                        </div>
                        
                        <div class="flex justify-between">
                            <span class="text-gray-600">Persentase Lunas:</span>
                            <span class="font-medium text-green-600">
                                <?= $total_transaksi > 0 ? round(($total_lunas / $total_transaksi) * 100, 1) : 0 ?>%
                            </span>
                        </div>
                        
                        <div class="flex justify-between">
                            <span class="text-gray-600">Total Transaksi:</span>
                            <span class="font-medium text-gray-800"><?= count($transaksi_list) ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-white border-t border-gray-200 mt-12">
        <div class="container mx-auto px-4 py-6">
            <div class="text-center text-gray-600 text-sm">
                © 2025 Portal Klien Desa - Sistem Manajemen Transaksi
            </div>
        </div>
    </footer>

    <script>
        // Chart data
        const chartData = {
            labels: ['Lunas', 'DP', 'Belum Bayar', 'Jatuh Tempo'],
            datasets: [{
                data: [
                    <?= $total_lunas ?>,
                    <?= $total_dp ?>,
                    <?= $total_belum_bayar ?>,
                    <?= $total_jatuh_tempo ?>
                ],
                backgroundColor: [
                    '#48bb78',
                    '#ed8936',
                    '#4299e1',
                    '#f56565'
                ],
                borderWidth: 0
            }]
        };

        // Create chart
        const ctx = document.getElementById('statusChart').getContext('2d');
        new Chart(ctx, {
            type: 'doughnut',
            data: chartData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            usePointStyle: true
                        }
                    }
                }
            }
        });

        // Filter functionality
        function filterTransactions(status) {
            const items = document.querySelectorAll('.transaction-item');
            const tabs = document.querySelectorAll('.filter-tab');
            
            // Update active tab
            tabs.forEach(tab => {
                tab.classList.remove('active', 'border-blue-500', 'text-blue-600');
                tab.classList.add('border-transparent', 'text-gray-500');
            });
            
            event.target.classList.add('active', 'border-blue-500', 'text-blue-600');
            event.target.classList.remove('border-transparent', 'text-gray-500');
            
            // Filter items
            items.forEach(item => {
                if (status === 'all') {
                    item.style.display = 'block';
                } else {
                    const itemStatus = item.dataset.status;
                    if (status === 'belum_bayar' && (itemStatus === 'belum_bayar' || itemStatus === 'belum_jatuh_tempo')) {
                        item.style.display = 'block';
                    } else if (itemStatus === status) {
                        item.style.display = 'block';
                    } else {
                        item.style.display = 'none';
                    }
                }
            });
        }
        
        // Highlight overdue items
        document.querySelectorAll('[data-status="jatuh_tempo"]').forEach(item => {
            item.classList.add('animate-pulse');
        });
        
        // Auto refresh every 60 seconds
        setInterval(function() {
            location.reload();
        }, 60000);
    </script>
</body>
</html>