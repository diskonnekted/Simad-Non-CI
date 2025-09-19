<?php
require_once '../config/database.php';

// Mulai session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Redirect jika sudah login
if (isset($_SESSION['desa_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_desa = trim($_POST['nama_desa'] ?? '');
    $kecamatan = trim($_POST['kecamatan'] ?? '');
    $pin = trim($_POST['pin'] ?? '');
    
    if (empty($nama_desa) || empty($kecamatan) || empty($pin)) {
        $error = 'Semua field harus diisi';
    } else {
        try {
            $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8", DB_USER, DB_PASS);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Cari desa berdasarkan nama dan kecamatan
            $stmt = $pdo->prepare("SELECT id, nama_desa, kecamatan, status FROM desa WHERE nama_desa = ? AND kecamatan = ?");
            $stmt->execute([$nama_desa, $kecamatan]);
            $desa = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$desa) {
                $error = 'Desa tidak ditemukan. Pastikan nama desa dan kecamatan benar.';
            } elseif ($desa['status'] !== 'aktif') {
                $error = 'Akun desa tidak aktif. Hubungi administrator.';
            } else {
                // Login berhasil
                $_SESSION['desa_id'] = $desa['id'];
                $_SESSION['desa_nama'] = $desa['nama_desa'];
                $_SESSION['desa_kecamatan'] = $desa['kecamatan'];
                $_SESSION['login_time'] = time();
                
                header('Location: dashboard.php');
                exit;
            }
        } catch (Exception $e) {
            $error = 'Terjadi kesalahan sistem. Silakan coba lagi.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Desa - Portal Klien Desa</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .bg-gradient {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .pin-input {
            font-size: 24px;
            letter-spacing: 8px;
            text-align: center;
        }
    </style>
</head>
<body class="bg-gradient min-h-screen">
    <div class="max-w-6xl mx-auto px-6 py-8">
        <!-- Header -->
        <div class="text-center mb-8">
            <div class="bg-white rounded-full w-20 h-20 mx-auto flex items-center justify-center mb-4 shadow-lg">
                <i class="fas fa-map-marker-alt text-3xl text-blue-600"></i>
            </div>
            <h1 class="text-3xl font-bold text-white mb-2">Portal Klien Desa</h1>
            <p class="text-blue-100">Sistem Manajemen Transaksi & Layanan Desa</p>
        </div>

        <!-- Login Form -->
        <div class="max-w-md mx-auto bg-white rounded-lg shadow-xl p-8">
            <h2 class="text-2xl font-bold text-gray-800 mb-6 text-center">
                <i class="fas fa-sign-in-alt mr-2"></i>Login Desa
            </h2>
            
            <?php if ($error): ?>
                <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6">
                    <i class="fas fa-exclamation-circle mr-2"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-6">
                <div>
                    <label for="nama_desa" class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-map-marker-alt mr-1"></i>Nama Desa
                    </label>
                    <input type="text" 
                           id="nama_desa" 
                           name="nama_desa" 
                           value="<?= htmlspecialchars($_POST['nama_desa'] ?? '') ?>"
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                           placeholder="Contoh: Adipasir"
                           required>
                </div>
                
                <div>
                    <label for="kecamatan" class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-building mr-1"></i>Kecamatan
                    </label>
                    <input type="text" 
                           id="kecamatan" 
                           name="kecamatan" 
                           value="<?= htmlspecialchars($_POST['kecamatan'] ?? '') ?>"
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                           placeholder="Contoh: Rakit"
                           required>
                </div>
                
                <div>
                    <label for="pin" class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-lock mr-1"></i>PIN (6 digit)
                    </label>
                    <input type="password" 
                           id="pin" 
                           name="pin" 
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 pin-input"
                           placeholder="••••••"
                           maxlength="6"
                           pattern="[0-9]{6}"
                           required>
                    <p class="text-xs text-gray-500 mt-1">Masukkan 6 digit PIN yang telah didaftarkan</p>
                </div>

                <button type="submit" 
                        class="w-full bg-blue-600 text-white py-3 rounded-lg hover:bg-blue-700 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition duration-200 font-medium">
                    <i class="fas fa-sign-in-alt mr-2"></i>Masuk ke Portal
                </button>
            </form>

            <!-- Register Link -->
            <div class="mt-6 pt-6 border-t border-gray-200 text-center">
                <p class="text-gray-600 mb-3">
                    Belum memiliki akun?
                </p>
                <a href="register.php" class="text-blue-600 hover:text-blue-800 font-medium">
                    <i class="fas fa-user-plus mr-1"></i>Daftarkan Desa Anda
                </a>
            </div>

            <!-- Help Section -->
            <div class="mt-6 p-4 bg-gray-50 rounded-lg">
                <h3 class="font-medium text-gray-800 mb-2">
                    <i class="fas fa-question-circle mr-1"></i>Butuh Bantuan?
                </h3>
                <ul class="text-sm text-gray-600 space-y-1">
                    <li><i class="fas fa-check mr-2 text-green-500"></i>Pastikan nama desa dan kecamatan benar</li>
                    <li><i class="fas fa-check mr-2 text-green-500"></i>PIN harus 6 digit angka</li>
                    <li><i class="fas fa-check mr-2 text-green-500"></i>Hubungi admin jika lupa PIN</li>
                </ul>
            </div>
        </div>

        <!-- Features Preview -->
        <div class="max-w-4xl mx-auto mt-12">
            <h3 class="text-2xl font-bold text-white text-center mb-8">Fitur Portal Desa</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="bg-white bg-opacity-10 backdrop-blur-sm rounded-lg p-6 text-center">
                    <div class="bg-white rounded-full w-16 h-16 mx-auto flex items-center justify-center mb-4">
                        <i class="fas fa-calendar-alt text-2xl text-blue-600"></i>
                    </div>
                    <h4 class="text-white font-semibold mb-2">Kalender Kunjungan</h4>
                    <p class="text-blue-100 text-sm">Jadwal kunjungan ke desa dan agenda kegiatan</p>
                </div>
                
                <div class="bg-white bg-opacity-10 backdrop-blur-sm rounded-lg p-6 text-center">
                    <div class="bg-white rounded-full w-16 h-16 mx-auto flex items-center justify-center mb-4">
                        <i class="fas fa-shopping-cart text-2xl text-green-600"></i>
                    </div>
                    <h4 class="text-white font-semibold mb-2">Pemesanan</h4>
                    <p class="text-blue-100 text-sm">Pesan produk dan layanan dengan mudah</p>
                </div>
                
                <div class="bg-white bg-opacity-10 backdrop-blur-sm rounded-lg p-6 text-center">
                    <div class="bg-white rounded-full w-16 h-16 mx-auto flex items-center justify-center mb-4">
                        <i class="fas fa-chart-line text-2xl text-purple-600"></i>
                    </div>
                    <h4 class="text-white font-semibold mb-2">Status Keuangan</h4>
                    <p class="text-blue-100 text-sm">Pantau status pembayaran dan keuangan</p>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="text-center mt-12">
            <p class="text-blue-100 text-sm">
                © 2025 Portal Klien Desa - Sistem Manajemen Transaksi
            </p>
        </div>
    </div>

    <script>
        // PIN input validation
        document.getElementById('pin').addEventListener('input', function(e) {
            e.target.value = e.target.value.replace(/[^0-9]/g, '');
        });
        
        // Auto-submit when PIN is complete
        document.getElementById('pin').addEventListener('input', function(e) {
            if (e.target.value.length === 6) {
                // Optional: auto-submit form when PIN is complete
                // document.querySelector('form').submit();
            }
        });
        
        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const pin = document.getElementById('pin').value;
            
            if (pin.length !== 6) {
                e.preventDefault();
                alert('PIN harus 6 digit!');
                return false;
            }
        });
    </script>
</body>
</html>