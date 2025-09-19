<?php
// Script untuk mengupdate data perangkat desa Panarusanwetan

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

echo "\n=== UPDATE DATA PANARUSANWETAN ===\n";

// Data dari user
$kepalaDesa = 'AGUS TIYONO';
$sekretarisDesa = 'TEGUH DHANI PRISTIWANTO';
$noHpSekdes = '082225699985';

// Update data desa Panarusanwetan
$stmt = $pdo->prepare("
    UPDATE desa 
    SET nama_kepala_desa = ?, 
        nama_sekdes = ?, 
        no_hp_sekdes = ?
    WHERE nama_desa = 'Panarusanwetan' AND kecamatan = 'Susukan'
");

$result = $stmt->execute([$kepalaDesa, $sekretarisDesa, $noHpSekdes]);

if ($result && $stmt->rowCount() > 0) {
    echo "✓ Berhasil update data Panarusanwetan:\n";
    echo "  - Kepala Desa: $kepalaDesa\n";
    echo "  - Sekretaris Desa: $sekretarisDesa\n";
    echo "  - No HP Sekdes: $noHpSekdes\n";
} else {
    echo "✗ Gagal update atau data tidak berubah\n";
}

// Verifikasi hasil update
echo "\n=== VERIFIKASI HASIL ===\n";
$stmt = $pdo->prepare("
    SELECT nama_desa, nama_kepala_desa, nama_sekdes, no_hp_sekdes 
    FROM desa 
    WHERE nama_desa = 'Panarusanwetan' AND kecamatan = 'Susukan'
");
$stmt->execute();
$result = $stmt->fetch(PDO::FETCH_ASSOC);

if ($result) {
    echo "Data Panarusanwetan setelah update:\n";
    echo "- Kepala Desa: " . ($result['nama_kepala_desa'] ?: 'NULL') . "\n";
    echo "- Sekretaris Desa: " . ($result['nama_sekdes'] ?: 'NULL') . "\n";
    echo "- No HP Sekdes: " . ($result['no_hp_sekdes'] ?: 'NULL') . "\n";
    
    // Cek kelengkapan
    if (!empty($result['nama_kepala_desa']) && !empty($result['nama_sekdes']) && !empty($result['no_hp_sekdes'])) {
        echo "\n✓ Status: KONTAK LENGKAP\n";
    } else {
        echo "\n⚠ Status: MASIH PERLU DATA\n";
    }
} else {
    echo "Desa tidak ditemukan\n";
}

echo "\nSelesai!\n";
?>