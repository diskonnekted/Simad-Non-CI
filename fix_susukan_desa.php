<?php
// Script untuk memperbaiki desa-desa yang seharusnya di Kecamatan Susukan

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

echo "\n=== MEMPERBAIKI DESA-DESA KECAMATAN SUSUKAN ===\n";

// Daftar desa yang seharusnya di Kecamatan Susukan
$desaSusukan = [
    'piasawetan' => 'Piasa Wetan',
    'pakikiran' => 'Pakikiran', 
    'panarusankulon' => 'Panarusankulon',
    'panarusanwetan' => 'Panarusanwetan',
    'gumelemkulon' => 'Gumelem Kulon',
    'gumelemwetan' => 'Gumelem Wetan',
    'karangjati' => 'Karangjati',
    'kedawung' => 'Kedawung',
    'susukan' => 'Susukan',
    'kemranggon' => 'Kemranggon',
    'Karangsalam' => 'Karangsalam'
];

$updated = 0;
$notFound = 0;

foreach ($desaSusukan as $namaLama => $namaBaru) {
    // Cek apakah desa ada dengan nama lama
    $stmt = $pdo->prepare("SELECT id, nama_desa, kecamatan FROM desa WHERE nama_desa = ? OR nama_desa = ?");
    $stmt->execute([$namaLama, $namaBaru]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        echo "Ditemukan: {$result['nama_desa']} (kecamatan: '{$result['kecamatan']}')\n";
        
        // Update kecamatan dan nama desa jika perlu
        $updateStmt = $pdo->prepare("UPDATE desa SET kecamatan = 'Susukan', nama_desa = ? WHERE id = ?");
        $updateStmt->execute([$namaBaru, $result['id']]);
        
        echo "✓ Updated: {$result['nama_desa']} -> $namaBaru (Kecamatan: Susukan)\n";
        $updated++;
    } else {
        echo "✗ Tidak ditemukan: $namaLama / $namaBaru\n";
        $notFound++;
    }
}

echo "\n=== HASIL UPDATE ===\n";
echo "Desa berhasil diupdate: $updated\n";
echo "Desa tidak ditemukan: $notFound\n";

// Cek hasil akhir
echo "\n=== DESA DI KECAMATAN SUSUKAN SETELAH UPDATE ===\n";
$stmt = $pdo->prepare("SELECT nama_desa FROM desa WHERE kecamatan = 'Susukan' ORDER BY nama_desa");
$stmt->execute();
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($results as $row) {
    echo "- " . $row['nama_desa'] . "\n";
}

echo "\nTotal desa di Kecamatan Susukan: " . count($results) . "\n";
echo "\nSelesai!\n";
?>