<?php
/**
 * Script untuk membuat tabel yang hilang dan mengisi data dasar
 * Menambahkan tabel users, transaksi, dan data penting lainnya
 */

class MissingTablesCreator {
    private $pdo;
    private $logFile;
    
    public function __construct($host = 'localhost', $dbname = 'smd', $username = 'root', $password = '') {
        try {
            $this->pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->logFile = 'missing_tables_log_' . date('Y-m-d_H-i-s') . '.txt';
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
    
    public function createMissingTables() {
        $this->log("=== Membuat tabel yang hilang ===");
        
        // Create users table
        $this->createUsersTable();
        
        // Create transaksi table
        $this->createTransaksiTable();
        
        // Create transaksi_detail table
        $this->createTransaksiDetailTable();
        
        // Create vendor table if not exists
        $this->createVendorTable();
        
        // Create website_desa table if not exists
        $this->createWebsiteDesaTable();
        
        // Create website_maintenance table if not exists
        $this->createWebsiteMaintenanceTable();
        
        // Create programmer_replies table if not exists
        $this->createProgrammerRepliesTable();
        
        // Create tiket_support table if not exists
        $this->createTiketSupportTable();
        
        // Create saldo_bank table if not exists
        $this->createSaldoBankTable();
        
        // Create stock_opname table if not exists
        $this->createStockOpnameTable();
        
        $this->log("✓ Semua tabel yang hilang berhasil dibuat");
    }
    
    private function createUsersTable() {
        $sql = "
        CREATE TABLE IF NOT EXISTS `users` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `username` varchar(50) NOT NULL,
          `password` varchar(255) NOT NULL,
          `nama_lengkap` varchar(100) NOT NULL,
          `email` varchar(100) DEFAULT NULL,
          `role` enum('admin','teknisi','finance','programmer') NOT NULL DEFAULT 'teknisi',
          `status` enum('aktif','nonaktif') DEFAULT 'aktif',
          `last_login` timestamp NULL DEFAULT NULL,
          `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
          `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
          PRIMARY KEY (`id`),
          UNIQUE KEY `username` (`username`),
          UNIQUE KEY `email` (`email`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        
        try {
            $this->pdo->exec($sql);
            $this->log("✓ Tabel 'users' berhasil dibuat");
            
            // Insert default admin user
            $this->insertDefaultUsers();
        } catch (PDOException $e) {
            $this->log("✗ Error membuat tabel 'users': " . $e->getMessage());
        }
    }
    
    private function insertDefaultUsers() {
        $users = [
            [
                'username' => 'admin',
                'password' => password_hash('admin123', PASSWORD_DEFAULT),
                'nama_lengkap' => 'Administrator',
                'email' => 'admin@simad.local',
                'role' => 'admin'
            ],
            [
                'username' => 'teknisi',
                'password' => password_hash('teknisi123', PASSWORD_DEFAULT),
                'nama_lengkap' => 'Teknisi SIMAD',
                'email' => 'teknisi@simad.local',
                'role' => 'teknisi'
            ],
            [
                'username' => 'finance',
                'password' => password_hash('finance123', PASSWORD_DEFAULT),
                'nama_lengkap' => 'Finance SIMAD',
                'email' => 'finance@simad.local',
                'role' => 'finance'
            ]
        ];
        
        foreach ($users as $user) {
            try {
                $stmt = $this->pdo->prepare("
                    INSERT IGNORE INTO users (username, password, nama_lengkap, email, role) 
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $user['username'],
                    $user['password'],
                    $user['nama_lengkap'],
                    $user['email'],
                    $user['role']
                ]);
                $this->log("✓ User '{$user['username']}' berhasil ditambahkan");
            } catch (PDOException $e) {
                $this->log("⚠ User '{$user['username']}' sudah ada atau error: " . $e->getMessage());
            }
        }
    }
    
    private function createTransaksiTable() {
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
          KEY `bank_id` (`bank_id`),
          CONSTRAINT `transaksi_ibfk_1` FOREIGN KEY (`desa_id`) REFERENCES `desa` (`id`),
          CONSTRAINT `transaksi_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
          CONSTRAINT `transaksi_ibfk_3` FOREIGN KEY (`bank_id`) REFERENCES `bank` (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        
        try {
            $this->pdo->exec($sql);
            $this->log("✓ Tabel 'transaksi' berhasil dibuat");
        } catch (PDOException $e) {
            $this->log("✗ Error membuat tabel 'transaksi': " . $e->getMessage());
        }
    }
    
    private function createTransaksiDetailTable() {
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
          KEY `layanan_id` (`layanan_id`),
          CONSTRAINT `transaksi_detail_ibfk_1` FOREIGN KEY (`transaksi_id`) REFERENCES `transaksi` (`id`) ON DELETE CASCADE,
          CONSTRAINT `transaksi_detail_ibfk_2` FOREIGN KEY (`produk_id`) REFERENCES `produk` (`id`),
          CONSTRAINT `transaksi_detail_ibfk_3` FOREIGN KEY (`layanan_id`) REFERENCES `layanan` (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        
        try {
            $this->pdo->exec($sql);
            $this->log("✓ Tabel 'transaksi_detail' berhasil dibuat");
        } catch (PDOException $e) {
            $this->log("✗ Error membuat tabel 'transaksi_detail': " . $e->getMessage());
        }
    }
    
    private function createVendorTable() {
        $sql = "
        CREATE TABLE IF NOT EXISTS `vendor` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `kode_vendor` varchar(20) NOT NULL,
          `nama_vendor` varchar(100) NOT NULL,
          `alamat` text DEFAULT NULL,
          `telepon` varchar(20) DEFAULT NULL,
          `email` varchar(100) DEFAULT NULL,
          `kontak_person` varchar(100) DEFAULT NULL,
          `status` enum('aktif','nonaktif') DEFAULT 'aktif',
          `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
          `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
          PRIMARY KEY (`id`),
          UNIQUE KEY `kode_vendor` (`kode_vendor`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        
        try {
            $this->pdo->exec($sql);
            $this->log("✓ Tabel 'vendor' berhasil dibuat");
        } catch (PDOException $e) {
            $this->log("✗ Error membuat tabel 'vendor': " . $e->getMessage());
        }
    }
    
    private function createWebsiteDesaTable() {
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
          KEY `desa_id` (`desa_id`),
          CONSTRAINT `website_desa_ibfk_1` FOREIGN KEY (`desa_id`) REFERENCES `desa` (`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        
        try {
            $this->pdo->exec($sql);
            $this->log("✓ Tabel 'website_desa' berhasil dibuat");
        } catch (PDOException $e) {
            $this->log("✗ Error membuat tabel 'website_desa': " . $e->getMessage());
        }
    }
    
    private function createWebsiteMaintenanceTable() {
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
          KEY `teknisi_id` (`teknisi_id`),
          CONSTRAINT `website_maintenance_ibfk_1` FOREIGN KEY (`desa_id`) REFERENCES `desa` (`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        
        try {
            $this->pdo->exec($sql);
            $this->log("✓ Tabel 'website_maintenance' berhasil dibuat");
        } catch (PDOException $e) {
            $this->log("✗ Error membuat tabel 'website_maintenance': " . $e->getMessage());
        }
    }
    
    private function createProgrammerRepliesTable() {
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
          KEY `programmer_id` (`programmer_id`),
          CONSTRAINT `programmer_replies_ibfk_1` FOREIGN KEY (`admin_message_id`) REFERENCES `admin_messages` (`id`) ON DELETE CASCADE,
          CONSTRAINT `programmer_replies_ibfk_2` FOREIGN KEY (`programmer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
        ";
        
        try {
            $this->pdo->exec($sql);
            $this->log("✓ Tabel 'programmer_replies' berhasil dibuat");
        } catch (PDOException $e) {
            $this->log("✗ Error membuat tabel 'programmer_replies': " . $e->getMessage());
        }
    }
    
    private function createTiketSupportTable() {
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
          KEY `teknisi_id` (`teknisi_id`),
          CONSTRAINT `tiket_support_ibfk_1` FOREIGN KEY (`desa_id`) REFERENCES `desa` (`id`),
          CONSTRAINT `tiket_support_ibfk_2` FOREIGN KEY (`teknisi_id`) REFERENCES `users` (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        
        try {
            $this->pdo->exec($sql);
            $this->log("✓ Tabel 'tiket_support' berhasil dibuat");
        } catch (PDOException $e) {
            $this->log("✗ Error membuat tabel 'tiket_support': " . $e->getMessage());
        }
    }
    
    private function createSaldoBankTable() {
        $sql = "
        CREATE TABLE IF NOT EXISTS `saldo_bank` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `bank_id` int(11) NOT NULL,
          `saldo` decimal(15,2) NOT NULL DEFAULT 0.00,
          `tanggal_update` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
          `keterangan` text DEFAULT NULL,
          PRIMARY KEY (`id`),
          KEY `bank_id` (`bank_id`),
          CONSTRAINT `saldo_bank_ibfk_1` FOREIGN KEY (`bank_id`) REFERENCES `bank` (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        
        try {
            $this->pdo->exec($sql);
            $this->log("✓ Tabel 'saldo_bank' berhasil dibuat");
        } catch (PDOException $e) {
            $this->log("✗ Error membuat tabel 'saldo_bank': " . $e->getMessage());
        }
    }
    
    private function createStockOpnameTable() {
        $sql = "
        CREATE TABLE IF NOT EXISTS `stock_opname` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `produk_id` int(11) NOT NULL,
          `user_id` int(11) NOT NULL,
          `stok_sistem` int(11) NOT NULL,
          `stok_fisik` int(11) NOT NULL,
          `selisih` int(11) GENERATED ALWAYS AS (`stok_fisik` - `stok_sistem`) STORED,
          `keterangan` text DEFAULT NULL,
          `tanggal_opname` timestamp NOT NULL DEFAULT current_timestamp(),
          PRIMARY KEY (`id`),
          KEY `produk_id` (`produk_id`),
          KEY `user_id` (`user_id`),
          CONSTRAINT `fk_stock_opname_produk` FOREIGN KEY (`produk_id`) REFERENCES `produk` (`id`) ON DELETE CASCADE,
          CONSTRAINT `fk_stock_opname_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        
        try {
            $this->pdo->exec($sql);
            $this->log("✓ Tabel 'stock_opname' berhasil dibuat");
        } catch (PDOException $e) {
            $this->log("✗ Error membuat tabel 'stock_opname': " . $e->getMessage());
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
        
        $this->log("✓ Total tabel di database: " . count($tables));
    }
}

// Main execution
if (php_sapi_name() === 'cli') {
    echo "=== Missing Tables Creator ===\n\n";
    
    $creator = new MissingTablesCreator();
    
    echo "Akan membuat tabel yang hilang dan data dasar...\n\n";
    
    // Create missing tables
    $creator->createMissingTables();
    
    // Verify tables
    $creator->verifyTables();
    
    echo "\n✓ Proses selesai! Database siap digunakan.\n";
    echo "\nCredentials default:\n";
    echo "- Admin: username=admin, password=admin123\n";
    echo "- Teknisi: username=teknisi, password=teknisi123\n";
    echo "- Finance: username=finance, password=finance123\n";
    
} else {
    // Web interface
    echo "<h2>Missing Tables Creator</h2>";
    echo "<p>Jalankan script ini melalui command line:</p>";
    echo "<code>php create_missing_tables.php</code>";
    
    if (isset($_GET['create'])) {
        $creator = new MissingTablesCreator();
        $creator->createMissingTables();
        $creator->verifyTables();
        echo "<p style='color: green'>✓ Tabel yang hilang berhasil dibuat!</p>";
    } else {
        echo "<p><a href='?create=1'>Buat Tabel yang Hilang</a></p>";
    }
}
?>