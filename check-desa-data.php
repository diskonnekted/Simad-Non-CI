<?php
require_once 'config/database.php';

// Initialize database connection
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Handle form submission for fixing data
if ($_POST && isset($_POST['action'])) {
    if ($_POST['action'] === 'fix_empty') {
        try {
            // Update empty kecamatan
            $stmt = $pdo->prepare("UPDATE desa SET kecamatan = ? WHERE id = ?");
            foreach ($_POST['kecamatan'] as $id => $kecamatan) {
                if (!empty($kecamatan)) {
                    $stmt->execute([$kecamatan, $id]);
                }
            }
            
            // Update empty nama_desa
            $stmt = $pdo->prepare("UPDATE desa SET nama_desa = ? WHERE id = ?");
            foreach ($_POST['nama_desa'] as $id => $nama_desa) {
                if (!empty($nama_desa)) {
                    $stmt->execute([$nama_desa, $id]);
                }
            }
            
            $success_message = "Data berhasil diperbaiki!";
        } catch(PDOException $e) {
            $error_message = "Error: " . $e->getMessage();
        }
    }
    
    if ($_POST['action'] === 'delete_empty') {
        try {
            $stmt = $pdo->prepare("DELETE FROM desa WHERE (nama_desa IS NULL OR nama_desa = '' OR TRIM(nama_desa) = '') AND (kecamatan IS NULL OR kecamatan = '' OR TRIM(kecamatan) = '')");
            $stmt->execute();
            $deleted_count = $stmt->rowCount();
            $success_message = "$deleted_count data kosong berhasil dihapus!";
        } catch(PDOException $e) {
            $error_message = "Error: " . $e->getMessage();
        }
    }
}

// Get statistics
$stats = [];

// Total desa
$stmt = $pdo->query("SELECT COUNT(*) as total FROM desa");
$stats['total'] = $stmt->fetch()['total'];

// Empty nama_desa
$stmt = $pdo->query("SELECT COUNT(*) as count FROM desa WHERE nama_desa IS NULL OR nama_desa = '' OR TRIM(nama_desa) = ''");
$stats['empty_nama'] = $stmt->fetch()['count'];

// Empty kecamatan
$stmt = $pdo->query("SELECT COUNT(*) as count FROM desa WHERE kecamatan IS NULL OR kecamatan = '' OR TRIM(kecamatan) = ''");
$stats['empty_kecamatan'] = $stmt->fetch()['count'];

// Both empty
$stmt = $pdo->query("SELECT COUNT(*) as count FROM desa WHERE (nama_desa IS NULL OR nama_desa = '' OR TRIM(nama_desa) = '') AND (kecamatan IS NULL OR kecamatan = '' OR TRIM(kecamatan) = '')");
$stats['both_empty'] = $stmt->fetch()['count'];

