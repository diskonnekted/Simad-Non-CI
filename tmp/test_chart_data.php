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

echo "<h2>Raw Monthly Stats Data:</h2>";
echo "<pre>";
print_r($monthly_stats);
echo "</pre>";

echo "<h2>JSON Encoded Data (seperti di dashboard.php):</h2>";
echo "<pre>";
echo json_encode($monthly_stats, JSON_PRETTY_PRINT);
echo "</pre>";

// Simulasi JavaScript processing seperti di dashboard.php
echo "<h2>Processed Chart Data (Dashboard Style):</h2>";
if ($monthly_stats && count($monthly_stats) > 0) {
    $chartLabels = [];
    $transaksiData = [];
    $nilaiData = [];
    
    foreach ($monthly_stats as $item) {
        list($year, $month) = explode('-', $item['bulan']);
        $monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
        $chartLabels[] = $monthNames[intval($month) - 1] . ' ' . $year;
        $transaksiData[] = intval($item['jumlah_transaksi']);
        $nilaiData[] = round(floatval($item['total_nilai']) / 1000000);
    }
    
    echo "<p><strong>Chart Labels:</strong></p>";
    echo "<pre>";
    print_r($chartLabels);
    echo "</pre>";
    
    echo "<p><strong>Transaksi Data:</strong></p>";
    echo "<pre>";
    print_r($transaksiData);
    echo "</pre>";
    
    echo "<p><strong>Nilai Data (in millions):</strong></p>";
    echo "<pre>";
    print_r($nilaiData);
    echo "</pre>";
} else {
    echo "<p>No data found - would show empty chart</p>";
}

// Test pembelian data untuk perbandingan
echo "<h2>Pembelian Data for Comparison:</h2>";
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

if ($pembelian_monthly && count($pembelian_monthly) > 0) {
    $chartLabels = [];
    $pembelianData = [];
    $nilaiData = [];
    
    foreach ($pembelian_monthly as $item) {
        list($year, $month) = explode('-', $item['bulan']);
        $monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
        $chartLabels[] = $monthNames[intval($month) - 1] . ' ' . $year;
        $pembelianData[] = intval($item['total_pembelian']);
        $nilaiData[] = round(floatval($item['total_nilai']) / 1000000);
    }
    
    echo "<p><strong>Pembelian Chart Labels:</strong></p>";
    echo "<pre>";
    print_r($chartLabels);
    echo "</pre>";
    
    echo "<p><strong>Pembelian Data:</strong></p>";
    echo "<pre>";
    print_r($pembelianData);
    echo "</pre>";
    
    echo "<p><strong>Pembelian Nilai Data (in millions):</strong></p>";
    echo "<pre>";
    print_r($nilaiData);
    echo "</pre>";
}
?>