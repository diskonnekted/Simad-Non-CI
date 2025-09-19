<?php
require_once 'config/database.php';
require_once 'config/auth.php';

// Cek autentikasi
if (!AuthStatic::isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Cek role - hanya admin dan sales yang bisa edit layanan
if (!AuthStatic::hasRole(['admin', 'sales'])) {
    header('Location: index.php?error=' . urlencode('Anda tidak memiliki akses untuk mengedit layanan.'));
    exit;
}

$user = AuthStatic::getCurrentUser();
$db = getDatabase();
/** @var Database $db */

// Get layanan ID
$layanan_id = intval($_GET['id'] ?? 0);
if (!$layanan_id) {
    header('Location: layanan.php?error=' . urlencode('ID layanan tidak valid.'));
    exit;
}

// Get layanan data
$layanan = $db->select(
    "SELECT * FROM layanan WHERE id = ?",
    [$layanan_id]
);

if (empty($layanan)) {
    header('Location: layanan.php?error=' . urlencode('Layanan tidak ditemukan.'));
    exit;
}

$layanan = $layanan[0];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_layanan = trim($_POST['nama_layanan'] ?? '');
    $kode_layanan = trim($_POST['kode_layanan'] ?? '');
    $jenis_layanan = trim($_POST['jenis_layanan'] ?? '');
    $harga = floatval($_POST['harga'] ?? 0);
    $durasi_hari = intval($_POST['durasi'] ?? 0);
    $deskripsi = trim($_POST['deskripsi'] ?? '');
    $status = $_POST['status'] ?? 'aktif';
    
    $errors = [];
    
    // Validasi input
    if (empty($nama_layanan)) {
        $errors[] = 'Nama layanan harus diisi.';
    }
    
    if (empty($kode_layanan)) {
        $errors[] = 'Kode layanan harus diisi.';
    } else {
        // Cek duplikasi kode layanan (kecuali layanan ini sendiri)
        $existing = $db->select(
            "SELECT id FROM layanan WHERE kode_layanan = ? AND id != ?",
            [$kode_layanan, $layanan_id]
        );
        if (!empty($existing)) {
            $errors[] = 'Kode layanan sudah digunakan.';
        }
    }
    
    if (empty($jenis_layanan)) {
        $errors[] = 'Jenis layanan harus dipilih.';
    }
    
    if ($harga <= 0) {
        $errors[] = 'Harga harus lebih dari 0.';
    }
    
    if ($durasi_hari <= 0) {
        $errors[] = 'Durasi harus lebih dari 0.';
    }
    
    // Handle image upload
    $gambar_path = !empty($layanan['gambar']) ? $layanan['gambar'] : null; // Keep existing image by default
    
    if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/layanan/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_info = pathinfo($_FILES['gambar']['name']);
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (!in_array(strtolower($file_info['extension']), $allowed_types)) {
            $errors[] = 'Format gambar tidak didukung. Gunakan JPG, PNG, atau GIF.';
        } else if ($_FILES['gambar']['size'] > 2 * 1024 * 1024) { // 2MB
            $errors[] = 'Ukuran gambar terlalu besar. Maksimal 2MB.';
        } else {
            $new_filename = 'layanan_' . $layanan_id . '_' . time() . '.' . $file_info['extension'];
            $upload_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($_FILES['gambar']['tmp_name'], $upload_path)) {
                // Delete old image if exists
                if (!empty($layanan['gambar']) && file_exists($layanan['gambar'])) {
                    unlink($layanan['gambar']);
                }
                $gambar_path = $upload_path;
            } else {
                $errors[] = 'Gagal mengupload gambar.';
            }
        }
    }
    
    // Handle image deletion
    if (isset($_POST['hapus_gambar']) && $_POST['hapus_gambar'] === '1') {
        if (!empty($layanan['gambar']) && file_exists($layanan['gambar'])) {
            unlink($layanan['gambar']);
        }
        $gambar_path = null;
    }
    
    if (empty($errors)) {
        try {
            $update_data = [
                'nama_layanan' => $nama_layanan,
                'kode_layanan' => $kode_layanan,
                'jenis_layanan' => $jenis_layanan,
                'harga' => $harga,
                'durasi_hari' => $durasi_hari,
                'deskripsi' => $deskripsi,
                'status' => $status,
                'gambar' => $gambar_path
            ];
            
            $db->update('layanan', $update_data, ['id' => $layanan_id]);
            
            header('Location: layanan-view.php?id=' . $layanan_id . '&success=' . urlencode('Layanan berhasil diperbarui.'));
            exit;
        } catch (Exception $e) {
            $errors[] = 'Terjadi kesalahan: ' . $e->getMessage();
        }
    }
}

