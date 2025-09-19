<?php
require_once 'config/database.php';

// Mulai session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_desa = trim($_POST['nama_desa'] ?? '');
    $kecamatan = trim($_POST['kecamatan'] ?? '');
    
    if (empty($nama_desa) || empty($kecamatan)) {
        $error = 'Nama desa dan kecamatan harus diisi';
    } else {
        try {
            $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8", DB_USERNAME, DB_PASSWORD);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Cari desa berdasarkan nama dan kecamatan
            $stmt = $pdo->prepare("SELECT * FROM desa WHERE nama_desa = ? AND kecamatan = ? AND status = 'aktif'");
            $stmt->execute([$nama_desa, $kecamatan]);
            $desa = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($desa) {
                // Set session untuk desa
                $_SESSION['desa_logged_in'] = true;
                $_SESSION['desa_id'] = $desa['id'];
                $_SESSION['desa_nama'] = $desa['nama_desa'];
                $_SESSION['desa_kecamatan'] = $desa['kecamatan'];
                
                header('Location: desa-profile.php');
                exit;
            } else {
                $error = 'Desa tidak ditemukan atau tidak aktif. Pastikan nama desa dan kecamatan sudah benar.';
            }
        } catch (Exception $e) {
            $error = 'Terjadi kesalahan sistem: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Desa - Sistem Manajemen Transaksi Desa</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .bg-gradient {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
    </style>
</head>
<body class="bg-gradient min-h-screen flex items-center justify-center">
    <div class="max-w-md w-full mx-4">
        <!-- Logo/Header -->
        <div class="text-center mb-8">
            <div class="bg-white rounded-full w-20 h-20 mx-auto flex items-center justify-center mb-4 shadow-lg">
                <i class="fas fa-map-marker-alt text-3xl text-blue-600"></i>
            </div>
            <h1 class="text-3xl font-bold text-white mb-2">Portal Desa</h1>
            <p class="text-blue-100">Sistem Manajemen Transaksi Desa</p>
        </div>

        <!-- Login Form -->
        <div class="bg-white rounded-lg shadow-xl p-8">
            <h2 class="text-2xl font-bold text-gray-800 mb-6 text-center">
                <i class="fas fa-sign-in-alt mr-2"></i>Login Desa
            </h2>
            
            <?php if ($error): ?>
                <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6">
                    <i class="fas fa-exclamation-circle mr-2"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-6">
                    <i class="fas fa-check-circle mr-2"></i>
                    <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-6">
                <div>
                    <label for="kecamatan" class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-location-dot mr-1"></i>Kecamatan
                    </label>
                    <select id="kecamatan" 
                            name="kecamatan" 
                            required 
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-200"
                            onchange="loadDesaByKecamatan()">
                        <option value="">Pilih Kecamatan</option>
                    </select>
                    <p class="text-xs text-gray-500 mt-1">Pilih kecamatan tempat desa berada</p>
                </div>

                <div>
                    <label for="nama_desa" class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-map-marker-alt mr-1"></i>Nama Desa
                    </label>
                    <select id="nama_desa" 
                            name="nama_desa" 
                            required 
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-200"
                            disabled>
                        <option value="">Pilih Desa</option>
                    </select>
                    <p class="text-xs text-gray-500 mt-1">Pilih kecamatan terlebih dahulu</p>
                </div>

                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3 mb-4">
                    <p class="text-sm text-yellow-800">
                        <i class="fas fa-info-circle mr-2"></i>
                        <strong>Butuh PIN Login?</strong><br>
                        Hubungi admin di WhatsApp: 
                        <a href="https://wa.me/6285117041846" target="_blank" class="text-blue-600 hover:text-blue-800 font-medium">
                            +62 851-1704-1846
                        </a>
                    </p>
                </div>
                
                <button type="submit" 
                        class="w-full bg-blue-600 text-white py-3 px-4 rounded-lg hover:bg-blue-700 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition duration-200 font-medium">
                    <i class="fas fa-sign-in-alt mr-2"></i>Masuk ke Portal Desa
                </button>
            </form>

            <div class="mt-6 pt-6 border-t border-gray-200">
                <div class="text-center">
                    <p class="text-sm text-gray-600 mb-3">
                        <i class="fas fa-info-circle mr-1"></i>Informasi Login
                    </p>
                    <div class="bg-blue-50 p-4 rounded-lg text-left">
                        <ul class="text-sm text-blue-800 space-y-1">
                            <li><i class="fas fa-check mr-2"></i>Gunakan nama desa dan kecamatan yang terdaftar</li>
                            <li><i class="fas fa-check mr-2"></i>Tidak perlu menggunakan kata 'Desa' atau 'Kecamatan'</li>
                            <li><i class="fas fa-check mr-2"></i>Contoh: Adipasir, Rakit</li>
                        </ul>
                    </div>
                </div>
                
                <div class="text-center mt-4">
                    <a href="index.php" class="text-blue-600 hover:text-blue-800 text-sm">
                        <i class="fas fa-arrow-left mr-1"></i>Kembali ke Beranda
                    </a>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="text-center mt-8">
            <p class="text-blue-100 text-sm">
                © 2025 Sistem Manajemen Transaksi Desa
            </p>
        </div>
    </div>
    
    <script>
        // Load kecamatan data when page loads
        document.addEventListener('DOMContentLoaded', function() {
            loadKecamatanData();
        });
        
        // Load kecamatan data
        async function loadKecamatanData() {
            try {
                const response = await fetch('api/get_desa_data.php?action=get_kecamatan');
                const data = await response.json();
                console.log('Kecamatan data:', data);
                
                if (data.success) {
                    const kecamatanSelect = document.getElementById('kecamatan');
                    kecamatanSelect.innerHTML = '<option value="">Pilih Kecamatan</option>';
                    
                    data.data.forEach(kecamatan => {
                        // Skip empty kecamatan
                        if (kecamatan && kecamatan.trim() !== '') {
                            const option = document.createElement('option');
                            option.value = kecamatan;
                            option.textContent = kecamatan;
                            kecamatanSelect.appendChild(option);
                        }
                    });
                    console.log('Kecamatan options added:', kecamatanSelect.children.length);
                }
            } catch (error) {
                console.error('Error loading kecamatan:', error);
            }
        }
        
        // Load desa by kecamatan
        async function loadDesaByKecamatan() {
            const kecamatan = document.getElementById('kecamatan').value;
            const desaSelect = document.getElementById('nama_desa');
            
            if (!kecamatan) {
                desaSelect.innerHTML = '<option value="">Pilih Desa</option>';
                desaSelect.disabled = true;
                return;
            }
            
            try {
                const response = await fetch(`api/get_desa_data.php?action=get_desa&kecamatan=${encodeURIComponent(kecamatan)}`);
                const data = await response.json();
                
                if (data.success) {
                    desaSelect.innerHTML = '<option value="">Pilih Desa</option>';
                    
                    data.data.forEach(desa => {
                        const option = document.createElement('option');
                        option.value = desa.nama_desa;
                        option.textContent = desa.nama_desa;
                        desaSelect.appendChild(option);
                    });
                    
                    desaSelect.disabled = false;
                }
            } catch (error) {
                console.error('Error loading desa:', error);
            }
        }
    </script>
</body>
</html>