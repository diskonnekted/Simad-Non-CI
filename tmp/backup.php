<?php
require_once 'config/database.php';
require_once 'config/auth.php';

// Cek autentikasi dan role admin
if (!AuthStatic::isLoggedIn()) {
    header('Location: login.php');
    exit;
}

if (!AuthStatic::hasRole(['admin'])) {
    header('Location: index.php?error=access_denied');
    exit;
}

$user = AuthStatic::getCurrentUser();
$db = getDatabase();
$error = '';
$success = '';

// Handle backup process
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_backup'])) {
    try {
        $backup_name = 'smd_backup_' . date('Y-m-d_H-i-s');
        $backup_dir = 'tmp/backup/';
        $backup_path = $backup_dir . $backup_name;
        
        // Buat direktori backup jika belum ada
        if (!is_dir($backup_dir)) {
            mkdir($backup_dir, 0755, true);
        }
        
        if (!is_dir($backup_path)) {
            mkdir($backup_path, 0755, true);
        }
        
        // 1. Export Database
        $sql_file = $backup_path . '/database.sql';
        $tables = $db->select("SHOW TABLES");
        
        $sql_content = "-- SMD Database Backup\n";
        $sql_content .= "-- Created: " . date('Y-m-d H:i:s') . "\n";
        $sql_content .= "-- By: " . $user['nama'] . "\n\n";
        
        $sql_content .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";
        
        foreach ($tables as $table) {
            $table_name = array_values($table)[0];
            
            // Get table structure
            $create_table = $db->select("SHOW CREATE TABLE `{$table_name}`");
            $sql_content .= "-- Table: {$table_name}\n";
            $sql_content .= "DROP TABLE IF EXISTS `{$table_name}`;\n";
            $sql_content .= $create_table[0]['Create Table'] . ";\n\n";
            
            // Get table data
            $rows = $db->select("SELECT * FROM `{$table_name}`");
            if (!empty($rows)) {
                $columns = array_keys($rows[0]);
                $sql_content .= "INSERT INTO `{$table_name}` (`" . implode('`, `', $columns) . "`) VALUES\n";
                
                $values = [];
                foreach ($rows as $row) {
                    $row_values = [];
                    foreach ($row as $value) {
                        if ($value === null) {
                            $row_values[] = 'NULL';
                        } else {
                            $row_values[] = "'" . addslashes($value) . "'";
                        }
                    }
                    $values[] = '(' . implode(', ', $row_values) . ')';
                }
                $sql_content .= implode(",\n", $values) . ";\n\n";
            }
        }
        
        $sql_content .= "SET FOREIGN_KEY_CHECKS = 1;\n";
        
        file_put_contents($sql_file, $sql_content);
        
        // 2. Copy Upload Files
        $upload_dirs = ['uploads/produk', 'uploads/layanan', 'uploads/users'];
        foreach ($upload_dirs as $upload_dir) {
            if (is_dir($upload_dir)) {
                $dest_dir = $backup_path . '/' . $upload_dir;
                if (!is_dir(dirname($dest_dir))) {
                    mkdir(dirname($dest_dir), 0755, true);
                }
                if (!is_dir($dest_dir)) {
                    mkdir($dest_dir, 0755, true);
                }
                
                // Copy files
                $files = glob($upload_dir . '/*');
                foreach ($files as $file) {
                    if (is_file($file)) {
                        copy($file, $dest_dir . '/' . basename($file));
                    }
                }
            }
        }
        
        // 3. Create ZIP file
        $zip_file = $backup_dir . $backup_name . '.zip';
        $zip = new ZipArchive();
        
        if ($zip->open($zip_file, ZipArchive::CREATE) === TRUE) {
            // Add database file
            $zip->addFile($sql_file, 'database.sql');
            
            // Add upload files
            foreach ($upload_dirs as $upload_dir) {
                $source_dir = $backup_path . '/' . $upload_dir;
                if (is_dir($source_dir)) {
                    $files = glob($source_dir . '/*');
                    foreach ($files as $file) {
                        if (is_file($file)) {
                            $zip->addFile($file, $upload_dir . '/' . basename($file));
                        }
                    }
                }
            }
            
            $zip->close();
            
            // Clean up temporary files
            function deleteDirectory($dir) {
                if (!is_dir($dir)) return;
                $files = array_diff(scandir($dir), ['.', '..']);
                foreach ($files as $file) {
                    $path = $dir . '/' . $file;
                    is_dir($path) ? deleteDirectory($path) : unlink($path);
                }
                rmdir($dir);
            }
            deleteDirectory($backup_path);
            
            $success = 'Backup berhasil dibuat: ' . basename($zip_file);
            
            // Auto download
            if (isset($_POST['auto_download'])) {
                header('Content-Type: application/zip');
                header('Content-Disposition: attachment; filename="' . basename($zip_file) . '"');
                header('Content-Length: ' . filesize($zip_file));
                readfile($zip_file);
                unlink($zip_file); // Delete after download
                exit;
            }
            
        } else {
            throw new Exception('Gagal membuat file ZIP');
        }
        
    } catch (Exception $e) {
        $error = 'Gagal membuat backup: ' . $e->getMessage();
    }
}

