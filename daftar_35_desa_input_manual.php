<?php
require_once 'config/database.php';

try {
    echo "=== DAFTAR 35 DESA YANG PERLU INPUT MANUAL KONTAK PERSON ===\n\n";
    
    $db = getDatabase();
    
    // Query desa bermasalah dengan detail lengkap
    $desa_bermasalah = $db->select(
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
    
    echo "📊 TOTAL DESA BERMASALAH: " . count($desa_bermasalah) . " desa\n\n";
    
    // Kelompokkan per kecamatan
    $per_kecamatan = [];
    foreach ($desa_bermasalah as $desa) {
        $kec = $desa['kecamatan'];
        if (!isset($per_kecamatan[$kec])) {
            $per_kecamatan[$kec] = [];
        }
        $per_kecamatan[$kec][] = $desa;
    }
    
    echo "📋 DAFTAR DESA PER KECAMATAN:\n";
    echo str_repeat("=", 80) . "\n";
    
    $no = 1;
    $daftar_id = [];
    
    foreach ($per_kecamatan as $kecamatan => $desa_list) {
        echo "\n🏛️  KECAMATAN {$kecamatan} (" . count($desa_list) . " desa)\n";
        echo str_repeat("-", 50) . "\n";
        
        foreach ($desa_list as $desa) {
            $daftar_id[] = $desa['id'];
            
            // Tentukan masalah
            $masalah = [];
            if (empty($desa['nama_kepala_desa'])) $masalah[] = '❌ Nama Kepala Desa';
            if (empty($desa['nama_sekdes'])) $masalah[] = '❌ Nama Sekretaris Desa';
            if (empty($desa['no_hp_kepala_desa'])) $masalah[] = '📱 No HP Kepala Desa';
            if (empty($desa['no_hp_sekdes'])) $masalah[] = '📱 No HP Sekretaris Desa';
            
            echo "{$no}. {$desa['nama_desa']} (ID: {$desa['id']})\n";
            echo "   Data yang perlu dilengkapi:\n";
            foreach ($masalah as $m) {
                echo "   • {$m}\n";
            }
            
            // Tampilkan data yang sudah ada
            $ada_data = [];
            if (!empty($desa['nama_kepala_desa'])) $ada_data[] = "✅ Kepala: {$desa['nama_kepala_desa']}";
            if (!empty($desa['nama_sekdes'])) $ada_data[] = "✅ Sekdes: {$desa['nama_sekdes']}";
            if (!empty($desa['no_hp_kepala_desa'])) $ada_data[] = "✅ HP Kepala: {$desa['no_hp_kepala_desa']}";
            if (!empty($desa['no_hp_sekdes'])) $ada_data[] = "✅ HP Sekdes: {$desa['no_hp_sekdes']}";
            
            if (!empty($ada_data)) {
                echo "   Data yang sudah ada:\n";
                foreach ($ada_data as $data) {
                    echo "   • {$data}\n";
                }
            }
            echo "\n";
            $no++;
        }
    }
    
    // Ringkasan untuk copy-paste
    echo "\n" . str_repeat("=", 80) . "\n";
    echo "📝 RINGKASAN UNTUK INPUT MANUAL:\n";
    echo str_repeat("=", 80) . "\n";
    
    echo "\n🔢 DAFTAR ID DESA (untuk query database):\n";
    echo implode(', ', $daftar_id) . "\n\n";
    
    echo "📊 STATISTIK PER KECAMATAN:\n";
    foreach ($per_kecamatan as $kecamatan => $desa_list) {
        echo "• {$kecamatan}: " . count($desa_list) . " desa\n";
    }
    
    echo "\n🎯 PRIORITAS INPUT MANUAL:\n";
    echo "1. PRIORITAS TINGGI (data kosong semua): ";
    $prioritas_tinggi = 0;
    foreach ($desa_bermasalah as $desa) {
        $kosong_semua = empty($desa['nama_kepala_desa']) && empty($desa['nama_sekdes']) && 
                       empty($desa['no_hp_kepala_desa']) && empty($desa['no_hp_sekdes']);
        if ($kosong_semua) $prioritas_tinggi++;
    }
    echo "{$prioritas_tinggi} desa\n";
    
    echo "2. PRIORITAS SEDANG (sebagian data ada): ";
    $prioritas_sedang = count($desa_bermasalah) - $prioritas_tinggi;
    echo "{$prioritas_sedang} desa\n";
    
    echo "\n💡 TEMPLATE UPDATE SQL:\n";
    echo "UPDATE desa SET \n";
    echo "  nama_kepala_desa = '[NAMA_KEPALA]',\n";
    echo "  nama_sekdes = '[NAMA_SEKDES]',\n";
    echo "  no_hp_kepala_desa = '[HP_KEPALA]',\n";
    echo "  no_hp_sekdes = '[HP_SEKDES]'\n";
    echo "WHERE id = [ID_DESA];\n";
    
    echo "\n📋 CHECKLIST UNTUK ADMIN:\n";
    echo "□ Siapkan data kontak person dari sumber resmi\n";
    echo "□ Verifikasi nomor HP masih aktif\n";
    echo "□ Update data satu per satu atau batch\n";
    echo "□ Test tampilan halaman desa setelah update\n";
    echo "□ Jalankan verifikasi ulang setelah selesai\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>