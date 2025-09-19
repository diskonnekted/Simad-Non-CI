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

// Get jadwal ID
$jadwal_id = intval($_GET['id'] ?? 0);
if ($jadwal_id <= 0) {
    header('Location: jadwal.php?error=' . urlencode('ID jadwal tidak valid.'));
    exit;
}

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        $action = $_POST['action'];
        $keterangan_update = trim($_POST['keterangan_update'] ?? '');
        
        if ($action === 'update_status') {
            $new_status = $_POST['new_status'] ?? '';
            $valid_statuses = ['dijadwalkan', 'selesai', 'ditunda', 'dibatalkan'];
            
            if (!in_array($new_status, $valid_statuses)) {
                throw new Exception('Status tidak valid.');
            }
            
            // Check authorization
            if (!AuthStatic::hasRole(['admin', 'teknisi', 'programmer']) && $user['role'] !== 'sales') {
                throw new Exception('Anda tidak memiliki akses untuk mengubah status.');
            }
            
            // Get current jadwal
            $current_jadwal = $db->select(
                "SELECT * FROM jadwal_kunjungan WHERE id = ?",
                [$jadwal_id]
            );
            
            if (empty($current_jadwal)) {
                throw new Exception('Jadwal tidak ditemukan.');
            }
            
            $current_jadwal = $current_jadwal[0];
            
            // Check if user can update this jadwal
            if ($user['role'] === 'sales' && $current_jadwal['user_id'] != $user['id']) {
                throw new Exception('Anda hanya dapat mengubah jadwal yang Anda buat.');
            }
            
            // Update status
            $update_data = [
                'status' => $new_status
            ];
            
            // Add completion info if status is selesai
            if ($new_status === 'selesai') {
                $update_data['tanggal_selesai'] = date('Y-m-d H:i:s');
            }
            
            // Update catatan_kunjungan if provided
            if ($keterangan_update) {
                $existing_catatan = $current_jadwal['catatan_kunjungan'];
                $new_catatan = $existing_catatan ? $existing_catatan . '\n\n' : '';
                $new_catatan .= '[' . date('d/m/Y H:i') . ' - ' . ($user['nama_lengkap'] ?? $user['username'] ?? 'User') . '] ' . $keterangan_update;
                $update_data['catatan_kunjungan'] = $new_catatan;
            }
            
            $db->update('jadwal_kunjungan', $update_data, ['id' => $jadwal_id]);
            
            header('Location: jadwal-view.php?id=' . $jadwal_id . '&success=' . urlencode('Status jadwal berhasil diperbarui.'));
            exit;
        }
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Get jadwal data
$jadwal_query = "
    SELECT jk.*, d.nama_desa, d.alamat, d.nama_kepala_desa, d.no_hp_kepala_desa, d.nama_sekdes, d.no_hp_sekdes, d.nama_admin_it, d.no_hp_admin_it, d.email_desa,
           u_sales.nama_lengkap as sales_name, u_sales.no_hp as sales_telepon,
           DATEDIFF(jk.tanggal_kunjungan, CURDATE()) as hari_tersisa
    FROM jadwal_kunjungan jk
    JOIN desa d ON jk.desa_id = d.id
    JOIN users u_sales ON jk.user_id = u_sales.id
    WHERE jk.id = ?
";

$jadwal = $db->select($jadwal_query, [$jadwal_id]);

if (empty($jadwal)) {
    header('Location: jadwal.php?error=' . urlencode('Jadwal tidak ditemukan.'));
    exit;
}

$jadwal = $jadwal[0];



// Get related data
// Get produk yang dibawa
$produk_query = "
    SELECT jp.*, p.nama_produk, p.kode_produk, p.harga_satuan
    FROM jadwal_produk jp
    JOIN produk p ON jp.produk_id = p.id
    WHERE jp.jadwal_id = ?
    ORDER BY p.nama_produk
";
$produk_list = $db->select($produk_query, [$jadwal_id]);

// Get peralatan yang dibawa
$peralatan_query = "
    SELECT jpr.*, pr.nama_peralatan, pr.kode_peralatan, pr.kondisi as kondisi_default
    FROM jadwal_peralatan jpr
    JOIN peralatan pr ON jpr.peralatan_id = pr.id
    WHERE jpr.jadwal_id = ?
    ORDER BY pr.nama_peralatan
";
$peralatan_list = $db->select($peralatan_query, [$jadwal_id]);

// Get personal yang disertakan
$personal_query = "
    SELECT jp.*, u.nama_lengkap as nama, u.email, u.role
    FROM jadwal_personal jp
    JOIN users u ON jp.user_id = u.id
    WHERE jp.jadwal_id = ?
    ORDER BY jp.role_dalam_kunjungan, u.nama_lengkap
";
$personal_list = $db->select($personal_query, [$jadwal_id]);

