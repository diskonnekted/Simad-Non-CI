<?php
// Laporan final hasil update kontak person desa

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

echo "\n=== LAPORAN FINAL UPDATE KONTAK PERSON DESA ===\n";
echo "Tanggal: " . date('Y-m-d H:i:s') . "\n";

// Statistik umum
echo "\n1. STATISTIK UMUM:\n";
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_desa,
        SUM(CASE WHEN nama_kepala_desa != '' AND nama_kepala_desa IS NOT NULL THEN 1 ELSE 0 END) as ada_kepala,
        SUM(CASE WHEN nama_sekdes != '' AND nama_sekdes IS NOT NULL THEN 1 ELSE 0 END) as ada_sekretaris,
        SUM(CASE WHEN no_hp_kepala_desa != '' AND no_hp_kepala_desa IS NOT NULL THEN 1 ELSE 0 END) as ada_hp_kepala,
        SUM(CASE WHEN no_hp_sekdes != '' AND no_hp_sekdes IS NOT NULL THEN 1 ELSE 0 END) as ada_hp_sekdes
    FROM desa 
    WHERE kecamatan != '' AND kecamatan IS NOT NULL
");
$stmt->execute();
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

$pctKepala = round(($stats['ada_kepala'] / $stats['total_desa']) * 100, 1);
$pctSekretaris = round(($stats['ada_sekretaris'] / $stats['total_desa']) * 100, 1);
$pctHpKepala = round(($stats['ada_hp_kepala'] / $stats['total_desa']) * 100, 1);
$pctHpSekdes = round(($stats['ada_hp_sekdes'] / $stats['total_desa']) * 100, 1);
$avgKelengkapan = round(($pctKepala + $pctSekretaris + $pctHpKepala + $pctHpSekdes) / 4, 1);

echo "   Total desa: {$stats['total_desa']}\n";
echo "   Desa dengan nama kepala desa: {$stats['ada_kepala']} ({$pctKepala}%)\n";
echo "   Desa dengan nama sekretaris: {$stats['ada_sekretaris']} ({$pctSekretaris}%)\n";
echo "   Desa dengan no HP kepala: {$stats['ada_hp_kepala']} ({$pctHpKepala}%)\n";
echo "   Desa dengan no HP sekdes: {$stats['ada_hp_sekdes']} ({$pctHpSekdes}%)\n";
echo "   Rata-rata kelengkapan: {$avgKelengkapan}%\n";

// Desa yang masih belum lengkap
echo "\n2. DESA YANG MASIH PERLU DILENGKAPI:\n";

// Desa tanpa kepala desa
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
    echo "\n   Desa tanpa nama kepala desa (" . count($desaTanpaKepala) . "):";
    $currentKecamatan = '';
    foreach ($desaTanpaKepala as $desa) {
        if ($currentKecamatan != $desa['kecamatan']) {
            $currentKecamatan = $desa['kecamatan'];
            echo "\n     Kecamatan {$currentKecamatan}:\n";
        }
        echo "       - {$desa['nama_desa']}\n";
    }
} else {
    echo "   ✓ Semua desa sudah memiliki nama kepala desa\n";
}

// Desa tanpa sekretaris
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
    echo "\n   Desa tanpa nama sekretaris (" . count($desaTanpaSekretaris) . "):";
    $currentKecamatan = '';
    foreach ($desaTanpaSekretaris as $desa) {
        if ($currentKecamatan != $desa['kecamatan']) {
            $currentKecamatan = $desa['kecamatan'];
            echo "\n     Kecamatan {$currentKecamatan}:\n";
        }
        echo "       - {$desa['nama_desa']}\n";
    }
} else {
    echo "   ✓ Semua desa sudah memiliki nama sekretaris\n";
}

// Ranking kecamatan berdasarkan kelengkapan
echo "\n3. RANKING KECAMATAN BERDASARKAN KELENGKAPAN:\n";

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

// Hitung skor dan ranking
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
        'ada_kepala' => $stat['ada_kepala'],
        'ada_sekretaris' => $stat['ada_sekretaris'],
        'ada_hp_kepala' => $stat['ada_hp_kepala'],
        'ada_hp_sekdes' => $stat['ada_hp_sekdes'],
        'skor' => round($avgScore, 1)
    ];
}

