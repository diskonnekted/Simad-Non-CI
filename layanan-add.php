<?php
require_once 'config/database.php';
require_once 'config/auth.php';

// Cek autentikasi
if (!AuthStatic::isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Cek otorisasi
if (!AuthStatic::hasRole(['admin', 'sales'])) {
    header('Location: index.php?error=unauthorized');
    exit;
}

$user = AuthStatic::getCurrentUser();
$db = getDatabase();
/** @var Database $db */

// Define service types instead of using product categories
$jenis_layanan_options = [
    'maintenance' => 'Maintenance',
    'pelatihan' => 'Pelatihan',
    'instalasi' => 'Instalasi',
    'konsultasi' => 'Konsultasi',
    'pengembangan' => 'Pengembangan'
];

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validasi input
    $nama_layanan = trim($_POST['nama_layanan'] ?? '');
    $kode_layanan = trim($_POST['kode_layanan'] ?? '');
    $jenis_layanan = trim($_POST['jenis_layanan'] ?? '');
    $harga = floatval($_POST['harga'] ?? 0);
    $durasi = trim($_POST['durasi'] ?? '');
    $deskripsi = trim($_POST['deskripsi'] ?? '');
    $status = $_POST['status'] ?? 'aktif';
    
    // Validasi required fields
    if (empty($nama_layanan)) {
        $errors[] = 'Nama layanan harus diisi.';
    }
    
    if (empty($kode_layanan)) {
        $errors[] = 'Kode layanan harus diisi.';
    }
    
    if ($harga <= 0) {
        $errors[] = 'Harga harus lebih dari 0.';
    }
    
    // Cek duplikasi kode layanan
    if (!empty($kode_layanan)) {
        $existing = $db->select(
            "SELECT id FROM layanan WHERE kode_layanan = ? AND status != 'deleted'",
            [$kode_layanan]
        );
        
        if (!empty($existing)) {
            $errors[] = 'Kode layanan sudah digunakan.';
        }
    }
    
    // Handle file upload
    $gambar_filename = null;
    if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/layanan/';
        
        // Create directory if not exists
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_info = pathinfo($_FILES['gambar']['name']);
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (!in_array(strtolower($file_info['extension']), $allowed_extensions)) {
            $errors[] = 'Format gambar tidak didukung. Gunakan JPG, PNG, atau GIF.';
        } else {
            // Check file size (max 5MB)
            if ($_FILES['gambar']['size'] > 5 * 1024 * 1024) {
                $errors[] = 'Ukuran gambar terlalu besar. Maksimal 5MB.';
            } else {
                $gambar_filename = time() . '_' . uniqid() . '.' . $file_info['extension'];
                $upload_path = $upload_dir . $gambar_filename;
                
                if (!move_uploaded_file($_FILES['gambar']['tmp_name'], $upload_path)) {
                    $errors[] = 'Gagal mengupload gambar.';
                    $gambar_filename = null;
                }
            }
        }
    }
    
    // Jika tidak ada error, simpan data
    if (empty($errors)) {
        try {
            $data = [
                'nama_layanan' => $nama_layanan,
                'kode_layanan' => $kode_layanan,
                'jenis_layanan' => $jenis_layanan,
                'harga' => $harga,
                'durasi_hari' => $durasi,
                'deskripsi' => $deskripsi,
                'status' => $status
            ];
            
            $layanan_id = $db->insert('layanan', $data);
            
            if ($layanan_id) {
                header('Location: layanan.php?success=' . urlencode('Layanan berhasil ditambahkan.'));
                exit;
            } else {
                $errors[] = 'Gagal menyimpan data layanan.';
            }
        } catch (Exception $e) {
            $errors[] = 'Terjadi kesalahan: ' . $e->getMessage();
        }
    }
}

