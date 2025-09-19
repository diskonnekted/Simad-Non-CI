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

// Ambil data desa
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $stmt = $pdo->prepare("SELECT * FROM desa WHERE id = ?");
    $stmt->execute([$_SESSION['desa_id']]);
    $desa = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Ambil statistik transaksi
    $stats_stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_transaksi,
            SUM(CASE WHEN status_pembayaran = 'lunas' THEN 1 ELSE 0 END) as transaksi_lunas,
            SUM(CASE WHEN status_pembayaran = 'dp' THEN 1 ELSE 0 END) as transaksi_dp,
            SUM(CASE WHEN status_pembayaran = 'belum_bayar' THEN 1 ELSE 0 END) as transaksi_belum_bayar,
            SUM(total_amount) as total_nilai
        FROM transaksi 
        WHERE desa_id = ?
    ");
    $stats_stmt->execute([$_SESSION['desa_id']]);
    $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Ambil transaksi terbaru
    $recent_stmt = $pdo->prepare("
        SELECT 
            t.*,
            GROUP_CONCAT(DISTINCT td.nama_item SEPARATOR ', ') as item_nama
        FROM transaksi t
        LEFT JOIN transaksi_detail td ON t.id = td.transaksi_id
        WHERE t.desa_id = ?
        GROUP BY t.id
        ORDER BY t.created_at DESC
        LIMIT 5
    ");
    $recent_stmt->execute([$_SESSION['desa_id']]);
    $recent_transactions = $recent_stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $error = 'Terjadi kesalahan sistem: ' . $e->getMessage();
}

// Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: ../index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Portal Klien Desa</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .bg-gradient {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <!-- Header -->
    <header class="bg-gradient shadow-lg">
        <div class="container mx-auto px-4 py-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <div class="bg-white rounded-full w-12 h-12 flex items-center justify-center">
                        <i class="fas fa-map-marker-alt text-xl text-blue-600"></i>
                    </div>
                    <div>
                        <h1 class="text-xl font-bold text-white">Portal Desa</h1>
                        <p class="text-blue-100 text-sm"><?= htmlspecialchars($desa['nama_desa'] ?? '') ?>, <?= htmlspecialchars($desa['kecamatan'] ?? '') ?></p>
                    </div>
                </div>
                
                <div class="flex items-center space-x-4">
                    <div class="hidden md:block text-right">
                        <p class="text-white font-medium"><?= htmlspecialchars($desa['nama_kepala_desa'] ?? 'Kepala Desa') ?></p>
                        <p class="text-blue-100 text-sm">Kepala Desa</p>
                    </div>
                    <a href="?logout=1" class="bg-white bg-opacity-20 text-white px-4 py-2 rounded-lg hover:bg-opacity-30 transition duration-200">
                        <i class="fas fa-sign-out-alt mr-1"></i>Logout
                    </a>
                </div>
            </div>
        </div>
    </header>

    <div class="max-w-6xl mx-auto px-6 py-8">
        <!-- Welcome Section -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <h2 class="text-2xl font-bold text-gray-800 mb-2">
                <i class="fas fa-home mr-2 text-blue-600"></i>
                Selamat Datang di Portal Desa
            </h2>
            <p class="text-gray-600">
                Kelola transaksi, pemesanan, dan layanan desa Anda dengan mudah melalui portal ini.
            </p>
        </div>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Total Transaksi</p>
                        <p class="text-2xl font-bold text-gray-800"><?= $stats['total_transaksi'] ?? 0 ?></p>
                    </div>
                    <div class="bg-blue-100 rounded-full w-12 h-12 flex items-center justify-center">
                        <i class="fas fa-receipt text-blue-600"></i>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Transaksi Lunas</p>
                        <p class="text-2xl font-bold text-green-600"><?= $stats['transaksi_lunas'] ?? 0 ?></p>
                    </div>
                    <div class="bg-green-100 rounded-full w-12 h-12 flex items-center justify-center">
                        <i class="fas fa-check-circle text-green-600"></i>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Transaksi DP</p>
                        <p class="text-2xl font-bold text-yellow-600"><?= $stats['transaksi_dp'] ?? 0 ?></p>
                    </div>
                    <div class="bg-yellow-100 rounded-full w-12 h-12 flex items-center justify-center">
                        <i class="fas fa-clock text-yellow-600"></i>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Belum Bayar</p>
                        <p class="text-2xl font-bold text-red-600"><?= $stats['transaksi_belum_bayar'] ?? 0 ?></p>
                    </div>
                    <div class="bg-red-100 rounded-full w-12 h-12 flex items-center justify-center">
                        <i class="fas fa-exclamation-triangle text-red-600"></i>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Total Nilai</p>
                        <p class="text-2xl font-bold text-purple-600">Rp <?= number_format($stats['total_nilai'] ?? 0, 0, ',', '.') ?></p>
                    </div>
                    <div class="bg-purple-100 rounded-full w-12 h-12 flex items-center justify-center">
                        <i class="fas fa-money-bill-wave text-purple-600"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Features -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
            <!-- Kalender Kunjungan -->
            <a href="calendar.php" class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition duration-200 group">
                <div class="flex items-center justify-between mb-4">
                    <div class="bg-blue-100 rounded-full w-12 h-12 flex items-center justify-center group-hover:bg-blue-200 transition duration-200">
                        <i class="fas fa-calendar-alt text-blue-600"></i>
                    </div>
                    <i class="fas fa-arrow-right text-gray-400 group-hover:text-blue-600 transition duration-200"></i>
                </div>
                <h3 class="text-lg font-semibold text-gray-800 mb-2">Kalender Kunjungan</h3>
                <p class="text-gray-600 text-sm">Lihat jadwal kunjungan ke desa dan agenda kegiatan</p>
            </a>
            
            <!-- Pemesanan -->
            <a href="order.php" class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition duration-200 group">
                <div class="flex items-center justify-between mb-4">
                    <div class="bg-green-100 rounded-full w-12 h-12 flex items-center justify-center group-hover:bg-green-200 transition duration-200">
                        <i class="fas fa-shopping-cart text-green-600"></i>
                    </div>
                    <i class="fas fa-arrow-right text-gray-400 group-hover:text-green-600 transition duration-200"></i>
                </div>
                <h3 class="text-lg font-semibold text-gray-800 mb-2">Pemesanan</h3>
                <p class="text-gray-600 text-sm">Pesan produk dan layanan dengan berbagai metode pembayaran</p>
            </a>
            
            <!-- Status Keuangan -->
            <a href="financial.php" class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition duration-200 group">
                <div class="flex items-center justify-between mb-4">
                    <div class="bg-purple-100 rounded-full w-12 h-12 flex items-center justify-center group-hover:bg-purple-200 transition duration-200">
                        <i class="fas fa-chart-line text-purple-600"></i>
                    </div>
                    <i class="fas fa-arrow-right text-gray-400 group-hover:text-purple-600 transition duration-200"></i>
                </div>
                <h3 class="text-lg font-semibold text-gray-800 mb-2">Status Keuangan</h3>
                <p class="text-gray-600 text-sm">Pantau status pembayaran, jatuh tempo, dan keuangan</p>
            </a>
            
            <!-- Konfirmasi Pengiriman -->
            <a href="delivery.php" class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition duration-200 group">
                <div class="flex items-center justify-between mb-4">
                    <div class="bg-orange-100 rounded-full w-12 h-12 flex items-center justify-center group-hover:bg-orange-200 transition duration-200">
                        <i class="fas fa-truck text-orange-600"></i>
                    </div>
                    <i class="fas fa-arrow-right text-gray-400 group-hover:text-orange-600 transition duration-200"></i>
                </div>
                <h3 class="text-lg font-semibold text-gray-800 mb-2">Konfirmasi Pengiriman</h3>
                <p class="text-gray-600 text-sm">Konfirmasi jadwal pengiriman barang dan layanan</p>
            </a>
            
            <!-- Promo Produk -->
            <a href="promo.php" class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition duration-200 group">
                <div class="flex items-center justify-between mb-4">
                    <div class="bg-red-100 rounded-full w-12 h-12 flex items-center justify-center group-hover:bg-red-200 transition duration-200">
                        <i class="fas fa-tags text-red-600"></i>
                    </div>
                    <i class="fas fa-arrow-right text-gray-400 group-hover:text-red-600 transition duration-200"></i>
                </div>
                <h3 class="text-lg font-semibold text-gray-800 mb-2">Promo Produk</h3>
                <p class="text-gray-600 text-sm">Lihat promo dan penawaran khusus produk terbaru</p>
            </a>
            
            <!-- Form Konsultasi -->
            <a href="consultation.php" class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition duration-200 group">
                <div class="flex items-center justify-between mb-4">
                    <div class="bg-teal-100 rounded-full w-12 h-12 flex items-center justify-center group-hover:bg-teal-200 transition duration-200">
                        <i class="fas fa-comments text-teal-600"></i>
                    </div>
                    <i class="fas fa-arrow-right text-gray-400 group-hover:text-teal-600 transition duration-200"></i>
                </div>
                <h3 class="text-lg font-semibold text-gray-800 mb-2">Form Konsultasi</h3>
                <p class="text-gray-600 text-sm">Konsultasi dan komunikasi dengan administrator</p>
            </a>
        </div>

        <!-- Recent Transactions -->
        <?php if (!empty($recent_transactions)): ?>
        <div class="bg-white rounded-lg shadow-md p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">
                <i class="fas fa-history mr-2 text-gray-600"></i>
                Transaksi Terbaru
            </h3>
            
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-gray-200">
                            <th class="text-left py-3 px-4 text-sm font-medium text-gray-600">Tanggal</th>
                            <th class="text-left py-3 px-4 text-sm font-medium text-gray-600">Item</th>
                            <th class="text-left py-3 px-4 text-sm font-medium text-gray-600">Total</th>
                            <th class="text-left py-3 px-4 text-sm font-medium text-gray-600">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_transactions as $transaction): ?>
                        <tr class="border-b border-gray-100 hover:bg-gray-50">
                            <td class="py-3 px-4 text-sm text-gray-800">
                                <?= date('d/m/Y', strtotime($transaction['created_at'])) ?>
                            </td>
                            <td class="py-3 px-4 text-sm text-gray-800">
                                <?= htmlspecialchars($transaction['item_nama'] ?: 'Item tidak diketahui') ?>
                            </td>
                            <td class="py-3 px-4 text-sm text-gray-800">
                                Rp <?= number_format($transaction['total_amount'] ?? 0, 0, ',', '.') ?>
                            </td>
                            <td class="py-3 px-4">
                                <?php
                                $status_class = '';
                                switch ($transaction['status_pembayaran']) {
                                    case 'lunas':
                                        $status_class = 'bg-green-100 text-green-800';
                                        break;
                                    case 'dp':
                                        $status_class = 'bg-yellow-100 text-yellow-800';
                                        break;
                                    case 'belum_bayar':
                                        $status_class = 'bg-red-100 text-red-800';
                                        break;
                                    case 'jatuh_tempo':
                                        $status_class = 'bg-orange-100 text-orange-800';
                                        break;
                                    default:
                                        $status_class = 'bg-gray-100 text-gray-800';
                                }
                                ?>
                                <span class="px-2 py-1 text-xs font-medium rounded-full <?= $status_class ?>">
                                    <?php
                                    switch ($transaction['status_pembayaran']) {
                                        case 'lunas':
                                            echo 'Lunas';
                                            break;
                                        case 'dp':
                                            echo 'DP';
                                            break;
                                        case 'belum_bayar':
                                            echo 'Belum Bayar';
                                            break;
                                        case 'jatuh_tempo':
                                            echo 'Jatuh Tempo';
                                            break;
                                        default:
                                            echo ucfirst($transaction['status_pembayaran']);
                                    }
                                    ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="mt-4 text-center">
                <a href="financial.php" class="text-blue-600 hover:text-blue-800 font-medium">
                    Lihat Semua Transaksi <i class="fas fa-arrow-right ml-1"></i>
                </a>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <footer class="bg-white border-t border-gray-200 mt-12">
        <div class="container mx-auto px-4 py-6">
            <div class="text-center text-gray-600 text-sm">
                © 2025 Portal Klien Desa - Sistem Manajemen Transaksi
            </div>
        </div>
    </footer>
</body>
</html>