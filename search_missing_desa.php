<?php
// Script untuk mencari 3 desa yang masih hilang dengan variasi nama

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

echo "\n=== MENCARI 3 DESA YANG HILANG ===\n";

// Variasi nama untuk 3 desa yang hilang
$variasiNama = [
    'Pakikiran' => ['pakikiran', 'pekikiran', 'Pekikiran', 'pakikiran', 'PAKIKIRAN'],
    'Panarusankulon' => ['panarusankulon', 'panarusan kulon', 'Panarusan Kulon', 'panerusankulon', 'panerusan kulon'],
    'Panarusanwetan' => ['panarusanwetan', 'panarusan wetan', 'Panarusan Wetan', 'panerusanwetan', 'panerusan wetan']
];

foreach ($variasiNama as $namaTarget => $variasi) {
    echo "\nMencari variasi untuk: $namaTarget\n";
    
    foreach ($variasi as $nama) {
        $stmt = $pdo->prepare("SELECT id, nama_desa, kecamatan FROM desa WHERE nama_desa LIKE ?");
        $stmt->execute(["%$nama%"]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($results) > 0) {
            echo "  Ditemukan dengan pattern '$nama':\n";
            foreach ($results as $row) {
                echo "    - {$row['nama_desa']} (kecamatan: '{$row['kecamatan']}')\n";
            }
        }
    }
}

echo "\n=== MENCARI DENGAN PATTERN UMUM ===\n";

// Cari dengan pattern yang lebih umum
$patterns = ['%kiran%', '%rusan%', '%wetan%', '%kulon%'];

foreach ($patterns as $pattern) {
    echo "\nPattern: $pattern\n";
    $stmt = $pdo->prepare("SELECT nama_desa, kecamatan FROM desa WHERE nama_desa LIKE ? AND kecamatan = '' ORDER BY nama_desa");
    $stmt->execute([$pattern]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($results) > 0) {
        foreach ($results as $row) {
            echo "  - {$row['nama_desa']} (kecamatan: '{$row['kecamatan']}')\n";
        }
    } else {
        echo "  Tidak ada hasil\n";
    }
}

echo "\n=== SEMUA DESA DENGAN KECAMATAN KOSONG ===\n";
$stmt = $pdo->prepare("SELECT nama_desa FROM desa WHERE kecamatan = '' ORDER BY nama_desa LIMIT 20");
$stmt->execute();
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "20 desa pertama dengan kecamatan kosong:\n";
foreach ($results as $row) {
    echo "- {$row['nama_desa']}\n";
}

echo "\nSelesai!\n";
?>