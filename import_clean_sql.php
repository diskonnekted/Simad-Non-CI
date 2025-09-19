<?php
/**
 * Script Import untuk file SQL yang sudah diperbaiki
 * Mengimpor simadorbitdev_smd_clean.sql
 */

class CleanSQLImporter {
    private $pdo;
    private $logFile;
    
    public function __construct($host = 'localhost', $dbname = 'smd', $username = 'root', $password = '') {
        try {
            $this->pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->logFile = 'clean_import_log_' . date('Y-m-d_H-i-s') . '.txt';
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
    
    public function clearDatabase() {
        $this->log("=== Membersihkan database ===");
        
        // Disable foreign key checks
        $this->pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
        
        // Get all tables
        $stmt = $this->pdo->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($tables as $table) {
            try {
                $this->pdo->exec("DROP TABLE IF EXISTS `$table`");
                $this->log("✓ Tabel '$table' dihapus");
            } catch (PDOException $e) {
                $this->log("✗ Error menghapus tabel '$table': " . $e->getMessage());
            }
        }
        
        // Re-enable foreign key checks
        $this->pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
        
        $this->log("✓ Database berhasil dibersihkan");
    }
    
    public function importCleanSQL($sqlFile) {
        if (!file_exists($sqlFile)) {
            $this->log("✗ File SQL tidak ditemukan: $sqlFile");
            return false;
        }
        
        $this->log("=== Memulai import SQL bersih: $sqlFile ===");
        
        // Read SQL file
        $sql = file_get_contents($sqlFile);
        if ($sql === false) {
            $this->log("✗ Gagal membaca file SQL");
            return false;
        }
        
        // Setup MySQL settings
        $this->pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
        $this->pdo->exec("SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO'");
        $this->pdo->exec("SET AUTOCOMMIT = 0");
        $this->pdo->exec("START TRANSACTION");
        
        try {
            // Split SQL into statements
            $statements = $this->splitSQLStatements($sql);
            
            $successCount = 0;
            $errorCount = 0;
            $skipCount = 0;
            
            foreach ($statements as $index => $statement) {
                $statement = trim($statement);
                if (empty($statement) || substr($statement, 0, 2) === '--') {
                    continue;
                }
                
                // Skip corrupted data comments
                if (strpos($statement, 'data removed due to corruption') !== false) {
                    $skipCount++;
                    continue;
                }
                
                try {
                    $this->pdo->exec($statement);
                    $successCount++;
                    
                    // Log progress every 25 statements
                    if ($successCount % 25 === 0) {
                        $this->log("Progress: $successCount statements berhasil dieksekusi");
                    }
                } catch (PDOException $e) {
                    $errorCount++;
                    $errorMsg = $e->getMessage();
                    
                    $this->log("✗ Error pada statement #" . ($index + 1) . ": $errorMsg");
                    $this->log("Statement: " . substr($statement, 0, 100) . "...");
                    
                    // Stop on critical errors
                    if (strpos($errorMsg, 'syntax error') !== false) {
                        $this->log("✗ Critical syntax error, rolling back");
                        $this->pdo->exec("ROLLBACK");
                        return false;
                    }
                }
            }
            
            // Commit transaction
            $this->pdo->exec("COMMIT");
            $this->pdo->exec("SET AUTOCOMMIT = 1");
            
            // Re-enable foreign key checks
            $this->pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
            
            $this->log("=== Import selesai ===");
            $this->log("✓ Berhasil: $successCount statements");
            $this->log("⚠ Dilewati: $skipCount statements (data korup)");
            $this->log("✗ Error: $errorCount statements");
            
            return $errorCount === 0;
            
        } catch (Exception $e) {
            $this->pdo->exec("ROLLBACK");
            $this->log("✗ Fatal error: " . $e->getMessage());
            return false;
        }
    }
    
    private function splitSQLStatements($sql) {
        // Remove comments
        $sql = preg_replace('/--.*$/m', '', $sql);
        $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);
        
        // Split by semicolon but be careful with strings
        $statements = [];
        $current = '';
        $inString = false;
        $stringChar = '';
        
        for ($i = 0; $i < strlen($sql); $i++) {
            $char = $sql[$i];
            
            if (!$inString && ($char === '"' || $char === "'")) {
                $inString = true;
                $stringChar = $char;
            } elseif ($inString && $char === $stringChar && $sql[$i-1] !== '\\') {
                $inString = false;
                $stringChar = '';
            } elseif (!$inString && $char === ';') {
                $statements[] = trim($current);
                $current = '';
                continue;
            }
            
            $current .= $char;
        }
        
        if (!empty(trim($current))) {
            $statements[] = trim($current);
        }
        
        return array_filter($statements, function($stmt) {
            return !empty(trim($stmt));
        });
    }
    
    public function verifyImport() {
        $this->log("=== Verifikasi hasil import ===");
        
        $stmt = $this->pdo->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $this->log("Total tabel setelah import: " . count($tables));
        
        $totalRecords = 0;
        foreach ($tables as $table) {
            try {
                $stmt = $this->pdo->query("SELECT COUNT(*) FROM `$table`");
                $count = $stmt->fetchColumn();
                $totalRecords += $count;
                $this->log("✓ Tabel '$table': $count records");
            } catch (PDOException $e) {
                $this->log("✗ Error mengecek tabel '$table': " . $e->getMessage());
            }
        }
        
        $this->log("✓ Total records di semua tabel: $totalRecords");
        
        // Cek tabel penting
        $importantTables = ['desa', 'users', 'transaksi', 'produk', 'layanan'];
        foreach ($importantTables as $table) {
            if (in_array($table, $tables)) {
                $stmt = $this->pdo->query("SELECT COUNT(*) FROM `$table`");
                $count = $stmt->fetchColumn();
                if ($count > 0) {
                    $this->log("✓ Tabel penting '$table' berisi data ($count records)");
                } else {
                    $this->log("⚠ Tabel penting '$table' kosong");
                }
            } else {
                $this->log("✗ Tabel penting '$table' tidak ditemukan");
            }
        }
    }
}

// Main execution
if (php_sapi_name() === 'cli') {
    echo "=== Clean SQL Importer ===\n\n";
    
    $sqlFile = 'simad150925sore/desaonline.cloud/simadorbitdev_smd_clean.sql';
    
    if (!file_exists($sqlFile)) {
        echo "✗ File SQL bersih tidak ditemukan: $sqlFile\n";
        echo "Jalankan fix_sql_file.php terlebih dahulu\n";
        exit(1);
    }
    
    $importer = new CleanSQLImporter();
    
    echo "Akan mengimpor file SQL yang sudah dibersihkan.\n";
    echo "File: $sqlFile\n\n";
    
    echo "PERINGATAN: Ini akan menghapus semua data yang ada di database!\n";
    echo "Lanjutkan? (y/N): ";
    
    $confirm = trim(fgets(STDIN));
    if (strtolower($confirm) !== 'y') {
        echo "Import dibatalkan\n";
        exit(0);
    }
    
    // Clear database
    $importer->clearDatabase();
    
    // Import clean SQL
    $success = $importer->importCleanSQL($sqlFile);
    
    // Verify import
    $importer->verifyImport();
    
    if ($success) {
        echo "\n✓ Import berhasil diselesaikan!\n";
        echo "Database siap digunakan.\n";
    } else {
        echo "\n⚠ Import selesai dengan beberapa error. Periksa log untuk detail.\n";
    }
    
} else {
    // Web interface
    echo "<h2>Clean SQL Importer</h2>";
    echo "<p>Jalankan script ini melalui command line:</p>";
    echo "<code>php import_clean_sql.php</code>";
    
    if (isset($_GET['import'])) {
        $sqlFile = 'simad150925sore/desaonline.cloud/simadorbitdev_smd_clean.sql';
        $importer = new CleanSQLImporter();
        
        $importer->clearDatabase();
        $success = $importer->importCleanSQL($sqlFile);
        $importer->verifyImport();
        
        echo $success ? "<p style='color: green'>✓ Import berhasil!</p>" : "<p style='color: orange'>⚠ Import selesai dengan error</p>";
    } else {
        echo "<p><a href='?import=1'>Jalankan Import Bersih</a></p>";
    }
}
?>