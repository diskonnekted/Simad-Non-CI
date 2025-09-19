<?php
require_once 'config/database.php';
require_once 'config/auth.php';

// Cek autentikasi dan role
if (!AuthStatic::isLoggedIn()) {
    header('Location: login.php');
    exit;
}

if (!AuthStatic::hasRole(['admin', 'supervisor', 'operator', 'programmer'])) {
    header('Location: index.php?error=access_denied');
    exit;
}

$user = AuthStatic::getCurrentUser();
$db = getDatabase();
/** @var Database $db */

// Get website ID
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: website-desa.php?error=invalid_id');
    exit;
}

// Get website data with desa information
$website = $db->select(
    "SELECT wd.*, d.nama_desa, d.kecamatan, d.kabupaten, d.provinsi, d.kode_pos, 
            d.nama_kepala_desa, d.no_hp_kepala_desa, d.nama_sekdes, d.no_hp_sekdes, 
            d.nama_admin_it, d.no_hp_admin_it, d.email_desa, d.alamat
     FROM website_desa wd 
     LEFT JOIN desa d ON wd.desa_id = d.id 
     WHERE wd.id = ?", 
    [$id]
);

if (empty($website)) {
    header('Location: website-desa.php?error=not_found');
    exit;
}

$website = $website[0];
?>
<?php
$page_title = 'Detail Website Desa';
include 'layouts/header.php';
?>

    <!-- Page Header -->
    <div class="bg-white shadow-sm border-b border-gray-200 mb-6">
        <div class="px-6 py-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center">
                    <a href="website-desa.php" class="text-gray-600 hover:text-gray-800 mr-4">
                        <i class="fas fa-arrow-left text-lg"></i>
                    </a>
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">Detail Website Desa</h1>
                        <p class="text-sm text-gray-600 mt-1">Informasi lengkap website desa</p>
                    </div>
                </div>
                <div class="mt-4 sm:mt-0 flex space-x-2">
                    <a href="<?= htmlspecialchars($website['website_url']) ?>" target="_blank"
                       class="inline-flex items-center px-4 py-2 border border-transparent rounded-lg text-sm font-medium text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition-colors">
                        <i class="fas fa-external-link-alt mr-2"></i>
                        Kunjungi Website
                    </a>
                    <?php if (AuthStatic::hasRole(['admin', 'supervisor'])): ?>
                    <a href="website-desa-edit.php?id=<?= $website['id'] ?>"
                       class="inline-flex items-center px-4 py-2 border border-transparent rounded-lg text-sm font-medium text-white bg-yellow-600 hover:bg-yellow-700 focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:ring-offset-2 transition-colors">
                        <i class="fas fa-edit mr-2"></i>
                        Edit
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Container -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Website Information -->
                <div class="lg:col-span-2">
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h2 class="text-lg font-semibold text-gray-900 flex items-center">
                                <i class="fas fa-globe mr-2 text-primary-500"></i>
                                Informasi Website
                            </h2>
                        </div>
                        
                        <div class="p-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Website URL -->
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 mb-2">URL Website</label>
                                    <div class="flex items-center space-x-2">
                                        <input type="text" value="<?= htmlspecialchars($website['website_url']) ?>" readonly
                                               class="flex-1 px-3 py-2 border border-gray-300 rounded-md bg-gray-50 text-gray-900">
                                        <a href="<?= htmlspecialchars($website['website_url']) ?>" target="_blank"
                                           class="px-3 py-2 bg-primary-600 hover:bg-primary-700 text-white rounded-md transition-colors duration-200">
                                            <i class="fas fa-external-link-alt"></i>
                                        </a>
                                    </div>
                                </div>
                                
                                <!-- Status Database -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Status Database</label>
                                    <span class="inline-flex items-center px-3 py-2 rounded-full text-sm font-medium <?= $website['has_database'] === 'ada' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                        <i class="fas <?= $website['has_database'] === 'ada' ? 'fa-check-circle' : 'fa-times-circle' ?> mr-2"></i>
                                        <?= $website['has_database'] === 'ada' ? 'Ada' : 'Tidak Ada' ?>
                                    </span>
                                </div>
                                
                                <!-- Status Berita -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Status Berita</label>
                                    <span class="inline-flex items-center px-3 py-2 rounded-full text-sm font-medium <?= $website['news_active'] === 'aktif' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' ?>">
                                        <i class="fas <?= $website['news_active'] === 'aktif' ? 'fa-check-circle' : 'fa-times-circle' ?> mr-2"></i>
                                        <?= $website['news_active'] === 'aktif' ? 'Aktif' : 'Tidak Aktif' ?>
                                    </span>
                                </div>
                                
                                <!-- Developer -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Developer</label>
                                    <span class="inline-flex items-center px-3 py-2 rounded-full text-sm font-medium <?= $website['developer_type'] === 'clasnet' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800' ?>">
                                        <i class="fas fa-code mr-2"></i>
                                        <?= $website['developer_type'] === 'clasnet' ? 'Clasnet' : 'Bukan Clasnet' ?>
                                    </span>
                                </div>
                                
                                <!-- Sinkron OpenData -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Sinkron OpenData</label>
                                    <?php 
                                    $sync_colors = [
                                        'sinkron' => 'bg-green-100 text-green-800',
                                        'proses' => 'bg-yellow-100 text-yellow-800',
                                        'tidak_sinkron' => 'bg-red-100 text-red-800'
                                    ];
                                    $sync_icons = [
                                        'sinkron' => 'fa-check-circle',
                                        'proses' => 'fa-clock',
                                        'tidak_sinkron' => 'fa-times-circle'
                                    ];
                                    $sync_labels = [
                                        'sinkron' => 'Sinkron',
                                        'proses' => 'Proses',
                                        'tidak_sinkron' => 'Tidak Sinkron'
                                    ];
                                    ?>
                                    <span class="inline-flex items-center px-3 py-2 rounded-full text-sm font-medium <?= $sync_colors[$website['opendata_sync']] ?>">
                                        <i class="fas <?= $sync_icons[$website['opendata_sync']] ?> mr-2"></i>
                                        <?= $sync_labels[$website['opendata_sync']] ?>
                                    </span>
                                </div>
                                
                                <!-- Keterangan -->
                                <?php if (!empty($website['keterangan'])): ?>
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Keterangan</label>
                                    <div class="px-3 py-2 border border-gray-300 rounded-md bg-gray-50">
                                        <p class="text-gray-900 whitespace-pre-wrap"><?= htmlspecialchars($website['keterangan']) ?></p>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <!-- Timestamps -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Dibuat</label>
                                    <p class="text-gray-900">
                                        <?= date('d/m/Y H:i', strtotime($website['created_at'])) ?>
                                    </p>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Terakhir Diupdate</label>
                                    <p class="text-gray-900">
                                        <?= date('d/m/Y H:i', strtotime($website['updated_at'])) ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Desa Information -->
                <div>
                    <?php if ($website['desa_id']): ?>
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h2 class="text-lg font-semibold text-gray-900 flex items-center">
                                <i class="fas fa-map-marker-alt mr-2 text-primary-500"></i>
                                Informasi Desa
                            </h2>
                        </div>
                        
                        <div class="p-6">
                            <div class="space-y-4">
                                <!-- Nama Desa -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Nama Desa</label>
                                    <p class="text-gray-900 font-medium"><?= htmlspecialchars($website['nama_desa']) ?></p>
                                </div>
                                
                                <!-- Alamat -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Alamat</label>
                                    <p class="text-gray-900">
                                        <?= htmlspecialchars($website['kecamatan']) ?><br>
                                        <?= htmlspecialchars($website['kabupaten']) ?><br>
                                        <?= htmlspecialchars($website['provinsi']) ?>
                                        <?php if ($website['kode_pos']): ?>
                                        <br><?= htmlspecialchars($website['kode_pos']) ?>
                                        <?php endif; ?>
                                    </p>
                                </div>
                                
                                <!-- Kepala Desa -->
                                <?php if ($website['nama_kepala_desa']): ?>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Kepala Desa</label>
                                    <p class="text-gray-900 font-medium"><?= htmlspecialchars($website['nama_kepala_desa']) ?></p>
                                    <?php if ($website['no_hp_kepala_desa']): ?>
                                    <p class="text-sm text-gray-600">
                                        <i class="fas fa-phone mr-1"></i>
                                        <?= htmlspecialchars($website['no_hp_kepala_desa']) ?>
                                    </p>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                                
                                <!-- Kontak -->
                                <?php if ($website['nama_sekdes'] || $website['nama_admin_it'] || $website['email_desa']): ?>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Kontak</label>
                                    <div class="space-y-2">
                                        <?php if ($website['nama_sekdes']): ?>
                                        <div class="flex items-center text-sm text-gray-600">
                                            <i class="fas fa-user mr-2 w-4"></i>
                                            <?= htmlspecialchars($website['nama_sekdes']) ?> (Sekdes)
                                            <?php if ($website['no_hp_sekdes']): ?>
                                            <span class="ml-2 text-primary-600"><?= htmlspecialchars($website['no_hp_sekdes']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($website['nama_admin_it']): ?>
                                        <div class="flex items-center text-sm text-gray-600">
                                            <i class="fas fa-laptop mr-2 w-4"></i>
                                            <?= htmlspecialchars($website['nama_admin_it']) ?> (Admin IT)
                                            <?php if ($website['no_hp_admin_it']): ?>
                                            <span class="ml-2 text-primary-600"><?= htmlspecialchars($website['no_hp_admin_it']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($website['email_desa']): ?>
                                        <div class="flex items-center text-sm text-gray-600">
                                            <i class="fas fa-envelope mr-2 w-4"></i>
                                            <a href="mailto:<?= htmlspecialchars($website['email_desa']) ?>" class="text-primary-600 hover:text-primary-800">
                                                <?= htmlspecialchars($website['email_desa']) ?>
                                            </a>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <!-- Link ke Detail Desa -->
                                <div class="pt-4 border-t border-gray-200">
                                    <a href="desa-view.php?id=<?= $website['desa_id'] ?>" 
                                       class="inline-flex items-center text-primary-600 hover:text-primary-800 text-sm font-medium">
                                        <i class="fas fa-eye mr-2"></i>
                                        Lihat Detail Desa
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h2 class="text-lg font-semibold text-gray-900 flex items-center">
                                <i class="fas fa-info-circle mr-2 text-gray-400"></i>
                                Informasi Desa
                            </h2>
                        </div>
                        
                        <div class="p-6 text-center">
                            <i class="fas fa-map-marker-alt text-gray-300 text-3xl mb-3"></i>
                            <p class="text-gray-600">Website ini belum terkait dengan data desa yang terdaftar</p>
                            <?php if (AuthStatic::hasRole(['admin', 'supervisor'])): ?>
                            <a href="website-desa-edit.php?id=<?= $website['id'] ?>" 
                               class="inline-flex items-center mt-3 text-primary-600 hover:text-primary-800 text-sm font-medium">
                                <i class="fas fa-link mr-2"></i>
                                Hubungkan dengan Desa
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

<script>
// Sidebar toggle
document.getElementById('sidebar-toggle')?.addEventListener('click', function() {
    const sidebar = document.getElementById('sidebar');
    const backdrop = document.getElementById('sidebar-backdrop');
    sidebar.classList.remove('hidden');
    backdrop.classList.remove('hidden');
});

// Close sidebar
document.getElementById('sidebar-close')?.addEventListener('click', function() {
    const sidebar = document.getElementById('sidebar');
    const backdrop = document.getElementById('sidebar-backdrop');
    sidebar.classList.add('hidden');
    backdrop.classList.add('hidden');
});

// Close sidebar when clicking backdrop
document.getElementById('sidebar-backdrop')?.addEventListener('click', function() {
    const sidebar = document.getElementById('sidebar');
    const backdrop = document.getElementById('sidebar-backdrop');
    sidebar.classList.add('hidden');
    backdrop.classList.add('hidden');
});

// Profile dropdown toggle
document.getElementById('user-menu-button')?.addEventListener('click', function() {
    const dropdown = document.getElementById('dropdown-user');
    dropdown.classList.toggle('hidden');
});

// Close dropdown when clicking outside
document.addEventListener('click', function(event) {
    const button = document.getElementById('user-menu-button');
    const dropdown = document.getElementById('dropdown-user');
    
    if (!button?.contains(event.target) && !dropdown?.contains(event.target)) {
        dropdown?.classList.add('hidden');
    }
});
</script>

<?php require_once 'layouts/footer.php'; ?>