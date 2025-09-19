<?php
require_once 'config/database.php';

try {
    echo "=== ANALISIS DESA BANDINGAN YANG TERCAMPUR ===\n\n";
    
    $db = getDatabase();
    
    // 1. Cek data desa Bandingan di database
    echo "📊 DATA DESA BANDINGAN DI DATABASE:\n";
    echo str_repeat("=", 60) . "\n";
    
    $desa_bandingan_db = $db->select(
        "SELECT id, nama_desa, kecamatan, nama_kepala_desa, nama_sekdes, no_hp_kepala_desa, no_hp_sekdes
         FROM desa 
         WHERE nama_desa LIKE '%Bandingan%'
         ORDER BY kecamatan, nama_desa"
    );
    
    foreach ($desa_bandingan_db as $desa) {
        echo "🏛️  ID: {$desa['id']} - {$desa['nama_desa']} - Kec. {$desa['kecamatan']}\n";
        echo "   Kepala Desa: " . ($desa['nama_kepala_desa'] ?: 'KOSONG') . "\n";
        echo "   Sekretaris: " . ($desa['nama_sekdes'] ?: 'KOSONG') . "\n";
        echo "   HP Kepala: " . ($desa['no_hp_kepala_desa'] ?: 'KOSONG') . "\n";
        echo "   HP Sekdes: " . ($desa['no_hp_sekdes'] ?: 'KOSONG') . "\n\n";
    }
    
    // 2. Analisis data CSV
    echo "\n📋 ANALISIS DATA BANDINGAN DARI FILE CSV:\n";
    echo str_repeat("=", 60) . "\n";
    
    $csv_file = 'data-desa.csv';
    if (!file_exists($csv_file)) {
        echo "❌ File {$csv_file} tidak ditemukan!\n";
        exit(1);
    }
    
    $handle = fopen($csv_file, 'r');
    if (!$handle) {
        echo "❌ Tidak dapat membuka file {$csv_file}!\n";
        exit(1);
    }
    
    // Skip header
    $header = fgetcsv($handle);
    
    $bandingan_data = [];
    $baris_num = 1;
    
    // Baca semua data Bandingan dari CSV
    while (($data = fgetcsv($handle)) !== FALSE) {
        $baris_num++;
        
        if (count($data) < 11) continue;
        
        $nama_desa = isset($data[1]) ? trim($data[1]) : '';
        $kecamatan = isset($data[2]) ? trim($data[2]) : '';
        
        if (strtolower($nama_desa) === 'bandingan') {
            $bandingan_data[] = [
                'baris' => $baris_num,
                'id_csv' => isset($data[0]) ? trim($data[0]) : '',
                'nama_desa' => $nama_desa,
                'kecamatan' => $kecamatan,
                'nama_lengkap' => isset($data[3]) ? trim($data[3]) : '',
                'ttl' => isset($data[4]) ? trim($data[4]) : '',
                'alamat' => isset($data[5]) ? trim($data[5]) : '',
                'telepon' => isset($data[6]) ? trim($data[6]) : '',
                'pendidikan' => isset($data[7]) ? trim($data[7]) : '',
                'diangkat' => isset($data[8]) ? trim($data[8]) : '',
                'no_sk' => isset($data[9]) ? trim($data[9]) : '',
                'jabatan' => isset($data[10]) ? trim($data[10]) : ''
            ];
        }
    }
    
    fclose($handle);
    
    echo "📊 TOTAL DATA BANDINGAN DI CSV: " . count($bandingan_data) . " perangkat\n\n";
    
    // Kelompokkan per kecamatan
    $per_kecamatan = [];
    foreach ($bandingan_data as $data) {
        $kec = $data['kecamatan'];
        if (!isset($per_kecamatan[$kec])) {
            $per_kecamatan[$kec] = [];
        }
        $per_kecamatan[$kec][] = $data;
    }
    
    // Tampilkan data per kecamatan
    foreach ($per_kecamatan as $kecamatan => $perangkat_list) {
        echo "🏛️  BANDINGAN KECAMATAN {$kecamatan} (" . count($perangkat_list) . " perangkat)\n";
        echo str_repeat("-", 50) . "\n";
        
        // Cari kepala desa dan sekdes
        $kepala_desa = null;
        $sekdes = null;
        
        foreach ($perangkat_list as $perangkat) {
            $jabatan = strtolower($perangkat['jabatan']);
            
            if (strpos($jabatan, 'kepala desa') !== false) {
                $kepala_desa = $perangkat;
            }
            if (strpos($jabatan, 'sekretaris desa') !== false || strpos($jabatan, 'sekdes') !== false) {
                $sekdes = $perangkat;
            }
        }
        
        echo "   👤 KEPALA DESA:\n";
        if ($kepala_desa) {
            echo "      Nama: {$kepala_desa['nama_lengkap']}\n";
            echo "      HP: {$kepala_desa['telepon']}\n";
            echo "      Alamat: {$kepala_desa['alamat']}\n";
            echo "      Baris CSV: {$kepala_desa['baris']}\n";
        } else {
            echo "      ❌ TIDAK DITEMUKAN\n";
        }
        
        echo "\n   📝 SEKRETARIS DESA:\n";
        if ($sekdes) {
            echo "      Nama: {$sekdes['nama_lengkap']}\n";
            echo "      HP: {$sekdes['telepon']}\n";
            echo "      Alamat: {$sekdes['alamat']}\n";
            echo "      Baris CSV: {$sekdes['baris']}\n";
        } else {
            echo "      ❌ TIDAK DITEMUKAN\n";
        }
        
        echo "\n   📋 SEMUA PERANGKAT:\n";
        foreach ($perangkat_list as $perangkat) {
            echo "      • {$perangkat['jabatan']}: {$perangkat['nama_lengkap']} ({$perangkat['telepon']})\n";
        }
        echo "\n";
    }
    
    // 3. Rekomendasi perbaikan
    echo "\n💡 REKOMENDASI PERBAIKAN:\n";
    echo str_repeat("=", 60) . "\n";
    
    echo "\n🔧 LANGKAH PERBAIKAN:\n";
    echo "1. Verifikasi data kontak person yang benar untuk setiap kecamatan\n";
    echo "2. Update database dengan data yang sesuai dari CSV\n";
    echo "3. Pastikan tidak ada duplikasi atau kesalahan penempatan\n\n";
    
    // Generate SQL update untuk setiap desa Bandingan
    echo "📝 SQL UPDATE YANG DISARANKAN:\n";
    echo str_repeat("-", 40) . "\n";
    
    foreach ($per_kecamatan as $kecamatan => $perangkat_list) {
        $kepala_desa = null;
        $sekdes = null;
        
        foreach ($perangkat_list as $perangkat) {
            $jabatan = strtolower($perangkat['jabatan']);
            
            if (strpos($jabatan, 'kepala desa') !== false) {
                $kepala_desa = $perangkat;
            }
            if (strpos($jabatan, 'sekretaris desa') !== false || strpos($jabatan, 'sekdes') !== false) {
                $sekdes = $perangkat;
            }
        }
        
        // Cari ID desa di database
        $desa_id = null;
        foreach ($desa_bandingan_db as $desa) {
            if (strtolower($desa['kecamatan']) === strtolower($kecamatan)) {
                $desa_id = $desa['id'];
                break;
            }
        }
        
        if ($desa_id && ($kepala_desa || $sekdes)) {
            echo "\n-- Update Bandingan Kec. {$kecamatan} (ID: {$desa_id})\n";
            echo "UPDATE desa SET \n";
            
            if ($kepala_desa) {
                echo "  nama_kepala_desa = '{$kepala_desa['nama_lengkap']}',\n";
                echo "  no_hp_kepala_desa = '{$kepala_desa['telepon']}',\n";
            }
            
            if ($sekdes) {
                echo "  nama_sekdes = '{$sekdes['nama_lengkap']}',\n";
                echo "  no_hp_sekdes = '{$sekdes['telepon']}'\n";
            }
            
            echo "WHERE id = {$desa_id};\n";
        }
    }
    
    echo "\n⚠️  CATATAN PENTING:\n";
    echo "- Pastikan data yang digunakan adalah yang paling update\n";
    echo "- Verifikasi nomor HP masih aktif sebelum update\n";
    echo "- Backup database sebelum melakukan update\n";
    echo "- Test tampilan halaman desa setelah update\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>