// Get biaya operasional
$biaya_query = "
    SELECT jb.*, bo.nama_biaya, bo.kategori
    FROM jadwal_biaya jb
    JOIN biaya_operasional bo ON jb.biaya_operasional_id = bo.id
    WHERE jb.jadwal_id = ?
    ORDER BY bo.kategori, bo.nama_biaya
";
$biaya_list = $db->select($biaya_query, [$jadwal_id]);

// Check authorization to view
if ($user['role'] === 'sales' && $jadwal['user_id'] != $user['id']) {
    header('Location: jadwal.php?error=' . urlencode('Anda tidak memiliki akses untuk melihat jadwal ini.'));
    exit;
}

// Teknisi dan programmer dapat melihat semua jadwal

// Helper functions
function getStatusBadge($status) {
    $badges = [
        'dijadwalkan' => 'info',
        'selesai' => 'success',
        'ditunda' => 'warning',
        'dibatalkan' => 'danger'
    ];
    return $badges[$status] ?? 'default';
}

function getStatusText($status) {
    $texts = [
        'dijadwalkan' => 'Dijadwalkan',
        'selesai' => 'Selesai',
        'ditunda' => 'Ditunda',
        'dibatalkan' => 'Dibatalkan'
    ];
    return $texts[$status] ?? $status;
}

function getJenisKunjunganBadge($jenis) {
    $badges = [
        'maintenance' => 'primary',
        'instalasi' => 'success',
        'training' => 'info',
        'support' => 'warning',
        'atk_keliling' => 'secondary',
        'lainnya' => 'light'
    ];
    return $badges[$jenis] ?? 'default';
}

function getJenisKunjunganText($jenis) {
    $texts = [
        'maintenance' => 'Maintenance',
        'instalasi' => 'Instalasi',
        'training' => 'Training',
        'support' => 'Support',
        'atk_keliling' => 'ATK Keliling',
        'lainnya' => 'Lainnya'
    ];
    return $texts[$jenis] ?? $jenis;
}

function getUrgencyBadge($urgency) {
    $badges = [
        'rendah' => 'success',
        'normal' => 'primary',
        'tinggi' => 'warning',
        'urgent' => 'danger'
    ];
    return $badges[$urgency] ?? 'default';
}

function getTanggalBadge($hari_tersisa, $status) {
    if ($status !== 'dijadwalkan') return '';
    
    if ($hari_tersisa < 0) {
        return '<span class="label label-danger">Terlambat (' . abs($hari_tersisa) . ' hari)</span>';
    } elseif ($hari_tersisa == 0) {
        return '<span class="label label-warning">Hari Ini</span>';
    } elseif ($hari_tersisa == 1) {
        return '<span class="label label-info">Besok</span>';
    } elseif ($hari_tersisa <= 7) {
        return '<span class="label label-info">' . $hari_tersisa . ' hari lagi</span>';
    } else {
        return '<span class="label label-default">' . $hari_tersisa . ' hari lagi</span>';
    }
}

function canUpdateStatus($user, $jadwal) {
    if (!AuthStatic::hasRole(['admin', 'sales', 'teknisi', 'programmer'])) {
        return false;
    }
    
    if ($user['role'] === 'sales' && $jadwal['user_id'] != $user['id']) {
        return false;
    }
    
    // Teknisi dan programmer dapat mengupdate semua jadwal
    
    return true;
}

