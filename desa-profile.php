<?php
require_once 'config/database.php';

// Mulai session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Cek apakah desa sudah login
if (!isset($_SESSION['desa_logged_in']) || !$_SESSION['desa_logged_in']) {
    header('Location: desa-login.php');
    exit;
}

$error = '';
$success = '';
$desa_id = $_SESSION['desa_id'];

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8", DB_USERNAME, DB_PASSWORD);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Ambil data desa
    $stmt = $pdo->prepare("SELECT * FROM desa WHERE id = ?");
    $stmt->execute([$desa_id]);
    $desa = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$desa) {
        header('Location: desa-login.php?error=session_expired');
        exit;
    }
} catch (Exception $e) {
    $error = 'Terjadi kesalahan sistem: ' . $e->getMessage();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $alamat = trim($_POST['alamat'] ?? '');
    $kabupaten = trim($_POST['kabupaten'] ?? '');
    $provinsi = trim($_POST['provinsi'] ?? '');
    $kode_pos = trim($_POST['kode_pos'] ?? '');
    $nama_kepala_desa = trim($_POST['nama_kepala_desa'] ?? '');
    $no_hp_kepala_desa = trim($_POST['no_hp_kepala_desa'] ?? '');
    $nama_sekdes = trim($_POST['nama_sekdes'] ?? '');
    $no_hp_sekdes = trim($_POST['no_hp_sekdes'] ?? '');
    $nama_admin_it = trim($_POST['nama_admin_it'] ?? '');
    $no_hp_admin_it = trim($_POST['no_hp_admin_it'] ?? '');
    $email_desa = trim($_POST['email_desa'] ?? '');
    $catatan_khusus = trim($_POST['catatan_khusus'] ?? '');
    
    try {
        $stmt = $pdo->prepare("
            UPDATE desa SET 
                alamat = ?, kabupaten = ?, provinsi = ?, kode_pos = ?,
                nama_kepala_desa = ?, no_hp_kepala_desa = ?,
                nama_sekdes = ?, no_hp_sekdes = ?,
                nama_admin_it = ?, no_hp_admin_it = ?,
                email_desa = ?, catatan_khusus = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        
        $stmt->execute([
            $alamat, $kabupaten, $provinsi, $kode_pos,
            $nama_kepala_desa, $no_hp_kepala_desa,
            $nama_sekdes, $no_hp_sekdes,
            $nama_admin_it, $no_hp_admin_it,
            $email_desa ?: null, $catatan_khusus,
            $desa_id
        ]);
        
        $success = 'Data desa berhasil diperbarui!';
        
        // Refresh data desa
        $stmt = $pdo->prepare("SELECT * FROM desa WHERE id = ?");
        $stmt->execute([$desa_id]);
        $desa = $stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        $error = 'Gagal memperbarui data: ' . $e->getMessage();
    }
}

// Handle logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header('Location: desa-login.php?success=logout');
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Desa <?= htmlspecialchars($desa['nama_desa']) ?> - Portal Desa</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <!-- Header -->
    <header class="bg-white shadow-sm border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center">
                    <div class="bg-blue-600 rounded-lg p-2 mr-3">
                        <i class="fas fa-map-marker-alt text-white text-xl"></i>
                    </div>
                    <div>
                        <h1 class="text-xl font-bold text-gray-900">
                            Desa <?= htmlspecialchars($desa['nama_desa']) ?>
                        </h1>
                        <p class="text-sm text-gray-600">
                            Kecamatan <?= htmlspecialchars($desa['kecamatan']) ?>
                        </p>
                    </div>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-sm text-gray-600">
                        <i class="fas fa-user mr-1"></i>
                        Portal Desa
                    </span>
                    <a href="?action=logout" 
                       class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition duration-200">
                        <i class="fas fa-sign-out-alt mr-1"></i>Logout
                    </a>
                </div>
            </div>
        </div>
    </header>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Alert Messages -->
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

        <!-- Welcome Section -->
        <div class="bg-white rounded-lg shadow-sm p-6 mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-2xl font-bold text-gray-900 mb-2">
                        <i class="fas fa-home mr-2 text-blue-600"></i>
                        Selamat Datang di Portal Desa
                    </h2>
                    <p class="text-gray-600">
                        Lengkapi data desa Anda untuk memudahkan proses transaksi dan komunikasi.
                    </p>
                </div>
                <div class="text-right">
                    <div class="bg-blue-50 px-4 py-2 rounded-lg">
                        <p class="text-sm text-blue-600 font-medium">Status Desa</p>
                        <p class="text-lg font-bold text-blue-800 capitalize">
                            <?= htmlspecialchars($desa['status']) ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Form Data Desa -->
        <form method="POST" class="space-y-8">
            <!-- Informasi Dasar -->
            <div class="bg-white rounded-lg shadow-sm p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-6">
                    <i class="fas fa-info-circle mr-2 text-blue-600"></i>
                    Informasi Dasar Desa
                </h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Nama Desa
                        </label>
                        <input type="text" 
                               value="<?= htmlspecialchars($desa['nama_desa']) ?>"
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg bg-gray-50"
                               readonly>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Kecamatan
                        </label>
                        <input type="text" 
                               value="<?= htmlspecialchars($desa['kecamatan']) ?>"
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg bg-gray-50"
                               readonly>
                    </div>
                    
                    <div>
                        <label for="kabupaten" class="block text-sm font-medium text-gray-700 mb-2">
                            Kabupaten *
                        </label>
                        <input type="text" 
                               id="kabupaten" 
                               name="kabupaten" 
                               value="<?= htmlspecialchars($desa['kabupaten']) ?>"
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
                               value="<?= htmlspecialchars($desa['provinsi']) ?>"
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                               placeholder="Contoh: Jawa Tengah"
                               required>
                    </div>
                    
                    <div>
                        <label for="kode_pos" class="block text-sm font-medium text-gray-700 mb-2">
                            Kode Pos
                        </label>
                        <input type="text" 
                               id="kode_pos" 
                               name="kode_pos" 
                               value="<?= htmlspecialchars($desa['kode_pos']) ?>"
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                               placeholder="Contoh: 53415">
                    </div>
                    
                    <div>
                        <label for="email_desa" class="block text-sm font-medium text-gray-700 mb-2">
                            Email Desa
                        </label>
                        <input type="email" 
                               id="email_desa" 
                               name="email_desa" 
                               value="<?= htmlspecialchars($desa['email_desa']) ?>"
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                               placeholder="contoh@desa.go.id">
                    </div>
                </div>
                
                <div class="mt-6">
                    <label for="alamat" class="block text-sm font-medium text-gray-700 mb-2">
                        Alamat Lengkap Kantor Desa *
                    </label>
                    <textarea id="alamat" 
                              name="alamat" 
                              rows="3"
                              class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                              placeholder="Alamat lengkap kantor desa atau balai desa"
                              required><?= htmlspecialchars($desa['alamat']) ?></textarea>
                </div>
            </div>

            <!-- Kontak Person -->
            <div class="bg-white rounded-lg shadow-sm p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-6">
                    <i class="fas fa-users mr-2 text-green-600"></i>
                    Kontak Person Desa
                </h3>
                
                <!-- Kepala Desa -->
                <div class="mb-8">
                    <h4 class="text-md font-medium text-gray-800 mb-4">
                        <i class="fas fa-user-tie mr-2"></i>Kepala Desa
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="nama_kepala_desa" class="block text-sm font-medium text-gray-700 mb-2">
                                Nama Kepala Desa
                            </label>
                            <input type="text" 
                                   id="nama_kepala_desa" 
                                   name="nama_kepala_desa" 
                                   value="<?= htmlspecialchars($desa['nama_kepala_desa']) ?>"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                   placeholder="Nama lengkap kepala desa">
                        </div>
                        <div>
                            <label for="no_hp_kepala_desa" class="block text-sm font-medium text-gray-700 mb-2">
                                No. HP Kepala Desa
                            </label>
                            <input type="tel" 
                                   id="no_hp_kepala_desa" 
                                   name="no_hp_kepala_desa" 
                                   value="<?= htmlspecialchars($desa['no_hp_kepala_desa']) ?>"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                   placeholder="08xxxxxxxxxx">
                        </div>
                    </div>
                </div>
                
                <!-- Sekretaris Desa -->
                <div class="mb-8">
                    <h4 class="text-md font-medium text-gray-800 mb-4">
                        <i class="fas fa-user-edit mr-2"></i>Sekretaris Desa
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="nama_sekdes" class="block text-sm font-medium text-gray-700 mb-2">
                                Nama Sekretaris Desa
                            </label>
                            <input type="text" 
                                   id="nama_sekdes" 
                                   name="nama_sekdes" 
                                   value="<?= htmlspecialchars($desa['nama_sekdes']) ?>"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                   placeholder="Nama lengkap sekretaris desa">
                        </div>
                        <div>
                            <label for="no_hp_sekdes" class="block text-sm font-medium text-gray-700 mb-2">
                                No. HP Sekretaris Desa
                            </label>
                            <input type="tel" 
                                   id="no_hp_sekdes" 
                                   name="no_hp_sekdes" 
                                   value="<?= htmlspecialchars($desa['no_hp_sekdes']) ?>"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                   placeholder="08xxxxxxxxxx">
                        </div>
                    </div>
                </div>
                
                <!-- Admin IT -->
                <div>
                    <h4 class="text-md font-medium text-gray-800 mb-4">
                        <i class="fas fa-laptop mr-2"></i>Admin IT / Operator
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="nama_admin_it" class="block text-sm font-medium text-gray-700 mb-2">
                                Nama Admin IT
                            </label>
                            <input type="text" 
                                   id="nama_admin_it" 
                                   name="nama_admin_it" 
                                   value="<?= htmlspecialchars($desa['nama_admin_it']) ?>"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                   placeholder="Nama lengkap admin IT">
                        </div>
                        <div>
                            <label for="no_hp_admin_it" class="block text-sm font-medium text-gray-700 mb-2">
                                No. HP Admin IT
                            </label>
                            <input type="tel" 
                                   id="no_hp_admin_it" 
                                   name="no_hp_admin_it" 
                                   value="<?= htmlspecialchars($desa['no_hp_admin_it']) ?>"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                   placeholder="08xxxxxxxxxx">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Catatan Khusus -->
            <div class="bg-white rounded-lg shadow-sm p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-6">
                    <i class="fas fa-sticky-note mr-2 text-yellow-600"></i>
                    Catatan Khusus
                </h3>
                
                <div>
                    <label for="catatan_khusus" class="block text-sm font-medium text-gray-700 mb-2">
                        Catatan atau Informasi Tambahan
                    </label>
                    <textarea id="catatan_khusus" 
                              name="catatan_khusus" 
                              rows="4"
                              class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                              placeholder="Informasi tambahan tentang desa, kebutuhan khusus, atau catatan lainnya..."><?= htmlspecialchars($desa['catatan_khusus']) ?></textarea>
                </div>
            </div>

            <!-- Submit Button -->
            <div class="bg-white rounded-lg shadow-sm p-6">
                <div class="flex justify-between items-center">
                    <div>
                        <p class="text-sm text-gray-600">
                            <i class="fas fa-info-circle mr-1"></i>
                            Data yang Anda masukkan akan membantu kami memberikan layanan yang lebih baik.
                        </p>
                    </div>
                    <button type="submit" 
                            class="bg-blue-600 text-white px-8 py-3 rounded-lg hover:bg-blue-700 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition duration-200 font-medium">
                        <i class="fas fa-save mr-2"></i>Simpan Data Desa
                    </button>
                </div>
            </div>
        </form>

        <!-- Footer Info -->
        <div class="bg-blue-50 rounded-lg p-6 mt-8">
            <div class="flex items-start space-x-4">
                <div class="bg-blue-600 rounded-full p-2">
                    <i class="fas fa-lightbulb text-white"></i>
                </div>
                <div>
                    <h4 class="font-medium text-blue-900 mb-2">Tips Pengisian Data</h4>
                    <ul class="text-sm text-blue-800 space-y-1">
                        <li><i class="fas fa-check mr-2"></i>Pastikan data kontak yang dimasukkan aktif dan dapat dihubungi</li>
                        <li><i class="fas fa-check mr-2"></i>Alamat email akan digunakan untuk komunikasi resmi</li>
                        <li><i class="fas fa-check mr-2"></i>Data yang lengkap akan mempercepat proses transaksi</li>
                        <li><i class="fas fa-check mr-2"></i>Anda dapat memperbarui data kapan saja melalui portal ini</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</body>
</html>