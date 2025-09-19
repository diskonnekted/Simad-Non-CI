<?php
/**
 * Script Import SQL untuk simad150925sore.sql
 * Mengatasi masalah tabel yang sudah ada dan error import lainnya
 */

class SQLImporter {
    private $pdo;
    private $logFile;
    
    public function __construct($host = 'localhost', $dbname = 'smd', $username = 'root', $password = '') {
        try {
            $this->pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->logFile = 'import_log_' . date('Y-m-d_H-i-s') . '.txt';
            echo "✓ Koneksi database berhasil\n";
        } catch (PDOException $e) {
            die("✗ Error koneksi database: " . $e->getMessage() . "\n");
        }
    }
    
    private function log($message) {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] $message\n";
        file_put_contents($this->logFile, $logMessage, FILE_APPEND);
        echo $logMessage;
    }
    
    public function checkExistingTables() {
        $this->log("=== Memeriksa tabel yang sudah ada ===");
        
        $stmt = $this->pdo->query("SHOW TABLES");
        $existingTables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (empty($existingTables)) {
            $this->log("Database kosong, siap untuk import");
            return [];
        }
        
        $this->log("Tabel yang sudah ada: " . implode(', ', $existingTables));
        return $existingTables;
    }
    
    public function dropExistingTables($tables) {
        $this->log("=== Menghapus tabel yang sudah ada ===");
        
        // Disable foreign key checks
        $this->pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
        
        foreach ($tables as $table) {
            try {
                $this->pdo->exec("DROP TABLE IF EXISTS `$table`");
                $this->log("✓ Tabel '$table' berhasil dihapus");
            } catch (PDOException $e) {
                $this->log("✗ Error menghapus tabel '$table': " . $e->getMessage());
            }
        }
        
        // Re-enable foreign key checks
        $this->pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    }
    
    public function importSQL($sqlFile) {
        if (!file_exists($sqlFile)) {
            $this->log("✗ File SQL tidak ditemukan: $sqlFile");
            return false;
        }
        
        $this->log("=== Memulai import SQL: $sqlFile ===");
        
        // Read SQL file
        $sql = file_get_contents($sqlFile);
        if ($sql === false) {
            $this->log("✗ Gagal membaca file SQL");
            return false;
        }
        
        // Disable foreign key checks
        $this->pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
        $this->pdo->exec("SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO'");
        
        // Split SQL into individual statements
        $statements = $this->splitSQLStatements($sql);
        
        $successCount = 0;
        $errorCount = 0;
        
        foreach ($statements as $index => $statement) {
            $statement = trim($statement);
            if (empty($statement) || substr($statement, 0, 2) === '--') {
                continue;
            }
            
            try {
                $this->pdo->exec($statement);
                $successCount++;
                
                // Log progress every 50 statements
                if ($successCount % 50 === 0) {
                    $this->log("Progress: $successCount statements berhasil dieksekusi");
                }
            } catch (PDOException $e) {
                $errorCount++;
                $errorMsg = $e->getMessage();
                
                // Skip certain errors that are acceptable
                if (strpos($errorMsg, 'already exists') !== false) {
                    $this->log("⚠ Dilewati (tabel sudah ada): Statement #" . ($index + 1));
                    continue;
                }
                
                $this->log("✗ Error pada statement #" . ($index + 1) . ": $errorMsg");
                $this->log("Statement: " . substr($statement, 0, 100) . "...");
                
                // Stop on critical errors
                if (strpos($errorMsg, 'syntax error') !== false || 
                    strpos($errorMsg, 'Unknown column') !== false) {
                    $this->log("✗ Critical error detected, stopping import");
                    break;
                }
            }
        }
        
        // Re-enable foreign key checks
        $this->pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
        
        $this->log("=== Import selesai ===");
        $this->log("✓ Berhasil: $successCount statements");
        $this->log("✗ Error: $errorCount statements");
        
        return $errorCount === 0;
    }
    
    private function splitSQLStatements($sql) {
        // Remove comments and split by semicolon
        $sql = preg_replace('/--.*$/m', '', $sql);
        $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);
        
        $statements = explode(';', $sql);
        return array_filter($statements, function($stmt) {
            return !empty(trim($stmt));
        });
    }
    
    public function verifyImport() {
        $this->log("=== Verifikasi hasil import ===");
        
        $stmt = $this->pdo->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $this->log("Total tabel setelah import: " . count($tables));
        
        foreach ($tables as $table) {
            try {
                $stmt = $this->pdo->query("SELECT COUNT(*) FROM `$table`");
                $count = $stmt->fetchColumn();
                $this->log("✓ Tabel '$table': $count records");
            } catch (PDOException $e) {
                $this->log("✗ Error mengecek tabel '$table': " . $e->getMessage());
            }
        }
    }
}

// Main execution
if (php_sapi_name() === 'cli') {
    echo "=== SQL Importer untuk simad150925sore.sql ===\n\n";
    
    $sqlFile = 'simad150925sore/desaonline.cloud/simadorbitdev_smd.sql';
    
    if (!file_exists($sqlFile)) {
        echo "✗ File SQL tidak ditemukan: $sqlFile\n";
        echo "Pastikan file berada di lokasi yang benar\n";
        exit(1);
    }
    
    $importer = new SQLImporter();
    
    // Check existing tables
    $existingTables = $importer->checkExistingTables();
    
    if (!empty($existingTables)) {
        echo "\nTerdapat " . count($existingTables) . " tabel yang sudah ada.\n";
        echo "Pilihan:\n";
        echo "1. Hapus semua tabel dan import ulang (RECOMMENDED)\n";
        echo "2. Lanjutkan import (mungkin ada error)\n";
        echo "3. Batal\n";
        echo "Pilih (1-3): ";
        
        $choice = trim(fgets(STDIN));
        
        switch ($choice) {
            case '1':
                $importer->dropExistingTables($existingTables);
                break;
            case '2':
                echo "Melanjutkan import tanpa menghapus tabel...\n";
                break;
            case '3':
            default:
                echo "Import dibatalkan\n";
                exit(0);
        }
    }
    
    // Import SQL
    $success = $importer->importSQL($sqlFile);
    
    // Verify import
    $importer->verifyImport();
    
    if ($success) {
        echo "\n✓ Import berhasil diselesaikan!\n";
    } else {
        echo "\n⚠ Import selesai dengan beberapa error. Periksa log untuk detail.\n";
    }
    
} else {
    // Web interface
    echo "<h2>SQL Importer untuk simad150925sore.sql</h2>";
    echo "<p>Jalankan script ini melalui command line untuk import interaktif:</p>";
    echo "<code>php import_simad150925sore.php</code>";
    
    if (isset($_GET['auto'])) {
        $importer = new SQLImporter();
        $existingTables = $importer->checkExistingTables();
        
        if (!empty($existingTables)) {
            $importer->dropExistingTables($existingTables);
        }
        
        $sqlFile = 'simad150925sore/desaonline.cloud/simadorbitdev_smd.sql';
        $success = $importer->importSQL($sqlFile);
        $importer->verifyImport();
        
        echo $success ? "<p style='color: green'>✓ Import berhasil!</p>" : "<p style='color: orange'>⚠ Import selesai dengan error</p>";
    } else {
        echo "<p><a href='?auto=1'>Jalankan Auto Import</a></p>";
    }
}
?>