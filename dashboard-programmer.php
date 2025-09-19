<?php
require_once 'config/database.php';
require_once 'config/auth.php';

// Cek apakah user sudah login dan memiliki role programmer
if (!AuthStatic::isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$user = AuthStatic::getCurrentUser();

// Pastikan hanya programmer yang bisa akses
if (!AuthStatic::hasRole(['programmer'])) {
    header('Location: index.php');
    exit;
}

$db = getDatabase();
/** @var Database $db */

// Ambil data statistik khusus untuk programmer
try {
    // Total website maintenance yang perlu ditangani
    $totalMaintenance = $db->select("
        SELECT COUNT(*) as total 
        FROM website_maintenance 
        WHERE status IN ('maintenance', 'pending_verification')
    ")[0]['total'] ?? 0;
    
    // Website maintenance yang sedang dikerjakan programmer ini
    $myMaintenance = $db->select("
        SELECT COUNT(*) as total 
        FROM website_maintenance 
        WHERE programmer_id = ? AND status = 'maintenance'
    ", [$user['id']])[0]['total'] ?? 0;
    
    // Jadwal kunjungan hari ini yang melibatkan maintenance/support
    $jadwalHariIni = $db->select("
        SELECT COUNT(*) as total 
        FROM jadwal_kunjungan 
        WHERE DATE(tanggal_kunjungan) = CURDATE() 
        AND status = 'dijadwalkan' 
        AND jenis_kunjungan IN ('maintenance', 'support')
    ")[0]['total'] ?? 0;
    
    // Website desa yang memerlukan update/maintenance (berdasarkan deadline)
    $websiteNeedUpdate = $db->select("
        SELECT COUNT(*) as total 
        FROM website_maintenance 
        WHERE deadline <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) 
        AND status = 'maintenance'
    ")[0]['total'] ?? 0;
    
    // Daftar maintenance yang ditugaskan ke programmer ini
    $myMaintenanceList = $db->select("
        SELECT wm.*, d.nama_desa as desa_nama,
               u1.nama_lengkap as penanggung_jawab_nama,
               u2.nama_lengkap as programmer_nama
        FROM website_maintenance wm
        LEFT JOIN desa d ON wm.desa_id = d.id
        LEFT JOIN users u1 ON wm.penanggung_jawab_id = u1.id
        LEFT JOIN users u2 ON wm.programmer_id = u2.id
        WHERE wm.programmer_id = ? AND wm.status IN ('maintenance', 'pending_verification')
        ORDER BY wm.deadline ASC, wm.created_at ASC
        LIMIT 10
    ", [$user['id']]);
    
    // Maintenance urgent yang belum ditugaskan (deadline dalam 3 hari)
    $urgentMaintenance = $db->select("
        SELECT wm.*, d.nama_desa as desa_nama,
               u1.nama_lengkap as penanggung_jawab_nama
        FROM website_maintenance wm
        LEFT JOIN desa d ON wm.desa_id = d.id
        LEFT JOIN users u1 ON wm.penanggung_jawab_id = u1.id
        WHERE wm.programmer_id IS NULL 
        AND wm.deadline <= DATE_ADD(CURDATE(), INTERVAL 3 DAY) 
        AND wm.status = 'maintenance'
        ORDER BY wm.deadline ASC, wm.created_at ASC
        LIMIT 5
    ");
    
    // Jadwal kunjungan yang relevan untuk programmer
    $relevantSchedules = $db->select("
        SELECT jk.*, d.nama_desa, d.nama_kepala_desa
        FROM jadwal_kunjungan jk
        JOIN desa d ON jk.desa_id = d.id
        WHERE jk.jenis_kunjungan IN ('maintenance', 'support', 'training')
        AND jk.status = 'dijadwalkan'
        AND jk.tanggal_kunjungan >= CURDATE()
        ORDER BY jk.tanggal_kunjungan ASC
        LIMIT 8
    ");
    
    // Statistik maintenance per status
    $maintenanceStats = $db->select("
        SELECT 
            status,
            COUNT(*) as count
        FROM website_maintenance
        GROUP BY status
    ");
    
    // Konversi ke array untuk mudah diakses
    $statsArray = [];
    foreach ($maintenanceStats as $stat) {
        $statsArray[$stat['status']] = $stat['count'];
    }
    
    // Notifikasi khusus programmer
    $notifications = [];
    
    // Maintenance urgent
    if (count($urgentMaintenance) > 0) {
        $notifications[] = [
            'type' => 'error',
            'icon' => 'fa-exclamation-triangle',
            'message' => count($urgentMaintenance) . ' maintenance urgent belum ditugaskan',
            'link' => 'website-maintenance.php'
        ];
    }
    
    // Maintenance yang sudah lama dalam status maintenance
    $oldMaintenance = $db->select("
        SELECT COUNT(*) as total 
        FROM website_maintenance 
        WHERE status = 'maintenance' AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)
    ")[0]['total'] ?? 0;
    
    if ($oldMaintenance > 0) {
        $notifications[] = [
            'type' => 'warning',
            'icon' => 'fa-clock',
            'message' => "$oldMaintenance maintenance berjalan lebih dari 7 hari",
            'link' => 'website-maintenance.php?status=maintenance'
        ];
    }
    
    // Maintenance yang mendekati deadline
    $nearDeadline = $db->select("
        SELECT COUNT(*) as total 
        FROM website_maintenance 
        WHERE status = 'maintenance' AND deadline <= DATE_ADD(CURDATE(), INTERVAL 2 DAY)
    ")[0]['total'] ?? 0;
    
    if ($nearDeadline > 0) {
        $notifications[] = [
            'type' => 'error',
            'icon' => 'fa-calendar-times',
            'message' => "$nearDeadline maintenance mendekati deadline",
            'link' => 'website-maintenance.php?deadline=urgent'
        ];
    }
    
} catch (Exception $e) {
    // Jika ada error, set default values
    $totalMaintenance = 0;
    $myMaintenance = 0;
    $jadwalHariIni = 0;
    $websiteNeedUpdate = 0;
    $myMaintenanceList = [];
    $urgentMaintenance = [];
    $relevantSchedules = [];
    $notifications = [];
    $statsArray = [];
}

// Helper functions
function getPriorityBadge($deadline) {
    $today = new DateTime();
    $deadlineDate = new DateTime($deadline);
    $diff = $today->diff($deadlineDate)->days;
    
    if ($deadlineDate < $today) {
        return 'bg-red-100 text-red-800'; // Overdue
    } elseif ($diff <= 2) {
        return 'bg-red-100 text-red-800'; // Urgent
    } elseif ($diff <= 7) {
        return 'bg-yellow-100 text-yellow-800'; // Soon
    } else {
        return 'bg-green-100 text-green-800'; // Normal
    }
}

function getStatusBadge($status) {
    switch ($status) {
        case 'maintenance':
            return 'bg-blue-100 text-blue-800';
        case 'pending_verification':
            return 'bg-yellow-100 text-yellow-800';
        case 'completed':
            return 'bg-green-100 text-green-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
}

function getStatusText($status) {
    switch ($status) {
        case 'maintenance':
            return 'Maintenance';
        case 'pending_verification':
            return 'Menunggu Verifikasi';
        case 'completed':
            return 'Selesai';
        default:
            return ucfirst($status);
    }
}

$page_title = 'Dashboard Programmer';
require_once 'layouts/header.php';
?>

<style>
/* Import Google Fonts */
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Poppins:wght@300;400;500;600;700;800&display=swap');

body {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    background-color: #f8fafc;
}

.dashboard-title {
    font-family: 'Poppins', sans-serif;
    font-weight: 700;
    font-size: 1.75rem;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.section-title {
    font-family: 'Poppins', sans-serif;
    font-weight: 600;
    font-size: 1.25rem;
    color: #1f2937;
}

.stats-label {
    font-family: 'Inter', sans-serif;
    font-weight: 500;
    color: #6b7280;
    font-size: 0.875rem;
    text-transform: uppercase;
    font-size: 0.8rem;
}

.stats-value {
    font-family: 'Poppins', sans-serif;
    font-weight: 700;
    font-size: 2rem;
    color: #111827;
}

.card-hover {
    transition: all 0.3s ease;
}

.card-hover:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
}

.priority-indicator {
    width: 4px;
    height: 100%;
    position: absolute;
    left: 0;
    top: 0;
    border-radius: 0 0 0 0.5rem;
}

.priority-high {
    background-color: #ef4444;
}

.priority-medium {
    background-color: #f59e0b;
}

.priority-low {
    background-color: #10b981;
}

.notification-item {
    transition: all 0.2s ease;
}

.notification-item:hover {
    background-color: #f3f4f6;
}

/* Layout optimizations for large screens */
@media (min-width: 1920px) {
    .max-w-7xl {
        max-width: 1400px;
    }
}

@media (min-width: 2560px) {
    .max-w-7xl {
        max-width: 1600px;
    }
}

/* Compact grid for better space utilization */
.compact-grid {
    display: grid;
    gap: 1rem;
}

@media (min-width: 1024px) {
    .compact-grid {
        gap: 1.5rem;
    }
}

/* Layout adjustments for content alignment */
.content-left-align {
    margin-right: auto;
}

@media (min-width: 1024px) {
    .main-content-grid {
        grid-template-columns: 1fr 320px;
        gap: 1rem;
        align-items: start;
    }
    
    .content-card {
        max-width: none;
        margin-right: 0;
    }
    
    .sidebar-fixed {
        position: sticky;
        top: 1rem;
    }
}
</style>

<!-- Page Header -->
<div class="max-w-7xl ml-0 px-4 sm:px-6 lg:px-8 mb-6">
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="dashboard-title">Dashboard Programmer</h1>
                <p class="text-gray-600 mt-1">Selamat datang, <?= htmlspecialchars($user['nama_lengkap']) ?>! Kelola tugas maintenance dan development Anda.</p>
            </div>
            <div class="flex space-x-3">
                <button onclick="location.reload()" class="w-12 h-12 bg-blue-100 hover:bg-blue-200 rounded-lg flex items-center justify-center transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                    <i class="fas fa-sync-alt text-blue-600"></i>
                </button>
                <a href="website-maintenance-add.php" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg flex items-center space-x-2 transition-colors">
                    <i class="fas fa-plus"></i>
                    <span>Tambah Maintenance</span>
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Statistics Cards -->
<div class="max-w-7xl ml-0 px-4 sm:px-6 lg:px-8 mb-6">
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Total Maintenance -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 card-hover">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-tools text-blue-600 text-lg"></i>
                    </div>
                </div>
                <div class="ml-4">
                    <p class="stats-label">Total Maintenance</p>
                    <p class="stats-value"><?= number_format($totalMaintenance) ?></p>
                    <p class="text-sm text-gray-500">Pending & Progress</p>
                </div>
            </div>
        </div>

        <!-- My Tasks -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 card-hover">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-user-cog text-green-600 text-lg"></i>
                    </div>
                </div>
                <div class="ml-4">
                    <p class="stats-label">Tugas Saya</p>
                    <p class="stats-value"><?= number_format($myMaintenance) ?></p>
                    <p class="text-sm text-green-600">Sedang Dikerjakan</p>
                </div>
            </div>
        </div>

        <!-- Today's Schedule -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 card-hover">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-calendar-day text-purple-600 text-lg"></i>
                    </div>
                </div>
                <div class="ml-4">
                    <p class="stats-label">Jadwal Hari Ini</p>
                    <p class="stats-value"><?= number_format($jadwalHariIni) ?></p>
                    <p class="text-sm text-gray-500">Maintenance & Support</p>
                </div>
            </div>
        </div>

        <!-- Websites Need Update -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 card-hover">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-exclamation-triangle text-red-600 text-lg"></i>
                    </div>
                </div>
                <div class="ml-4">
                    <p class="stats-label">Perlu Update</p>
                    <p class="stats-value"><?= number_format($websiteNeedUpdate) ?></p>
                    <p class="text-sm text-red-600">Deadline < 7 Hari</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Main Content Grid -->
<div class="max-w-7xl ml-0 px-4 sm:px-6 lg:px-8">
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-4">
        <!-- My Maintenance Tasks -->
        <div class="lg:col-span-3">
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="section-title">Tugas Maintenance Saya</h3>
                    <a href="website-maintenance.php" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                        Lihat Semua
                    </a>
                </div>
                
                <?php if (empty($myMaintenanceList)): ?>
                    <div class="text-center py-8">
                        <i class="fas fa-clipboard-check text-gray-400 text-3xl mb-3"></i>
                        <p class="text-gray-500">Tidak ada tugas maintenance yang sedang dikerjakan</p>
                        <a href="website-maintenance.php" class="text-blue-600 hover:text-blue-800 text-sm mt-2 inline-block">
                            Lihat tugas yang tersedia
                        </a>
                    </div>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($myMaintenanceList as $maintenance): ?>
                        <div class="relative border border-gray-200 rounded-lg p-4 hover:bg-gray-50 transition-colors">
                            <?php 
                            $today = new DateTime();
                            $deadlineDate = new DateTime($maintenance['deadline']);
                            $diff = $today->diff($deadlineDate)->days;
                            $priorityClass = 'priority-low';
                            if ($deadlineDate < $today) {
                                $priorityClass = 'priority-high';
                            } elseif ($diff <= 2) {
                                $priorityClass = 'priority-high';
                            } elseif ($diff <= 7) {
                                $priorityClass = 'priority-medium';
                            }
                            ?>
                            <div class="priority-indicator <?= $priorityClass ?>"></div>
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <div class="flex items-center space-x-2 mb-2">
                                        <h4 class="font-semibold text-gray-900"><?= htmlspecialchars($maintenance['nama_desa']) ?></h4>
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?= getPriorityBadge($maintenance['deadline']) ?>">
                                            <?php 
                                            if ($deadlineDate < $today) {
                                                echo 'Terlambat';
                                            } elseif ($diff <= 2) {
                                                echo 'Urgent';
                                            } elseif ($diff <= 7) {
                                                echo 'Segera';
                                            } else {
                                                echo 'Normal';
                                            }
                                            ?>
                                        </span>
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?= getStatusBadge($maintenance['status']) ?>">
                                            <?= getStatusText($maintenance['status']) ?>
                                        </span>
                                    </div>
                                    <p class="text-sm text-gray-600 mb-2"><?= htmlspecialchars($maintenance['keterangan'] ?? 'Tidak ada keterangan') ?></p>
                                    <div class="flex items-center space-x-4 text-xs text-gray-500">
                                        <span><i class="fas fa-globe mr-1"></i><?= htmlspecialchars($maintenance['website_url']) ?></span>
                                        <span><i class="fas fa-calendar mr-1"></i>Deadline: <?= date('d M Y', strtotime($maintenance['deadline'])) ?></span>
                                        <span><i class="fas fa-clock mr-1"></i><?= date('d M Y', strtotime($maintenance['created_at'])) ?></span>
                                    </div>
                                </div>
                                <div class="flex space-x-2">
                                    <a href="website-maintenance-detail.php?id=<?= $maintenance['id'] ?>" class="text-blue-600 hover:text-blue-800">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="website-maintenance-edit.php?id=<?= $maintenance['id'] ?>" class="text-green-600 hover:text-green-800">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="space-y-4">
            <!-- Quick Actions -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                <h3 class="section-title mb-4">Quick Actions</h3>
                <div class="space-y-3">
                    <a href="website-maintenance.php" class="flex items-center p-3 bg-blue-50 hover:bg-blue-100 rounded-lg transition-colors">
                        <i class="fas fa-list text-blue-600 mr-3"></i>
                        <span class="text-blue-700 font-medium">Lihat Semua Maintenance</span>
                    </a>
                    <a href="website-maintenance-add.php" class="flex items-center p-3 bg-green-50 hover:bg-green-100 rounded-lg transition-colors">
                        <i class="fas fa-plus text-green-600 mr-3"></i>
                        <span class="text-green-700 font-medium">Tambah Maintenance</span>
                    </a>
                    <a href="jadwal.php" class="flex items-center p-3 bg-purple-50 hover:bg-purple-100 rounded-lg transition-colors">
                        <i class="fas fa-calendar text-purple-600 mr-3"></i>
                        <span class="text-purple-700 font-medium">Lihat Jadwal Kunjungan</span>
                    </a>
                </div>
            </div>

            <!-- Notifications -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="section-title">Notifikasi</h3>
                    <i class="fas fa-bell text-yellow-600"></i>
                </div>
                
                <?php if (empty($notifications)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-check-circle text-green-400 text-2xl mb-2"></i>
                        <p class="text-gray-500 text-sm">Semua tugas terkendali</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-3">
                        <?php foreach ($notifications as $notif): ?>
                        <div class="notification-item p-3 rounded-lg border border-gray-200">
                            <div class="flex items-start">
                                <div class="flex-shrink-0">
                                    <div class="w-8 h-8 rounded-full bg-<?= $notif['type'] === 'error' ? 'red' : ($notif['type'] === 'warning' ? 'yellow' : 'blue') ?>-100 flex items-center justify-center">
                                        <i class="fas <?= $notif['icon'] ?> text-<?= $notif['type'] === 'error' ? 'red' : ($notif['type'] === 'warning' ? 'yellow' : 'blue') ?>-600 text-sm"></i>
                                    </div>
                                </div>
                                <div class="ml-3 flex-1">
                                    <p class="text-sm text-gray-700"><?= htmlspecialchars($notif['message']) ?></p>
                                    <?php if (isset($notif['link'])): ?>
                                    <a href="<?= $notif['link'] ?>" class="text-xs text-blue-600 hover:text-blue-800 mt-1 inline-block">
                                        Lihat Detail
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Upcoming Schedules -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h3 class="section-title mb-4">Jadwal Mendatang</h3>
                
                <?php if (empty($relevantSchedules)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-calendar-times text-gray-400 text-2xl mb-2"></i>
                        <p class="text-gray-500 text-sm">Tidak ada jadwal maintenance</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-3">
                        <?php foreach (array_slice($relevantSchedules, 0, 5) as $schedule): ?>
                        <div class="p-3 border border-gray-200 rounded-lg">
                            <div class="flex items-center justify-between mb-1">
                                <span class="text-sm font-medium text-gray-900"><?= htmlspecialchars($schedule['nama_desa']) ?></span>
                                <span class="text-xs text-gray-500"><?= date('d/m', strtotime($schedule['tanggal_kunjungan'])) ?></span>
                            </div>
                            <p class="text-xs text-gray-600"><?= ucfirst($schedule['jenis_kunjungan']) ?></p>
                            <?php if (!empty($schedule['catatan_kunjungan'])): ?>
                            <p class="text-xs text-gray-500 mt-1"><?= htmlspecialchars(substr($schedule['catatan_kunjungan'], 0, 50)) ?>...</p>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <?php if (count($relevantSchedules) > 5): ?>
                    <div class="mt-3 text-center">
                        <a href="jadwal.php" class="text-blue-600 hover:text-blue-800 text-sm">
                            Lihat <?= count($relevantSchedules) - 5 ?> jadwal lainnya
                        </a>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
include 'layouts/footer.php';
?>