<?php
require_once 'config/database.php';

$db = getDatabase();

echo "=== CEK STRUKTUR TABEL PRODUK ===\n\n";

// 1. Struktur tabel produk
$struktur = $db->select('DESCRIBE produk');
echo "1. Struktur tabel produk:\n";
foreach($struktur as $row) {
    echo "   - {$row['Field']} | {$row['Type']} | Null: {$row['Null']} | Default: {$row['Default']}\n";
}
echo "\n";

// 2. Cek data produk laptop dengan semua kolom
$produk_laptop = $db->select("SELECT * FROM produk WHERE nama_produk LIKE '%laptop%' OR nama_produk LIKE '%Laptop%'");
echo "2. Data produk laptop lengkap:\n";
foreach($produk_laptop as $row) {
    echo "   - ID: {$row['id']}\n";
    echo "     Nama: {$row['nama_produk']}\n";
    echo "     Kode: {$row['kode_produk']}\n";
    if (isset($row['stok'])) {
        echo "     Stok: {$row['stok']}\n";
    } else {
        echo "     Stok: [kolom tidak ada]\n";
    }
    if (isset($row['quantity'])) {
        echo "     Quantity: {$row['quantity']}\n";
    }
    echo "     Status: {$row['status']}\n";
    echo "\n";
}

// 3. Cek masalah pembelian laptop yang statusnya diterima_lengkap tapi quantity_terima = 0
echo "3. Analisis masalah pembelian laptop:\n";
$masalah_po = $db->select("
    SELECT p.*, pd.nama_item, pd.quantity_pesan, pd.quantity_terima, v.nama_vendor
    FROM pembelian p
    LEFT JOIN pembelian_detail pd ON p.id = pd.pembelian_id
    LEFT JOIN vendor v ON p.vendor_id = v.id
    WHERE (pd.nama_item LIKE '%laptop%' OR pd.nama_item LIKE '%Laptop%')
    AND p.status_pembelian = 'diterima_lengkap'
    AND pd.quantity_terima = 0
    ORDER BY p.created_at DESC
");

foreach($masalah_po as $row) {
    echo "   ⚠️  MASALAH DITEMUKAN:\n";
    echo "      PO: {$row['nomor_po']}\n";
    echo "      Item: {$row['nama_item']}\n";
    echo "      Vendor: {$row['nama_vendor']}\n";
    echo "      Qty Pesan: {$row['quantity_pesan']}\n";
    echo "      Qty Terima: {$row['quantity_terima']} (HARUSNYA > 0)\n";
    echo "      Status: {$row['status_pembelian']} (TIDAK KONSISTEN)\n";
    echo "      Tanggal: {$row['tanggal_pembelian']}\n";
    echo "\n";
}

// 4. Cek apakah ada penerimaan untuk PO tersebut
echo "4. Cek penerimaan untuk PO bermasalah:\n";
foreach($masalah_po as $row) {
    $penerimaan = $db->select("
        SELECT pb.*, pd.quantity_terima as qty_detail_terima
        FROM penerimaan_barang pb
        LEFT JOIN penerimaan_detail pd ON pb.id = pd.penerimaan_id
        WHERE pb.pembelian_id = ?
    ", [$row['id']]);
    
    echo "   PO {$row['nomor_po']}:\n";
    if (empty($penerimaan)) {
        echo "     ❌ TIDAK ADA PENERIMAAN SAMA SEKALI\n";
    } else {
        foreach($penerimaan as $terima) {
            echo "     ✓ Ada penerimaan: {$terima['nomor_penerimaan']}, Qty: {$terima['qty_detail_terima']}\n";
        }
    }
    echo "\n";
}

// 5. Cek trigger database
echo "5. Cek trigger database:\n";
try {
    $triggers = $db->select("SHOW TRIGGERS");
    if (empty($triggers)) {
        echo "   ❌ TIDAK ADA TRIGGER SAMA SEKALI\n";
    } else {
        echo "   Trigger yang ada:\n";
        foreach($triggers as $trigger) {
            echo "     - {$trigger['Trigger']} on {$trigger['Table']} ({$trigger['Event']})\n";
        }
    }
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
}

echo "\n=== SELESAI ===\n";
?>