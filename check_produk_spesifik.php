<?php
require_once 'config/database.php';

echo "=== CEK PRODUK SPESIFIK (ID 3, 4, 7) ===\n";
echo "=========================================\n\n";

$db = getDatabase();

try {
    // Cek produk yang seharusnya mendapat update stok
    $produk_ids = [3, 4, 7];
    
    foreach ($produk_ids as $id) {
        $produk = $db->select("SELECT * FROM produk WHERE id = ?", [$id]);
        
        if (!empty($produk)) {
            $p = $produk[0];
            echo "=== PRODUK ID: {$id} ===\n";
            echo "Nama: {$p['nama_produk']}\n";
            echo "Kode: {$p['kode_produk']}\n";
            echo "Stok: {$p['stok_tersedia']} unit\n";
            echo "Status: {$p['status']}\n";
            echo "Harga: Rp " . number_format($p['harga_satuan'], 0, ',', '.') . "\n";
            echo "Created: {$p['created_at']}\n";
            echo "Updated: {$p['updated_at']}\n";
            echo "\n";
        } else {
            echo "❌ PRODUK ID {$id} TIDAK DITEMUKAN!\n\n";
        }
    }
    
    // Cek apakah ada trigger database
    echo "=== CEK DATABASE TRIGGERS ===\n";
    $triggers = $db->select("SHOW TRIGGERS LIKE 'penerimaan_detail'");
    
    if (!empty($triggers)) {
        foreach ($triggers as $trigger) {
            echo "✓ Trigger: {$trigger['Trigger']}\n";
            echo "  Event: {$trigger['Event']}\n";
            echo "  Table: {$trigger['Table']}\n";
            echo "\n";
        }
    } else {
        echo "❌ TIDAK ADA TRIGGER UNTUK UPDATE STOK!\n";
        echo "Ini mungkin penyebab stok tidak terupdate otomatis.\n\n";
    }
    
    // Cek manual update stok dari penerimaan terakhir
    echo "=== MANUAL UPDATE STOK ===\n";
    echo "Mencoba update stok secara manual...\n\n";
    
    $detail_penerimaan = $db->select("
        SELECT pd.quantity_terima, pbd.produk_id, pbd.nama_item
        FROM penerimaan_detail pd
        LEFT JOIN pembelian_detail pbd ON pd.pembelian_detail_id = pbd.id
        WHERE pd.penerimaan_id = (SELECT MAX(id) FROM penerimaan_barang)
    ");
    
    foreach ($detail_penerimaan as $detail) {
        $produk_id = $detail['produk_id'];
        $quantity = $detail['quantity_terima'];
        
        // Update stok
        $update_result = $db->execute("
            UPDATE produk 
            SET stok_tersedia = stok_tersedia + ?, 
                updated_at = NOW() 
            WHERE id = ?
        ", [$quantity, $produk_id]);
        
        if ($update_result) {
            echo "✓ Update stok produk ID {$produk_id} (+{$quantity} unit)\n";
        } else {
            echo "❌ Gagal update stok produk ID {$produk_id}\n";
        }
    }
    
    echo "\n=== STOK SETELAH UPDATE MANUAL ===\n";
    foreach ($produk_ids as $id) {
        $produk = $db->select("SELECT nama_produk, stok_tersedia FROM produk WHERE id = ?", [$id]);
        if (!empty($produk)) {
            $p = $produk[0];
            echo "ID {$id}: {$p['nama_produk']} = {$p['stok_tersedia']} unit\n";
        }
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
?>