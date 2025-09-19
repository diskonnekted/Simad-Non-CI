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
    
    // Cek duplikasi URL
    if (empty($errors)) {
        $existing = $db->select("SELECT id FROM website_desa WHERE website_url = ?", [$website_url]);
        if (!empty($existing)) {
            $errors[] = 'URL Website sudah terdaftar';
        }
    }
    
    if (empty($errors)) {
        try {
            $query = "
                INSERT INTO website_desa (desa_id, website_url, has_database, news_active, developer_type, opendata_sync, keterangan, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ";
            
            $params = [
                $desa_id,
                $website_url,
                $has_database,
                $news_active,
                $developer_type,
                $opendata_sync,
                $keterangan
            ];
            
            $db->execute($query, $params);
            
            header('Location: website-desa.php?success=1');
            exit;
        } catch (Exception $e) {
            $errors[] = 'Gagal menyimpan data: ' . $e->getMessage();
        }
    }
}

// Get desa list for dropdown
$desa_list = $db->select("SELECT id, CONCAT(UPPER(SUBSTRING(nama_desa, 1, 1)), LOWER(SUBSTRING(nama_desa, 2))) as nama_desa, kecamatan, kabupaten FROM desa ORDER BY nama_desa ASC");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Tambah Website Desa - Sistem Manajemen Desa">
    <meta name="keywords" content="website, desa, tambah, sistem">
    <title>Tambah Website Desa - Sistem Manajemen Desa</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#eff6ff',
                            500: '#3b82f6',
                            600: '#2563eb',
                            700: '#1d4ed8'
                        }
                    }
                }
            }
        }
    </script>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Select2 -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
</head>
<body class="bg-gray-50">
    <?php include 'layouts/header.php'; ?>
    
    <div class="flex">
        <!-- Sidebar -->
        <div class="w-64 bg-white shadow-sm min-h-screen">
            <nav class="mt-5 px-2">
                <a href="index.php" class="group flex items-center px-2 py-2 text-base font-medium rounded-md text-gray-600 hover:bg-gray-50 hover:text-gray-900">
                    <i class="fas fa-tachometer-alt mr-4 text-gray-400"></i>
                    Dashboard
                </a>
                <a href="transaksi.php" class="group flex items-center px-2 py-2 text-base font-medium rounded-md text-gray-600 hover:bg-gray-50 hover:text-gray-900">
                    <i class="fas fa-exchange-alt mr-4 text-gray-400"></i>
                    Transaksi
                </a>
                <a href="desa.php" class="group flex items-center px-2 py-2 text-base font-medium rounded-md text-gray-600 hover:bg-gray-50 hover:text-gray-900">
                    <i class="fas fa-map-marker-alt mr-4 text-gray-400"></i>
                    Data Desa
                </a>
                <a href="website-desa.php" class="group flex items-center px-2 py-2 text-base font-medium rounded-md bg-primary-50 text-primary-700">
                    <i class="fas fa-globe mr-4 text-primary-500"></i>
                    Website Desa
                </a>
                <a href="produk.php" class="group flex items-center px-2 py-2 text-base font-medium rounded-md text-gray-600 hover:bg-gray-50 hover:text-gray-900">
                    <i class="fas fa-box mr-4 text-gray-400"></i>
                    Produk
                </a>
                <a href="layanan.php" class="group flex items-center px-2 py-2 text-base font-medium rounded-md text-gray-600 hover:bg-gray-50 hover:text-gray-900">
                    <i class="fas fa-cogs mr-4 text-gray-400"></i>
                    Layanan
                </a>
                <?php if (AuthStatic::hasRole(['admin'])): ?>
                <a href="user.php" class="group flex items-center px-2 py-2 text-base font-medium rounded-md text-gray-600 hover:bg-gray-50 hover:text-gray-900">
                    <i class="fas fa-users mr-4 text-gray-400"></i>
                    Pengguna
                </a>
                <?php endif; ?>
            </nav>
        </div>
        
        <!-- Main Content -->
        <div class="flex-1 p-8">
            <!-- Header -->
            <div class="mb-8">
                <div class="flex items-center">
                    <a href="website-desa.php" class="text-gray-600 hover:text-gray-800 mr-4">
                        <i class="fas fa-arrow-left text-lg"></i>
                    </a>
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900">Tambah Website Desa</h1>
                        <p class="mt-2 text-gray-600">Tambahkan data website desa baru</p>
                    </div>
                </div>
            </div>
            
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
                            Simpan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        $(document).ready(function() {
            $('#desa_id').select2({
                placeholder: 'Pilih Desa (Opsional)',
                allowClear: true,
                width: '100%'
            });
        });
    </script>
</body>
</html>