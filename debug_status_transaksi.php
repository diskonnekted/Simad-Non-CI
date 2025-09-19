<?php
require_once 'config/database.php';

$db = getDatabase();

echo "<h2>Debug Status Transaksi Dashboard</h2>";
echo "<hr>";

// Test query status transaksi
echo "<h3>1. Status Transaksi Query Test</h3>";
$status_stats = $db->select("
    SELECT 
        status_transaksi,
        COUNT(*) as jumlah,
        COALESCE(SUM(total_amount), 0) as total_nilai
    FROM transaksi t
    WHERE 1=1
    GROUP BY status_transaksi
    ORDER BY jumlah DESC
");

echo "<h4>Status Stats:</h4>";
echo "<pre>";
print_r($status_stats);
echo "</pre>";

// Test semua status yang ada di database
echo "<h3>2. Semua Status Transaksi di Database</h3>";
$all_status = $db->select("
    SELECT DISTINCT status_transaksi, COUNT(*) as count
    FROM transaksi 
    GROUP BY status_transaksi
    ORDER BY count DESC
");

echo "<pre>";
print_r($all_status);
echo "</pre>";

// Test data transaksi sample
echo "<h3>3. Sample Data Transaksi (10 terbaru)</h3>";
$sample_data = $db->select("
    SELECT id, nomor_invoice, status_transaksi, total_amount, created_at
    FROM transaksi 
    ORDER BY created_at DESC 
    LIMIT 10
");

echo "<pre>";
print_r($sample_data);
echo "</pre>";

// Test chart data
echo "<h3>4. Chart Data (7 hari terakhir)</h3>";
$chart_data = $db->select("
    SELECT 
        DATE(t.created_at) as tanggal,
        COUNT(*) as jumlah_transaksi,
        COALESCE(SUM(t.total_amount), 0) as total_nilai
    FROM transaksi t
    WHERE t.created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY DATE(t.created_at)
    ORDER BY tanggal ASC
");

echo "<h4>Chart Data:</h4>";
echo "<pre>";
print_r($chart_data);
echo "</pre>";

echo "<h4>Chart Data JSON:</h4>";
echo "<pre>";
echo json_encode($chart_data, JSON_PRETTY_PRINT);
echo "</pre>";

// Test statistik hari ini
echo "<h3>5. Statistik Hari Ini</h3>";
$today_stats = $db->select("
    SELECT 
        COUNT(*) as total_transaksi,
        COALESCE(SUM(total_amount), 0) as total_nilai,
        COALESCE(SUM(CASE WHEN status_transaksi = 'selesai' THEN total_amount ELSE 0 END), 0) as nilai_selesai
    FROM transaksi t
    WHERE DATE(t.created_at) = CURDATE()
");

echo "<pre>";
print_r($today_stats);
echo "</pre>";

echo "<h3>6. Test Tanggal Hari Ini</h3>";
echo "CURDATE(): " . date('Y-m-d') . "<br>";
echo "NOW(): " . date('Y-m-d H:i:s') . "<br>";

// Test transaksi hari ini manual
$today_manual = $db->select("
    SELECT COUNT(*) as count, DATE(created_at) as date_created
    FROM transaksi 
    WHERE DATE(created_at) = '" . date('Y-m-d') . "'
    GROUP BY DATE(created_at)
");

echo "<h4>Manual Today Check:</h4>";
echo "<pre>";
print_r($today_manual);
echo "</pre>";

// Test bulan ini
echo "<h3>7. Statistik Bulan Ini</h3>";
$month_stats = $db->select("
    SELECT 
        COUNT(*) as total_transaksi,
        COALESCE(SUM(total_amount), 0) as total_nilai,
        COALESCE(SUM(CASE WHEN status_transaksi = 'selesai' THEN total_amount ELSE 0 END), 0) as nilai_selesai
    FROM transaksi t
    WHERE MONTH(t.created_at) = MONTH(CURDATE()) 
    AND YEAR(t.created_at) = YEAR(CURDATE())
");

echo "<pre>";
print_r($month_stats);
echo "</pre>";

// Test metode pembayaran
echo "<h3>8. Metode Pembayaran</h3>";
$payment_stats = $db->select("
    SELECT 
        metode_pembayaran,
        COUNT(*) as jumlah,
        COALESCE(SUM(total_amount), 0) as total_nilai
    FROM transaksi t
    WHERE MONTH(t.created_at) = MONTH(CURDATE()) 
    AND YEAR(t.created_at) = YEAR(CURDATE())
    GROUP BY metode_pembayaran
    ORDER BY jumlah DESC
");

echo "<pre>";
print_r($payment_stats);
echo "</pre>";

?>