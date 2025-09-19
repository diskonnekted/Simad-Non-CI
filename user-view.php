<?php
require_once 'config/database.php';
require_once 'config/auth.php';

// Cek autentikasi dan role
if (!AuthStatic::isLoggedIn()) {
    header('Location: login.php');
    exit;
}

if (!AuthStatic::hasRole(['admin'])) {
    header('Location: index.php?error=access_denied');
    exit;
}

$current_user = AuthStatic::getCurrentUser();
/** @var Database $db */
$db = getDatabase();

$user_id = intval($_GET['id'] ?? 0);

if (!$user_id) {
    header('Location: user.php?error=invalid_id');
    exit;
}

// Ambil data user
$user_data = $db->select(
    "SELECT * FROM users WHERE id = ?",
    [$user_id]
);

if (empty($user_data)) {
    header('Location: user.php?error=not_found');
    exit;
}

$user = $user_data[0];

// Ambil statistik penggunaan user dalam jadwal
$usage_stats = $db->select("
    SELECT 
        COUNT(DISTINCT jp.id) as total_jadwal,
        COUNT(DISTINCT CASE WHEN jk.tanggal_kunjungan >= CURDATE() THEN jp.id END) as jadwal_mendatang,
        COUNT(DISTINCT CASE WHEN jk.tanggal_kunjungan < CURDATE() THEN jp.id END) as jadwal_selesai,
        COUNT(DISTINCT CASE WHEN jk.tanggal_kunjungan = CURDATE() THEN jp.id END) as jadwal_hari_ini
    FROM jadwal_personal jp
    LEFT JOIN jadwal_kunjungan jk ON jp.jadwal_id = jk.id
    WHERE jp.user_id = ?
", [$user_id]);

$stats = $usage_stats[0] ?? [
    'total_jadwal' => 0,
    'jadwal_mendatang' => 0,
    'jadwal_selesai' => 0,
    'jadwal_hari_ini' => 0
];

// Ambil riwayat jadwal terbaru (10 terakhir)
$recent_schedules = $db->select("
    SELECT 
        jp.*,
        jk.jenis_kunjungan,
        jk.catatan_kunjungan as jadwal_deskripsi,
        jk.tanggal_kunjungan as tanggal_jadwal,
        jk.waktu_mulai,
        jk.waktu_selesai,
        jk.status as status_jadwal,
        d.nama_desa
    FROM jadwal_personal jp
    LEFT JOIN jadwal_kunjungan jk ON jp.jadwal_id = jk.id
    LEFT JOIN desa d ON jk.desa_id = d.id
    WHERE jp.user_id = ?
    ORDER BY jk.tanggal_kunjungan DESC, jk.waktu_mulai DESC
    LIMIT 10
", [$user_id]);

// Helper functions
function getRoleBadge($role) {
    $badges = [
        'admin' => 'danger',
        'akunting' => 'success',
    'supervisor' => 'warning',
    'teknisi' => 'info',
    'programmer' => 'secondary'
    ];
    return $badges[$role] ?? 'secondary';
}

function getRoleText($role) {
    $roles = [
        'admin' => 'Administrator',
        'akunting' => 'Akunting',
    'supervisor' => 'Supervisor',
    'teknisi' => 'Teknisi',
    'programmer' => 'Programmer'
    ];
    return $roles[$role] ?? ucfirst($role);
}

function getStatusBadge($status) {
    return $status === 'aktif' ? 'success' : 'secondary';
}

function getStatusText($status) {
    return $status === 'aktif' ? 'Aktif' : 'Tidak Aktif';
}

function formatTanggal($date) {
    return $date ? date('d/m/Y H:i', strtotime($date)) : 'Belum pernah';
}

function formatTanggalSaja($date) {
    return $date ? date('d/m/Y', strtotime($date)) : '-';
}

function formatWaktu($time) {
    return $time ? date('H:i', strtotime($time)) : '-';
}

function getJadwalStatusBadge($status) {
    $badges = [
        'draft' => 'secondary',
        'aktif' => 'primary',
        'selesai' => 'success',
        'dibatalkan' => 'danger'
    ];
    return $badges[$status] ?? 'secondary';
}

function getJadwalStatusText($status) {
    $statuses = [
        'draft' => 'Draft',
        'aktif' => 'Aktif',
        'selesai' => 'Selesai',
        'dibatalkan' => 'Dibatalkan'
    ];
    return $statuses[$status] ?? ucfirst($status);
}

$page_title = 'Detail User - ' . $user['nama_lengkap'];
require_once 'layouts/header.php';
?>

<!-- Page Header -->
<div class="bg-white shadow-sm border-b border-gray-200 mb-6">
    <div class="px-6 py-4">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 flex items-center">
                    <i class="fa fa-user text-primary-500 mr-3"></i>
                    Detail User
                </h1>
                <p class="text-gray-600 mt-1">Informasi lengkap pengguna sistem</p>
            </div>
            <nav class="flex" aria-label="Breadcrumb">
                <ol class="flex items-center space-x-2 text-sm">
                    <li><a href="index.php" class="text-primary-600 hover:text-primary-700">Dashboard</a></li>
                    <li class="text-gray-400">/</li>
                    <li><a href="user.php" class="text-primary-600 hover:text-primary-700">Manajemen User</a></li>
                    <li class="text-gray-400">/</li>
                    <li class="text-gray-600">Detail User</li>
                </ol>
            </nav>
        </div>
    </div>
</div>

<!-- Main Content -->
<div class="max-w-7xl ml-0 px-4 sm:px-6 lg:px-8 py-8 overflow-hidden">
    <!-- Action Buttons -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
        <div class="flex flex-wrap gap-3">
            <a href="user-edit.php?id=<?php echo $user['id']; ?>" class="inline-flex items-center px-4 py-2 bg-yellow-600 text-white rounded-lg hover:bg-yellow-700 transition-colors">
                <i class="fas fa-edit mr-2"></i>Edit User
            </a>
            <a href="user.php" class="inline-flex items-center px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors">
                <i class="fas fa-arrow-left mr-2"></i>Kembali ke Daftar
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- User Information -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-6">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h6 class="text-lg font-semibold text-primary-600">Informasi User</h6>
                </div>
                <div class="p-6 text-center">
                    <div class="avatar-circle-large mb-3 mx-auto">
                        <i class="fas fa-user fa-3x"></i>
                    </div>
                    <h5 class="text-xl font-semibold text-gray-900 mb-1"><?php echo htmlspecialchars($user['nama_lengkap']); ?></h5>
                    <p class="text-gray-500 mb-3"><?php echo htmlspecialchars($user['email']); ?></p>
                    <div class="mb-4">
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-<?php echo getRoleBadge($user['role']) === 'danger' ? 'red' : (getRoleBadge($user['role']) === 'success' ? 'green' : (getRoleBadge($user['role']) === 'warning' ? 'yellow' : (getRoleBadge($user['role']) === 'info' ? 'blue' : 'gray'))); ?>-100 text-<?php echo getRoleBadge($user['role']) === 'danger' ? 'red' : (getRoleBadge($user['role']) === 'success' ? 'green' : (getRoleBadge($user['role']) === 'warning' ? 'yellow' : (getRoleBadge($user['role']) === 'info' ? 'blue' : 'gray'))); ?>-800">
                            <?php echo getRoleText($user['role']); ?>
                        </span>
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium ml-2 bg-<?php echo getStatusBadge($user['status']) === 'success' ? 'green' : 'gray'; ?>-100 text-<?php echo getStatusBadge($user['status']) === 'success' ? 'green' : 'gray'; ?>-800">
                            <?php echo getStatusText($user['status']); ?>
                        </span>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4 text-center">
                        <div class="border-r border-gray-200">
                            <div class="text-2xl font-bold text-blue-600 mb-1"><?php echo number_format($stats['total_jadwal']); ?></div>
                            <div class="text-sm text-gray-500">Total Jadwal</div>
                        </div>
                        <div>
                            <div class="text-2xl font-bold text-green-600 mb-1"><?php echo number_format($stats['jadwal_hari_ini']); ?></div>
                            <div class="text-sm text-gray-500">Jadwal Hari Ini</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Contact Information -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-6">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h6 class="text-lg font-semibold text-primary-600">Informasi Kontak</h6>
                </div>
                <div class="p-6">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-500 mb-1">Email</label>
                        <div class="flex items-center">
                            <i class="fas fa-envelope text-gray-400 mr-3"></i>
                            <span class="text-gray-900"><?php echo htmlspecialchars($user['email']); ?></span>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-500 mb-1">Username</label>
                        <div class="flex items-center">
                            <i class="fas fa-user text-gray-400 mr-3"></i>
                            <span class="text-gray-900"><?php echo htmlspecialchars($user['username']); ?></span>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-500 mb-1">Tanggal Bergabung</label>
                        <div class="flex items-center">
                            <i class="fas fa-calendar text-gray-400 mr-3"></i>
                            <span class="text-gray-900"><?php echo formatTanggal($user['created_at']); ?></span>
                        </div>
                    </div>
                    
                    <div class="mb-0">
                        <label class="block text-sm font-medium text-gray-500 mb-1">Login Terakhir</label>
                        <div class="flex items-center">
                            <i class="fas fa-clock text-gray-400 mr-3"></i>
                            <span class="text-gray-900"><?php echo formatTanggal($user['last_login']); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Statistics and Activity -->
        <div class="lg:col-span-2">
            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <div class="flex items-center">
                        <div class="p-3 bg-blue-100 rounded-lg">
                            <i class="fa fa-calendar text-blue-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-2xl font-bold text-gray-900"><?php echo number_format($stats['total_jadwal']); ?></p>
                            <p class="text-gray-600 text-sm">Total Jadwal</p>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <div class="flex items-center">
                        <div class="p-3 bg-yellow-100 rounded-lg">
                            <i class="fa fa-clock text-yellow-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-2xl font-bold text-gray-900"><?php echo number_format($stats['jadwal_mendatang']); ?></p>
                            <p class="text-gray-600 text-sm">Jadwal Mendatang</p>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <div class="flex items-center">
                        <div class="p-3 bg-green-100 rounded-lg">
                            <i class="fa fa-check-circle text-green-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-2xl font-bold text-gray-900"><?php echo number_format($stats['jadwal_selesai']); ?></p>
                            <p class="text-gray-600 text-sm">Jadwal Selesai</p>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <div class="flex items-center">
                        <div class="p-3 bg-purple-100 rounded-lg">
                            <i class="fa fa-calendar-day text-purple-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-2xl font-bold text-gray-900"><?php echo number_format($stats['jadwal_hari_ini']); ?></p>
                            <p class="text-gray-600 text-sm">Jadwal Hari Ini</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Recent Activity -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                    <h6 class="text-lg font-semibold text-primary-600">Riwayat Jadwal Terbaru</h6>
                    <span class="text-gray-500 text-sm"><?php echo count($recent_schedules); ?> dari <?php echo $stats['total_jadwal']; ?> jadwal</span>
                </div>
                <div class="p-6">
                    <?php if (empty($recent_schedules)): ?>
                    <div class="text-center py-8">
                        <i class="fas fa-calendar-times text-4xl text-gray-300 mb-4"></i>
                        <p class="text-gray-500">User ini belum memiliki jadwal.</p>
                    </div>
                    <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Jenis Kunjungan</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Desa</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Waktu</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($recent_schedules as $schedule): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div>
                                            <div class="font-semibold text-gray-900"><?php echo ucfirst(str_replace('_', ' ', $schedule['jenis_kunjungan'] ?? 'Tidak ada jenis')); ?></div>
                                            <?php if ($schedule['jadwal_deskripsi']): ?>
                                            <div class="text-gray-500 text-sm"><?php echo htmlspecialchars(substr($schedule['jadwal_deskripsi'], 0, 50)) . (strlen($schedule['jadwal_deskripsi']) > 50 ? '...' : ''); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="font-semibold text-gray-900"><?php echo htmlspecialchars($schedule['nama_desa'] ?? 'Tidak ada desa'); ?></span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="text-gray-900"><?php echo formatTanggalSaja($schedule['tanggal_jadwal']); ?></span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="text-gray-900">
                                            <?php echo formatWaktu($schedule['waktu_mulai']); ?> - 
                                            <?php echo formatWaktu($schedule['waktu_selesai']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-<?php echo getJadwalStatusBadge($schedule['status_jadwal']) === 'secondary' ? 'gray' : (getJadwalStatusBadge($schedule['status_jadwal']) === 'primary' ? 'blue' : (getJadwalStatusBadge($schedule['status_jadwal']) === 'success' ? 'green' : 'red')); ?>-100 text-<?php echo getJadwalStatusBadge($schedule['status_jadwal']) === 'secondary' ? 'gray' : (getJadwalStatusBadge($schedule['status_jadwal']) === 'primary' ? 'blue' : (getJadwalStatusBadge($schedule['status_jadwal']) === 'success' ? 'green' : 'red')); ?>-800">
                                            <?php echo getJadwalStatusText($schedule['status_jadwal']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <a href="jadwal-view.php?id=<?php echo $schedule['jadwal_id']; ?>" 
                                           class="inline-flex items-center px-2 py-1 border border-blue-600 text-blue-600 text-xs font-medium rounded hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-blue-500" title="Lihat Detail">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <?php if ($stats['total_jadwal'] > 10): ?>
                    <div class="text-center mt-6">
                        <a href="jadwal.php?user_id=<?php echo $user['id']; ?>" class="inline-flex items-center px-4 py-2 border border-blue-600 text-blue-600 text-sm font-medium rounded-md hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <i class="fas fa-list mr-2"></i>Lihat Semua Jadwal
                        </a>
                    </div>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    </div>
</div>

<style>
.avatar-circle-large {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    background: linear-gradient(45deg, #3b82f6, #1d4ed8);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
}

.text-primary-500 {
    color: #3b82f6;
}

.text-primary-600 {
    color: #2563eb;
}

.text-primary-700 {
    color: #1d4ed8;
}
</style>

<?php require_once 'layouts/footer.php'; ?>