<?php
/**
 * Script Import Produk Final - Mengatasi Error Foreign Key Constraint
 * Menggunakan struktur tabel produk yang benar
 */

require_once 'config/database.php';

$db = new Database();
$conn = $db->getConnection();

// Fungsi untuk validasi vendor_id
function isValidVendorId($conn, $vendor_id) {
    $query = "SELECT id FROM vendor WHERE id = :vendor_id AND status = 'aktif'";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':vendor_id', $vendor_id);
    $stmt->execute();
    return $stmt->rowCount() > 0;
}

// Fungsi untuk mendapatkan vendor_id default
function getDefaultVendorId($conn) {
    $query = "SELECT id FROM vendor WHERE status = 'aktif' ORDER BY id LIMIT 1";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? $result['id'] : 1;
}

// Fungsi untuk cek duplikasi kode_produk
function isDuplicateProduct($conn, $kode_produk) {
    $query = "SELECT id FROM produk WHERE kode_produk = :kode_produk";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':kode_produk', $kode_produk);
    $stmt->execute();
    return $stmt->rowCount() > 0;
}

try {
    echo "=== IMPORT PRODUK FINAL ===\n";
    
    $csv_file = 'produk_all_160_fixed.csv';
    
    if (!file_exists($csv_file)) {
        throw new Exception("File CSV tidak ditemukan: {$csv_file}");
    }
    
    // Buka file CSV
    $handle = fopen($csv_file, 'r');
    if (!$handle) {
        throw new Exception("Tidak dapat membuka file CSV");
    }
    
    // Baca header
    $header = fgetcsv($handle);
    echo "📋 Header CSV: " . implode(', ', $header) . "\n\n";
    
    // Mapping kolom
    $column_map = [];
    foreach ($header as $index => $column) {
        $column_map[trim($column)] = $index;
    }
    
    // Validasi kolom yang diperlukan
    $required_columns = ['kode_produk', 'nama_produk', 'vendor_id'];
    foreach ($required_columns as $col) {
        if (!isset($column_map[$col])) {
            throw new Exception("Kolom '{$col}' tidak ditemukan dalam CSV");
        }
    }
    
    // Statistik import
    $stats = [
        'total_rows' => 0,
        'success' => 0,
        'skipped_duplicate' => 0,
        'fixed_vendor' => 0,
        'errors' => 0
    ];
    
    $default_vendor_id = getDefaultVendorId($conn);
    echo "🔧 Default vendor_id: {$default_vendor_id}\n";
    
    // Mulai transaksi
    $conn->beginTransaction();
    
    // Proses setiap baris
    $line_number = 1;
    while (($data = fgetcsv($handle)) !== FALSE) {
        $line_number++;
        $stats['total_rows']++;
        
        try {
            // Ambil data dari CSV dengan mapping yang benar
            $kode_produk = trim($data[$column_map['kode_produk']] ?? '');
            $nama_produk = trim($data[$column_map['nama_produk']] ?? '');
            $vendor_id = intval($data[$column_map['vendor_id']] ?? $default_vendor_id);
            $kategori_id = intval($data[$column_map['kategori_id']] ?? 1);
            
            // Validasi data dasar
            if (empty($kode_produk) || empty($nama_produk)) {
                echo "⚠️  Baris {$line_number}: Data tidak lengkap, dilewati\n";
                $stats['errors']++;
                continue;
            }
            
            // Cek duplikasi
            if (isDuplicateProduct($conn, $kode_produk)) {
                echo "🔄 Baris {$line_number}: Produk {$kode_produk} sudah ada, dilewati\n";
                $stats['skipped_duplicate']++;
                continue;
            }
            
            // Validasi dan perbaiki vendor_id
            if (!isValidVendorId($conn, $vendor_id)) {
                echo "🔧 Baris {$line_number}: vendor_id {$vendor_id} tidak valid, diubah ke {$default_vendor_id}\n";
                $vendor_id = $default_vendor_id;
                $stats['fixed_vendor']++;
            }
            
            // Siapkan data untuk insert dengan kolom yang benar
            $insert_data = [
                'kode_produk' => $kode_produk,
                'nama_produk' => $nama_produk,
                'vendor_id' => $vendor_id,
                'kategori_id' => $kategori_id,
                'jenis' => trim($data[$column_map['jenis']] ?? 'barang_it'), // Kolom jenis langsung
                'deskripsi' => trim($data[$column_map['deskripsi']] ?? ''), // Kolom deskripsi langsung
                'spesifikasi' => trim($data[$column_map['spesifikasi']] ?? ''), // Kolom spesifikasi langsung
                'harga_satuan' => floatval($data[$column_map['harga_satuan']] ?? 0),
                'satuan' => trim($data[$column_map['satuan']] ?? 'unit'),
                'stok_tersedia' => intval($data[$column_map['stok_tersedia']] ?? 0),
                'stok_minimal' => intval($data[$column_map['stok_minimal']] ?? 0), // Kolom stok_minimal langsung
                'gambar' => trim($data[$column_map['gambar']] ?? ''), // Kolom gambar langsung
                'status' => 'aktif',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            // Query insert dengan kolom yang benar
            $insert_query = "
                INSERT INTO produk (
                    kode_produk, nama_produk, vendor_id, kategori_id, jenis,
                    deskripsi, spesifikasi, harga_satuan, satuan,
                    stok_tersedia, stok_minimal, gambar, status, created_at, updated_at
                ) VALUES (
                    :kode_produk, :nama_produk, :vendor_id, :kategori_id, :jenis,
                    :deskripsi, :spesifikasi, :harga_satuan, :satuan,
                    :stok_tersedia, :stok_minimal, :gambar, :status, :created_at, :updated_at
                )
            ";
            
            $insert_stmt = $conn->prepare($insert_query);
            
            // Bind parameters
            foreach ($insert_data as $key => $value) {
                $insert_stmt->bindValue(":$key", $value);
            }
            
            // Execute insert
            if ($insert_stmt->execute()) {
                echo "✅ Baris {$line_number}: Produk {$kode_produk} berhasil diimport\n";
                $stats['success']++;
            } else {
                echo "❌ Baris {$line_number}: Gagal import produk {$kode_produk}\n";
                $stats['errors']++;
            }
            
        } catch (Exception $e) {
            echo "❌ Baris {$line_number}: Error - " . $e->getMessage() . "\n";
            $stats['errors']++;
        }
        
        // Progress indicator
        if ($stats['total_rows'] % 10 == 0) {
            echo "📊 Progress: {$stats['total_rows']} baris diproses...\n";
        }
    }
    
    fclose($handle);
    
    // Commit transaksi
    $conn->commit();
    
    // Tampilkan statistik
    echo "\n=== STATISTIK IMPORT ===\n";
    echo "📊 Total baris diproses: {$stats['total_rows']}\n";
    echo "✅ Berhasil diimport: {$stats['success']}\n";
    echo "🔄 Dilewati (duplikasi): {$stats['skipped_duplicate']}\n";
    echo "🔧 Vendor ID diperbaiki: {$stats['fixed_vendor']}\n";
    echo "❌ Error: {$stats['errors']}\n";
    
    $success_rate = $stats['total_rows'] > 0 ? round(($stats['success'] / $stats['total_rows']) * 100, 2) : 0;
    echo "📈 Tingkat keberhasilan: {$success_rate}%\n";
    
    echo "\n✅ IMPORT SELESAI!\n";
    
    // Verifikasi hasil import
    echo "\n=== VERIFIKASI HASIL ===\n";
    $count_query = "SELECT COUNT(*) as total FROM produk";
    $count_stmt = $conn->prepare($count_query);
    $count_stmt->execute();
    $total_produk = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    echo "📦 Total produk di database: {$total_produk}\n";
    
    // Tampilkan beberapa produk terakhir yang diimport
    $recent_query = "SELECT kode_produk, nama_produk, vendor_id FROM produk ORDER BY created_at DESC LIMIT 5";
    $recent_stmt = $conn->prepare($recent_query);
    $recent_stmt->execute();
    $recent_products = $recent_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "\n🆕 5 Produk terakhir yang diimport:\n";
    foreach ($recent_products as $product) {
        echo "   - {$product['kode_produk']}: {$product['nama_produk']} (Vendor: {$product['vendor_id']})\n";
    }
    
} catch (Exception $e) {
    // Rollback jika ada error
    if ($conn->inTransaction()) {
        $conn->rollback();
    }
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "🔄 Transaksi dibatalkan\n";
}
?>