<?php
/**
 * Script Backup Database Sebelum Pembersihan Data Produksi
 * 
 * Script ini akan membuat backup lengkap database sebelum melakukan
 * pembersihan data percobaan untuk persiapan produksi.
 * 
 * @author SMD System
 * @date 2025-01-20
 */

require_once '../config/database.php';

// Konfigurasi backup
$backup_dir = '../backup/';
$backup_filename = 'backup_before_cleanup_' . date('Y-m-d_H-i-s') . '.sql';
$backup_path = $backup_dir . $backup_filename;

// Pastikan direktori backup ada
if (!is_dir($backup_dir)) {
    mkdir($backup_dir, 0755, true);
}

function createDatabaseBackup($host, $username, $password, $database, $backup_path) {
    try {
        // Koneksi ke database
        $pdo = new PDO("mysql:host=$host;dbname=$database;charset=utf8mb4", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Buka file untuk menulis backup
        $backup_file = fopen($backup_path, 'w');
        
        if (!$backup_file) {
            throw new Exception("Tidak dapat membuat file backup: $backup_path");
        }
        
        // Header SQL
        fwrite($backup_file, "-- SMD Database Backup\n");
        fwrite($backup_file, "-- Generated on: " . date('Y-m-d H:i:s') . "\n");
        fwrite($backup_file, "-- Database: $database\n\n");
        fwrite($backup_file, "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n");
        fwrite($backup_file, "START TRANSACTION;\n");
        fwrite($backup_file, "SET time_zone = '+00:00';\n\n");
        
        // Dapatkan semua tabel
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($tables as $table) {
            echo "Backing up table: $table\n";
            
            // Struktur tabel
            fwrite($backup_file, "\n-- --------------------------------------------------------\n");
            fwrite($backup_file, "-- Struktur dari tabel `$table`\n");
            fwrite($backup_file, "-- --------------------------------------------------------\n\n");
            
            $create_table = $pdo->query("SHOW CREATE TABLE `$table`")->fetch();
            fwrite($backup_file, "DROP TABLE IF EXISTS `$table`;\n");
            fwrite($backup_file, $create_table['Create Table'] . ";\n\n");
            
            // Data tabel
            $rows = $pdo->query("SELECT * FROM `$table`");
            $row_count = $pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
            
            if ($row_count > 0) {
                fwrite($backup_file, "-- Dumping data untuk tabel `$table`\n\n");
                
                $columns = $pdo->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_COLUMN);
                $column_list = '`' . implode('`, `', $columns) . '`';
                
                while ($row = $rows->fetch(PDO::FETCH_ASSOC)) {
                    $values = array();
                    foreach ($row as $value) {
                        if ($value === null) {
                            $values[] = 'NULL';
                        } else {
                            $values[] = "'" . addslashes($value) . "'";
                        }
                    }
                    $values_string = implode(', ', $values);
                    fwrite($backup_file, "INSERT INTO `$table` ($column_list) VALUES ($values_string);\n");
                }
                fwrite($backup_file, "\n");
            }
        }
        
        fwrite($backup_file, "COMMIT;\n");
        fclose($backup_file);
        
        return true;
        
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
        return false;
    }
}

// Eksekusi backup
echo "Memulai backup database...\n";
echo "File backup: $backup_path\n";

if (createDatabaseBackup(DB_HOST, DB_USER, DB_PASS, DB_NAME, $backup_path)) {
    echo "Backup berhasil dibuat!\n";
    echo "Ukuran file: " . formatBytes(filesize($backup_path)) . "\n";
    echo "Lokasi: $backup_path\n";
} else {
    echo "Backup gagal!\n";
    exit(1);
}

function formatBytes($size, $precision = 2) {
    $base = log($size, 1024);
    $suffixes = array('B', 'KB', 'MB', 'GB', 'TB');
    return round(pow(1024, $base - floor($base)), $precision) . ' ' . $suffixes[floor($base)];
}

echo "\n=== BACKUP SELESAI ===\n";
echo "Silakan lanjutkan dengan script pembersihan data.\n";
?>