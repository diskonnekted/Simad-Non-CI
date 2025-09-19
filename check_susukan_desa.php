<?php
// Script untuk memeriksa desa yang ada di Kecamatan Susukan dalam database

$host = 'localhost';
$username = 'root';
$password = '';
$database = 'simadorbitdev_simad';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$database;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "Koneksi database berhasil\n";
} catch(PDOException $e) {
    die("Koneksi database gagal: " . $e->getMessage());
}

echo "\n=== DESA DI KECAMATAN SUSUKAN DALAM DATABASE ===\n";

$stmt = $pdo->prepare("SELECT nama_desa FROM desa WHERE kecamatan = 'Susukan' ORDER BY nama_desa");
$stmt->execute();
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($results as $row) {
    echo "- " . $row['nama_desa'] . "\n";
}

echo "\nTotal desa dalam database: " . count($results) . "\n";

// Daftar desa yang seharusnya ada menurut user
$desaSeharusnya = [
    'Piasa Wetan',
    'Pakikiran', 
    'Brengkok',
    'Panarusankulon',
    'Panarusanwetan',
    'Gumelem Kulon',
    'Gumelem Wetan',
    'Derik',
    'Berta',
    'Karangjati',
    'Kedawung',
    'Dermasari',
    'Susukan',
    'Kemranggon',
    'Karangsalam'
];

echo "\n=== DESA YANG SEHARUSNYA ADA (15 DESA) ===\n";
foreach ($desaSeharusnya as $desa) {
    echo "- $desa\n";
}

echo "\n=== ANALISIS ===\n";
$desaDalam = array_column($results, 'nama_desa');
$desaTidakAda = [];

foreach ($desaSeharusnya as $desa) {
    if (!in_array($desa, $desaDalam)) {
        $desaTidakAda[] = $desa;
    }
}

if (count($desaTidakAda) > 0) {
    echo "Desa yang TIDAK ADA dalam database (" . count($desaTidakAda) . " desa):\n";
    foreach ($desaTidakAda as $desa) {
        echo "- $desa\n";
    }
} else {
    echo "Semua desa sudah ada dalam database.\n";
}

echo "\nSelesai!\n";
?>