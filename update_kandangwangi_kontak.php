<?php
require_once 'config/database.php';

try {
    $db = getDatabase();
    echo "=== UPDATE KONTAK PERSON DESA KANDANGWANGI ===\n\n";
    
    // Data kontak yang akan diupdate
    $data_kontak = [
        'nama_kepala_desa' => 'Suhono',
        'nama_sekdes' => 'Aminah',
        'no_hp_kepala_desa' => '082118745479',
        'no_hp_sekdes' => '082137456789'
    ];
    
    echo "📝 Data yang akan diupdate:\n";
    foreach ($data_kontak as $field => $value) {
        echo "   $field: $value\n";
    }
    echo "\n";
    
    // Update data
    $sql = "UPDATE desa SET 
                nama_kepala_desa = ?, 
                nama_sekdes = ?, 
                no_hp_kepala_desa = ?, 
                no_hp_sekdes = ? 
            WHERE id = 157";
    
    $params = [
        $data_kontak['nama_kepala_desa'],
        $data_kontak['nama_sekdes'],
        $data_kontak['no_hp_kepala_desa'],
        $data_kontak['no_hp_sekdes']
    ];
    
    $result = $db->execute($sql, $params);
    
    if ($result) {
        echo "✅ Data kontak berhasil diupdate!\n\n";
        
        // Verifikasi hasil update
        $verify = $db->select(
            "SELECT id, nama_desa, kecamatan, nama_kepala_desa, nama_sekdes, no_hp_kepala_desa, no_hp_sekdes 
             FROM desa WHERE id = 157"
        );
        
        if (!empty($verify)) {
            $desa = $verify[0];
            echo "🔍 VERIFIKASI DATA SETELAH UPDATE:\n";
            echo "   Nama Desa: {$desa['nama_desa']}\n";
            echo "   Kecamatan: {$desa['kecamatan']}\n";
            echo "   Kepala Desa: {$desa['nama_kepala_desa']}\n";
            echo "   Sekretaris Desa: {$desa['nama_sekdes']}\n";
            echo "   No HP Kepala: {$desa['no_hp_kepala_desa']}\n";
            echo "   No HP Sekdes: {$desa['no_hp_sekdes']}\n\n";
            
            // Analisis tampilan di halaman desa.php
            echo "📱 TAMPILAN DI HALAMAN DESA:\n";
            $kontak_person = $desa['nama_sekdes'] ?: ($desa['nama_kepala_desa'] ?: '-');
            $telepon = $desa['no_hp_sekdes'] ?: '-';
            echo "   Kolom 'Kontak Person': '$kontak_person'\n";
            echo "   Kolom 'Telepon': '$telepon'\n\n";
            
            echo "✅ Kontak person sekarang akan muncul di halaman desa!\n";
        }
    } else {
        echo "❌ Gagal mengupdate data kontak!\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>