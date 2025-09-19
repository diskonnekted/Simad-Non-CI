<?php
require_once 'config/database.php';
require_once 'config/auth.php';

// Cek autentikasi dan role
if (!AuthStatic::isLoggedIn()) {
    header('Location: login.php');
    exit;
}

if (!AuthStatic::hasRole(['admin', 'sales'])) {
    header('Location: index.php?error=access_denied');
    exit;
}

$user = AuthStatic::getCurrentUser();
/** @var Database $db */
$db = getDatabase();

$error = '';
$success = '';
$form_data = [
    'nama_kategori' => '',
    'deskripsi' => ''
];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_kategori = trim($_POST['nama_kategori'] ?? '');
    $deskripsi = trim($_POST['deskripsi'] ?? '');
    
    $form_data = [
        'nama_kategori' => $nama_kategori,
        'deskripsi' => $deskripsi
    ];
    
    // Validasi
    $errors = [];
    
    if (empty($nama_kategori)) {
        $errors[] = 'Nama kategori harus diisi';
    } elseif (strlen($nama_kategori) > 50) {
        $errors[] = 'Nama kategori maksimal 50 karakter';
    }
    
    if (!empty($deskripsi) && strlen($deskripsi) > 500) {
        $errors[] = 'Deskripsi maksimal 500 karakter';
    }
    
    // Cek duplikasi nama kategori
    if (empty($errors)) {
        $existing = $db->select(
            "SELECT id FROM kategori_produk WHERE nama_kategori = ?",
            [$nama_kategori]
        );
        
        if (!empty($existing)) {
            $errors[] = 'Nama kategori sudah digunakan';
        }
    }
    
    if (empty($errors)) {
        try {
            $db->execute(
                "INSERT INTO kategori_produk (nama_kategori, deskripsi, created_at) VALUES (?, ?, NOW())",
                [$nama_kategori, $deskripsi]
            );
            
            header('Location: kategori.php?success=created');
            exit;
        } catch (Exception $e) {
            $error = 'Gagal menyimpan kategori. Silakan coba lagi.';
        }
    } else {
        $error = implode('<br>', $errors);
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Kategori Produk - Sistem Manajemen Desa</title>
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-50 font-sans">
    <?php include 'layouts/header.php'; ?>
    
    <!-- Page Header -->
    <div class="bg-white shadow-sm border-b border-gray-200 mb-6">
        <div class="px-6 py-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 flex items-center">
                        <i class="fa fa-plus text-primary-600 mr-3"></i>
                        Tambah Kategori Produk
                    </h1>
                    <p class="text-sm text-gray-600 mt-1">Buat kategori baru untuk mengorganisir produk</p>
                </div>
                <nav class="mt-4 sm:mt-0 text-sm text-gray-500">
                    <a href="index.php" class="hover:text-primary-600">Dashboard</a>
                    <span class="mx-2">/</span>
                    <a href="produk.php" class="hover:text-primary-600">Produk</a>
                    <span class="mx-2">/</span>
                    <a href="kategori.php" class="hover:text-primary-600">Kategori</a>
                    <span class="mx-2">/</span>
                    <span class="text-gray-900">Tambah</span>
                </nav>
            </div>
        </div>
    </div>

    <!-- Main Container -->
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Alert Messages -->
        <?php if ($error): ?>
            <div class="mb-6" data-alert>
                <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                    <div class="flex items-center">
                        <i class="fa fa-exclamation-triangle text-red-600 mr-3"></i>
                        <div class="text-red-800">
                            <?= $error ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Form Section -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                            <i class="fa fa-tag text-primary-600 mr-2"></i>
                            Informasi Kategori
                        </h3>
                    </div>
                    <div class="p-6">
                        <form method="POST" id="categoryForm">
                            <div class="space-y-6">
                                <div>
                                    <label for="nama_kategori" class="block text-sm font-medium text-gray-700 mb-2">
                                        Nama Kategori <span class="text-red-500">*</span>
                                    </label>
                                    <input type="text" 
                                           id="nama_kategori" 
                                           name="nama_kategori" 
                                           value="<?= htmlspecialchars($form_data['nama_kategori']) ?>"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500" 
                                           placeholder="Contoh: Komputer & Laptop"
                                           maxlength="50"
                                           required>
                                    <p class="text-sm text-gray-500 mt-1">Maksimal 50 karakter</p>
                                </div>

                                <div>
                                    <label for="deskripsi" class="block text-sm font-medium text-gray-700 mb-2">
                                        Deskripsi
                                    </label>
                                    <textarea id="deskripsi" 
                                              name="deskripsi" 
                                              rows="4"
                                              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500" 
                                              placeholder="Deskripsi singkat tentang kategori ini..."
                                              maxlength="500"><?= htmlspecialchars($form_data['deskripsi']) ?></textarea>
                                    <p class="text-sm text-gray-500 mt-1">Maksimal 500 karakter (opsional)</p>
                                </div>
                            </div>

                            <div class="flex justify-end gap-3 pt-6 border-t border-gray-200 mt-6">
                                <a href="kategori.php" 
                                   class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition-colors">
                                    <i class="fa fa-times mr-2"></i>Batal
                                </a>
                                <button type="submit" 
                                        class="px-4 py-2 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700 focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 transition-colors">
                                    <i class="fa fa-save mr-2"></i>Simpan Kategori
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Info Section -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                            <i class="fa fa-info-circle text-blue-600 mr-2"></i>
                            Informasi
                        </h3>
                    </div>
                    <div class="p-6">
                        <div class="space-y-4">
                            <div class="flex items-start">
                                <i class="fa fa-lightbulb text-yellow-500 mt-1 mr-3"></i>
                                <div>
                                    <h4 class="text-sm font-medium text-gray-900 mb-1">Tips Penamaan</h4>
                                    <p class="text-sm text-gray-600">Gunakan nama yang jelas dan mudah dipahami untuk memudahkan pencarian produk.</p>
                                </div>
                            </div>
                            
                            <div class="flex items-start">
                                <i class="fa fa-tag text-blue-500 mt-1 mr-3"></i>
                                <div>
                                    <h4 class="text-sm font-medium text-gray-900 mb-1">Contoh Kategori</h4>
                                    <ul class="text-sm text-gray-600 space-y-1">
                                        <li>• Komputer & Laptop</li>
                                        <li>• Printer & Scanner</li>
                                        <li>• ATK Umum</li>
                                        <li>• Furniture Kantor</li>
                                    </ul>
                                </div>
                            </div>
                            
                            <div class="flex items-start">
                                <i class="fa fa-check-circle text-green-500 mt-1 mr-3"></i>
                                <div>
                                    <h4 class="text-sm font-medium text-gray-900 mb-1">Manfaat Kategori</h4>
                                    <p class="text-sm text-gray-600">Memudahkan pencarian, filtering, dan pelaporan produk berdasarkan jenisnya.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Preview Card -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 mt-6">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                            <i class="fa fa-eye text-purple-600 mr-2"></i>
                            Preview
                        </h3>
                    </div>
                    <div class="p-6">
                        <div class="border border-gray-200 rounded-lg p-4">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 h-10 w-10">
                                    <div class="h-10 w-10 rounded-lg bg-primary-100 flex items-center justify-center">
                                        <i class="fa fa-tag text-primary-600"></i>
                                    </div>
                                </div>
                                <div class="ml-4">
                                    <div class="text-sm font-medium text-gray-900" id="previewName">
                                        Nama Kategori
                                    </div>
                                    <div class="text-sm text-gray-500" id="previewDesc">
                                        Deskripsi kategori
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include 'layouts/footer.php'; ?>

    <script>
        // Live preview
        document.getElementById('nama_kategori').addEventListener('input', function() {
            const value = this.value || 'Nama Kategori';
            document.getElementById('previewName').textContent = value;
        });

        document.getElementById('deskripsi').addEventListener('input', function() {
            const value = this.value || 'Deskripsi kategori';
            document.getElementById('previewDesc').textContent = value;
        });

        // Form validation
        document.getElementById('categoryForm').addEventListener('submit', function(e) {
            const namaKategori = document.getElementById('nama_kategori').value.trim();
            
            if (!namaKategori) {
                e.preventDefault();
                alert('Nama kategori harus diisi!');
                document.getElementById('nama_kategori').focus();
                return false;
            }
            
            if (namaKategori.length > 50) {
                e.preventDefault();
                alert('Nama kategori maksimal 50 karakter!');
                document.getElementById('nama_kategori').focus();
                return false;
            }
            
            const deskripsi = document.getElementById('deskripsi').value.trim();
            if (deskripsi.length > 500) {
                e.preventDefault();
                alert('Deskripsi maksimal 500 karakter!');
                document.getElementById('deskripsi').focus();
                return false;
            }
        });

        // Auto hide alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('[data-alert]');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.transition = 'opacity 0.5s';
                    alert.style.opacity = '0';
                    setTimeout(() => alert.remove(), 500);
                }, 5000);
            });
        });
    </script>
</body>
</html>