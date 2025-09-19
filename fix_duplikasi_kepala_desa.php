<?php
require_once 'config/database.php';

/**
 * Script untuk memperbaiki masalah duplikasi kepala desa
 * di semua desa (pencegahan dan pembersihan)
 */

echo "=== PERBAIKAN DUPLIKASI KEPALA DESA ===\n";
echo "Tanggal: " . date('Y-m-d H:i:s') . "\n\n";

try {
    $db = getDatabase();
    
    // 1. Cari semua desa dengan duplikasi kepala desa
    echo "1. PENCARIAN DUPLIKASI KEPALA DESA\n";
    echo "==================================\n";
    
    $duplikasi = $db->select("
        SELECT 
            d.id as desa_id,
            d.nama_desa,
            d.kecamatan,
            COUNT(CASE WHEN (p.jabatan LIKE '%kepala desa%' OR p.jabatan LIKE '%kades%') AND p.status = 'aktif' THEN 1 END) as jumlah_kepala_desa,
            GROUP_CONCAT(
                CASE WHEN (p.jabatan LIKE '%kepala desa%' OR p.jabatan LIKE '%kades%') AND p.status = 'aktif'
                THEN CONCAT(p.id, ':', p.nama_lengkap, ' (', p.jabatan, ') - ', COALESCE(p.tahun_diangkat, 'N/A')) 
                END SEPARATOR '; '
            ) as daftar_kepala_desa
        FROM desa d
        LEFT JOIN perangkat_desa p ON d.id = p.desa_id
        WHERE d.status = 'aktif'
        GROUP BY d.id, d.nama_desa, d.kecamatan
        HAVING jumlah_kepala_desa > 1
        ORDER BY d.kecamatan, d.nama_desa
    ");
    
    if (empty($duplikasi)) {
        echo "✓ Tidak ada duplikasi kepala desa ditemukan\n";
    } else {
        echo "⚠ Ditemukan " . count($duplikasi) . " desa dengan duplikasi kepala desa:\n\n";
        
        foreach ($duplikasi as $desa) {
            echo "ID {$desa['desa_id']}: {$desa['nama_desa']} - {$desa['kecamatan']}\n";
            echo "  Jumlah Kepala Desa: {$desa['jumlah_kepala_desa']}\n";
            echo "  Detail: {$desa['daftar_kepala_desa']}\n\n";
        }
    }
    
    // 2. Cari duplikasi berdasarkan nama yang sama
    echo "2. PENCARIAN DUPLIKASI BERDASARKAN NAMA\n";
    echo "=======================================\n";
    
    $duplikasi_nama = $db->select("
        SELECT 
            d.id as desa_id,
            d.nama_desa,
            d.kecamatan,
            p.nama_lengkap,
            COUNT(*) as jumlah_duplikat,
            GROUP_CONCAT(CONCAT(p.id, ':', p.jabatan, ' (', COALESCE(p.tahun_diangkat, 'N/A'), ')') SEPARATOR '; ') as detail_duplikat
        FROM desa d
        JOIN perangkat_desa p ON d.id = p.desa_id
        WHERE d.status = 'aktif' AND p.status = 'aktif'
        GROUP BY d.id, p.nama_lengkap
        HAVING COUNT(*) > 1
        ORDER BY d.kecamatan, d.nama_desa, p.nama_lengkap
    ");
    
    if (empty($duplikasi_nama)) {
        echo "✓ Tidak ada duplikasi nama perangkat ditemukan\n";
    } else {
        echo "⚠ Ditemukan " . count($duplikasi_nama) . " duplikasi nama perangkat:\n\n";
        
        foreach ($duplikasi_nama as $dup) {
            echo "ID {$dup['desa_id']}: {$dup['nama_desa']} - {$dup['kecamatan']}\n";
            echo "  Nama: {$dup['nama_lengkap']}\n";
            echo "  Jumlah: {$dup['jumlah_duplikat']}\n";
            echo "  Detail: {$dup['detail_duplikat']}\n\n";
        }
    }
    
    // 3. Perbaikan otomatis untuk duplikasi kepala desa
    echo "3. PERBAIKAN OTOMATIS DUPLIKASI KEPALA DESA\n";
    echo "==========================================\n";
    
    if (!empty($duplikasi)) {
        echo "Memulai perbaikan otomatis...\n\n";
        
        foreach ($duplikasi as $desa) {
            echo "Memperbaiki desa: {$desa['nama_desa']} - {$desa['kecamatan']}\n";
            
            // Ambil semua kepala desa untuk desa ini
            $kepala_desa_list = $db->select("
                SELECT id, nama_lengkap, jabatan, tahun_diangkat, created_at, updated_at
                FROM perangkat_desa 
                WHERE desa_id = ? 
                AND (jabatan LIKE '%kepala desa%' OR jabatan LIKE '%kades%')
                AND status = 'aktif'
                ORDER BY 
                    CASE 
                        WHEN tahun_diangkat IS NOT NULL AND tahun_diangkat != '' THEN tahun_diangkat 
                        ELSE '0000' 
                    END DESC,
                    created_at DESC
            ", [$desa['desa_id']]);
            
            if (count($kepala_desa_list) > 1) {
                // Pertahankan yang terbaru, ubah yang lama
                $kepala_aktif = array_shift($kepala_desa_list); // Yang pertama (terbaru)
                
                echo "  Mempertahankan: {$kepala_aktif['nama_lengkap']} (Tahun: {$kepala_aktif['tahun_diangkat']})\n";
                
                foreach ($kepala_desa_list as $kepala_lama) {
                    // Ubah jabatan menjadi "Mantan Kepala Desa" atau nonaktifkan
                    $new_jabatan = 'Mantan Kepala Desa';
                    
                    $db->update(
                        'perangkat_desa',
                        ['jabatan' => $new_jabatan, 'updated_at' => date('Y-m-d H:i:s')],
                        ['id' => $kepala_lama['id']]
                    );
                    
                    echo "  Diubah: {$kepala_lama['nama_lengkap']} → {$new_jabatan}\n";
                }
            }
            
            echo "\n";
        }
        
        echo "Perbaikan otomatis selesai!\n\n";
    } else {
        echo "Tidak ada duplikasi yang perlu diperbaiki\n\n";
    }
    
    // 4. Perbaikan untuk duplikasi nama
    echo "4. PERBAIKAN DUPLIKASI NAMA PERANGKAT\n";
    echo "====================================\n";
    
    if (!empty($duplikasi_nama)) {
        echo "Menangani duplikasi nama perangkat...\n\n";
        
        foreach ($duplikasi_nama as $dup) {
            echo "Desa: {$dup['nama_desa']} - Nama: {$dup['nama_lengkap']}\n";
            
            // Ambil detail duplikasi
            $detail_perangkat = $db->select("
                SELECT id, nama_lengkap, jabatan, tahun_diangkat, created_at
                FROM perangkat_desa 
                WHERE desa_id = ? AND nama_lengkap = ? AND status = 'aktif'
                ORDER BY 
                    CASE 
                        WHEN tahun_diangkat IS NOT NULL AND tahun_diangkat != '' THEN tahun_diangkat 
                        ELSE '0000' 
                    END DESC,
                    created_at DESC
            ", [$dup['desa_id'], $dup['nama_lengkap']]);
            
            if (count($detail_perangkat) > 1) {
                // Pertahankan yang terbaru
                $perangkat_aktif = array_shift($detail_perangkat);
                
                echo "  Mempertahankan: ID {$perangkat_aktif['id']} - {$perangkat_aktif['jabatan']}\n";
                
                foreach ($detail_perangkat as $perangkat_lama) {
                    // Nonaktifkan duplikat
                    $db->update(
                        'perangkat_desa',
                        ['status' => 'nonaktif', 'updated_at' => date('Y-m-d H:i:s')],
                        ['id' => $perangkat_lama['id']]
                    );
                    
                    echo "  Dinonaktifkan: ID {$perangkat_lama['id']} - {$perangkat_lama['jabatan']}\n";
                }
            }
            
            echo "\n";
        }
        
        echo "Perbaikan duplikasi nama selesai!\n\n";
    } else {
        echo "Tidak ada duplikasi nama yang perlu diperbaiki\n\n";
    }
    
    // 5. Verifikasi hasil perbaikan
    echo "5. VERIFIKASI HASIL PERBAIKAN\n";
    echo "=============================\n";
    
    // Cek ulang duplikasi kepala desa
    $duplikasi_setelah = $db->select("
        SELECT 
            d.id as desa_id,
            d.nama_desa,
            d.kecamatan,
            COUNT(CASE WHEN (p.jabatan LIKE '%kepala desa%' OR p.jabatan LIKE '%kades%') AND p.status = 'aktif' THEN 1 END) as jumlah_kepala_desa
        FROM desa d
        LEFT JOIN perangkat_desa p ON d.id = p.desa_id
        WHERE d.status = 'aktif'
        GROUP BY d.id, d.nama_desa, d.kecamatan
        HAVING jumlah_kepala_desa > 1
        ORDER BY d.kecamatan, d.nama_desa
    ");
    
    if (empty($duplikasi_setelah)) {
        echo "✓ Semua duplikasi kepala desa berhasil diperbaiki!\n";
    } else {
        echo "⚠ Masih ada " . count($duplikasi_setelah) . " desa dengan duplikasi kepala desa\n";
    }
    
    // Statistik akhir
    $stats = $db->select("
        SELECT 
            COUNT(DISTINCT d.id) as total_desa,
            COUNT(DISTINCT CASE WHEN p.jabatan LIKE '%kepala desa%' AND p.status = 'aktif' THEN d.id END) as desa_dengan_kepala,
            COUNT(CASE WHEN p.jabatan LIKE '%kepala desa%' AND p.status = 'aktif' THEN 1 END) as total_kepala_aktif,
            COUNT(CASE WHEN p.jabatan LIKE '%mantan kepala desa%' AND p.status = 'aktif' THEN 1 END) as total_mantan_kepala
        FROM desa d
        LEFT JOIN perangkat_desa p ON d.id = p.desa_id
        WHERE d.status = 'aktif'
    ")[0];
    
    echo "\nSTATISTIK AKHIR:\n";
    echo "- Total desa aktif: {$stats['total_desa']}\n";
    echo "- Desa dengan kepala desa: {$stats['desa_dengan_kepala']}\n";
    echo "- Total kepala desa aktif: {$stats['total_kepala_aktif']}\n";
    echo "- Total mantan kepala desa: {$stats['total_mantan_kepala']}\n";
    
    // 6. Rekomendasi untuk mencegah duplikasi di masa depan
    echo "\n6. REKOMENDASI PENCEGAHAN\n";
    echo "========================\n";
    echo "1. Tambahkan constraint UNIQUE di database untuk (desa_id, jabatan) untuk jabatan kunci\n";
    echo "2. Validasi di form input untuk mencegah duplikasi jabatan\n";
    echo "3. Implementasi soft delete untuk perangkat yang tidak aktif\n";
    echo "4. Review berkala data perangkat desa\n";
    echo "5. Implementasi approval workflow untuk perubahan jabatan\n";
    
    echo "\n=== PERBAIKAN SELESAI ===\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}
?>