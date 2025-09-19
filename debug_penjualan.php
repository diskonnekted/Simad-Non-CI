<?php
require_once 'config/database.php';
$db = new Database();

echo "<h2>=== ANALISIS DATA PENJUALAN/TRANSAKSI ===</h2>";

// Cek total transaksi
$total = $db->select("SELECT COUNT(*) as total, SUM(total_amount) as sum_total FROM transaksi");
echo "<p><strong>Total Transaksi:</strong> " . $total[0]['total'] . "</p>";
echo "<p><strong>Total Nilai:</strong> Rp " . number_format($total[0]['sum_total'], 0, ',', '.') . "</p>";

// Cek transaksi dengan nilai tertinggi
$highest = $db->select("SELECT id, nomor_invoice, total_amount, tanggal_transaksi, status_transaksi FROM transaksi ORDER BY total_amount DESC LIMIT 10");
echo "<h3>=== 10 TRANSAKSI DENGAN NILAI TERTINGGI ===</h3>";
echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr><th>ID</th><th>Invoice</th><th>Nilai</th><th>Tanggal</th><th>Status</th></tr>";
foreach($highest as $t) {
    echo "<tr>";
    echo "<td>" . $t['id'] . "</td>";
    echo "<td>" . $t['nomor_invoice'] . "</td>";
    echo "<td>Rp " . number_format($t['total_amount'], 0, ',', '.') . "</td>";
    echo "<td>" . $t['tanggal_transaksi'] . "</td>";
    echo "<td>" . $t['status_transaksi'] . "</td>";
    echo "</tr>";
}
echo "</table>";

echo "<h3>=== TRANSAKSI TAHUN INI ===</h3>";
$yearly = $db->select("SELECT DATE_FORMAT(tanggal_transaksi, '%Y-%m') as bulan, COUNT(*) as jumlah, SUM(total_amount) as total FROM transaksi WHERE tanggal_transaksi >= CONCAT(YEAR(CURDATE()), '-01-01') GROUP BY DATE_FORMAT(tanggal_transaksi, '%Y-%m') ORDER BY bulan");
echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr><th>Bulan</th><th>Jumlah</th><th>Total Nilai</th></tr>";
foreach($yearly as $y) {
    echo "<tr>";
    echo "<td>" . $y['bulan'] . "</td>";
    echo "<td>" . $y['jumlah'] . "</td>";
    echo "<td>Rp " . number_format($y['total'], 0, ',', '.') . "</td>";
    echo "</tr>";
}
echo "</table>";

// Cek data yang digunakan di dashboard untuk chart
echo "<h3>=== DATA YANG DIGUNAKAN DI DASHBOARD (API CHART) ===</h3>";
$dashboard_data = $db->select("
    SELECT 
        DATE_FORMAT(tanggal_transaksi, '%Y-%m') as bulan,
        COALESCE(SUM(total_amount), 0) as nominal_penjualan,
        COUNT(*) as jumlah_transaksi
    FROM transaksi 
    WHERE tanggal_transaksi >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(tanggal_transaksi, '%Y-%m')
    ORDER BY bulan ASC
");

echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr><th>Bulan</th><th>Jumlah Transaksi</th><th>Nominal Penjualan</th></tr>";
$total_dashboard = 0;
foreach($dashboard_data as $d) {
    $total_dashboard += $d['nominal_penjualan'];
    echo "<tr>";
    echo "<td>" . $d['bulan'] . "</td>";
    echo "<td>" . $d['jumlah_transaksi'] . "</td>";
    echo "<td>Rp " . number_format($d['nominal_penjualan'], 0, ',', '.') . "</td>";
    echo "</tr>";
}
echo "</table>";

echo "<p><strong>Total yang akan ditampilkan di dashboard:</strong> Rp " . number_format($total_dashboard, 0, ',', '.') . "</p>";

// Cek transaksi terbaru
echo "<h3>=== 5 TRANSAKSI TERBARU ===</h3>";
$recent = $db->select("SELECT id, nomor_invoice, total_amount, tanggal_transaksi, status_transaksi, created_at FROM transaksi ORDER BY created_at DESC LIMIT 5");
echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr><th>ID</th><th>Invoice</th><th>Nilai</th><th>Tanggal Transaksi</th><th>Status</th><th>Created At</th></tr>";
foreach($recent as $r) {
    echo "<tr>";
    echo "<td>" . $r['id'] . "</td>";
    echo "<td>" . $r['nomor_invoice'] . "</td>";
    echo "<td>Rp " . number_format($r['total_amount'], 0, ',', '.') . "</td>";
    echo "<td>" . $r['tanggal_transaksi'] . "</td>";
    echo "<td>" . $r['status_transaksi'] . "</td>";
    echo "<td>" . $r['created_at'] . "</td>";
    echo "</tr>";
}
echo "</table>";
?>