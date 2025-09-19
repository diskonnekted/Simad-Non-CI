<?php
/**
 * Backup and Restore Helper Functions
 * Provides automated backup and restore functionality for database and files
 */

class BackupHelper {
    
    private static $backup_dir = 'tmp/backup/';
    private static $temp_dir = 'tmp/restore/';
    private static $uploads_dir = 'uploads/';
    
    /**
     * Create automatic backup with timestamp
     */
    public static function createAutoBackup($include_files = true) {
        try {
            // Create backup directory if not exists
            if (!is_dir(self::$backup_dir)) {
                mkdir(self::$backup_dir, 0755, true);
            }
            
            $timestamp = date('Y-m-d_H-i-s');
            $backup_filename = "auto_backup_{$timestamp}.zip";
            $backup_path = self::$backup_dir . $backup_filename;
            
            // Create ZIP archive
            $zip = new ZipArchive();
            if ($zip->open($backup_path, ZipArchive::CREATE) !== TRUE) {
                throw new Exception('Gagal membuat file ZIP');
            }
            
            // Export database
            $sql_content = self::exportDatabase();
            $zip->addFromString("database_backup_{$timestamp}.sql", $sql_content);
            
            // Add upload files if requested
            if ($include_files && is_dir(self::$uploads_dir)) {
                self::addDirectoryToZip($zip, self::$uploads_dir, 'uploads/');
            }
            
            $zip->close();
            
            return [
                'success' => true,
                'filename' => $backup_filename,
                'path' => $backup_path,
                'size' => filesize($backup_path)
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Restore from backup file
     */
    public static function restoreFromBackup($backup_filename, $restore_files = true) {
        try {
            $backup_path = self::$backup_dir . basename($backup_filename);
            
            if (!file_exists($backup_path)) {
                throw new Exception('File backup tidak ditemukan');
            }
            
            // Create temp directory
            if (!is_dir(self::$temp_dir)) {
                mkdir(self::$temp_dir, 0755, true);
            }
            
            // Extract backup
            $extract_dir = self::$temp_dir . 'restore_' . time() . '/';
            $zip = new ZipArchive();
            
            if ($zip->open($backup_path) !== TRUE) {
                throw new Exception('Gagal membuka file backup');
            }
            
            if (!$zip->extractTo($extract_dir)) {
                $zip->close();
                throw new Exception('Gagal mengekstrak backup');
            }
            $zip->close();
            
            // Find and restore database
            $sql_file = self::findSqlFile($extract_dir);
            if ($sql_file) {
                self::importDatabase($sql_file);
            }
            
            // Restore files if requested
            if ($restore_files) {
                $uploads_backup = $extract_dir . 'uploads/';
                if (is_dir($uploads_backup)) {
                    self::restoreUploads($uploads_backup);
                }
            }
            
            // Clean up
            self::deleteDirectory($extract_dir);
            
            return [
                'success' => true,
                'message' => 'Restore berhasil dilakukan'
            ];
            
        } catch (Exception $e) {
            // Clean up on error
            if (isset($extract_dir) && is_dir($extract_dir)) {
                self::deleteDirectory($extract_dir);
            }
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Export database to SQL string
     */
    private static function exportDatabase() {
        require_once 'config/database.php';
        
        $pdo = Database::getConnection();
        $sql_content = "-- SMD Database Backup\n";
        $sql_content .= "-- Generated on: " . date('Y-m-d H:i:s') . "\n\n";
        
        // Get all tables
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($tables as $table) {
            // Drop table if exists
            $sql_content .= "DROP TABLE IF EXISTS `{$table}`;\n";
            
            // Get create table statement
            $create_table = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch();
            $sql_content .= $create_table['Create Table'] . ";\n\n";
            
            // Get table data
            $rows = $pdo->query("SELECT * FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($rows)) {
                $columns = array_keys($rows[0]);
                $sql_content .= "INSERT INTO `{$table}` (`" . implode('`, `', $columns) . "`) VALUES\n";
                
                $values = [];
                foreach ($rows as $row) {
                    $escaped_values = [];
                    foreach ($row as $value) {
                        if ($value === null) {
                            $escaped_values[] = 'NULL';
                        } else {
                            $escaped_values[] = $pdo->quote($value);
                        }
                    }
                    $values[] = '(' . implode(', ', $escaped_values) . ')';
                }
                
                $sql_content .= implode(",\n", $values) . ";\n\n";
            }
        }
        
        return $sql_content;
    }
    
    /**
     * Import database from SQL file
     */
    private static function importDatabase($sql_file) {
        require_once 'config/database.php';
        
        $sql_content = file_get_contents($sql_file);
        if ($sql_content === false) {
            throw new Exception('Gagal membaca file SQL');
        }
        
        $queries = array_filter(array_map('trim', explode(';', $sql_content)));
        
        $pdo = Database::getConnection();
        $pdo->beginTransaction();
        
        try {
            foreach ($queries as $query) {
                if (!empty($query) && !preg_match('/^\s*--/', $query)) {
                    $pdo->exec($query);
                }
            }
            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollback();
            throw new Exception('Gagal mengeksekusi SQL: ' . $e->getMessage());
        }
    }
    
    /**
     * Add directory to ZIP recursively
     */
    private static function addDirectoryToZip($zip, $dir, $zip_dir = '') {
        $files = scandir($dir);
        
        foreach ($files as $file) {
            if ($file != '.' && $file != '..') {
                $file_path = $dir . $file;
                $zip_path = $zip_dir . $file;
                
                if (is_dir($file_path)) {
                    $zip->addEmptyDir($zip_path);
                    self::addDirectoryToZip($zip, $file_path . '/', $zip_path . '/');
                } else {
                    $zip->addFile($file_path, $zip_path);
                }
            }
        }
    }
    
    /**
     * Restore uploads directory
     */
    private static function restoreUploads($uploads_backup) {
        // Backup existing uploads
        if (is_dir(self::$uploads_dir)) {
            $backup_existing = 'tmp/uploads_backup_' . time();
            rename(self::$uploads_dir, $backup_existing);
        }
        
        // Create uploads directory
        mkdir(self::$uploads_dir, 0755, true);
        
        // Copy files recursively
        self::copyDirectory($uploads_backup, self::$uploads_dir);
    }
    
    /**
     * Copy directory recursively
     */
    private static function copyDirectory($src, $dst) {
        $dir = opendir($src);
        if (!is_dir($dst)) {
            mkdir($dst, 0755, true);
        }
        
        while (($file = readdir($dir)) !== false) {
            if ($file != '.' && $file != '..') {
                $src_file = $src . '/' . $file;
                $dst_file = $dst . '/' . $file;
                
                if (is_dir($src_file)) {
                    self::copyDirectory($src_file, $dst_file);
                } else {
                    copy($src_file, $dst_file);
                }
            }
        }
        closedir($dir);
    }
    
    /**
     * Delete directory recursively
     */
    private static function deleteDirectory($dir) {
        if (!is_dir($dir)) return;
        
        $files = array_diff(scandir($dir), array('.', '..'));
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? self::deleteDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
    
    /**
     * Find SQL file in directory
     */
    private static function findSqlFile($dir) {
        $files = scandir($dir);
        foreach ($files as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) === 'sql') {
                return $dir . $file;
            }
        }
        return null;
    }
    
    /**
     * Get list of backup files
     */
    public static function getBackupFiles() {
        $backup_files = [];
        
        if (is_dir(self::$backup_dir)) {
            $files = scandir(self::$backup_dir);
            foreach ($files as $file) {
                if (pathinfo($file, PATHINFO_EXTENSION) === 'zip') {
                    $file_path = self::$backup_dir . $file;
                    $backup_files[] = [
                        'name' => $file,
                        'size' => filesize($file_path),
                        'date' => date('Y-m-d H:i:s', filemtime($file_path)),
                        'path' => $file_path
                    ];
                }
            }
            
            // Sort by date descending
            usort($backup_files, function($a, $b) {
                return strtotime($b['date']) - strtotime($a['date']);
            });
        }
        
        return $backup_files;
    }
    
    /**
     * Delete backup file
     */
    public static function deleteBackup($filename) {
        $file_path = self::$backup_dir . basename($filename);
        
        if (file_exists($file_path) && pathinfo($file_path, PATHINFO_EXTENSION) === 'zip') {
            return unlink($file_path);
        }
        
        return false;
    }
    
    /**
     * Clean old backups (keep only latest N backups)
     */
    public static function cleanOldBackups($keep_count = 10) {
        $backup_files = self::getBackupFiles();
        
        if (count($backup_files) > $keep_count) {
            $files_to_delete = array_slice($backup_files, $keep_count);
            
            foreach ($files_to_delete as $file) {
                self::deleteBackup($file['name']);
            }
            
            return count($files_to_delete);
        }
        
        return 0;
    }
}
?>