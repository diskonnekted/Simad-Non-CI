<?php
/**
 * Script untuk memperbaiki masalah foreign key constraint vendor_id
 * Error: SQLSTATE[23000]: Integrity constraint violation: 1452
 */

require_once 'config/database.php';

$db = new Database();
$conn = $db->getConnection();

try {
    echo "=== ANALISIS MASALAH VENDOR CONSTRAINT ===\n";
    
    // 1. Cek vendor yang ada di database
    echo "\n1. Vendor yang tersedia di database:\n";
    $vendor_query = "SELECT id, kode_vendor, nama_vendor, status FROM vendor ORDER BY id";
    $vendor_stmt = $conn->prepare($vendor_query);
    $vendor_stmt->execute();
    $vendors = $vendor_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($vendors as $vendor) {
        echo "   ID: {$vendor['id']} - {$vendor['kode_vendor']} - {$vendor['nama_vendor']} ({$vendor['status']})\n";
    }
    
    // 2. Cek produk dengan vendor_id yang tidak valid
    echo "\n2. Mencari produk dengan vendor_id tidak valid:\n";
    $invalid_query = "
        SELECT p.id, p.kode_produk, p.nama_produk, p.vendor_id 
        FROM produk p 
        LEFT JOIN vendor v ON p.vendor_id = v.id 
        WHERE v.id IS NULL
    ";
    $invalid_stmt = $conn->prepare($invalid_query);
    $invalid_stmt->execute();
    $invalid_products = $invalid_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($invalid_products) > 0) {
        echo "   DITEMUKAN " . count($invalid_products) . " produk dengan vendor_id tidak valid:\n";
        foreach ($invalid_products as $product) {
            echo "   - ID: {$product['id']}, Kode: {$product['kode_produk']}, Vendor ID: {$product['vendor_id']}\n";
        }
        
        // 3. Perbaiki produk dengan vendor_id tidak valid
        echo "\n3. Memperbaiki produk dengan vendor_id tidak valid...\n";
        $fix_query = "UPDATE produk SET vendor_id = 1 WHERE vendor_id NOT IN (SELECT id FROM vendor)";
        $fix_stmt = $conn->prepare($fix_query);
        $fix_stmt->execute();
        $fixed_count = $fix_stmt->rowCount();
        echo "   ✅ Berhasil memperbaiki {$fixed_count} produk (vendor_id diubah ke 1 - Generic)\n";
        
    } else {
        echo "   ✅ Tidak ada produk dengan vendor_id tidak valid\n";
    }
    
    // 4. Tambahkan vendor baru jika diperlukan
    echo "\n4. Menambahkan vendor tambahan jika diperlukan:\n";
    
    // Cek apakah vendor dengan ID 12 diperlukan
    $need_vendor_12 = false;
    
    // Jika ada kebutuhan vendor ID 12, tambahkan
    if ($need_vendor_12) {
        $check_vendor_12 = "SELECT id FROM vendor WHERE id = 12";
        $check_stmt = $conn->prepare($check_vendor_12);
        $check_stmt->execute();
        
        if ($check_stmt->rowCount() == 0) {
            $add_vendor_query = "
                INSERT INTO vendor (id, kode_vendor, nama_vendor, nama_perusahaan, jenis_vendor, status) 
                VALUES (12, 'VND012', 'Vendor Tambahan', 'Vendor Tambahan PT', 'supplier', 'aktif')
            ";
            $add_stmt = $conn->prepare($add_vendor_query);
            $add_stmt->execute();
            echo "   ✅ Vendor ID 12 berhasil ditambahkan\n";
        } else {
            echo "   ✅ Vendor ID 12 sudah ada\n";
        }
    } else {
        echo "   ✅ Tidak perlu menambahkan vendor baru\n";
    }
    
    // 5. Verifikasi akhir
    echo "\n5. Verifikasi akhir:\n";
    $final_check = "
        SELECT COUNT(*) as total_produk,
               COUNT(DISTINCT vendor_id) as total_vendor_used
        FROM produk p
        INNER JOIN vendor v ON p.vendor_id = v.id
    ";
    $final_stmt = $conn->prepare($final_check);
    $final_stmt->execute();
    $final_result = $final_stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "   ✅ Total produk dengan vendor valid: {$final_result['total_produk']}\n";
    echo "   ✅ Total vendor yang digunakan: {$final_result['total_vendor_used']}\n";
    
    // 6. Test import CSV
    echo "\n6. Test import file CSV:\n";
    $csv_file = 'produk_import_ready.csv';
    
    if (file_exists($csv_file)) {
        echo "   📁 File CSV ditemukan: {$csv_file}\n";
        
        // Baca beberapa baris untuk test
        $handle = fopen($csv_file, 'r');
        $header = fgetcsv($handle);
        $vendor_id_index = array_search('vendor_id', $header);
        
        $line_count = 0;
        $vendor_ids_used = [];
        
        while (($data = fgetcsv($handle)) !== FALSE && $line_count < 10) {
            if (isset($data[$vendor_id_index])) {
                $vendor_ids_used[] = $data[$vendor_id_index];
            }
            $line_count++;
        }
        fclose($handle);
        
        $unique_vendor_ids = array_unique($vendor_ids_used);
        echo "   📊 Vendor ID yang digunakan dalam CSV: " . implode(', ', $unique_vendor_ids) . "\n";
        
        // Cek apakah semua vendor_id valid
        $invalid_vendor_ids = [];
        foreach ($unique_vendor_ids as $vid) {
            $check_query = "SELECT id FROM vendor WHERE id = :vendor_id";
            $check_stmt = $conn->prepare($check_query);
            $check_stmt->bindParam(':vendor_id', $vid);
            $check_stmt->execute();
            
            if ($check_stmt->rowCount() == 0) {
                $invalid_vendor_ids[] = $vid;
            }
        }
        
        if (count($invalid_vendor_ids) > 0) {
            echo "   ❌ Vendor ID tidak valid dalam CSV: " . implode(', ', $invalid_vendor_ids) . "\n";
            echo "   💡 Solusi: Ganti vendor_id tersebut dengan 1 (Generic)\n";
        } else {
            echo "   ✅ Semua vendor_id dalam CSV valid\n";
        }
        
    } else {
        echo "   ❌ File CSV tidak ditemukan: {$csv_file}\n";
    }
    
    echo "\n=== ANALISIS SELESAI ===\n";
    echo "\n💡 REKOMENDASI:\n";
    echo "1. Gunakan file 'produk_import_ready.csv' untuk import\n";
    echo "2. Pastikan semua vendor_id menggunakan nilai yang valid (1, 2, atau 3)\n";
    echo "3. Jika masih error, jalankan script ini lagi untuk analisis lebih lanjut\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>