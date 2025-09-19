<?php
// Script ringkasan distribusi desa per kecamatan setelah update

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

echo "\n=== RINGKASAN DISTRIBUSI DESA PER KECAMATAN ===\n";

// Ambil distribusi desa per kecamatan
$stmt = $pdo->prepare("
    SELECT 
        kecamatan, 
        COUNT(*) as jumlah_desa,
        GROUP_CONCAT(nama_desa ORDER BY nama_desa SEPARATOR ', ') as daftar_desa
    FROM desa 
    WHERE kecamatan != '' AND kecamatan IS NOT NULL 
    GROUP BY kecamatan 
    ORDER BY kecamatan
");
$stmt->execute();
$distribusi = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Total kecamatan: " . count($distribusi) . "\n\n";

foreach ($distribusi as $kec) {
    echo "KECAMATAN {$kec['kecamatan']} ({$kec['jumlah_desa']} desa):\n";
    
    // Bagi daftar desa menjadi array
    $desaList = explode(', ', $kec['daftar_desa']);
    
    // Tampilkan dalam format kolom
    $counter = 0;
    foreach ($desaList as $desa) {
        echo sprintf("  %-25s", $desa);
        $counter++;
        if ($counter % 3 == 0) {
            echo "\n";
        }
    }
    if ($counter % 3 != 0) {
        echo "\n";
    }
    echo "\n";
}

// Statistik umum
echo "=== STATISTIK UMUM ===\n";

$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM desa");
$stmt->execute();
$totalDesa = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM desa WHERE kecamatan != '' AND kecamatan IS NOT NULL");
$stmt->execute();
$desaDenganKecamatan = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM desa WHERE kecamatan = '' OR kecamatan IS NULL");
$stmt->execute();
$desaTanpaKecamatan = $stmt->fetch(PDO::FETCH_ASSOC);

echo "Total desa: {$totalDesa['total']}\n";
echo "Desa dengan kecamatan: {$desaDenganKecamatan['total']}\n";
echo "Desa tanpa kecamatan: {$desaTanpaKecamatan['total']}\n";
echo "Persentase kelengkapan: " . round(($desaDenganKecamatan['total'] / $totalDesa['total']) * 100, 2) . "%\n";

// Kecamatan dengan jumlah desa terbanyak dan tersedikit
echo "\n=== RANKING KECAMATAN ===\n";

$stmt = $pdo->prepare("
    SELECT kecamatan, COUNT(*) as jumlah_desa 
    FROM desa 
    WHERE kecamatan != '' AND kecamatan IS NOT NULL 
    GROUP BY kecamatan 
    ORDER BY jumlah_desa DESC, kecamatan
");
$stmt->execute();
$ranking = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Kecamatan dengan desa terbanyak:\n";
for ($i = 0; $i < min(5, count($ranking)); $i++) {
    $rank = $ranking[$i];
    echo "  " . ($i + 1) . ". {$rank['kecamatan']} ({$rank['jumlah_desa']} desa)\n";
}

echo "\nKecamatan dengan desa tersedikit:\n";
for ($i = count($ranking) - 1; $i >= max(0, count($ranking) - 5); $i--) {
    $rank = $ranking[$i];
    $position = count($ranking) - $i;
    echo "  $position. {$rank['kecamatan']} ({$rank['jumlah_desa']} desa)\n";
}

echo "\n=== VERIFIKASI DATA KONTAK ===\n";

// Cek status kontak person
$stmt = $pdo->prepare("
    SELECT 
        kecamatan,
        COUNT(*) as total_desa,
        SUM(CASE WHEN nama_kepala_desa != '' AND nama_kepala_desa IS NOT NULL THEN 1 ELSE 0 END) as ada_kepala,
        SUM(CASE WHEN nama_sekdes != '' AND nama_sekdes IS NOT NULL THEN 1 ELSE 0 END) as ada_sekretaris,
        SUM(CASE WHEN no_hp_sekdes != '' AND no_hp_sekdes IS NOT NULL THEN 1 ELSE 0 END) as ada_kontak
    FROM desa 
    WHERE kecamatan != '' AND kecamatan IS NOT NULL 
    GROUP BY kecamatan 
    ORDER BY kecamatan
");
$stmt->execute();
$kontakStatus = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Status kontak person per kecamatan:\n";
echo sprintf("%-20s %5s %7s %10s %7s\n", "Kecamatan", "Total", "Kepala", "Sekretaris", "Kontak");
echo str_repeat("-", 60) . "\n";

foreach ($kontakStatus as $status) {
    echo sprintf("%-20s %5d %7d %10d %7d\n", 
        $status['kecamatan'], 
        $status['total_desa'], 
        $status['ada_kepala'], 
        $status['ada_sekretaris'], 
        $status['ada_kontak']
    );
}

echo "\nSelesai!\n";
?>