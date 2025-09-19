<?php
require_once 'config/database.php';

// Inisialisasi koneksi database
$db = new Database();
$pdo = $db->getConnection();

// Fungsi untuk normalisasi nama kecamatan
function normalizeKecamatan($kecamatan) {
    return trim(strtolower($kecamatan));
}

// Fungsi untuk mendapatkan ID desa berdasarkan nama desa dan kecamatan
function getDesaId($pdo, $namaDesa, $namaKecamatan) {
    $stmt = $pdo->prepare("SELECT id FROM desa WHERE LOWER(nama_desa) = ? AND LOWER(kecamatan) = ?");
    $stmt->execute([strtolower(trim($namaDesa)), normalizeKecamatan($namaKecamatan)]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? $result['id'] : null;
}

// Fungsi untuk cek apakah perangkat sudah ada
function isPerangkatExists($pdo, $desaId, $namaLengkap, $jabatan) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM perangkat_desa WHERE desa_id = ? AND nama_lengkap = ? AND jabatan = ?");
    $stmt->execute([$desaId, $namaLengkap, $jabatan]);
    return $stmt->fetchColumn() > 0;
}

// File CSV yang akan diproses untuk Kecamatan Purwanegara
$csvFiles = [
    'csv/data-desa-fix1.csv',
    'csv/data-desa-fix2.csv', 
    'csv/data-desa-fix3.csv',
    'csv/data-desa-fix4.csv',
    'csv/data-desa-fix5.csv',
    'csv/data-desa-fix6.csv'
];

$totalImported = 0;
$totalErrors = 0;
$importSummary = [];

foreach ($csvFiles as $csvFile) {
    if (!file_exists($csvFile)) {
        echo "File tidak ditemukan: $csvFile\n";
        continue;
    }
    
    echo "Memproses file: $csvFile\n";
    
    if (($handle = fopen($csvFile, "r")) !== FALSE) {
        $header = fgetcsv($handle); // Skip header jika ada
        
        while (($data = fgetcsv($handle)) !== FALSE) {
            // Skip baris kosong
            if (empty($data) || count($data) < 10) continue;
            
            $namaDesa = trim($data[1]);
            $namaKecamatan = trim($data[2]);
            
            // Filter hanya untuk Kecamatan Purwanegara
            if (normalizeKecamatan($namaKecamatan) !== 'purwanegara') {
                continue;
            }
            
            $namaLengkap = trim($data[3]);
            $tempatTanggalLahir = trim($data[4]);
            $alamat = trim($data[5]);
            $noTelepon = trim($data[6]);
            $pendidikan = trim($data[7]);
            $tahunJabatan = trim($data[8]);
            $noSK = trim($data[9]);
            $jabatan = trim($data[10]);
            
            // Skip jika data penting kosong
            if (empty($namaLengkap) || empty($jabatan)) {
                continue;
            }
            
            // Dapatkan ID desa
            $desaId = getDesaId($pdo, $namaDesa, $namaKecamatan);
            
            if (!$desaId) {
                echo "Desa tidak ditemukan: $namaDesa, $namaKecamatan\n";
                $totalErrors++;
                continue;
            }
            
            // Cek apakah perangkat sudah ada
            if (isPerangkatExists($pdo, $desaId, $namaLengkap, $jabatan)) {
                echo "Perangkat sudah ada: $namaLengkap - $jabatan di $namaDesa\n";
                continue;
            }
            
            try {
                // Insert data perangkat
                $stmt = $pdo->prepare("
                    INSERT INTO perangkat_desa 
                    (desa_id, nama_lengkap, tempat_tanggal_lahir, alamat, no_telepon, pendidikan, tahun_jabatan, no_sk, jabatan, created_at, updated_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                ");
                
                $stmt->execute([
                    $desaId,
                    $namaLengkap,
                    $tempatTanggalLahir,
                    $alamat,
                    $noTelepon,
                    $pendidikan,
                    $tahunJabatan,
                    $noSK,
                    $jabatan
                ]);
                
                $totalImported++;
                
                // Update summary per desa
                if (!isset($importSummary[$namaDesa])) {
                    $importSummary[$namaDesa] = 0;
                }
                $importSummary[$namaDesa]++;
                
                echo "Imported: $namaLengkap - $jabatan di $namaDesa\n";
                
            } catch (Exception $e) {
                echo "Error importing $namaLengkap: " . $e->getMessage() . "\n";
                $totalErrors++;
            }
        }
        
        fclose($handle);
    }
}

echo "\n=== RINGKASAN IMPORT KECAMATAN PURWANEGARA ===\n";
echo "Total data imported: $totalImported\n";
echo "Total errors: $totalErrors\n";
echo "\nRingkasan per desa:\n";

foreach ($importSummary as $desa => $count) {
    echo "- $desa: $count perangkat\n";
}

// Tampilkan desa yang tidak ada perangkat yang diimport
echo "\nDesa tanpa perangkat yang diimport:\n";
$stmt = $pdo->prepare("SELECT nama_desa FROM desa WHERE LOWER(kecamatan) = 'purwanegara' ORDER BY nama_desa");
$stmt->execute();
$allDesa = $stmt->fetchAll(PDO::FETCH_COLUMN);

foreach ($allDesa as $desa) {
    if (!isset($importSummary[$desa])) {
        echo "- $desa: 0 perangkat\n";
    }
}

echo "\nImport selesai!\n";
?>