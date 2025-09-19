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

// File CSV yang akan diproses
$csvFile = 'data-desa.csv';

$totalImported = 0;
$totalErrors = 0;
$errors = [];

echo "Memulai import data perangkat desa untuk Kecamatan Wanayasa dari file asli...\n";

if (!file_exists($csvFile)) {
    echo "File $csvFile tidak ditemukan!\n";
    exit(1);
}

echo "Memproses file: $csvFile\n";

if (($handle = fopen($csvFile, "r")) !== FALSE) {
    // Skip header
    $header = fgetcsv($handle, 1000, ",");
    
    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
        // Skip baris kosong
        if (empty($data) || count($data) < 11) continue;
        
        // Parsing data CSV berdasarkan struktur file asli
        $no = trim($data[0]);
        $namaDesa = trim($data[1]);
        $kecamatan = trim($data[2]);
        $namaLengkap = trim($data[3]);
        $ttl = trim($data[4]);
        $alamat = trim($data[5]);
        $telepon = trim($data[6]);
        $pendidikan = trim($data[7]);
        $diangkat = trim($data[8]);
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
        if (isPerangkatExists($conn, $namaLengkap, $desaId)) {
            echo "Perangkat $namaLengkap sudah ada di $namaDesa\n";
            continue;
        }
        
        try {
            // Pisahkan tempat dan tanggal lahir dari TTL
            $tempatLahir = '';
            $tanggalLahir = null;
            
            if (!empty($ttl)) {
                // Format TTL biasanya: "Tempat, tanggal"
                $ttlParts = explode(',', $ttl, 2);
                if (count($ttlParts) >= 1) {
                    $tempatLahir = trim($ttlParts[0]);
                }
                if (count($ttlParts) >= 2) {
                    $tanggalStr = trim($ttlParts[1]);
                    // Coba parse tanggal (format bisa bervariasi)
                    $tanggalLahir = null; // Untuk sementara set null karena format tanggal bervariasi
                }
            }
            
            // Insert data perangkat
            $stmt = $conn->prepare("
                INSERT INTO perangkat_desa 
                (desa_id, nama_lengkap, tempat_lahir, tanggal_lahir, alamat, no_telepon, pendidikan, tahun_diangkat, no_sk, jabatan) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $desaId,
                $namaLengkap,
                $tempatLahir,
                $tanggalLahir,
                $alamat,
                $telepon,
                $pendidikan,
                $diangkat,
                $noSK,
                $jabatan
            ]);
            
            echo "Import: $namaLengkap di $namaDesa\n";
            $totalImported++;
            
        } catch (Exception $e) {
            $errors[] = "Error import $namaLengkap: " . $e->getMessage();
            $totalErrors++;
        }
    }
    
    fclose($handle);
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