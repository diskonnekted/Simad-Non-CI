<?php
// Script untuk menganalisis kontak person yang masih kosong

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

echo "\n=== ANALISIS KONTAK PERSON DESA ===\n";

// Desa tanpa kepala desa
echo "\n1. DESA TANPA NAMA KEPALA DESA:\n";
$stmt = $pdo->prepare("
    SELECT nama_desa, kecamatan 
    FROM desa 
    WHERE (nama_kepala_desa = '' OR nama_kepala_desa IS NULL) 
    AND kecamatan != '' AND kecamatan IS NOT NULL
    ORDER BY kecamatan, nama_desa
");
$stmt->execute();
$desaTanpaKepala = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($desaTanpaKepala) > 0) {
    $currentKecamatan = '';
    foreach ($desaTanpaKepala as $desa) {
        if ($currentKecamatan != $desa['kecamatan']) {
            $currentKecamatan = $desa['kecamatan'];
            echo "\n  Kecamatan {$currentKecamatan}:\n";
        }
        echo "    - {$desa['nama_desa']}\n";
    }
} else {
    echo "  Semua desa sudah memiliki nama kepala desa\n";
}

// Desa tanpa sekretaris desa
echo "\n2. DESA TANPA NAMA SEKRETARIS DESA:\n";
$stmt = $pdo->prepare("
    SELECT nama_desa, kecamatan 
    FROM desa 
    WHERE (nama_sekdes = '' OR nama_sekdes IS NULL) 
    AND kecamatan != '' AND kecamatan IS NOT NULL
    ORDER BY kecamatan, nama_desa
");
$stmt->execute();
$desaTanpaSekretaris = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($desaTanpaSekretaris) > 0) {
    $currentKecamatan = '';
    foreach ($desaTanpaSekretaris as $desa) {
        if ($currentKecamatan != $desa['kecamatan']) {
            $currentKecamatan = $desa['kecamatan'];
            echo "\n  Kecamatan {$currentKecamatan}:\n";
        }
        echo "    - {$desa['nama_desa']}\n";
    }
} else {
    echo "  Semua desa sudah memiliki nama sekretaris desa\n";
}

// Desa tanpa no HP kepala desa
echo "\n3. DESA TANPA NO HP KEPALA DESA:\n";
$stmt = $pdo->prepare("
    SELECT nama_desa, kecamatan, nama_kepala_desa 
    FROM desa 
    WHERE (no_hp_kepala_desa = '' OR no_hp_kepala_desa IS NULL) 
    AND kecamatan != '' AND kecamatan IS NOT NULL
    ORDER BY kecamatan, nama_desa
");
$stmt->execute();
$desaTanpaHpKepala = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($desaTanpaHpKepala) > 0) {
    $currentKecamatan = '';
    foreach ($desaTanpaHpKepala as $desa) {
        if ($currentKecamatan != $desa['kecamatan']) {
            $currentKecamatan = $desa['kecamatan'];
            echo "\n  Kecamatan {$currentKecamatan}:\n";
        }
        echo "    - {$desa['nama_desa']} (Kepala: {$desa['nama_kepala_desa']})\n";
    }
} else {
    echo "  Semua desa sudah memiliki no HP kepala desa\n";
}

// Desa tanpa no HP sekdes
echo "\n4. DESA TANPA NO HP SEKRETARIS DESA:\n";
$stmt = $pdo->prepare("
    SELECT nama_desa, kecamatan, nama_sekdes 
    FROM desa 
    WHERE (no_hp_sekdes = '' OR no_hp_sekdes IS NULL) 
    AND kecamatan != '' AND kecamatan IS NOT NULL
    ORDER BY kecamatan, nama_desa
");
$stmt->execute();
$desaTanpaHpSekdes = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($desaTanpaHpSekdes) > 0) {
    $currentKecamatan = '';
    foreach ($desaTanpaHpSekdes as $desa) {
        if ($currentKecamatan != $desa['kecamatan']) {
            $currentKecamatan = $desa['kecamatan'];
            echo "\n  Kecamatan {$currentKecamatan}:\n";
        }
        echo "    - {$desa['nama_desa']} (Sekdes: {$desa['nama_sekdes']})\n";
    }
} else {
    echo "  Semua desa sudah memiliki no HP sekretaris desa\n";
}

