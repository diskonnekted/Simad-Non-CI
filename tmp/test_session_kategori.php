<?php
require_once 'config/database.php';

// Mulai session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

echo "<h3>Test Session dan Kategori</h3>";

// Cek session
if (!isset($_SESSION['desa_id'])) {
    echo "<p style='color: red;'>Session desa_id tidak ada. Setting manual untuk test...</p>";
    $_SESSION['desa_id'] = 1; // Set manual untuk testing
} else {
    echo "<p style='color: green;'>Session desa_id ada: " . $_SESSION['desa_id'] . "</p>";
}

try {
    $pdo = getDBConnection();
    echo "<p style='color: green;'>Koneksi database: BERHASIL</p>";
    
    // Test query kategori seperti di promo.php
    $kategori_stmt = $pdo->prepare("
        SELECT DISTINCT k.nama_kategori 
        FROM kategori_produk k 
        INNER JOIN produk p ON k.id = p.kategori_id 
        WHERE p.status = 'aktif'
        ORDER BY k.nama_kategori
    ");
    $kategori_stmt->execute();
    $kategori_list = $kategori_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h4>Hasil Query Kategori (INNER JOIN):</h4>";
    echo "<p>Jumlah kategori: " . count($kategori_list) . "</p>";
    if (count($kategori_list) > 0) {
        echo "<ul>";
        foreach($kategori_list as $kat) {
            echo "<li>" . htmlspecialchars($kat['nama_kategori']) . "</li>";
        }
        echo "</ul>";
    } else {
        echo "<p style='color: red;'>Tidak ada kategori yang ditemukan!</p>";
    }
    
    // Test query semua kategori
    $all_stmt = $pdo->prepare("SELECT nama_kategori FROM kategori_produk ORDER BY nama_kategori");
    $all_stmt->execute();
    $all_kategori = $all_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h4>Semua Kategori di Database:</h4>";
    echo "<p>Jumlah total kategori: " . count($all_kategori) . "</p>";
    if (count($all_kategori) > 0) {
        echo "<ul>";
        foreach($all_kategori as $kat) {
            echo "<li>" . htmlspecialchars($kat['nama_kategori']) . "</li>";
        }
        echo "</ul>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>ERROR: " . $e->getMessage() . "</p>";
}
?>