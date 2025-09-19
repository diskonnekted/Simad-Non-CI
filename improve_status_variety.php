<?php
require_once 'config/database.php';

$db = getDatabase();

echo "<h2>Meningkatkan Variasi Status Transaksi</h2>";
echo "<hr>";

// 1. Cek status saat ini
echo "<h3>1. Status Transaksi Saat Ini</h3>";
$current_status = $db->select("
    SELECT 
        status_transaksi,
        COUNT(*) as jumlah
    FROM transaksi
    GROUP BY status_transaksi
    ORDER BY jumlah DESC
");

echo "<pre>";
print_r($current_status);
echo "</pre>";

// 2. Update beberapa transaksi ke status yang berbeda untuk variasi
echo "<h3>2. Membuat Variasi Status Transaksi</h3>";

// Set 20% transaksi ke status 'proses'
$proses_count = $db->execute("
    UPDATE transaksi 
    SET status_transaksi = 'proses' 
    WHERE id IN (
        SELECT id FROM (
            SELECT id FROM transaksi 
            ORDER BY created_at DESC 
            LIMIT 10
        ) as temp
    )
");
echo "Transaksi diubah ke status 'proses': " . $proses_count . " baris<br>";

// Set 10% transaksi ke status 'draft'
$draft_count = $db->execute("
    UPDATE transaksi 
    SET status_transaksi = 'draft' 
    WHERE id IN (
        SELECT id FROM (
            SELECT id FROM transaksi 
            WHERE status_transaksi = 'selesai'
            ORDER BY created_at ASC 
            LIMIT 5
        ) as temp
    )
");
echo "Transaksi diubah ke status 'draft': " . $draft_count . " baris<br>";

// Set beberapa transaksi ke status 'pending'
$pending_count = $db->execute("
    UPDATE transaksi 
    SET status_transaksi = 'pending' 
    WHERE id IN (
        SELECT id FROM (
            SELECT id FROM transaksi 
            WHERE status_transaksi = 'selesai'
            ORDER BY RAND() 
            LIMIT 3
        ) as temp
    )
");
echo "Transaksi diubah ke status 'pending': " . $pending_count . " baris<br>";

// 3. Variasi metode pembayaran
echo "<h3>3. Membuat Variasi Metode Pembayaran</h3>";

// Set beberapa ke transfer
$transfer_count = $db->execute("
    UPDATE transaksi 
    SET metode_pembayaran = 'transfer' 
    WHERE id IN (
        SELECT id FROM (
            SELECT id FROM transaksi 
            WHERE metode_pembayaran = 'tunai'
            ORDER BY total_amount DESC 
            LIMIT 15
        ) as temp
    )
");
echo "Transaksi diubah ke metode 'transfer': " . $transfer_count . " baris<br>";

// Set beberapa ke kredit
$kredit_count = $db->execute("
    UPDATE transaksi 
    SET metode_pembayaran = 'kredit' 
    WHERE id IN (
        SELECT id FROM (
            SELECT id FROM transaksi 
            WHERE metode_pembayaran = 'tunai'
            ORDER BY total_amount ASC 
            LIMIT 8
        ) as temp
    )
");
echo "Transaksi diubah ke metode 'kredit': " . $kredit_count . " baris<br>";

// 4. Verifikasi hasil
echo "<h3>4. Status Transaksi Setelah Perbaikan</h3>";
$status_after = $db->select("
    SELECT 
        status_transaksi,
        COUNT(*) as jumlah,
        COALESCE(SUM(total_amount), 0) as total_nilai
    FROM transaksi
    GROUP BY status_transaksi
    ORDER BY jumlah DESC
");

echo "<pre>";
print_r($status_after);
echo "</pre>";

echo "<h3>5. Metode Pembayaran Setelah Perbaikan</h3>";
$payment_after = $db->select("
    SELECT 
        metode_pembayaran,
        COUNT(*) as jumlah,
        COALESCE(SUM(total_amount), 0) as total_nilai
    FROM transaksi
    GROUP BY metode_pembayaran
    ORDER BY jumlah DESC
");

echo "<pre>";
print_r($payment_after);
echo "</pre>";

// 5. Test statistik bulan ini dengan variasi
echo "<h3>6. Statistik Bulan Ini dengan Variasi</h3>";
$month_stats_varied = $db->select("
    SELECT 
        COUNT(*) as total_transaksi,
        COALESCE(SUM(total_amount), 0) as total_nilai,
        COALESCE(SUM(CASE WHEN status_transaksi = 'selesai' THEN total_amount ELSE 0 END), 0) as nilai_selesai,
        COALESCE(SUM(CASE WHEN status_transaksi = 'proses' THEN total_amount ELSE 0 END), 0) as nilai_proses,
        COALESCE(SUM(CASE WHEN status_transaksi = 'draft' THEN total_amount ELSE 0 END), 0) as nilai_draft,
        COALESCE(SUM(CASE WHEN status_transaksi = 'pending' THEN total_amount ELSE 0 END), 0) as nilai_pending
    FROM transaksi t
    WHERE MONTH(t.created_at) = MONTH(CURDATE()) 
    AND YEAR(t.created_at) = YEAR(CURDATE())
");

echo "<pre>";
print_r($month_stats_varied);
echo "</pre>";

echo "<h3>✅ Peningkatan Variasi Selesai!</h3>";
echo "<p>Dashboard sekarang menampilkan variasi status dan metode pembayaran yang lebih realistis.</p>";
echo "<p>Silakan refresh halaman <a href='transaksi-dashboard.php' target='_blank'>transaksi-dashboard.php</a> untuk melihat hasilnya.</p>";

?>