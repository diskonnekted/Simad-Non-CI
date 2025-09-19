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

// Handle delete action
if (isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['jadwal_id'])) {
    if (AuthStatic::hasRole(['admin', 'sales'])) {
        $jadwal_id = intval($_POST['jadwal_id']);
        
        try {
            // Check if jadwal exists and can be deleted
            $jadwal = $db->select(
                "SELECT * FROM jadwal_kunjungan WHERE id = ?",
                [$jadwal_id]
            );
            
            if (!empty($jadwal)) {
                $jadwal = $jadwal[0];
                
                // Handle deletion based on current status
                if (in_array($jadwal['status'], ['dijadwalkan', 'ditunda'])) {
                    // Cancel the jadwal (change status to 'dibatalkan')
                    $db->update('jadwal_kunjungan', [
                        'status' => 'dibatalkan',
                        'catatan_kunjungan' => ($jadwal['catatan_kunjungan'] ? $jadwal['catatan_kunjungan'] . ' | ' : '') . 'Dibatalkan oleh ' . $user['nama_lengkap'] . ' pada ' . date('d/m/Y H:i')
                    ], ['id' => $jadwal_id]);
                    
                    header('Location: jadwal.php?success=' . urlencode('Jadwal berhasil dibatalkan.'));
                    exit;
                } elseif ($jadwal['status'] === 'dibatalkan') {
                    // Permanently delete from database if already cancelled
                    $db->delete('jadwal_kunjungan', ['id' => $jadwal_id]);
                    
                    header('Location: jadwal.php?success=' . urlencode('Data jadwal berhasil dihapus permanen dari database.'));
                    exit;
                } else {
                    header('Location: jadwal.php?error=' . urlencode('Jadwal dengan status "' . $jadwal['status'] . '" tidak dapat dihapus.'));
                    exit;
                }
            } else {
                header('Location: jadwal.php?error=' . urlencode('Jadwal tidak ditemukan.'));
                exit;
            }
        } catch (Exception $e) {
            header('Location: jadwal.php?error=' . urlencode('Terjadi kesalahan: ' . $e->getMessage()));
            exit;
        }
    }
}

// Get filter parameters
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? 'semua';
$desa_filter = $_GET['desa'] ?? '';
$teknisi_filter = $_GET['teknisi'] ?? '';
$jenis_filter = $_GET['jenis'] ?? '';
$tanggal_filter = $_GET['tanggal'] ?? '';
$sort = $_GET['sort'] ?? 'tanggal_kunjungan';
$order = $_GET['order'] ?? 'ASC';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// Build WHERE clause
$where_conditions = [];
$params = [];

// Filter berdasarkan role
if ($user['role'] === 'sales') {
    $where_conditions[] = "jk.user_id = ?";
    $params[] = $user['id'];
} elseif ($user['role'] === 'teknisi' || $user['role'] === 'programmer') {
    $where_conditions[] = "jk.teknisi_id = ?";
    $params[] = $user['id'];
}

if ($search) {
    $where_conditions[] = "(d.nama_desa LIKE ? OR jk.jenis_kunjungan LIKE ? OR jk.catatan_kunjungan LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if ($status_filter && $status_filter !== 'semua') {
    $where_conditions[] = "jk.status = ?";
    $params[] = $status_filter;
}

if ($desa_filter) {
    $where_conditions[] = "d.id = ?";
    $params[] = $desa_filter;
}

if ($teknisi_filter) {
    $where_conditions[] = "jk.teknisi_id = ?";
    $params[] = $teknisi_filter;
}

if ($jenis_filter) {
    $where_conditions[] = "jk.jenis_kunjungan = ?";
    $params[] = $jenis_filter;
}

