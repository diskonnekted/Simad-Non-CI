<?php
require_once 'config/database.php';

/**
 * Script untuk menguji perbaikan duplikasi kepala desa
 * Memverifikasi bahwa hanya 1 kepala desa yang ditampilkan per desa
 */

echo "=== TEST PERBAIKAN DUPLIKASI KEPALA DESA ===\n";
echo "Tanggal: " . date('Y-m-d H:i:s') . "\n\n";

// Fungsi getPerangkatDesa yang sudah diperbaiki (copy dari desa-view.php)
function getPerangkatDesa($nama_desa, $desa_id = null) {
    global $conn;
    $perangkat = [];
    
    try {
        // Prioritas 1: Ambil dari database jika desa_id tersedia
        if ($desa_id && $conn) {
            $stmt = $conn->prepare("SELECT nama_lengkap, jabatan, tempat_lahir, tanggal_lahir, alamat, no_telepon, pendidikan, tahun_diangkat, no_sk FROM perangkat_desa WHERE desa_id = ? AND status = 'aktif' ORDER BY CASE jabatan WHEN 'Kepala Desa' THEN 1 WHEN 'Sekretaris Desa' THEN 2 WHEN 'Kepala Dusun I' THEN 3 WHEN 'Kepala Dusun II' THEN 4 WHEN 'Anggota BPD' THEN 5 ELSE 6 END, nama_lengkap");
            $stmt->bind_param("i", $desa_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $kepala_desa_found = false; // Flag untuk memastikan hanya 1 kepala desa
            
            while ($row = $result->fetch_assoc()) {
                $ttl = '';
                if ($row['tempat_lahir'] && $row['tanggal_lahir']) {
                    $ttl = $row['tempat_lahir'] . ', ' . date('d-m-Y', strtotime($row['tanggal_lahir']));
                }
                
                // Cek jika ini kepala desa
                $is_kepala_desa = (stripos($row['jabatan'], 'kepala desa') !== false || stripos($row['jabatan'], 'kades') !== false);
                
                // Jika sudah ada kepala desa dan ini juga kepala desa, skip
                if ($is_kepala_desa && $kepala_desa_found) {
                    error_log("Warning: Duplikasi kepala desa ditemukan untuk desa ID {$desa_id}: {$row['nama_lengkap']}");
                    continue;
                }
                
                if ($is_kepala_desa) {
                    $kepala_desa_found = true;
                }
                
                $perangkat[] = [
                    'nama_lengkap' => $row['nama_lengkap'],
                    'jabatan' => $row['jabatan'],
                    'telepon' => $row['no_telepon'],
                    'alamat' => $row['alamat'],
                    'ttl' => $ttl,
                    'pendidikan' => $row['pendidikan'],
                    'tahun_diangkat' => $row['tahun_diangkat'],
                    'no_sk' => $row['no_sk']
                ];
            }
            $stmt->close();
            
            // Jika data ditemukan di database, return langsung
            if (!empty($perangkat)) {
                return $perangkat;
            }
        }
        
        // Prioritas 2: Fallback ke CSV jika tidak ada data di database
        $csv_file = 'data-desa.csv';
        $nama_desa_clean = trim(preg_replace('/^desa\s+/i', '', $nama_desa));
        
        if (file_exists($csv_file)) {
            $handle = fopen($csv_file, 'r');
            $header = fgetcsv($handle); // Skip header
            
            $kepala_desa_found_csv = false; // Flag untuk CSV juga
            
            while (($data = fgetcsv($handle)) !== FALSE) {
                // Kolom CSV: No, Nama Desa, Kecamatan, Nama Lengkap, TTL, Alamat, Telepon, Pendidikan, Diangkat, No SK, Jabatan
                // Hilangkan kata "Desa" dari data CSV juga untuk perbandingan
                $csv_nama_desa = trim(preg_replace('/^desa\s+/i', '', trim($data[1])));
                if (count($data) >= 11 && strtolower($csv_nama_desa) === strtolower($nama_desa_clean)) {
                    
                    $jabatan = trim($data[10]);
                    $is_kepala_desa_csv = (stripos($jabatan, 'kepala desa') !== false || stripos($jabatan, 'kades') !== false);
                    
                    // Jika sudah ada kepala desa dan ini juga kepala desa, skip
                    if ($is_kepala_desa_csv && $kepala_desa_found_csv) {
                        error_log("Warning: Duplikasi kepala desa ditemukan di CSV untuk desa {$nama_desa}: {$data[3]}");
                        continue;
                    }
                    
                    if ($is_kepala_desa_csv) {
                        $kepala_desa_found_csv = true;
                    }
                    
                    $perangkat[] = [
                        'nama_lengkap' => trim($data[3]),
                        'jabatan' => $jabatan,
                        'telepon' => trim($data[6]),
                        'alamat' => trim($data[5]),
                        'ttl' => trim($data[4]),
                        'pendidikan' => trim($data[7]),
                        'tahun_diangkat' => trim($data[8]),
                        'no_sk' => trim($data[9])
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
            'kepala desa' => 1,
            'sekretaris desa' => 2,
            'kasi' => 3,
            'kaur' => 4,
            'kadus' => 5,
            'kepala dusun' => 5,
            'staf' => 6
        ];
        
        $a_priority = 999;
        $b_priority = 999;
        
        foreach ($priority as $key => $value) {
            if (stripos($a['jabatan'], $key) !== false) {
                $a_priority = $value;
                break;
            }
        }
        
        foreach ($priority as $key => $value) {
            if (stripos($b['jabatan'], $key) !== false) {
                $b_priority = $value;
                break;
            }
        }
        
        return $a_priority - $b_priority;
    });
    
    return $perangkat;
}

// Test dengan beberapa desa yang berpotensi memiliki duplikasi
$test_desa = [
    ['id' => 13, 'nama' => 'Bandingan', 'keterangan' => 'Desa yang dilaporkan user'],
    ['id' => 1, 'nama' => 'Kecitran', 'keterangan' => 'Desa dengan duplikasi nama perangkat'],
    ['id' => 50, 'nama' => 'Gununggiana', 'keterangan' => 'Desa dengan duplikasi nama'],
    ['id' => 100, 'nama' => 'Karangnangka', 'keterangan' => 'Desa dengan duplikasi nama'],
    ['id' => 150, 'nama' => 'Pandanarum', 'keterangan' => 'Desa dengan duplikasi nama']
];

echo "1. TEST FUNGSI getPerangkatDesa YANG DIPERBAIKI\n";
echo "===============================================\n";

$total_test = 0;
$test_berhasil = 0;

foreach ($test_desa as $desa) {
    $total_test++;
    echo "\nTest #{$total_test}: {$desa['nama']} (ID: {$desa['id']}) - {$desa['keterangan']}\n";
    echo str_repeat('-', 60) . "\n";
    
    try {
        // Ambil data perangkat desa
        $perangkat = getPerangkatDesa($desa['nama'], $desa['id']);
        
        echo "Total perangkat: " . count($perangkat) . "\n";
        
        // Hitung jumlah kepala desa
        $kepala_count = 0;
        $kepala_desa_list = [];
        
        foreach ($perangkat as $p) {
            if (stripos($p['jabatan'], 'kepala desa') !== false || stripos($p['jabatan'], 'kades') !== false) {
                $kepala_count++;
                $kepala_desa_list[] = $p['nama_lengkap'] . ' - ' . $p['jabatan'];
            }
        }
        
        echo "Jumlah kepala desa: {$kepala_count}\n";
        
        if ($kepala_count > 0) {
            echo "Daftar kepala desa:\n";
            foreach ($kepala_desa_list as $i => $kepala) {
                echo "  " . ($i + 1) . ". {$kepala}\n";
            }
        }
        
        // Evaluasi hasil
        if ($kepala_count <= 1) {
            echo "✓ TEST BERHASIL: Hanya {$kepala_count} kepala desa ditampilkan\n";
            $test_berhasil++;
        } else {
            echo "❌ TEST GAGAL: Masih ada {$kepala_count} kepala desa (seharusnya maksimal 1)\n";
        }
        
    } catch (Exception $e) {
        echo "❌ ERROR: " . $e->getMessage() . "\n";
    }
}

echo "\n" . str_repeat('=', 60) . "\n";
echo "RINGKASAN TEST:\n";
echo "Total test: {$total_test}\n";
echo "Test berhasil: {$test_berhasil}\n";
echo "Test gagal: " . ($total_test - $test_berhasil) . "\n";
echo "Tingkat keberhasilan: " . round(($test_berhasil / $total_test) * 100, 2) . "%\n\n";

// Test khusus untuk desa Bandingan Bawang
echo "2. TEST KHUSUS DESA BANDINGAN BAWANG\n";
echo "====================================\n";

try {
    // Query langsung ke database untuk melihat data mentah
    $stmt = $conn->prepare("SELECT nama_lengkap, jabatan FROM perangkat_desa WHERE desa_id = 13 AND status = 'aktif' AND (jabatan LIKE '%kepala desa%' OR jabatan LIKE '%kades%') ORDER BY nama_lengkap");
    $stmt->execute();
    $result = $stmt->get_result();
    
    echo "Data kepala desa di database (desa_id = 13):\n";
    $db_kepala_count = 0;
    while ($row = $result->fetch_assoc()) {
        $db_kepala_count++;
        echo "  {$db_kepala_count}. {$row['nama_lengkap']} - {$row['jabatan']}\n";
    }
    $stmt->close();
    
    echo "\nTotal kepala desa di database: {$db_kepala_count}\n";
    
    // Test fungsi getPerangkatDesa
    $perangkat_bandingan = getPerangkatDesa('Bandingan', 13);
    $kepala_tampil = 0;
    
    echo "\nKepala desa yang ditampilkan oleh fungsi getPerangkatDesa:\n";
    foreach ($perangkat_bandingan as $p) {
        if (stripos($p['jabatan'], 'kepala desa') !== false || stripos($p['jabatan'], 'kades') !== false) {
            $kepala_tampil++;
            echo "  {$kepala_tampil}. {$p['nama_lengkap']} - {$p['jabatan']}\n";
        }
    }
    
    echo "\nTotal kepala desa yang ditampilkan: {$kepala_tampil}\n";
    
    if ($kepala_tampil <= 1) {
        echo "✓ PERBAIKAN BERHASIL: Duplikasi kepala desa sudah teratasi\n";
    } else {
        echo "❌ PERBAIKAN GAGAL: Masih ada duplikasi kepala desa\n";
    }
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}

echo "\n3. REKOMENDASI LANJUTAN\n";
echo "======================\n";
echo "1. Monitor log error untuk melihat warning duplikasi\n";
echo "2. Lakukan pembersihan data duplikasi di database secara berkala\n";
echo "3. Implementasi validasi di form input perangkat desa\n";
echo "4. Tambahkan constraint UNIQUE di database untuk mencegah duplikasi\n";
echo "5. Review data CSV untuk memastikan tidak ada duplikasi\n\n";

echo "=== TEST SELESAI ===\n";

?>