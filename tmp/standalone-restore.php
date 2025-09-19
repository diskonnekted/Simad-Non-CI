<?php
/**
 * Standalone Restore System
 * Sistem restore mandiri yang dapat dijalankan tanpa koneksi ke aplikasi utama
 * 
 * Cara penggunaan:
 * 1. Upload file ini ke server
 * 2. Akses melalui browser: http://yourserver.com/standalone-restore.php
 * 3. Upload file backup (.zip)
 * 4. Masukkan kredensial database
 * 5. Klik tombol restore untuk memulihkan data
 */

// Konfigurasi
ini_set('max_execution_time', 0);
ini_set('memory_limit', '512M');

// Fungsi untuk mengekstrak file backup
function extractBackupFile($backupFile, $extractPath) {
    $zip = new ZipArchive();
    
    if ($zip->open($backupFile) !== TRUE) {
        throw new Exception("Cannot open backup file");
    }
    
    if (!$zip->extractTo($extractPath)) {
        $zip->close();
        throw new Exception("Cannot extract backup file");
    }
    
    $zip->close();
    return true;
}

// Fungsi untuk restore database
function restoreDatabase($host, $username, $password, $database, $sqlFile) {
    try {
        $connection = new mysqli($host, $username, $password);
        
        if ($connection->connect_error) {
            throw new Exception("Connection failed: " . $connection->connect_error);
        }
        
        $connection->set_charset("utf8");
        
        // Create database if not exists
        $connection->query("CREATE DATABASE IF NOT EXISTS `{$database}`");
        $connection->select_db($database);
        
        // Read SQL file
        $sql = file_get_contents($sqlFile);
        if ($sql === false) {
            throw new Exception("Cannot read SQL file");
        }
        
        // Split SQL into individual queries
        $queries = explode(';', $sql);
        
        foreach ($queries as $query) {
            $query = trim($query);
            if (!empty($query)) {
                if (!$connection->query($query)) {
                    throw new Exception("SQL Error: " . $connection->error . " in query: " . substr($query, 0, 100));
                }
            }
        }
        
        $connection->close();
        return true;
        
    } catch (Exception $e) {
        throw new Exception("Database restore failed: " . $e->getMessage());
    }
}

// Fungsi untuk restore uploads
function restoreUploads($uploadsZipFile, $targetPath) {
    if (!file_exists($uploadsZipFile)) {
        return false;
    }
    
    $zip = new ZipArchive();
    
    if ($zip->open($uploadsZipFile) !== TRUE) {
        throw new Exception("Cannot open uploads backup file");
    }
    
    // Create target directory if not exists
    if (!is_dir($targetPath)) {
        if (!mkdir($targetPath, 0755, true)) {
            throw new Exception("Cannot create uploads directory");
        }
    }
    
    if (!$zip->extractTo($targetPath)) {
        $zip->close();
        throw new Exception("Cannot extract uploads backup");
    }
    
    $zip->close();
    return true;
}

