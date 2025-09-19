<?php
// Script untuk mengupdate kecamatan desa yang berhasil dicocokkan

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

echo "\n=== UPDATE KECAMATAN DESA ===\n";

// Ambil desa yang kecamatannya kosong
$stmt = $pdo->prepare("SELECT id, nama_desa FROM desa WHERE kecamatan = '' OR kecamatan IS NULL ORDER BY nama_desa");
$stmt->execute();
$desaTanpaKecamatan = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Total desa tanpa kecamatan: " . count($desaTanpaKecamatan) . "\n";

// Baca data dari CSV untuk referensi kecamatan
$csvFile = 'data-desa.csv';
if (!file_exists($csvFile)) {
    echo "File $csvFile tidak ditemukan\n";
    exit;
}

$csvData = [];
if (($handle = fopen($csvFile, "r")) !== FALSE) {
    $header = fgetcsv($handle, 1000, ",");
    
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

// Cocokkan dan update desa
$matched = [];
$notMatched = [];
$updated = 0;
$failed = 0;

echo "\n=== PROSES UPDATE ===\n";

foreach ($desaTanpaKecamatan as $desa) {
    $namaDesaLower = strtolower($desa['nama_desa']);
    
    // Cari exact match di CSV
    if (isset($csvData[$namaDesaLower])) {
        $kecamatan = $csvData[$namaDesaLower];
        
        // Update ke database
        $stmt = $pdo->prepare("UPDATE desa SET kecamatan = ? WHERE id = ?");
        $result = $stmt->execute([$kecamatan, $desa['id']]);
        
        if ($result && $stmt->rowCount() > 0) {
            echo "✓ Updated: {$desa['nama_desa']} -> {$kecamatan}\n";
            $updated++;
            $matched[] = [
                'nama_desa' => $desa['nama_desa'],
                'kecamatan' => $kecamatan,
                'match_type' => 'exact_csv'
            ];
        } else {
            echo "✗ Failed: {$desa['nama_desa']}\n";
            $failed++;
        }
    } else {
        // Cari partial match di CSV
        $found = false;
        foreach ($csvData as $csvDesa => $csvKecamatan) {
            if (strpos($csvDesa, $namaDesaLower) !== false || strpos($namaDesaLower, $csvDesa) !== false) {
                // Update ke database
                $stmt = $pdo->prepare("UPDATE desa SET kecamatan = ? WHERE id = ?");
                $result = $stmt->execute([$csvKecamatan, $desa['id']]);
                
                if ($result && $stmt->rowCount() > 0) {
                    echo "✓ Updated: {$desa['nama_desa']} -> {$csvKecamatan} (partial match: {$csvDesa})\n";
                    $updated++;
                    $matched[] = [
                        'nama_desa' => $desa['nama_desa'],
                        'kecamatan' => $csvKecamatan,
                        'match_type' => 'partial_csv',
                        'csv_nama' => $csvDesa
                    ];
                } else {
                    echo "✗ Failed: {$desa['nama_desa']}\n";
                    $failed++;
                }
                $found = true;
                break;
            }
        }
        
        if (!$found) {
            $notMatched[] = $desa;
        }
    }
}

echo "\n=== RINGKASAN UPDATE ===\n";
echo "Total desa berhasil diupdate: $updated\n";
echo "Total desa gagal diupdate: $failed\n";
echo "Total desa tidak cocok: " . count($notMatched) . "\n";

// Desa yang tidak cocok perlu penanganan manual
if (count($notMatched) > 0) {
    echo "\n=== DESA YANG PERLU PENANGANAN MANUAL ===\n";
    foreach ($notMatched as $desa) {
        echo "- {$desa['nama_desa']} (ID: {$desa['id']})\n";
    }
    
    // Mapping manual untuk desa yang tidak cocok
    $manualMapping = [
        'Kecepit' => 'Punggelan',
        'klampok' => 'Banjarnegara',
        'Legoksayem' => 'Wanayasa',
        'merden' => 'Banjarnegara',
        'Penanggungan' => 'Wanayasa',
        'Petir' => 'Banjarnegara',
        'Pucungbedug' => 'Banjarnegara',
        'Semangkung' => 'Kalibening'
    ];
    
    echo "\n=== UPDATE MANUAL MAPPING ===\n";
    $manualUpdated = 0;
    
    foreach ($notMatched as $desa) {
        if (isset($manualMapping[$desa['nama_desa']])) {
            $kecamatan = $manualMapping[$desa['nama_desa']];
            
            $stmt = $pdo->prepare("UPDATE desa SET kecamatan = ? WHERE id = ?");
            $result = $stmt->execute([$kecamatan, $desa['id']]);
            
            if ($result && $stmt->rowCount() > 0) {
                echo "✓ Manual Update: {$desa['nama_desa']} -> {$kecamatan}\n";
                $manualUpdated++;
            } else {
                echo "✗ Manual Failed: {$desa['nama_desa']}\n";
            }
        } else {
            echo "? Tidak ada mapping untuk: {$desa['nama_desa']}\n";
        }
    }
    
    echo "\nTotal manual update: $manualUpdated\n";
}

// Cek ulang desa tanpa kecamatan
echo "\n=== VERIFIKASI AKHIR ===\n";
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM desa WHERE kecamatan = '' OR kecamatan IS NULL");
$stmt->execute();
$remaining = $stmt->fetch(PDO::FETCH_ASSOC);

echo "Desa yang masih tanpa kecamatan: {$remaining['total']}\n";

if ($remaining['total'] > 0) {
    $stmt = $pdo->prepare("SELECT id, nama_desa FROM desa WHERE kecamatan = '' OR kecamatan IS NULL ORDER BY nama_desa");
    $stmt->execute();
    $stillEmpty = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "\nDesa yang masih kosong:\n";
    foreach ($stillEmpty as $desa) {
        echo "- {$desa['nama_desa']} (ID: {$desa['id']})\n";
    }
}

echo "\nSelesai!\n";
?>