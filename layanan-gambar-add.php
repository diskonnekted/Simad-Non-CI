<?php
require_once 'config/database.php';
require_once 'config/auth.php';

// Check if user is logged in and has admin role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$success_message = '';
$error_message = '';

// Get PDO connection
$pdo = getDBConnection();

// Get all layanan for dropdown
try {
    $stmt = $pdo->query("SELECT id, nama_layanan FROM layanan ORDER BY nama_layanan");
    $layanan_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Error mengambil data layanan: " . $e->getMessage();
    $layanan_list = [];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $layanan_id = $_POST['layanan_id'] ?? '';
    
    if (empty($layanan_id)) {
        $error_message = "Silakan pilih layanan terlebih dahulu.";
    } elseif (!isset($_FILES['gambar']) || $_FILES['gambar']['error'] !== UPLOAD_ERR_OK) {
        $error_message = "Silakan pilih file gambar yang valid.";
    } else {
        $file = $_FILES['gambar'];
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        $max_size = 5 * 1024 * 1024; // 5MB
        
        // Validate file type
        if (!in_array($file['type'], $allowed_types)) {
            $error_message = "Format file tidak didukung. Gunakan JPG, PNG, atau GIF.";
        } elseif ($file['size'] > $max_size) {
            $error_message = "Ukuran file terlalu besar. Maksimal 5MB.";
        } else {
            // Create upload directory if not exists
            $upload_dir = 'img/layanan/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            // Generate unique filename
            $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $new_filename = 'layanan_' . $layanan_id . '_' . time() . '.' . $file_extension;
            $upload_path = $upload_dir . $new_filename;
            
            // Move uploaded file
            if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                try {
                    // Get current image to delete if exists
                    $stmt = $pdo->prepare("SELECT gambar FROM layanan WHERE id = ?");
                    $stmt->execute([$layanan_id]);
                    $current_layanan = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    // Delete old image if exists
                    if ($current_layanan && !empty($current_layanan['gambar']) && file_exists($current_layanan['gambar'])) {
                        unlink($current_layanan['gambar']);
                    }
                    
                    // Update database
                    $stmt = $pdo->prepare("UPDATE layanan SET gambar = ? WHERE id = ?");
                    $stmt->execute([$upload_path, $layanan_id]);
                    
                    $success_message = "Gambar berhasil ditambahkan ke layanan!";
                } catch (PDOException $e) {
                    $error_message = "Error menyimpan ke database: " . $e->getMessage();
                    // Delete uploaded file if database update fails
                    if (file_exists($upload_path)) {
                        unlink($upload_path);
                    }
                }
            } else {
                $error_message = "Gagal mengupload file.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Gambar Layanan - Manajemen Transaksi Desa</title>
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
                        },
                        secondary: {
                            50: '#f0f9ff',
                            100: '#e0f2fe',
                            200: '#bae6fd',
                            300: '#7dd3fc',
                            400: '#38bdf8',
                            500: '#0ea5e9',
                            600: '#0284c7',
                            700: '#0369a1',
                            800: '#075985',
                            900: '#0c4a6e'
                        }
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen py-8">
        <div class="max-w-2xl mx-auto px-4">
            <!-- Header -->
            <div class="bg-gradient-to-r from-primary-500 to-secondary-500 text-white rounded-lg p-6 mb-6">
                <h1 class="text-2xl font-bold">Tambah Gambar Layanan</h1>
                <p class="text-primary-100 mt-2">Upload gambar untuk layanan yang sudah ada</p>
            </div>

            <!-- Navigation -->
            <div class="mb-6">
                <nav class="flex space-x-4">
                    <a href="layanan.php" class="text-primary-600 hover:text-primary-800 font-medium">← Kembali ke Daftar Layanan</a>
                </nav>
            </div>

            <!-- Messages -->
            <?php if ($success_message): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                    <?= htmlspecialchars($success_message) ?>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                    <?= htmlspecialchars($error_message) ?>
                </div>
            <?php endif; ?>

            <!-- Form -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <form method="POST" enctype="multipart/form-data" class="space-y-6">
                    <!-- Pilih Layanan -->
                    <div>
                        <label for="layanan_id" class="block text-sm font-medium text-gray-700 mb-2">
                            Pilih Layanan *
                        </label>
                        <select name="layanan_id" id="layanan_id" required 
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                            <option value="">-- Pilih Layanan --</option>
                            <?php foreach ($layanan_list as $layanan): ?>
                                <option value="<?= $layanan['id'] ?>" <?= (isset($_POST['layanan_id']) && $_POST['layanan_id'] == $layanan['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($layanan['nama_layanan']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Upload Gambar -->
                    <div>
                        <label for="gambar" class="block text-sm font-medium text-gray-700 mb-2">
                            Upload Gambar *
                        </label>
                        <input type="file" name="gambar" id="gambar" accept="image/*" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                        <p class="text-sm text-gray-500 mt-1">
                            Format yang didukung: JPG, PNG, GIF. Maksimal 5MB.
                        </p>
                    </div>

                    <!-- Preview Area -->
                    <div id="preview-area" class="hidden">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Preview Gambar</label>
                        <img id="preview-image" src="" alt="Preview" class="max-w-xs h-auto border border-gray-300 rounded-md">
                    </div>

                    <!-- Submit Button -->
                    <div class="flex justify-end space-x-4">
                        <a href="layanan.php" 
                           class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary-500">
                            Batal
                        </a>
                        <button type="submit" 
                                class="px-6 py-2 bg-primary-600 text-white rounded-md hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500">
                            Upload Gambar
                        </button>
                    </div>
                </form>
            </div>

            <!-- Info Box -->
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mt-6">
                <h3 class="text-blue-800 font-medium mb-2">Informasi:</h3>
                <ul class="text-blue-700 text-sm space-y-1">
                    <li>• Gambar akan menggantikan gambar yang sudah ada (jika ada)</li>
                    <li>• Pastikan gambar memiliki kualitas yang baik untuk tampilan terbaik</li>
                    <li>• Gambar akan disimpan di folder img/layanan/</li>
                </ul>
            </div>
        </div>
    </div>

    <script>
        // Preview image before upload
        document.getElementById('gambar').addEventListener('change', function(e) {
            const file = e.target.files[0];
            const previewArea = document.getElementById('preview-area');
            const previewImage = document.getElementById('preview-image');
            
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewImage.src = e.target.result;
                    previewArea.classList.remove('hidden');
                };
                reader.readAsDataURL(file);
            } else {
                previewArea.classList.add('hidden');
            }
        });
    </script>
</body>
</html>