// Get jenis layanan options
$jenis_layanan_options = [
    'maintenance' => 'Maintenance',
    'pelatihan' => 'Pelatihan', 
    'instalasi' => 'Instalasi',
    'konsultasi' => 'Konsultasi',
    'pengembangan' => 'Pengembangan'
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Edit Layanan - Sistem Manajemen Desa</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#eff6ff',
                            100: '#dbeafe',
                            200: '#bfdbfe',
                            300: '#93c5fd',
                            400: '#60a5fa',
                            500: '#3b82f6',
                            600: '#2563eb',
                            700: '#1d4ed8',
                            800: '#1e40af',
                            900: '#1e3a8a'
                        }
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <?php include 'layouts/header.php'; ?>
    
    <div class="flex">
        <!-- Main Content -->
        <div class="flex-1 ml-64">
            <!-- Page Header -->
            <div class="bg-white shadow-sm border-b px-6 py-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900 flex items-center">
                            <i class="fas fa-edit mr-3 text-primary-600"></i>
                            Edit Layanan
                        </h1>
                        <p class="text-gray-600 mt-1">Perbarui informasi layanan</p>
                    </div>
                    <nav class="flex" aria-label="Breadcrumb">
                        <ol class="inline-flex items-center space-x-1 md:space-x-3">
                            <li class="inline-flex items-center">
                                <a href="index.php" class="text-gray-700 hover:text-primary-600">
                                    Dashboard
                                </a>
                            </li>
                            <li>
                                <div class="flex items-center">
                                    <i class="fas fa-chevron-right text-gray-400 mx-2"></i>
                                    <a href="layanan.php" class="text-gray-700 hover:text-primary-600">Layanan</a>
                                </div>
                            </li>
                            <li>
                                <div class="flex items-center">
                                    <i class="fas fa-chevron-right text-gray-400 mx-2"></i>
                                    <a href="layanan-view.php?id=<?= $layanan['id'] ?>" class="text-gray-700 hover:text-primary-600"><?= htmlspecialchars($layanan['nama_layanan']) ?></a>
                                </div>
                            </li>
                            <li aria-current="page">
                                <div class="flex items-center">
                                    <i class="fas fa-chevron-right text-gray-400 mx-2"></i>
                                    <span class="text-gray-500">Edit</span>
                                </div>
                            </li>
                        </ol>
                    </nav>
                </div>
            </div>

            <!-- Content -->
            <div class="max-w-7xl ml-0 px-4 sm:px-6 lg:px-8 py-8">
                <!-- Error Messages -->
                <?php if (!empty($errors)): ?>
                <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-exclamation-circle text-red-400"></i>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-red-800">Terdapat kesalahan:</h3>
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
                    <div class="bg-white rounded-lg shadow-sm border border-l-4 border-l-primary-500 p-6 mb-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4 pb-2 border-b border-gray-200 flex items-center">
                            <i class="fas fa-info-circle mr-2 text-primary-600"></i>
                            Informasi Dasar
                        </h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="nama_layanan" class="block text-sm font-medium text-gray-700 mb-2">
                                    Nama Layanan <span class="text-red-500">*</span>
                                </label>
                                <input type="text" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500" 
                                       id="nama_layanan" 
                                       name="nama_layanan" 
                                       value="<?= htmlspecialchars($layanan['nama_layanan']) ?>" 
                                       required>
                                <p class="mt-1 text-xs text-gray-500">Nama layanan akan otomatis dikapitalisasi</p>
                            </div>
                            <div>
                                <label for="kode_layanan" class="block text-sm font-medium text-gray-700 mb-2">
                                    Kode Layanan <span class="text-red-500">*</span>
                                </label>
                                <input type="text" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500" 
                                       id="kode_layanan" 
                                       name="kode_layanan" 
                                       value="<?= htmlspecialchars($layanan['kode_layanan']) ?>" 
                                       required>
                                <p class="mt-1 text-xs text-gray-500">Kode unik untuk identifikasi layanan</p>
                            </div>
                        </div>
                        
                        <div class="mt-6">
                            <label for="jenis_layanan" class="block text-sm font-medium text-gray-700 mb-2">
                                Jenis Layanan <span class="text-red-500">*</span>
                            </label>
                            <select class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500" 
                                    id="jenis_layanan" 
                                    name="jenis_layanan" 
                                    required>
                                <option value="">Pilih Jenis Layanan</option>
                                <?php foreach ($jenis_layanan_options as $value => $label): ?>
                                <option value="<?= $value ?>" <?= $layanan['jenis_layanan'] == $value ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($label) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Harga dan Durasi -->
                    <div class="bg-white rounded-lg shadow-sm border border-l-4 border-l-primary-500 p-6 mb-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4 pb-2 border-b border-gray-200 flex items-center">
                            <i class="fas fa-money-bill-wave mr-2 text-primary-600"></i>
                            Harga dan Durasi
                        </h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="harga" class="block text-sm font-medium text-gray-700 mb-2">
                                    Harga <span class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <span class="absolute left-3 top-2 text-gray-500">Rp</span>
                                    <input type="number" 
                                           class="w-full pl-8 pr-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500" 
                                           id="harga" 
                                           name="harga" 
                                           value="<?= $layanan['harga'] ?>" 
                                           min="1" 
                                           step="1000" 
                                           required>
                                </div>
                            </div>
                            <div>
                                <label for="durasi" class="block text-sm font-medium text-gray-700 mb-2">
                                    Durasi (Hari) <span class="text-red-500">*</span>
                                </label>
                                <input type="number" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500" 
                                       id="durasi" 
                                       name="durasi" 
                                       value="<?= $layanan['durasi_hari'] ?? '' ?>" 
                                       min="1" 
                                       required>
                                <p class="mt-1 text-xs text-gray-500">Estimasi waktu penyelesaian layanan</p>
                            </div>
                        </div>
                    </div>

                    <!-- Detail Layanan -->
                    <div class="bg-white rounded-lg shadow-sm border border-l-4 border-l-primary-500 p-6 mb-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4 pb-2 border-b border-gray-200 flex items-center">
                            <i class="fas fa-file-text mr-2 text-primary-600"></i>
                            Detail Layanan
                        </h3>
                        
                        <div class="space-y-6">
                            <div>
                                <label for="deskripsi" class="block text-sm font-medium text-gray-700 mb-2">
                                    Deskripsi Layanan
                                </label>
                                <textarea class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500" 
                                          id="deskripsi" 
                                          name="deskripsi" 
                                          rows="4" 
                                          placeholder="Jelaskan detail layanan yang ditawarkan..."><?= htmlspecialchars($layanan['deskripsi']) ?></textarea>
                            </div>
                            
                            <div>
                                <label for="persyaratan" class="block text-sm font-medium text-gray-700 mb-2">
                                    Persyaratan
                                </label>
                                <textarea class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500" 
                                          id="persyaratan" 
                                          name="persyaratan" 
                                          rows="3" 
                                          placeholder="Persyaratan yang harus dipenuhi untuk layanan ini..."><?= htmlspecialchars($layanan['persyaratan'] ?? '') ?></textarea>
                            </div>
                            
                            <div>
                                <label for="garansi" class="block text-sm font-medium text-gray-700 mb-2">
                                    Garansi
                                </label>
                                <input type="text" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500" 
                                       id="garansi" 
                                       name="garansi" 
                                       value="<?= htmlspecialchars($layanan['garansi'] ?? '') ?>" 
                                       placeholder="Contoh: 30 hari garansi layanan">
                            </div>
                        </div>
                    </div>

                    <!-- Gambar -->
                    <div class="bg-white rounded-lg shadow-sm border border-l-4 border-l-primary-500 p-6 mb-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4 pb-2 border-b border-gray-200 flex items-center">
                            <i class="fas fa-image mr-2 text-primary-600"></i>
                            Gambar Layanan
                        </h3>
                        
                        <div class="space-y-6">
                            <div>
                                <label for="gambar" class="block text-sm font-medium text-gray-700 mb-2">
                                    Upload Gambar
                                </label>
                                <input type="file" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-primary-50 file:text-primary-700 hover:file:bg-primary-100" 
                                       id="gambar" 
                                       name="gambar" 
                                       accept="image/jpeg,image/jpg,image/png,image/gif">
                                <p class="mt-1 text-xs text-gray-500">Format: JPG, PNG, GIF. Maksimal 2MB.</p>
                            </div>
                            
                            <?php if (isset($layanan['gambar']) && $layanan['gambar']): ?>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Gambar Saat Ini:
                                </label>
                                <div class="max-w-xs border-2 border-green-300 rounded-lg p-3 text-center" id="currentImagePreview">
                                    <img src="<?= htmlspecialchars($layanan['gambar']) ?>" 
                                         alt="Gambar Layanan" 
                                         class="max-w-full max-h-48 rounded-md">
                                    <div class="mt-3">
                                        <label class="inline-flex items-center">
                                            <input type="checkbox" 
                                                   id="hapus_gambar" 
                                                   name="hapus_gambar" 
                                                   value="1" 
                                                   class="rounded border-gray-300 text-primary-600 shadow-sm focus:border-primary-300 focus:ring focus:ring-primary-200 focus:ring-opacity-50">
                                            <span class="ml-2 text-sm text-gray-600">Hapus gambar ini</span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <div class="max-w-xs border-2 border-dashed border-gray-300 rounded-lg p-3 text-center hidden" id="imagePreview">
                                <img id="previewImg" src="" alt="Preview" class="max-w-full max-h-48 rounded-md">
                                <div class="mt-3">
                                    <button type="button" 
                                            class="inline-flex items-center px-3 py-1 border border-transparent text-sm font-medium rounded-md text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500" 
                                            onclick="removeImagePreview()">
                                        <i class="fas fa-times mr-1"></i> Hapus
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Status -->
                    <div class="bg-white rounded-lg shadow-sm border border-l-4 border-l-primary-500 p-6 mb-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4 pb-2 border-b border-gray-200 flex items-center">
                            <i class="fas fa-toggle-on mr-2 text-primary-600"></i>
                            Status
                        </h3>
                        
                        <div>
                            <label for="status" class="block text-sm font-medium text-gray-700 mb-2">
                                Status Layanan
                            </label>
                            <select class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500" 
                                    id="status" 
                                    name="status">
                                <option value="aktif" <?= $layanan['status'] === 'aktif' ? 'selected' : '' ?>>Aktif</option>
                                <option value="nonaktif" <?= $layanan['status'] === 'nonaktif' ? 'selected' : '' ?>>Nonaktif</option>
                            </select>
                            <p class="mt-1 text-xs text-gray-500">Layanan nonaktif tidak akan muncul dalam daftar transaksi</p>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="bg-gray-50 rounded-lg p-6 flex justify-end space-x-3">
                        <a href="layanan-view.php?id=<?= $layanan['id'] ?>" 
                           class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                            <i class="fas fa-arrow-left mr-2"></i> Kembali
                        </a>
                        <button type="submit" 
                                class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                            <i class="fas fa-save mr-2"></i> Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            // Auto capitalize nama layanan
            $('#nama_layanan').on('input', function() {
                let value = $(this).val();
                let words = value.split(' ');
                for (let i = 0; i < words.length; i++) {
                    if (words[i].length > 0) {
                        words[i] = words[i][0].toUpperCase() + words[i].substr(1).toLowerCase();
                    }
                }
                $(this).val(words.join(' '));
            });
            
            // Generate kode layanan otomatis
            $('#nama_layanan, #jenis_layanan').on('change', function() {
                if ($('#kode_layanan').val() === '' || $('#kode_layanan').data('auto-generated')) {
                    generateKodeLayanan();
                }
            });
            
            // Image preview
            $('#gambar').on('change', function() {
                const file = this.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        $('#previewImg').attr('src', e.target.result);
                        $('#imagePreview').removeClass('hidden').addClass('border-green-300');
                        $('#currentImagePreview').addClass('hidden');
                        $('#hapus_gambar').prop('checked', false);
                    };
                    reader.readAsDataURL(file);
                }
            });
            
            // Handle hapus gambar checkbox
            $('#hapus_gambar').on('change', function() {
                if ($(this).is(':checked')) {
                    $('#currentImagePreview').addClass('hidden');
                    $('#gambar').val('');
                    $('#imagePreview').addClass('hidden');
                } else {
                    $('#currentImagePreview').removeClass('hidden');
                }
            });
            
            // Form validation
            $('#layananForm').on('submit', function(e) {
                let isValid = true;
                
                // Validate required fields
                $('input[required], select[required], textarea[required]').each(function() {
                    if (!$(this).val().trim()) {
                        isValid = false;
                        $(this).addClass('error');
                    } else {
                        $(this).removeClass('error');
                    }
                });
                
                // Validate harga
                const harga = parseFloat($('#harga').val());
                if (harga <= 0) {
                    isValid = false;
                    $('#harga').addClass('error');
                    alert('Harga harus lebih dari 0.');
                }
                
                // Validate durasi
                const durasi = parseInt($('#durasi').val());
                if (durasi <= 0) {
                    isValid = false;
                    $('#durasi').addClass('error');
                    alert('Durasi harus lebih dari 0.');
                }
                
                if (!isValid) {
                    e.preventDefault();
                    alert('Mohon lengkapi semua field yang wajib diisi.');
                }
            });
        });
        
        function generateKodeLayanan() {
            const namaLayanan = $('#nama_layanan').val();
            const jenisLayanan = $('#jenis_layanan').val();
            
            if (namaLayanan && jenisLayanan) {
                const jenisCode = jenisLayanan.substring(0, 3).toUpperCase();
                const namaCode = namaLayanan.split(' ').map(word => word.charAt(0)).join('').toUpperCase();
                const randomNum = Math.floor(Math.random() * 100).toString().padStart(2, '0');
                
                const kodeLayanan = `${jenisCode}-${namaCode}-${randomNum}`;
                $('#kode_layanan').val(kodeLayanan).data('auto-generated', true);
            }
        }
        
        function removeImagePreview() {
            $('#imagePreview').addClass('hidden').removeClass('border-green-300');
            $('#previewImg').attr('src', '');
            $('#gambar').val('');
            $('#currentImagePreview').removeClass('hidden');
        }
        
        // Auto-hide alerts
        setTimeout(function() {
            $('.alert').fadeOut();
        }, 5000);
    </script>
</body>
</html>
