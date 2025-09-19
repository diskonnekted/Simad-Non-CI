<?php
require_once 'config/database.php';

try {
    echo "=== ANALISIS DESA BERMASALAH DARI FILE CSV ===\n\n";
    
    // Baca file CSV
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
    echo "📋 Header CSV: " . implode(' | ', $header) . "\n\n";
    
    $desa_bermasalah_csv = [];
    $total_baris = 0;
    $baris_kosong = 0;
    $baris_dengan_kosong = 0;
    
    // Analisis setiap baris
    while (($data = fgetcsv($handle)) !== FALSE) {
        $total_baris++;
        
        // Skip baris yang benar-benar kosong
        if (empty(array_filter($data))) {
            $baris_kosong++;
            continue;
        }
        
        // Cek apakah ada field 'KOSONG'
        $ada_kosong = false;
        foreach ($data as $field) {
            if (trim($field) === 'KOSONG' || trim($field) === 'KOSON') {
                $ada_kosong = true;
                break;
            }
        }
        
        if ($ada_kosong) {
            $baris_dengan_kosong++;
            
            // Ambil info desa dan kecamatan
            $nama_desa = isset($data[1]) ? trim($data[1]) : '';
            $kecamatan = isset($data[2]) ? trim($data[2]) : '';
            $nama_lengkap = isset($data[3]) ? trim($data[3]) : '';
            $jabatan = isset($data[10]) ? trim($data[10]) : '';
            
            if (!empty($nama_desa) && !empty($kecamatan)) {
                $key = $nama_desa . '_' . $kecamatan;
                if (!isset($desa_bermasalah_csv[$key])) {
                    $desa_bermasalah_csv[$key] = [
                        'nama_desa' => $nama_desa,
                        'kecamatan' => $kecamatan,
                        'perangkat_kosong' => []
                    ];
                }
                
                $desa_bermasalah_csv[$key]['perangkat_kosong'][] = [
                    'nama' => $nama_lengkap,
                    'jabatan' => $jabatan,
                    'baris' => $total_baris + 1
                ];
            }
        }
    }
    
    fclose($handle);
    
    echo "📊 STATISTIK FILE CSV:\n";
    echo "   Total baris: {$total_baris}\n";
    echo "   Baris kosong: {$baris_kosong}\n";
    echo "   Baris dengan data 'KOSONG': {$baris_dengan_kosong}\n";
    echo "   Desa yang teridentifikasi bermasalah: " . count($desa_bermasalah_csv) . "\n\n";
    
    // Tampilkan desa bermasalah dari CSV
    if (!empty($desa_bermasalah_csv)) {
        echo "🔍 DESA BERMASALAH DARI FILE CSV:\n";
        echo str_repeat("=", 60) . "\n";
        
        foreach ($desa_bermasalah_csv as $desa) {
            echo "📍 {$desa['nama_desa']} - Kec. {$desa['kecamatan']}\n";
            echo "   Perangkat dengan data KOSONG (" . count($desa['perangkat_kosong']) . " orang):\n";
            
            foreach ($desa['perangkat_kosong'] as $perangkat) {
                echo "   • {$perangkat['jabatan']} - Baris {$perangkat['baris']}\n";
            }
            echo "\n";
        }
    }
    
    // Bandingkan dengan hasil analisis database
    echo "\n🔄 PERBANDINGAN DENGAN ANALISIS DATABASE:\n";
    echo str_repeat("=", 60) . "\n";
    
    $db = getDatabase();
    
    // Ambil desa bermasalah dari database
    $desa_bermasalah_db = $db->select(
        "SELECT id, nama_desa, kecamatan, nama_kepala_desa, nama_sekdes, no_hp_kepala_desa, no_hp_sekdes
         FROM desa 
         WHERE status = 'aktif'
         AND (
             nama_kepala_desa IS NULL OR nama_kepala_desa = '' OR
             nama_sekdes IS NULL OR nama_sekdes = '' OR
             no_hp_kepala_desa IS NULL OR no_hp_kepala_desa = '' OR
             no_hp_sekdes IS NULL OR no_hp_sekdes = ''
         )
         ORDER BY kecamatan, nama_desa"
    );
    
    echo "📊 PERBANDINGAN HASIL:\n";
    echo "   Desa bermasalah dari CSV: " . count($desa_bermasalah_csv) . "\n";
    echo "   Desa bermasalah dari Database: " . count($desa_bermasalah_db) . "\n\n";
    
    // Cari desa yang ada di CSV tapi tidak di database atau sebaliknya
    $desa_csv_names = [];
    foreach ($desa_bermasalah_csv as $desa) {
        $desa_csv_names[] = strtolower($desa['nama_desa'] . '_' . $desa['kecamatan']);
    }
    
    $desa_db_names = [];
    foreach ($desa_bermasalah_db as $desa) {
        $desa_db_names[] = strtolower($desa['nama_desa'] . '_' . $desa['kecamatan']);
    }
    
    $hanya_di_csv = array_diff($desa_csv_names, $desa_db_names);
    $hanya_di_db = array_diff($desa_db_names, $desa_csv_names);
    
    if (!empty($hanya_di_csv)) {
        echo "⚠️  DESA BERMASALAH HANYA DI CSV (" . count($hanya_di_csv) . "):\n";
        foreach ($hanya_di_csv as $desa_name) {
            echo "   • " . str_replace('_', ' - Kec. ', $desa_name) . "\n";
        }
        echo "\n";
    }
    
    if (!empty($hanya_di_db)) {
        echo "⚠️  DESA BERMASALAH HANYA DI DATABASE (" . count($hanya_di_db) . "):\n";
        $count = 0;
        foreach ($desa_bermasalah_db as $desa) {
            $desa_key = strtolower($desa['nama_desa'] . '_' . $desa['kecamatan']);
            if (in_array($desa_key, $hanya_di_db)) {
                $count++;
                $masalah = [];
                if (empty($desa['nama_kepala_desa'])) $masalah[] = 'Nama Kepala';
                if (empty($desa['nama_sekdes'])) $masalah[] = 'Nama Sekdes';
                if (empty($desa['no_hp_kepala_desa'])) $masalah[] = 'HP Kepala';
                if (empty($desa['no_hp_sekdes'])) $masalah[] = 'HP Sekdes';
                
                echo "   • {$desa['nama_desa']} - Kec. {$desa['kecamatan']} (ID: {$desa['id']})\n";
                echo "     Masalah: " . implode(', ', $masalah) . "\n";
                
                if ($count >= 10) {
                    echo "   ... dan " . (count($hanya_di_db) - 10) . " desa lainnya\n";
                    break;
                }
            }
        }
        echo "\n";
    }
    
    echo "💡 KESIMPULAN:\n";
    echo "   - File CSV berisi data perangkat desa dengan beberapa entry 'KOSONG'\n";
    echo "   - Database berisi data kontak person desa yang tidak lengkap\n";
    echo "   - Keduanya menunjukkan masalah data yang berbeda tapi saling melengkapi\n";
    echo "   - CSV: masalah di level perangkat desa\n";
    echo "   - Database: masalah di level kontak person desa\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>