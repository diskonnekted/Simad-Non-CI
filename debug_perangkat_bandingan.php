<?php
require_once 'config/database.php';

/**
 * Script untuk debug data perangkat desa Bandingan
 * Memeriksa semua data perangkat yang ditampilkan
 */

echo "=== DEBUG PERANGKAT DESA BANDINGAN ===\n";
echo "Tanggal: " . date('Y-m-d H:i:s') . "\n\n";

try {
    $db = getDatabase();
    
    // 1. Cari semua desa Bandingan
    echo "1. DAFTAR DESA BANDINGAN\n";
    echo "========================\n";
    
    $desa_bandingan = $db->select("
        SELECT id, nama_desa, kecamatan, status
        FROM desa 
        WHERE LOWER(nama_desa) LIKE '%bandingan%'
        ORDER BY kecamatan, nama_desa
    ");
    
    foreach ($desa_bandingan as $desa) {
        echo "ID {$desa['id']}: {$desa['nama_desa']} - {$desa['kecamatan']} ({$desa['status']})\n";
    }
    echo "\n";
    
    // 2. Fokus pada Bandingan Bawang (ID 13)
    echo "2. DETAIL PERANGKAT BANDINGAN BAWANG (ID 13)\n";
    echo "============================================\n";
    
    $perangkat_db = $db->select("
        SELECT id, nama_lengkap, jabatan, tempat_lahir, tanggal_lahir, 
               alamat, no_telepon, pendidikan, tahun_diangkat, no_sk, 
               status, created_at, updated_at
        FROM perangkat_desa 
        WHERE desa_id = 13
        ORDER BY 
            CASE jabatan 
                WHEN 'KEPALA DESA' THEN 1 
                WHEN 'Kepala Desa' THEN 1
                WHEN 'kepala desa' THEN 1
                WHEN 'SEKRETARIS DESA' THEN 2 
                WHEN 'Sekretaris Desa' THEN 2
                ELSE 3 
            END, 
            nama_lengkap
    ");
    
    if (empty($perangkat_db)) {
        echo "⚠ Tidak ada data perangkat di database\n";
    } else {
        echo "Total perangkat di database: " . count($perangkat_db) . "\n\n";
        
        $kepala_desa_count = 0;
        
        foreach ($perangkat_db as $i => $perangkat) {
            echo "" . ($i + 1) . ". {$perangkat['nama_lengkap']}\n";
            echo "   ID: {$perangkat['id']}\n";
            echo "   Jabatan: '{$perangkat['jabatan']}'\n";
            echo "   Status: {$perangkat['status']}\n";
            echo "   Telepon: {$perangkat['no_telepon']}\n";
            echo "   Alamat: {$perangkat['alamat']}\n";
            echo "   Tahun Diangkat: {$perangkat['tahun_diangkat']}\n";
            echo "   No SK: {$perangkat['no_sk']}\n";
            echo "   Created: {$perangkat['created_at']}\n";
            echo "   Updated: {$perangkat['updated_at']}\n";
            
            // Hitung kepala desa
            if (stripos($perangkat['jabatan'], 'kepala desa') !== false) {
                $kepala_desa_count++;
                echo "   >>> INI KEPALA DESA (#{$kepala_desa_count}) <<<\n";
            }
            
            echo "\n";
        }
        
        echo "RINGKASAN:\n";
        echo "- Total perangkat: " . count($perangkat_db) . "\n";
        echo "- Jumlah Kepala Desa: {$kepala_desa_count}\n";
        
        if ($kepala_desa_count > 1) {
            echo "⚠ MASALAH: Ada {$kepala_desa_count} kepala desa!\n";
        } else {
            echo "✓ Normal: Hanya ada {$kepala_desa_count} kepala desa\n";
        }
    }
    
    // 3. Cek data dari CSV
    echo "\n3. DATA PERANGKAT DARI CSV\n";
    echo "=========================\n";
    
    $csv_file = 'data-desa.csv';
    if (file_exists($csv_file)) {
        $handle = fopen($csv_file, 'r');
        $header = fgetcsv($handle);
        $baris = 1;
        $perangkat_csv = [];
        
        while (($data = fgetcsv($handle)) !== FALSE) {
            $baris++;
            
            if (count($data) < 11) continue;
            
            $nama_desa_csv = trim(preg_replace('/^desa\s+/i', '', trim($data[1])));
            $kecamatan_csv = trim($data[2]);
            
            if (strtolower($nama_desa_csv) === 'bandingan' && strtolower($kecamatan_csv) === 'bawang') {
                $perangkat_csv[] = [
                    'baris' => $baris,
                    'nama' => trim($data[3]),
                    'jabatan' => trim($data[10]),
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
        
        if (empty($perangkat_csv)) {
            echo "⚠ Tidak ada data perangkat di CSV\n";
        } else {
            echo "Total perangkat di CSV: " . count($perangkat_csv) . "\n\n";
            
            $kepala_desa_csv_count = 0;
            
            foreach ($perangkat_csv as $i => $perangkat) {
                echo "" . ($i + 1) . ". {$perangkat['nama']} (Baris {$perangkat['baris']})\n";
                echo "   Jabatan: '{$perangkat['jabatan']}'\n";
                echo "   Telepon: {$perangkat['telepon']}\n";
                echo "   Alamat: {$perangkat['alamat']}\n";
                echo "   TTL: {$perangkat['ttl']}\n";
                echo "   Pendidikan: {$perangkat['pendidikan']}\n";
                echo "   Tahun Diangkat: {$perangkat['tahun_diangkat']}\n";
                echo "   No SK: {$perangkat['no_sk']}\n";
                
                // Hitung kepala desa
                if (stripos($perangkat['jabatan'], 'kepala desa') !== false) {
                    $kepala_desa_csv_count++;
                    echo "   >>> INI KEPALA DESA (#{$kepala_desa_csv_count}) <<<\n";
                }
                
                echo "\n";
            }
            
            echo "RINGKASAN CSV:\n";
            echo "- Total perangkat: " . count($perangkat_csv) . "\n";
            echo "- Jumlah Kepala Desa: {$kepala_desa_csv_count}\n";
            
            if ($kepala_desa_csv_count > 1) {
                echo "⚠ MASALAH: Ada {$kepala_desa_csv_count} kepala desa di CSV!\n";
            } else {
                echo "✓ Normal: Hanya ada {$kepala_desa_csv_count} kepala desa di CSV\n";
            }
        }
    } else {
        echo "⚠ File CSV tidak ditemukan\n";
    }
    
    // 4. Simulasi fungsi getPerangkatDesa
    echo "\n4. SIMULASI FUNGSI getPerangkatDesa()\n";
    echo "====================================\n";
    
    // Simulasi fungsi getPerangkatDesa untuk Bandingan
    function simulateGetPerangkatDesa($nama_desa, $desa_id, $db) {
        $perangkat = [];
        
        // Prioritas 1: Database
        if ($desa_id) {
            $result = $db->select("
                SELECT nama_lengkap, jabatan, tempat_lahir, tanggal_lahir, alamat, no_telepon, pendidikan, tahun_diangkat, no_sk 
                FROM perangkat_desa 
                WHERE desa_id = ? AND status = 'aktif' 
                ORDER BY CASE jabatan 
                    WHEN 'Kepala Desa' THEN 1 
                    WHEN 'KEPALA DESA' THEN 1
                    WHEN 'Sekretaris Desa' THEN 2 
                    WHEN 'SEKRETARIS DESA' THEN 2
                    ELSE 3 
                END, nama_lengkap
            ", [$desa_id]);
            
            foreach ($result as $row) {
                $ttl = '';
                if ($row['tempat_lahir'] && $row['tanggal_lahir']) {
                    $ttl = $row['tempat_lahir'] . ', ' . date('d-m-Y', strtotime($row['tanggal_lahir']));
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
            
            if (!empty($perangkat)) {
                return $perangkat;
            }
        }
        
        // Prioritas 2: CSV (jika database kosong)
        $csv_file = 'data-desa.csv';
        if (file_exists($csv_file)) {
            $handle = fopen($csv_file, 'r');
            $header = fgetcsv($handle);
            
            while (($data = fgetcsv($handle)) !== FALSE) {
                $csv_nama_desa = trim(preg_replace('/^desa\s+/i', '', trim($data[1])));
                if (count($data) >= 11 && strtolower($csv_nama_desa) === strtolower($nama_desa)) {
                    $perangkat[] = [
                        'nama_lengkap' => trim($data[3]),
                        'jabatan' => trim($data[10]),
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
        
        return $perangkat;
    }
    
    $hasil_simulasi = simulateGetPerangkatDesa('Bandingan', 13, $db);
    
    echo "Hasil simulasi getPerangkatDesa('Bandingan', 13):\n";
    echo "Total perangkat: " . count($hasil_simulasi) . "\n\n";
    
    $kepala_simulasi = 0;
    foreach ($hasil_simulasi as $i => $perangkat) {
        echo "" . ($i + 1) . ". {$perangkat['nama_lengkap']}\n";
        echo "   Jabatan: {$perangkat['jabatan']}\n";
        echo "   Telepon: {$perangkat['telepon']}\n";
        
        if (stripos($perangkat['jabatan'], 'kepala desa') !== false) {
            $kepala_simulasi++;
            echo "   >>> KEPALA DESA #{$kepala_simulasi} <<<\n";
        }
        echo "\n";
    }
    
    echo "KESIMPULAN SIMULASI:\n";
    echo "- Jumlah Kepala Desa yang akan ditampilkan: {$kepala_simulasi}\n";
    
    if ($kepala_simulasi > 1) {
        echo "⚠ MASALAH DITEMUKAN: Fungsi akan menampilkan {$kepala_simulasi} kepala desa!\n";
        echo "\nSOLUSI YANG DIPERLUKAN:\n";
        echo "1. Bersihkan data duplikat di database\n";
        echo "2. Atau tambahkan filter untuk hanya menampilkan 1 kepala desa\n";
    } else {
        echo "✓ Normal: Fungsi akan menampilkan {$kepala_simulasi} kepala desa\n";
    }
    
    echo "\n=== DEBUG SELESAI ===\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
?>