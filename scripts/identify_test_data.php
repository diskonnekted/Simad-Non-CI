<?php
/**
 * Script Identifikasi Data Percobaan
 * 
 * Script ini akan mengidentifikasi data-data percobaan yang perlu dibersihkan
 * sebelum aplikasi masuk ke tahap produksi.
 * 
 * Kriteria data percobaan:
 * 1. Data dengan nama yang mengandung kata 'test', 'contoh', 'demo'
 * 2. Data dengan tanggal sebelum tanggal cutoff produksi
 * 3. Data dengan status tertentu (draft, nonaktif)
 * 4. Data desa dengan informasi tidak lengkap
 * 
 * @author SMD System
 * @date 2025-01-20
 */

require_once '../config/database.php';

// Konfigurasi identifikasi
$production_cutoff_date = '2025-01-20'; // Tanggal mulai produksi
$test_keywords = ['test', 'contoh', 'demo', 'percobaan', 'coba', 'sample'];

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== IDENTIFIKASI DATA PERCOBAAN ===\n\n";
    
    // 1. Identifikasi Desa Percobaan
    echo "1. DESA PERCOBAAN:\n";
    echo "-------------------\n";
    
    $test_desa_conditions = [];
    foreach ($test_keywords as $keyword) {
        $test_desa_conditions[] = "nama_desa LIKE '%$keyword%'";
    }
    $test_desa_sql = "SELECT id, nama_desa, kecamatan, status, created_at 
                      FROM desa 
                      WHERE (" . implode(' OR ', $test_desa_conditions) . ")
                         OR status = 'nonaktif'
                         OR nama_kepala_desa IS NULL 
                         OR nama_kepala_desa = ''
                         OR nama_kepala_desa = 'Belum ada'
                      ORDER BY created_at";
    
    $test_desa = $pdo->query($test_desa_sql)->fetchAll();
    
    if (count($test_desa) > 0) {
        foreach ($test_desa as $desa) {
            echo "- ID: {$desa['id']}, Nama: {$desa['nama_desa']}, Kecamatan: {$desa['kecamatan']}, Status: {$desa['status']}\n";
        }
        echo "Total desa percobaan: " . count($test_desa) . "\n\n";
    } else {
        echo "Tidak ada desa percobaan ditemukan.\n\n";
    }
    
    // 2. Identifikasi Produk Percobaan
    echo "2. PRODUK PERCOBAAN:\n";
    echo "--------------------\n";
    
    $test_produk_conditions = [];
    foreach ($test_keywords as $keyword) {
        $test_produk_conditions[] = "nama_produk LIKE '%$keyword%'";
    }
    $test_produk_sql = "SELECT id, kode_produk, nama_produk, status, created_at 
                        FROM produk 
                        WHERE (" . implode(' OR ', $test_produk_conditions) . ")
                           OR status = 'nonaktif'
                           OR stok_tersedia = 0
                        ORDER BY created_at";
    
    $test_produk = $pdo->query($test_produk_sql)->fetchAll();
    
    if (count($test_produk) > 0) {
        foreach ($test_produk as $produk) {
            echo "- ID: {$produk['id']}, Kode: {$produk['kode_produk']}, Nama: {$produk['nama_produk']}, Status: {$produk['status']}\n";
        }
        echo "Total produk percobaan: " . count($test_produk) . "\n\n";
    } else {
        echo "Tidak ada produk percobaan ditemukan.\n\n";
    }
    
    // 3. Identifikasi Transaksi Percobaan
    echo "3. TRANSAKSI PERCOBAAN:\n";
    echo "-----------------------\n";
    
    $test_transaksi_sql = "SELECT t.id, t.nomor_invoice, d.nama_desa, t.total_amount, t.status_transaksi, t.created_at 
                           FROM transaksi t
                           LEFT JOIN desa d ON t.desa_id = d.id
                           WHERE t.status_transaksi = 'draft'
                              OR t.catatan LIKE '%test%'
                              OR t.catatan LIKE '%percobaan%'
                              OR t.total_amount < 50000
                           ORDER BY t.created_at";
    
    $test_transaksi = $pdo->query($test_transaksi_sql)->fetchAll();
    
    if (count($test_transaksi) > 0) {
        foreach ($test_transaksi as $transaksi) {
            echo "- ID: {$transaksi['id']}, Invoice: {$transaksi['nomor_invoice']}, Desa: {$transaksi['nama_desa']}, Amount: {$transaksi['total_amount']}, Status: {$transaksi['status_transaksi']}\n";
        }
        echo "Total transaksi percobaan: " . count($test_transaksi) . "\n\n";
    } else {
        echo "Tidak ada transaksi percobaan ditemukan.\n\n";
    }
    
    // 4. Identifikasi Activity Logs Lama
    echo "4. ACTIVITY LOGS LAMA:\n";
    echo "----------------------\n";
    
    $old_logs_sql = "SELECT COUNT(*) as total 
                     FROM activity_logs 
                     WHERE created_at < '$production_cutoff_date'";
    
    $old_logs = $pdo->query($old_logs_sql)->fetch();
    echo "Total activity logs sebelum $production_cutoff_date: {$old_logs['total']}\n\n";
    
    // 5. Identifikasi Data Kategori Tidak Terpakai
    echo "5. KATEGORI TIDAK TERPAKAI:\n";
    echo "---------------------------\n";
    
    $unused_kategori_sql = "SELECT k.id, k.nama, k.status 
                            FROM kategori k
                            LEFT JOIN produk p ON k.id = p.kategori_id
                            WHERE p.kategori_id IS NULL
                               OR k.status = 'nonaktif'";
    
    $unused_kategori = $pdo->query($unused_kategori_sql)->fetchAll();
    
    if (count($unused_kategori) > 0) {
        foreach ($unused_kategori as $kategori) {
            echo "- ID: {$kategori['id']}, Nama: {$kategori['nama']}, Status: {$kategori['status']}\n";
        }
        echo "Total kategori tidak terpakai: " . count($unused_kategori) . "\n\n";
    } else {
        echo "Semua kategori sedang digunakan.\n\n";
    }
    
    // 6. Identifikasi Biaya Operasional Tidak Aktif
    echo "6. BIAYA OPERASIONAL NONAKTIF:\n";
    echo "------------------------------\n";
    
    $inactive_biaya_sql = "SELECT id, kode_biaya, nama_biaya, status 
                           FROM biaya_operasional 
                           WHERE status = 'nonaktif'";
    
    $inactive_biaya = $pdo->query($inactive_biaya_sql)->fetchAll();
    
    if (count($inactive_biaya) > 0) {
        foreach ($inactive_biaya as $biaya) {
            echo "- ID: {$biaya['id']}, Kode: {$biaya['kode_biaya']}, Nama: {$biaya['nama_biaya']}\n";
        }
        echo "Total biaya operasional nonaktif: " . count($inactive_biaya) . "\n\n";
    } else {
        echo "Semua biaya operasional aktif.\n\n";
    }
    
    // Summary
    echo "=== RINGKASAN IDENTIFIKASI ===\n";
    echo "Desa percobaan: " . count($test_desa) . "\n";
    echo "Produk percobaan: " . count($test_produk) . "\n";
    echo "Transaksi percobaan: " . count($test_transaksi) . "\n";
    echo "Activity logs lama: {$old_logs['total']}\n";
    echo "Kategori tidak terpakai: " . count($unused_kategori) . "\n";
    echo "Biaya operasional nonaktif: " . count($inactive_biaya) . "\n";
    echo "\nSilakan review data di atas sebelum melakukan pembersihan.\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>