if ($tanggal_filter) {
    switch ($tanggal_filter) {
        case 'hari_ini':
            $where_conditions[] = "DATE(jk.tanggal_kunjungan) = CURDATE()";
            break;
        case 'minggu_ini':
            $where_conditions[] = "YEARWEEK(jk.tanggal_kunjungan) = YEARWEEK(CURDATE())";
            break;
        case 'bulan_ini':
            $where_conditions[] = "YEAR(jk.tanggal_kunjungan) = YEAR(CURDATE()) AND MONTH(jk.tanggal_kunjungan) = MONTH(CURDATE())";
            break;
        case 'mendatang':
            $where_conditions[] = "jk.tanggal_kunjungan > NOW()";
            break;
    }
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count
$total_query = "
    SELECT COUNT(*) as total 
    FROM jadwal_kunjungan jk
    JOIN desa d ON jk.desa_id = d.id
    JOIN users u_sales ON jk.user_id = u_sales.id
    $where_clause
";
$total_result = $db->select($total_query, $params);
$total_records = $total_result[0]['total'];
$total_pages = ceil($total_records / $limit);

// Get jadwal data
$jadwal_query = "
    SELECT jk.*, d.nama_desa, d.nama_kepala_desa, d.no_hp_kepala_desa, d.nama_sekdes, d.no_hp_sekdes, d.nama_admin_it, d.no_hp_admin_it,
           u_sales.nama_lengkap as sales_name,
           DATEDIFF(jk.tanggal_kunjungan, CURDATE()) as hari_tersisa
    FROM jadwal_kunjungan jk
    JOIN desa d ON jk.desa_id = d.id
    JOIN users u_sales ON jk.user_id = u_sales.id
    $where_clause
    ORDER BY jk.$sort $order
    LIMIT $limit OFFSET $offset
";

$jadwal_list = $db->select($jadwal_query, $params);

// Get filter options
$desa_list = $db->select("SELECT id, nama_desa FROM desa WHERE status = 'aktif' ORDER BY nama_desa");
$teknisi_list = $db->select("SELECT id, nama_lengkap as nama FROM users WHERE role = 'teknisi' AND status = 'aktif' ORDER BY nama_lengkap");

// Get statistics
$stats_where = '';
$stats_params = [];
if ($user['role'] === 'sales') {
    $stats_where = "WHERE user_id = ?";
    $stats_params[] = $user['id'];
} elseif ($user['role'] === 'teknisi' || $user['role'] === 'programmer') {
    $stats_where = "WHERE teknisi_id = ?";
    $stats_params = [$user['id']];
}

$stats = $db->select("
    SELECT 
        COUNT(*) as total_jadwal,
        SUM(CASE WHEN status = 'dijadwalkan' THEN 1 ELSE 0 END) as dijadwalkan,
        SUM(CASE WHEN status = 'selesai' THEN 1 ELSE 0 END) as selesai,
        SUM(CASE WHEN status = 'ditunda' THEN 1 ELSE 0 END) as ditunda,
        SUM(CASE WHEN status = 'dibatalkan' THEN 1 ELSE 0 END) as dibatalkan,
        COUNT(CASE WHEN DATE(tanggal_kunjungan) = CURDATE() AND status = 'dijadwalkan' THEN 1 END) as hari_ini
    FROM jadwal_kunjungan
    $stats_where
", $stats_params)[0];

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
        'support' => 'warning'
    ];
    return $badges[$jenis] ?? 'default';
}

function getJenisKunjunganText($jenis) {
    $texts = [
        'maintenance' => 'Maintenance',
        'instalasi' => 'Instalasi',
        'training' => 'Training',
        'support' => 'Support'
    ];
    return $texts[$jenis] ?? $jenis;
}

