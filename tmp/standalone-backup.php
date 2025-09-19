<?php
/**
 * Standalone Backup System
 * Sistem backup mandiri yang dapat dijalankan tanpa koneksi ke aplikasi utama
 * 
 * Cara penggunaan:
 * 1. Upload file ini ke server
 * 2. Akses melalui browser: http://yourserver.com/standalone-backup.php
 * 3. Masukkan kredensial database
 * 4. Klik tombol backup untuk mengunduh file backup
 */

// Konfigurasi
ini_set('max_execution_time', 0);
ini_set('memory_limit', '512M');

// Fungsi untuk membuat backup database
function createDatabaseBackup($host, $username, $password, $database) {
    try {
        $connection = new mysqli($host, $username, $password, $database);
        
        if ($connection->connect_error) {
            throw new Exception("Connection failed: " . $connection->connect_error);
        }
        
        $connection->set_charset("utf8");
        
        $backup = "-- Database Backup\n";
        $backup .= "-- Generated on: " . date('Y-m-d H:i:s') . "\n";
        $backup .= "-- Database: {$database}\n\n";
        
        // Disable foreign key checks
        $backup .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";
        
        // Get all tables
        $tables = array();
        $result = $connection->query("SHOW TABLES");
        while ($row = $result->fetch_row()) {
            $tables[] = $row[0];
        }
        
        foreach ($tables as $table) {
            // Drop table if exists
            $backup .= "DROP TABLE IF EXISTS `{$table}`;\n";
            
            // Create table structure
            $result = $connection->query("SHOW CREATE TABLE `{$table}`");
            $row = $result->fetch_row();
            $backup .= $row[1] . ";\n\n";
            
            // Insert data
            $result = $connection->query("SELECT * FROM `{$table}`");
            if ($result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $backup .= "INSERT INTO `{$table}` VALUES (";
                    $values = array();
                    foreach ($row as $value) {
                        if ($value === null) {
                            $values[] = 'NULL';
                        } else {
                            $values[] = "'" . $connection->real_escape_string($value) . "'";
                        }
                    }
                    $backup .= implode(', ', $values) . ");\n";
                }
                $backup .= "\n";
            }
        }
        
        // Re-enable foreign key checks
        $backup .= "SET FOREIGN_KEY_CHECKS = 1;\n";
        
        $connection->close();
        return $backup;
        
    } catch (Exception $e) {
        throw new Exception("Backup failed: " . $e->getMessage());
    }
}

// Fungsi untuk mengompres file uploads
function createUploadsBackup($uploadsPath) {
    if (!is_dir($uploadsPath)) {
        return null;
    }
    
    $zip = new ZipArchive();
    $zipFile = tempnam(sys_get_temp_dir(), 'uploads_backup_') . '.zip';
    
    if ($zip->open($zipFile, ZipArchive::CREATE) !== TRUE) {
        throw new Exception("Cannot create uploads backup");
    }
    
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($uploadsPath, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    
    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $relativePath = str_replace($uploadsPath . DIRECTORY_SEPARATOR, '', $file->getPathname());
            $zip->addFile($file->getPathname(), 'uploads/' . $relativePath);
        }
    }
    
    $zip->close();
    return $zipFile;
}

// Proses backup
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'backup') {
    try {
        $host = $_POST['db_host'] ?? 'localhost';
        $username = $_POST['db_username'] ?? '';
        $password = $_POST['db_password'] ?? '';
        $database = $_POST['db_name'] ?? '';
        $uploadsPath = $_POST['uploads_path'] ?? '';
        
        if (empty($username) || empty($database)) {
            throw new Exception("Database credentials are required");
        }
        
        // Create database backup
        $sqlBackup = createDatabaseBackup($host, $username, $password, $database);
        
        // Create final backup ZIP
        $zip = new ZipArchive();
        $backupFile = tempnam(sys_get_temp_dir(), 'full_backup_') . '.zip';
        
        if ($zip->open($backupFile, ZipArchive::CREATE) !== TRUE) {
            throw new Exception("Cannot create backup file");
        }
        
        // Add SQL backup
        $zip->addFromString('database_backup.sql', $sqlBackup);
        
        // Add uploads if path provided and exists
        if (!empty($uploadsPath) && is_dir($uploadsPath)) {
            $uploadsZip = createUploadsBackup($uploadsPath);
            if ($uploadsZip) {
                $zip->addFile($uploadsZip, 'uploads_backup.zip');
            }
        }
        
        // Add backup info
        $backupInfo = [
            'created_at' => date('Y-m-d H:i:s'),
            'database' => $database,
            'host' => $host,
            'includes_uploads' => !empty($uploadsPath) && is_dir($uploadsPath)
        ];
        $zip->addFromString('backup_info.json', json_encode($backupInfo, JSON_PRETTY_PRINT));
        
        $zip->close();
        
        // Download file
        $filename = 'backup_' . $database . '_' . date('Y-m-d_H-i-s') . '.zip';
        
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($backupFile));
        
        readfile($backupFile);
        
        // Clean up
        unlink($backupFile);
        if (isset($uploadsZip) && file_exists($uploadsZip)) {
            unlink($uploadsZip);
        }
        
        exit;
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Standalone Backup System</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 600px;
            margin: 50px auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            text-align: center;
            margin-bottom: 30px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #555;
        }
        input[type="text"], input[type="password"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .btn {
            background-color: #007bff;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            width: 100%;
        }
        .btn:hover {
            background-color: #0056b3;
        }
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .help-text {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        .info-box {
            background-color: #e7f3ff;
            border: 1px solid #b3d9ff;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🗄️ Standalone Backup System</h1>
        
        <div class="info-box">
            <strong>Informasi:</strong><br>
            Sistem backup mandiri ini dapat dijalankan tanpa koneksi ke aplikasi utama. 
            Cukup upload file ini ke server dan akses melalui browser.
        </div>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <strong>Error:</strong> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <form method="POST">
            <input type="hidden" name="action" value="backup">
            
            <div class="form-group">
                <label for="db_host">Database Host:</label>
                <input type="text" id="db_host" name="db_host" value="localhost" required>
                <div class="help-text">Biasanya 'localhost' untuk server lokal</div>
            </div>
            
            <div class="form-group">
                <label for="db_username">Database Username:</label>
                <input type="text" id="db_username" name="db_username" required>
                <div class="help-text">Username untuk mengakses database</div>
            </div>
            
            <div class="form-group">
                <label for="db_password">Database Password:</label>
                <input type="password" id="db_password" name="db_password">
                <div class="help-text">Password database (kosongkan jika tidak ada)</div>
            </div>
            
            <div class="form-group">
                <label for="db_name">Database Name:</label>
                <input type="text" id="db_name" name="db_name" required>
                <div class="help-text">Nama database yang akan di-backup</div>
            </div>
            
            <div class="form-group">
                <label for="uploads_path">Path Folder Uploads (Opsional):</label>
                <input type="text" id="uploads_path" name="uploads_path" placeholder="/path/to/uploads">
                <div class="help-text">Path lengkap ke folder uploads (kosongkan jika tidak perlu backup file)</div>
            </div>
            
            <button type="submit" class="btn">📥 Download Backup</button>
        </form>
        
        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; font-size: 12px; color: #666; text-align: center;">
            Standalone Backup System v1.0
        </div>
    </div>
</body>
</html>