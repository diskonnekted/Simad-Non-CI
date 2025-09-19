<?php
require_once 'config/database.php';

/**
 * Script untuk memperbaiki fungsi getPerangkatDesa
 * Menambahkan validasi untuk mencegah duplikasi kepala desa
 */

echo "=== PERBAIKAN FUNGSI getPerangkatDesa ===\n";
echo "Tanggal: " . date('Y-m-d H:i:s') . "\n\n";

// Backup file asli
$original_file = 'desa-view.php';
$backup_file = 'desa-view.php.backup.' . date('Y-m-d_H-i-s');

if (file_exists($original_file)) {
    copy($original_file, $backup_file);
    echo "✓ Backup dibuat: {$backup_file}\n\n";
}

// Baca file asli
$content = file_get_contents($original_file);

// Fungsi getPerangkatDesa yang diperbaiki
$new_function = '
    // Fungsi untuk membaca data perangkat desa dari CSV
    function getPerangkatDesa($nama_desa, $desa_id = null) {
        global $conn;
        $perangkat = [];
        
        try {
            // Prioritas 1: Ambil dari database jika desa_id tersedia
            if ($desa_id && $conn) {
                $stmt = $conn->prepare("SELECT nama_lengkap, jabatan, tempat_lahir, tanggal_lahir, alamat, no_telepon, pendidikan, tahun_diangkat, no_sk FROM perangkat_desa WHERE desa_id = ? AND status = \'aktif\' ORDER BY CASE jabatan WHEN \'Kepala Desa\' THEN 1 WHEN \'KEPALA DESA\' THEN 1 WHEN \'Sekretaris Desa\' THEN 2 WHEN \'SEKRETARIS DESA\' THEN 2 WHEN \'Kepala Dusun I\' THEN 3 WHEN \'Kepala Dusun II\' THEN 4 WHEN \'Anggota BPD\' THEN 5 ELSE 6 END, nama_lengkap");
                $stmt->bind_param("i", $desa_id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                $kepala_desa_found = false; // Flag untuk memastikan hanya 1 kepala desa
                
                while ($row = $result->fetch_assoc()) {
                    $ttl = \'\';
                    if ($row[\'tempat_lahir\'] && $row[\'tanggal_lahir\']) {
                        $ttl = $row[\'tempat_lahir\'] . \', \' . date(\'d-m-Y\', strtotime($row[\'tanggal_lahir\']));
                    }
                    
                    // Cek jika ini kepala desa
                    $is_kepala_desa = (stripos($row[\'jabatan\'], \'kepala desa\') !== false || stripos($row[\'jabatan\'], \'kades\') !== false);
                    
                    // Jika sudah ada kepala desa dan ini juga kepala desa, skip
                    if ($is_kepala_desa && $kepala_desa_found) {
                        error_log("Warning: Duplikasi kepala desa ditemukan untuk desa ID {$desa_id}: {$row[\'nama_lengkap\']}");
                        continue;
                    }
                    
                    if ($is_kepala_desa) {
                        $kepala_desa_found = true;
                    }
                    
                    $perangkat[] = [
                        \'nama_lengkap\' => $row[\'nama_lengkap\'],
                        \'jabatan\' => $row[\'jabatan\'],
                        \'telepon\' => $row[\'no_telepon\'],
                        \'alamat\' => $row[\'alamat\'],
                        \'ttl\' => $ttl,
                        \'pendidikan\' => $row[\'pendidikan\'],
                        \'tahun_diangkat\' => $row[\'tahun_diangkat\'],
                        \'no_sk\' => $row[\'no_sk\']
                    ];
                }
                $stmt->close();
                
                // Jika data ditemukan di database, return langsung
                if (!empty($perangkat)) {
                    return $perangkat;
                }
            }
            
            // Prioritas 2: Fallback ke CSV jika tidak ada data di database
            $csv_file = \'data-desa.csv\';
            $nama_desa_clean = trim(preg_replace(\'/^desa\\s+/i\', \'\', $nama_desa));
            
            if (file_exists($csv_file)) {
                $handle = fopen($csv_file, \'r\');
                $header = fgetcsv($handle); // Skip header
                
                $kepala_desa_found = false; // Flag untuk CSV juga
                
                while (($data = fgetcsv($handle)) !== FALSE) {
                    // Kolom CSV: No, Nama Desa, Kecamatan, Nama Lengkap, TTL, Alamat, Telepon, Pendidikan, Diangkat, No SK, Jabatan
                    // Hilangkan kata "Desa" dari data CSV juga untuk perbandingan
                    $csv_nama_desa = trim(preg_replace(\'/^desa\\s+/i\', \'\', trim($data[1])));
                    if (count($data) >= 11 && strtolower($csv_nama_desa) === strtolower($nama_desa_clean)) {
                        
                        $jabatan = trim($data[10]);
                        $is_kepala_desa = (stripos($jabatan, \'kepala desa\') !== false || stripos($jabatan, \'kades\') !== false);
                        
                        // Jika sudah ada kepala desa dan ini juga kepala desa, skip
                        if ($is_kepala_desa && $kepala_desa_found) {
                            error_log("Warning: Duplikasi kepala desa ditemukan di CSV untuk desa {$nama_desa}: {$data[3]}");
                            continue;
                        }
                        
                        if ($is_kepala_desa) {
                            $kepala_desa_found = true;
                        }
                        
                        $perangkat[] = [
                            \'nama_lengkap\' => trim($data[3]),
                            \'jabatan\' => $jabatan,
                            \'telepon\' => trim($data[6]),
                            \'alamat\' => trim($data[5]),
                            \'ttl\' => trim($data[4]),
                            \'pendidikan\' => trim($data[7]),
                            \'tahun_diangkat\' => trim($data[8]),
                            \'no_sk\' => trim($data[9])
                        ];
                    }
                }
                fclose($handle);
            }
        } catch (Exception $e) {
            error_log("Error in getPerangkatDesa: " . $e->getMessage());
        }
        
        // Urutkan berdasarkan jabatan (Kepala Desa, Sekretaris Desa, dll)
        usort($perangkat, function($a, $b) {
            $priority = [
                \'kepala desa\' => 1,
                \'sekretaris desa\' => 2,
                \'kasi\' => 3,
                \'kaur\' => 4,
                \'kadus\' => 5,
                \'kepala dusun\' => 5,
                \'staf\' => 6
            ];
            
            $a_priority = 999;
            $b_priority = 999;
            
            foreach ($priority as $key => $value) {
                if (stripos($a[\'jabatan\'], $key) !== false) {
                    $a_priority = $value;
                    break;
                }
            }
            
            foreach ($priority as $key => $value) {
                if (stripos($b[\'jabatan\'], $key) !== false) {
                    $b_priority = $value;
                    break;
                }
            }
            
            return $a_priority - $b_priority;
        });
        
        return $perangkat;
    }';

// Cari dan ganti fungsi lama
$pattern = '/\/\/ Fungsi untuk membaca data perangkat desa dari CSV\s*function getPerangkatDesa\([^}]+\}(?:\s*\})*/';

if (preg_match($pattern, $content)) {
    $new_content = preg_replace($pattern, $new_function, $content);
    
    // Simpan file yang sudah diperbaiki
    if (file_put_contents($original_file, $new_content)) {
        echo "✓ Fungsi getPerangkatDesa berhasil diperbaiki\n";
        echo "✓ File {$original_file} telah diupdate\n\n";
        
        echo "PERBAIKAN YANG DILAKUKAN:\n";
        echo "========================\n";
        echo "1. Menambahkan flag \$kepala_desa_found untuk mencegah duplikasi\n";
        echo "2. Validasi kepala desa di database query\n";
        echo "3. Validasi kepala desa di CSV parsing\n";
        echo "4. Logging warning jika duplikasi ditemukan\n";
        echo "5. Skip record duplikat kepala desa\n\n";
        
        echo "FITUR BARU:\n";
        echo "===========\n";
        echo "- Hanya 1 kepala desa yang akan ditampilkan per desa\n";
        echo "- Warning log jika ada duplikasi ditemukan\n";
        echo "- Prioritas kepala desa pertama yang ditemukan\n";
        echo "- Kompatibel dengan data database dan CSV\n\n";
        
    } else {
        echo "❌ Gagal menyimpan file\n";
    }
} else {
    echo "❌ Fungsi getPerangkatDesa tidak ditemukan dalam file\n";
    echo "Pattern yang dicari: {$pattern}\n";
}

// Test fungsi yang sudah diperbaiki
echo "TEST FUNGSI YANG DIPERBAIKI:\n";
echo "============================\n";

try {
    // Include file yang sudah diperbaiki untuk test
    include_once $original_file;
    
    // Test dengan desa Bandingan Bawang
    echo "Test dengan desa Bandingan Bawang (ID 13):\n";
    
    // Simulasi koneksi database untuk test
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
    $perangkat_test = getPerangkatDesa('Bandingan', 13);
    
    echo "Total perangkat: " . count($perangkat_test) . "\n";
    
    $kepala_count = 0;
    foreach ($perangkat_test as $p) {
        if (stripos($p['jabatan'], 'kepala desa') !== false) {
            $kepala_count++;
            echo "Kepala Desa #{$kepala_count}: {$p['nama_lengkap']} - {$p['jabatan']}\n";
        }
    }
    
    if ($kepala_count <= 1) {
        echo "✓ Test berhasil: Hanya {$kepala_count} kepala desa ditampilkan\n";
    } else {
        echo "⚠ Test gagal: Masih ada {$kepala_count} kepala desa\n";
    }
    
    $conn->close();
    
} catch (Exception $e) {
    echo "Error saat test: " . $e->getMessage() . "\n";
}

echo "\n=== PERBAIKAN SELESAI ===\n";
echo "File backup tersimpan di: {$backup_file}\n";
echo "Silakan test halaman desa-view.php untuk memastikan perbaikan bekerja\n";

?>