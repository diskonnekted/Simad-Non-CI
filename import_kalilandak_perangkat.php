<?php
require_once 'config/database.php';

$db = getDatabase();
/** @var Database $db */

echo "Starting import of Kalilandak perangkat desa data...\n";

// Baca file CSV yang berisi data Kalilandak
$csv_file = 'csv/data-desa-fix2.csv';
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
    
    // Hanya proses data Kalilandak
    if (strtolower($nama_desa) !== 'kalilandak') {
        continue;
    }
    
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
    
    echo "Processing: $nama_lengkap - $jabatan\n";
    
    try {
        // Cari desa_id berdasarkan nama desa dan kecamatan
        // Handle perbedaan nama kecamatan antara CSV dan database
        $kecamatan_db = $kecamatan;
        if (strtolower($kecamatan) === 'purwareja klampok') {
            $kecamatan_db = 'Klampok';
        }
        
        $desa_key = strtolower($nama_desa . '_' . $kecamatan_db);
        if (!isset($desa_cache[$desa_key])) {
            $desa_result = $db->select("SELECT id FROM desa WHERE LOWER(nama_desa) = ? AND LOWER(kecamatan) = ? LIMIT 1", [strtolower($nama_desa), strtolower($kecamatan_db)]);
            if (!empty($desa_result)) {
                $desa_cache[$desa_key] = $desa_result[0]['id'];
                echo "Found desa_id: " . $desa_result[0]['id'] . " for $nama_desa, $kecamatan_db\n";
            } else {
                echo "Desa tidak ditemukan: $nama_desa di kecamatan $kecamatan_db (original: $kecamatan)\n";
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
                } elseif (preg_match('/(\d{1,2})\s+(\w+)\s*(\d{4})/', $tanggal_str, $matches)) {
                    // Handle format like "03 Mei1990"
                    $bulan_map = [
                        'januari' => '01', 'februari' => '02', 'maret' => '03', 'april' => '04',
                        'mei' => '05', 'juni' => '06', 'juli' => '07', 'agustus' => '08',
                        'september' => '09', 'oktober' => '10', 'november' => '11', 'desember' => '12'
                    ];
                    $bulan_nama = strtolower($matches[2]);
                    if (isset($bulan_map[$bulan_nama])) {
                        $tanggal_lahir = $matches[3] . '-' . $bulan_map[$bulan_nama] . '-' . str_pad($matches[1], 2, '0', STR_PAD_LEFT);
                    }
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
            echo "Imported: $nama_lengkap\n";
        } else {
            echo "Already exists: $nama_lengkap\n";
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

// Tampilkan data perangkat Kalilandak yang berhasil diimport
echo "\nData perangkat Kalilandak:\n";
$kalilandak_perangkat = $db->select("
    SELECT pd.nama_lengkap, pd.jabatan, pd.no_telepon
    FROM perangkat_desa pd
    JOIN desa d ON pd.desa_id = d.id
    WHERE LOWER(d.nama_desa) = 'kalilandak'
    ORDER BY pd.jabatan
");

foreach ($kalilandak_perangkat as $perangkat) {
    echo $perangkat['nama_lengkap'] . " - " . $perangkat['jabatan'] . " - " . ($perangkat['no_telepon'] ?: 'No telepon tidak ada') . "\n";
}
?>