<?php
require_once 'config/database.php';

$db = getDatabase();

echo "=== ANALISIS LOGIKA PROSES PEMBELIAN DAN PENJUALAN ===\n\n";

// 1. Struktur Tabel Produk
echo "1. STRUKTUR TABEL PRODUK:\n";
echo "   ================================\n";
$struktur = $db->select('DESCRIBE produk');
foreach($struktur as $row) {
    echo sprintf("   %-20s | %-25s | %s\n", 
        $row['Field'], 
        $row['Type'], 
        $row['Null'] === 'YES' ? 'NULL' : 'NOT NULL'
    );
}

// 2. Field Kunci untuk Stok Management
echo "\n2. FIELD KUNCI UNTUK MANAJEMEN STOK:\n";
echo "   =====================================\n";
echo "   - stok_minimal    : Batas minimum stok (untuk alert)\n";
echo "   - stok_tersedia   : Stok yang tersedia saat ini\n";
echo "   - harga_satuan    : Harga jual normal\n";
echo "   - harga_grosir    : Harga untuk pembelian dalam jumlah besar\n";
echo "   - harga_diskon    : Harga setelah diskon (promo)\n";
echo "   - vendor_id       : Vendor utama untuk produk ini\n";
echo "   - jenis           : Kategori produk (barang_it, atk, layanan)\n";

// 3. Analisis Logika Pembelian (Purchase)
echo "\n3. LOGIKA PROSES PEMBELIAN (PURCHASE):\n";
echo "   ====================================\n";
echo "   a. TABEL UTAMA:\n";
echo "      - pembelian        : Header PO (Purchase Order)\n";
echo "      - pembelian_detail : Detail item yang dibeli\n";
echo "      - penerimaan_barang: Bukti penerimaan barang\n";
echo "      - penerimaan_detail: Detail barang yang diterima\n";
echo "\n   b. FLOW PEMBELIAN:\n";
echo "      1. Buat PO (status: draft)\n";
echo "      2. Kirim ke vendor (status: dikirim)\n";
echo "      3. Terima barang (status: diterima_sebagian/diterima_lengkap)\n";
echo "      4. Update stok otomatis via trigger\n";
echo "\n   c. UPDATE STOK OTOMATIS:\n";
echo "      - Trigger: update_stok_after_penerimaan\n";
echo "      - Kondisi: Saat insert ke penerimaan_detail\n";
echo "      - Action: stok_tersedia += quantity_terima (jika kondisi = 'baik')\n";

// 4. Analisis Logika Penjualan (Sales)
echo "\n4. LOGIKA PROSES PENJUALAN (SALES):\n";
echo "   =================================\n";
echo "   a. TABEL UTAMA:\n";
echo "      - transaksi        : Header transaksi penjualan\n";
echo "      - transaksi_detail : Detail item yang dijual\n";
echo "      - piutang          : Untuk penjualan kredit\n";
echo "      - pembayaran       : Riwayat pembayaran\n";
echo "\n   b. FLOW PENJUALAN:\n";
echo "      1. Buat transaksi (status: draft)\n";
echo "      2. Konfirmasi (status: confirmed)\n";
echo "      3. Update stok langsung saat save\n";
echo "      4. Buat piutang jika kredit/tempo\n";
echo "\n   c. UPDATE STOK MANUAL:\n";
echo "      - Location: transaksi-add.php, transaksi-edit.php\n";
echo "      - Action: stok_tersedia -= quantity (langsung di code)\n";
echo "      - Kondisi: Hanya untuk produk (bukan layanan)\n";

// 5. Cek Data Sample
echo "\n5. CONTOH DATA PRODUK:\n";
echo "   ===================\n";
$sample_products = $db->select("SELECT id, kode_produk, nama_produk, jenis, stok_tersedia, stok_minimal, harga_satuan, status FROM produk LIMIT 5");
foreach($sample_products as $prod) {
    echo sprintf("   ID: %d | %s | %s | Stok: %d/%d | Harga: %s | %s\n",
        $prod['id'],
        $prod['kode_produk'],
        substr($prod['nama_produk'], 0, 25),
        $prod['stok_tersedia'],
        $prod['stok_minimal'],
        number_format($prod['harga_satuan'], 0, ',', '.'),
        $prod['status']
    );
}