// Generate kode layanan otomatis
function generateKodeLayanan($db) {
    $prefix = 'LYN';
    $year = date('y');
    $month = date('m');
    
    // Cari nomor urut terakhir untuk bulan ini
    $last_code = $db->select(
        "SELECT kode_layanan FROM layanan WHERE kode_layanan LIKE ? ORDER BY kode_layanan DESC LIMIT 1",
        [$prefix . $year . $month . '%']
    );
    
    if (!empty($last_code)) {
        $last_number = intval(substr($last_code[0]['kode_layanan'], -3));
        $new_number = $last_number + 1;
    } else {
        $new_number = 1;
    }
    
    return $prefix . $year . $month . str_pad($new_number, 3, '0', STR_PAD_LEFT);
}

$suggested_code = generateKodeLayanan($db);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tambah Layanan - Sistem Manajemen Desa</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .code-generator {
            background: #f8f9fa;
            padding: 8px;
            border-radius: 4px;
            margin-top: 5px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .btn-generate {
            margin-left: 10px;
        }
        
        /* Primary color definitions */
        .bg-primary-100 {
            background-color: #dbeafe;
        }
        .border-primary-500 {
            border-color: #3b82f6;
        }
        .text-primary-700 {
            color: #1d4ed8;
        }
        .text-primary-500 {
            color: #3b82f6;
        }
    </style>
</head>
<body class="bg-gray-50 font-sans">
    <!-- Loading Overlay -->
    <div id="loading" class="fixed inset-0 bg-white bg-opacity-75 flex items-center justify-center z-50 hidden">
        <div class="text-center">
            <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-primary-600 mx-auto"></div>
            <p class="mt-4 text-gray-600">Memuat...</p>
        </div>
    </div>

    <?php include 'layouts/header.php'; ?>
    
    <!-- Page Header -->
    <div class="bg-white shadow-sm border-b border-gray-200 mb-6">
        <div class="px-6 py-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 flex items-center">
                        <i class="fa fa-plus-circle text-primary-600 mr-3"></i>
                        Tambah Layanan Baru
                    </h1>
                    <p class="text-sm text-gray-600 mt-1">Tambahkan layanan baru ke dalam sistem</p>
                </div>
                <nav class="mt-4 sm:mt-0 text-sm text-gray-500">
                    <a href="index.php" class="hover:text-primary-600">Dashboard</a>
                    <span class="mx-2">/</span>
                    <a href="layanan.php" class="hover:text-primary-600">Layanan</a>
                    <span class="mx-2">/</span>
                    <span class="text-gray-900">Tambah Layanan</span>
                </nav>
            </div>
        </div>
    </div>

    <!-- Main Container -->
    <div class="max-w-7xl ml-0 px-4 sm:px-6 lg:px-8 py-8">
            <div class="mb-8">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900 flex items-center">
                            <i class="fa fa-plus mr-2"></i>
                            Tambah Layanan
                        </h1>
                        <p class="text-gray-600 mt-1">Tambah layanan maintenance dan pelatihan baru</p>
                    </div>
                </div>
                <nav class="flex mt-4" aria-label="Breadcrumb">
                    <ol class="flex items-center space-x-2">
                        <li><a href="index.php" class="text-blue-600 hover:text-blue-800">Dashboard</a></li>
                        <li class="text-gray-500">/</li>
                        <li><a href="layanan.php" class="text-blue-600 hover:text-blue-800">Layanan</a></li>
                        <li class="text-gray-500">/</li>
                        <li class="text-gray-900">Tambah Layanan</li>
                    </ol>
                </nav>
            </div>

            <div class="space-y-6">
                <!-- Error Messages -->
                <?php if (!empty($errors)): ?>
                <div class="bg-red-50 border border-red-200 rounded-md p-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fa fa-exclamation-triangle text-red-400"></i>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-red-800">Terjadi Kesalahan:</h3>
                            <ul class="mt-2 text-sm text-red-700 list-disc list-inside">
                                <?php foreach ($errors as $error): ?>
                                <li><?= htmlspecialchars($error) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data" id="layananForm">
                    <!-- Informasi Dasar -->
                    <div class="bg-white rounded-lg shadow p-6 mb-6">
                        <div class="flex items-center mb-6">
                            <i class="fa fa-info-circle text-blue-500 mr-2"></i>
                            <h2 class="text-lg font-semibold text-gray-900">Informasi Dasar</h2>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="nama_layanan" class="block text-sm font-medium text-gray-700 mb-2">
                                    Nama Layanan <span class="text-red-500">*</span>
                                </label>
                                <input type="text" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" 
                                       id="nama_layanan" name="nama_layanan" 
                                       value="<?= htmlspecialchars($_POST['nama_layanan'] ?? '') ?>" required>
                                <p class="text-xs text-gray-500 mt-1">Contoh: Maintenance Komputer, Pelatihan Microsoft Office</p>
                            </div>
                            <div>
                                <label for="kode_layanan" class="block text-sm font-medium text-gray-700 mb-2">
                                    Kode Layanan <span class="text-red-500">*</span>
                                </label>
                                <input type="text" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" 
                                       id="kode_layanan" name="kode_layanan" 
                                           value="<?= htmlspecialchars($_POST['kode_layanan'] ?? $suggested_code) ?>" required>
                                <div class="bg-gray-50 p-3 rounded-md mt-2 flex items-center justify-between">
                                    <small class="text-gray-600">Kode yang disarankan: <strong class="text-gray-900"><?= $suggested_code ?></strong></small>
                                    <button type="button" class="ml-3 px-3 py-1 text-xs bg-gray-200 text-gray-700 rounded hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-500" onclick="generateCode()">
                                        <i class="fa fa-refresh mr-1"></i> Generate
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                            <div>
                                <label for="jenis_layanan" class="block text-sm font-medium text-gray-700 mb-2">Jenis Layanan</label>
                                <select class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" id="jenis_layanan" name="jenis_layanan">
                                    <option value="">Pilih Jenis Layanan</option>
                                    <?php foreach ($jenis_layanan_options as $value => $label): ?>
                                    <option value="<?= $value ?>" 
                                            <?= (isset($_POST['jenis_layanan']) && $_POST['jenis_layanan'] == $value) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($label) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label for="status" class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                                <select class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" id="status" name="status">
                                    <option value="aktif" <?= (isset($_POST['status']) && $_POST['status'] === 'aktif') ? 'selected' : '' ?>>Aktif</option>
                                    <option value="nonaktif" <?= (isset($_POST['status']) && $_POST['status'] === 'nonaktif') ? 'selected' : '' ?>>Nonaktif</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Harga dan Durasi -->
                    <div class="bg-white rounded-lg shadow p-6 mb-6">
                        <div class="flex items-center mb-6">
                            <i class="fa fa-money text-green-500 mr-2"></i>
                            <h2 class="text-lg font-semibold text-gray-900">Harga dan Durasi</h2>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div>
                                <label for="harga" class="block text-sm font-medium text-gray-700 mb-2">
                                    Harga <span class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <span class="absolute left-3 top-2 text-gray-500">Rp</span>
                                    <input type="number" class="w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" 
                                           id="harga" name="harga" 
                                           value="<?= htmlspecialchars($_POST['harga'] ?? '') ?>" 
                                           min="0" step="1000" required>
                                </div>
                            </div>

                            <div>
                                <label for="durasi" class="block text-sm font-medium text-gray-700 mb-2">Durasi</label>
                                <input type="text" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" 
                                       id="durasi" name="durasi" 
                                       value="<?= htmlspecialchars($_POST['durasi'] ?? '') ?>" 
                                       placeholder="2 jam, 1 hari, dll">
                                <p class="text-xs text-gray-500 mt-1">Contoh: 2 jam, 1 hari, 3 sesi</p>
                            </div>
                        </div>
                    </div>

                    <!-- Detail Layanan -->
                    <div class="bg-white rounded-lg shadow p-6 mb-6">
                        <div class="flex items-center mb-6">
                            <i class="fa fa-file-text text-purple-500 mr-2"></i>
                            <h2 class="text-lg font-semibold text-gray-900">Detail Layanan</h2>
                        </div>
                        
                        <div class="space-y-6">
                            <div>
                                <label for="deskripsi" class="block text-sm font-medium text-gray-700 mb-2">Deskripsi</label>
                                <textarea class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" 
                                          id="deskripsi" name="deskripsi" rows="4" 
                                          placeholder="Deskripsi singkat tentang layanan ini..."><?= htmlspecialchars($_POST['deskripsi'] ?? '') ?></textarea>
                            </div>
                            

                        </div>
                    </div>

                    <!-- Gambar -->
                    <div class="bg-white rounded-lg shadow p-6 mb-6">
                        <div class="flex items-center mb-6">
                            <i class="fa fa-image text-orange-500 mr-2"></i>
                            <h2 class="text-lg font-semibold text-gray-900">Gambar Layanan</h2>
                        </div>
                        
                        <div>
                            <label for="gambar" class="block text-sm font-medium text-gray-700 mb-2">Upload Gambar</label>
                            <input type="file" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" 
                                   id="gambar" name="gambar" 
                                   accept="image/jpeg,image/png,image/gif" onchange="previewImage(this)">
                            <p class="text-xs text-gray-500 mt-1">Format: JPG, PNG, GIF. Maksimal 5MB.</p>
                            <img id="imagePreview" class="max-w-xs max-h-48 border border-gray-300 rounded-md mt-3 hidden" alt="Preview">
                        </div>
                    </div>

                    <!-- Submit Buttons -->
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="flex justify-end space-x-4">
                            <a href="layanan.php" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-500">
                                <i class="fa fa-times mr-2"></i>Batal
                            </a>
                            <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <i class="fa fa-save mr-2"></i>Simpan Layanan
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script>
        function previewImage(input) {
            const preview = document.getElementById('imagePreview');
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.classList.remove('hidden');
                };
                
                reader.readAsDataURL(input.files[0]);
            } else {
                preview.classList.add('hidden');
            }
        }
        
        function generateCode() {
            // Generate new code based on current timestamp
            const now = new Date();
            const year = now.getFullYear().toString().substr(-2);
            const month = (now.getMonth() + 1).toString().padStart(2, '0');
            const random = Math.floor(Math.random() * 999) + 1;
            const code = 'LYN' + year + month + random.toString().padStart(3, '0');
            
            document.getElementById('kode_layanan').value = code;
        }
        
        // Auto-capitalize nama layanan
        document.getElementById('nama_layanan').addEventListener('input', function() {
            let value = this.value;
            // Capitalize first letter of each word
            value = value.replace(/\b\w/g, function(char) {
                return char.toUpperCase();
            });
            this.value = value;
        });
        
        // Format harga input
        document.getElementById('harga').addEventListener('input', function() {
            let value = this.value.replace(/[^0-9]/g, '');
            this.value = value;
        });
        
        // Form validation
        document.getElementById('layananForm').addEventListener('submit', function(e) {
            const namaLayanan = document.getElementById('nama_layanan').value.trim();
            const kodeLayanan = document.getElementById('kode_layanan').value.trim();
            const harga = document.getElementById('harga').value;
            
            if (!namaLayanan) {
                alert('Nama layanan harus diisi.');
                e.preventDefault();
                return;
            }
            
            if (!kodeLayanan) {
                alert('Kode layanan harus diisi.');
                e.preventDefault();
                return;
            }
            
            if (!harga || parseFloat(harga) <= 0) {
                alert('Harga harus diisi dan lebih dari 0.');
                e.preventDefault();
                return;
            }
        });
    </script>
    </div>
</body>
</html>
