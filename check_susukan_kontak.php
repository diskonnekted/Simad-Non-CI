<?php
// Script untuk memeriksa status kontak person di Kecamatan Susukan

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

echo "\n=== STATUS KONTAK PERSON KECAMATAN SUSUKAN ===\n";

$stmt = $pdo->prepare("
    SELECT 
        nama_desa,
        nama_kepala_desa,
        nama_sekdes,
        no_hp_sekdes
    FROM desa 
    WHERE kecamatan = 'Susukan' 
    ORDER BY nama_desa
");

$stmt->execute();
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

$kontakLengkap = 0;
$perluData = 0;

foreach ($results as $desa) {
    $status = [];
    
    if (empty($desa['nama_kepala_desa'])) {
        $status[] = 'Kepala Desa: NULL';
    } else {
        $status[] = 'Kepala Desa: ' . $desa['nama_kepala_desa'];
    }
    
    if (empty($desa['nama_sekdes'])) {
        $status[] = 'Sekretaris Desa: NULL';
    } else {
        $status[] = 'Sekretaris Desa: ' . $desa['nama_sekdes'];
    }
    
    if (empty($desa['no_hp_sekdes'])) {
        $status[] = 'No HP Sekdes: NULL';
    } else {
        $status[] = 'No HP Sekdes: ' . $desa['no_hp_sekdes'];
    }
    
    // Hitung status
    if (!empty($desa['nama_kepala_desa']) && !empty($desa['nama_sekdes']) && !empty($desa['no_hp_sekdes'])) {
        $kontakLengkap++;
        echo "✓ {$desa['nama_desa']}: " . implode(', ', $status) . "\n";
    } else {
        $perluData++;
        echo "⚠ {$desa['nama_desa']}: " . implode(', ', $status) . "\n";
    }
}

echo "\n=== RINGKASAN ===\n";
echo "Total desa di Kecamatan Susukan: " . count($results) . "\n";
echo "Desa dengan kontak lengkap: $kontakLengkap\n";
echo "Desa yang perlu data: $perluData\n";

echo "\nSelesai!\n";
?>