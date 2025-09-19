<?php
require_once 'config/database.php';

$db = getDatabase();

echo "<h2>Fix Status Transaksi dan Metode Pembayaran</h2>";
echo "<hr>";

// 1. Cek data yang bermasalah
echo "<h3>1. Data Bermasalah</h3>";
$problematic_data = $db->select("
    SELECT 
        COUNT(*) as total_kosong_status,
        COUNT(CASE WHEN status_transaksi IS NULL OR status_transaksi = '' THEN 1 END) as status_kosong,
        COUNT(CASE WHEN metode_pembayaran IS NULL OR metode_pembayaran = '' THEN 1 END) as metode_kosong
    FROM transaksi
");

echo "<pre>";
print_r($problematic_data);
echo "</pre>";

// 2. Sample data bermasalah
echo "<h3>2. Sample Data Bermasalah (10 data)</h3>";
$sample_problematic = $db->select("
    SELECT id, nomor_invoice, status_transaksi, metode_pembayaran, total_amount, created_at
    FROM transaksi 
    WHERE status_transaksi IS NULL OR status_transaksi = '' OR metode_pembayaran IS NULL OR metode_pembayaran = ''
    ORDER BY created_at DESC
    LIMIT 10
");

echo "<pre>";
print_r($sample_problematic);
echo "</pre>";

// 3. Fix status transaksi kosong
echo "<h3>3. Memperbaiki Status Transaksi Kosong</h3>";
$fix_status_result = $db->execute("
    UPDATE transaksi 
    SET status_transaksi = 'selesai' 
    WHERE status_transaksi IS NULL OR status_transaksi = ''
");

echo "Status transaksi diperbaiki: " . $fix_status_result . " baris<br>";

// 4. Fix metode pembayaran kosong
echo "<h3>4. Memperbaiki Metode Pembayaran Kosong</h3>";
$fix_payment_result = $db->execute("
    UPDATE transaksi 
    SET metode_pembayaran = 'tunai' 
    WHERE metode_pembayaran IS NULL OR metode_pembayaran = ''
");

echo "Metode pembayaran diperbaiki: " . $fix_payment_result . " baris<br>";

// 5. Verifikasi hasil perbaikan
echo "<h3>5. Verifikasi Hasil Perbaikan</h3>";
$verification = $db->select("
    SELECT 
        COUNT(*) as total_transaksi,
        COUNT(CASE WHEN status_transaksi IS NULL OR status_transaksi = '' THEN 1 END) as status_kosong,
        COUNT(CASE WHEN metode_pembayaran IS NULL OR metode_pembayaran = '' THEN 1 END) as metode_kosong
    FROM transaksi
");

echo "<pre>";
print_r($verification);
echo "</pre>";

// 6. Test status transaksi setelah perbaikan
echo "<h3>6. Status Transaksi Setelah Perbaikan</h3>";
$status_after_fix = $db->select("
    SELECT 
        status_transaksi,
        COUNT(*) as jumlah,
        COALESCE(SUM(total_amount), 0) as total_nilai
    FROM transaksi
    GROUP BY status_transaksi
    ORDER BY jumlah DESC
");

echo "<pre>";
print_r($status_after_fix);
echo "</pre>";

// 7. Test metode pembayaran setelah perbaikan
echo "<h3>7. Metode Pembayaran Setelah Perbaikan</h3>";
$payment_after_fix = $db->select("
    SELECT 
        metode_pembayaran,
        COUNT(*) as jumlah,
        COALESCE(SUM(total_amount), 0) as total_nilai
    FROM transaksi
    GROUP BY metode_pembayaran
    ORDER BY jumlah DESC
");

echo "<pre>";
print_r($payment_after_fix);
echo "</pre>";

// 8. Test statistik bulan ini setelah perbaikan
echo "<h3>8. Statistik Bulan Ini Setelah Perbaikan</h3>";
$month_stats_fixed = $db->select("
    SELECT 
        COUNT(*) as total_transaksi,
        COALESCE(SUM(total_amount), 0) as total_nilai,
        COALESCE(SUM(CASE WHEN status_transaksi = 'selesai' THEN total_amount ELSE 0 END), 0) as nilai_selesai
    FROM transaksi t
    WHERE MONTH(t.created_at) = MONTH(CURDATE()) 
    AND YEAR(t.created_at) = YEAR(CURDATE())
");

echo "<pre>";
print_r($month_stats_fixed);
echo "</pre>";

echo "<h3>✅ Perbaikan Selesai!</h3>";
echo "<p>Silakan refresh halaman <a href='transaksi-dashboard.php' target='_blank'>transaksi-dashboard.php</a> untuk melihat hasilnya.</p>";

?>