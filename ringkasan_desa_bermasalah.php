<?php
require_once 'config/database.php';

try {
    $db = getDatabase();
    echo "=== RINGKASAN DESA DENGAN KONTAK PERSON KOSONG ===\n\n";
    
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
    $bisa_diperbaiki = [];
    
    foreach ($desa_bermasalah as $desa) {
        $kecamatan = $desa['kecamatan'];
        if (!isset($kecamatan_stats[$kecamatan])) {
            $kecamatan_stats[$kecamatan] = 0;
        }
        $kecamatan_stats[$kecamatan]++;
        
        // Cek apakah ada kepala desa atau sekretaris di perangkat
        $perangkat_penting = $db->select(
            "SELECT jabatan, nama_lengkap, no_telepon 
             FROM perangkat_desa 
             WHERE desa_id = ? 
             AND (jabatan LIKE '%kepala%' OR jabatan LIKE '%kades%' OR jabatan LIKE '%sekretaris%' OR jabatan LIKE '%sekdes%')
             ORDER BY jabatan",
            [$desa['id']]
        );
        
        $status_kontak = [];
        if (empty($desa['nama_kepala_desa'])) $status_kontak[] = 'Nama Kepala';
        if (empty($desa['nama_sekdes'])) $status_kontak[] = 'Nama Sekdes';
        if (empty($desa['no_hp_kepala_desa'])) $status_kontak[] = 'HP Kepala';
        if (empty($desa['no_hp_sekdes'])) $status_kontak[] = 'HP Sekdes';
        
        echo "📍 {$desa['nama_desa']} (ID: {$desa['id']}) - Kec. {$desa['kecamatan']}\n";
        echo "   Perangkat: {$desa['jumlah_perangkat']} | Kosong: " . implode(', ', $status_kontak) . "\n";
        
        if (!empty($perangkat_penting)) {
            echo "   ✅ Ada perangkat penting: " . count($perangkat_penting) . " orang\n";
            $bisa_diperbaiki[] = $desa['id'];
        } else {
            echo "   ⚠️  Tidak ada kepala/sekretaris di data perangkat\n";
        }
        echo "\n";
    }
    
    // Statistik per kecamatan
    echo "\n📊 STATISTIK PER KECAMATAN:\n";
    arsort($kecamatan_stats);
    foreach ($kecamatan_stats as $kec => $jumlah) {
        echo "   {$kec}: {$jumlah} desa\n";
    }
    
    echo "\n📈 RINGKASAN:\n";
    echo "   Total desa bermasalah: " . count($desa_bermasalah) . "\n";
    echo "   Bisa diperbaiki otomatis: " . count($bisa_diperbaiki) . "\n";
    echo "   Perlu input manual: " . (count($desa_bermasalah) - count($bisa_diperbaiki)) . "\n";
    
    if (!empty($bisa_diperbaiki)) {
        echo "\n🔧 DESA YANG BISA DIPERBAIKI OTOMATIS:\n";
        echo "   ID: " . implode(', ', $bisa_diperbaiki) . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>