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

// Inisialisasi variabel default
$transaksi_list = [];
$desa = null;

// Ambil data desa
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $stmt = $pdo->prepare("SELECT * FROM desa WHERE id = ?");
    $stmt->execute([$_SESSION['desa_id']]);
    $desa = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Ambil transaksi dengan jadwal pengiriman
    $transaksi_stmt = $pdo->prepare("
        SELECT 
            t.*,
            p.nama as produk_nama,
            l.nama as layanan_nama,
            CASE 
                WHEN t.produk_id IS NOT NULL THEN p.nama
                WHEN t.layanan_id IS NOT NULL THEN l.nama
                ELSE 'Item tidak diketahui'
            END as item_nama
        FROM transaksi t
        LEFT JOIN produk p ON t.produk_id = p.id
        LEFT JOIN layanan l ON t.layanan_id = l.id
        WHERE t.desa_id = ? 
        AND t.status_pembayaran IN ('lunas', 'pending', 'hutang')
        ORDER BY 
            CASE 
                WHEN t.tanggal_pengiriman IS NULL THEN 0
                ELSE 1
            END,
            t.tanggal_pengiriman ASC,
            t.created_at DESC
    ");
    $transaksi_stmt->execute([$_SESSION['desa_id']]);
    $transaksi_list = $transaksi_stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $error = 'Terjadi kesalahan sistem: ' . $e->getMessage();
}

$success = '';
$error = '';

// Proses konfirmasi jadwal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $transaksi_id = (int)($_POST['transaksi_id'] ?? 0);
    
    if ($action === 'confirm_schedule' && $transaksi_id > 0) {
        try {
            // Cek apakah transaksi milik desa ini
            $check_stmt = $pdo->prepare("SELECT id FROM transaksi WHERE id = ? AND desa_id = ?");
            $check_stmt->execute([$transaksi_id, $_SESSION['desa_id']]);
            
            if ($check_stmt->fetch()) {
                // Update konfirmasi jadwal
                $update_stmt = $pdo->prepare("
                    UPDATE transaksi 
                    SET konfirmasi_jadwal = 'dikonfirmasi', updated_at = NOW() 
                    WHERE id = ? AND desa_id = ?
                ");
                $update_stmt->execute([$transaksi_id, $_SESSION['desa_id']]);
                
                $success = 'Jadwal pengiriman berhasil dikonfirmasi!';
            } else {
                $error = 'Transaksi tidak ditemukan atau tidak memiliki akses.';
            }
        } catch (Exception $e) {
            $error = 'Terjadi kesalahan saat mengkonfirmasi jadwal: ' . $e->getMessage();
        }
    }
}

// Fungsi untuk format tanggal Indonesia
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

// Fungsi untuk mendapatkan status badge
function getStatusBadge($status) {
    switch ($status) {
        case 'pending':
            return '<span class="px-2 py-1 text-xs font-medium bg-yellow-100 text-yellow-800 rounded-full">Menunggu</span>';
        case 'diproses':
            return '<span class="px-2 py-1 text-xs font-medium bg-blue-100 text-blue-800 rounded-full">Diproses</span>';
        case 'dikirim':
            return '<span class="px-2 py-1 text-xs font-medium bg-purple-100 text-purple-800 rounded-full">Dikirim</span>';
        case 'selesai':
            return '<span class="px-2 py-1 text-xs font-medium bg-green-100 text-green-800 rounded-full">Selesai</span>';
        case 'dibatalkan':
            return '<span class="px-2 py-1 text-xs font-medium bg-red-100 text-red-800 rounded-full">Dibatalkan</span>';
        default:
            return '<span class="px-2 py-1 text-xs font-medium bg-gray-100 text-gray-800 rounded-full">' . ucfirst($status) . '</span>';
    }
}

