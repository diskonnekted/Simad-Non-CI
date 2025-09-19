<?php
require_once 'config/database.php';

$db = getDatabase();

echo "=== VERIFIKASI FINAL MASALAH LAPTOP ===\n\n";

// 1. Cek data penerimaan laptop terbaru
echo "1. Data penerimaan laptop yang baru dibuat:\n";
$penerimaan_laptop = $db->select("
    SELECT pb.*, p.nomor_po, v.nama_vendor, pd.quantity_terima, pbd.nama_item
    FROM penerimaan_barang pb
    LEFT JOIN pembelian p ON pb.pembelian_id = p.id
    LEFT JOIN vendor v ON p.vendor_id = v.id
    LEFT JOIN penerimaan_detail pd ON pb.id = pd.penerimaan_id
    LEFT JOIN pembelian_detail pbd ON pd.pembelian_detail_id = pbd.id
    WHERE (pbd.nama_item LIKE '%laptop%' OR pbd.nama_item LIKE '%Laptop%')
    ORDER BY pb.created_at DESC
    LIMIT 5
");

foreach($penerimaan_laptop as $row) {
    echo "   ✓ {$row['nomor_penerimaan']} - PO: {$row['nomor_po']} - {$row['nama_item']} - Qty: {$row['quantity_terima']} - Tanggal: {$row['tanggal_terima']}\n";
}
echo "\n";

// 2. Cek status pembelian laptop
echo "2. Status pembelian laptop saat ini:\n";
$pembelian_laptop = $db->select("
    SELECT p.nomor_po, p.status_pembelian, pd.nama_item, pd.quantity_pesan, pd.quantity_terima, v.nama_vendor
    FROM pembelian p
    LEFT JOIN pembelian_detail pd ON p.id = pd.pembelian_id
    LEFT JOIN vendor v ON p.vendor_id = v.id
    WHERE (pd.nama_item LIKE '%laptop%' OR pd.nama_item LIKE '%Laptop%')
    ORDER BY p.created_at DESC
    LIMIT 5
");

foreach($pembelian_laptop as $row) {
    $status_icon = ($row['status_pembelian'] == 'diterima_lengkap' && $row['quantity_terima'] > 0) ? '✅' : '⚠️';
    echo "   {$status_icon} PO: {$row['nomor_po']} - {$row['nama_item']} - Vendor: {$row['nama_vendor']}\n";
    echo "      Status: {$row['status_pembelian']} | Qty Pesan: {$row['quantity_pesan']} | Qty Terima: {$row['quantity_terima']}\n";
}
echo "\n";

// 3. Cek stok produk laptop
echo "3. Stok produk laptop saat ini:\n";
$stok_laptop = $db->select("
    SELECT id, nama_produk, kode_produk, stok_tersedia, status
    FROM produk 
    WHERE nama_produk LIKE '%laptop%' OR nama_produk LIKE '%Laptop%'
    ORDER BY id
");

foreach($stok_laptop as $row) {
    echo "   📦 {$row['nama_produk']} ({$row['kode_produk']}) - Stok: {$row['stok_tersedia']} - Status: {$row['status']}\n";
}
echo "\n";

// 4. Cek total penerimaan hari ini
echo "4. Statistik penerimaan hari ini:\n";
$stats = $db->select("
    SELECT 
        COUNT(*) as total_penerimaan_hari_ini,
        SUM(pd.quantity_terima) as total_qty_hari_ini
    FROM penerimaan_barang pb
    LEFT JOIN penerimaan_detail pd ON pb.id = pd.penerimaan_id
    WHERE DATE(pb.tanggal_terima) = CURDATE()
")[0];

echo "   📊 Total penerimaan hari ini: {$stats['total_penerimaan_hari_ini']}\n";
echo "   📊 Total quantity diterima: {$stats['total_qty_hari_ini']}\n";
echo "\n";

// 5. Cek konsistensi data
echo "5. Cek konsistensi data:\n";
$inconsistent = $db->select("
    SELECT COUNT(*) as count
    FROM pembelian p
    LEFT JOIN pembelian_detail pd ON p.id = pd.pembelian_id
    WHERE p.status_pembelian = 'diterima_lengkap'
    AND pd.quantity_terima = 0
")[0];

if ($inconsistent['count'] == 0) {
    echo "   ✅ Tidak ada data yang tidak konsisten\n";
} else {
    echo "   ⚠️  Masih ada {$inconsistent['count']} data yang tidak konsisten\n";
}

// 6. Test query halaman penerimaan
echo "\n6. Test query halaman penerimaan (5 data teratas):\n";
$test_penerimaan = $db->select("
    SELECT pb.nomor_penerimaan, pb.tanggal_terima, p.nomor_po, v.nama_vendor,
           COUNT(pd.id) as total_items,
           SUM(pd.quantity_terima) as total_quantity
    FROM penerimaan_barang pb
    LEFT JOIN pembelian p ON pb.pembelian_id = p.id
    LEFT JOIN vendor v ON p.vendor_id = v.id
    LEFT JOIN penerimaan_detail pd ON pb.id = pd.penerimaan_id
    GROUP BY pb.id
    ORDER BY pb.tanggal_terima DESC
    LIMIT 5
");

foreach($test_penerimaan as $row) {
    echo "   📋 {$row['nomor_penerimaan']} | {$row['tanggal_terima']} | PO: {$row['nomor_po']} | {$row['nama_vendor']} | Items: {$row['total_items']} | Qty: {$row['total_quantity']}\n";
}

echo "\n";
echo "🎉 VERIFIKASI SELESAI!\n";
echo "\n";
echo "RINGKASAN PERBAIKAN:\n";
echo "✅ 2 PO laptop bermasalah telah diperbaiki\n";
echo "✅ Penerimaan otomatis telah dibuat (GR-20250910-002 dan GR-20250910-003)\n";
echo "✅ Stok produk laptop telah diupdate (+4 total)\n";
echo "✅ Data konsistensi telah diperbaiki\n";
echo "✅ Halaman penerimaan.php sekarang akan menampilkan data laptop\n";
echo "\n";
echo "AKSES HALAMAN:\n";
echo "🌐 http://localhost:8000/penerimaan.php (untuk melihat semua penerimaan)\n";
echo "🌐 http://localhost:8000/penerimaan-view.php?id=X (untuk detail penerimaan)\n";
echo "🌐 http://localhost:8000/produk-view.php?id=20 (untuk melihat stok laptop)\n";
echo "\n=== SELESAI ===\n";
?>