<?php
require_once 'config/database.php';

// Inisialisasi koneksi database
$db = new Database();
$pdo = $db->getConnection();

// Daftar file CSV dan desa yang akan diimport
$csv_files = [
    'csv/data-desa-fix2.csv' => ['Kalimandi'],
    'csv/data-desa-fix3.csv' => ['Kaliwinasuh'],
    'csv/data-desa-fix4.csv' => ['Kecitran'],
    'csv/data-desa-fix5.csv' => ['Pagak'],
    'csv/data-desa-fix7.csv' => ['Purwareja'],
    'csv/data-desa-fix8.csv' => ['Sirkandi']
];

$total_imported = 0;
$total_errors = 0;
$kecamatan_target = 'Klampok';

echo "=== IMPORT PERANGKAT DESA KECAMATAN KLAMPOK ===\n";
echo "Tanggal: " . date('Y-m-d H:i:s') . "\n\n";

foreach ($csv_files as $csv_file => $desa_list) {
    if (!file_exists($csv_file)) {
        echo "File tidak ditemukan: $csv_file\n";
        continue;
    }
    
    echo "Memproses file: $csv_file\n";
    
    $file = fopen($csv_file, 'r');
    $header = fgetcsv($file); // Skip header
    $imported_count = 0;
    $error_count = 0;
    
    while (($data = fgetcsv($file)) !== FALSE) {
        if (count($data) < 11) continue;
        
        $nama_desa = trim($data[1]);
        $kecamatan = trim($data[2]);
        $nama_lengkap = trim($data[3]);
        $ttl = trim($data[4]);
        $alamat = trim($data[5]);
        $telepon = trim($data[6]);
        $pendidikan = trim($data[7]);
        $diangkat = trim($data[8]);
        $no_sk = trim($data[9]);
        $jabatan = trim($data[10]);
        
        // Skip jika bukan desa target
        if (!in_array($nama_desa, $desa_list)) {
            continue;
        }
        
        // Normalisasi nama kecamatan
        $kecamatan_normalized = ($kecamatan == 'Purwareja Klampok') ? 'Klampok' : $kecamatan;
        
        // Cari desa_id
        $stmt = $pdo->prepare("SELECT id FROM desa WHERE nama_desa = ? AND kecamatan = ?");
        $stmt->execute([$nama_desa, $kecamatan_normalized]);
        $desa_row = $stmt->fetch();
        
        if (!$desa_row) {
            echo "  ERROR: Desa $nama_desa di kecamatan $kecamatan_normalized tidak ditemukan\n";
            $error_count++;
            continue;
        }
        
        $desa_id = $desa_row['id'];
        
        // Parse tanggal lahir
        $tanggal_lahir = null;
        if (!empty($ttl)) {
            $ttl_parts = explode(',', $ttl);
            if (count($ttl_parts) >= 2) {
                $tanggal_str = trim($ttl_parts[1]);
                $tanggal_lahir = date('Y-m-d', strtotime($tanggal_str));
                if ($tanggal_lahir == '1970-01-01') {
                    $tanggal_lahir = null;
                }
            }
        }
        
        // Cek apakah data sudah ada
        $stmt = $pdo->prepare("SELECT id FROM perangkat_desa WHERE desa_id = ? AND nama_lengkap = ? AND jabatan = ?");
        $stmt->execute([$desa_id, $nama_lengkap, $jabatan]);
        if ($stmt->fetch()) {
            echo "  SKIP: $nama_lengkap ($jabatan) sudah ada di $nama_desa\n";
            continue;
        }
        
        // Insert data perangkat
        try {
            $stmt = $pdo->prepare("
                INSERT INTO perangkat_desa 
                (desa_id, nama_lengkap, tempat_lahir, tanggal_lahir, alamat, no_telepon, pendidikan, tahun_diangkat, no_sk, jabatan, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $tempat_lahir = '';
            if (!empty($ttl)) {
                $ttl_parts = explode(',', $ttl);
                if (count($ttl_parts) >= 1) {
                    $tempat_lahir = trim($ttl_parts[0]);
                }
            }
            
            $stmt->execute([
                $desa_id,
                $nama_lengkap,
                $tempat_lahir,
                $tanggal_lahir,
                $alamat,
                $telepon,
                $pendidikan,
                $diangkat,
                $no_sk,
                $jabatan
            ]);
            
            echo "  IMPORTED: $nama_lengkap ($jabatan) - $nama_desa\n";
            $imported_count++;
            
        } catch (Exception $e) {
            echo "  ERROR: Gagal import $nama_lengkap - " . $e->getMessage() . "\n";
            $error_count++;
        }
    }
    
    fclose($file);
    echo "  File $csv_file: $imported_count imported, $error_count errors\n\n";
    
    $total_imported += $imported_count;
    $total_errors += $error_count;
}

echo "=== RINGKASAN IMPORT ===\n";
echo "Total data imported: $total_imported\n";
echo "Total errors: $total_errors\n";
echo "Selesai pada: " . date('Y-m-d H:i:s') . "\n";

// Tampilkan daftar desa yang sudah diimport
echo "\n=== DESA YANG SUDAH DIIMPORT ===\n";
$stmt = $pdo->prepare("
    SELECT d.nama_desa, COUNT(p.id) as jumlah_perangkat 
    FROM desa d 
    LEFT JOIN perangkat_desa p ON d.id = p.desa_id 
    WHERE d.kecamatan = ? 
    GROUP BY d.id, d.nama_desa 
    ORDER BY d.nama_desa
");
$stmt->execute([$kecamatan_target]);

while ($row = $stmt->fetch()) {
    echo "- {$row['nama_desa']}: {$row['jumlah_perangkat']} perangkat\n";
}

echo "\n=== SELESAI ===\n";
?>