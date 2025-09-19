<?php
// Script untuk memeriksa data desa Kandangwangi ID 157

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

echo "\n=== DATA DESA KANDANGWANGI (ID: 157) ===\n";

// Ambil data desa dengan ID 157
$stmt = $pdo->prepare("SELECT * FROM desa WHERE id = 157");
$stmt->execute();
$desa = $stmt->fetch(PDO::FETCH_ASSOC);

if ($desa) {
    echo "Nama Desa: {$desa['nama_desa']}\n";
    echo "Kecamatan: {$desa['kecamatan']}\n";
    echo "Kepala Desa: " . ($desa['nama_kepala_desa'] ?: 'KOSONG') . "\n";
    echo "No HP Kepala: " . ($desa['no_hp_kepala_desa'] ?: 'KOSONG') . "\n";
    echo "Sekretaris Desa: " . ($desa['nama_sekdes'] ?: 'KOSONG') . "\n";
    echo "No HP Sekdes: " . ($desa['no_hp_sekdes'] ?: 'KOSONG') . "\n";
    
    echo "\n=== ANALISIS MASALAH ===\n";
    
    $masalah = [];
    
    if (empty($desa['nama_kepala_desa'])) {
        $masalah[] = "Nama kepala desa kosong";
    }
    
    if (empty($desa['no_hp_kepala_desa'])) {
        $masalah[] = "No HP kepala desa kosong";
    }
    
    if (empty($desa['nama_sekdes'])) {
        $masalah[] = "Nama sekretaris desa kosong";
    }
    
    if (empty($desa['no_hp_sekdes'])) {
        $masalah[] = "No HP sekretaris desa kosong";
    }
    
    // Cek format nomor HP
    if (!empty($desa['no_hp_kepala_desa'])) {
        $hp_kepala = $desa['no_hp_kepala_desa'];
        if (!preg_match('/^[0-9+\-\s()]+$/', $hp_kepala)) {
            $masalah[] = "Format no HP kepala desa tidak valid: $hp_kepala";
        }
    }
    
    if (!empty($desa['no_hp_sekdes'])) {
        $hp_sekdes = $desa['no_hp_sekdes'];
        if (!preg_match('/^[0-9+\-\s()]+$/', $hp_sekdes)) {
            $masalah[] = "Format no HP sekretaris desa tidak valid: $hp_sekdes";
        }
    }
    
    if (count($masalah) > 0) {
        echo "Masalah yang ditemukan:\n";
        foreach ($masalah as $i => $m) {
            echo "  " . ($i + 1) . ". $m\n";
        }
    } else {
        echo "Tidak ada masalah ditemukan\n";
    }
    
    echo "\n=== REKOMENDASI PERBAIKAN ===\n";
    
    if (empty($desa['nama_kepala_desa']) || empty($desa['no_hp_kepala_desa'])) {
        echo "1. Perlu melengkapi data kepala desa\n";
    }
    
    if (empty($desa['nama_sekdes']) || empty($desa['no_hp_sekdes'])) {
        echo "2. Perlu melengkapi data sekretaris desa\n";
    }
    
    if (!empty($desa['no_hp_sekdes']) && !preg_match('/^[0-9+\-\s()]+$/', $desa['no_hp_sekdes'])) {
        echo "3. Perlu memperbaiki format no HP sekretaris desa\n";
    }
    
} else {
    echo "Desa dengan ID 157 tidak ditemukan\n";
}

echo "\nSelesai!\n";
?>