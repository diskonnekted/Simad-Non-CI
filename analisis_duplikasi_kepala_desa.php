<?php
require_once 'config/database.php';

/**
 * Script untuk menganalisis dan memperbaiki duplikasi kepala desa
 * di data perangkat desa
 */

echo "=== ANALISIS DUPLIKASI KEPALA DESA ===\n";
echo "Tanggal: " . date('Y-m-d H:i:s') . "\n\n";

try {
    $db = getDatabase();
    
    // 1. Analisis duplikasi kepala desa di database
    echo "1. ANALISIS DUPLIKASI KEPALA DESA DI DATABASE\n";
    echo "============================================\n";
    
    $duplikasi_db = $db->select("
        SELECT 
            d.id as desa_id,
            d.nama_desa,
            d.kecamatan,
            COUNT(CASE WHEN p.jabatan LIKE '%kepala desa%' OR p.jabatan LIKE '%kades%' THEN 1 END) as jumlah_kepala_desa,
            GROUP_CONCAT(
                CASE WHEN p.jabatan LIKE '%kepala desa%' OR p.jabatan LIKE '%kades%' 
                THEN CONCAT(p.nama_lengkap, ' (', p.jabatan, ')') 
                END SEPARATOR '; '
            ) as daftar_kepala_desa
        FROM desa d
        LEFT JOIN perangkat_desa p ON d.id = p.desa_id AND p.status = 'aktif'
        WHERE d.status = 'aktif'
        GROUP BY d.id, d.nama_desa, d.kecamatan
        HAVING jumlah_kepala_desa > 1
        ORDER BY d.kecamatan, d.nama_desa
    ");
    
    if (empty($duplikasi_db)) {
        echo "✓ Tidak ada duplikasi kepala desa di database\n";
    } else {
        echo "⚠ Ditemukan " . count($duplikasi_db) . " desa dengan duplikasi kepala desa:\n\n";
        
        foreach ($duplikasi_db as $desa) {
            echo "ID {$desa['desa_id']}: {$desa['nama_desa']} - {$desa['kecamatan']}\n";
            echo "  Jumlah Kepala Desa: {$desa['jumlah_kepala_desa']}\n";
            echo "  Daftar: {$desa['daftar_kepala_desa']}\n\n";
        }
    }
    
    // 2. Analisis duplikasi di data CSV
    echo "2. ANALISIS DUPLIKASI KEPALA DESA DI CSV\n";
    echo "=======================================\n";
    
    $csv_file = 'data-desa.csv';
    $duplikasi_csv = [];
    
    if (file_exists($csv_file)) {
        $handle = fopen($csv_file, 'r');
        $header = fgetcsv($handle); // Skip header
        
        $desa_kepala = [];
        $baris = 1;
        
        while (($data = fgetcsv($handle)) !== FALSE) {
            $baris++;
            
            if (count($data) < 11) continue;
            
            $nama_desa = trim(preg_replace('/^desa\s+/i', '', trim($data[1])));
            $kecamatan = trim($data[2]);
            $nama_lengkap = trim($data[3]);
            $jabatan = trim($data[10]);
            
            // Cek jika jabatan adalah kepala desa
            if (stripos($jabatan, 'kepala desa') !== false || stripos($jabatan, 'kades') !== false) {
                $key = strtolower($nama_desa . '|' . $kecamatan);
                
                if (!isset($desa_kepala[$key])) {
                    $desa_kepala[$key] = [
                        'nama_desa' => $nama_desa,
                        'kecamatan' => $kecamatan,
                        'kepala_desa' => []
                    ];
                }
                
                $desa_kepala[$key]['kepala_desa'][] = [
                    'nama' => $nama_lengkap,
                    'jabatan' => $jabatan,
                    'baris' => $baris
                ];
            }
        }
        fclose($handle);
        
        // Filter yang memiliki lebih dari 1 kepala desa
        foreach ($desa_kepala as $key => $data) {
            if (count($data['kepala_desa']) > 1) {
                $duplikasi_csv[] = $data;
            }
        }
        
        if (empty($duplikasi_csv)) {
            echo "✓ Tidak ada duplikasi kepala desa di CSV\n";
        } else {
            echo "⚠ Ditemukan " . count($duplikasi_csv) . " desa dengan duplikasi kepala desa di CSV:\n\n";
            
            foreach ($duplikasi_csv as $desa) {
                echo "{$desa['nama_desa']} - {$desa['kecamatan']}\n";
                echo "  Jumlah Kepala Desa: " . count($desa['kepala_desa']) . "\n";
                
                foreach ($desa['kepala_desa'] as $kepala) {
                    echo "  - {$kepala['nama']} ({$kepala['jabatan']}) - Baris {$kepala['baris']}\n";
                }
                echo "\n";
            }
        }
    } else {
        echo "⚠ File CSV tidak ditemukan: {$csv_file}\n";
    }
    
    // 3. Fokus pada desa Bandingan Bawang
    echo "3. ANALISIS KHUSUS DESA BANDINGAN BAWANG\n";
    echo "=======================================\n";
    
    // Cari desa Bandingan di kecamatan Bawang
    $bandingan_bawang = $db->select("
        SELECT id, nama_desa, kecamatan
        FROM desa 
        WHERE LOWER(nama_desa) LIKE '%bandingan%' 
        AND LOWER(kecamatan) = 'bawang'
        AND status = 'aktif'
    ");
    
    if (empty($bandingan_bawang)) {
        echo "⚠ Desa Bandingan di Kecamatan Bawang tidak ditemukan\n";
    } else {
        $desa = $bandingan_bawang[0];
        echo "Desa ditemukan: ID {$desa['id']} - {$desa['nama_desa']} - {$desa['kecamatan']}\n\n";
        
        // Ambil semua perangkat dengan jabatan kepala desa
        $kepala_desa_list = $db->select("
            SELECT nama_lengkap, jabatan, no_telepon, alamat, tahun_diangkat, no_sk, created_at
            FROM perangkat_desa 
            WHERE desa_id = ? 
            AND (jabatan LIKE '%kepala desa%' OR jabatan LIKE '%kades%')
            AND status = 'aktif'
            ORDER BY created_at ASC
        ", [$desa['id']]);
        
        if (empty($kepala_desa_list)) {
            echo "✓ Tidak ada data kepala desa di database untuk desa ini\n";
        } else {
            echo "Data Kepala Desa di Database (" . count($kepala_desa_list) . " record):\n";
            
            foreach ($kepala_desa_list as $i => $kepala) {
                echo "  " . ($i + 1) . ". {$kepala['nama_lengkap']}\n";
                echo "     Jabatan: {$kepala['jabatan']}\n";
                echo "     Telepon: {$kepala['no_telepon']}\n";
                echo "     Alamat: {$kepala['alamat']}\n";
                echo "     Tahun Diangkat: {$kepala['tahun_diangkat']}\n";
                echo "     No SK: {$kepala['no_sk']}\n";
                echo "     Dibuat: {$kepala['created_at']}\n\n";
            }
        }
        
        // Cek juga di CSV untuk desa Bandingan Bawang
        echo "Data Kepala Desa di CSV:\n";
        
        if (file_exists($csv_file)) {
            $handle = fopen($csv_file, 'r');
            $header = fgetcsv($handle);
            $baris = 1;
            $found_csv = [];
            
            while (($data = fgetcsv($handle)) !== FALSE) {
                $baris++;
                
                if (count($data) < 11) continue;
                
                $nama_desa_csv = trim(preg_replace('/^desa\s+/i', '', trim($data[1])));
                $kecamatan_csv = trim($data[2]);
                $jabatan_csv = trim($data[10]);
                
                if (strtolower($nama_desa_csv) === 'bandingan' && 
                    strtolower($kecamatan_csv) === 'bawang' &&
                    (stripos($jabatan_csv, 'kepala desa') !== false || stripos($jabatan_csv, 'kades') !== false)) {
                    
                    $found_csv[] = [
                        'nama' => trim($data[3]),
                        'jabatan' => $jabatan_csv,
                        'telepon' => trim($data[6]),
                        'alamat' => trim($data[5]),
                        'baris' => $baris
                    ];
                }
            }
            fclose($handle);
            
            if (empty($found_csv)) {
                echo "✓ Tidak ada data kepala desa di CSV untuk desa ini\n";
            } else {
                echo "Ditemukan " . count($found_csv) . " record kepala desa di CSV:\n";
                
                foreach ($found_csv as $i => $kepala) {
                    echo "  " . ($i + 1) . ". {$kepala['nama']} (Baris {$kepala['baris']})\n";
                    echo "     Jabatan: {$kepala['jabatan']}\n";
                    echo "     Telepon: {$kepala['telepon']}\n";
                    echo "     Alamat: {$kepala['alamat']}\n\n";
                }
            }
        }
    }
    
    // 4. Rekomendasi perbaikan
    echo "4. REKOMENDASI PERBAIKAN\n";
    echo "=======================\n";
    
    if (!empty($duplikasi_db) || !empty($duplikasi_csv)) {
        echo "MASALAH DITEMUKAN - Perlu perbaikan:\n\n";
        
        echo "A. Untuk Database:\n";
        if (!empty($duplikasi_db)) {
            echo "   - Identifikasi kepala desa yang benar (berdasarkan tahun diangkat terbaru)\n";
            echo "   - Ubah jabatan kepala desa lama menjadi 'Mantan Kepala Desa' atau hapus\n";
            echo "   - Pastikan hanya ada 1 kepala desa aktif per desa\n";
        } else {
            echo "   ✓ Database sudah bersih\n";
        }
        
        echo "\nB. Untuk CSV:\n";
        if (!empty($duplikasi_csv)) {
            echo "   - Review dan bersihkan data CSV\n";
            echo "   - Pastikan hanya ada 1 kepala desa per desa\n";
            echo "   - Re-import data yang sudah dibersihkan\n";
        } else {
            echo "   ✓ CSV sudah bersih\n";
        }
        
        echo "\nC. Langkah Perbaikan Otomatis:\n";
        echo "   1. Backup database terlebih dahulu\n";
        echo "   2. Jalankan script perbaikan otomatis\n";
        echo "   3. Verifikasi hasil perbaikan\n";
        echo "   4. Update data kontak person di tabel desa\n";
    } else {
        echo "✓ TIDAK ADA MASALAH DUPLIKASI DITEMUKAN\n";
        echo "Semua desa sudah memiliki maksimal 1 kepala desa\n";
    }
    
    echo "\n=== ANALISIS SELESAI ===\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
?>