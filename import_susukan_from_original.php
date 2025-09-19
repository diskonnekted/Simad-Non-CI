<?php
// Script untuk import data perangkat desa di Kecamatan Susukan dari file data-desa.csv
// Data akan diupdate ke kolom nama_kepala_desa dan nama_sekdes di tabel desa

// Inisialisasi koneksi database
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

// Baca file CSV
$csvFile = 'data-desa.csv';
if (!file_exists($csvFile)) {
    die("File $csvFile tidak ditemukan\n");
}

$handle = fopen($csvFile, 'r');
if ($handle === FALSE) {
    die("Gagal membuka file $csvFile\n");
}

// Skip header
fgetcsv($handle);

$importCount = 0;
$errorCount = 0;
$duplicateCount = 0;
$desaList = [];
$desaData = []; // Array untuk menyimpan data per desa

echo "Mulai import data perangkat desa Kecamatan Susukan...\n";

while (($data = fgetcsv($handle)) !== FALSE) {
    // Normalisasi nama kecamatan
    $kecamatan = trim($data[2]);
    if (strtolower($kecamatan) !== 'susukan') {
        continue;
    }
    
    $namaDesa = trim($data[1]);
    $namaLengkap = trim($data[3]);
    $ttl = trim($data[4]);
    $alamat = trim($data[5]);
    $telepon = trim($data[6]);
    $pendidikan = trim($data[7]);
    $diangkat = trim($data[8]);
    $noSK = trim($data[9]);
    $jabatan = trim($data[10]);
    
    // Tambahkan desa ke list
    if (!in_array($namaDesa, $desaList)) {
        $desaList[] = $namaDesa;
        $desaData[$namaDesa] = [];
    }
    
    // Skip jika data kosong
    if (empty($namaLengkap) || empty($jabatan)) {
        continue;
    }
    
    // Kategorikan jabatan
    $jabatanLower = strtolower($jabatan);
    if (strpos($jabatanLower, 'kepala desa') !== false) {
        $desaData[$namaDesa]['kepala_desa'] = [
            'nama' => $namaLengkap,
            'jabatan' => $jabatan,
            'telepon' => $telepon
        ];
    } elseif (strpos($jabatanLower, 'sekretaris desa') !== false || strpos($jabatanLower, 'sekdes') !== false) {
        $desaData[$namaDesa]['sekretaris'] = [
            'nama' => $namaLengkap,
            'telepon' => $telepon
        ];
    }
    
    echo "Memproses: $namaLengkap - $jabatan di $namaDesa\n";
}

fclose($handle);

// Update data ke tabel desa
echo "\nMengupdate data ke tabel desa...\n";
foreach ($desaData as $namaDesa => $perangkat) {
    // Cari desa di database
    $desaStmt = $pdo->prepare("SELECT id, nama_kepala_desa, nama_sekdes FROM desa WHERE nama_desa = ? AND kecamatan = 'Susukan'");
    $desaStmt->execute([$namaDesa]);
    $desaResult = $desaStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$desaResult) {
        echo "Desa tidak ditemukan: $namaDesa\n";
        $errorCount++;
        continue;
    }
    
    $desaId = $desaResult['id'];
    $updateFields = [];
    $updateValues = [];
    
    // Update kepala desa jika ada dan belum diisi
    if (isset($perangkat['kepala_desa']) && empty($desaResult['nama_kepala_desa'])) {
        $updateFields[] = "nama_kepala_desa = ?";
        $updateFields[] = "jabatan_kepala_desa = ?";
        $updateFields[] = "no_hp_kepala_desa = ?";
        $updateValues[] = $perangkat['kepala_desa']['nama'];
        $updateValues[] = $perangkat['kepala_desa']['jabatan'];
        $updateValues[] = $perangkat['kepala_desa']['telepon'];
        echo "Update kepala desa: {$perangkat['kepala_desa']['nama']} di $namaDesa\n";
    }
    
    // Update sekretaris desa jika ada dan belum diisi
    if (isset($perangkat['sekretaris']) && empty($desaResult['nama_sekdes'])) {
        $updateFields[] = "nama_sekdes = ?";
        $updateFields[] = "no_hp_sekdes = ?";
        $updateValues[] = $perangkat['sekretaris']['nama'];
        $updateValues[] = $perangkat['sekretaris']['telepon'];
        echo "Update sekretaris desa: {$perangkat['sekretaris']['nama']} di $namaDesa\n";
    }
    
    // Lakukan update jika ada field yang perlu diupdate
    if (!empty($updateFields)) {
        try {
            $updateValues[] = $desaId;
            $updateSQL = "UPDATE desa SET " . implode(', ', $updateFields) . ", updated_at = NOW() WHERE id = ?";
            $updateStmt = $pdo->prepare($updateSQL);
            $updateStmt->execute($updateValues);
            $importCount++;
        } catch (PDOException $e) {
            $errorCount++;
            echo "Error update $namaDesa: " . $e->getMessage() . "\n";
        }
    } else {
        echo "Data sudah lengkap untuk $namaDesa\n";
        $duplicateCount++;
    }
}
    


echo "\n=== RINGKASAN IMPORT KECAMATAN SUSUKAN ===\n";
echo "Data imported: $importCount\n";
echo "Data sudah ada: $duplicateCount\n";
echo "Errors: $errorCount\n";
echo "Total desa: " . count($desaList) . "\n";
echo "Daftar desa: " . implode(', ', $desaList) . "\n";

// Cek status kontak person desa
echo "\n=== STATUS KONTAK PERSON DESA ===\n";
foreach ($desaList as $desa) {
    $desaStmt = $pdo->prepare("SELECT nama_kepala_desa, nama_sekdes, no_hp_sekdes FROM desa WHERE nama_desa = ? AND kecamatan = 'Susukan'");
    $desaStmt->execute([$desa]);
    $result = $desaStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        $status = [];
        if (empty($result['nama_kepala_desa'])) $status[] = 'Kepala Desa kosong';
        if (empty($result['nama_sekdes'])) $status[] = 'Sekretaris Desa kosong';
        if (empty($result['no_hp_sekdes'])) $status[] = 'No HP Sekdes kosong';
        
        if (empty($status)) {
            echo "✓ $desa: Kontak lengkap\n";
        } else {
            echo "⚠ $desa: " . implode(', ', $status) . "\n";
        }
    }
}

echo "\nSelesai!\n";
?>