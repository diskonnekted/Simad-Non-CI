<?php
require_once 'config/database.php';
require_once 'config/auth.php';

// Skip auth for testing
$db = getDatabase();

// Test query yang sama dengan dashboard.php
$monthly_stats = $db->select("
    SELECT 
        DATE_FORMAT(t.created_at, '%Y-%m') as bulan,
        COUNT(*) as jumlah_transaksi,
        COALESCE(SUM(total_amount), 0) as total_nilai
    FROM transaksi t
    WHERE t.created_at >= CONCAT(YEAR(CURDATE()), '-01-01')
    GROUP BY DATE_FORMAT(t.created_at, '%Y-%m')
    ORDER BY bulan ASC
");

echo "<h2>Data Monthly Stats Dashboard:</h2>";
echo "<pre>";
print_r($monthly_stats);
echo "</pre>";

// Test query yang sama dengan pembelian-statistik.php
$pembelian_monthly = $db->select("
    SELECT 
        DATE_FORMAT(tanggal_pembelian, '%Y-%m') as bulan,
        COUNT(*) as total_pembelian,
        COALESCE(SUM(total_amount), 0) as total_nilai
    FROM pembelian
    WHERE tanggal_pembelian >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(tanggal_pembelian, '%Y-%m')
    ORDER BY bulan ASC
");

echo "<h2>Data Monthly Stats Pembelian:</h2>";
echo "<pre>";
print_r($pembelian_monthly);
echo "</pre>";

// Test struktur tabel transaksi
echo "<h2>Struktur Tabel Transaksi:</h2>";
$struktur = $db->select("DESCRIBE transaksi");
echo "<pre>";
print_r($struktur);
echo "</pre>";

// Test data transaksi sample
echo "<h2>Sample Data Transaksi (5 record terbaru):</h2>";
$sample = $db->select("SELECT * FROM transaksi ORDER BY created_at DESC LIMIT 5");
echo "<pre>";
print_r($sample);
echo "</pre>";
?>