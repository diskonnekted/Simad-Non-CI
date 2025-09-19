<?php
require_once 'config/database.php';

try {
    $db = getDatabase();
    echo "=== VERIFIKASI HASIL PERBAIKAN KONTAK PERSON ===\n\n";
    
    // Statistik umum
    $total_desa = $db->select("SELECT COUNT(*) as total FROM desa WHERE status = 'aktif'")[0]['total'];
    
    $dengan_kepala = $db->select(
        "SELECT COUNT(*) as total FROM desa 
         WHERE status = 'aktif' AND nama_kepala_desa IS NOT NULL AND nama_kepala_desa != ''"
    )[0]['total'];
    
    $dengan_sekdes = $db->select(
        "SELECT COUNT(*) as total FROM desa 
         WHERE status = 'aktif' AND nama_sekdes IS NOT NULL AND nama_sekdes != ''"
    )[0]['total'];
    
    $dengan_hp_kepala = $db->select(
        "SELECT COUNT(*) as total FROM desa 
         WHERE status = 'aktif' AND no_hp_kepala_desa IS NOT NULL AND no_hp_kepala_desa != ''"
    )[0]['total'];
    
    $dengan_hp_sekdes = $db->select(
        "SELECT COUNT(*) as total FROM desa 
         WHERE status = 'aktif' AND no_hp_sekdes IS NOT NULL AND no_hp_sekdes != ''"
    )[0]['total'];
    
    echo "📊 STATISTIK KELENGKAPAN KONTAK PERSON:\n";
    echo "   Total desa aktif: {$total_desa}\n";
    echo "   Dengan nama kepala desa: {$dengan_kepala} (" . round(($dengan_kepala/$total_desa)*100, 1) . "%)\n";
    echo "   Dengan nama sekretaris: {$dengan_sekdes} (" . round(($dengan_sekdes/$total_desa)*100, 1) . "%)\n";
    echo "   Dengan no HP kepala: {$dengan_hp_kepala} (" . round(($dengan_hp_kepala/$total_desa)*100, 1) . "%)\n";
    echo "   Dengan no HP sekdes: {$dengan_hp_sekdes} (" . round(($dengan_hp_sekdes/$total_desa)*100, 1) . "%)\n\n";
    
    // Desa yang masih bermasalah
    $masih_bermasalah = $db->select(
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
    
    if (!empty($masih_bermasalah)) {
        echo "⚠️  DESA YANG MASIH PERLU PERBAIKAN (" . count($masih_bermasalah) . " desa):\n\n";
        
        $kecamatan_bermasalah = [];
        foreach ($masih_bermasalah as $desa) {
            $kecamatan = $desa['kecamatan'];
            if (!isset($kecamatan_bermasalah[$kecamatan])) {
                $kecamatan_bermasalah[$kecamatan] = 0;
            }
            $kecamatan_bermasalah[$kecamatan]++;
            
            $masalah = [];
            if (empty($desa['nama_kepala_desa'])) $masalah[] = 'Nama Kepala';
            if (empty($desa['nama_sekdes'])) $masalah[] = 'Nama Sekdes';
            if (empty($desa['no_hp_kepala_desa'])) $masalah[] = 'HP Kepala';
            if (empty($desa['no_hp_sekdes'])) $masalah[] = 'HP Sekdes';
            
            echo "   📍 {$desa['nama_desa']} (ID: {$desa['id']}) - Kec. {$desa['kecamatan']}\n";
            echo "      Perlu: " . implode(', ', $masalah) . "\n\n";
        }
        
        echo "📊 KECAMATAN DENGAN DESA BERMASALAH:\n";
        arsort($kecamatan_bermasalah);
        foreach ($kecamatan_bermasalah as $kec => $jumlah) {
            echo "   {$kec}: {$jumlah} desa\n";
        }
    } else {
        echo "🎉 SEMUA DESA SUDAH MEMILIKI KONTAK PERSON LENGKAP!\n";
    }
    
    // Cek desa yang akan muncul di halaman (memiliki nama_sekdes atau no_hp_sekdes)
    $muncul_di_halaman = $db->select(
        "SELECT COUNT(*) as total FROM desa 
         WHERE status = 'aktif' 
         AND (nama_sekdes IS NOT NULL AND nama_sekdes != '') 
         AND (no_hp_sekdes IS NOT NULL AND no_hp_sekdes != '')"
    )[0]['total'];
    
    echo "\n📱 TAMPILAN DI HALAMAN DESA:\n";
    echo "   Desa dengan kontak person muncul: {$muncul_di_halaman} dari {$total_desa} (" . round(($muncul_di_halaman/$total_desa)*100, 1) . "%)\n";
    echo "   Desa dengan kontak kosong: " . ($total_desa - $muncul_di_halaman) . "\n";
    
    // Rata-rata kelengkapan
    $rata_rata = round((($dengan_kepala + $dengan_sekdes + $dengan_hp_kepala + $dengan_hp_sekdes) / (4 * $total_desa)) * 100, 1);
    echo "\n🎯 RATA-RATA KELENGKAPAN: {$rata_rata}%\n";
    
    if ($rata_rata >= 90) {
        echo "✅ EXCELLENT! Target kelengkapan tercapai.\n";
    } elseif ($rata_rata >= 80) {
        echo "👍 GOOD! Kelengkapan sudah baik.\n";
    } else {
        echo "⚠️  Masih perlu perbaikan lebih lanjut.\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>