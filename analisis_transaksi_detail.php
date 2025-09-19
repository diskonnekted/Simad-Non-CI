<?php
require_once 'config/database.php';

$db = getDatabase();

echo "=== ANALISIS DETAIL TABEL TRANSAKSI DAN TRANSAKSI_DETAIL ===\n\n";

// 1. Struktur Tabel Transaksi
echo "1. STRUKTUR TABEL TRANSAKSI (PENJUALAN):\n";
echo "   ========================================\n";
try {
    $struktur_transaksi = $db->select('DESCRIBE transaksi');
    foreach($struktur_transaksi as $row) {
        echo sprintf("   %-25s | %-30s | %s\n", 
            $row['Field'], 
            $row['Type'], 
            $row['Null'] === 'YES' ? 'NULL' : 'NOT NULL'
        );
    }
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
}

// 2. Struktur Tabel Transaksi Detail
echo "\n2. STRUKTUR TABEL TRANSAKSI_DETAIL:\n";
echo "   =================================\n";
try {
    $struktur_detail = $db->select('DESCRIBE transaksi_detail');
    foreach($struktur_detail as $row) {
        echo sprintf("   %-25s | %-30s | %s\n", 
            $row['Field'], 
            $row['Type'], 
            $row['Null'] === 'YES' ? 'NULL' : 'NOT NULL'
        );
    }
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
}

// 3. Analisis Hubungan Produk dan Layanan
echo "\n3. ANALISIS HUBUNGAN PRODUK DAN LAYANAN:\n";
echo "   ======================================\n";
echo "   a. FIELD KUNCI di transaksi_detail:\n";
echo "      - produk_id  : ID produk yang dijual (NULL jika layanan)\n";
echo "      - layanan_id : ID layanan yang dijual (NULL jika produk)\n";
echo "      - nama_item  : Nama item (produk/layanan) saat transaksi\n";
echo "      - quantity   : Jumlah yang dijual\n";
echo "      - harga_satuan: Harga per unit saat transaksi\n";
echo "      - subtotal   : Total harga (quantity * harga_satuan)\n";