// Sort by score descending
usort($rankingData, function($a, $b) {
    return $b['skor'] <=> $a['skor'];
});

echo "\n   Top 10 Kecamatan Terlengkap:\n";
for ($i = 0; $i < min(10, count($rankingData)); $i++) {
    $rank = $i + 1;
    $data = $rankingData[$i];
    echo "     {$rank}. {$data['kecamatan']} - {$data['skor']}% ({$data['total_desa']} desa)\n";
    echo "        Kepala: {$data['ada_kepala']}/{$data['total_desa']}, Sekdes: {$data['ada_sekretaris']}/{$data['total_desa']}, HP Kepala: {$data['ada_hp_kepala']}/{$data['total_desa']}, HP Sekdes: {$data['ada_hp_sekdes']}/{$data['total_desa']}\n";
}

echo "\n   Kecamatan yang Perlu Perbaikan (skor < 90%):\n";
$needImprovement = array_filter($rankingData, function($item) {
    return $item['skor'] < 90;
});

if (count($needImprovement) > 0) {
    foreach ($needImprovement as $data) {
        echo "     - {$data['kecamatan']} - {$data['skor']}% ({$data['total_desa']} desa)\n";
        echo "       Kepala: {$data['ada_kepala']}/{$data['total_desa']}, Sekdes: {$data['ada_sekretaris']}/{$data['total_desa']}, HP Kepala: {$data['ada_hp_kepala']}/{$data['total_desa']}, HP Sekdes: {$data['ada_hp_sekdes']}/{$data['total_desa']}\n";
    }
} else {
    echo "     ✓ Semua kecamatan sudah memiliki kelengkapan ≥ 90%\n";
}

// Rekomendasi tindak lanjut
echo "\n4. REKOMENDASI TINDAK LANJUT:\n";

if ($avgKelengkapan >= 95) {
    echo "   ✓ EXCELLENT: Kelengkapan kontak person sudah sangat baik ({$avgKelengkapan}%)\n";
    echo "   - Lakukan pemeliharaan data secara berkala\n";
    echo "   - Verifikasi nomor kontak yang sudah ada\n";
} elseif ($avgKelengkapan >= 85) {
    echo "   ✓ GOOD: Kelengkapan kontak person sudah baik ({$avgKelengkapan}%)\n";
    echo "   - Fokus melengkapi data di kecamatan dengan skor rendah\n";
    echo "   - Koordinasi dengan kecamatan untuk data yang masih kosong\n";
} elseif ($avgKelengkapan >= 70) {
    echo "   ⚠ FAIR: Kelengkapan kontak person cukup ({$avgKelengkapan}%)\n";
    echo "   - Perlu upaya intensif untuk melengkapi data\n";
    echo "   - Prioritaskan kecamatan dengan skor < 70%\n";
} else {
    echo "   ✗ POOR: Kelengkapan kontak person masih rendah ({$avgKelengkapan}%)\n";
    echo "   - Perlu program khusus untuk pengumpulan data\n";
    echo "   - Koordinasi intensif dengan semua kecamatan\n";
}

echo "\n5. RINGKASAN PENCAPAIAN:\n";
echo "   - Total desa yang diproses: {$stats['total_desa']}\n";
echo "   - Kelengkapan nama kepala desa: {$pctKepala}%\n";
echo "   - Kelengkapan nama sekretaris: {$pctSekretaris}%\n";
echo "   - Kelengkapan no HP kepala: {$pctHpKepala}%\n";
echo "   - Kelengkapan no HP sekdes: {$pctHpSekdes}%\n";
echo "   - Rata-rata kelengkapan: {$avgKelengkapan}%\n";

if ($avgKelengkapan > 80) {
    echo "\n   🎉 SUKSES: Target kelengkapan kontak person tercapai!\n";
} else {
    echo "\n   📋 PROGRESS: Masih perlu perbaikan untuk mencapai target optimal\n";
}

echo "\n" . str_repeat("=", 60) . "\n";
echo "LAPORAN SELESAI - " . date('Y-m-d H:i:s') . "\n";
echo str_repeat("=", 60) . "\n";

?>