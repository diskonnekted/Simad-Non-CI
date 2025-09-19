<?php
// Script untuk mengecek desa-desa yang belum memiliki kecamatan dan mencocokkannya

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

echo "\n=== DESA TANPA KECAMATAN ===\n";

// Ambil desa yang kecamatannya kosong
$stmt = $pdo->prepare("SELECT id, nama_desa FROM desa WHERE kecamatan = '' OR kecamatan IS NULL ORDER BY nama_desa");
$stmt->execute();
$desaTanpaKecamatan = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Total desa tanpa kecamatan: " . count($desaTanpaKecamatan) . "\n\n";

if (count($desaTanpaKecamatan) > 0) {
    echo "Daftar desa tanpa kecamatan (20 pertama):\n";
    for ($i = 0; $i < min(20, count($desaTanpaKecamatan)); $i++) {
        $desa = $desaTanpaKecamatan[$i];
        echo "- {$desa['nama_desa']} (ID: {$desa['id']})\n";
    }
    if (count($desaTanpaKecamatan) > 20) {
        echo "... dan " . (count($desaTanpaKecamatan) - 20) . " desa lainnya\n";
    }
}

// Baca data dari CSV untuk referensi kecamatan
echo "\n=== MEMBACA DATA REFERENSI DARI CSV ===\n";

$csvFile = 'data-desa.csv';
if (!file_exists($csvFile)) {
    echo "File $csvFile tidak ditemukan\n";
    exit;
}

$csvData = [];
if (($handle = fopen($csvFile, "r")) !== FALSE) {
    $header = fgetcsv($handle, 1000, ","); // Menggunakan koma sebagai delimiter
    
    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
        if (count($data) >= 3) {
            $namaDesa = trim($data[1]);
            $kecamatan = trim($data[2]);
            
            if (!empty($namaDesa) && !empty($kecamatan)) {
                $key = strtolower($namaDesa);
                if (!isset($csvData[$key])) {
                    $csvData[$key] = $kecamatan;
                }
            }
        }
    }
    fclose($handle);
}

echo "Data referensi dari CSV: " . count($csvData) . " entri\n";

// Ambil daftar kecamatan yang sudah ada di database
echo "\n=== KECAMATAN YANG ADA DI DATABASE ===\n";
$stmt = $pdo->prepare("SELECT DISTINCT kecamatan, COUNT(*) as jumlah FROM desa WHERE kecamatan != '' AND kecamatan IS NOT NULL GROUP BY kecamatan ORDER BY kecamatan");
$stmt->execute();
$kecamatanDB = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Kecamatan yang sudah ada di database:\n";
foreach ($kecamatanDB as $kec) {
    echo "- {$kec['kecamatan']} ({$kec['jumlah']} desa)\n";
}

// Cocokkan desa tanpa kecamatan dengan data CSV
echo "\n=== PENCOCOKAN DESA DENGAN KECAMATAN ===\n";

$matched = [];
$notMatched = [];

foreach ($desaTanpaKecamatan as $desa) {
    $namaDesaLower = strtolower($desa['nama_desa']);
    
    // Cari exact match di CSV
    if (isset($csvData[$namaDesaLower])) {
        $matched[] = [
            'id' => $desa['id'],
            'nama_desa' => $desa['nama_desa'],
            'kecamatan' => $csvData[$namaDesaLower],
            'match_type' => 'exact_csv'
        ];
    } else {
        // Cari partial match di CSV
        $found = false;
        foreach ($csvData as $csvDesa => $csvKecamatan) {
            // Cek apakah nama desa mengandung atau terkandung dalam nama CSV
            if (strpos($csvDesa, $namaDesaLower) !== false || strpos($namaDesaLower, $csvDesa) !== false) {
                $matched[] = [
                    'id' => $desa['id'],
                    'nama_desa' => $desa['nama_desa'],
                    'kecamatan' => $csvKecamatan,
                    'match_type' => 'partial_csv',
                    'csv_nama' => $csvDesa
                ];
                $found = true;
                break;
            }
        }
        
        if (!$found) {
            $notMatched[] = $desa;
        }
    }
}

echo "\n=== HASIL PENCOCOKAN ===\n";
echo "Desa yang berhasil dicocokkan: " . count($matched) . "\n";
echo "Desa yang tidak cocok: " . count($notMatched) . "\n\n";

if (count($matched) > 0) {
    echo "DESA YANG BERHASIL DICOCOKKAN (10 pertama):\n";
    for ($i = 0; $i < min(10, count($matched)); $i++) {
        $match = $matched[$i];
        echo "- {$match['nama_desa']} -> {$match['kecamatan']} ({$match['match_type']})";
        if (isset($match['csv_nama'])) {
            echo " [CSV: {$match['csv_nama']}]";
        }
        echo "\n";
    }
    if (count($matched) > 10) {
        echo "... dan " . (count($matched) - 10) . " desa lainnya\n";
    }
}

if (count($notMatched) > 0) {
    echo "\nDESA YANG TIDAK COCOK (20 pertama):\n";
    for ($i = 0; $i < min(20, count($notMatched)); $i++) {
        $desa = $notMatched[$i];
        echo "- {$desa['nama_desa']}\n";
    }
    if (count($notMatched) > 20) {
        echo "... dan " . (count($notMatched) - 20) . " desa lainnya\n";
    }
}

echo "\nSelesai!\n";
?>