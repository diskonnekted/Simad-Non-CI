<?php
require_once 'config/database.php';

$db = new Database();
$conn = $db->getConnection();

echo "Status kontak person desa di Kecamatan Wanayasa:\n";
echo "================================================\n";

$stmt = $conn->prepare("
    SELECT nama_desa, nama_kepala_desa, nama_sekdes, no_hp_sekdes 
    FROM desa 
    WHERE kecamatan = 'wanayasa' 
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
?>