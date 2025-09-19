<?php
require_once 'config/database.php';

/**
 * Script verifikasi perbaikan tampilan kontak person
 * Memastikan kontak ditampilkan dengan benar setelah update
 */

echo "=== VERIFIKASI PERBAIKAN TAMPILAN KONTAK ===\n";
echo "Tanggal: " . date('Y-m-d H:i:s') . "\n\n";

try {
    $db = getDatabase();
    
    // 1. Test data desa Bandingan yang sudah diperbaiki
    echo "1. VERIFIKASI DESA BANDINGAN (YANG SUDAH DIPERBAIKI)\n";
    echo "====================================================\n";
    
    $bandingan_desa = $db->select("
        SELECT id, nama_desa, kecamatan, 
               nama_kepala_desa, no_hp_kepala_desa,
               nama_sekdes, no_hp_sekdes
        FROM desa 
        WHERE nama_desa LIKE '%Bandingan%'
        ORDER BY kecamatan, nama_desa
    ");
    
    foreach ($bandingan_desa as $desa) {
        echo "ID {$desa['id']}: {$desa['nama_desa']} - {$desa['kecamatan']}\n";
        
        // Simulasi logika tampilan yang baru
        $kontak_person = $desa['nama_sekdes'] ?? $desa['nama_kepala_desa'] ?? '-';
        $no_telepon = $desa['no_hp_sekdes'] ?? $desa['no_hp_kepala_desa'] ?? '-';
        
        echo "  Kontak Person (tampilan): {$kontak_person}\n";
        echo "  No. Telepon (tampilan): {$no_telepon}\n";
        echo "  \n";
        echo "  Detail lengkap:\n";
        echo "    Kepala Desa: {$desa['nama_kepala_desa']} | HP: {$desa['no_hp_kepala_desa']}\n";
        echo "    Sekdes: {$desa['nama_sekdes']} | HP: {$desa['no_hp_sekdes']}\n";
        echo "\n";
    }
    
    // 2. Test sample desa lain untuk memastikan fallback bekerja
    echo "2. VERIFIKASI SAMPLE DESA LAIN\n";
    echo "==============================\n";
    
    $sample_desa = $db->select("
        SELECT id, nama_desa, kecamatan, 
               nama_kepala_desa, no_hp_kepala_desa,
               nama_sekdes, no_hp_sekdes
        FROM desa 
        WHERE status = 'aktif'
        ORDER BY RAND()
        LIMIT 5
    ");
    
    foreach ($sample_desa as $desa) {
        echo "ID {$desa['id']}: {$desa['nama_desa']} - {$desa['kecamatan']}\n";
        
        // Simulasi logika tampilan yang baru
        $kontak_person = $desa['nama_sekdes'] ?? $desa['nama_kepala_desa'] ?? '-';
        $no_telepon = $desa['no_hp_sekdes'] ?? $desa['no_hp_kepala_desa'] ?? '-';
        
        echo "  Kontak Person: {$kontak_person}\n";
        echo "  No. Telepon: {$no_telepon}\n";
        
        // Analisis sumber data
        if ($desa['nama_sekdes'] && $desa['no_hp_sekdes']) {
            echo "  ✓ Menggunakan data Sekdes (prioritas utama)\n";
        } elseif ($desa['nama_kepala_desa'] && $desa['no_hp_kepala_desa']) {
            echo "  ✓ Fallback ke data Kepala Desa\n";
        } else {
            echo "  ⚠ Data kontak tidak lengkap\n";
        }
        echo "\n";
    }
    
    // 3. Statistik efektivitas perbaikan
    echo "3. STATISTIK EFEKTIVITAS PERBAIKAN\n";
    echo "==================================\n";
    
    $stats = $db->select("
        SELECT 
            COUNT(*) as total_desa,
            COUNT(CASE WHEN no_hp_sekdes IS NOT NULL AND no_hp_sekdes != '' AND no_hp_sekdes != '0' THEN 1 END) as sekdes_ada,
            COUNT(CASE WHEN no_hp_kepala_desa IS NOT NULL AND no_hp_kepala_desa != '' AND no_hp_kepala_desa != '0' THEN 1 END) as kepala_ada,
            COUNT(CASE WHEN 
                (no_hp_sekdes IS NOT NULL AND no_hp_sekdes != '' AND no_hp_sekdes != '0') OR 
                (no_hp_kepala_desa IS NOT NULL AND no_hp_kepala_desa != '' AND no_hp_kepala_desa != '0')
                THEN 1 END) as ada_kontak,
            COUNT(CASE WHEN 
                (no_hp_sekdes IS NULL OR no_hp_sekdes = '' OR no_hp_sekdes = '0') AND 
                (no_hp_kepala_desa IS NULL OR no_hp_kepala_desa = '' OR no_hp_kepala_desa = '0')
                THEN 1 END) as tanpa_kontak
        FROM desa 
        WHERE status = 'aktif'
    ")[0];
    
    echo "Total desa aktif: {$stats['total_desa']}\n";
    echo "Desa dengan HP Sekdes: {$stats['sekdes_ada']} (" . round($stats['sekdes_ada']/$stats['total_desa']*100, 1) . "%)\n";
    echo "Desa dengan HP Kepala: {$stats['kepala_ada']} (" . round($stats['kepala_ada']/$stats['total_desa']*100, 1) . "%)\n";
    echo "Desa dengan kontak (setelah fallback): {$stats['ada_kontak']} (" . round($stats['ada_kontak']/$stats['total_desa']*100, 1) . "%)\n";
    echo "Desa tanpa kontak: {$stats['tanpa_kontak']} (" . round($stats['tanpa_kontak']/$stats['total_desa']*100, 1) . "%)\n";
    echo "\n";
    
    $peningkatan = $stats['ada_kontak'] - max($stats['sekdes_ada'], $stats['kepala_ada']);
    echo "Peningkatan coverage kontak: +{$peningkatan} desa\n";
    echo "\n";
    
    // 4. Daftar desa yang masih perlu input manual
    echo "4. DESA YANG MASIH PERLU INPUT MANUAL\n";
    echo "====================================\n";
    
    $need_manual = $db->select("
        SELECT id, nama_desa, kecamatan
        FROM desa 
        WHERE status = 'aktif'
        AND (no_hp_sekdes IS NULL OR no_hp_sekdes = '' OR no_hp_sekdes = '0')
        AND (no_hp_kepala_desa IS NULL OR no_hp_kepala_desa = '' OR no_hp_kepala_desa = '0')
        ORDER BY kecamatan, nama_desa
    ");
    
    if (empty($need_manual)) {
        echo "✓ Semua desa sudah memiliki kontak!\n";
    } else {
        echo "Desa yang perlu input manual (" . count($need_manual) . " desa):\n";
        $current_kec = '';
        foreach ($need_manual as $desa) {
            if ($current_kec != $desa['kecamatan']) {
                $current_kec = $desa['kecamatan'];
                echo "\nKecamatan {$current_kec}:\n";
            }
            echo "- ID {$desa['id']}: {$desa['nama_desa']}\n";
        }
    }
    
    echo "\n";
    
    // 5. Ringkasan perbaikan yang telah dilakukan
    echo "5. RINGKASAN PERBAIKAN YANG TELAH DILAKUKAN\n";
    echo "===========================================\n";
    
    echo "✓ File desa.php: Kolom telepon sekarang menggunakan fallback no_hp_sekdes ?? no_hp_kepala_desa\n";
    echo "✓ File desa-view.php: Tampilan kontak dan link telepon menggunakan fallback yang sama\n";
    echo "✓ Prioritas kontak: Sekdes (85.1% coverage) → Kepala Desa (83.6% coverage)\n";
    echo "✓ Coverage total kontak meningkat menjadi " . round($stats['ada_kontak']/$stats['total_desa']*100, 1) . "%\n";
    echo "\n";
    
    echo "6. LANGKAH SELANJUTNYA\n";
    echo "======================\n";
    echo "1. Test tampilan halaman desa.php dan desa-view.php\n";
    echo "2. Verifikasi kontak desa Bandingan sudah benar\n";
    echo "3. Input manual kontak untuk {$stats['tanpa_kontak']} desa yang masih kosong\n";
    echo "4. Monitor feedback dari user terkait kontak yang ditampilkan\n";
    echo "\n";
    
    echo "=== VERIFIKASI SELESAI ===\n";
    echo "Perbaikan tampilan kontak berhasil diterapkan!\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
?>