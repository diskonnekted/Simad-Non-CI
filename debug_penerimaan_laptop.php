<?php
require_once 'config/database.php';

$db = getDatabase();

echo "=== ANALISIS MASALAH PENERIMAAN LAPTOP ===\n\n";

// 1. Cek total data penerimaan
$total_penerimaan = $db->select('SELECT COUNT(*) as total FROM penerimaan_barang');
echo "1. Total penerimaan barang: " . $total_penerimaan[0]['total'] . "\n\n";

// 2. Cek data penerimaan terbaru
$penerimaan_terbaru = $db->select("
    SELECT pb.*, p.nomor_po, v.nama_vendor 
    FROM penerimaan_barang pb
    LEFT JOIN pembelian p ON pb.pembelian_id = p.id
    LEFT JOIN vendor v ON p.vendor_id = v.id
    ORDER BY pb.created_at DESC 
    LIMIT 10
");

echo "2. Data penerimaan terbaru (10 terakhir):\n";
foreach($penerimaan_terbaru as $row) {
    echo "   - ID: {$row['id']}, Nomor: {$row['nomor_penerimaan']}, PO: {$row['nomor_po']}, Vendor: {$row['nama_vendor']}, Tanggal: {$row['tanggal_terima']}\n";
}
echo "\n";

// 3. Cek pembelian laptop terbaru
$pembelian_laptop = $db->select("
    SELECT p.*, v.nama_vendor, pd.nama_item, pd.quantity_pesan, pd.quantity_terima
    FROM pembelian p
    LEFT JOIN vendor v ON p.vendor_id = v.id
    LEFT JOIN pembelian_detail pd ON p.id = pd.pembelian_id
    WHERE pd.nama_item LIKE '%laptop%' OR pd.nama_item LIKE '%Laptop%'
    ORDER BY p.created_at DESC
    LIMIT 5
");

echo "3. Pembelian laptop terbaru:\n";
foreach($pembelian_laptop as $row) {
    echo "   - PO: {$row['nomor_po']}, Item: {$row['nama_item']}, Qty Pesan: {$row['quantity_pesan']}, Qty Terima: {$row['quantity_terima']}, Status: {$row['status_pembelian']}\n";
}
echo "\n";

// 4. Cek produk laptop di database
$produk_laptop = $db->select("
    SELECT * FROM produk 
    WHERE nama_produk LIKE '%laptop%' OR nama_produk LIKE '%Laptop%'
    ORDER BY id DESC
");

echo "4. Produk laptop di database:\n";
foreach($produk_laptop as $row) {
    echo "   - ID: {$row['id']}, Nama: {$row['nama_produk']}, Stok: {$row['stok']}, Status: {$row['status']}\n";
}
echo "\n";

// 5. Cek pembelian yang belum ada penerimaannya
$pembelian_belum_terima = $db->select("
    SELECT p.*, v.nama_vendor, pd.nama_item, pd.quantity_pesan, pd.quantity_terima
    FROM pembelian p
    LEFT JOIN vendor v ON p.vendor_id = v.id
    LEFT JOIN pembelian_detail pd ON p.id = pd.pembelian_id
    WHERE p.status_pembelian IN ('dikirim', 'diterima_sebagian')
    AND (pd.nama_item LIKE '%laptop%' OR pd.nama_item LIKE '%Laptop%')
    ORDER BY p.created_at DESC
");

echo "5. Pembelian laptop yang belum diterima lengkap:\n";
foreach($pembelian_belum_terima as $row) {
    echo "   - PO: {$row['nomor_po']}, Item: {$row['nama_item']}, Qty Pesan: {$row['quantity_pesan']}, Qty Terima: {$row['quantity_terima']}, Status: {$row['status_pembelian']}\n";
}
echo "\n";

// 6. Cek detail penerimaan untuk laptop
$detail_penerimaan_laptop = $db->select("
    SELECT pd.*, pb.nomor_penerimaan, p.nomor_po, pbd.nama_item
    FROM penerimaan_detail pd
    LEFT JOIN penerimaan_barang pb ON pd.penerimaan_id = pb.id
    LEFT JOIN pembelian_detail pbd ON pd.pembelian_detail_id = pbd.id
    LEFT JOIN pembelian p ON pbd.pembelian_id = p.id
    WHERE pbd.nama_item LIKE '%laptop%' OR pbd.nama_item LIKE '%Laptop%'
    ORDER BY pd.id DESC
    LIMIT 10
");

echo "6. Detail penerimaan laptop:\n";
foreach($detail_penerimaan_laptop as $row) {
    echo "   - Penerimaan: {$row['nomor_penerimaan']}, PO: {$row['nomor_po']}, Item: {$row['nama_item']}, Qty: {$row['quantity_terima']}, Kondisi: {$row['kondisi']}\n";
}
echo "\n";

// 7. Cek trigger database untuk update stok
echo "7. Cek apakah trigger update stok berfungsi:\n";
try {
    $triggers = $db->select("SHOW TRIGGERS LIKE 'update_stok_after_penerimaan'");
    if (count($triggers) > 0) {
        echo "   ✓ Trigger update_stok_after_penerimaan ada\n";
    } else {
        echo "   ✗ Trigger update_stok_after_penerimaan TIDAK ADA\n";
    }
} catch (Exception $e) {
    echo "   ✗ Error cek trigger: " . $e->getMessage() . "\n";
}
echo "\n";

echo "=== ANALISIS SELESAI ===\n";
?>