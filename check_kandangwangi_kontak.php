<?php
require_once 'config/database.php';

try {
    $db = getDatabase();
    echo "=== PEMERIKSAAN DATA KONTAK DESA KANDANGWANGI ===\n\n";
    
    // Ambil data desa Kandangwangi
    $result = $db->select(
        "SELECT id, nama_desa, kecamatan, nama_kepala_desa, nama_sekdes, no_hp_kepala_desa, no_hp_sekdes 
         FROM desa WHERE id = 157"
    );
    
    if (empty($result)) {
        echo "❌ Desa dengan ID 157 tidak ditemukan!\n";
        exit(1);
    }
    
    $desa = $result[0];
    
    echo "📍 Data Desa Kandangwangi (ID: {$desa['id']})\n";
    echo "   Nama Desa: {$desa['nama_desa']}\n";
    echo "   Kecamatan: {$desa['kecamatan']}\n\n";
    
    echo "👤 KONTAK PERSON:\n";
    echo "   Kepala Desa: " . ($desa['nama_kepala_desa'] ?: 'KOSONG') . "\n";
    echo "   Sekretaris Desa: " . ($desa['nama_sekdes'] ?: 'KOSONG') . "\n\n";
    
    echo "📞 NOMOR TELEPON:\n";
    echo "   No HP Kepala: " . ($desa['no_hp_kepala_desa'] ?: 'KOSONG') . "\n";
    echo "   No HP Sekdes: " . ($desa['no_hp_sekdes'] ?: 'KOSONG') . "\n\n";
    
    // Analisis tampilan di halaman desa.php
    echo "🔍 ANALISIS TAMPILAN DI HALAMAN DESA:\n";
    
    // Kolom Kontak Person (menampilkan nama_sekdes atau nama_kepala_desa)
    $kontak_person = $desa['nama_sekdes'] ?: ($desa['nama_kepala_desa'] ?: '-');
    echo "   Kolom 'Kontak Person' akan menampilkan: '$kontak_person'\n";
    
    // Kolom Telepon (menampilkan no_hp_sekdes)
    $telepon = $desa['no_hp_sekdes'] ?: '-';
    echo "   Kolom 'Telepon' akan menampilkan: '$telepon'\n\n";
    
    // Rekomendasi
    echo "💡 REKOMENDASI:\n";
    if (empty($desa['nama_sekdes'])) {
        echo "   ⚠️  Field 'nama_sekdes' kosong - perlu diisi agar muncul di kolom Kontak Person\n";
    }
    if (empty($desa['no_hp_sekdes'])) {
        echo "   ⚠️  Field 'no_hp_sekdes' kosong - perlu diisi agar muncul di kolom Telepon\n";
    }
    
    if (!empty($desa['nama_sekdes']) && !empty($desa['no_hp_sekdes'])) {
        echo "   ✅ Data kontak sudah lengkap dan akan muncul di halaman desa\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>