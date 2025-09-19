<?php
require_once 'config/database.php';

$db = getDatabase();

echo "=== PERBAIKAN MASALAH LAPTOP ===\n\n";

try {
    $db->beginTransaction();
    
    // 1. Identifikasi PO bermasalah
    $po_bermasalah = $db->select("
        SELECT p.id, p.nomor_po, pd.id as detail_id, pd.nama_item, pd.quantity_pesan, pd.produk_id
        FROM pembelian p
        LEFT JOIN pembelian_detail pd ON p.id = pd.pembelian_id
        WHERE (pd.nama_item LIKE '%laptop%' OR pd.nama_item LIKE '%Laptop%')
        AND p.status_pembelian = 'diterima_lengkap'
        AND pd.quantity_terima = 0
        ORDER BY p.created_at DESC
    ");
    
    echo "1. PO bermasalah yang ditemukan:\n";
    foreach($po_bermasalah as $po) {
        echo "   - PO: {$po['nomor_po']}, Item: {$po['nama_item']}, Qty: {$po['quantity_pesan']}\n";
    }
    echo "\n";
    
    if (empty($po_bermasalah)) {
        echo "Tidak ada PO bermasalah ditemukan.\n";
        exit;
    }
    
    // 2. Ubah status PO kembali ke 'dikirim' agar bisa dibuat penerimaannya
    echo "2. Mengubah status PO bermasalah kembali ke 'dikirim':\n";
    foreach($po_bermasalah as $po) {
        $db->execute("
            UPDATE pembelian 
            SET status_pembelian = 'dikirim'
            WHERE id = ?
        ", [$po['id']]);
        echo "   ✓ PO {$po['nomor_po']} status diubah ke 'dikirim'\n";
    }
    echo "\n";
    
    // 3. Buat penerimaan otomatis untuk setiap PO
    echo "3. Membuat penerimaan otomatis:\n";
    foreach($po_bermasalah as $po) {
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
            $po['id'], 
            $nomor_penerimaan, 
            date('Y-m-d'), 
            1, // user admin
            'Penerimaan otomatis untuk perbaikan data'
        ]);
        
        $penerimaan_id = $db->lastInsertId();
        
        // Insert penerimaan_detail
        $db->execute("
            INSERT INTO penerimaan_detail (
                penerimaan_id, pembelian_detail_id, quantity_terima, kondisi, catatan
            ) VALUES (?, ?, ?, ?, ?)
        ", [
            $penerimaan_id,
            $po['detail_id'],
            $po['quantity_pesan'], // terima semua yang dipesan
            'baik',
            'Penerimaan otomatis untuk perbaikan data'
        ]);
        
        echo "   ✓ Penerimaan {$nomor_penerimaan} dibuat untuk PO {$po['nomor_po']}\n";
        echo "     - Qty diterima: {$po['quantity_pesan']}\n";
        
        // Update quantity_terima di pembelian_detail
        $db->execute("
            UPDATE pembelian_detail 
            SET quantity_terima = quantity_pesan
            WHERE id = ?
        ", [$po['detail_id']]);
        
        // Update status pembelian ke diterima_lengkap
        $db->execute("
            UPDATE pembelian 
            SET status_pembelian = 'diterima_lengkap'
            WHERE id = ?
        ", [$po['id']]);
        
        echo "     - Status PO diupdate ke 'diterima_lengkap'\n";
        echo "     - Quantity terima diupdate\n";
        
        // Update stok produk (manual karena trigger mungkin tidak jalan)
        if ($po['produk_id']) {
            $db->execute("
                UPDATE produk 
                SET stok_tersedia = stok_tersedia + ?
                WHERE id = ?
            ", [$po['quantity_pesan'], $po['produk_id']]);
            echo "     - Stok produk ID {$po['produk_id']} ditambah {$po['quantity_pesan']}\n";
        }
        
        echo "\n";
    }
    
    $db->commit();
    
    echo "4. Verifikasi hasil perbaikan:\n";
    
    // Cek status PO setelah perbaikan
    $po_setelah = $db->select("
        SELECT p.nomor_po, p.status_pembelian, pd.quantity_pesan, pd.quantity_terima
        FROM pembelian p
        LEFT JOIN pembelian_detail pd ON p.id = pd.pembelian_id
        WHERE p.id IN (" . implode(',', array_column($po_bermasalah, 'id')) . ")
    ");
    
    foreach($po_setelah as $po) {
        echo "   - PO {$po['nomor_po']}: Status = {$po['status_pembelian']}, Qty Pesan = {$po['quantity_pesan']}, Qty Terima = {$po['quantity_terima']}\n";
    }
    
    // Cek stok produk laptop
    echo "\n5. Stok produk laptop setelah perbaikan:\n";
    $stok_laptop = $db->select("
        SELECT id, nama_produk, stok_tersedia
        FROM produk 
        WHERE nama_produk LIKE '%laptop%' OR nama_produk LIKE '%Laptop%'
    ");
    
    foreach($stok_laptop as $produk) {
        echo "   - {$produk['nama_produk']} (ID: {$produk['id']}): Stok = {$produk['stok_tersedia']}\n";
    }
    
    echo "\n✅ PERBAIKAN SELESAI! Sekarang data laptop sudah konsisten dan akan muncul di halaman penerimaan.\n";
    
} catch (Exception $e) {
    $db->rollback();
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}

echo "\n=== SELESAI ===\n";
?>