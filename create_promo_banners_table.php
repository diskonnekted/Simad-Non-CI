<?php
require_once 'config/database.php';

/**
 * Script untuk membuat tabel promo_banners yang hilang
 */

echo "=== MEMBUAT TABEL PROMO_BANNERS ===\n";
echo "Tanggal: " . date('Y-m-d H:i:s') . "\n\n";

// Inisialisasi koneksi database
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
echo "✓ Koneksi database berhasil\n\n";

// Cek apakah tabel sudah ada
$check_table = "SHOW TABLES LIKE 'promo_banners'";
$result = $conn->query($check_table);

if ($result->num_rows > 0) {
    echo "⚠️ Tabel promo_banners sudah ada\n";
} else {
    echo "📝 Membuat tabel promo_banners...\n";
    
    // SQL untuk membuat tabel promo_banners
    $create_table_sql = "
    CREATE TABLE promo_banners (
        id INT PRIMARY KEY AUTO_INCREMENT,
        judul VARCHAR(100) NOT NULL,
        deskripsi TEXT,
        gambar VARCHAR(255) NOT NULL,
        posisi ENUM('1', '2') NOT NULL COMMENT 'Posisi card promo (1 atau 2)',
        status ENUM('aktif', 'nonaktif') DEFAULT 'aktif',
        tanggal_mulai DATE NULL,
        tanggal_berakhir DATE NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    if ($conn->query($create_table_sql) === TRUE) {
        echo "✓ Tabel promo_banners berhasil dibuat\n";
        
        // Insert data default
        echo "📝 Menambahkan data default...\n";
        $insert_data_sql = "
        INSERT INTO promo_banners (judul, deskripsi, gambar, posisi, status) VALUES
        ('Promo Spesial', 'Dapatkan penawaran terbaik untuk produk pilihan', 'default-promo-1.jpg', '1', 'aktif'),
        ('Penawaran Khusus', 'Hemat lebih banyak hari ini dengan diskon menarik', 'default-promo-2.jpg', '2', 'aktif')
        ";
        
        if ($conn->query($insert_data_sql) === TRUE) {
            echo "✓ Data default berhasil ditambahkan\n";
        } else {
            echo "❌ Error menambahkan data default: " . $conn->error . "\n";
        }
        
    } else {
        echo "❌ Error membuat tabel: " . $conn->error . "\n";
    }
}

// Verifikasi tabel
echo "\n=== VERIFIKASI TABEL ===\n";
$verify_sql = "SELECT COUNT(*) as total FROM promo_banners";
$result = $conn->query($verify_sql);
if ($result) {
    $row = $result->fetch_assoc();
    echo "✓ Tabel promo_banners tersedia dengan {$row['total']} record\n";
} else {
    echo "❌ Error verifikasi: " . $conn->error . "\n";
}

$conn->close();
echo "\n=== SELESAI ===\n";
?>