<?php
/**
 * Backup and Restore Validation Helper
 * Provides validation and error handling for backup/restore operations
 */

class BackupValidator {
    
    private static $allowed_extensions = ['zip'];
    private static $max_file_size = 100 * 1024 * 1024; // 100MB
    private static $required_tables = ['users', 'produk', 'layanan', 'transaksi'];
    
    /**
     * Validate backup file upload
     */
    public static function validateUploadedFile($file) {
        $errors = [];
        
        // Check if file was uploaded
        if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'File tidak berhasil diupload';
            return $errors;
        }
        
        // Check file size
        if ($file['size'] > self::$max_file_size) {
            $errors[] = 'Ukuran file terlalu besar (maksimal ' . (self::$max_file_size / 1024 / 1024) . 'MB)';
        }
        
        // Check file extension
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, self::$allowed_extensions)) {
            $errors[] = 'Format file tidak didukung. Hanya file ZIP yang diperbolehkan';
        }
        
        // Check MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        $allowed_mimes = ['application/zip', 'application/x-zip-compressed'];
        if (!in_array($mime_type, $allowed_mimes)) {
            $errors[] = 'Tipe file tidak valid';
        }
        
        return $errors;
    }
    
    /**
     * Validate backup ZIP file contents
     */
    public static function validateBackupContents($zip_file) {
        $errors = [];
        
        try {
            $zip = new ZipArchive();
            if ($zip->open($zip_file) !== TRUE) {
                $errors[] = 'File ZIP tidak dapat dibuka atau rusak';
                return $errors;
            }
            
            $has_sql = false;
            $sql_files = [];
            
            // Check ZIP contents
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $file_info = $zip->statIndex($i);
                $filename = $file_info['name'];
                
                // Check for SQL file
                if (pathinfo($filename, PATHINFO_EXTENSION) === 'sql') {
                    $has_sql = true;
                    $sql_files[] = $filename;
                }
            }
            
            $zip->close();
            
            // Validate SQL file presence
            if (!$has_sql) {
                $errors[] = 'File backup tidak mengandung file database SQL';
            }
            
            // Check for multiple SQL files
            if (count($sql_files) > 1) {
                $errors[] = 'File backup mengandung lebih dari satu file SQL';
            }
            
        } catch (Exception $e) {
            $errors[] = 'Error saat memvalidasi file ZIP: ' . $e->getMessage();
        }
        
        return $errors;
    }
    
    /**
     * Validate SQL file contents
     */
    public static function validateSqlFile($sql_file) {
        $errors = [];
        
        if (!file_exists($sql_file)) {
            $errors[] = 'File SQL tidak ditemukan';
            return $errors;
        }
        
        $sql_content = file_get_contents($sql_file);
        if ($sql_content === false) {
            $errors[] = 'Tidak dapat membaca file SQL';
            return $errors;
        }
        
        // Check for required tables
        $found_tables = [];
        foreach (self::$required_tables as $table) {
            if (preg_match('/CREATE TABLE.*`?' . $table . '`?/i', $sql_content)) {
                $found_tables[] = $table;
            }
        }
        
        $missing_tables = array_diff(self::$required_tables, $found_tables);
        if (!empty($missing_tables)) {
            $errors[] = 'File SQL tidak mengandung tabel yang diperlukan: ' . implode(', ', $missing_tables);
        }
        
        // Check for dangerous SQL commands
        $dangerous_patterns = [
            '/DROP\s+DATABASE/i',
            '/TRUNCATE\s+DATABASE/i',
            '/DELETE\s+FROM\s+mysql\./i',
            '/UPDATE\s+mysql\./i'
        ];
        
        foreach ($dangerous_patterns as $pattern) {
            if (preg_match($pattern, $sql_content)) {
                $errors[] = 'File SQL mengandung perintah berbahaya yang tidak diperbolehkan';
                break;
            }
        }
        
        return $errors;
    }
    
    /**
     * Validate database connection
     */
    public static function validateDatabaseConnection() {
        try {
            require_once 'config/database.php';
            $pdo = Database::getConnection();
            
            // Test connection with simple query
            $pdo->query('SELECT 1');
            
            return [];
        } catch (Exception $e) {
            return ['Koneksi database gagal: ' . $e->getMessage()];
        }
    }
    
    /**
     * Validate system requirements
     */
    public static function validateSystemRequirements() {
        $errors = [];
        
        // Check PHP extensions
        $required_extensions = ['zip', 'pdo', 'pdo_mysql'];
        foreach ($required_extensions as $ext) {
            if (!extension_loaded($ext)) {
                $errors[] = "PHP extension '{$ext}' tidak tersedia";
            }
        }
        
        // Check directory permissions
        $required_dirs = ['tmp/', 'tmp/backup/', 'tmp/restore/', 'uploads/'];
        foreach ($required_dirs as $dir) {
            if (!is_dir($dir)) {
                if (!mkdir($dir, 0755, true)) {
                    $errors[] = "Tidak dapat membuat direktori: {$dir}";
                }
            } elseif (!is_writable($dir)) {
                $errors[] = "Direktori tidak dapat ditulis: {$dir}";
            }
        }
        
        // Check disk space (minimum 100MB free)
        $free_space = disk_free_space('.');
        if ($free_space !== false && $free_space < 100 * 1024 * 1024) {
            $errors[] = 'Ruang disk tidak mencukupi (minimal 100MB diperlukan)';
        }
        
        return $errors;
    }
    
    /**
     * Validate backup file integrity
     */
    public static function validateBackupIntegrity($backup_file) {
        $errors = [];
        
        if (!file_exists($backup_file)) {
            $errors[] = 'File backup tidak ditemukan';
            return $errors;
        }
        
        // Check file size
        $file_size = filesize($backup_file);
        if ($file_size === false || $file_size === 0) {
            $errors[] = 'File backup kosong atau rusak';
            return $errors;
        }
        
        // Test ZIP integrity
        $zip = new ZipArchive();
        $result = $zip->open($backup_file, ZipArchive::CHECKCONS);
        
        if ($result !== TRUE) {
            switch ($result) {
                case ZipArchive::ER_NOZIP:
                    $errors[] = 'File bukan format ZIP yang valid';
                    break;
                case ZipArchive::ER_INCONS:
                    $errors[] = 'File ZIP tidak konsisten atau rusak';
                    break;
                case ZipArchive::ER_CRC:
                    $errors[] = 'Error CRC pada file ZIP';
                    break;
                default:
                    $errors[] = 'File ZIP tidak dapat dibuka (Error code: ' . $result . ')';
            }
        } else {
            $zip->close();
        }
        
        return $errors;
    }
    
    /**
     * Sanitize filename
     */
    public static function sanitizeFilename($filename) {
        // Remove path traversal attempts
        $filename = basename($filename);
        
        // Remove dangerous characters
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
        
        // Limit length
        if (strlen($filename) > 100) {
            $filename = substr($filename, 0, 100);
        }
        
        return $filename;
    }
    
    /**
     * Check if user has permission for backup/restore operations
     */
    public static function validateUserPermissions() {
        require_once 'config/auth.php';
        
        $errors = [];
        
        if (!AuthStatic::isLoggedIn()) {
            $errors[] = 'User tidak terautentikasi';
        }
        
        if (!AuthStatic::hasRole(['admin'])) {
            $errors[] = 'User tidak memiliki hak akses admin';
        }
        
        return $errors;
    }
    
    /**
     * Validate backup before restore operation
     */
    public static function validateBeforeRestore($backup_file) {
        $all_errors = [];
        
        // System requirements
        $system_errors = self::validateSystemRequirements();
        $all_errors = array_merge($all_errors, $system_errors);
        
        // User permissions
        $permission_errors = self::validateUserPermissions();
        $all_errors = array_merge($all_errors, $permission_errors);
        
        // Database connection
        $db_errors = self::validateDatabaseConnection();
        $all_errors = array_merge($all_errors, $db_errors);
        
        // Backup file integrity
        $integrity_errors = self::validateBackupIntegrity($backup_file);
        $all_errors = array_merge($all_errors, $integrity_errors);
        
        // Backup contents
        if (empty($integrity_errors)) {
            $content_errors = self::validateBackupContents($backup_file);
            $all_errors = array_merge($all_errors, $content_errors);
        }
        
        return $all_errors;
    }
    
    /**
     * Validate before backup operation
     */
    public static function validateBeforeBackup() {
        $all_errors = [];
        
        // System requirements
        $system_errors = self::validateSystemRequirements();
        $all_errors = array_merge($all_errors, $system_errors);
        
        // User permissions
        $permission_errors = self::validateUserPermissions();
        $all_errors = array_merge($all_errors, $permission_errors);
        
        // Database connection
        $db_errors = self::validateDatabaseConnection();
        $all_errors = array_merge($all_errors, $db_errors);
        
        return $all_errors;
    }
}
?>