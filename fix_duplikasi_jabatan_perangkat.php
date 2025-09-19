<?php
require_once 'config/database.php';

/**
 * Script untuk memperbaiki duplikasi jabatan perangkat desa
 * Menangani duplikasi jabatan seperti Kasi Kesejahteraan, Kasi Pelayanan, dll
 */

echo "=== PERBAIKAN DUPLIKASI JABATAN PERANGKAT DESA ===\n";
echo "Tanggal: " . date('Y-m-d H:i:s') . "\n\n";

// Inisialisasi koneksi database
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
echo "✓ Koneksi database berhasil\n\n";

// 1. Analisis duplikasi jabatan di database
echo "1. ANALISIS DUPLIKASI JABATAN\n";
echo "==============================\n";

try {
    // Query untuk mencari duplikasi jabatan per desa
    $query = "
        SELECT 
            d.nama_desa,
            pd.desa_id,
            pd.jabatan,
            COUNT(*) as jumlah,
            GROUP_CONCAT(CONCAT(pd.nama_lengkap, ' (ID:', pd.id, ')') SEPARATOR '; ') as daftar_nama
        FROM perangkat_desa pd
        JOIN desa d ON pd.desa_id = d.id
        WHERE pd.status = 'aktif'
        GROUP BY pd.desa_id, pd.jabatan
        HAVING COUNT(*) > 1
        ORDER BY d.nama_desa, pd.jabatan
    ";
    
    $result = $conn->query($query);
    
    if ($result->num_rows > 0) {
        echo "Ditemukan duplikasi jabatan:\n";
        echo str_repeat('-', 80) . "\n";
        
        $duplikasi_count = 0;
        while ($row = $result->fetch_assoc()) {
            $duplikasi_count++;
            echo "{$duplikasi_count}. Desa: {$row['nama_desa']} - Jabatan: {$row['jabatan']}\n";
            echo "   Jumlah: {$row['jumlah']}\n";
            echo "   Detail: {$row['daftar_nama']}\n\n";
        }
        
        echo "Total duplikasi jabatan ditemukan: {$duplikasi_count}\n\n";
    } else {
        echo "✓ Tidak ada duplikasi jabatan ditemukan\n\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n\n";
}

// 2. Fokus pada desa Bandingan (ID 13)
echo "2. ANALISIS KHUSUS DESA BANDINGAN\n";
echo "=================================\n";

try {
    $stmt = $conn->prepare("
        SELECT id, nama_lengkap, jabatan, tahun_diangkat, no_sk 
        FROM perangkat_desa 
        WHERE desa_id = 13 AND status = 'aktif' 
        ORDER BY jabatan, tahun_diangkat DESC
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    
    $jabatan_groups = [];
    while ($row = $result->fetch_assoc()) {
        $jabatan_clean = trim(strtolower($row['jabatan']));
        // Normalisasi nama jabatan
        if (strpos($jabatan_clean, 'kasi kesejahteraan') !== false || 
            strpos($jabatan_clean, 'kasi kesra') !== false ||
            strpos($jabatan_clean, 'kesejahteraanj') !== false) {
            $jabatan_clean = 'kasi kesejahteraan';
        }
        
        if (!isset($jabatan_groups[$jabatan_clean])) {
            $jabatan_groups[$jabatan_clean] = [];
        }
        $jabatan_groups[$jabatan_clean][] = $row;
    }
    
    echo "Perangkat desa Bandingan berdasarkan jabatan:\n";
    echo str_repeat('-', 60) . "\n";
    
    foreach ($jabatan_groups as $jabatan => $members) {
        echo "Jabatan: " . ucwords($jabatan) . " (" . count($members) . " orang)\n";
        foreach ($members as $i => $member) {
            echo "  " . ($i + 1) . ". {$member['nama_lengkap']} - SK: {$member['no_sk']} - Tahun: {$member['tahun_diangkat']}\n";
        }
        echo "\n";
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n\n";
}

// 3. Perbaikan otomatis duplikasi jabatan
echo "3. PERBAIKAN OTOMATIS DUPLIKASI JABATAN\n";
echo "=======================================\n";

try {
    // Ambil semua duplikasi jabatan
    $query = "
        SELECT 
            pd.desa_id,
            pd.jabatan,
            pd.id,
            pd.nama_lengkap,
            pd.tahun_diangkat,
            pd.no_sk,
            d.nama_desa
        FROM perangkat_desa pd
        JOIN desa d ON pd.desa_id = d.id
        WHERE pd.status = 'aktif'
        AND pd.desa_id IN (
            SELECT desa_id 
            FROM perangkat_desa 
            WHERE status = 'aktif'
            GROUP BY desa_id, jabatan 
            HAVING COUNT(*) > 1
        )
        ORDER BY pd.desa_id, pd.jabatan, pd.tahun_diangkat DESC
    ";
    
    $result = $conn->query($query);
    $duplikasi_data = [];
    
    while ($row = $result->fetch_assoc()) {
        $key = $row['desa_id'] . '_' . trim(strtolower($row['jabatan']));
        
        // Normalisasi jabatan untuk pengecekan duplikasi
        $jabatan_clean = trim(strtolower($row['jabatan']));
        if (strpos($jabatan_clean, 'kasi kesejahteraan') !== false || 
            strpos($jabatan_clean, 'kasi kesra') !== false ||
            strpos($jabatan_clean, 'kesejahteraanj') !== false) {
            $key = $row['desa_id'] . '_kasi_kesejahteraan';
        }
        
        if (!isset($duplikasi_data[$key])) {
            $duplikasi_data[$key] = [];
        }
        $duplikasi_data[$key][] = $row;
    }
    
    $perbaikan_count = 0;
    
    foreach ($duplikasi_data as $key => $records) {
        if (count($records) > 1) {
            // Urutkan berdasarkan tahun diangkat (terbaru dulu)
            usort($records, function($a, $b) {
                return $b['tahun_diangkat'] <=> $a['tahun_diangkat'];
            });
            
            // Pertahankan yang terbaru, nonaktifkan yang lain
            $kept = array_shift($records); // Yang pertama (terbaru)
            
            echo "Desa: {$kept['nama_desa']} - Jabatan: {$kept['jabatan']}\n";
            echo "  Mempertahankan: ID {$kept['id']} - {$kept['nama_lengkap']} (Tahun: {$kept['tahun_diangkat']})\n";
            
            foreach ($records as $record) {
                // Nonaktifkan record duplikat
                $update_stmt = $conn->prepare("UPDATE perangkat_desa SET status = 'nonaktif' WHERE id = ?");
                $update_stmt->bind_param("i", $record['id']);
                
                if ($update_stmt->execute()) {
                    echo "  Dinonaktifkan: ID {$record['id']} - {$record['nama_lengkap']} (Tahun: {$record['tahun_diangkat']})\n";
                    $perbaikan_count++;
                } else {
                    echo "  ❌ Gagal menonaktifkan: ID {$record['id']} - {$record['nama_lengkap']}\n";
                }
                
                $update_stmt->close();
            }
            echo "\n";
        }
    }
    
    if ($perbaikan_count > 0) {
        echo "✓ Berhasil menonaktifkan {$perbaikan_count} record duplikat\n\n";
    } else {
        echo "✓ Tidak ada duplikasi yang perlu diperbaiki\n\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n\n";
}

// 4. Verifikasi hasil perbaikan
echo "4. VERIFIKASI HASIL PERBAIKAN\n";
echo "=============================\n";

try {
    // Cek duplikasi setelah perbaikan
    $query = "
        SELECT 
            d.nama_desa,
            pd.jabatan,
            COUNT(*) as jumlah
        FROM perangkat_desa pd
        JOIN desa d ON pd.desa_id = d.id
        WHERE pd.status = 'aktif'
        GROUP BY pd.desa_id, pd.jabatan
        HAVING COUNT(*) > 1
        ORDER BY d.nama_desa, pd.jabatan
    ";
    
    $result = $conn->query($query);
    
    if ($result->num_rows > 0) {
        echo "⚠ Masih ada duplikasi jabatan:\n";
        while ($row = $result->fetch_assoc()) {
            echo "  - {$row['nama_desa']}: {$row['jabatan']} ({$row['jumlah']} orang)\n";
        }
    } else {
        echo "✓ Semua duplikasi jabatan berhasil diperbaiki!\n";
    }
    
    // Statistik akhir
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM perangkat_desa WHERE status = 'aktif'");
    $stmt->execute();
    $total_aktif = $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();
    
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM perangkat_desa WHERE status = 'nonaktif'");
    $stmt->execute();
    $total_nonaktif = $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();
    
    echo "\nSTATISTIK AKHIR:\n";
    echo "- Total perangkat aktif: {$total_aktif}\n";
    echo "- Total perangkat nonaktif: {$total_nonaktif}\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n5. REKOMENDASI LANJUTAN\n";
echo "======================\n";
echo "1. Update fungsi getPerangkatDesa untuk mencegah duplikasi jabatan\n";
echo "2. Tambahkan validasi di form input perangkat desa\n";
echo "3. Implementasi constraint UNIQUE untuk (desa_id, jabatan)\n";
echo "4. Review berkala data perangkat desa\n";
echo "5. Implementasi approval workflow untuk perubahan jabatan\n\n";

echo "=== PERBAIKAN SELESAI ===\n";

?>