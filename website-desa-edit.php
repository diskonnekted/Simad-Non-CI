<?php
require_once 'config/database.php';
require_once 'config/auth.php';

// Cek autentikasi dan role
if (!AuthStatic::isLoggedIn()) {
    header('Location: login.php');
    exit;
}

if (!AuthStatic::hasRole(['admin', 'supervisor', 'programmer'])) {
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

// Get website data
$website = $db->select(
    "SELECT wd.*, d.nama_desa, d.kecamatan, d.kabupaten 
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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $desa_id = !empty($_POST['desa_id']) ? (int)$_POST['desa_id'] : null;
    $website_url = trim($_POST['website_url'] ?? '');
    $has_database = $_POST['has_database'] ?? 'tidak_ada';
    $news_active = $_POST['news_active'] ?? 'tidak_aktif';
    $developer_type = $_POST['developer_type'] ?? 'bukan_clasnet';
    $opendata_sync = $_POST['opendata_sync'] ?? 'tidak_sinkron';
    $keterangan = trim($_POST['keterangan'] ?? '');
    
    // Validasi
    $errors = [];
    
    if (empty($website_url)) {
        $errors[] = 'URL Website harus diisi';
    } elseif (!filter_var($website_url, FILTER_VALIDATE_URL)) {
        $errors[] = 'Format URL Website tidak valid';
    }
    
    if (!in_array($has_database, ['ada', 'tidak_ada'])) {
        $errors[] = 'Status database tidak valid';
    }
    
    if (!in_array($news_active, ['aktif', 'tidak_aktif'])) {
        $errors[] = 'Status berita tidak valid';
    }
    
    if (!in_array($developer_type, ['clasnet', 'bukan_clasnet'])) {
        $errors[] = 'Tipe developer tidak valid';
    }
    
    if (!in_array($opendata_sync, ['sinkron', 'proses', 'tidak_sinkron'])) {
        $errors[] = 'Status sinkron opendata tidak valid';
    }
    
    // Cek duplikasi URL (kecuali untuk record yang sedang diedit)
    if (empty($errors)) {
        $existing = $db->select("SELECT id FROM website_desa WHERE website_url = ? AND id != ?", [$website_url, $id]);
        if (!empty($existing)) {
            $errors[] = 'URL Website sudah terdaftar';
        }
    }
    
    if (empty($errors)) {
        try {
            $query = "
                UPDATE website_desa SET 
                    desa_id = ?, 
                    website_url = ?, 
                    has_database = ?, 
                    news_active = ?, 
                    developer_type = ?, 
                    opendata_sync = ?, 
                    keterangan = ?, 
                    updated_at = NOW()
                WHERE id = ?
            ";
            
            $params = [
                $desa_id,
                $website_url,
                $has_database,
                $news_active,
                $developer_type,
                $opendata_sync,
                $keterangan,
                $id
            ];
            
            $db->execute($query, $params);
            
            header('Location: website-desa.php?success=updated');
            exit;
        } catch (Exception $e) {
            $errors[] = 'Gagal menyimpan data: ' . $e->getMessage();
        }
    }
} else {
    // Pre-fill form with existing data
    $_POST = [
        'desa_id' => $website['desa_id'],
        'website_url' => $website['website_url'],
        'has_database' => $website['has_database'],
        'news_active' => $website['news_active'],
        'developer_type' => $website['developer_type'],
        'opendata_sync' => $website['opendata_sync'],
        'keterangan' => $website['keterangan']
    ];
}

// Get desa list for dropdown
$desa_list = $db->select("SELECT id, CONCAT(UPPER(SUBSTRING(nama_desa, 1, 1)), LOWER(SUBSTRING(nama_desa, 2))) as nama_desa, kecamatan, kabupaten FROM desa ORDER BY nama_desa ASC");
?>
<?php
$page_title = 'Edit Website Desa';
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
                        <h1 class="text-2xl font-bold text-gray-900">Edit Website Desa</h1>
                        <p class="text-sm text-gray-600 mt-1">Edit data website: <?= htmlspecialchars($website['website_url']) ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Container -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            
            <!-- Error Messages -->
            <?php if (!empty($errors)): ?>
            <div class="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg">
                <div class="flex items-start">
                    <i class="fas fa-exclamation-circle mr-2 mt-0.5"></i>
                    <div>
                        <h4 class="font-medium">Terdapat kesalahan:</h4>
                        <ul class="mt-2 list-disc list-inside">
                            <?php foreach ($errors as $error): ?>
                            <li><?= htmlspecialchars($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Form -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900">Informasi Website Desa</h2>
                </div>
                
                <form method="POST" class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Desa -->
                        <div class="md:col-span-2">
                            <label for="desa_id" class="block text-sm font-medium text-gray-700 mb-2">
                                Desa <span class="text-gray-400">(Opsional)</span>
                            </label>
                            <select id="desa_id" name="desa_id" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                                <option value="">Pilih Desa (Opsional)</option>
                                <?php foreach ($desa_list as $desa): ?>
                                <option value="<?= $desa['id'] ?>" <?= (isset($_POST['desa_id']) && $_POST['desa_id'] == $desa['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($desa['nama_desa']) ?> - <?= htmlspecialchars($desa['kecamatan']) ?>, <?= htmlspecialchars($desa['kabupaten']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="mt-1 text-sm text-gray-500">Pilih desa jika website terkait dengan desa yang sudah terdaftar</p>
                        </div>
                        
                        <!-- Website URL -->
                        <div class="md:col-span-2">
                            <label for="website_url" class="block text-sm font-medium text-gray-700 mb-2">
                                URL Website <span class="text-red-500">*</span>
                            </label>
                            <input type="url" id="website_url" name="website_url" required
                                   value="<?= htmlspecialchars($_POST['website_url'] ?? '') ?>"
                                   placeholder="https://example.desa.id"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        </div>
                        
                        <!-- Has Database -->
                        <div>
                            <label for="has_database" class="block text-sm font-medium text-gray-700 mb-2">
                                Status Database <span class="text-red-500">*</span>
                            </label>
                            <select id="has_database" name="has_database" required
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                                <option value="ada" <?= (isset($_POST['has_database']) && $_POST['has_database'] === 'ada') ? 'selected' : '' ?>>Ada</option>
                                <option value="tidak_ada" <?= (isset($_POST['has_database']) && $_POST['has_database'] === 'tidak_ada') ? 'selected' : '' ?>>Tidak Ada</option>
                            </select>
                        </div>
                        
                        <!-- News Active -->
                        <div>
                            <label for="news_active" class="block text-sm font-medium text-gray-700 mb-2">
                                Status Berita <span class="text-red-500">*</span>
                            </label>
                            <select id="news_active" name="news_active" required
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                                <option value="aktif" <?= (isset($_POST['news_active']) && $_POST['news_active'] === 'aktif') ? 'selected' : '' ?>>Aktif</option>
                                <option value="tidak_aktif" <?= (isset($_POST['news_active']) && $_POST['news_active'] === 'tidak_aktif') ? 'selected' : '' ?>>Tidak Aktif</option>
                            </select>
                        </div>
                        
                        <!-- Developer Type -->
                        <div>
                            <label for="developer_type" class="block text-sm font-medium text-gray-700 mb-2">
                                Developer <span class="text-red-500">*</span>
                            </label>
                            <select id="developer_type" name="developer_type" required
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                                <option value="clasnet" <?= (isset($_POST['developer_type']) && $_POST['developer_type'] === 'clasnet') ? 'selected' : '' ?>>Clasnet</option>
                                <option value="bukan_clasnet" <?= (isset($_POST['developer_type']) && $_POST['developer_type'] === 'bukan_clasnet') ? 'selected' : '' ?>>Bukan Clasnet</option>
                            </select>
                        </div>
                        
                        <!-- OpenData Sync -->
                        <div>
                            <label for="opendata_sync" class="block text-sm font-medium text-gray-700 mb-2">
                                Sinkron OpenData <span class="text-red-500">*</span>
                            </label>
                            <select id="opendata_sync" name="opendata_sync" required
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                                <option value="sinkron" <?= (isset($_POST['opendata_sync']) && $_POST['opendata_sync'] === 'sinkron') ? 'selected' : '' ?>>Sinkron</option>
                                <option value="proses" <?= (isset($_POST['opendata_sync']) && $_POST['opendata_sync'] === 'proses') ? 'selected' : '' ?>>Proses</option>
                                <option value="tidak_sinkron" <?= (isset($_POST['opendata_sync']) && $_POST['opendata_sync'] === 'tidak_sinkron') ? 'selected' : '' ?>>Tidak Sinkron</option>
                            </select>
                        </div>
                        
                        <!-- Keterangan -->
                        <div class="md:col-span-2">
                            <label for="keterangan" class="block text-sm font-medium text-gray-700 mb-2">
                                Keterangan
                            </label>
                            <textarea id="keterangan" name="keterangan" rows="4"
                                      placeholder="Catatan atau keterangan tambahan..."
                                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500"><?= htmlspecialchars($_POST['keterangan'] ?? '') ?></textarea>
                        </div>
                    </div>
                    
                    <!-- Form Actions -->
                    <div class="mt-8 flex justify-end space-x-4">
                        <a href="website-desa.php" 
                           class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 bg-white hover:bg-gray-50 font-medium transition-colors duration-200">
                            Batal
                        </a>
                        <button type="submit" 
                                class="px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white rounded-md font-medium transition-colors duration-200">
                            <i class="fas fa-save mr-2"></i>
                            Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        // Wait for jQuery and Select2 to load
        document.addEventListener('DOMContentLoaded', function() {
            // Check if jQuery and Select2 are loaded
            if (typeof $ !== 'undefined' && typeof $.fn.select2 !== 'undefined') {
                // Select2 initialization
                $('#desa_id').select2({
                    placeholder: 'Pilih Desa (Opsional)',
                    allowClear: true,
                    width: '100%'
                });
            }
        });
        
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