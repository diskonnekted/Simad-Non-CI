<?php
require_once 'config/database.php';

try {
    $db = getDatabase();
    echo "=== PERBAIKAN KONTAK PERSON OTOMATIS ===\n\n";
    
    // Daftar ID desa yang bisa diperbaiki otomatis
    $desa_ids = [16, 17, 24, 58, 59, 60, 143, 144, 145, 61, 146, 147, 148, 63, 64, 5, 38, 11, 21, 49, 137, 13, 22, 29, 31, 46, 52, 7, 23, 138, 139, 141, 142, 4, 28, 100, 101, 162, 102, 103, 163, 104, 171, 172, 166, 168, 165, 167, 20, 33, 39, 44, 149, 150, 66, 67, 151, 152, 68, 69, 153, 154, 70, 155, 18, 32, 37, 96, 160, 98, 97, 99, 6, 8, 131, 181, 182, 132, 133, 183, 184, 134, 185, 135, 136, 186, 187, 189, 190, 191, 192, 194, 195, 30, 40, 43, 128, 129, 9, 35, 41, 82, 83, 85, 86, 87, 88, 89, 90, 91, 92, 93, 95, 42, 71, 72, 74, 10, 14, 51, 118, 119, 120, 122, 123, 124, 15, 34, 53, 54, 55, 56, 57, 27, 36, 47, 48, 178, 126, 179, 180, 164, 77, 78, 79, 80, 209, 19, 45, 105, 106, 107, 108, 110, 111, 113, 211, 114, 115, 116, 117];
    
    $berhasil_update = 0;
    $gagal_update = 0;
    $tidak_ada_data = 0;
    
    echo "🔧 Memproses " . count($desa_ids) . " desa...\n\n";
    
    foreach ($desa_ids as $desa_id) {
        // Ambil data desa saat ini
        $desa = $db->select(
            "SELECT id, nama_desa, kecamatan, nama_kepala_desa, nama_sekdes, no_hp_kepala_desa, no_hp_sekdes 
             FROM desa WHERE id = ?",
            [$desa_id]
        );
        
        if (empty($desa)) {
            echo "❌ Desa ID {$desa_id} tidak ditemukan\n";
            $tidak_ada_data++;
            continue;
        }
        
        $desa_data = $desa[0];
        
        // Cari perangkat kepala desa dan sekretaris
        $kepala_desa = $db->select(
            "SELECT nama_lengkap, no_telepon 
             FROM perangkat_desa 
             WHERE desa_id = ? 
             AND (jabatan LIKE '%kepala desa%' OR jabatan LIKE '%kades%' OR jabatan = 'Kepala Desa')
             AND status = 'aktif'
             LIMIT 1",
            [$desa_id]
        );
        
        $sekretaris = $db->select(
            "SELECT nama_lengkap, no_telepon 
             FROM perangkat_desa 
             WHERE desa_id = ? 
             AND (jabatan LIKE '%sekretaris%' OR jabatan LIKE '%sekdes%' OR jabatan = 'Sekretaris Desa')
             AND status = 'aktif'
             LIMIT 1",
            [$desa_id]
        );
        
        $update_fields = [];
        $update_values = [];
        
        // Update nama kepala desa jika kosong dan ada data perangkat
        if (empty($desa_data['nama_kepala_desa']) && !empty($kepala_desa)) {
            $update_fields[] = 'nama_kepala_desa = ?';
            $update_values[] = $kepala_desa[0]['nama_lengkap'];
        }
        
        // Update no HP kepala desa jika kosong dan ada data perangkat
        if (empty($desa_data['no_hp_kepala_desa']) && !empty($kepala_desa) && !empty($kepala_desa[0]['no_telepon'])) {
            $update_fields[] = 'no_hp_kepala_desa = ?';
            $update_values[] = $kepala_desa[0]['no_telepon'];
        }
        
        // Update nama sekretaris jika kosong dan ada data perangkat
        if (empty($desa_data['nama_sekdes']) && !empty($sekretaris)) {
            $update_fields[] = 'nama_sekdes = ?';
            $update_values[] = $sekretaris[0]['nama_lengkap'];
        }
        
        // Update no HP sekretaris jika kosong dan ada data perangkat
        if (empty($desa_data['no_hp_sekdes']) && !empty($sekretaris) && !empty($sekretaris[0]['no_telepon'])) {
            $update_fields[] = 'no_hp_sekdes = ?';
            $update_values[] = $sekretaris[0]['no_telepon'];
        }
        
        if (!empty($update_fields)) {
            $sql = "UPDATE desa SET " . implode(', ', $update_fields) . " WHERE id = ?";
            $update_values[] = $desa_id;
            
            try {
                $result = $db->execute($sql, $update_values);
                if ($result) {
                    echo "✅ {$desa_data['nama_desa']} (ID: {$desa_id}) - Updated: " . implode(', ', $update_fields) . "\n";
                    $berhasil_update++;
                } else {
                    echo "❌ {$desa_data['nama_desa']} (ID: {$desa_id}) - Gagal update\n";
                    $gagal_update++;
                }
            } catch (Exception $e) {
                echo "❌ {$desa_data['nama_desa']} (ID: {$desa_id}) - Error: {$e->getMessage()}\n";
                $gagal_update++;
            }
        } else {
            echo "⚠️  {$desa_data['nama_desa']} (ID: {$desa_id}) - Tidak ada data perangkat untuk update\n";
            $tidak_ada_data++;
        }
    }
    
    echo "\n📊 RINGKASAN PERBAIKAN:\n";
    echo "   Berhasil diupdate: {$berhasil_update}\n";
    echo "   Gagal update: {$gagal_update}\n";
    echo "   Tidak ada data perangkat: {$tidak_ada_data}\n";
    echo "   Total diproses: " . count($desa_ids) . "\n";
    
    if ($berhasil_update > 0) {
        echo "\n✅ Perbaikan kontak person berhasil dilakukan!\n";
        echo "   Silakan cek halaman desa untuk melihat perubahan.\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>