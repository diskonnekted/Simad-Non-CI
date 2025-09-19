<?php
/**
 * Script untuk memeriksa dan mengatasi konflik tabel database
 * Khususnya untuk mengatasi error #1050 - Table already exists
 */

require_once 'config/database.php';

class TableConflictChecker {
    private $pdo;
    
    public function __construct() {
        try {
            $this->pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]
            );
        } catch (PDOException $e) {
            die("Koneksi database gagal: " . $e->getMessage());
        }
    }
    
    /**
     * Cek apakah tabel sudah ada
     */
    public function tableExists($tableName) {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT COUNT(*) as count FROM information_schema.tables 
                 WHERE table_schema = ? AND table_name = ?"
            );
            $stmt->execute([DB_NAME, $tableName]);
            $result = $stmt->fetch();
            return $result['count'] > 0;
        } catch (PDOException $e) {
            echo "Error checking table: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Tampilkan struktur tabel
     */
    public function showTableStructure($tableName) {
        try {
            if (!$this->tableExists($tableName)) {
                echo "Tabel '$tableName' tidak ditemukan.\n";
                return false;
            }
            
            $stmt = $this->pdo->prepare("DESCRIBE `$tableName`");
            $stmt->execute();
            $columns = $stmt->fetchAll();
            
            echo "\n=== Struktur Tabel '$tableName' ===\n";
            printf("%-20s %-15s %-10s %-10s %-15s %-10s\n", 
                   'Field', 'Type', 'Null', 'Key', 'Default', 'Extra');
            echo str_repeat('-', 80) . "\n";
            
            foreach ($columns as $column) {
                printf("%-20s %-15s %-10s %-10s %-15s %-10s\n",
                    $column['Field'],
                    $column['Type'],
                    $column['Null'],
                    $column['Key'],
                    $column['Default'] ?? 'NULL',
                    $column['Extra']
                );
            }
            echo "\n";
            return true;
        } catch (PDOException $e) {
            echo "Error showing table structure: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Hitung jumlah record dalam tabel
     */
    public function countRecords($tableName) {
        try {
            if (!$this->tableExists($tableName)) {
                return 0;
            }
            
            $stmt = $this->pdo->prepare("SELECT COUNT(*) as count FROM `$tableName`");
            $stmt->execute();
            $result = $stmt->fetch();
            return $result['count'];
        } catch (PDOException $e) {
            echo "Error counting records: " . $e->getMessage() . "\n";
            return 0;
        }
    }
    
    /**
     * Backup tabel sebelum drop
     */
    public function backupTable($tableName) {
        try {
            if (!$this->tableExists($tableName)) {
                echo "Tabel '$tableName' tidak ditemukan untuk backup.\n";
                return false;
            }
            
            $backupTableName = $tableName . '_backup_' . date('Ymd_His');
            
            // Buat tabel backup
            $sql = "CREATE TABLE `$backupTableName` AS SELECT * FROM `$tableName`";
            $this->pdo->exec($sql);
            
            echo "Backup tabel '$tableName' berhasil dibuat sebagai '$backupTableName'\n";
            return $backupTableName;
        } catch (PDOException $e) {
            echo "Error creating backup: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Drop tabel dengan konfirmasi
     */
    public function dropTable($tableName, $force = false) {
        try {
            if (!$this->tableExists($tableName)) {
                echo "Tabel '$tableName' tidak ditemukan.\n";
                return true;
            }
            
            $recordCount = $this->countRecords($tableName);
            
            if (!$force && $recordCount > 0) {
                echo "PERINGATAN: Tabel '$tableName' berisi $recordCount record(s).\n";
                echo "Gunakan parameter force=true untuk menghapus paksa.\n";
                return false;
            }
            
            // Backup dulu jika ada data
            if ($recordCount > 0) {
                $this->backupTable($tableName);
            }
            
            $this->pdo->exec("DROP TABLE `$tableName`");
            echo "Tabel '$tableName' berhasil dihapus.\n";
            return true;
        } catch (PDOException $e) {
            echo "Error dropping table: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Buat tabel activity_logs dengan struktur yang benar
     */
    public function createActivityLogsTable() {
        try {
            $sql = "
                CREATE TABLE IF NOT EXISTS `activity_logs` (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `user_id` int(11) NOT NULL,
                  `activity_type` varchar(50) NOT NULL,
                  `description` text NOT NULL,
                  `target_table` varchar(50) DEFAULT NULL,
                  `target_id` int(11) DEFAULT NULL,
                  `ip_address` varchar(45) DEFAULT NULL,
                  `user_agent` text DEFAULT NULL,
                  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                  PRIMARY KEY (`id`),
                  KEY `idx_user_id` (`user_id`),
                  KEY `idx_activity_type` (`activity_type`),
                  KEY `idx_created_at` (`created_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ";
            
            $this->pdo->exec($sql);
            echo "Tabel 'activity_logs' berhasil dibuat atau sudah ada.\n";
            return true;
        } catch (PDOException $e) {
            echo "Error creating activity_logs table: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Tampilkan semua tabel dalam database
     */
    public function showAllTables() {
        try {
            $stmt = $this->pdo->prepare("SHOW TABLES");
            $stmt->execute();
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            echo "\n=== Daftar Tabel dalam Database '" . DB_NAME . "' ===\n";
            foreach ($tables as $table) {
                $count = $this->countRecords($table);
                echo "- $table ($count records)\n";
            }
            echo "\n";
        } catch (PDOException $e) {
            echo "Error showing tables: " . $e->getMessage() . "\n";
        }
    }
}

// Penggunaan script
if (php_sapi_name() === 'cli' || isset($_GET['run'])) {
    echo "=== Table Conflict Checker ===\n\n";
    
    $checker = new TableConflictChecker();
    
    // Tampilkan semua tabel
    $checker->showAllTables();
    
    // Cek tabel activity_logs
    echo "Mengecek tabel 'activity_logs'...\n";
    if ($checker->tableExists('activity_logs')) {
        echo "✓ Tabel 'activity_logs' sudah ada.\n";
        $checker->showTableStructure('activity_logs');
        
        $recordCount = $checker->countRecords('activity_logs');
        echo "Jumlah record: $recordCount\n\n";
        
        echo "SOLUSI untuk error #1050:\n";
        echo "1. Gunakan CREATE TABLE IF NOT EXISTS dalam script SQL\n";
        echo "2. Atau hapus tabel yang ada dengan: \$checker->dropTable('activity_logs', true)\n";
        echo "3. Atau skip pembuatan tabel ini dalam import\n\n";
    } else {
        echo "✗ Tabel 'activity_logs' belum ada.\n";
        echo "Membuat tabel 'activity_logs'...\n";
        $checker->createActivityLogsTable();
    }
    
    echo "\n=== Selesai ===\n";
} else {
    echo "<h2>Table Conflict Checker</h2>";
    echo "<p><a href='?run=1'>Jalankan Pengecekan</a></p>";
    echo "<p>Atau jalankan dari command line: <code>php check_table_conflicts.php</code></p>";
}
?>