// Fungsi untuk membersihkan direktori temporary
function cleanupTempDir($dir) {
    if (is_dir($dir)) {
        $files = array_diff(scandir($dir), array('.', '..'));
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            if (is_dir($path)) {
                cleanupTempDir($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }
}

// Proses restore
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'restore') {
    try {
        $host = $_POST['db_host'] ?? 'localhost';
        $username = $_POST['db_username'] ?? '';
        $password = $_POST['db_password'] ?? '';
        $database = $_POST['db_name'] ?? '';
        $uploadsPath = $_POST['uploads_path'] ?? '';
        
        if (empty($username) || empty($database)) {
            throw new Exception("Database credentials are required");
        }
        
        if (!isset($_FILES['backup_file']) || $_FILES['backup_file']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("Please select a valid backup file");
        }
        
        $backupFile = $_FILES['backup_file']['tmp_name'];
        $originalName = $_FILES['backup_file']['name'];
        
        // Validate file extension
        if (pathinfo($originalName, PATHINFO_EXTENSION) !== 'zip') {
            throw new Exception("Backup file must be a ZIP file");
        }
        
        // Create temporary directory
        $tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'restore_' . uniqid();
        if (!mkdir($tempDir, 0755, true)) {
            throw new Exception("Cannot create temporary directory");
        }
        
        try {
            // Extract backup file
            extractBackupFile($backupFile, $tempDir);
            
            // Check for required files
            $sqlFile = $tempDir . DIRECTORY_SEPARATOR . 'database_backup.sql';
            if (!file_exists($sqlFile)) {
                throw new Exception("Database backup file not found in the archive");
            }
            
            // Restore database
            restoreDatabase($host, $username, $password, $database, $sqlFile);
            $success[] = "Database restored successfully";
            
            // Restore uploads if available and path provided
            $uploadsZipFile = $tempDir . DIRECTORY_SEPARATOR . 'uploads_backup.zip';
            if (!empty($uploadsPath) && file_exists($uploadsZipFile)) {
                restoreUploads($uploadsZipFile, dirname($uploadsPath));
                $success[] = "Uploads restored successfully";
            }
            
            // Read backup info if available
            $backupInfoFile = $tempDir . DIRECTORY_SEPARATOR . 'backup_info.json';
            if (file_exists($backupInfoFile)) {
                $backupInfo = json_decode(file_get_contents($backupInfoFile), true);
                if ($backupInfo) {
                    $success[] = "Backup created on: " . $backupInfo['created_at'];
                }
            }
            
        } finally {
            // Clean up temporary directory
            cleanupTempDir($tempDir);
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
        // Clean up on error
        if (isset($tempDir) && is_dir($tempDir)) {
            cleanupTempDir($tempDir);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Standalone Restore System</title>
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
        input[type="text"], input[type="password"], input[type="file"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .btn {
            background-color: #28a745;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            width: 100%;
        }
        .btn:hover {
            background-color: #218838;
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
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .help-text {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        .info-box {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .warning-box {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            color: #721c24;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔄 Standalone Restore System</h1>
        
        <div class="warning-box">
            <strong>⚠️ Peringatan:</strong><br>
            Proses restore akan mengganti semua data yang ada di database. 
            Pastikan Anda telah membuat backup sebelum melanjutkan.
        </div>
        
        <div class="info-box">
            <strong>Informasi:</strong><br>
            Sistem restore mandiri ini dapat memulihkan database dan file uploads 
            dari file backup yang dibuat oleh sistem backup standalone.
        </div>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <strong>Error:</strong> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($success)): ?>
            <div class="alert alert-success">
                <strong>Success:</strong><br>
                <?php foreach ($success as $msg): ?>
                    ✅ <?= htmlspecialchars($msg) ?><br>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="restore">
            
            <div class="form-group">
                <label for="backup_file">File Backup (.zip):</label>
                <input type="file" id="backup_file" name="backup_file" accept=".zip" required>
                <div class="help-text">Pilih file backup ZIP yang dibuat oleh sistem backup</div>
            </div>
            
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
                <div class="help-text">Nama database tujuan restore</div>
            </div>
            
            <div class="form-group">
                <label for="uploads_path">Path Folder Uploads (Opsional):</label>
                <input type="text" id="uploads_path" name="uploads_path" placeholder="/path/to/uploads">
                <div class="help-text">Path lengkap ke folder uploads (kosongkan jika tidak perlu restore file)</div>
            </div>
            
            <button type="submit" class="btn" onclick="return confirm('Apakah Anda yakin ingin melakukan restore? Semua data yang ada akan diganti!')">🔄 Mulai Restore</button>
        </form>
        
        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; font-size: 12px; color: #666; text-align: center;">
            Standalone Restore System v1.0
        </div>
    </div>
    
    <script>
        // Show loading state when form is submitted
        document.querySelector('form').addEventListener('submit', function() {
            const btn = document.querySelector('.btn');
            btn.innerHTML = '⏳ Memproses Restore...';
            btn.disabled = true;
        });
    </script>
</body>
</html>