// Get problematic data
$stmt = $pdo->query("SELECT * FROM desa WHERE nama_desa IS NULL OR nama_desa = '' OR TRIM(nama_desa) = '' OR kecamatan IS NULL OR kecamatan = '' OR TRIM(kecamatan) = '' ORDER BY id");
$problematic_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get unique kecamatan for suggestions
$stmt = $pdo->query("SELECT DISTINCT kecamatan FROM desa WHERE kecamatan IS NOT NULL AND kecamatan != '' AND TRIM(kecamatan) != '' ORDER BY kecamatan");
$kecamatan_list = $stmt->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cek & Perbaiki Data Desa - SIMAD</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8">
        <!-- Header -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-800">
                        <i class="fas fa-database mr-3 text-blue-600"></i>
                        Cek & Perbaiki Data Desa
                    </h1>
                    <p class="text-gray-600 mt-2">Periksa dan perbaiki data desa dan kecamatan yang kosong atau tidak lengkap</p>
                </div>
                <a href="index.php" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg transition duration-200">
                    <i class="fas fa-arrow-left mr-2"></i>Kembali
                </a>
            </div>
        </div>

        <!-- Messages -->
        <?php if (isset($success_message)): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
            <i class="fas fa-check-circle mr-2"></i><?= htmlspecialchars($success_message) ?>
        </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
            <i class="fas fa-exclamation-circle mr-2"></i><?= htmlspecialchars($error_message) ?>
        </div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
            <div class="bg-blue-500 text-white p-6 rounded-lg">
                <div class="flex items-center">
                    <i class="fas fa-map-marker-alt text-2xl mr-4"></i>
                    <div>
                        <p class="text-blue-100">Total Desa</p>
                        <p class="text-2xl font-bold"><?= $stats['total'] ?></p>
                    </div>
                </div>
            </div>
            <div class="bg-yellow-500 text-white p-6 rounded-lg">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-triangle text-2xl mr-4"></i>
                    <div>
                        <p class="text-yellow-100">Nama Desa Kosong</p>
                        <p class="text-2xl font-bold"><?= $stats['empty_nama'] ?></p>
                    </div>
                </div>
            </div>
            <div class="bg-orange-500 text-white p-6 rounded-lg">
                <div class="flex items-center">
                    <i class="fas fa-map text-2xl mr-4"></i>
                    <div>
                        <p class="text-orange-100">Kecamatan Kosong</p>
                        <p class="text-2xl font-bold"><?= $stats['empty_kecamatan'] ?></p>
                    </div>
                </div>
            </div>
            <div class="bg-red-500 text-white p-6 rounded-lg">
                <div class="flex items-center">
                    <i class="fas fa-times-circle text-2xl mr-4"></i>
                    <div>
                        <p class="text-red-100">Keduanya Kosong</p>
                        <p class="text-2xl font-bold"><?= $stats['both_empty'] ?></p>
                    </div>
                </div>
            </div>
        </div>

        <?php if (count($problematic_data) > 0): ?>
        <!-- Fix Form -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4">
                <i class="fas fa-tools mr-2 text-blue-600"></i>
                Data yang Perlu Diperbaiki (<?= count($problematic_data) ?> record)
            </h2>
            
            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="fix_empty">
                
                <div class="overflow-x-auto">
                    <table class="min-w-full table-auto">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama Desa</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kecamatan</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kabupaten</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($problematic_data as $row): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-2 text-sm text-gray-900"><?= $row['id'] ?></td>
                                <td class="px-4 py-2">
                                    <?php if (empty(trim($row['nama_desa']))): ?>
                                        <input type="text" 
                                               name="nama_desa[<?= $row['id'] ?>]" 
                                               class="w-full px-3 py-2 border border-red-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                               placeholder="Masukkan nama desa">
                                    <?php else: ?>
                                        <span class="text-sm text-gray-900"><?= htmlspecialchars($row['nama_desa']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-2">
                                    <?php if (empty(trim($row['kecamatan']))): ?>
                                        <select name="kecamatan[<?= $row['id'] ?>]" 
                                                class="w-full px-3 py-2 border border-red-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                            <option value="">Pilih Kecamatan</option>
                                            <?php foreach ($kecamatan_list as $kec): ?>
                                                <option value="<?= htmlspecialchars($kec) ?>"><?= htmlspecialchars($kec) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php else: ?>
                                        <span class="text-sm text-gray-900"><?= htmlspecialchars($row['kecamatan']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-2 text-sm text-gray-900"><?= htmlspecialchars($row['kabupaten'] ?? '-') ?></td>
                                <td class="px-4 py-2">
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full <?= $row['status'] === 'aktif' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                        <?= htmlspecialchars($row['status'] ?? 'tidak diketahui') ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="flex justify-between items-center pt-4">
                    <button type="submit" 
                            class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg transition duration-200">
                        <i class="fas fa-save mr-2"></i>Simpan Perbaikan
                    </button>
                </div>
            </form>
        </div>

        <!-- Danger Zone -->
        <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-red-500">
            <h2 class="text-xl font-bold text-red-600 mb-4">
                <i class="fas fa-exclamation-triangle mr-2"></i>
                Danger Zone
            </h2>
            <p class="text-gray-600 mb-4">Hapus semua data yang nama desa dan kecamatannya kosong. <strong>Tindakan ini tidak dapat dibatalkan!</strong></p>
            
            <?php if ($stats['both_empty'] > 0): ?>
            <form method="POST" onsubmit="return confirm('Apakah Anda yakin ingin menghapus <?= $stats['both_empty'] ?> data kosong? Tindakan ini tidak dapat dibatalkan!')">
                <input type="hidden" name="action" value="delete_empty">
                <button type="submit" 
                        class="bg-red-600 hover:bg-red-700 text-white px-6 py-3 rounded-lg transition duration-200">
                    <i class="fas fa-trash mr-2"></i>Hapus <?= $stats['both_empty'] ?> Data Kosong
                </button>
            </form>
            <?php else: ?>
            <p class="text-green-600 font-medium">
                <i class="fas fa-check-circle mr-2"></i>
                Tidak ada data yang perlu dihapus.
            </p>
            <?php endif; ?>
        </div>
        
        <?php else: ?>
        <!-- No Issues -->
        <div class="bg-white rounded-lg shadow-md p-8 text-center">
            <i class="fas fa-check-circle text-6xl text-green-500 mb-4"></i>
            <h2 class="text-2xl font-bold text-gray-800 mb-2">Data Sudah Lengkap!</h2>
            <p class="text-gray-600">Semua data desa dan kecamatan sudah terisi dengan lengkap.</p>
        </div>
        <?php endif; ?>
    </div>

    <script>
        // Auto-refresh after successful operation
        <?php if (isset($success_message)): ?>
        setTimeout(function() {
            window.location.reload();
        }, 2000);
        <?php endif; ?>
    </script>
</body>
</html>