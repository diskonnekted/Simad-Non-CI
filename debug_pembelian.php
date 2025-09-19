<?php
require_once 'config/database.php';
$db = new Database();

echo "<h2>=== ANALISIS DATA PEMBELIAN ===</h2>";

// Cek total pembelian
$total = $db->select("SELECT COUNT(*) as total, SUM(total_amount) as sum_total FROM pembelian");
echo "<p><strong>Total Pembelian:</strong> " . $total[0]['total'] . "</p>";
echo "<p><strong>Total Nilai:</strong> Rp " . number_format($total[0]['sum_total'], 0, ',', '.') . "</p>";

// Cek pembelian dengan nilai tertinggi
$highest = $db->select("SELECT id, nomor_po, total_amount, tanggal_pembelian FROM pembelian ORDER BY total_amount DESC LIMIT 10");
echo "<h3>=== 10 PEMBELIAN DENGAN NILAI TERTINGGI ===</h3>";
echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr><th>ID</th><th>Nomor PO</th><th>Nilai</th><th>Tanggal</th></tr>";
foreach($highest as $p) {
    echo "<tr>";
    echo "<td>" . $p['id'] . "</td>";
    echo "<td>" . $p['nomor_po'] . "</td>";
    echo "<td>Rp " . number_format($p['total_amount'], 0, ',', '.') . "</td>";
    echo "<td>" . $p['tanggal_pembelian'] . "</td>";
    echo "</tr>";
}
echo "</table>";

echo "<h3>=== PEMBELIAN TAHUN INI ===</h3>";
$yearly = $db->select("SELECT DATE_FORMAT(created_at, '%Y-%m') as bulan, COUNT(*) as jumlah, SUM(total_amount) as total FROM pembelian WHERE created_at >= CONCAT(YEAR(CURDATE()), '-01-01') GROUP BY DATE_FORMAT(created_at, '%Y-%m') ORDER BY bulan");
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

// Cek data yang digunakan di dashboard
echo "<h3>=== DATA YANG DIGUNAKAN DI DASHBOARD ===</h3>";
$dashboard_data = $db->select("
    SELECT 
        DATE_FORMAT(p.created_at, '%Y-%m') as bulan,
        COUNT(*) as jumlah_pembelian,
        COALESCE(SUM(p.total_amount), 0) as total_nilai_pembelian
    FROM pembelian p
    WHERE p.created_at >= CONCAT(YEAR(CURDATE()), '-01-01')
    GROUP BY DATE_FORMAT(p.created_at, '%Y-%m')
    ORDER BY bulan ASC
");

echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr><th>Bulan</th><th>Jumlah</th><th>Total Nilai</th><th>Dalam Juta</th></tr>";
$total_dashboard = 0;
foreach($dashboard_data as $d) {
    $nilai_juta = round($d['total_nilai_pembelian'] / 1000000);
    $total_dashboard += $nilai_juta;
    echo "<tr>";
    echo "<td>" . $d['bulan'] . "</td>";
    echo "<td>" . $d['jumlah_pembelian'] . "</td>";
    echo "<td>Rp " . number_format($d['total_nilai_pembelian'], 0, ',', '.') . "</td>";
    echo "<td>" . $nilai_juta . " juta</td>";
    echo "</tr>";
}
echo "</table>";

echo "<p><strong>Total yang akan ditampilkan di dashboard:</strong> " . $total_dashboard . " juta</p>";
echo "<p><strong>Dalam rupiah:</strong> Rp " . number_format($total_dashboard * 1000000, 0, ',', '.') . "</p>";
?>