function canEdit($user, $jadwal) {
    if (!AuthStatic::hasRole(['admin', 'sales'])) {
        return false;
    }
    
    if ($user['role'] === 'sales' && $jadwal['user_id'] != $user['id']) {
        return false;
    }
    
    return in_array($jadwal['status'], ['dijadwalkan', 'ditunda']);
}
?>
<?php
$page_title = 'Detail Jadwal Kunjungan';
require_once 'layouts/header.php';
?>

    <!-- Main Container -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div class="mb-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900 flex items-center">
                            <i class="fa fa-eye mr-3 text-blue-600"></i>
                            Detail Jadwal Kunjungan
                        </h1>
                        <p class="text-gray-600 mt-1">Informasi lengkap jadwal kunjungan</p>
                    </div>
                </div>
                <nav class="flex mt-4" aria-label="Breadcrumb">
                    <ol class="inline-flex items-center space-x-1 md:space-x-3">
                        <li class="inline-flex items-center">
                            <a href="index.php" class="text-gray-700 hover:text-blue-600">Dashboard</a>
                        </li>
                        <li>
                            <div class="flex items-center">
                                <i class="fa fa-chevron-right text-gray-400 mx-2"></i>
                                <span class="text-gray-500">Jadwal & Kunjungan</span>
                            </div>
                        </li>
                        <li>
                            <div class="flex items-center">
                                <i class="fa fa-chevron-right text-gray-400 mx-2"></i>
                                <a href="jadwal.php" class="text-gray-700 hover:text-blue-600">Jadwal Kunjungan</a>
                            </div>
                        </li>
                        <li>
                            <div class="flex items-center">
                                <i class="fa fa-chevron-right text-gray-400 mx-2"></i>
                                <span class="text-gray-500">Detail</span>
                            </div>
                        </li>
                    </ol>
                </nav>
            </div>

            <div class="space-y-6">
                <!-- Success/Error Messages -->
                <?php if (isset($_GET['success'])): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                    <span class="block sm:inline"><?= htmlspecialchars($_GET['success']) ?></span>
                    <span class="absolute top-0 bottom-0 right-0 px-4 py-3">
                        <button onclick="this.parentElement.parentElement.style.display='none'" class="text-green-500 hover:text-green-700">
                            <i class="fa fa-times"></i>
                        </button>
                    </span>
                </div>
                <?php endif; ?>

                <?php if (isset($error_message)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                    <span class="block sm:inline"><?= $error_message ?></span>
                    <span class="absolute top-0 bottom-0 right-0 px-4 py-3">
                        <button onclick="this.parentElement.parentElement.style.display='none'" class="text-red-500 hover:text-red-700">
                            <i class="fa fa-times"></i>
                        </button>
                    </span>
                </div>
                <?php endif; ?>

                <!-- Jadwal Header -->
                <div class="relative bg-gradient-to-br from-blue-600 to-blue-800 text-white p-8 rounded-lg mb-6 shadow-lg">
                    <?php
                    $urgencyColor = 'gray-500';
                    if (isset($jadwal['urgensi'])) {
                        switch ($jadwal['urgensi']) {
                            case 'tinggi':
                                $urgencyColor = 'red-500';
                                break;
                            case 'sedang':
                                $urgencyColor = 'yellow-500';
                                break;
                            case 'rendah':
                                $urgencyColor = 'green-500';
                                break;
                            default:
                                $urgencyColor = 'gray-500';
                        }
                    }
                    ?>
                    <div class="absolute left-0 top-0 bottom-0 w-1 bg-<?= $urgencyColor ?> rounded-l-lg"></div>
                    <h2 class="text-2xl font-bold mb-3"><?= isset($jadwal['jenis_kunjungan']) ? htmlspecialchars($jadwal['jenis_kunjungan']) : 'Kunjungan' ?></h2>
                    <p class="text-blue-100 mb-4 flex items-center space-x-4">
                        <span class="flex items-center">
                            <i class="fa fa-map-marker mr-2"></i>
                            <?= htmlspecialchars($jadwal['nama_desa']) ?>
                        </span>
                        <span class="flex items-center">
                            <i class="fa fa-calendar mr-2"></i>
                            <?= date('d/m/Y H:i', strtotime($jadwal['tanggal_kunjungan'])) ?>
                        </span>
                    </p>
                    <div class="flex flex-wrap gap-2">
                        <span class="px-3 py-1 text-xs font-semibold rounded-full bg-<?= getJenisKunjunganBadge($jadwal['jenis_kunjungan']) === 'primary' ? 'blue' : (getJenisKunjunganBadge($jadwal['jenis_kunjungan']) === 'success' ? 'green' : (getJenisKunjunganBadge($jadwal['jenis_kunjungan']) === 'warning' ? 'yellow' : 'gray')) ?>-100 text-<?= getJenisKunjunganBadge($jadwal['jenis_kunjungan']) === 'primary' ? 'blue' : (getJenisKunjunganBadge($jadwal['jenis_kunjungan']) === 'success' ? 'green' : (getJenisKunjunganBadge($jadwal['jenis_kunjungan']) === 'warning' ? 'yellow' : 'gray')) ?>-800">
                            <?= getJenisKunjunganText($jadwal['jenis_kunjungan']) ?>
                        </span>
                        <span class="px-3 py-1 text-xs font-semibold rounded-full bg-<?= getStatusBadge($jadwal['status']) === 'success' ? 'green' : (getStatusBadge($jadwal['status']) === 'warning' ? 'yellow' : (getStatusBadge($jadwal['status']) === 'danger' ? 'red' : 'gray')) ?>-100 text-<?= getStatusBadge($jadwal['status']) === 'success' ? 'green' : (getStatusBadge($jadwal['status']) === 'warning' ? 'yellow' : (getStatusBadge($jadwal['status']) === 'danger' ? 'red' : 'gray')) ?>-800">
                            <?= getStatusText($jadwal['status']) ?>
                        </span>
                        <?php
                        $urgencyColors = [
                            'rendah' => 'bg-green-100 text-green-800',
                            'sedang' => 'bg-yellow-100 text-yellow-800',
                            'tinggi' => 'bg-red-100 text-red-800'
                        ];
                        $urgencyClass = isset($jadwal['urgensi']) ? ($urgencyColors[$jadwal['urgensi']] ?? 'bg-gray-100 text-gray-800') : 'bg-gray-100 text-gray-800';
                        ?>
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium <?= $urgencyClass ?>">
                            <?= isset($jadwal['urgensi']) ? ucfirst($jadwal['urgensi']) : 'Tidak diketahui' ?>
                        </span>
                        <?= getTanggalBadge($jadwal['hari_tersisa'], $jadwal['status']) ?>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div class="lg:col-span-2 space-y-6">
                        <!-- Informasi Jadwal -->
                        <div class="bg-white p-6 rounded-lg shadow-md border-l-4 border-blue-500">
                            <h4 class="text-lg font-semibold text-blue-600 mb-4 pb-2 border-b border-gray-200 flex items-center">
                                <i class="fa fa-calendar mr-2"></i>
                                Informasi Jadwal
                            </h4>
                            
                            <div class="space-y-4">
                                <div class="flex flex-col md:flex-row md:items-start">
                                    <div class="font-semibold text-gray-600 w-full md:w-48 flex-shrink-0 mb-1 md:mb-0">Tanggal & Waktu:</div>
                                    <div class="flex-1">
                                        <div class="font-bold text-gray-900"><?= date('l, d F Y', strtotime($jadwal['tanggal_kunjungan'])) ?></div>
                                        <div class="text-gray-600 flex items-center mt-1">
                                            <i class="fa fa-clock mr-2"></i>
                                            <?= $jadwal['waktu_mulai'] ? date('H:i', strtotime($jadwal['waktu_mulai'])) : '00:00' ?> WIB
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="flex flex-col md:flex-row md:items-start">
                                    <div class="font-semibold text-gray-600 w-full md:w-48 flex-shrink-0 mb-1 md:mb-0">Jenis Kunjungan:</div>
                                    <div class="flex-1">
                                        <span class="px-3 py-1 text-xs font-semibold rounded-full bg-<?= getJenisKunjunganBadge($jadwal['jenis_kunjungan']) === 'primary' ? 'blue' : (getJenisKunjunganBadge($jadwal['jenis_kunjungan']) === 'success' ? 'green' : (getJenisKunjunganBadge($jadwal['jenis_kunjungan']) === 'warning' ? 'yellow' : 'gray')) ?>-100 text-<?= getJenisKunjunganBadge($jadwal['jenis_kunjungan']) === 'primary' ? 'blue' : (getJenisKunjunganBadge($jadwal['jenis_kunjungan']) === 'success' ? 'green' : (getJenisKunjunganBadge($jadwal['jenis_kunjungan']) === 'warning' ? 'yellow' : 'gray')) ?>-800">
                                            <?= getJenisKunjunganText($jadwal['jenis_kunjungan']) ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="flex flex-col md:flex-row md:items-start">
                                    <div class="font-semibold text-gray-600 w-full md:w-48 flex-shrink-0 mb-1 md:mb-0">Estimasi Durasi:</div>
                                    <div class="flex-1">
                                        <?php if (isset($jadwal['estimasi_durasi']) && $jadwal['estimasi_durasi'] > 0): ?>
                                            <span class="text-gray-900"><?= $jadwal['estimasi_durasi'] ?> menit</span>
                                            <span class="text-gray-500 text-sm">(<?= floor($jadwal['estimasi_durasi'] / 60) ?> jam <?= $jadwal['estimasi_durasi'] % 60 ?> menit)</span>
                                        <?php else: ?>
                                            <span class="text-gray-500">Tidak ditentukan</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="flex flex-col md:flex-row md:items-start">
                                    <div class="font-semibold text-gray-600 w-full md:w-48 flex-shrink-0 mb-1 md:mb-0">Status:</div>
                                    <div class="flex-1">
                                        <span class="px-3 py-1 text-xs font-semibold rounded-full bg-<?= getStatusBadge($jadwal['status']) === 'success' ? 'green' : (getStatusBadge($jadwal['status']) === 'warning' ? 'yellow' : (getStatusBadge($jadwal['status']) === 'danger' ? 'red' : 'gray')) ?>-100 text-<?= getStatusBadge($jadwal['status']) === 'success' ? 'green' : (getStatusBadge($jadwal['status']) === 'warning' ? 'yellow' : (getStatusBadge($jadwal['status']) === 'danger' ? 'red' : 'gray')) ?>-800">
                                            <?= getStatusText($jadwal['status']) ?>
                                        </span>
                                        <?php if ($jadwal['status'] === 'selesai' && $jadwal['tanggal_selesai']): ?>
                                            <div class="text-gray-500 text-sm mt-1">
                                                Selesai pada: <?= date('d/m/Y H:i', strtotime($jadwal['tanggal_selesai'])) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="flex flex-col md:flex-row md:items-center">
                                    <div class="font-semibold text-gray-600 w-full md:w-32 flex-shrink-0 mb-1 md:mb-0">Urgensi:</div>
                                    <div class="flex-1">
                                        <?php
                                        $urgencyColors = [
                                            'rendah' => 'bg-green-100 text-green-800',
                                            'sedang' => 'bg-yellow-100 text-yellow-800',
                                            'tinggi' => 'bg-red-100 text-red-800'
                                        ];
                                        $urgencyClass = isset($jadwal['urgensi']) ? ($urgencyColors[$jadwal['urgensi']] ?? 'bg-gray-100 text-gray-800') : 'bg-gray-100 text-gray-800';
                                        ?>
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium <?= $urgencyClass ?>">
                                            <?= isset($jadwal['urgensi']) ? ucfirst($jadwal['urgensi']) : 'Tidak diketahui' ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Informasi Desa -->
                        <div class="bg-white p-6 rounded-lg shadow-md border-l-4 border-blue-500">
                            <h4 class="text-lg font-semibold text-blue-600 mb-4 pb-2 border-b border-gray-200 flex items-center">
                                <i class="fa fa-map-marker mr-2"></i>
                                Informasi Desa
                            </h4>
                            
                            <div class="space-y-4">
                                <div class="flex flex-col md:flex-row md:items-start">
                                    <div class="font-semibold text-gray-600 w-full md:w-48 flex-shrink-0 mb-1 md:mb-0">Nama Desa:</div>
                                    <div class="flex-1 flex items-center space-x-3">
                                        <span class="font-bold text-gray-900"><?= htmlspecialchars($jadwal['nama_desa']) ?></span>
                                        <a href="desa-view.php?id=<?= $jadwal['desa_id'] ?>" class="inline-flex items-center px-3 py-1 text-xs font-medium text-blue-600 bg-blue-100 rounded-full hover:bg-blue-200 transition-colors">
                                            <i class="fa fa-eye mr-1"></i>
                                            Lihat Detail
                                        </a>
                                    </div>
                                </div>
                                
                                <div class="flex flex-col md:flex-row md:items-start">
                                    <div class="font-semibold text-gray-600 w-full md:w-48 flex-shrink-0 mb-1 md:mb-0">Alamat:</div>
                                    <div class="flex-1 text-gray-900"><?= htmlspecialchars($jadwal['alamat']) ?></div>
                                </div>
                                
                                <?php if (isset($jadwal['kontak_person']) && $jadwal['kontak_person'] || isset($jadwal['telepon']) && $jadwal['telepon'] || isset($jadwal['email']) && $jadwal['email']): ?>
                                <div class="bg-gray-50 p-4 rounded-lg mt-4">
                                    <div class="font-semibold text-gray-700 mb-2">Kontak Person:</div>
                                    <div class="space-y-2">
                                        <?php if (isset($jadwal['kontak_person']) && $jadwal['kontak_person']): ?>
                                            <div class="flex items-center text-gray-600">
                                                <i class="fa fa-user w-4 mr-2"></i>
                                                <?= htmlspecialchars($jadwal['kontak_person']) ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (isset($jadwal['telepon']) && $jadwal['telepon']): ?>
                                            <div class="flex items-center text-gray-600">
                                                <i class="fa fa-phone w-4 mr-2"></i>
                                                <?= htmlspecialchars($jadwal['telepon']) ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (isset($jadwal['email']) && $jadwal['email']): ?>
                                            <div class="flex items-center text-gray-600">
                                                <i class="fa fa-envelope w-4 mr-2"></i>
                                                <?= htmlspecialchars($jadwal['email']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>



                        <!-- Catatan Kunjungan -->
                        <?php if (isset($jadwal['catatan_kunjungan']) && $jadwal['catatan_kunjungan']): ?>
                        <div class="bg-white p-6 rounded-lg shadow-md border-l-4 border-blue-500">
                            <h4 class="text-lg font-semibold text-blue-600 mb-4 pb-2 border-b border-gray-200 flex items-center">
                                <i class="fa fa-comment mr-2"></i>
                                Catatan Kunjungan
                            </h4>
                            <div class="bg-gray-50 p-4 rounded-lg border-l-4 border-blue-500 whitespace-pre-line text-gray-700">
                                <?= htmlspecialchars($jadwal['catatan_kunjungan']) ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                     <div class="space-y-6">
                         <!-- Tim Kunjungan -->
                         <div class="bg-white p-6 rounded-lg shadow-md border-l-4 border-green-500">
                             <h4 class="text-lg font-semibold text-green-600 mb-4 pb-2 border-b border-gray-200 flex items-center">
                                 <i class="fa fa-users mr-2"></i>
                                 Tim Kunjungan
                             </h4>
                             
                             <div class="space-y-4">
                                 <div class="bg-gradient-to-r from-blue-50 to-blue-100 p-4 rounded-lg border border-blue-200">
                                     <div class="text-sm font-semibold text-blue-600 mb-2">Penanggung Jawab</div>
                                     <div class="text-lg font-bold text-blue-800">
                                         <?= htmlspecialchars($jadwal['sales_name']) ?>
                                     </div>
                                     <?php if (isset($jadwal['sales_telepon']) && $jadwal['sales_telepon']): ?>
                                         <div class="text-sm text-blue-600 mt-1">
                                             <i class="fa fa-phone mr-1"></i> <?= htmlspecialchars($jadwal['sales_telepon']) ?>
                                         </div>
                                     <?php endif; ?>
                                 </div>

                             </div>
                         </div>

                        <!-- Produk yang Dibawa -->
                        <div class="bg-white p-6 rounded-lg shadow-md border-l-4 border-purple-500">
                            <h4 class="text-lg font-semibold text-purple-600 mb-4 pb-2 border-b border-gray-200 flex items-center">
                                <i class="fa fa-box mr-2"></i>
                                Produk yang Dibawa
                            </h4>
                            
                            <?php if (!empty($produk_list)): ?>
                                <div class="space-y-3">
                                    <?php foreach ($produk_list as $produk): ?>
                                        <div class="bg-purple-50 p-4 rounded-lg border border-purple-200">
                                            <div class="flex justify-between items-start">
                                                <div class="flex-1">
                                                    <div class="font-semibold text-purple-800"><?= htmlspecialchars($produk['nama_produk']) ?></div>
                                                    <div class="text-sm text-purple-600">Kode: <?= htmlspecialchars($produk['kode_produk']) ?></div>
                                                    <div class="text-sm text-gray-600 mt-1">
                                                        Harga Satuan: Rp <?= number_format($produk['harga_satuan'], 0, ',', '.') ?>
                                                    </div>
                                                </div>
                                                <div class="text-right">
                                                    <div class="text-lg font-bold text-purple-800"><?= $produk['quantity'] ?> unit</div>
                                                    <div class="text-sm text-gray-600">
                                                        Total: Rp <?= number_format($produk['quantity'] * $produk['harga_satuan'], 0, ',', '.') ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                    
                                    <?php 
                                    $total_produk = 0;
                                    foreach ($produk_list as $produk) {
                                        $total_produk += $produk['quantity'] * $produk['harga_satuan'];
                                    }
                                    ?>
                                    <div class="bg-purple-100 p-3 rounded-lg border-2 border-purple-300">
                                        <div class="flex justify-between items-center">
                                            <span class="font-semibold text-purple-800">Total Nilai Produk:</span>
                                            <span class="text-lg font-bold text-purple-900">Rp <?= number_format($total_produk, 0, ',', '.') ?></span>
                                        </div>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-8 text-gray-500">
                                    <i class="fa fa-box text-4xl mb-3"></i>
                                    <p>Tidak ada produk yang dibawa untuk kunjungan ini</p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Peralatan yang Dibawa -->
                        <div class="bg-white p-6 rounded-lg shadow-md border-l-4 border-indigo-500">
                            <h4 class="text-lg font-semibold text-indigo-600 mb-4 pb-2 border-b border-gray-200 flex items-center">
                                <i class="fa fa-tools mr-2"></i>
                                Peralatan yang Dibawa
                            </h4>
                            
                            <?php if (!empty($peralatan_list)): ?>
                                <div class="space-y-3">
                                    <?php foreach ($peralatan_list as $peralatan): ?>
                                        <div class="bg-indigo-50 p-4 rounded-lg border border-indigo-200">
                                            <div class="flex justify-between items-start">
                                                <div class="flex-1">
                                                    <div class="font-semibold text-indigo-800"><?= htmlspecialchars($peralatan['nama_peralatan']) ?></div>
                                                    <div class="text-sm text-indigo-600">Kode: <?= htmlspecialchars($peralatan['kode_peralatan']) ?></div>
                                                    <div class="text-sm text-gray-600 mt-2">
                                                        <div class="grid grid-cols-2 gap-2">
                                                            <div>
                                                                <span class="font-medium">Kondisi Awal:</span>
                                                                <span class="px-2 py-1 text-xs rounded-full <?= $peralatan['kondisi_awal'] === 'baik' ? 'bg-green-100 text-green-800' : ($peralatan['kondisi_awal'] === 'rusak' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800') ?>">
                                                                    <?= ucfirst($peralatan['kondisi_awal']) ?>
                                                                </span>
                                                            </div>
                                                            <?php if ($peralatan['kondisi_akhir']): ?>
                                                            <div>
                                                                <span class="font-medium">Kondisi Akhir:</span>
                                                                <span class="px-2 py-1 text-xs rounded-full <?= $peralatan['kondisi_akhir'] === 'baik' ? 'bg-green-100 text-green-800' : ($peralatan['kondisi_akhir'] === 'rusak' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800') ?>">
                                                                    <?= ucfirst($peralatan['kondisi_akhir']) ?>
                                                                </span>
                                                            </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="text-right">
                                                    <div class="text-lg font-bold text-indigo-800"><?= $peralatan['quantity'] ?> unit</div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-8 text-gray-500">
                                    <i class="fa fa-tools text-4xl mb-3"></i>
                                    <p>Tidak ada peralatan yang dibawa untuk kunjungan ini</p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Personal yang Disertakan -->
                        <div class="bg-white p-6 rounded-lg shadow-md border-l-4 border-teal-500">
                            <h4 class="text-lg font-semibold text-teal-600 mb-4 pb-2 border-b border-gray-200 flex items-center">
                                <i class="fa fa-user-friends mr-2"></i>
                                Personal yang Disertakan
                            </h4>
                            
                            <?php if (!empty($personal_list)): ?>
                                <div class="space-y-3">
                                    <?php foreach ($personal_list as $personal): ?>
                                        <div class="bg-teal-50 p-4 rounded-lg border border-teal-200">
                                            <div class="flex justify-between items-start">
                                                <div class="flex-1">
                                                    <div class="font-semibold text-teal-800"><?= htmlspecialchars($personal['nama']) ?></div>
                                                    <div class="text-sm text-teal-600"><?= htmlspecialchars($personal['email']) ?></div>
                                                    <div class="text-sm text-gray-600 mt-1">
                                                        Role Sistem: <span class="px-2 py-1 text-xs bg-gray-100 text-gray-800 rounded-full"><?= ucfirst($personal['role']) ?></span>
                                                    </div>
                                                </div>
                                                <div class="text-right">
                                                    <div class="text-sm font-medium text-teal-800"><?= htmlspecialchars($personal['role_dalam_kunjungan']) ?></div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-8 text-gray-500">
                                    <i class="fa fa-user-friends text-4xl mb-3"></i>
                                    <p>Tidak ada personal tambahan yang disertakan untuk kunjungan ini</p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Biaya Operasional -->
                        <?php if (!empty($biaya_list)): ?>
                        <div class="bg-white p-6 rounded-lg shadow-md border-l-4 border-yellow-500">
                            <h4 class="text-lg font-semibold text-yellow-600 mb-4 pb-2 border-b border-gray-200 flex items-center">
                                <i class="fa fa-money-bill mr-2"></i>
                                Biaya Operasional
                            </h4>
                            
                            <div class="space-y-3">
                                <?php foreach ($biaya_list as $biaya): ?>
                                    <div class="bg-yellow-50 p-4 rounded-lg border border-yellow-200">
                                        <div class="flex justify-between items-start">
                                            <div class="flex-1">
                                                <div class="font-semibold text-yellow-800"><?= htmlspecialchars($biaya['nama_biaya']) ?></div>
                                                <div class="text-sm text-yellow-600">Kategori: <?= htmlspecialchars($biaya['kategori']) ?></div>
                                                <div class="text-sm text-gray-600 mt-1">
                                                    <?= $biaya['quantity'] ?> × Rp <?= number_format($biaya['harga_satuan'], 0, ',', '.') ?>
                                                </div>
                                            </div>
                                            <div class="text-right">
                                                <div class="text-lg font-bold text-yellow-800">Rp <?= number_format($biaya['total_biaya'], 0, ',', '.') ?></div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                
                                <?php 
                                $total_biaya = 0;
                                foreach ($biaya_list as $biaya) {
                                    $total_biaya += $biaya['total_biaya'];
                                }
                                ?>
                                <div class="bg-yellow-100 p-3 rounded-lg border-2 border-yellow-300">
                                    <div class="flex justify-between items-center">
                                        <span class="font-semibold text-yellow-800">Total Biaya Operasional:</span>
                                        <span class="text-lg font-bold text-yellow-900">Rp <?= number_format($total_biaya, 0, ',', '.') ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Update Status -->
                        <?php if (canUpdateStatus($user, $jadwal) && $jadwal['status'] !== 'dibatalkan'): ?>
                        <div class="bg-white p-6 rounded-lg shadow-md border-l-4 border-orange-500">
                            <h4 class="text-lg font-semibold text-orange-600 mb-4 pb-2 border-b border-gray-200 flex items-center">
                                <i class="fa fa-edit mr-2"></i>
                                Update Status
                            </h4>
                            
                            <form method="POST" class="space-y-4">
                                <input type="hidden" name="action" value="update_status">
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Status Baru:</label>
                                    <select name="new_status" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-orange-500" required>
                                        <option value="">Pilih Status</option>
                                        <?php if ($jadwal['status'] !== 'selesai'): ?>
                                        <option value="selesai">Selesai</option>
                                        <?php endif; ?>
                                        <?php if ($jadwal['status'] !== 'ditunda'): ?>
                                        <option value="ditunda">Ditunda</option>
                                        <?php endif; ?>
                                        <?php if ($jadwal['status'] !== 'dijadwalkan'): ?>
                                        <option value="dijadwalkan">Dijadwalkan</option>
                                        <?php endif; ?>
                                        <?php if (AuthStatic::hasRole(['admin', 'sales'])): ?>
                                        <option value="dibatalkan">Dibatalkan</option>
                                        <?php endif; ?>
                                    </select>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Keterangan Update:</label>
                                    <textarea name="keterangan_update" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-orange-500" rows="3" 
                                              placeholder="Catatan tambahan (opsional)"></textarea>
                                </div>
                                
                                <button type="submit" class="w-full bg-orange-600 text-white px-4 py-2 rounded-md hover:bg-orange-700 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-2 transition-colors flex items-center justify-center">
                                    <i class="fa fa-save mr-2"></i> Update Status
                                </button>
                            </form>
                        </div>
                        <?php endif; ?>

                        <!-- Informasi Sistem -->
                        <div class="bg-white p-6 rounded-lg shadow-md border-l-4 border-purple-500">
                            <h4 class="text-lg font-semibold text-purple-600 mb-4 pb-2 border-b border-gray-200 flex items-center">
                                <i class="fa fa-info mr-2"></i>
                                Informasi Sistem
                            </h4>
                            
                            <div class="space-y-3">
                                <div class="flex flex-col md:flex-row md:items-center">
                                    <div class="font-semibold text-gray-600 w-full md:w-24 flex-shrink-0 mb-1 md:mb-0">Dibuat:</div>
                                    <div class="flex-1 text-gray-900">
                                        <?= date('d/m/Y H:i', strtotime($jadwal['created_at'])) ?>
                                    </div>
                                </div>
                                
                                <?php if ($jadwal['updated_at']): ?>
                                <div class="flex flex-col md:flex-row md:items-center">
                                    <div class="font-semibold text-gray-600 w-full md:w-24 flex-shrink-0 mb-1 md:mb-0">Diperbarui:</div>
                                    <div class="flex-1 text-gray-900">
                                        <?= date('d/m/Y H:i', strtotime($jadwal['updated_at'])) ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="flex flex-wrap gap-3 mt-8 pt-6 border-t border-gray-200">
                    <a href="jadwal.php" class="inline-flex items-center px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition-colors">
                        <i class="fa fa-arrow-left mr-2"></i> Kembali ke Daftar
                    </a>
                    
                    <?php if (canEdit($user, $jadwal)): ?>
                    <a href="jadwal-edit.php?id=<?= $jadwal['id'] ?>" class="inline-flex items-center px-4 py-2 bg-yellow-600 text-white rounded-md hover:bg-yellow-700 focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:ring-offset-2 transition-colors">
                        <i class="fa fa-edit mr-2"></i> Edit Jadwal
                    </a>
                    <?php endif; ?>
                    
                    <button onclick="window.print()" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors">
                        <i class="fa fa-print mr-2"></i> Cetak
                    </button>
                    
                    <a href="jadwal-berita-acara.php?id=<?= $jadwal['id'] ?>" target="_blank" class="inline-flex items-center px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition-colors">
                        <i class="fa fa-file-pdf mr-2"></i> Berita Acara PDF
                    </a>
                    
                    <?php if (AuthStatic::hasRole(['admin', 'sales'])): ?>
                    <a href="jadwal-add.php" class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition-colors">
                        <i class="fa fa-plus mr-2"></i> Buat Jadwal Baru
                    </a>
                    <?php endif; ?>
                    
                    <a href="jadwal.php" class="inline-flex items-center px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition-colors">
                        <i class="fa fa-arrow-left mr-2"></i> Kembali
                    </a>
                </div>
            </div>
        </div>
    </div>

        </div>

<script>
    // Auto hide alerts after 5 seconds
    setTimeout(function() {
        const alerts = document.querySelectorAll('[role="alert"]');
        alerts.forEach(alert => {
            alert.style.display = 'none';
        });
    }, 5000);

    // Print functionality
    function printJadwal() {
        window.print();
    }

    // Sidebar toggle functionality
    document.getElementById('toggleSidebarMobile').addEventListener('click', function() {
        const sidebar = document.getElementById('sidebar');
        const backdrop = document.getElementById('sidebarBackdrop');
        
        sidebar.classList.toggle('hidden');
        backdrop.classList.toggle('hidden');
    });

    // Close sidebar when clicking backdrop
    document.getElementById('sidebarBackdrop').addEventListener('click', function() {
        const sidebar = document.getElementById('sidebar');
        const backdrop = document.getElementById('sidebarBackdrop');
        
        sidebar.classList.add('hidden');
        backdrop.classList.add('hidden');
    });

    // Profile dropdown functionality
    document.getElementById('user-menu-button').addEventListener('click', function() {
        const dropdown = document.getElementById('dropdown-user');
        dropdown.classList.toggle('hidden');
    });

    // Close dropdown when clicking outside
    document.addEventListener('click', function(event) {
        const button = document.getElementById('user-menu-button');
        const dropdown = document.getElementById('dropdown-user');
        
        if (!button.contains(event.target) && !dropdown.contains(event.target)) {
            dropdown.classList.add('hidden');
        }
    });
</script>

<style>
    @media print {
        .no-print {
            display: none !important;
        }
        
        body {
            background: white !important;
        }
        
        .bg-gradient-to-br {
            background: #2563eb !important;
            -webkit-print-color-adjust: exact;
        }
    }
</style>

<?php require_once 'layouts/footer.php'; ?>