// 6. Analisis Stok Movement
echo "\n6. TRACKING PERGERAKAN STOK:\n";
echo "   ==========================\n";
echo "   a. STOK MASUK (dari pembelian):\n";
echo "      - Source: penerimaan_detail\n";
echo "      - Trigger: update_stok_after_penerimaan\n";
echo "      - Field: quantity_terima\n";
echo "\n   b. STOK KELUAR (dari penjualan):\n";
echo "      - Source: transaksi_detail\n";
echo "      - Manual: UPDATE produk SET stok_tersedia = stok_tersedia - quantity\n";
echo "      - Field: quantity\n";
echo "\n   c. RIWAYAT STOK (untuk audit):\n";
echo "      - Tabel: stock_movement (jika ada)\n";
echo "      - View: Gabungan dari pembelian + transaksi\n";

// 7. Cek Trigger yang Ada
echo "\n7. TRIGGER DATABASE YANG AKTIF:\n";
echo "   =============================\n";
try {
    $triggers = $db->select("SHOW TRIGGERS");
    foreach($triggers as $trigger) {
        if (strpos($trigger['Trigger'], 'stok') !== false || strpos($trigger['Trigger'], 'stock') !== false) {
            echo "   ✓ {$trigger['Trigger']} on {$trigger['Table']} ({$trigger['Event']})\n";
        }
    }
} catch (Exception $e) {
    echo "   ❌ Error checking triggers: " . $e->getMessage() . "\n";
}

// 8. Rekomendasi Optimasi
echo "\n8. REKOMENDASI OPTIMASI:\n";
echo "   ======================\n";
echo "   a. KONSISTENSI STOK:\n";
echo "      - Buat trigger untuk penjualan juga (saat ini manual)\n";
echo "      - Implementasi stock_movement table untuk audit trail\n";
echo "\n   b. VALIDASI STOK:\n";
echo "      - Cek stok_tersedia >= quantity sebelum penjualan\n";
echo "      - Alert jika stok < stok_minimal\n";
echo "\n   c. REPORTING:\n";
echo "      - Dashboard stok real-time\n";
echo "      - Laporan pergerakan stok harian/bulanan\n";
echo "      - Prediksi kebutuhan pembelian\n";

// 9. Cek Konsistensi Data
echo "\n9. CEK KONSISTENSI DATA STOK:\n";
echo "   ===========================\n";

// Produk dengan stok minus
$stok_minus = $db->select("SELECT id, kode_produk, nama_produk, stok_tersedia FROM produk WHERE stok_tersedia < 0");
if (empty($stok_minus)) {
    echo "   ✓ Tidak ada produk dengan stok minus\n";
} else {
    echo "   ❌ Produk dengan stok minus:\n";
    foreach($stok_minus as $prod) {
        echo "      - {$prod['kode_produk']}: {$prod['nama_produk']} (Stok: {$prod['stok_tersedia']})\n";
    }
}

// Produk dengan stok di bawah minimum
$stok_rendah = $db->select("SELECT id, kode_produk, nama_produk, stok_tersedia, stok_minimal FROM produk WHERE stok_tersedia < stok_minimal AND stok_minimal > 0");
if (empty($stok_rendah)) {
    echo "   ✓ Semua produk memiliki stok di atas minimum\n";
} else {
    echo "   ⚠️  Produk dengan stok di bawah minimum:\n";
    foreach($stok_rendah as $prod) {
        echo "      - {$prod['kode_produk']}: {$prod['nama_produk']} (Stok: {$prod['stok_tersedia']}/{$prod['stok_minimal']})\n";
    }
}

echo "\n=== ANALISIS SELESAI ===\n";
?>