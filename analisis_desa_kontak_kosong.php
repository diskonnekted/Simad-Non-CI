<?php
require_once 'config/database.php';

try {
    $db = getDatabase();
    echo "=== ANALISIS DESA DENGAN KONTAK PERSON KOSONG ===\n\n";
    
    // Cari desa yang kontak personnya kosong tapi punya data perangkat
    $query = "
        SELECT DISTINCT d.id, d.nama_desa, d.kecamatan, 
               d.nama_kepala_desa, d.nama_sekdes, d.no_hp_kepala_desa, d.no_hp_sekdes,
               COUNT(p.id) as jumlah_perangkat
        FROM desa d
        LEFT JOIN perangkat_desa p ON d.id = p.desa_id
        WHERE d.status = 'aktif'
        AND (
            d.nama_kepala_desa IS NULL OR d.nama_kepala_desa = '' OR
            d.nama_sekdes IS NULL OR d.nama_sekdes = '' OR
            d.no_hp_kepala_desa IS NULL OR d.no_hp_kepala_desa = '' OR
            d.no_hp_sekdes IS NULL OR d.no_hp_sekdes = ''
        )
        GROUP BY d.id
        HAVING jumlah_perangkat > 0
        ORDER BY d.kecamatan, d.nama_desa
    ";
    
    $desa_bermasalah = $db->select($query);
    
    if (empty($desa_bermasalah)) {
        echo "✅ Semua desa dengan data perangkat sudah memiliki kontak person lengkap!\n";
        exit(0);
    }
    
    echo "🔍 DITEMUKAN " . count($desa_bermasalah) . " DESA DENGAN MASALAH KONTAK PERSON:\n\n";
    
    $kecamatan_stats = [];
    $total_bisa_diperbaiki = 0;
    
    foreach ($desa_bermasalah as $desa) {
        $kecamatan = $desa['kecamatan'];
        if (!isset($kecamatan_stats[$kecamatan])) {
            $kecamatan_stats[$kecamatan] = 0;
        }
        $kecamatan_stats[$kecamatan]++;
        
        echo "📍 {$desa['nama_desa']} (ID: {$desa['id']}) - Kec. {$desa['kecamatan']}\n";
        echo "   Perangkat: {$desa['jumlah_perangkat']} orang\n";
        echo "   Status Kontak:\n";
        echo "   - Kepala Desa: " . ($desa['nama_kepala_desa'] ?: '❌ KOSONG') . "\n";
        echo "   - Sekretaris: " . ($desa['nama_sekdes'] ?: '❌ KOSONG') . "\n";
        echo "   - HP Kepala: " . ($desa['no_hp_kepala_desa'] ?: '❌ KOSONG') . "\n";
        echo "   - HP Sekdes: " . ($desa['no_hp_sekdes'] ?: '❌ KOSONG') . "\n";
        
        // Cek detail perangkat untuk desa ini
        $perangkat = $db->select(
            "SELECT jabatan, nama_lengkap, no_telepon FROM perangkat_desa WHERE desa_id = ? ORDER BY jabatan",
            [$desa['id']]
        );
        
        $ada_kepala = false;
        $ada_sekretaris = false;
        
        echo "   Perangkat tersedia:\n";
        foreach ($perangkat as $p) {
            echo "   - {$p['jabatan']}: {$p['nama_lengkap']}";
            if (!empty($p['no_telepon'])) {
                echo " (HP: {$p['no_telepon']})";
            }
            echo "\n";
            
            if (stripos($p['jabatan'], 'kepala') !== false || stripos($p['jabatan'], 'kades') !== false) {
                $ada_kepala = true;
            }
            if (stripos($p['jabatan'], 'sekretaris') !== false || stripos($p['jabatan'], 'sekdes') !== false) {
                $ada_sekretaris = true;
            }
        }
        
        if ($ada_kepala || $ada_sekretaris) {
            echo "   ✅ BISA DIPERBAIKI\n";
            $total_bisa_diperbaiki++;
        } else {
            echo "   ⚠️  Perlu data manual (tidak ada jabatan kepala/sekretaris)\n";
        }
        
        echo "\n" . str_repeat('-', 60) . "\n\n";
    }
    
    // Statistik per kecamatan
    echo "📊 STATISTIK PER KECAMATAN:\n";
    arsort($kecamatan_stats);
    foreach ($kecamatan_stats as $kec => $jumlah) {
        echo "   {$kec}: {$jumlah} desa\n";
    }
    
    echo "\n📈 RINGKASAN:\n";
    echo "   Total desa bermasalah: " . count($desa_bermasalah) . "\n";
    echo "   Bisa diperbaiki otomatis: {$total_bisa_diperbaiki}\n";
    echo "   Perlu input manual: " . (count($desa_bermasalah) - $total_bisa_diperbaiki) . "\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>