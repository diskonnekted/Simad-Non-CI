<?php
/**
 * Halaman Admin untuk Pembersihan Data Percobaan
 * 
 * Halaman ini menyediakan interface untuk menjalankan script pembersihan data
 * dengan konfirmasi dan validasi yang ketat sebelum masuk produksi.
 * 
 * @author SMD System
 * @date 2025-01-20
 */

require_once 'config/database.php';
require_once 'config/auth.php';

// Cek apakah user adalah admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$message = '';
$error = '';
$cleanup_results = [];

// Proses form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $confirmation = $_POST['confirmation'] ?? '';
    
    // Validasi konfirmasi
    if ($confirmation !== 'HAPUS DATA PERCOBAAN') {
        $error = 'Konfirmasi tidak sesuai. Ketik "HAPUS DATA PERCOBAAN" untuk melanjutkan.';
    } else {
        try {
            $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            switch ($action) {
                case 'backup':
                    // Jalankan script backup
                    $backup_script = __DIR__ . '/scripts/backup_database.php';
                    if (file_exists($backup_script)) {
                        ob_start();
                        include $backup_script;
                        $backup_output = ob_get_clean();
                        $message = 'Backup database berhasil dibuat.';
                        $cleanup_results['backup'] = $backup_output;
                    } else {
                        $error = 'File backup script tidak ditemukan.';
                    }
                    break;
                    
                case 'identify':
                    // Jalankan script identifikasi
                    $identify_script = __DIR__ . '/scripts/identify_test_data.php';
                    if (file_exists($identify_script)) {
                        ob_start();
                        include $identify_script;
                        $identify_output = ob_get_clean();
                        $message = 'Identifikasi data percobaan selesai.';
                        $cleanup_results['identify'] = $identify_output;
                    } else {
                        $error = 'File identify script tidak ditemukan.';
                    }
                    break;
                    
                case 'cleanup_products':
                    // Jalankan script cleanup produk
                    $sql_file = __DIR__ . '/scripts/cleanup_products.sql';
                    if (file_exists($sql_file)) {
                        $sql_content = file_get_contents($sql_file);
                        $statements = explode(';', $sql_content);
                        
                        $pdo->beginTransaction();
                        $results = [];
                        
                        foreach ($statements as $statement) {
                            $statement = trim($statement);
                            if (!empty($statement) && !preg_match('/^\s*--/', $statement)) {
                                try {
                                    $stmt = $pdo->prepare($statement);
                                    $stmt->execute();
                                    if ($stmt->rowCount() > 0) {
                                        $results[] = "Executed: " . substr($statement, 0, 50) . "... (" . $stmt->rowCount() . " rows affected)";
                                    }
                                } catch (Exception $e) {
                                    // Skip errors for non-critical statements
                                    if (!preg_match('/(CREATE TABLE|ALTER TABLE)/', $statement)) {
                                        $results[] = "Warning: " . $e->getMessage();
                                    }
                                }
                            }
                        }
                        
                        $pdo->commit();
                        $message = 'Pembersihan data produk berhasil.';
                        $cleanup_results['cleanup_products'] = implode("\n", $results);
                    } else {
                        $error = 'File cleanup products script tidak ditemukan.';
                    }
                    break;
                    
                case 'cleanup_transactions':
                    // Jalankan script cleanup transaksi
                    $sql_file = __DIR__ . '/scripts/cleanup_transactions.sql';
                    if (file_exists($sql_file)) {
                        $sql_content = file_get_contents($sql_file);
                        $statements = explode(';', $sql_content);
                        
                        $pdo->beginTransaction();
                        $results = [];
                        
                        foreach ($statements as $statement) {
                            $statement = trim($statement);
                            if (!empty($statement) && !preg_match('/^\s*--/', $statement)) {
                                try {
                                    $stmt = $pdo->prepare($statement);
                                    $stmt->execute();
                                    if ($stmt->rowCount() > 0) {
                                        $results[] = "Executed: " . substr($statement, 0, 50) . "... (" . $stmt->rowCount() . " rows affected)";
                                    }
                                } catch (Exception $e) {
                                    if (!preg_match('/(CREATE TABLE|ALTER TABLE)/', $statement)) {
                                        $results[] = "Warning: " . $e->getMessage();
                                    }
                                }
                            }
                        }
                        
                        $pdo->commit();
                        $message = 'Pembersihan data transaksi berhasil.';
                        $cleanup_results['cleanup_transactions'] = implode("\n", $results);
                    } else {
                        $error = 'File cleanup transactions script tidak ditemukan.';
                    }
                    break;
                    
                case 'cleanup_other':
                    // Jalankan script cleanup data lainnya
                    $sql_file = __DIR__ . '/scripts/cleanup_other_data.sql';
                    if (file_exists($sql_file)) {
                        $sql_content = file_get_contents($sql_file);
                        $statements = explode(';', $sql_content);
                        
                        $pdo->beginTransaction();
                        $results = [];
                        
                        foreach ($statements as $statement) {
                            $statement = trim($statement);
                            if (!empty($statement) && !preg_match('/^\s*--/', $statement)) {
                                try {
                                    $stmt = $pdo->prepare($statement);
                                    $stmt->execute();
                                    if ($stmt->rowCount() > 0) {
                                        $results[] = "Executed: " . substr($statement, 0, 50) . "... (" . $stmt->rowCount() . " rows affected)";
                                    }
                                } catch (Exception $e) {
                                    if (!preg_match('/(CREATE TABLE|ALTER TABLE)/', $statement)) {
                                        $results[] = "Warning: " . $e->getMessage();
                                    }
                                }
                            }
                        }
                        
                        $pdo->commit();
                        $message = 'Pembersihan data lainnya berhasil.';
                        $cleanup_results['cleanup_other'] = implode("\n", $results);
                    } else {
                        $error = 'File cleanup other data script tidak ditemukan.';
                    }
                    break;
                    
                case 'cleanup_all':
                    // Jalankan semua script cleanup secara berurutan
                    $scripts = [
                        'backup' => __DIR__ . '/scripts/backup_database.php',
                        'cleanup_products' => __DIR__ . '/scripts/cleanup_products.sql',
                        'cleanup_transactions' => __DIR__ . '/scripts/cleanup_transactions.sql',
                        'cleanup_other' => __DIR__ . '/scripts/cleanup_other_data.sql'
                    ];
                    
                    $all_results = [];
                    
                    // Backup dulu
                    if (file_exists($scripts['backup'])) {
                        ob_start();
                        include $scripts['backup'];
                        $backup_output = ob_get_clean();
                        $all_results['backup'] = 'Backup completed';
                    }
                    
                    // Cleanup berurutan
                    foreach (['cleanup_products', 'cleanup_transactions', 'cleanup_other'] as $script_name) {
                        if (file_exists($scripts[$script_name])) {
                            $sql_content = file_get_contents($scripts[$script_name]);
                            $statements = explode(';', $sql_content);
                            
                            $pdo->beginTransaction();
                            $results = [];
                            
                            foreach ($statements as $statement) {
                                $statement = trim($statement);
                                if (!empty($statement) && !preg_match('/^\s*--/', $statement)) {
                                    try {
                                        $stmt = $pdo->prepare($statement);
                                        $stmt->execute();
                                        if ($stmt->rowCount() > 0) {
                                            $results[] = $stmt->rowCount() . " rows affected";
                                        }
                                    } catch (Exception $e) {
                                        if (!preg_match('/(CREATE TABLE|ALTER TABLE)/', $statement)) {
                                            $results[] = "Warning: " . $e->getMessage();
                                        }
                                    }
                                }
                            }
                            
                            $pdo->commit();
                            $all_results[$script_name] = implode(", ", $results);
                        }
                    }
                    
                    $message = 'Pembersihan data lengkap berhasil.';
                    $cleanup_results = $all_results;
                    break;
                    
                default:
                    $error = 'Aksi tidak valid.';
            }
            
        } catch (Exception $e) {
            $error = 'Error: ' . $e->getMessage();
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
        }
    }
}