function getUrgencyBadge($urgency) {
    $badges = [
        'rendah' => 'default',
        'normal' => 'info',
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

function getSortIcon($column, $current_sort, $current_order) {
    if ($column !== $current_sort) {
        return '<i class="fa fa-sort text-muted"></i>';
    }
    return $current_order === 'ASC' ? 
        '<i class="fa fa-sort-up text-primary"></i>' : 
        '<i class="fa fa-sort-down text-primary"></i>';
}

function getSortUrl($column, $current_sort, $current_order) {
    $new_order = ($column === $current_sort && $current_order === 'ASC') ? 'DESC' : 'ASC';
    $params = $_GET;
    $params['sort'] = $column;
    $params['order'] = $new_order;
    unset($params['page']);
    return '?' . http_build_query($params);
}
?>
<?php require_once 'layouts/header.php'; ?>

<style>
    .jadwal-row.overdue {
        background-color: #fef2f2 !important;
    }
    .jadwal-row.today {
        background-color: #f0fdf4 !important;
    }
    .urgency-indicator {
        width: 12px;
        height: 12px;
        border-radius: 50%;
        display: inline-block;
        margin-right: 5px;
    }
    .urgency-rendah { background-color: #10b981; }
    .urgency-normal { background-color: #06b6d4; }
    .urgency-tinggi { background-color: #f59e0b; }
    .urgency-urgent { background-color: #ef4444; }
</style>
<!-- Main Content -->
<div class="min-h-screen bg-gray-50">
    <div class="max-w-7xl ml-0 px-4 sm:px-6 lg:px-8 py-8">
        <!-- Page Header -->
        <div class="mb-8">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900 flex items-center">
                        <i class="fas fa-calendar-alt mr-3 text-blue-600"></i>
                        Jadwal Kunjungan
                    </h1>
                    <p class="mt-2 text-gray-600">Kelola jadwal kunjungan lapangan dan maintenance</p>
                </div>
                <div>
                    <?php if (AuthStatic::hasRole(['admin', 'sales'])): ?>
                    <a href="jadwal-add.php" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center transition-colors">
                        <i class="fas fa-plus mr-2"></i> Buat Jadwal
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
                <!-- Success/Error Messages -->
                <?php if (isset($_GET['success'])): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4 relative">
                    <span class="block sm:inline"><?= htmlspecialchars($_GET['success']) ?></span>
                    <span class="absolute top-0 bottom-0 right-0 px-4 py-3">
                        <svg class="fill-current h-6 w-6 text-green-500" role="button" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><title>Close</title><path d="M14.348 14.849a1.2 1.2 0 0 1-1.697 0L10 11.819l-2.651 3.029a1.2 1.2 0 1 1-1.697-1.697l2.758-3.15-2.759-3.152a1.2 1.2 0 1 1 1.697-1.697L10 8.183l2.651-3.031a1.2 1.2 0 1 1 1.697 1.697l-2.758 3.152 2.758 3.15a1.2 1.2 0 0 1 0 1.698z"/></svg>
                    </span>
                </div>
                <?php endif; ?>

                <?php if (isset($_GET['error'])): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4 relative">
                    <span class="block sm:inline"><?= htmlspecialchars($_GET['error']) ?></span>
                    <span class="absolute top-0 bottom-0 right-0 px-4 py-3">
                        <svg class="fill-current h-6 w-6 text-red-500" role="button" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><title>Close</title><path d="M14.348 14.849a1.2 1.2 0 0 1-1.697 0L10 11.819l-2.651 3.029a1.2 1.2 0 1 1-1.697-1.697l2.758-3.15-2.759-3.152a1.2 1.2 0 1 1 1.697-1.697L10 8.183l2.651-3.031a1.2 1.2 0 1 1 1.697 1.697l-2.758 3.152 2.758 3.15a1.2 1.2 0 0 1 0 1.698z"/></svg>
                    </span>
                </div>
                <?php endif; ?>

                <!-- Statistics -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4 mb-6">
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                </svg>
                            </div>
                            <div class="ml-4">
                                <div class="text-2xl font-bold text-gray-900"><?= number_format($stats['total_jadwal'] ?? 0) ?></div>
                                <div class="text-sm text-gray-600">Total Jadwal</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-yellow-100 text-yellow-600">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <div class="ml-4">
                                <div class="text-2xl font-bold text-gray-900"><?= number_format($stats['dijadwalkan'] ?? 0) ?></div>
                                <div class="text-sm text-gray-600">Dijadwalkan</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-orange-100 text-orange-600">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <div class="ml-4">
                                <div class="text-2xl font-bold text-gray-900"><?= number_format($stats['hari_ini'] ?? 0) ?></div>
                                <div class="text-sm text-gray-600">Hari Ini</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-green-100 text-green-600">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <div class="ml-4">
                                <div class="text-2xl font-bold text-gray-900"><?= number_format($stats['selesai'] ?? 0) ?></div>
                                <div class="text-sm text-gray-600">Selesai</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-yellow-100 text-yellow-600">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <div class="ml-4">
                                <div class="text-2xl font-bold text-gray-900"><?= number_format($stats['ditunda'] ?? 0) ?></div>
                                <div class="text-sm text-gray-600">Ditunda</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-red-100 text-red-600">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <div class="ml-4">
                                <div class="text-2xl font-bold text-gray-900"><?= number_format($stats['dibatalkan'] ?? 0) ?></div>
                                <div class="text-sm text-gray-600">Dibatalkan</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filter Section -->
                <div class="bg-white rounded-lg shadow p-6 mb-6">
                    <form method="GET" class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4">
                            <div>
                                <input type="text" name="search" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" 
                                       placeholder="Cari desa, keperluan..." value="<?= htmlspecialchars($search) ?>">
                            </div>
                            
                            <div>
                                <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                    <option value="semua" <?= $status_filter === 'semua' ? 'selected' : '' ?>>Semua Status</option>
                                    <option value="dijadwalkan" <?= $status_filter === 'dijadwalkan' ? 'selected' : '' ?>>Dijadwalkan</option>
                                    <option value="selesai" <?= $status_filter === 'selesai' ? 'selected' : '' ?>>Selesai</option>
                                    <option value="ditunda" <?= $status_filter === 'ditunda' ? 'selected' : '' ?>>Ditunda</option>
                                    <option value="dibatalkan" <?= $status_filter === 'dibatalkan' ? 'selected' : '' ?>>Dibatalkan</option>
                                </select>
                            </div>
                            
                            <div>
                                <select name="desa" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                    <option value="">Semua Desa</option>
                                    <?php foreach ($desa_list as $desa): ?>
                                    <option value="<?= $desa['id'] ?>" <?= $desa_filter == $desa['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($desa['nama_desa']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <?php if (AuthStatic::hasRole(['admin'])): ?>
                            <div>
                                <select name="teknisi" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                    <option value="">Semua Teknisi</option>
                                    <?php foreach ($teknisi_list as $teknisi): ?>
                                    <option value="<?= $teknisi['id'] ?>" <?= $teknisi_filter == $teknisi['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($teknisi['nama']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php endif; ?>
                            
                            <div>
                                <select name="jenis" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                    <option value="">Semua Jenis</option>
                                    <option value="maintenance" <?= $jenis_filter === 'maintenance' ? 'selected' : '' ?>>Maintenance</option>
                                    <option value="instalasi" <?= $jenis_filter === 'instalasi' ? 'selected' : '' ?>>Instalasi</option>
                                    <option value="training" <?= $jenis_filter === 'training' ? 'selected' : '' ?>>Training</option>
                                    <option value="support" <?= $jenis_filter === 'support' ? 'selected' : '' ?>>Support</option>
                                </select>
                            </div>
                            
                            <div>
                                <select name="tanggal" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                    <option value="">Semua Tanggal</option>
                                    <option value="hari_ini" <?= $tanggal_filter === 'hari_ini' ? 'selected' : '' ?>>Hari Ini</option>
                                    <option value="minggu_ini" <?= $tanggal_filter === 'minggu_ini' ? 'selected' : '' ?>>Minggu Ini</option>
                                    <option value="bulan_ini" <?= $tanggal_filter === 'bulan_ini' ? 'selected' : '' ?>>Bulan Ini</option>
                                    <option value="mendatang" <?= $tanggal_filter === 'mendatang' ? 'selected' : '' ?>>Mendatang</option>
                                </select>
                            </div>
                            
                            <div class="flex space-x-2">
                                <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                                    <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                    </svg>
                                    Filter
                                </button>
                                
                                <a href="jadwal.php" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                                    <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                    </svg>
                                    Reset
                                </a>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Jadwal Table -->
                <div class="bg-white rounded-lg shadow">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">Daftar Jadwal Kunjungan (<?= number_format($total_records ?? 0) ?> jadwal)</h3>
                    </div>
                    
                    <?php if (empty($jadwal_list)): ?>
                    <div class="p-6">
                        <div class="text-center py-12">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                            <h4 class="mt-4 text-lg font-medium text-gray-900">Tidak ada jadwal ditemukan</h4>
                            <p class="mt-2 text-sm text-gray-500">Silakan ubah filter pencarian atau buat jadwal baru.</p>
                            <?php if (AuthStatic::hasRole(['admin', 'sales'])): ?>
                            <a href="jadwal-add.php" class="mt-4 inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                </svg>
                                Buat Jadwal Pertama
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200" id="jadwalTable">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100" onclick="location.href='<?= getSortUrl('tanggal_kunjungan', $sort, $order) ?>'">
                                            Tanggal Kunjungan <?= getSortIcon('tanggal_kunjungan', $sort, $order) ?>
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Desa</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Jenis & Keperluan</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Penanggung Jawab</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Urgency</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($jadwal_list as $jadwal): ?>
                                    <?php 
                                        $row_class = 'hover:bg-gray-50';
                                        if ($jadwal['hari_tersisa'] < 0 && $jadwal['status'] === 'dijadwalkan') {
                                            $row_class = 'bg-red-50 hover:bg-red-100';
                                        } elseif ($jadwal['hari_tersisa'] == 0 && $jadwal['status'] === 'dijadwalkan') {
                                            $row_class = 'bg-yellow-50 hover:bg-yellow-100';
                                        }
                                    ?>
                                    <tr class="<?= $row_class ?>">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            <div class="flex items-center">
                                                <div class="w-3 h-3 rounded-full mr-3 bg-blue-500"></div>
                                                <div>
                                                    <div class="font-medium text-gray-900"><?= date('d/m/Y', strtotime($jadwal['tanggal_kunjungan'])) ?></div>
                                                    <div class="text-gray-500"><?= date('H:i', strtotime($jadwal['tanggal_kunjungan'])) ?></div>
                                                    <div class="mt-1"><?= getTanggalBadge($jadwal['hari_tersisa'], $jadwal['status']) ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            <div class="font-medium text-gray-900"><?= htmlspecialchars($jadwal['nama_desa']) ?></div>
                                            <?php if (!empty($jadwal['nama_kepala_desa'])): ?>
                                            <div class="text-gray-500"><?= htmlspecialchars($jadwal['nama_kepala_desa']) ?></div>
                                            <?php endif; ?>
                                            <?php if (!empty($jadwal['no_hp_kepala_desa'])): ?>
                                            <div class="text-gray-500 flex items-center">
                                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                                                </svg>
                                                <?= htmlspecialchars($jadwal['no_hp_kepala_desa']) ?>
                                            </div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?= getJenisKunjunganBadge($jadwal['jenis_kunjungan']) ?>">
                                                <?= getJenisKunjunganText($jadwal['jenis_kunjungan']) ?>
                                            </span>
                                            <?php if (!empty($jadwal['catatan_kunjungan'])): ?>
                                            <div class="font-medium text-gray-900 mt-1"><?= htmlspecialchars($jadwal['catatan_kunjungan']) ?></div>
                                            <?php endif; ?>
                                        </td>

                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                                                Normal
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?= getStatusBadge($jadwal['status']) ?>">
                                                <?= getStatusText($jadwal['status']) ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <div class="flex space-x-1">
                                                <a href="jadwal-view.php?id=<?= $jadwal['id'] ?>" 
                                                   class="inline-flex items-center px-2 py-1 border border-transparent text-xs font-medium rounded text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500" 
                                                   title="Lihat Detail">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                                    </svg>
                                                    <span class="ml-1">Detail</span>
                                                </a>
                                                
                                                <?php if (AuthStatic::hasRole(['admin', 'sales']) && in_array($jadwal['status'], ['dijadwalkan', 'ditunda'])): ?>
                                                <a href="jadwal-edit.php?id=<?= $jadwal['id'] ?>" 
                                                   class="inline-flex items-center px-2 py-1 border border-transparent text-xs font-medium rounded text-white bg-yellow-600 hover:bg-yellow-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-yellow-500" 
                                                   title="Edit">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                                    </svg>
                                                    <span class="ml-1">Edit</span>
                                                </a>
                                                <?php endif; ?>
                                                
                                                <?php if (AuthStatic::hasRole(['admin', 'sales'])): ?>
                                                <button type="button" 
                                                        class="inline-flex items-center px-2 py-1 border border-transparent text-xs font-medium rounded text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500" 
                                                        onclick="confirmDelete(<?= $jadwal['id'] ?>, '<?= htmlspecialchars($jadwal['catatan_kunjungan'] ?? 'Jadwal #' . $jadwal['id']) ?>', '<?= $jadwal['status'] ?>')" 
                                                        title="<?= $jadwal['status'] === 'dibatalkan' ? 'Hapus Permanen' : 'Batalkan' ?>">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                    </svg>
                                                    <span class="ml-1"><?= $jadwal['status'] === 'dibatalkan' ? 'Hapus' : 'Batalkan' ?></span>
                                                </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                    <div class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6">
                        <div class="flex-1 flex justify-between sm:hidden">
                            <?php if ($page > 1): ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                Previous
                            </a>
                            <?php endif; ?>
                            <?php if ($page < $total_pages): ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                Next
                            </a>
                            <?php endif; ?>
                        </div>
                        <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                            <div>
                                <p class="text-sm text-gray-700">
                                    Menampilkan
                                    <span class="font-medium"><?= number_format(($page - 1) * $limit + 1) ?></span>
                                    sampai
                                    <span class="font-medium"><?= number_format(min($page * $limit, $total_records)) ?></span>
                                    dari
                                    <span class="font-medium"><?= number_format($total_records) ?></span>
                                    jadwal
                                </p>
                            </div>
                            <div>
                                <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                                    <?php if ($page > 1): ?>
                                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                        <span class="sr-only">Previous</span>
                                        <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                            <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                                        </svg>
                                    </a>
                                    <?php endif; ?>
                                    
                                    <?php
                                    $start_page = max(1, $page - 2);
                                    $end_page = min($total_pages, $page + 2);
                                    
                                    for ($i = $start_page; $i <= $end_page; $i++):
                                    ?>
                                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" class="<?= $i === $page ? 'bg-blue-50 border-blue-500 text-blue-600' : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50' ?> relative inline-flex items-center px-4 py-2 border text-sm font-medium">
                                        <?= $i ?>
                                    </a>
                                    <?php endfor; ?>
                                    
                                    <?php if ($page < $total_pages): ?>
                                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                        <span class="sr-only">Next</span>
                                        <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                                        </svg>
                                    </a>
                                    <?php endif; ?>
                                </nav>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden" id="deleteModal">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3 text-center">
                <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100">
                    <svg class="h-6 w-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.732-.833-2.5 0L4.268 15.5c-.77.833.192 2.5 1.732 2.5z"></path>
                    </svg>
                </div>
                <h3 class="text-lg leading-6 font-medium text-gray-900 mt-4" id="deleteModalTitle">Konfirmasi Pembatalan</h3>
                <div class="mt-2 px-7 py-3">
                    <p class="text-sm text-gray-500" id="deleteModalMessage">Apakah Anda yakin ingin membatalkan jadwal kunjungan ini?</p>
                    <p class="text-sm font-medium text-gray-900 mt-2" id="deleteJadwalName"></p>
                    <p class="text-sm text-gray-500 mt-2" id="deleteModalWarning">Jadwal yang dibatalkan tidak dapat dikembalikan.</p>
                </div>
                <div class="items-center px-4 py-3">
                    <form method="POST" id="deleteForm" class="flex space-x-3">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="jadwal_id" id="deleteJadwalId">
                        <button type="button" onclick="closeModal()" class="px-4 py-2 bg-gray-500 text-white text-base font-medium rounded-md w-full shadow-sm hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-gray-300">
                            Batal
                        </button>
                        <button type="submit" class="px-4 py-2 bg-red-600 text-white text-base font-medium rounded-md w-full shadow-sm hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500" id="deleteSubmitButton">
                            Batalkan Jadwal
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script src="js/jquery.min.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script src="js/plugins.js"></script>
    <script src="assets/js/datatables.min.js"></script>
    <script>
        function confirmDelete(jadwalId, keperluan, status) {
            document.getElementById('deleteJadwalId').value = jadwalId;
            document.getElementById('deleteJadwalName').textContent = keperluan;
            
            const modalTitle = document.getElementById('deleteModalTitle');
            const modalMessage = document.getElementById('deleteModalMessage');
            const modalWarning = document.getElementById('deleteModalWarning');
            const submitButton = document.getElementById('deleteSubmitButton');
            
            if (status === 'dibatalkan') {
                modalTitle.textContent = 'Konfirmasi Penghapusan Permanen';
                modalMessage.textContent = 'Apakah Anda yakin ingin menghapus jadwal ini secara permanen dari database?';
                modalWarning.textContent = 'Data yang dihapus tidak dapat dikembalikan.';
                submitButton.textContent = 'Hapus Permanen';
            } else {
                modalTitle.textContent = 'Konfirmasi Pembatalan';
                modalMessage.textContent = 'Apakah Anda yakin ingin membatalkan jadwal kunjungan ini?';
                modalWarning.textContent = 'Jadwal yang dibatalkan tidak dapat dikembalikan.';
                submitButton.textContent = 'Batalkan Jadwal';
            }
            
            document.getElementById('deleteModal').classList.remove('hidden');
        }
        
        function closeModal() {
            document.getElementById('deleteModal').classList.add('hidden');
        }
        
        // Auto-hide alerts
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                alert.style.opacity = '0';
                setTimeout(function() {
                    alert.style.display = 'none';
                }, 300);
            });
        }, 5000);
        
        // Close modal when clicking outside
        document.getElementById('deleteModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
    </script>
</body>
</html>