// Statistik per kecamatan
echo "\n=== STATISTIK KELENGKAPAN PER KECAMATAN ===\n";
$stmt = $pdo->prepare("
    SELECT 
        kecamatan,
        COUNT(*) as total_desa,
        SUM(CASE WHEN nama_kepala_desa != '' AND nama_kepala_desa IS NOT NULL THEN 1 ELSE 0 END) as ada_kepala,
        SUM(CASE WHEN nama_sekdes != '' AND nama_sekdes IS NOT NULL THEN 1 ELSE 0 END) as ada_sekretaris,
        SUM(CASE WHEN no_hp_kepala_desa != '' AND no_hp_kepala_desa IS NOT NULL THEN 1 ELSE 0 END) as ada_hp_kepala,
        SUM(CASE WHEN no_hp_sekdes != '' AND no_hp_sekdes IS NOT NULL THEN 1 ELSE 0 END) as ada_hp_sekdes
    FROM desa 
    WHERE kecamatan != '' AND kecamatan IS NOT NULL
    GROUP BY kecamatan
    ORDER BY kecamatan
");
$stmt->execute();
$statsKecamatan = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($statsKecamatan as $stat) {
    $pctKepala = round(($stat['ada_kepala'] / $stat['total_desa']) * 100, 1);
    $pctSekretaris = round(($stat['ada_sekretaris'] / $stat['total_desa']) * 100, 1);
    $pctHpKepala = round(($stat['ada_hp_kepala'] / $stat['total_desa']) * 100, 1);
    $pctHpSekdes = round(($stat['ada_hp_sekdes'] / $stat['total_desa']) * 100, 1);
    
    echo "\nKecamatan {$stat['kecamatan']} ({$stat['total_desa']} desa):\n";
    echo "  Kepala Desa: {$stat['ada_kepala']}/{$stat['total_desa']} ({$pctKepala}%)\n";
    echo "  Sekretaris: {$stat['ada_sekretaris']}/{$stat['total_desa']} ({$pctSekretaris}%)\n";
    echo "  HP Kepala: {$stat['ada_hp_kepala']}/{$stat['total_desa']} ({$pctHpKepala}%)\n";
    echo "  HP Sekdes: {$stat['ada_hp_sekdes']}/{$stat['total_desa']} ({$pctHpSekdes}%)\n";
}

// Kecamatan dengan kelengkapan tertinggi dan terendah
echo "\n=== RANKING KELENGKAPAN KECAMATAN ===\n";

// Hitung skor kelengkapan (rata-rata persentase)
$rankingData = [];
foreach ($statsKecamatan as $stat) {
    $pctKepala = ($stat['ada_kepala'] / $stat['total_desa']) * 100;
    $pctSekretaris = ($stat['ada_sekretaris'] / $stat['total_desa']) * 100;
    $pctHpKepala = ($stat['ada_hp_kepala'] / $stat['total_desa']) * 100;
    $pctHpSekdes = ($stat['ada_hp_sekdes'] / $stat['total_desa']) * 100;
    
    $avgScore = ($pctKepala + $pctSekretaris + $pctHpKepala + $pctHpSekdes) / 4;
    
    $rankingData[] = [
        'kecamatan' => $stat['kecamatan'],
        'total_desa' => $stat['total_desa'],
        'skor' => round($avgScore, 1)
    ];
}

// Sort by score descending
usort($rankingData, function($a, $b) {
    return $b['skor'] <=> $a['skor'];
});

echo "Top 5 Kecamatan Terlengkap:\n";
for ($i = 0; $i < min(5, count($rankingData)); $i++) {
    $rank = $i + 1;
    echo "  {$rank}. {$rankingData[$i]['kecamatan']} - {$rankingData[$i]['skor']}% ({$rankingData[$i]['total_desa']} desa)\n";
}

echo "\nBottom 5 Kecamatan Perlu Perbaikan:\n";
for ($i = max(0, count($rankingData) - 5); $i < count($rankingData); $i++) {
    $rank = count($rankingData) - $i;
    echo "  {$rank}. {$rankingData[$i]['kecamatan']} - {$rankingData[$i]['skor']}% ({$rankingData[$i]['total_desa']} desa)\n";
}

echo "\n=== RINGKASAN TOTAL ===\n";
echo "Desa tanpa nama kepala desa: " . count($desaTanpaKepala) . "\n";
echo "Desa tanpa nama sekretaris: " . count($desaTanpaSekretaris) . "\n";
echo "Desa tanpa no HP kepala: " . count($desaTanpaHpKepala) . "\n";
echo "Desa tanpa no HP sekdes: " . count($desaTanpaHpSekdes) . "\n";

echo "\nSelesai!\n";
?>