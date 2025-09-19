<?php
require_once 'config/database.php';

$db = new Database();
$conn = $db->getConnection();

echo "Daftar desa di Kecamatan Wanayasa:\n";

$stmt = $conn->prepare("SELECT nama_desa FROM desa WHERE LOWER(kecamatan) = 'wanayasa' ORDER BY nama_desa");
$stmt->execute();
$desas = $stmt->fetchAll(PDO::FETCH_COLUMN);

foreach ($desas as $desa) {
    echo "- $desa\n";
}

echo "\nTotal: " . count($desas) . " desa\n";

// Cek juga apakah ada desa dengan nama mirip Susukan
echo "\nMencari desa dengan nama mirip 'Susukan':\n";
$stmt = $conn->prepare("SELECT nama_desa, kecamatan FROM desa WHERE nama_desa LIKE '%susukan%' OR nama_desa LIKE '%Susukan%'");
$stmt->execute();
$similarDesas = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($similarDesas)) {
    echo "Tidak ada desa dengan nama mirip 'Susukan'\n";
} else {
    foreach ($similarDesas as $desa) {
        echo "- {$desa['nama_desa']} (Kecamatan: {$desa['kecamatan']})\n";
    }
}
?>