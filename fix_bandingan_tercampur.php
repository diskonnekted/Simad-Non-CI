<?php
require_once 'config/database.php';

try {
    echo "=== PERBAIKAN DATA BANDINGAN YANG TERCAMPUR ===\n\n";
    
    $db = getDatabase();
    
    // Data update berdasarkan analisis CSV
    $updates = [
        [
            'id' => 13,
            'kecamatan' => 'Bawang',
            'nama_kepala_desa' => 'NURUL HASTUTI CANDRA DESINTA',
            'no_hp_kepala_desa' => '08112925050',
            'nama_sekdes' => 'AGUS HERIYANTO',
            'no_hp_sekdes' => '085227988122'
        ],
        [
            'id' => 14,
            'kecamatan' => 'Rakit',
            'nama_kepala_desa' => 'WAHITO',
            'no_hp_kepala_desa' => '085201191100',
            'nama_sekdes' => 'SLAMET RIYADI',
            'no_hp_sekdes' => '082367220962'
        ],
        [
            'id' => 15,
            'kecamatan' => 'Sigaluh',
            'nama_kepala_desa' => 'SLAMET FIANTO',
            'no_hp_kepala_desa' => '085227227222',
            'nama_sekdes' => 'WIDI LASMONO',
            'no_hp_sekdes' => '085326723003'
        ]
    ];
    
    echo "📊 VERIFIKASI DATA SEBELUM UPDATE:\n";
    echo str_repeat("=", 60) . "\n";
    
    // Verifikasi data saat ini
    foreach ($updates as $update_data) {
        $current = $db->select(
            "SELECT id, nama_desa, kecamatan, nama_kepala_desa, nama_sekdes, no_hp_kepala_desa, no_hp_sekdes
             FROM desa WHERE id = ?",
            [$update_data['id']]
        )[0] ?? null;
        
        if ($current) {
            echo "\n🏛️  {$current['nama_desa']} - Kec. {$current['kecamatan']} (ID: {$current['id']})\n";
            echo "   DATA SAAT INI:\n";
            echo "   • Kepala Desa: " . ($current['nama_kepala_desa'] ?: 'KOSONG') . "\n";
            echo "   • HP Kepala: " . ($current['no_hp_kepala_desa'] ?: 'KOSONG') . "\n";
            echo "   • Sekretaris: " . ($current['nama_sekdes'] ?: 'KOSONG') . "\n";
            echo "   • HP Sekdes: " . ($current['no_hp_sekdes'] ?: 'KOSONG') . "\n";
            
            echo "   DATA YANG AKAN DIUPDATE:\n";
            echo "   • Kepala Desa: {$update_data['nama_kepala_desa']}\n";
            echo "   • HP Kepala: {$update_data['no_hp_kepala_desa']}\n";
            echo "   • Sekretaris: {$update_data['nama_sekdes']}\n";
            echo "   • HP Sekdes: {$update_data['no_hp_sekdes']}\n";
            
            // Cek apakah ada perubahan
            $ada_perubahan = (
                $current['nama_kepala_desa'] !== $update_data['nama_kepala_desa'] ||
                $current['no_hp_kepala_desa'] !== $update_data['no_hp_kepala_desa'] ||
                $current['nama_sekdes'] !== $update_data['nama_sekdes'] ||
                $current['no_hp_sekdes'] !== $update_data['no_hp_sekdes']
            );
            
            echo "   STATUS: " . ($ada_perubahan ? "⚠️  PERLU UPDATE" : "✅ SUDAH SESUAI") . "\n";
        } else {
            echo "❌ Desa ID {$update_data['id']} tidak ditemukan!\n";
        }
    }
    
    echo "\n" . str_repeat("=", 60) . "\n";
    echo "🔧 MELAKUKAN UPDATE DATA...\n\n";
    
    $updated_count = 0;
    $skipped_count = 0;
    
    foreach ($updates as $update_data) {
        // Cek data saat ini
        $current = $db->select(
            "SELECT nama_kepala_desa, nama_sekdes, no_hp_kepala_desa, no_hp_sekdes
             FROM desa WHERE id = ?",
            [$update_data['id']]
        )[0] ?? null;
        
        if (!$current) {
            echo "❌ Desa ID {$update_data['id']} tidak ditemukan, skip!\n";
            $skipped_count++;
            continue;
        }
        
        // Cek apakah perlu update
        $perlu_update = (
            $current['nama_kepala_desa'] !== $update_data['nama_kepala_desa'] ||
            $current['no_hp_kepala_desa'] !== $update_data['no_hp_kepala_desa'] ||
            $current['nama_sekdes'] !== $update_data['nama_sekdes'] ||
            $current['no_hp_sekdes'] !== $update_data['no_hp_sekdes']
        );
        
        if (!$perlu_update) {
            echo "✅ Bandingan Kec. {$update_data['kecamatan']} (ID: {$update_data['id']}) sudah sesuai, skip!\n";
            $skipped_count++;
            continue;
        }
        
        // Lakukan update
        try {
            $result = $db->update(
                'desa',
                [
                    'nama_kepala_desa' => $update_data['nama_kepala_desa'],
                    'no_hp_kepala_desa' => $update_data['no_hp_kepala_desa'],
                    'nama_sekdes' => $update_data['nama_sekdes'],
                    'no_hp_sekdes' => $update_data['no_hp_sekdes']
                ],
                ['id' => $update_data['id']]
            );
            
            if ($result) {
                echo "✅ Berhasil update Bandingan Kec. {$update_data['kecamatan']} (ID: {$update_data['id']})\n";
                $updated_count++;
            } else {
                echo "❌ Gagal update Bandingan Kec. {$update_data['kecamatan']} (ID: {$update_data['id']})\n";
            }
        } catch (Exception $e) {
            echo "❌ Error update Bandingan Kec. {$update_data['kecamatan']}: " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n" . str_repeat("=", 60) . "\n";
    echo "📊 RINGKASAN UPDATE:\n";
    echo "   • Berhasil diupdate: {$updated_count} desa\n";
    echo "   • Dilewati (sudah sesuai): {$skipped_count} desa\n";
    echo "   • Total diproses: " . count($updates) . " desa\n\n";
    
    // Verifikasi hasil update
    if ($updated_count > 0) {
        echo "🔍 VERIFIKASI HASIL UPDATE:\n";
        echo str_repeat("-", 40) . "\n";
        
        foreach ($updates as $update_data) {
            $updated = $db->select(
                "SELECT id, nama_desa, kecamatan, nama_kepala_desa, nama_sekdes, no_hp_kepala_desa, no_hp_sekdes
                 FROM desa WHERE id = ?",
                [$update_data['id']]
            )[0] ?? null;
            
            if ($updated) {
                echo "\n✅ {$updated['nama_desa']} - Kec. {$updated['kecamatan']} (ID: {$updated['id']})\n";
                echo "   • Kepala Desa: {$updated['nama_kepala_desa']} ({$updated['no_hp_kepala_desa']})\n";
                echo "   • Sekretaris: {$updated['nama_sekdes']} ({$updated['no_hp_sekdes']})\n";
            }
        }
    }
    
    echo "\n💡 LANGKAH SELANJUTNYA:\n";
    echo "1. ✅ Verifikasi tampilan halaman desa untuk ketiga Bandingan\n";
    echo "2. ✅ Test kontak person apakah nomor HP masih aktif\n";
    echo "3. ✅ Jalankan verifikasi ulang hasil perbaikan\n";
    echo "4. ✅ Update dokumentasi perubahan data\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>