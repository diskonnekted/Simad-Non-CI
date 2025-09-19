<?php
require_once 'config/database.php';

try {
    $db = getDatabase();
    echo "=== DAFTAR 34 DESA YANG MASIH BERMASALAH ===\n\n";
    
    // Ambil desa yang masih bermasalah
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
    
    echo "📊 TOTAL: " . count($desa_bermasalah) . " DESA YANG PERLU INPUT MANUAL\n\n";
    
    // Kelompokkan per kecamatan
    $per_kecamatan = [];
    foreach ($desa_bermasalah as $desa) {
        $kecamatan = $desa['kecamatan'];
        if (!isset($per_kecamatan[$kecamatan])) {
            $per_kecamatan[$kecamatan] = [];
        }
        $per_kecamatan[$kecamatan][] = $desa;
    }
    
    // Tampilkan per kecamatan
    foreach ($per_kecamatan as $kecamatan => $desa_list) {
        echo "🏛️  KECAMATAN {$kecamatan} (" . count($desa_list) . " desa):\n";
        echo str_repeat("=", 50) . "\n";
        
        foreach ($desa_list as $desa) {
            $masalah = [];
            if (empty($desa['nama_kepala_desa'])) $masalah[] = 'Nama Kepala Desa';
            if (empty($desa['nama_sekdes'])) $masalah[] = 'Nama Sekretaris Desa';
            if (empty($desa['no_hp_kepala_desa'])) $masalah[] = 'No HP Kepala Desa';
            if (empty($desa['no_hp_sekdes'])) $masalah[] = 'No HP Sekretaris Desa';
            
            echo "   📍 {$desa['nama_desa']} (ID: {$desa['id']})\n";
            echo "      ❌ Perlu diisi: " . implode(', ', $masalah) . "\n";
            echo "      📋 Status saat ini:\n";
            echo "         - Kepala Desa: " . ($desa['nama_kepala_desa'] ?: '❌ KOSONG') . "\n";
            echo "         - HP Kepala: " . ($desa['no_hp_kepala_desa'] ?: '❌ KOSONG') . "\n";
            echo "         - Sekretaris: " . ($desa['nama_sekdes'] ?: '❌ KOSONG') . "\n";
            echo "         - HP Sekdes: " . ($desa['no_hp_sekdes'] ?: '❌ KOSONG') . "\n";
            echo "\n";
        }
        echo "\n";
    }
    
    // Ringkasan prioritas
    echo "🎯 PRIORITAS PERBAIKAN:\n";
    echo str_repeat("=", 50) . "\n";
    
    // Hitung berdasarkan tingkat kelengkapan
    $prioritas_tinggi = [];
    $prioritas_sedang = [];
    $prioritas_rendah = [];
    
    foreach ($desa_bermasalah as $desa) {
        $kosong = 0;
        if (empty($desa['nama_kepala_desa'])) $kosong++;
        if (empty($desa['nama_sekdes'])) $kosong++;
        if (empty($desa['no_hp_kepala_desa'])) $kosong++;
        if (empty($desa['no_hp_sekdes'])) $kosong++;
        
        if ($kosong >= 3) {
            $prioritas_tinggi[] = $desa;
        } elseif ($kosong == 2) {
            $prioritas_sedang[] = $desa;
        } else {
            $prioritas_rendah[] = $desa;
        }
    }
    
    echo "🔴 PRIORITAS TINGGI (" . count($prioritas_tinggi) . " desa - hampir semua data kosong):\n";
    foreach ($prioritas_tinggi as $desa) {
        echo "   • {$desa['nama_desa']} - Kec. {$desa['kecamatan']} (ID: {$desa['id']})\n";
    }
    
    echo "\n🟡 PRIORITAS SEDANG (" . count($prioritas_sedang) . " desa - sebagian data kosong):\n";
    foreach ($prioritas_sedang as $desa) {
        echo "   • {$desa['nama_desa']} - Kec. {$desa['kecamatan']} (ID: {$desa['id']})\n";
    }
    
    echo "\n🟢 PRIORITAS RENDAH (" . count($prioritas_rendah) . " desa - hanya 1 field kosong):\n";
    foreach ($prioritas_rendah as $desa) {
        echo "   • {$desa['nama_desa']} - Kec. {$desa['kecamatan']} (ID: {$desa['id']})\n";
    }
    
    // Daftar ID untuk copy-paste
    echo "\n📋 DAFTAR ID DESA (untuk copy-paste):\n";
    echo str_repeat("=", 50) . "\n";
    $id_list = array_column($desa_bermasalah, 'id');
    echo implode(', ', $id_list) . "\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>