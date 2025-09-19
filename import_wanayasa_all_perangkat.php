<?php
require_once 'config/database.php';

// Inisialisasi koneksi database
$db = new Database();
$conn = $db->getConnection();

// Fungsi untuk normalisasi nama kecamatan
function normalizeKecamatan($kecamatan) {
    return strtolower(trim($kecamatan));
}

// Fungsi untuk mendapatkan ID desa berdasarkan nama desa dan kecamatan
function getDesaId($conn, $namaDesa, $kecamatan) {
    $stmt = $conn->prepare("SELECT id FROM desa WHERE LOWER(nama_desa) = ? AND LOWER(kecamatan) = ?");
    $stmt->execute([strtolower($namaDesa), normalizeKecamatan($kecamatan)]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? $result['id'] : null;
}

// Fungsi untuk mengecek apakah perangkat sudah ada
function isPerangkatExists($conn, $nama, $desaId) {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM perangkat_desa WHERE LOWER(nama_lengkap) = ? AND desa_id = ?");
    $stmt->execute([strtolower($nama), $desaId]);
    return $stmt->fetchColumn() > 0;
}

// Array file CSV yang akan diproses
$csvFiles = [
    'csv/data-desa-fix1.csv',
    'csv/data-desa-fix6.csv', 
    'csv/data-desa-fix7.csv',
    'csv/data-desa-fix8.csv',
    'csv/data-desa-fix9.csv'
];

$totalImported = 0;
$totalErrors = 0;
$errors = [];

echo "Memulai import data perangkat desa untuk Kecamatan Wanayasa...\n";

foreach ($csvFiles as $csvFile) {
    if (!file_exists($csvFile)) {
        echo "File $csvFile tidak ditemukan, skip...\n";
        continue;
    }
    
    echo "Memproses file: $csvFile\n";
    
    if (($handle = fopen($csvFile, "r")) !== FALSE) {
        // Skip header jika ada
        $header = fgetcsv($handle, 1000, ",");
        
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            // Skip baris kosong
            if (empty($data) || count($data) < 10) continue;
            
            // Parsing data CSV
            $namaDesa = trim($data[1]);
            $kecamatan = trim($data[2]);
            $namaPerangkat = trim($data[3]);
            $tempatTanggalLahir = trim($data[4]);
            $alamat = trim($data[5]);
            $noTelepon = trim($data[6]);
            $pendidikan = trim($data[7]);
            $tahunDiangkat = trim($data[8]);
            $noSK = trim($data[9]);
            $jabatan = trim($data[10]);
            
            // Filter hanya untuk Kecamatan Wanayasa
            if (normalizeKecamatan($kecamatan) !== 'wanayasa') {
                continue;
            }
            
            // Dapatkan ID desa
            $desaId = getDesaId($conn, $namaDesa, $kecamatan);
            
            if (!$desaId) {
                $errors[] = "Desa $namaDesa tidak ditemukan";
                $totalErrors++;
                continue;
            }
            
            // Cek apakah perangkat sudah ada
            if (isPerangkatExists($conn, $namaPerangkat, $desaId)) {
                echo "Perangkat $namaPerangkat sudah ada di $namaDesa\n";
                continue;
            }
            
            try {
                // Insert data perangkat
                $stmt = $conn->prepare("
                    INSERT INTO perangkat_desa 
                    (desa_id, nama_lengkap, tempat_lahir, alamat, no_telepon, pendidikan, tahun_diangkat, no_sk, jabatan) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $stmt->execute([
                    $desaId,
                    $namaPerangkat,
                    $tempatTanggalLahir,
                    $alamat,
                    $noTelepon,
                    $pendidikan,
                    $tahunDiangkat,
                    $noSK,
                    $jabatan
                ]);
                
                echo "Import: $namaPerangkat di $namaDesa\n";
                $totalImported++;
                
            } catch (Exception $e) {
                $errors[] = "Error import $namaPerangkat: " . $e->getMessage();
                $totalErrors++;
            }
        }
        
        fclose($handle);
    }
}

echo "\n=== RINGKASAN IMPORT ===\n";
echo "Total data imported: $totalImported\n";
echo "Total errors: $totalErrors\n";

if (!empty($errors)) {
    echo "\nErrors:\n";
    foreach ($errors as $error) {
        echo "- $error\n";
    }
}

// Tampilkan desa di Kecamatan Wanayasa yang tidak memiliki perangkat
echo "\n=== DESA TANPA PERANGKAT DI KECAMATAN WANAYASA ===\n";
$stmt = $conn->prepare("
    SELECT d.nama_desa 
    FROM desa d 
    LEFT JOIN perangkat_desa pd ON d.id = pd.desa_id 
    WHERE LOWER(d.kecamatan) = 'wanayasa' AND pd.id IS NULL
    ORDER BY d.nama_desa
");
$stmt->execute();
$desaTanpaPerangkat = $stmt->fetchAll(PDO::FETCH_COLUMN);

if (!empty($desaTanpaPerangkat)) {
    foreach ($desaTanpaPerangkat as $desa) {
        echo "- $desa\n";
    }
} else {
    echo "Semua desa di Kecamatan Wanayasa sudah memiliki perangkat.\n";
}

echo "\nImport selesai!\n";
?>