// 4. Cek Data Sample Transaksi
echo "\n4. SAMPLE DATA TRANSAKSI TERBARU:\n";
echo "   ===============================\n";
$sample_transaksi = $db->select("
    SELECT t.id, t.nomor_invoice, t.tanggal_transaksi, t.total_amount, 
           t.status_transaksi, t.metode_pembayaran, d.nama_desa
    FROM transaksi t
    LEFT JOIN desa d ON t.desa_id = d.id
    ORDER BY t.created_at DESC
    LIMIT 5
");

foreach($sample_transaksi as $trans) {
    echo sprintf("   %s | %s | %s | %s | %s\n",
        $trans['nomor_invoice'],
        $trans['tanggal_transaksi'],
        number_format($trans['total_amount'], 0, ',', '.'),
        $trans['status_transaksi'],
        $trans['nama_desa'] ?? 'N/A'
    );
}

// 5. Analisis Detail Transaksi
echo "\n5. SAMPLE DETAIL TRANSAKSI:\n";
echo "   =========================\n";
$sample_detail = $db->select("
    SELECT td.*, t.nomor_invoice, p.nama_produk, l.nama_layanan
    FROM transaksi_detail td
    LEFT JOIN transaksi t ON td.transaksi_id = t.id
    LEFT JOIN produk p ON td.produk_id = p.id
    LEFT JOIN layanan l ON td.layanan_id = l.id
    ORDER BY td.id DESC
    LIMIT 10
");

foreach($sample_detail as $detail) {
    $item_type = $detail['produk_id'] ? 'PRODUK' : ($detail['layanan_id'] ? 'LAYANAN' : 'UNKNOWN');
    $item_name = $detail['produk_id'] ? $detail['nama_produk'] : ($detail['layanan_id'] ? $detail['nama_layanan'] : $detail['nama_item']);
    
    echo sprintf("   %s | %s | %s | Qty: %d | Harga: %s | Total: %s\n",
        $detail['nomor_invoice'],
        $item_type,
        substr($item_name, 0, 30),
        $detail['quantity'],
        number_format($detail['harga_satuan'], 0, ',', '.'),
        number_format($detail['subtotal'], 0, ',', '.')
    );
}

// 6. Statistik Penjualan Produk vs Layanan
echo "\n6. STATISTIK PENJUALAN PRODUK VS LAYANAN:\n";
echo "   ======================================\n";

// Statistik produk
$stats_produk = $db->select("
    SELECT 
        COUNT(*) as total_transaksi,
        SUM(quantity) as total_quantity,
        SUM(subtotal) as total_nilai
    FROM transaksi_detail 
    WHERE produk_id IS NOT NULL
");

if (!empty($stats_produk)) {
    $stat = $stats_produk[0];
    echo "   PRODUK:\n";
    echo "   - Total Transaksi: " . number_format($stat['total_transaksi']) . "\n";
    echo "   - Total Quantity : " . number_format($stat['total_quantity']) . "\n";
    echo "   - Total Nilai    : Rp " . number_format($stat['total_nilai'], 0, ',', '.') . "\n";
}

// Statistik layanan
$stats_layanan = $db->select("
    SELECT 
        COUNT(*) as total_transaksi,
        SUM(quantity) as total_quantity,
        SUM(subtotal) as total_nilai
    FROM transaksi_detail 
    WHERE layanan_id IS NOT NULL
");

if (!empty($stats_layanan)) {
    $stat = $stats_layanan[0];
    echo "\n   LAYANAN:\n";
    echo "   - Total Transaksi: " . number_format($stat['total_transaksi']) . "\n";
    echo "   - Total Quantity : " . number_format($stat['total_quantity']) . "\n";
    echo "   - Total Nilai    : Rp " . number_format($stat['total_nilai'], 0, ',', '.') . "\n";
}

// 7. Produk Terlaris
echo "\n7. TOP 5 PRODUK TERLARIS:\n";
echo "   =======================\n";
$top_produk = $db->select("
    SELECT p.nama_produk, p.kode_produk,
           SUM(td.quantity) as total_terjual,
           SUM(td.subtotal) as total_nilai,
           COUNT(td.id) as total_transaksi
    FROM transaksi_detail td
    JOIN produk p ON td.produk_id = p.id
    WHERE td.produk_id IS NOT NULL
    GROUP BY p.id, p.nama_produk, p.kode_produk
    ORDER BY total_terjual DESC
    LIMIT 5
");

foreach($top_produk as $prod) {
    echo sprintf("   %s | %s | Terjual: %d | Nilai: %s | Transaksi: %d\n",
        $prod['kode_produk'],
        substr($prod['nama_produk'], 0, 25),
        $prod['total_terjual'],
        number_format($prod['total_nilai'], 0, ',', '.'),
        $prod['total_transaksi']
    );
}

// 8. Layanan Terlaris
echo "\n8. TOP 5 LAYANAN TERLARIS:\n";
echo "   ========================\n";
$top_layanan = $db->select("
    SELECT l.nama_layanan, l.kode_layanan,
           SUM(td.quantity) as total_terjual,
           SUM(td.subtotal) as total_nilai,
           COUNT(td.id) as total_transaksi
    FROM transaksi_detail td
    JOIN layanan l ON td.layanan_id = l.id
    WHERE td.layanan_id IS NOT NULL
    GROUP BY l.id, l.nama_layanan, l.kode_layanan
    ORDER BY total_terjual DESC
    LIMIT 5
");

if (empty($top_layanan)) {
    echo "   ❌ Tidak ada data penjualan layanan\n";
} else {
    foreach($top_layanan as $layanan) {
        echo sprintf("   %s | %s | Terjual: %d | Nilai: %s | Transaksi: %d\n",
            $layanan['kode_layanan'] ?? 'N/A',
            substr($layanan['nama_layanan'], 0, 25),
            $layanan['total_terjual'],
            number_format($layanan['total_nilai'], 0, ',', '.'),
            $layanan['total_transaksi']
        );
    }
}

// 9. Analisis Metode Pembayaran
echo "\n9. ANALISIS METODE PEMBAYARAN:\n";
echo "   ============================\n";
$payment_stats = $db->select("
    SELECT metode_pembayaran,
           COUNT(*) as total_transaksi,
           SUM(total_amount) as total_nilai,
           AVG(total_amount) as rata_rata_nilai
    FROM transaksi
    GROUP BY metode_pembayaran
    ORDER BY total_nilai DESC
");

foreach($payment_stats as $payment) {
    echo sprintf("   %-15s | Transaksi: %3d | Total: %12s | Rata-rata: %10s\n",
        strtoupper($payment['metode_pembayaran']),
        $payment['total_transaksi'],
        number_format($payment['total_nilai'], 0, ',', '.'),
        number_format($payment['rata_rata_nilai'], 0, ',', '.')
    );
}

// 10. Status Transaksi
echo "\n10. STATUS TRANSAKSI:\n";
echo "    ================\n";
$status_stats = $db->select("
    SELECT status_transaksi,
           COUNT(*) as total_transaksi,
           SUM(total_amount) as total_nilai
    FROM transaksi
    GROUP BY status_transaksi
    ORDER BY total_transaksi DESC
");

foreach($status_stats as $status) {
    echo sprintf("   %-15s | Transaksi: %3d | Total: %12s\n",
        strtoupper($status['status_transaksi']),
        $status['total_transaksi'],
        number_format($status['total_nilai'], 0, ',', '.')
    );
}

echo "\n=== ANALISIS TRANSAKSI SELESAI ===\n";
?>