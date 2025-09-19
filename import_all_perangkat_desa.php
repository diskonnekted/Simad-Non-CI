<?php
require_once 'config/database.php';

$db = getDatabase();
/** @var Database $db */

echo "Starting import of all perangkat desa data...\n";

// Baca file CSV
$csv_file = 'csv/data-desa-fix9.csv';
if (!file_exists($csv_file)) {
    die("File CSV tidak ditemukan: $csv_file\n");
}

$handle = fopen($csv_file, 'r');
$header = fgetcsv($handle); // Skip header

$imported_count = 0;
$error_count = 0;
$desa_cache = [];

while (($data = fgetcsv($handle)) !== FALSE) {
    if (count($data) < 11) {
        continue; // Skip incomplete rows
    }
    
    $nama_desa_raw = trim($data[1]);
    // Hilangkan spasi di awal dan kata "Desa" dari nama desa untuk mencocokkan dengan database
    $nama_desa = ltrim($nama_desa_raw); // Hilangkan spasi di awal terlebih dahulu
    $nama_desa = str_ireplace('Desa ', '', $nama_desa);
    $nama_desa = trim($nama_desa);
    
    $kecamatan = trim($data[2]);
    $nama_lengkap = trim($data[3]);
    $ttl = trim($data[4]);
    $alamat = trim($data[5]);
    $no_telepon = trim($data[6]);
    $pendidikan = trim($data[7]);
    $tahun_diangkat = trim($data[8]);
    $no_sk = trim($data[9]);
    $jabatan = trim($data[10]);
    
    // Skip jika data kosong
    if (empty($nama_lengkap) || empty($jabatan)) {
        continue;
    }
    
    try {
        // Cari desa_id berdasarkan nama desa dan kecamatan untuk menghindari duplikat
        // Normalisasi nama kecamatan untuk konsistensi
        $kecamatan_normalized = $kecamatan;
        if (strtolower($kecamatan) === 'purwareja klampok') {
            $kecamatan_normalized = 'Klampok';
        }
        
        $desa_key = strtolower($nama_desa . '_' . $kecamatan_normalized);
        if (!isset($desa_cache[$desa_key])) {
            $desa_result = $db->select("SELECT id FROM desa WHERE LOWER(nama_desa) = ? AND LOWER(kecamatan) = ? LIMIT 1", [strtolower($nama_desa), strtolower($kecamatan_normalized)]);
            if (!empty($desa_result)) {
                $desa_cache[$desa_key] = $desa_result[0]['id'];
            } else {
                echo "Desa tidak ditemukan: $nama_desa di kecamatan $kecamatan_normalized\n";
                $error_count++;
                continue;
            }
        }
        
        $desa_id = $desa_cache[$desa_key];
        
        // Parse tanggal lahir dari TTL
        $tempat_lahir = '';
        $tanggal_lahir = null;
        if (!empty($ttl)) {
            $ttl_parts = explode(',', $ttl);
            if (count($ttl_parts) >= 2) {
                $tempat_lahir = trim($ttl_parts[0]);
                $tanggal_str = trim($ttl_parts[1]);
                
                // Convert date format from DD-MM-YYYY to YYYY-MM-DD
                if (preg_match('/^(\d{1,2})-(\d{1,2})-(\d{4})$/', $tanggal_str, $matches)) {
                    $tanggal_lahir = $matches[3] . '-' . str_pad($matches[2], 2, '0', STR_PAD_LEFT) . '-' . str_pad($matches[1], 2, '0', STR_PAD_LEFT);
                }
            }
        }
        
        // Cek apakah data sudah ada
        $existing = $db->select(
            "SELECT id FROM perangkat_desa WHERE desa_id = ? AND nama_lengkap = ? AND jabatan = ? LIMIT 1",
            [$desa_id, $nama_lengkap, $jabatan]
        );
        
        if (empty($existing)) {
            // Insert data baru
            $db->execute(
                "INSERT INTO perangkat_desa (desa_id, nama_lengkap, jabatan, tempat_lahir, tanggal_lahir, alamat, no_telepon, pendidikan, tahun_diangkat, no_sk, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'aktif')",
                [
                    $desa_id,
                    $nama_lengkap,
                    $jabatan,
                    $tempat_lahir,
                    $tanggal_lahir,
                    $alamat,
                    $no_telepon,
                    $pendidikan,
                    $tahun_diangkat ?: null,
                    $no_sk
                ]
            );
            $imported_count++;
            
            if ($imported_count % 100 == 0) {
                echo "Imported $imported_count records...\n";
            }
        }
        
    } catch (Exception $e) {
        echo "Error importing $nama_lengkap from $nama_desa: " . $e->getMessage() . "\n";
        $error_count++;
    }
}

fclose($handle);

echo "\nImport completed!\n";
echo "Total imported: $imported_count\n";
echo "Total errors: $error_count\n";

// Tampilkan statistik per desa
echo "\nStatistik per desa:\n";
$stats = $db->select("
    SELECT d.nama_desa, COUNT(pd.id) as jumlah_perangkat
    FROM desa d
    LEFT JOIN perangkat_desa pd ON d.id = pd.desa_id
    GROUP BY d.id, d.nama_desa
    ORDER BY d.nama_desa
");

foreach ($stats as $stat) {
    echo $stat['nama_desa'] . ": " . $stat['jumlah_perangkat'] . " perangkat\n";
}
?>