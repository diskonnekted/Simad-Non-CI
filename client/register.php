<?php
require_once '../config/database.php';

// Mulai session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_desa = trim($_POST['nama_desa'] ?? '');
    $kecamatan = trim($_POST['kecamatan'] ?? '');
    $kabupaten = trim($_POST['kabupaten'] ?? '');
    $provinsi = trim($_POST['provinsi'] ?? '');
    $alamat = trim($_POST['alamat'] ?? '');
    $nama_kepala_desa = trim($_POST['nama_kepala_desa'] ?? '');
    $no_hp_kepala_desa = trim($_POST['no_hp_kepala_desa'] ?? '');
    $email_desa = trim($_POST['email_desa'] ?? '');
    $pin = trim($_POST['pin'] ?? '');
    $confirm_pin = trim($_POST['confirm_pin'] ?? '');
    
    // Validasi
    if (empty($nama_desa) || empty($kecamatan) || empty($kabupaten) || empty($provinsi) || empty($alamat)) {
        $error = 'Semua field wajib harus diisi';
    } elseif (empty($nama_kepala_desa) || empty($no_hp_kepala_desa)) {
        $error = 'Nama dan nomor HP kepala desa harus diisi';
    } elseif (empty($pin) || strlen($pin) < 6) {
        $error = 'PIN harus minimal 6 digit';
    } elseif ($pin !== $confirm_pin) {
        $error = 'Konfirmasi PIN tidak cocok';
    } elseif (!preg_match('/^[0-9]+$/', $pin)) {
        $error = 'PIN hanya boleh berisi angka';
    } else {
        try {
            $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8", DB_USER, DB_PASS);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Cek apakah desa sudah terdaftar
            $check_stmt = $pdo->prepare("SELECT id FROM desa WHERE nama_desa = ? AND kecamatan = ?");
            $check_stmt->execute([$nama_desa, $kecamatan]);
            
            if ($check_stmt->rowCount() > 0) {
                $error = 'Desa dengan nama dan kecamatan tersebut sudah terdaftar';
            } else {
                // Hash PIN
                $hashed_pin = password_hash($pin, PASSWORD_DEFAULT);
                
                // Insert desa baru
                $stmt = $pdo->prepare("
                    INSERT INTO desa (
                        nama_desa, alamat, kecamatan, kabupaten, provinsi,
                        nama_kepala_desa, no_hp_kepala_desa, email_desa,
                        status, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'aktif', NOW())
                ");
                
                $stmt->execute([
                    $nama_desa, $alamat, $kecamatan, $kabupaten, $provinsi,
                    $nama_kepala_desa, $no_hp_kepala_desa, $email_desa ?: null
                ]);
                
                $success = 'Registrasi berhasil! Silakan login dengan PIN yang telah dibuat.';
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
    <title>Registrasi Desa - Portal Klien Desa</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .bg-gradient {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .pin-input {
            -webkit-text-security: disc;
            text-security: disc;
        }
    </style>
</head>
<body class="bg-gradient min-h-screen">
    <div class="container mx-auto px-4 py-8">
        <!-- Header -->
        <div class="text-center mb-8">
            <div class="bg-white rounded-full w-20 h-20 mx-auto flex items-center justify-center mb-4 shadow-lg">
                <i class="fas fa-map-marker-alt text-3xl text-blue-600"></i>
            </div>
            <h1 class="text-3xl font-bold text-white mb-2">Registrasi Desa</h1>
            <p class="text-blue-100">Portal Klien Desa - Sistem Manajemen Transaksi</p>
        </div>

        <!-- Registration Form -->
        <div class="max-w-4xl mx-auto bg-white rounded-lg shadow-xl p-8">
            <h2 class="text-2xl font-bold text-gray-800 mb-6 text-center">
                <i class="fas fa-user-plus mr-2"></i>Daftarkan Desa Anda
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
                    <div class="mt-3">
                        <a href="login.php" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition duration-200">
                            <i class="fas fa-sign-in-alt mr-1"></i>Login Sekarang
                        </a>
                    </div>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-8">
                <!-- Informasi Desa -->
                <div class="bg-gray-50 p-6 rounded-lg">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">
                        <i class="fas fa-map-marker-alt mr-2 text-blue-600"></i>
                        Informasi Desa
                    </h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="nama_desa" class="block text-sm font-medium text-gray-700 mb-2">
                                Nama Desa *
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
                                Kecamatan *
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
                            <label for="kabupaten" class="block text-sm font-medium text-gray-700 mb-2">
                                Kabupaten *
                            </label>
                            <input type="text" 
                                   id="kabupaten" 
                                   name="kabupaten" 
                                   value="<?= htmlspecialchars($_POST['kabupaten'] ?? '') ?>"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                   placeholder="Contoh: Banjarnegara"
                                   required>
                        </div>
                        
                        <div>
                            <label for="provinsi" class="block text-sm font-medium text-gray-700 mb-2">
                                Provinsi *
                            </label>
                            <input type="text" 
                                   id="provinsi" 
                                   name="provinsi" 
                                   value="<?= htmlspecialchars($_POST['provinsi'] ?? '') ?>"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                   placeholder="Contoh: Jawa Tengah"
                                   required>
                        </div>
                    </div>
                    
                    <div class="mt-6">
                        <label for="alamat" class="block text-sm font-medium text-gray-700 mb-2">
                            Alamat Lengkap *
                        </label>
                        <textarea id="alamat" 
                                  name="alamat" 
                                  rows="3"
                                  class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                  placeholder="Alamat lengkap kantor desa atau balai desa"
                                  required><?= htmlspecialchars($_POST['alamat'] ?? '') ?></textarea>
                    </div>
                </div>

                <!-- Kontak Person -->
                <div class="bg-gray-50 p-6 rounded-lg">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">
                        <i class="fas fa-user-tie mr-2 text-green-600"></i>
                        Kontak Person
                    </h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="nama_kepala_desa" class="block text-sm font-medium text-gray-700 mb-2">
                                Nama Kepala Desa *
                            </label>
                            <input type="text" 
                                   id="nama_kepala_desa" 
                                   name="nama_kepala_desa" 
                                   value="<?= htmlspecialchars($_POST['nama_kepala_desa'] ?? '') ?>"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                   placeholder="Nama lengkap kepala desa"
                                   required>
                        </div>
                        
                        <div>
                            <label for="no_hp_kepala_desa" class="block text-sm font-medium text-gray-700 mb-2">
                                No. HP Kepala Desa *
                            </label>
                            <input type="tel" 
                                   id="no_hp_kepala_desa" 
                                   name="no_hp_kepala_desa" 
                                   value="<?= htmlspecialchars($_POST['no_hp_kepala_desa'] ?? '') ?>"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                   placeholder="08xxxxxxxxxx"
                                   required>
                        </div>
                        
                        <div class="md:col-span-2">
                            <label for="email_desa" class="block text-sm font-medium text-gray-700 mb-2">
                                Email Desa (Opsional)
                            </label>
                            <input type="email" 
                                   id="email_desa" 
                                   name="email_desa" 
                                   value="<?= htmlspecialchars($_POST['email_desa'] ?? '') ?>"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                   placeholder="contoh@desa.go.id">
                        </div>
                    </div>
                </div>

                <!-- PIN Akses -->
                <div class="bg-yellow-50 p-6 rounded-lg border border-yellow-200">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">
                        <i class="fas fa-lock mr-2 text-yellow-600"></i>
                        PIN Akses Portal
                    </h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="pin" class="block text-sm font-medium text-gray-700 mb-2">
                                PIN (6 digit angka) *
                            </label>
                            <input type="password" 
                                   id="pin" 
                                   name="pin" 
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                   placeholder="Masukkan 6 digit PIN"
                                   maxlength="6"
                                   pattern="[0-9]{6}"
                                   required>
                            <p class="text-xs text-gray-500 mt-1">PIN akan digunakan untuk login ke portal klien</p>
                        </div>
                        
                        <div>
                            <label for="confirm_pin" class="block text-sm font-medium text-gray-700 mb-2">
                                Konfirmasi PIN *
                            </label>
                            <input type="password" 
                                   id="confirm_pin" 
                                   name="confirm_pin" 
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                   placeholder="Ulangi PIN"
                                   maxlength="6"
                                   pattern="[0-9]{6}"
                                   required>
                        </div>
                    </div>
                    
                    <div class="mt-4 p-4 bg-yellow-100 rounded-lg">
                        <h4 class="font-medium text-yellow-800 mb-2">
                            <i class="fas fa-info-circle mr-1"></i>Penting!
                        </h4>
                        <ul class="text-sm text-yellow-700 space-y-1">
                            <li><i class="fas fa-check mr-2"></i>PIN harus 6 digit angka</li>
                            <li><i class="fas fa-check mr-2"></i>Simpan PIN dengan aman</li>
                            <li><i class="fas fa-check mr-2"></i>PIN digunakan untuk mengakses semua fitur portal</li>
                            <li><i class="fas fa-check mr-2"></i>Jangan berikan PIN kepada orang lain</li>
                        </ul>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="text-center">
                    <button type="submit" 
                            class="bg-blue-600 text-white px-8 py-4 rounded-lg hover:bg-blue-700 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition duration-200 font-medium text-lg">
                        <i class="fas fa-user-plus mr-2"></i>Daftarkan Desa
                    </button>
                </div>
            </form>

            <!-- Login Link -->
            <div class="mt-8 pt-6 border-t border-gray-200 text-center">
                <p class="text-gray-600 mb-3">
                    Sudah memiliki akun?
                </p>
                <a href="login.php" class="text-blue-600 hover:text-blue-800 font-medium">
                    <i class="fas fa-sign-in-alt mr-1"></i>Login ke Portal Desa
                </a>
            </div>
        </div>

        <!-- Footer -->
        <div class="text-center mt-8">
            <p class="text-blue-100 text-sm">
                © 2025 Portal Klien Desa - Sistem Manajemen Transaksi
            </p>
        </div>
    </div>

    <script>
        // PIN validation
        document.getElementById('pin').addEventListener('input', function(e) {
            e.target.value = e.target.value.replace(/[^0-9]/g, '');
        });
        
        document.getElementById('confirm_pin').addEventListener('input', function(e) {
            e.target.value = e.target.value.replace(/[^0-9]/g, '');
        });
        
        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const pin = document.getElementById('pin').value;
            const confirmPin = document.getElementById('confirm_pin').value;
            
            if (pin !== confirmPin) {
                e.preventDefault();
                alert('PIN dan konfirmasi PIN tidak cocok!');
                return false;
            }
            
            if (pin.length !== 6) {
                e.preventDefault();
                alert('PIN harus 6 digit!');
                return false;
            }
        });
    </script>
</body>
</html>