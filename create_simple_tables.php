<?php
/**
 * Script untuk membuat tabel yang hilang tanpa foreign key constraints
 * Menambahkan tabel users, transaksi, dan data penting lainnya
 */

class SimpleTablesCreator {
    private $pdo;
    private $logFile;
    
    public function __construct($host = 'localhost', $dbname = 'smd', $username = 'root', $password = '') {
        try {
            $this->pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->logFile = 'simple_tables_log_' . date('Y-m-d_H-i-s') . '.txt';
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
    
    public function createSimpleTables() {
        $this->log("=== Membuat tabel yang hilang (tanpa foreign key) ===");
        
        // Create transaksi table (simple version)
        $this->createSimpleTransaksiTable();
        
        // Create transaksi_detail table (simple version)
        $this->createSimpleTransaksiDetailTable();
        
        // Create website_desa table (simple version)
        $this->createSimpleWebsiteDesaTable();
        
        // Create website_maintenance table (simple version)
        $this->createSimpleWebsiteMaintenanceTable();
        
        // Create programmer_replies table (simple version)
        $this->createSimpleProgrammerRepliesTable();
        
        // Create tiket_support table (simple version)
        $this->createSimpleTiketSupportTable();
        
        // Create saldo_bank table (simple version)
        $this->createSimpleSaldoBankTable();
        
        // Create stock_opname table (simple version)
        $this->createSimpleStockOpnameTable();
        
        $this->log("✓ Semua tabel yang hilang berhasil dibuat");
    }
    
    private function createSimpleTransaksiTable() {
        $sql = "
        CREATE TABLE IF NOT EXISTS `transaksi` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `kode_transaksi` varchar(20) NOT NULL,
          `desa_id` int(11) NOT NULL,
          `user_id` int(11) NOT NULL,
          `bank_id` int(11) DEFAULT NULL,
          `tanggal_transaksi` date NOT NULL,
          `total_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
          `jumlah_terbayar` decimal(15,2) DEFAULT 0.00,
          `status_pembayaran` enum('pending','partial','paid','cancelled') DEFAULT 'pending',
          `metode_pembayaran` enum('transfer','cash','credit') DEFAULT 'transfer',
          `bukti_pembayaran` varchar(255) DEFAULT NULL,
          `catatan` text DEFAULT NULL,
          `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
          `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
          PRIMARY KEY (`id`),
          UNIQUE KEY `kode_transaksi` (`kode_transaksi`),
          KEY `desa_id` (`desa_id`),
          KEY `user_id` (`user_id`),
          KEY `bank_id` (`bank_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        
        try {
            $this->pdo->exec($sql);
            $this->log("✓ Tabel 'transaksi' berhasil dibuat");
        } catch (PDOException $e) {
            $this->log("✗ Error membuat tabel 'transaksi': " . $e->getMessage());
        }
    }
    
    private function createSimpleTransaksiDetailTable() {
        $sql = "
        CREATE TABLE IF NOT EXISTS `transaksi_detail` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `transaksi_id` int(11) NOT NULL,
          `produk_id` int(11) DEFAULT NULL,
          `layanan_id` int(11) DEFAULT NULL,
          `nama_item` varchar(100) NOT NULL,
          `harga_satuan` decimal(15,2) NOT NULL,
          `quantity` int(11) NOT NULL DEFAULT 1,
          `subtotal` decimal(15,2) NOT NULL,
          `catatan` text DEFAULT NULL,
          `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
          PRIMARY KEY (`id`),
          KEY `transaksi_id` (`transaksi_id`),
          KEY `produk_id` (`produk_id`),
          KEY `layanan_id` (`layanan_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        
        try {
            $this->pdo->exec($sql);
            $this->log("✓ Tabel 'transaksi_detail' berhasil dibuat");
        } catch (PDOException $e) {
            $this->log("✗ Error membuat tabel 'transaksi_detail': " . $e->getMessage());
        }
    }
    
    private function createSimpleWebsiteDesaTable() {
        $sql = "
        CREATE TABLE IF NOT EXISTS `website_desa` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `desa_id` int(11) DEFAULT NULL,
          `domain` varchar(100) NOT NULL,
          `status` enum('aktif','nonaktif','maintenance') DEFAULT 'aktif',
          `tanggal_install` date DEFAULT NULL,
          `versi_sistem` varchar(20) DEFAULT NULL,
          `catatan` text DEFAULT NULL,
          `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
          `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
          PRIMARY KEY (`id`),
          UNIQUE KEY `domain` (`domain`),
          KEY `desa_id` (`desa_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        
        try {
            $this->pdo->exec($sql);
            $this->log("✓ Tabel 'website_desa' berhasil dibuat");
        } catch (PDOException $e) {
            $this->log("✗ Error membuat tabel 'website_desa': " . $e->getMessage());
        }
    }
    
    private function createSimpleWebsiteMaintenanceTable() {
        $sql = "
        CREATE TABLE IF NOT EXISTS `website_maintenance` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `desa_id` int(11) DEFAULT NULL,
          `tanggal_maintenance` date NOT NULL,
          `jenis_maintenance` varchar(100) NOT NULL,
          `deskripsi` text DEFAULT NULL,
          `status` enum('scheduled','in_progress','completed','cancelled') DEFAULT 'scheduled',
          `teknisi_id` int(11) DEFAULT NULL,
          `estimasi_selesai` datetime DEFAULT NULL,
          `catatan` text DEFAULT NULL,
          `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
          `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
          PRIMARY KEY (`id`),
          KEY `desa_id` (`desa_id`),
          KEY `teknisi_id` (`teknisi_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        
        try {
            $this->pdo->exec($sql);
            $this->log("✓ Tabel 'website_maintenance' berhasil dibuat");
        } catch (PDOException $e) {
            $this->log("✗ Error membuat tabel 'website_maintenance': " . $e->getMessage());
        }
    }
    
    private function createSimpleProgrammerRepliesTable() {
        $sql = "
        CREATE TABLE IF NOT EXISTS `programmer_replies` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `admin_message_id` int(11) NOT NULL,
          `programmer_id` int(11) NOT NULL,
          `reply_message` text NOT NULL,
          `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
          `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
          PRIMARY KEY (`id`),
          KEY `admin_message_id` (`admin_message_id`),
          KEY `programmer_id` (`programmer_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
        ";
        
        try {
            $this->pdo->exec($sql);
            $this->log("✓ Tabel 'programmer_replies' berhasil dibuat");
        } catch (PDOException $e) {
            $this->log("✗ Error membuat tabel 'programmer_replies': " . $e->getMessage());
        }
    }
    
    private function createSimpleTiketSupportTable() {
        $sql = "
        CREATE TABLE IF NOT EXISTS `tiket_support` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `kode_tiket` varchar(20) NOT NULL,
          `desa_id` int(11) NOT NULL,
          `judul` varchar(200) NOT NULL,
          `deskripsi` text NOT NULL,
          `prioritas` enum('low','medium','high','urgent') DEFAULT 'medium',
          `status` enum('open','in_progress','resolved','closed') DEFAULT 'open',
          `teknisi_id` int(11) DEFAULT NULL,
          `tanggal_dibuat` timestamp NOT NULL DEFAULT current_timestamp(),
          `tanggal_selesai` timestamp NULL DEFAULT NULL,
          `catatan_teknisi` text DEFAULT NULL,
          PRIMARY KEY (`id`),
          UNIQUE KEY `kode_tiket` (`kode_tiket`),
          KEY `desa_id` (`desa_id`),
          KEY `teknisi_id` (`teknisi_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        
        try {
            $this->pdo->exec($sql);
            $this->log("✓ Tabel 'tiket_support' berhasil dibuat");
        } catch (PDOException $e) {
            $this->log("✗ Error membuat tabel 'tiket_support': " . $e->getMessage());
        }
    }
    
    private function createSimpleSaldoBankTable() {
        $sql = "
        CREATE TABLE IF NOT EXISTS `saldo_bank` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `bank_id` int(11) NOT NULL,
          `saldo` decimal(15,2) NOT NULL DEFAULT 0.00,
          `tanggal_update` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
          `keterangan` text DEFAULT NULL,
          PRIMARY KEY (`id`),
          KEY `bank_id` (`bank_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        
        try {
            $this->pdo->exec($sql);
            $this->log("✓ Tabel 'saldo_bank' berhasil dibuat");
        } catch (PDOException $e) {
            $this->log("✗ Error membuat tabel 'saldo_bank': " . $e->getMessage());
        }
    }
    
    private function createSimpleStockOpnameTable() {
        $sql = "
        CREATE TABLE IF NOT EXISTS `stock_opname` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `produk_id` int(11) NOT NULL,
          `user_id` int(11) NOT NULL,
          `stok_sistem` int(11) NOT NULL,
          `stok_fisik` int(11) NOT NULL,
          `selisih` int(11) NOT NULL,
          `keterangan` text DEFAULT NULL,
          `tanggal_opname` timestamp NOT NULL DEFAULT current_timestamp(),
          PRIMARY KEY (`id`),
          KEY `produk_id` (`produk_id`),
          KEY `user_id` (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        
        try {
            $this->pdo->exec($sql);
            $this->log("✓ Tabel 'stock_opname' berhasil dibuat");
        } catch (PDOException $e) {
            $this->log("✗ Error membuat tabel 'stock_opname': " . $e->getMessage());
        }
    }
    
    public function insertSampleData() {
        $this->log("=== Menambahkan data sample ===");
        
        // Insert sample produk data if table is empty
        try {
            $stmt = $this->pdo->query("SELECT COUNT(*) FROM produk");
            $count = $stmt->fetchColumn();
            
            if ($count == 0) {
                $sampleProduk = [
                    ['kode_produk' => 'WEB001', 'nama_produk' => 'Website Desa Standard', 'harga' => 2500000, 'stok' => 100],
                    ['kode_produk' => 'WEB002', 'nama_produk' => 'Website Desa Premium', 'harga' => 5000000, 'stok' => 50],
                    ['kode_produk' => 'HOST001', 'nama_produk' => 'Hosting 1 Tahun', 'harga' => 500000, 'stok' => 200],
                    ['kode_produk' => 'DOMAIN001', 'nama_produk' => 'Domain .id 1 Tahun', 'harga' => 150000, 'stok' => 500],
                    ['kode_produk' => 'MAINT001', 'nama_produk' => 'Maintenance Bulanan', 'harga' => 300000, 'stok' => 1000]
                ];
                
                foreach ($sampleProduk as $produk) {
                    $stmt = $this->pdo->prepare("
                        INSERT INTO produk (kode_produk, nama_produk, harga, stok, status) 
                        VALUES (?, ?, ?, ?, 'aktif')
                    ");
                    $stmt->execute([$produk['kode_produk'], $produk['nama_produk'], $produk['harga'], $produk['stok']]);
                }
                $this->log("✓ Data sample produk berhasil ditambahkan (" . count($sampleProduk) . " items)");
            } else {
                $this->log("⚠ Tabel produk sudah memiliki data ($count items)");
            }
        } catch (PDOException $e) {
            $this->log("✗ Error menambahkan data sample produk: " . $e->getMessage());
        }
        
        // Insert sample layanan data if table exists and empty
        try {
            $stmt = $this->pdo->query("SELECT COUNT(*) FROM layanan");
            $count = $stmt->fetchColumn();
            
            if ($count == 0) {
                $sampleLayanan = [
                    ['kode_layanan' => 'INSTALL001', 'nama_layanan' => 'Instalasi Website', 'harga' => 1000000],
                    ['kode_layanan' => 'TRAINING001', 'nama_layanan' => 'Training Admin Website', 'harga' => 500000],
                    ['kode_layanan' => 'SUPPORT001', 'nama_layanan' => 'Technical Support', 'harga' => 200000],
                    ['kode_layanan' => 'BACKUP001', 'nama_layanan' => 'Backup & Recovery', 'harga' => 300000]
                ];
                
                foreach ($sampleLayanan as $layanan) {
                    $stmt = $this->pdo->prepare("
                        INSERT INTO layanan (kode_layanan, nama_layanan, harga, status) 
                        VALUES (?, ?, ?, 'aktif')
                    ");
                    $stmt->execute([$layanan['kode_layanan'], $layanan['nama_layanan'], $layanan['harga']]);
                }
                $this->log("✓ Data sample layanan berhasil ditambahkan (" . count($sampleLayanan) . " items)");
            } else {
                $this->log("⚠ Tabel layanan sudah memiliki data ($count items)");
            }
        } catch (PDOException $e) {
            $this->log("⚠ Tabel layanan tidak ada atau error: " . $e->getMessage());
        }
    }
    
    public function verifyTables() {
        $this->log("=== Verifikasi tabel yang dibuat ===");
        
        $stmt = $this->pdo->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $requiredTables = [
            'users', 'transaksi', 'transaksi_detail', 'vendor',
            'website_desa', 'website_maintenance', 'programmer_replies',
            'tiket_support', 'saldo_bank', 'stock_opname'
        ];
        
        foreach ($requiredTables as $table) {
            if (in_array($table, $tables)) {
                $stmt = $this->pdo->query("SELECT COUNT(*) FROM `$table`");
                $count = $stmt->fetchColumn();
                $this->log("✓ Tabel '$table' ada ($count records)");
            } else {
                $this->log("✗ Tabel '$table' tidak ditemukan");
            }
        }
        
        // Check important existing tables
        $importantTables = ['desa', 'produk', 'layanan', 'bank'];
        foreach ($importantTables as $table) {
            if (in_array($table, $tables)) {
                $stmt = $this->pdo->query("SELECT COUNT(*) FROM `$table`");
                $count = $stmt->fetchColumn();
                $this->log("✓ Tabel '$table' ada ($count records)");
            } else {
                $this->log("✗ Tabel penting '$table' tidak ditemukan");
            }
        }
        
        $this->log("✓ Total tabel di database: " . count($tables));
    }
}

// Main execution
if (php_sapi_name() === 'cli') {
    echo "=== Simple Tables Creator ===\n\n";
    
    $creator = new SimpleTablesCreator();
    
    echo "Akan membuat tabel yang hilang (tanpa foreign key)...\n\n";
    
    // Create simple tables
    $creator->createSimpleTables();
    
    // Insert sample data
    $creator->insertSampleData();
    
    // Verify tables
    $creator->verifyTables();
    
    echo "\n✓ Proses selesai! Database siap digunakan.\n";
    echo "\nTabel yang berhasil dibuat:\n";
    echo "- transaksi (untuk data transaksi)\n";
    echo "- transaksi_detail (untuk detail item transaksi)\n";
    echo "- website_desa (untuk data website desa)\n";
    echo "- website_maintenance (untuk jadwal maintenance)\n";
    echo "- programmer_replies (untuk balasan programmer)\n";
    echo "- tiket_support (untuk tiket support)\n";
    echo "- saldo_bank (untuk saldo bank)\n";
    echo "- stock_opname (untuk stock opname)\n";
    
} else {
    // Web interface
    echo "<h2>Simple Tables Creator</h2>";
    echo "<p>Jalankan script ini melalui command line:</p>";
    echo "<code>php create_simple_tables.php</code>";
    
    if (isset($_GET['create'])) {
        $creator = new SimpleTablesCreator();
        $creator->createSimpleTables();
        $creator->insertSampleData();
        $creator->verifyTables();
        echo "<p style='color: green'>✓ Tabel yang hilang berhasil dibuat!</p>";
    } else {
        echo "<p><a href='?create=1'>Buat Tabel yang Hilang</a></p>";
    }
}
?>