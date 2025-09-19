<?php
// Script untuk memindahkan 3 desa yang tersisa ke Kecamatan Susukan

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

echo "\n=== MEMINDAHKAN 3 DESA TERSISA KE KECAMATAN SUSUKAN ===\n";

// Mapping desa yang ditemukan
$desaMapping = [
    'pekikiran' => 'Pakikiran',
    'Panerusankulon' => 'Panarusankulon', 
    'panerusanwetan' => 'Panarusanwetan'
];

$updated = 0;

foreach ($desaMapping as $namaLama => $namaBaru) {
    // Cek apakah desa ada
    $stmt = $pdo->prepare("SELECT id, nama_desa, kecamatan FROM desa WHERE nama_desa = ?");
    $stmt->execute([$namaLama]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        echo "Ditemukan: {$result['nama_desa']} (kecamatan: '{$result['kecamatan']}')\n";
        
        // Update kecamatan dan nama desa
        $updateStmt = $pdo->prepare("UPDATE desa SET kecamatan = 'Susukan', nama_desa = ? WHERE id = ?");
        $updateStmt->execute([$namaBaru, $result['id']]);
        
        echo "✓ Updated: {$result['nama_desa']} -> $namaBaru (Kecamatan: Susukan)\n";
        $updated++;
    } else {
        echo "✗ Tidak ditemukan: $namaLama\n";
    }
}

echo "\n=== HASIL UPDATE ===\n";
echo "Desa berhasil diupdate: $updated\n";

// Cek hasil akhir
echo "\n=== DESA DI KECAMATAN SUSUKAN SETELAH UPDATE FINAL ===\n";
$stmt = $pdo->prepare("SELECT nama_desa FROM desa WHERE kecamatan = 'Susukan' ORDER BY nama_desa");
$stmt->execute();
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($results as $row) {
    echo "- " . $row['nama_desa'] . "\n";
}

echo "\nTotal desa di Kecamatan Susukan: " . count($results) . "\n";

// Bandingkan dengan daftar yang seharusnya ada
$desaSeharusnya = [
    'Piasa Wetan', 'Pakikiran', 'Brengkok', 'Panarusankulon', 'Panarusanwetan',
    'Gumelem Kulon', 'Gumelem Wetan', 'Derik', 'Berta', 'Karangjati', 
    'Kedawung', 'Dermasari', 'Susukan', 'Kemranggon', 'Karangsalam'
];

echo "\n=== VERIFIKASI KELENGKAPAN ===\n";
echo "Desa yang seharusnya ada: " . count($desaSeharusnya) . "\n";
echo "Desa yang ada di database: " . count($results) . "\n";

$desaDalam = array_column($results, 'nama_desa');
$desaHilang = [];

foreach ($desaSeharusnya as $desa) {
    if (!in_array($desa, $desaDalam)) {
        $desaHilang[] = $desa;
    }
}

if (count($desaHilang) > 0) {
    echo "\nDesa yang masih hilang:\n";
    foreach ($desaHilang as $desa) {
        echo "- $desa\n";
    }
} else {
    echo "\n✓ Semua 15 desa sudah lengkap!\n";
}

echo "\nSelesai!\n";
?>