// Ambil statistik database saat ini
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $stats = [];
    $tables = ['desa', 'produk', 'transaksi', 'users', 'kategori', 'activity_logs'];
    
    foreach ($tables as $table) {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM $table");
        $stats[$table] = $stmt->fetch()['count'];
    }
    
} catch (Exception $e) {
    $stats = [];
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Pembersihan Data Percobaan</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-6xl mx-auto">
            <!-- Header -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900">
                            <i class="fas fa-broom text-red-500 mr-3"></i>
                            Pembersihan Data Percobaan
                        </h1>
                        <p class="text-gray-600 mt-2">Persiapan database sebelum masuk produksi</p>
                    </div>
                    <div class="text-right">
                        <p class="text-sm text-gray-500">Tanggal: <?= date('d/m/Y H:i') ?></p>
                        <p class="text-sm text-gray-500">User: <?= htmlspecialchars($_SESSION['username']) ?></p>
                    </div>
                </div>
            </div>

            <!-- Peringatan -->
            <div class="bg-red-50 border-l-4 border-red-400 p-4 mb-6">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-exclamation-triangle text-red-400"></i>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-red-800">PERINGATAN PENTING!</h3>
                        <div class="mt-2 text-sm text-red-700">
                            <ul class="list-disc list-inside space-y-1">
                                <li>Proses ini akan menghapus data secara permanen</li>
                                <li>Pastikan sudah melakukan backup database lengkap</li>
                                <li>Test terlebih dahulu di environment development</li>
                                <li>Hanya jalankan sekali sebelum masuk produksi</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Statistik Database -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">
                    <i class="fas fa-chart-bar text-blue-500 mr-2"></i>
                    Statistik Database Saat Ini
                </h2>
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
                    <?php foreach ($stats as $table => $count): ?>
                    <div class="bg-gray-50 p-4 rounded-lg text-center">
                        <div class="text-2xl font-bold text-gray-900"><?= number_format($count) ?></div>
                        <div class="text-sm text-gray-600 capitalize"><?= $table ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Messages -->
            <?php if ($message): ?>
            <div class="bg-green-50 border-l-4 border-green-400 p-4 mb-6">
                <div class="flex">
                    <i class="fas fa-check-circle text-green-400 mr-3"></i>
                    <p class="text-green-700"><?= htmlspecialchars($message) ?></p>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($error): ?>
            <div class="bg-red-50 border-l-4 border-red-400 p-4 mb-6">
                <div class="flex">
                    <i class="fas fa-times-circle text-red-400 mr-3"></i>
                    <p class="text-red-700"><?= htmlspecialchars($error) ?></p>
                </div>
            </div>
            <?php endif; ?>

            <!-- Hasil Cleanup -->
            <?php if (!empty($cleanup_results)): ?>
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">
                    <i class="fas fa-list-alt text-green-500 mr-2"></i>
                    Hasil Pembersihan
                </h2>
                <?php foreach ($cleanup_results as $type => $result): ?>
                <div class="mb-4">
                    <h3 class="font-medium text-gray-900 mb-2 capitalize"><?= $type ?></h3>
                    <pre class="bg-gray-100 p-3 rounded text-sm overflow-x-auto"><?= htmlspecialchars($result) ?></pre>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Form Actions -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Aksi Individual -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h2 class="text-xl font-semibold text-gray-900 mb-4">
                        <i class="fas fa-cogs text-blue-500 mr-2"></i>
                        Aksi Individual
                    </h2>
                    
                    <div class="space-y-4">
                        <!-- Backup -->
                        <form method="POST" class="border-b pb-4">
                            <input type="hidden" name="action" value="backup">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="font-medium text-gray-900">1. Backup Database</h3>
                                    <p class="text-sm text-gray-600">Buat backup lengkap sebelum pembersihan</p>
                                </div>
                                <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded">
                                    <i class="fas fa-download mr-1"></i> Backup
                                </button>
                            </div>
                            <input type="text" name="confirmation" placeholder="Ketik: HAPUS DATA PERCOBAAN" 
                                   class="mt-2 w-full px-3 py-2 border border-gray-300 rounded" required>
                        </form>

                        <!-- Identifikasi -->
                        <form method="POST" class="border-b pb-4">
                            <input type="hidden" name="action" value="identify">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="font-medium text-gray-900">2. Identifikasi Data</h3>
                                    <p class="text-sm text-gray-600">Lihat data yang akan dihapus</p>
                                </div>
                                <button type="submit" class="bg-yellow-500 hover:bg-yellow-600 text-white px-4 py-2 rounded">
                                    <i class="fas fa-search mr-1"></i> Identifikasi
                                </button>
                            </div>
                            <input type="text" name="confirmation" placeholder="Ketik: HAPUS DATA PERCOBAAN" 
                                   class="mt-2 w-full px-3 py-2 border border-gray-300 rounded" required>
                        </form>

                        <!-- Cleanup Produk -->
                        <form method="POST" class="border-b pb-4">
                            <input type="hidden" name="action" value="cleanup_products">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="font-medium text-gray-900">3. Hapus Data Produk</h3>
                                    <p class="text-sm text-gray-600">Hapus produk percobaan dan tidak aktif</p>
                                </div>
                                <button type="submit" class="bg-orange-500 hover:bg-orange-600 text-white px-4 py-2 rounded">
                                    <i class="fas fa-box mr-1"></i> Hapus Produk
                                </button>
                            </div>
                            <input type="text" name="confirmation" placeholder="Ketik: HAPUS DATA PERCOBAAN" 
                                   class="mt-2 w-full px-3 py-2 border border-gray-300 rounded" required>
                        </form>

                        <!-- Cleanup Transaksi -->
                        <form method="POST" class="border-b pb-4">
                            <input type="hidden" name="action" value="cleanup_transactions">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="font-medium text-gray-900">4. Hapus Data Transaksi</h3>
                                    <p class="text-sm text-gray-600">Hapus transaksi draft dan percobaan</p>
                                </div>
                                <button type="submit" class="bg-purple-500 hover:bg-purple-600 text-white px-4 py-2 rounded">
                                    <i class="fas fa-receipt mr-1"></i> Hapus Transaksi
                                </button>
                            </div>
                            <input type="text" name="confirmation" placeholder="Ketik: HAPUS DATA PERCOBAAN" 
                                   class="mt-2 w-full px-3 py-2 border border-gray-300 rounded" required>
                        </form>

                        <!-- Cleanup Data Lainnya -->
                        <form method="POST">
                            <input type="hidden" name="action" value="cleanup_other">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="font-medium text-gray-900">5. Hapus Data Lainnya</h3>
                                    <p class="text-sm text-gray-600">Hapus desa, user, kategori tidak terpakai</p>
                                </div>
                                <button type="submit" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded">
                                    <i class="fas fa-trash mr-1"></i> Hapus Lainnya
                                </button>
                            </div>
                            <input type="text" name="confirmation" placeholder="Ketik: HAPUS DATA PERCOBAAN" 
                                   class="mt-2 w-full px-3 py-2 border border-gray-300 rounded" required>
                        </form>
                    </div>
                </div>

                <!-- Aksi Lengkap -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h2 class="text-xl font-semibold text-gray-900 mb-4">
                        <i class="fas fa-rocket text-red-500 mr-2"></i>
                        Pembersihan Lengkap
                    </h2>
                    
                    <div class="bg-red-50 p-4 rounded-lg mb-4">
                        <h3 class="font-medium text-red-800 mb-2">Proses Otomatis Lengkap</h3>
                        <p class="text-sm text-red-700 mb-3">
                            Akan menjalankan semua script secara berurutan:
                        </p>
                        <ol class="text-sm text-red-700 list-decimal list-inside space-y-1">
                            <li>Backup database lengkap</li>
                            <li>Hapus data produk percobaan</li>
                            <li>Hapus data transaksi percobaan</li>
                            <li>Hapus data lainnya (desa, user, dll)</li>
                        </ol>
                    </div>
                    
                    <form method="POST">
                        <input type="hidden" name="action" value="cleanup_all">
                        <div class="space-y-4">
                            <input type="text" name="confirmation" placeholder="Ketik: HAPUS DATA PERCOBAAN" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded" required>
                            <button type="submit" 
                                    class="w-full bg-red-600 hover:bg-red-700 text-white px-6 py-3 rounded-lg font-medium"
                                    onclick="return confirm('Apakah Anda yakin ingin menjalankan pembersihan lengkap? Proses ini tidak dapat dibatalkan!')">
                                <i class="fas fa-broom mr-2"></i>
                                JALANKAN PEMBERSIHAN LENGKAP
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Footer -->
            <div class="mt-8 text-center">
                <a href="dashboard.php" class="text-blue-600 hover:text-blue-800">
                    <i class="fas fa-arrow-left mr-1"></i> Kembali ke Dashboard
                </a>
            </div>
        </div>
    </div>

    <script>
        // Auto-clear confirmation fields after submit
        document.addEventListener('DOMContentLoaded', function() {
            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
                form.addEventListener('submit', function() {
                    setTimeout(() => {
                        const confirmationInput = form.querySelector('input[name="confirmation"]');
                        if (confirmationInput) {
                            confirmationInput.value = '';
                        }
                    }, 100);
                });
            });
        });
    </script>
</body>
</html>