// Fungsi untuk mendapatkan konfirmasi badge
function getKonfirmasiBadge($konfirmasi) {
    switch ($konfirmasi) {
        case 'dikonfirmasi':
            return '<span class="px-2 py-1 text-xs font-medium bg-green-100 text-green-800 rounded-full"><i class="fas fa-check mr-1"></i>Dikonfirmasi</span>';
        case 'ditolak':
            return '<span class="px-2 py-1 text-xs font-medium bg-red-100 text-red-800 rounded-full"><i class="fas fa-times mr-1"></i>Ditolak</span>';
        default:
            return '<span class="px-2 py-1 text-xs font-medium bg-yellow-100 text-yellow-800 rounded-full"><i class="fas fa-clock mr-1"></i>Menunggu</span>';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Konfirmasi Pengiriman - Portal Klien Desa</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .bg-gradient {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .calendar-event {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
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
                        <h1 class="text-xl font-bold text-white">Konfirmasi Pengiriman</h1>
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
                <i class="fas fa-truck mr-2 text-blue-600"></i>
                Konfirmasi Jadwal Pengiriman
            </h2>
            <p class="text-gray-600">
                Lihat dan konfirmasi jadwal pengiriman produk atau layanan yang telah Anda pesan.
            </p>
        </div>

        <?php if ($error): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6">
                <i class="fas fa-exclamation-circle mr-2"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-6">
                <i class="fas fa-check-circle mr-2"></i>
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
            <!-- Main Content -->
            <div class="lg:col-span-3">
                <!-- Jadwal Pengiriman Terkonfirmasi -->
                <div class="bg-white rounded-lg shadow-md mb-8">
                    <div class="p-6 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-800">
                            <i class="fas fa-calendar-check mr-2 text-green-600"></i>
                            Jadwal Pengiriman Terkonfirmasi
                        </h3>
                    </div>
                    
                    <div class="p-6">
                        <?php 
                        $jadwal_terkonfirmasi = array_filter($transaksi_list, function($t) {
                            return !empty($t['tanggal_pengiriman']) && $t['konfirmasi_jadwal'] === 'dikonfirmasi';
                        });
                        ?>
                        
                        <?php if (empty($jadwal_terkonfirmasi)): ?>
                            <div class="text-center py-8">
                                <i class="fas fa-calendar-times text-gray-400 text-4xl mb-4"></i>
                                <p class="text-gray-600">Belum ada jadwal pengiriman yang terkonfirmasi</p>
                            </div>
                        <?php else: ?>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <?php foreach ($jadwal_terkonfirmasi as $transaksi): ?>
                                    <div class="calendar-event rounded-lg p-4 text-white">
                                        <div class="flex items-center justify-between mb-2">
                                            <span class="text-sm font-medium opacity-90">ID: #<?= $transaksi['id'] ?></span>
                                            <i class="fas fa-check-circle"></i>
                                        </div>
                                        <h4 class="font-semibold mb-1"><?= htmlspecialchars($transaksi['item_nama'] ?? 'Item tidak diketahui') ?></h4>
                                        <p class="text-sm opacity-90 mb-2">Jumlah: <?= $transaksi['jumlah'] ?></p>
                                        <div class="flex items-center text-sm">
                                            <i class="fas fa-calendar mr-2"></i>
                                            <?= formatTanggalIndonesia($transaksi['tanggal_pengiriman']) ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Daftar Transaksi -->
                <div class="bg-white rounded-lg shadow-md">
                    <div class="p-6 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-800">
                            <i class="fas fa-list mr-2 text-blue-600"></i>
                            Daftar Transaksi & Jadwal Pengiriman
                        </h3>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <?php if (empty($transaksi_list)): ?>
                            <div class="text-center py-12">
                                <i class="fas fa-inbox text-gray-400 text-4xl mb-4"></i>
                                <p class="text-gray-600 mb-4">Belum ada transaksi</p>
                                <a href="order.php" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition duration-200">
                                    <i class="fas fa-plus mr-2"></i>Buat Pesanan
                                </a>
                            </div>
                        <?php else: ?>
                            <table class="w-full">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Item</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Jumlah</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Jadwal Pengiriman</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Konfirmasi</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($transaksi_list as $transaksi): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                #<?= $transaksi['id'] ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($transaksi['item_nama'] ?? 'Item tidak diketahui') ?></div>
                                                <div class="text-sm text-gray-500">
                                                    <?= $transaksi['produk_id'] ? 'Produk' : 'Layanan' ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <?= $transaksi['jumlah'] ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                Rp <?= number_format($transaksi['total_harga'], 0, ',', '.') ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <?= getStatusBadge($transaksi['status_pembayaran']) ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <?php if ($transaksi['tanggal_pengiriman']): ?>
                                                    <div class="flex items-center">
                                                        <i class="fas fa-calendar text-blue-600 mr-2"></i>
                                                        <?= formatTanggalIndonesia($transaksi['tanggal_pengiriman']) ?>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="text-gray-400">Belum dijadwalkan</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <?php if ($transaksi['tanggal_pengiriman']): ?>
                                                    <?= getKonfirmasiBadge($transaksi['konfirmasi_jadwal']) ?>
                                                <?php else: ?>
                                                    <span class="text-gray-400">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <?php if ($transaksi['tanggal_pengiriman'] && $transaksi['konfirmasi_jadwal'] !== 'dikonfirmasi'): ?>
                                                    <form method="POST" class="inline">
                                                        <input type="hidden" name="action" value="confirm_schedule">
                                                        <input type="hidden" name="transaksi_id" value="<?= $transaksi['id'] ?>">
                                                        <button type="submit" 
                                                                class="bg-green-600 text-white px-3 py-1 rounded text-xs hover:bg-green-700 transition duration-200"
                                                                onclick="return confirm('Konfirmasi jadwal pengiriman ini?')">
                                                            <i class="fas fa-check mr-1"></i>Konfirmasi
                                                        </button>
                                                    </form>
                                                <?php elseif ($transaksi['konfirmasi_jadwal'] === 'dikonfirmasi'): ?>
                                                    <span class="text-green-600 text-xs">
                                                        <i class="fas fa-check-circle mr-1"></i>Terkonfirmasi
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-gray-400 text-xs">Menunggu jadwal</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="space-y-6">
                <!-- Info Panel -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">
                        <i class="fas fa-info-circle mr-2 text-blue-600"></i>
                        Informasi
                    </h3>
                    
                    <div class="space-y-4 text-sm">
                        <div class="flex items-start space-x-3">
                            <i class="fas fa-clock text-yellow-600 mt-1"></i>
                            <div>
                                <div class="font-medium text-gray-800">Menunggu Jadwal</div>
                                <div class="text-gray-600">Admin akan menentukan jadwal pengiriman</div>
                            </div>
                        </div>
                        
                        <div class="flex items-start space-x-3">
                            <i class="fas fa-calendar text-blue-600 mt-1"></i>
                            <div>
                                <div class="font-medium text-gray-800">Jadwal Ditetapkan</div>
                                <div class="text-gray-600">Anda akan menerima notifikasi jadwal</div>
                            </div>
                        </div>
                        
                        <div class="flex items-start space-x-3">
                            <i class="fas fa-check text-green-600 mt-1"></i>
                            <div>
                                <div class="font-medium text-gray-800">Konfirmasi</div>
                                <div class="text-gray-600">Konfirmasi jadwal untuk menambah ke kalender</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Statistics -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">
                        <i class="fas fa-chart-bar mr-2 text-purple-600"></i>
                        Statistik Pengiriman
                    </h3>
                    
                    <?php
                    $total_transaksi = count($transaksi_list);
                    $jadwal_tersedia = count(array_filter($transaksi_list, function($t) { return !empty($t['tanggal_pengiriman']); }));
                    $terkonfirmasi = count(array_filter($transaksi_list, function($t) { return $t['konfirmasi_jadwal'] === 'dikonfirmasi'; }));
                    $menunggu_jadwal = $total_transaksi - $jadwal_tersedia;
                    ?>
                    
                    <div class="space-y-3 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Total Transaksi:</span>
                            <span class="font-medium text-gray-800"><?= $total_transaksi ?></span>
                        </div>
                        
                        <div class="flex justify-between">
                            <span class="text-gray-600">Jadwal Tersedia:</span>
                            <span class="font-medium text-blue-600"><?= $jadwal_tersedia ?></span>
                        </div>
                        
                        <div class="flex justify-between">
                            <span class="text-gray-600">Terkonfirmasi:</span>
                            <span class="font-medium text-green-600"><?= $terkonfirmasi ?></span>
                        </div>
                        
                        <div class="flex justify-between">
                            <span class="text-gray-600">Menunggu Jadwal:</span>
                            <span class="font-medium text-yellow-600"><?= $menunggu_jadwal ?></span>
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
                        
                        <a href="financial.php" class="block w-full bg-green-600 text-white text-center py-2 rounded-lg hover:bg-green-700 transition duration-200">
                            <i class="fas fa-chart-line mr-2"></i>Status Keuangan
                        </a>
                        
                        <a href="calendar.php" class="block w-full bg-purple-600 text-white text-center py-2 rounded-lg hover:bg-purple-700 transition duration-200">
                            <i class="fas fa-calendar mr-2"></i>Kalender Kunjungan
                        </a>
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
        // Auto refresh setiap 30 detik untuk update jadwal terbaru
        setInterval(function() {
            // Hanya refresh jika tidak ada form yang sedang disubmit
            if (!document.querySelector('form:target')) {
                location.reload();
            }
        }, 30000);
        
        // Konfirmasi sebelum submit
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                const action = this.querySelector('input[name="action"]').value;
                
                if (action === 'confirm_schedule') {
                    if (!confirm('Apakah Anda yakin ingin mengkonfirmasi jadwal pengiriman ini? Jadwal akan otomatis ditambahkan ke kalender Anda.')) {
                        e.preventDefault();
                        return false;
                    }
                }
            });
        });
        
        // Highlight jadwal hari ini
        const today = new Date().toISOString().split('T')[0];
        document.querySelectorAll('[data-tanggal]').forEach(element => {
            if (element.dataset.tanggal === today) {
                element.classList.add('ring-2', 'ring-yellow-400');
            }
        });
    </script>
</body>
</html>