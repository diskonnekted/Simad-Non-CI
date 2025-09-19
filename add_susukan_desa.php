<?php
require_once 'config/database.php';

$db = new Database();
$conn = $db->getConnection();

try {
    // Cek apakah desa Susukan sudah ada
    $stmt = $conn->prepare("SELECT id FROM desa WHERE LOWER(nama_desa) = 'susukan' AND LOWER(kecamatan) = 'wanayasa'");
    $stmt->execute();
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing) {
        echo "Desa Susukan di Kecamatan Wanayasa sudah ada dengan ID: {$existing['id']}\n";
    } else {
        // Tambahkan desa Susukan
        $stmt = $conn->prepare("
            INSERT INTO desa (nama_desa, kecamatan, status) 
            VALUES ('Susukan', 'Wanayasa', 'aktif')
        ");
        $stmt->execute();
        
        $newId = $conn->lastInsertId();
        echo "Desa Susukan berhasil ditambahkan ke Kecamatan Wanayasa dengan ID: $newId\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\nSelesai!\n";
?>