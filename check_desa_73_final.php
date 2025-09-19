<?php
require_once 'config/database.php';

try {
    $db = getDatabase();
    
    $result = $db->select(
        "SELECT id, nama_desa, kecamatan, nama_kepala_desa, nama_sekdes, no_hp_kepala_desa, no_hp_sekdes 
         FROM desa WHERE id = 73"
    );
    
    if (!empty($result)) {
        $desa = $result[0];
        echo "=== STATUS DESA ID 73 SETELAH PERBAIKAN ===\n\n";
        echo "Desa: {$desa['nama_desa']} (ID: {$desa['id']})\n";
        echo "Kecamatan: {$desa['kecamatan']}\n\n";
        echo "KONTAK PERSON:\n";
        echo "Kepala Desa: " . ($desa['nama_kepala_desa'] ?: 'KOSONG') . "\n";
        echo "Sekretaris: " . ($desa['nama_sekdes'] ?: 'KOSONG') . "\n";
        echo "HP Kepala: " . ($desa['no_hp_kepala_desa'] ?: 'KOSONG') . "\n";
        echo "HP Sekdes: " . ($desa['no_hp_sekdes'] ?: 'KOSONG') . "\n\n";
        
        $kontak_person = $desa['nama_sekdes'] ?: ($desa['nama_kepala_desa'] ?: '-');
        $telepon = $desa['no_hp_sekdes'] ?: '-';
        
        echo "TAMPILAN DI HALAMAN DESA:\n";
        echo "Kolom 'Kontak Person': {$kontak_person}\n";
        echo "Kolom 'Telepon': {$telepon}\n\n";
        
        if ($kontak_person !== '-' && $telepon !== '-') {
            echo "✅ Kontak person sudah muncul di halaman desa!\n";
        } else {
            echo "❌ Kontak person masih belum muncul (data kosong)\n";
        }
    } else {
        echo "❌ Desa ID 73 tidak ditemukan\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>