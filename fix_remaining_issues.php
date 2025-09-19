<?php
require_once 'config/database.php';

$db = getDatabase();

echo "=== PERBAIKAN DATA YANG TERSISA ===\n\n";

// Cek data yang masih tidak konsisten
$inconsistent_data = $db->select("
    SELECT p.id, p.nomor_po, p.status_pembelian, pd.id as detail_id, pd.nama_item, 
           pd.quantity_pesan, pd.quantity_terima, pd.produk_id, v.nama_vendor
    FROM pembelian p
    LEFT JOIN pembelian_detail pd ON p.id = pd.pembelian_id
    LEFT JOIN vendor v ON p.vendor_id = v.id
    WHERE p.status_pembelian = 'diterima_lengkap'
    AND pd.quantity_terima = 0
    ORDER BY p.created_at DESC
");

echo "Data yang masih tidak konsisten:\n";
foreach($inconsistent_data as $data) {
    echo "   - PO: {$data['nomor_po']}, Item: {$data['nama_item']}, Vendor: {$data['nama_vendor']}\n";
    echo "     Qty Pesan: {$data['quantity_pesan']}, Qty Terima: {$data['quantity_terima']}\n";
}
echo "\n";

if (!empty($inconsistent_data)) {
    try {
        $db->beginTransaction();
        
        echo "Memperbaiki data yang tersisa...\n";
        
        foreach($inconsistent_data as $data) {
            // Cek apakah sudah ada penerimaan untuk PO ini
            $existing_receipt = $db->select("
                SELECT COUNT(*) as count 
                FROM penerimaan_barang 
                WHERE pembelian_id = ?
            ", [$data['id']])[0];
            
            if ($existing_receipt['count'] == 0) {
                echo "   Membuat penerimaan untuk PO {$data['nomor_po']}...\n";
                
                // Generate nomor penerimaan
                $tanggal = date('Ymd');
                $last_gr = $db->select("
                    SELECT nomor_penerimaan 
                    FROM penerimaan_barang 
                    WHERE nomor_penerimaan LIKE 'GR-{$tanggal}-%' 
                    ORDER BY nomor_penerimaan DESC 
                    LIMIT 1
                ");
                
                if (!empty($last_gr)) {
                    $last_number = intval(substr($last_gr[0]['nomor_penerimaan'], -3));
                    $new_number = str_pad($last_number + 1, 3, '0', STR_PAD_LEFT);
                } else {
                    $new_number = '001';
                }
                
                $nomor_penerimaan = "GR-{$tanggal}-{$new_number}";
                
                // Insert penerimaan_barang
                $db->execute("
                    INSERT INTO penerimaan_barang (
                        pembelian_id, nomor_penerimaan, tanggal_terima, user_id, catatan
                    ) VALUES (?, ?, ?, ?, ?)
                ", [
                    $data['id'], 
                    $nomor_penerimaan, 
                    date('Y-m-d'), 
                    1,
                    'Penerimaan otomatis untuk perbaikan data konsistensi'
                ]);
                
                $penerimaan_id = $db->lastInsertId();
                
                // Insert penerimaan_detail
                $db->execute("
                    INSERT INTO penerimaan_detail (
                        penerimaan_id, pembelian_detail_id, quantity_terima, kondisi, catatan
                    ) VALUES (?, ?, ?, ?, ?)
                ", [
                    $penerimaan_id,
                    $data['detail_id'],
                    $data['quantity_pesan'],
                    'baik',
                    'Penerimaan otomatis untuk perbaikan data konsistensi'
                ]);
                
                echo "     ✓ Penerimaan {$nomor_penerimaan} dibuat\n";
            } else {
                echo "   PO {$data['nomor_po']} sudah memiliki penerimaan, hanya update quantity...\n";
            }
            
            // Update quantity_terima di pembelian_detail
            $db->execute("
                UPDATE pembelian_detail 
                SET quantity_terima = quantity_pesan
                WHERE id = ?
            ", [$data['detail_id']]);
            
            // Update stok produk jika ada produk_id
            if ($data['produk_id']) {
                $db->execute("
                    UPDATE produk 
                    SET stok_tersedia = stok_tersedia + ?
                    WHERE id = ?
                ", [$data['quantity_pesan'], $data['produk_id']]);
                echo "     ✓ Stok produk ID {$data['produk_id']} ditambah {$data['quantity_pesan']}\n";
            }
            
            echo "     ✓ Quantity terima diupdate untuk PO {$data['nomor_po']}\n";
        }
        
        $db->commit();
        echo "\n✅ Semua data tidak konsisten telah diperbaiki!\n";
        
    } catch (Exception $e) {
        $db->rollback();
        echo "❌ ERROR: " . $e->getMessage() . "\n";
    }
} else {
    echo "✅ Tidak ada data yang tidak konsisten.\n";
}

// Verifikasi final
echo "\nVerifikasi final:\n";
$final_check = $db->select("
    SELECT COUNT(*) as count
    FROM pembelian p
    LEFT JOIN pembelian_detail pd ON p.id = pd.pembelian_id
    WHERE p.status_pembelian = 'diterima_lengkap'
    AND pd.quantity_terima = 0
")[0];

if ($final_check['count'] == 0) {
    echo "✅ Semua data sudah konsisten!\n";
} else {
    echo "⚠️  Masih ada {$final_check['count']} data yang tidak konsisten\n";
}

echo "\n=== SELESAI ===\n";
?>