// Get existing backup files
$backup_files = [];
if (is_dir('tmp/backup/')) {
    $files = glob('tmp/backup/*.zip');
    foreach ($files as $file) {
        $backup_files[] = [
            'name' => basename($file),
            'size' => filesize($file),
            'date' => date('Y-m-d H:i:s', filemtime($file)),
            'path' => $file
        ];
    }
    // Sort by date descending
    usort($backup_files, function($a, $b) {
        return strtotime($b['date']) - strtotime($a['date']);
    });
}

$page_title = 'Backup Database';
require_once 'layouts/header.php';
?>

<div class="min-h-screen bg-gray-50">
    <!-- Page Header -->
    <div class="bg-white shadow-sm border-b border-gray-200">
        <div class="max-w-7xl ml-0 px-4 sm:px-6 lg:px-8 py-6">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900 flex items-center">
                        <i class="fas fa-download text-blue-600 mr-3"></i>
                        Backup Database
                    </h1>
                    <p class="text-sm text-gray-600 mt-2">Buat backup lengkap database dan file gambar</p>
                </div>
                <nav class="mt-4 sm:mt-0 text-sm text-gray-500">
                    <a href="dashboard.php" class="hover:text-blue-600 flex items-center">
                        <i class="fas fa-home mr-1"></i> Dashboard
                    </a>
                    <span class="mx-2">/</span>
                    <span class="text-gray-900">Backup Database</span>
                </nav>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="max-w-7xl ml-0 px-4 sm:px-6 lg:px-8 py-8">
        <!-- Alert Messages -->
        <?php if ($error): ?>
            <div class="mb-6 bg-red-50 border border-red-200 rounded-lg p-4">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-triangle text-red-600 mr-3"></i>
                    <div class="text-red-800"><?= htmlspecialchars($error) ?></div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="mb-6 bg-green-50 border border-green-200 rounded-lg p-4">
                <div class="flex items-center">
                    <i class="fas fa-check text-green-600 mr-3"></i>
                    <div class="text-green-800"><?= htmlspecialchars($success) ?></div>
                </div>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Create Backup -->
            <div class="bg-white rounded-lg shadow-md">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900 flex items-center">
                        <i class="fas fa-plus-circle text-blue-600 mr-2"></i>
                        Buat Backup Baru
                    </h2>
                </div>
                <div class="p-6">
                    <div class="mb-6">
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                            <h3 class="font-medium text-blue-900 mb-2">Yang akan di-backup:</h3>
                            <ul class="text-sm text-blue-800 space-y-1">
                                <li class="flex items-center">
                                    <i class="fas fa-database mr-2"></i>
                                    Seluruh struktur dan data database
                                </li>
                                <li class="flex items-center">
                                    <i class="fas fa-images mr-2"></i>
                                    File gambar produk (uploads/produk/)
                                </li>
                                <li class="flex items-center">
                                    <i class="fas fa-file-image mr-2"></i>
                                    File gambar layanan (uploads/layanan/)
                                </li>
                                <li class="flex items-center">
                                    <i class="fas fa-user-circle mr-2"></i>
                                    File foto profil user (uploads/users/)
                                </li>
                            </ul>
                        </div>
                    </div>
                    
                    <form method="POST">
                        <div class="mb-4">
                            <label class="flex items-center">
                                <input type="checkbox" name="auto_download" value="1" checked class="mr-2">
                                <span class="text-sm text-gray-700">Download otomatis setelah backup selesai</span>
                            </label>
                        </div>
                        
                        <button type="submit" name="create_backup" 
                                class="w-full bg-blue-600 text-white px-4 py-3 rounded-lg hover:bg-blue-700 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors duration-200 flex items-center justify-center">
                            <i class="fas fa-download mr-2"></i>
                            Buat Backup Sekarang
                        </button>
                    </form>
                </div>
            </div>

            <!-- Backup History -->
            <div class="bg-white rounded-lg shadow-md">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900 flex items-center">
                        <i class="fas fa-history text-green-600 mr-2"></i>
                        Riwayat Backup
                    </h2>
                </div>
                <div class="p-6">
                    <?php if (empty($backup_files)): ?>
                        <div class="text-center py-8">
                            <i class="fas fa-folder-open text-gray-400 text-4xl mb-4"></i>
                            <p class="text-gray-500">Belum ada file backup</p>
                        </div>
                    <?php else: ?>
                        <div class="space-y-3">
                            <?php foreach ($backup_files as $backup): ?>
                                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                    <div class="flex-1">
                                        <div class="font-medium text-gray-900"><?= htmlspecialchars($backup['name']) ?></div>
                                        <div class="text-sm text-gray-500">
                                            <?= htmlspecialchars($backup['date']) ?> • 
                                            <?= number_format($backup['size'] / 1024 / 1024, 2) ?> MB
                                        </div>
                                    </div>
                                    <div class="flex space-x-2">
                                        <a href="<?= htmlspecialchars($backup['path']) ?>" 
                                           class="text-blue-600 hover:text-blue-800 p-2" 
                                           title="Download">
                                            <i class="fas fa-download"></i>
                                        </a>
                                        <button onclick="deleteBackup('<?= htmlspecialchars($backup['name']) ?>')" 
                                                class="text-red-600 hover:text-red-800 p-2" 
                                                title="Hapus">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="mt-8 bg-white rounded-lg shadow-md">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900 flex items-center">
                    <i class="fas fa-tools text-purple-600 mr-2"></i>
                    Aksi Cepat
                </h2>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <a href="restore.php" 
                       class="flex items-center p-4 bg-green-50 border border-green-200 rounded-lg hover:bg-green-100 transition-colors duration-200">
                        <i class="fas fa-upload text-green-600 mr-3 text-xl"></i>
                        <div>
                            <div class="font-medium text-green-900">Restore Database</div>
                            <div class="text-sm text-green-700">Upload dan restore backup</div>
                        </div>
                    </a>
                    
                    <a href="dashboard.php" 
                       class="flex items-center p-4 bg-blue-50 border border-blue-200 rounded-lg hover:bg-blue-100 transition-colors duration-200">
                        <i class="fas fa-chart-bar text-blue-600 mr-3 text-xl"></i>
                        <div>
                            <div class="font-medium text-blue-900">Dashboard</div>
                            <div class="text-sm text-blue-700">Kembali ke dashboard</div>
                        </div>
                    </a>
                    
                    <a href="produk.php" 
                       class="flex items-center p-4 bg-purple-50 border border-purple-200 rounded-lg hover:bg-purple-100 transition-colors duration-200">
                        <i class="fas fa-cube text-purple-600 mr-3 text-xl"></i>
                        <div>
                            <div class="font-medium text-purple-900">Kelola Produk</div>
                            <div class="text-sm text-purple-700">Manajemen produk</div>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function deleteBackup(filename) {
    if (confirm('Apakah Anda yakin ingin menghapus backup: ' + filename + '?')) {
        // Create form to delete backup
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'backup-delete.php';
        
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'filename';
        input.value = filename;
        
        form.appendChild(input);
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<?php require_once 'layouts/footer.php'; ?>