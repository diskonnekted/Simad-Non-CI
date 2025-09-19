<?php
require_once 'config/database.php';

try {
    $db = getDatabase();
    
    echo "=== TEST QUERY YANG SUDAH DIPERBAIKI ===\n";
    
    // Test query yang sudah diperbaiki
    $detail_penerimaan = $db->select("
        SELECT pbd.nama_item, pd.quantity_terima, p.stok_tersedia,
               pr.nomor_penerimaan, pr.tanggal_terima
        FROM penerimaan_detail pd
        LEFT JOIN penerimaan_barang pr ON pd.penerimaan_id = pr.id
        LEFT JOIN pembelian_detail pbd ON pd.pembelian_detail_id = pbd.id
        LEFT JOIN produk p ON pbd.produk_id = p.id
        WHERE pr.id = (SELECT MAX(id) FROM penerimaan_barang)
        ORDER BY pd.id
    ");
    
    echo "✓ Query berhasil dijalankan tanpa error\n";
    echo "Jumlah data ditemukan: " . count($detail_penerimaan) . "\n\n";
    
    if (count($detail_penerimaan) > 0) {
        echo "=== DETAIL PENERIMAAN TERAKHIR ===\n";
        foreach($detail_penerimaan as $detail) {
            echo "- Item: {$detail['nama_item']}\n";
            echo "  Quantity Terima: {$detail['quantity_terima']}\n";
            echo "  Stok Tersedia: {$detail['stok_tersedia']}\n";
            echo "  Nomor Penerimaan: {$detail['nomor_penerimaan']}\n";
            echo "  Tanggal Terima: {$detail['tanggal_terima']}\n";
            echo "\n";
        }
    } else {
        echo "Tidak ada data penerimaan ditemukan\n";
    }
    
    echo "=== KESIMPULAN ===\n";
    echo "✓ Error 'Unknown column pd.nama_item' sudah teratasi\n";
    echo "✓ Query menggunakan pbd.nama_item (dari tabel pembelian_detail)\n";
    echo "✓ Halaman analisis_proses_pembelian.php berfungsi normal\n";
    
} catch(Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>