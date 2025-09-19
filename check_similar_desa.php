<?php
// Script untuk mencari desa dengan nama serupa di database

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

echo "\n=== MENCARI DESA DENGAN NAMA SERUPA ===\n";

$stmt = $pdo->prepare("
    SELECT nama_desa, kecamatan 
    FROM desa 
    WHERE nama_desa LIKE '%Piasa%' 
       OR nama_desa LIKE '%Pakikiran%' 
       OR nama_desa LIKE '%Panarusan%' 
       OR nama_desa LIKE '%Gumelem%' 
       OR nama_desa LIKE '%Karangjati%' 
       OR nama_desa LIKE '%Kedawung%' 
       OR nama_desa LIKE '%Susukan%' 
       OR nama_desa LIKE '%Kemranggon%' 
       OR nama_desa LIKE '%Karangsalam%'
    ORDER BY nama_desa
");

$stmt->execute();
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($results) > 0) {
    echo "Desa dengan nama serupa ditemukan:\n";
    foreach ($results as $row) {
        echo "- " . $row['nama_desa'] . " (" . $row['kecamatan'] . ")\n";
    }
} else {
    echo "Tidak ada desa dengan nama serupa ditemukan.\n";
}

echo "\n=== MENCARI SEMUA DESA DI KECAMATAN LAIN YANG MUNGKIN SUSUKAN ===\n";

$stmt2 = $pdo->prepare("
    SELECT nama_desa, kecamatan 
    FROM desa 
    WHERE kecamatan LIKE '%Susukan%'
    ORDER BY kecamatan, nama_desa
");

$stmt2->execute();
$results2 = $stmt2->fetchAll(PDO::FETCH_ASSOC);

if (count($results2) > 0) {
    echo "Desa di kecamatan dengan nama mengandung 'Susukan':\n";
    foreach ($results2 as $row) {
        echo "- " . $row['nama_desa'] . " (" . $row['kecamatan'] . ")\n";
    }
} else {
    echo "Tidak ada kecamatan lain dengan nama mengandung 'Susukan'.\n";
}

echo "\n=== TOTAL DESA PER KECAMATAN ===\n";

$stmt3 = $pdo->prepare("
    SELECT kecamatan, COUNT(*) as jumlah_desa 
    FROM desa 
    GROUP BY kecamatan 
    ORDER BY kecamatan
");

$stmt3->execute();
$results3 = $stmt3->fetchAll(PDO::FETCH_ASSOC);

foreach ($results3 as $row) {
    echo "- " . $row['kecamatan'] . ": " . $row['jumlah_desa'] . " desa\n";
}

echo "\nSelesai!\n";
?>