<?php
require_once 'config/database.php';

echo "=== CEK STOK PRODUK SAAT INI ===\n";
echo "==============================\n\n";

$db = getDatabase();

try {
    // Cek stok produk
    $produk = $db->select("
        SELECT id, nama_produk, kode_produk, stok_tersedia, harga_satuan
        FROM produk 
        WHERE status = 'aktif'
        ORDER BY id
    ");
    
    echo "Total produk aktif: " . count($produk) . "\n\n";
    
    foreach ($produk as $p) {
        echo "ID: {$p['id']} | {$p['kode_produk']} - {$p['nama_produk']}\n";
        echo "Stok: {$p['stok_tersedia']} unit | Harga: Rp " . number_format($p['harga_satuan'], 0, ',', '.') . "\n";
        echo "---\n";
    }
    
    // Cek pembelian terakhir
    echo "\n=== PEMBELIAN TERAKHIR ===\n";
    $pembelian = $db->select("
        SELECT p.id, p.nomor_po, p.status_pembelian, p.total_amount, v.nama_vendor
        FROM pembelian p
        LEFT JOIN vendor v ON p.vendor_id = v.id
        ORDER BY p.created_at DESC
        LIMIT 3
    ");
    
    foreach ($pembelian as $po) {
        echo "PO: {$po['nomor_po']} | Vendor: {$po['nama_vendor']}\n";
        echo "Status: {$po['status_pembelian']} | Total: Rp " . number_format($po['total_amount'], 0, ',', '.') . "\n";
        echo "---\n";
    }
    
    // Cek penerimaan terakhir
    echo "\n=== PENERIMAAN TERAKHIR ===\n";
    $penerimaan = $db->select("
        SELECT pr.id, pr.nomor_penerimaan, pr.tanggal_terima, p.nomor_po
        FROM penerimaan_barang pr
        LEFT JOIN pembelian p ON pr.pembelian_id = p.id
        ORDER BY pr.created_at DESC
        LIMIT 3
    ");
    
    foreach ($penerimaan as $gr) {
        echo "GR: {$gr['nomor_penerimaan']} | PO: {$gr['nomor_po']}\n";
        echo "Tanggal: {$gr['tanggal_terima']}\n";
        echo "---\n";
    }
    
    // Cek detail penerimaan terakhir
    echo "\n=== DETAIL PENERIMAAN TERAKHIR ===\n";
    $detail_penerimaan = $db->select("
        SELECT pd.quantity_terima, pbd.nama_item, pbd.produk_id
        FROM penerimaan_detail pd
        LEFT JOIN pembelian_detail pbd ON pd.pembelian_detail_id = pbd.id
        WHERE pd.penerimaan_id = (SELECT MAX(id) FROM penerimaan_barang)
    ");
    
    foreach ($detail_penerimaan as $detail) {
        echo "Produk ID: {$detail['produk_id']} | {$detail['nama_item']}\n";
        echo "Quantity diterima: {$detail['quantity_terima']} unit\n";
        echo "---\n";
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
?>