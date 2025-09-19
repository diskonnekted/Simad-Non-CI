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

// Ambil data desa yang sedang login
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $stmt = $pdo->prepare("SELECT * FROM desa WHERE id = ?");
    $stmt->execute([$_SESSION['desa_id']]);
    $current_desa = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Ambil data desa yang memiliki jadwal kunjungan
    $all_desa_stmt = $pdo->prepare("
        SELECT DISTINCT
            d.*,
            COUNT(jk.id) as total_jadwal,
            MAX(jk.created_at) as jadwal_terakhir,
            MIN(jk.tanggal_kunjungan) as kunjungan_terdekat
        FROM desa d
        INNER JOIN jadwal_kunjungan jk ON d.id = jk.desa_id
        WHERE d.status = 'aktif'
        AND jk.tanggal_kunjungan IS NOT NULL
        AND jk.tanggal_kunjungan >= CURDATE()
        AND jk.status IN ('dijadwalkan', 'ditunda')
        GROUP BY d.id
        ORDER BY MIN(jk.tanggal_kunjungan) ASC, d.nama_desa ASC
    ");
    $all_desa_stmt->execute();
    $all_desa = $all_desa_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Ambil jadwal kunjungan mendatang
    $schedule_stmt = $pdo->prepare("
        SELECT 
            jk.id,
            jk.tanggal_kunjungan,
            jk.jenis_kunjungan,
            jk.status,
            d.nama_desa,
            d.kecamatan,
            d.kabupaten,
            u.nama_lengkap as sales_name
        FROM jadwal_kunjungan jk
        JOIN desa d ON jk.desa_id = d.id
        JOIN users u ON jk.user_id = u.id
        WHERE jk.tanggal_kunjungan IS NOT NULL
        AND jk.tanggal_kunjungan >= CURDATE()
        AND jk.status IN ('dijadwalkan', 'ditunda')
        ORDER BY jk.tanggal_kunjungan ASC
        LIMIT 10
    ");
    $schedule_stmt->execute();
    $schedules = $schedule_stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $error = 'Terjadi kesalahan sistem: ' . $e->getMessage();
    $all_desa = [];
    $schedules = [];
}

// Filter berdasarkan pencarian
$search = $_GET['search'] ?? '';
if ($search) {
    $filtered_desa = array_filter($all_desa, function($desa) use ($search) {
        return stripos($desa['nama_desa'], $search) !== false || 
               stripos($desa['kecamatan'], $search) !== false ||
               stripos($desa['kabupaten'], $search) !== false;
    });
} else {
    $filtered_desa = $all_desa;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jadwal Kunjungan Clasnet - Portal Klien Desa</title>
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
                    <a href="dashboard.php" class="bg-white bg-opacity-20 rounded-full w-10 h-10 flex items-center justify-center text-white hover:bg-opacity-30 transition duration-200">
                        <i class="fas fa-arrow-left"></i>
                    </a>
                    <div>
                        <h1 class="text-xl font-bold text-white">Jadwal Kunjungan Clasnet</h1>
                        <p class="text-blue-100 text-sm"><?= htmlspecialchars($current_desa['nama_desa'] ?? '') ?>, <?= htmlspecialchars($current_desa['kecamatan'] ?? '') ?></p>
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
                <i class="fas fa-calendar-alt mr-2 text-blue-600"></i>
                Jadwal Kunjungan Clasnet
            </h2>
            <p class="text-gray-600">
                Daftar desa dengan jadwal kunjungan terdekat dan informasi lengkap.
            </p>
        </div>

        <!-- Upcoming Schedules -->
        <?php if (!empty($schedules)): ?>
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">
                <i class="fas fa-clock mr-2 text-green-600"></i>
                Jadwal Kunjungan Mendatang
            </h3>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php foreach (array_slice($schedules, 0, 6) as $schedule): ?>
                <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition duration-200">
                    <div class="flex items-start justify-between mb-2">
                        <div class="flex-1">
                            <h4 class="font-semibold text-gray-800"><?= htmlspecialchars($schedule['nama_desa']) ?></h4>
                            <p class="text-sm text-gray-600"><?= htmlspecialchars($schedule['kecamatan']) ?></p>
                        </div>
                        <span class="bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded-full">
                            <?= date('d/m', strtotime($schedule['tanggal_kunjungan'])) ?>
                        </span>
                    </div>
                    
                    <div class="text-sm text-gray-600 mb-2">
                        <i class="fas fa-tools mr-1"></i>
                        <?= htmlspecialchars($schedule['jenis_kunjungan'] ?: 'Kunjungan') ?>
                    </div>
                    
                    <div class="text-sm text-gray-500 mb-2">
                        <i class="fas fa-calendar mr-1"></i>
                        <?= date('d F Y', strtotime($schedule['tanggal_kunjungan'])) ?>
                    </div>
                    
                    <div class="flex items-center justify-between">
                        <div class="text-xs text-gray-500">
                            <i class="fas fa-user mr-1"></i>
                            Sales: <?= htmlspecialchars($schedule['sales_name'] ?: 'Tidak diketahui') ?>
                        </div>
                        <?php
                        $status_class = '';
                        switch ($schedule['status']) {
                            case 'dijadwalkan':
                                $status_class = 'bg-blue-100 text-blue-800';
                                break;
                            case 'ditunda':
                                $status_class = 'bg-yellow-100 text-yellow-800';
                                break;
                            default:
                                $status_class = 'bg-gray-100 text-gray-800';
                        }
                        ?>
                        <span class="px-2 py-1 text-xs font-medium rounded-full <?= $status_class ?>">
                            <?= ucfirst($schedule['status']) ?>
                        </span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Search and Filter -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between space-y-4 md:space-y-0">
                <h3 class="text-lg font-semibold text-gray-800">
                    <i class="fas fa-calendar-check mr-2 text-purple-600"></i>
                    Desa dengan Jadwal Kunjungan (<?= count($filtered_desa) ?> desa)
                </h3>
                
                <form method="GET" class="flex space-x-2">
                    <input type="text" 
                           name="search" 
                           value="<?= htmlspecialchars($search) ?>"
                           placeholder="Cari nama desa, kecamatan, atau kabupaten..."
                           class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full md:w-80">
                    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition duration-200">
                        <i class="fas fa-search"></i>
                    </button>
                    <?php if ($search): ?>
                    <a href="calendar.php" class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600 transition duration-200">
                        <i class="fas fa-times"></i>
                    </a>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <!-- Desa Table -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="text-left py-4 px-6 text-sm font-medium text-gray-600">Desa</th>
                            <th class="text-left py-4 px-6 text-sm font-medium text-gray-600">Lokasi</th>
                            <th class="text-left py-4 px-6 text-sm font-medium text-gray-600">Kunjungan Terdekat</th>
                            <th class="text-left py-4 px-6 text-sm font-medium text-gray-600">Kontak</th>
                            <th class="text-left py-4 px-6 text-sm font-medium text-gray-600">Status</th>
                            <th class="text-left py-4 px-6 text-sm font-medium text-gray-600">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($filtered_desa as $desa): ?>
                        <tr class="hover:bg-gray-50 <?= $desa['id'] == $_SESSION['desa_id'] ? 'bg-blue-50' : '' ?>">
                            <td class="py-4 px-6">
                                <div class="flex items-center space-x-3">
                                    <?php if ($desa['id'] == $_SESSION['desa_id']): ?>
                                    <div class="bg-blue-100 rounded-full w-8 h-8 flex items-center justify-center">
                                        <i class="fas fa-user text-blue-600 text-sm"></i>
                                    </div>
                                    <?php else: ?>
                                    <div class="bg-gray-100 rounded-full w-8 h-8 flex items-center justify-center">
                                        <i class="fas fa-map-marker-alt text-gray-600 text-sm"></i>
                                    </div>
                                    <?php endif; ?>
                                    <div>
                                        <p class="font-semibold text-gray-800"><?= htmlspecialchars($desa['nama_desa']) ?></p>
                                        <?php if ($desa['id'] == $_SESSION['desa_id']): ?>
                                        <p class="text-xs text-blue-600 font-medium">Desa Anda</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            
                            <td class="py-4 px-6">
                                <div class="text-sm">
                                    <p class="text-gray-800"><?= htmlspecialchars($desa['kecamatan']) ?></p>
                                    <p class="text-gray-600"><?= htmlspecialchars($desa['kabupaten']) ?></p>
                                    <p class="text-gray-500"><?= htmlspecialchars($desa['provinsi']) ?></p>
                                </div>
                            </td>
                            
                            <td class="py-4 px-6">
                                <div class="text-sm">
                                    <?php if ($desa['kunjungan_terdekat']): ?>
                                    <p class="font-medium text-green-700">
                                        <i class="fas fa-calendar-alt mr-1"></i>
                                        <?= date('d/m/Y', strtotime($desa['kunjungan_terdekat'])) ?>
                                    </p>
                                    <p class="text-gray-600 text-xs">
                                        <?php 
                                        $days_diff = (strtotime($desa['kunjungan_terdekat']) - time()) / (60 * 60 * 24);
                                        if ($days_diff < 1) {
                                            echo 'Hari ini';
                                        } elseif ($days_diff < 2) {
                                            echo 'Besok';
                                        } else {
                                            echo ceil($days_diff) . ' hari lagi';
                                        }
                                        ?>
                                    </p>
                                    <?php else: ?>
                                    <p class="text-gray-500">Belum ada jadwal</p>
                                    <?php endif; ?>
                                </div>
                            </td>
                            
                            <td class="py-4 px-6">
                                <div class="text-sm">
                                    <?php if ($desa['nama_kepala_desa']): ?>
                                    <p class="text-gray-800 font-medium"><?= htmlspecialchars($desa['nama_kepala_desa']) ?></p>
                                    <p class="text-gray-600">Kepala Desa</p>
                                    <?php endif; ?>
                                    <?php if ($desa['no_hp_kepala_desa']): ?>
                                    <p class="text-gray-600">
                                        <i class="fas fa-phone mr-1"></i><?= htmlspecialchars($desa['no_hp_kepala_desa']) ?>
                                    </p>
                                    <?php endif; ?>
                                </div>
                            </td>
                            
                            <td class="py-4 px-6">
                                <?php
                                $status_class = '';
                                switch ($desa['status']) {
                                    case 'aktif':
                                        $status_class = 'bg-green-100 text-green-800';
                                        break;
                                    case 'nonaktif':
                                        $status_class = 'bg-red-100 text-red-800';
                                        break;
                                    default:
                                        $status_class = 'bg-gray-100 text-gray-800';
                                }
                                ?>
                                <span class="px-2 py-1 text-xs font-medium rounded-full <?= $status_class ?>">
                                    <?= ucfirst($desa['status']) ?>
                                </span>
                            </td>
                            
                            <td class="py-4 px-6">
                                <div class="flex space-x-2">
                                    <button onclick="showDesaDetail(<?= htmlspecialchars(json_encode($desa)) ?>)" 
                                            class="bg-blue-100 text-blue-600 px-3 py-1 rounded-lg hover:bg-blue-200 transition duration-200 text-sm">
                                        <i class="fas fa-eye mr-1"></i>Detail
                                    </button>
                                    
                                    <?php if ($desa['alamat']): ?>
                                    <button onclick="showLocation('<?= htmlspecialchars($desa['alamat']) ?>')" 
                                            class="bg-green-100 text-green-600 px-3 py-1 rounded-lg hover:bg-green-200 transition duration-200 text-sm">
                                        <i class="fas fa-map-marker-alt mr-1"></i>Lokasi
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if (empty($filtered_desa)): ?>
            <div class="text-center py-12">
                <i class="fas fa-search text-4xl text-gray-400 mb-4"></i>
                <h3 class="text-lg font-medium text-gray-600 mb-2">Tidak ada desa ditemukan</h3>
                <p class="text-gray-500">Coba ubah kata kunci pencarian Anda</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Detail Modal -->
    <div id="detailModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg max-w-2xl w-full max-h-screen overflow-y-auto">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-800">Detail Desa</h3>
                        <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>
                    
                    <div id="modalContent">
                        <!-- Content will be populated by JavaScript -->
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
        function showDesaDetail(desa) {
            const modal = document.getElementById('detailModal');
            const content = document.getElementById('modalContent');
            
            content.innerHTML = `
                <div class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <h4 class="font-semibold text-gray-800 mb-2">Informasi Desa</h4>
                            <div class="space-y-2 text-sm">
                                <p><span class="font-medium">Nama Desa:</span> ${desa.nama_desa}</p>
                                <p><span class="font-medium">Kecamatan:</span> ${desa.kecamatan}</p>
                                <p><span class="font-medium">Kabupaten:</span> ${desa.kabupaten}</p>
                                <p><span class="font-medium">Provinsi:</span> ${desa.provinsi}</p>
                                ${desa.kode_pos ? `<p><span class="font-medium">Kode Pos:</span> ${desa.kode_pos}</p>` : ''}
                            </div>
                        </div>
                        
                        <div>
                            <h4 class="font-semibold text-gray-800 mb-2">Kontak Person</h4>
                            <div class="space-y-2 text-sm">
                                ${desa.nama_kepala_desa ? `<p><span class="font-medium">Kepala Desa:</span> ${desa.nama_kepala_desa}</p>` : ''}
                                ${desa.no_hp_kepala_desa ? `<p><span class="font-medium">No. HP:</span> ${desa.no_hp_kepala_desa}</p>` : ''}
                                ${desa.nama_sekdes ? `<p><span class="font-medium">Sekdes:</span> ${desa.nama_sekdes}</p>` : ''}
                                ${desa.no_hp_sekdes ? `<p><span class="font-medium">HP Sekdes:</span> ${desa.no_hp_sekdes}</p>` : ''}
                                ${desa.email_desa ? `<p><span class="font-medium">Email:</span> ${desa.email_desa}</p>` : ''}
                            </div>
                        </div>
                    </div>
                    
                    ${desa.alamat ? `
                    <div>
                        <h4 class="font-semibold text-gray-800 mb-2">Alamat</h4>
                        <p class="text-sm text-gray-600">${desa.alamat}</p>
                    </div>
                    ` : ''}
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <h4 class="font-semibold text-gray-800 mb-2">Statistik</h4>
                            <div class="space-y-2 text-sm">
                                <p><span class="font-medium">Total Transaksi:</span> ${desa.total_transaksi || 0}</p>
                                <p><span class="font-medium">Status:</span> 
                                    <span class="px-2 py-1 text-xs rounded-full ${
                                        desa.status === 'aktif' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
                                    }">${desa.status}</span>
                                </p>
                                ${desa.transaksi_terakhir ? `<p><span class="font-medium">Transaksi Terakhir:</span> ${new Date(desa.transaksi_terakhir).toLocaleDateString('id-ID')}</p>` : ''}
                            </div>
                        </div>
                        
                        <div>
                            <h4 class="font-semibold text-gray-800 mb-2">Informasi Tambahan</h4>
                            <div class="space-y-2 text-sm">
                                ${desa.kategori ? `<p><span class="font-medium">Kategori:</span> ${desa.kategori}</p>` : ''}
                                ${desa.tingkat_digitalisasi ? `<p><span class="font-medium">Tingkat Digitalisasi:</span> ${desa.tingkat_digitalisasi}</p>` : ''}
                                <p><span class="font-medium">Terdaftar:</span> ${new Date(desa.created_at).toLocaleDateString('id-ID')}</p>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            modal.classList.remove('hidden');
        }
        
        function showLocation(address) {
            const encodedAddress = encodeURIComponent(address);
            window.open(`https://www.google.com/maps/search/?api=1&query=${encodedAddress}`, '_blank');
        }
        
        function closeModal() {
            document.getElementById('detailModal').classList.add('hidden');
        }
        
        // Close modal when clicking outside
        document.getElementById('detailModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
    </script>
</body>
</html>