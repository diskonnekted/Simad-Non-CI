<?php
require_once 'config/auth.php';
require_once 'config/database.php';

// Cek autentikasi dan role admin
if (!AuthStatic::isLoggedIn()) {
    header('Location: login.php');
    exit;
}

if (!AuthStatic::hasRole(['admin'])) {
    header('Location: restore.php?error=access_denied');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['backup_filename'])) {
    try {
        $filename = $_POST['backup_filename'];
        $backup_file = 'tmp/backup/' . basename($filename); // basename untuk keamanan
        
        // Validasi file exists dan format
        if (!file_exists($backup_file) || pathinfo($backup_file, PATHINFO_EXTENSION) !== 'zip') {
            throw new Exception('File backup tidak valid atau tidak ditemukan');
        }
        
        // Create temp directory for extraction
        $temp_dir = 'tmp/restore/';
        if (!is_dir($temp_dir)) {
            mkdir($temp_dir, 0755, true);
        }
        
        // Extract ZIP file
        $zip = new ZipArchive();
        if ($zip->open($backup_file) !== TRUE) {
            throw new Exception('Gagal membuka file ZIP');
        }
        
        $extract_dir = $temp_dir . 'extracted_' . time() . '/';
        if (!$zip->extractTo($extract_dir)) {
            $zip->close();
            throw new Exception('Gagal mengekstrak file ZIP');
        }
        $zip->close();
        
        // Find SQL file
        $sql_file = null;
        $files = scandir($extract_dir);
        foreach ($files as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) === 'sql') {
                $sql_file = $extract_dir . $file;
                break;
            }
        }
        
        if (!$sql_file || !file_exists($sql_file)) {
            throw new Exception('File SQL tidak ditemukan dalam backup');
        }
        
        // Read and execute SQL
        $sql_content = file_get_contents($sql_file);
        if ($sql_content === false) {
            throw new Exception('Gagal membaca file SQL');
        }
        
        // Split SQL into individual queries
        $queries = array_filter(array_map('trim', explode(';', $sql_content)));
        
        // Execute SQL queries
        $pdo = Database::getConnection();
        $pdo->beginTransaction();
        
        try {
            foreach ($queries as $query) {
                if (!empty($query)) {
                    $pdo->exec($query);
                }
            }
            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollback();
            throw new Exception('Gagal mengeksekusi SQL: ' . $e->getMessage());
        }
        
        // Restore images if exists
        $uploads_backup = $extract_dir . 'uploads/';
        if (is_dir($uploads_backup)) {
            // Backup existing uploads first
            if (is_dir('uploads/')) {
                $backup_existing = 'tmp/uploads_backup_' . time();
                rename('uploads/', $backup_existing);
            }
            
            // Create uploads directory
            mkdir('uploads/', 0755, true);
            
            // Copy files recursively
            function copyDirectory($src, $dst) {
                $dir = opendir($src);
                if (!is_dir($dst)) {
                    mkdir($dst, 0755, true);
                }
                
                while (($file = readdir($dir)) !== false) {
                    if ($file != '.' && $file != '..') {
                        $src_file = $src . '/' . $file;
                        $dst_file = $dst . '/' . $file;
                        
                        if (is_dir($src_file)) {
                            copyDirectory($src_file, $dst_file);
                        } else {
                            copy($src_file, $dst_file);
                        }
                    }
                }
                closedir($dir);
            }
            
            copyDirectory($uploads_backup, 'uploads/');
        }
        
        // Clean up temporary files
        function deleteDirectory($dir) {
            if (!is_dir($dir)) return;
            
            $files = array_diff(scandir($dir), array('.', '..'));
            foreach ($files as $file) {
                $path = $dir . '/' . $file;
                is_dir($path) ? deleteDirectory($path) : unlink($path);
            }
            rmdir($dir);
        }
        
        deleteDirectory($extract_dir);
        
        header('Location: restore.php?success=restore_completed');
        
    } catch (Exception $e) {
        // Clean up on error
        if (isset($extract_dir) && is_dir($extract_dir)) {
            deleteDirectory($extract_dir);
        }
        
        header('Location: restore.php?error=' . urlencode($e->getMessage()));
    }
} else {
    header('Location: restore.php');
}
exit;
?>