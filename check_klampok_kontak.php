<?php
require_once 'config/database.php';

$db = new Database();
$conn = $db->getConnection();

echo "Status kontak person desa di Kecamatan Klampok:\n";
echo "===============================================\n";

$stmt = $conn->prepare("
    SELECT nama_desa, nama_kepala_desa, nama_sekdes, no_hp_sekdes 
    FROM desa 
    WHERE kecamatan = 'klampok' 
    ORDER BY nama_desa
");
$stmt->execute();
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($results as $row) {
    $kepala = $row['nama_kepala_desa'] ?: 'NULL';
    $sekdes = $row['nama_sekdes'] ?: 'NULL';
    $noHp = $row['no_hp_sekdes'] ?: 'NULL';
    
    echo "{$row['nama_desa']}:\n";
    echo "  - Kepala Desa: $kepala\n";
    echo "  - Sekretaris Desa: $sekdes\n";
    echo "  - No HP Sekdes: $noHp\n";
    echo "\n";
}

echo "Total desa: " . count($results) . "\n";

// Hitung desa yang masih memerlukan kontak person
$desaPerluKontak = 0;
foreach ($results as $row) {
    if (empty($row['nama_kepala_desa']) || empty($row['nama_sekdes']) || empty($row['no_hp_sekdes'])) {
        $desaPerluKontak++;
    }
}

echo "\nDesa yang masih memerlukan kontak person: $desaPerluKontak\n";
echo "Desa dengan kontak person lengkap: " . (count($results) - $desaPerluKontak) . "\n";
?>