<?php
require_once 'config/database.php';

$db = getDatabase();

echo "<h2>Membersihkan Semua Data Kosong</h2>";
echo "<hr>";

// 1. Cek data kosong yang masih ada
echo "<h3>1. Data Kosong yang Masih Ada</h3>";
$empty_status = $db->select("
    SELECT COUNT(*) as jumlah_status_kosong
    FROM transaksi 
    WHERE status_transaksi IS NULL OR status_transaksi = ''
");

$empty_payment = $db->select("
    SELECT COUNT(*) as jumlah_payment_kosong
    FROM transaksi 
    WHERE metode_pembayaran IS NULL OR metode_pembayaran = ''
");

echo "Status transaksi kosong: " . $empty_status[0]['jumlah_status_kosong'] . " baris<br>";
echo "Metode pembayaran kosong: " . $empty_payment[0]['jumlah_payment_kosong'] . " baris<br>";

// 2. Update SEMUA status kosong
echo "<h3>2. Mengisi SEMUA Status Kosong</h3>";
$fix_all_status = $db->execute("
    UPDATE transaksi 
    SET status_transaksi = 'selesai' 
    WHERE status_transaksi IS NULL OR status_transaksi = '' OR TRIM(status_transaksi) = ''
");
echo "Status kosong diperbaiki: " . $fix_all_status . " baris<br>";

// 3. Update SEMUA metode pembayaran kosong
echo "<h3>3. Mengisi SEMUA Metode Pembayaran Kosong</h3>";
$fix_all_payment = $db->execute("
    UPDATE transaksi 
    SET metode_pembayaran = 'transfer' 
    WHERE metode_pembayaran IS NULL OR metode_pembayaran = '' OR TRIM(metode_pembayaran) = ''
");
echo "Metode pembayaran kosong diperbaiki: " . $fix_all_payment . " baris<br>";

// 4. Verifikasi tidak ada lagi data kosong
echo "<h3>4. Verifikasi Final</h3>";
$verify_status = $db->select("
    SELECT COUNT(*) as jumlah_status_kosong
    FROM transaksi 
    WHERE status_transaksi IS NULL OR status_transaksi = '' OR TRIM(status_transaksi) = ''
");

$verify_payment = $db->select("
    SELECT COUNT(*) as jumlah_payment_kosong
    FROM transaksi 
    WHERE metode_pembayaran IS NULL OR metode_pembayaran = '' OR TRIM(metode_pembayaran) = ''
");

echo "Status transaksi kosong tersisa: " . $verify_status[0]['jumlah_status_kosong'] . " baris<br>";
echo "Metode pembayaran kosong tersisa: " . $verify_payment[0]['jumlah_payment_kosong'] . " baris<br>";

// 5. Tampilkan distribusi final
echo "<h3>5. Distribusi Status Transaksi Final</h3>";
$status_distribution = $db->select("
    SELECT 
        status_transaksi,
        COUNT(*) as jumlah,
        ROUND((COUNT(*) * 100.0 / (SELECT COUNT(*) FROM transaksi)), 1) as persentase
    FROM transaksi
    GROUP BY status_transaksi
    ORDER BY jumlah DESC
");

echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr><th>Status</th><th>Jumlah</th><th>Persentase</th></tr>";
foreach($status_distribution as $row) {
    echo "<tr>";
    echo "<td>" . ($row['status_transaksi'] ?: 'KOSONG') . "</td>";
    echo "<td>" . $row['jumlah'] . "</td>";
    echo "<td>" . $row['persentase'] . "%</td>";
    echo "</tr>";
}
echo "</table>";

echo "<h3>6. Distribusi Metode Pembayaran Final</h3>";
$payment_distribution = $db->select("
    SELECT 
        metode_pembayaran,
        COUNT(*) as jumlah,
        ROUND((COUNT(*) * 100.0 / (SELECT COUNT(*) FROM transaksi)), 1) as persentase
    FROM transaksi
    GROUP BY metode_pembayaran
    ORDER BY jumlah DESC
");

echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr><th>Metode Pembayaran</th><th>Jumlah</th><th>Persentase</th></tr>";
foreach($payment_distribution as $row) {
    echo "<tr>";
    echo "<td>" . ($row['metode_pembayaran'] ?: 'KOSONG') . "</td>";
    echo "<td>" . $row['jumlah'] . "</td>";
    echo "<td>" . $row['persentase'] . "%</td>";
    echo "</tr>";
}
echo "</table>";

if($verify_status[0]['jumlah_status_kosong'] == 0 && $verify_payment[0]['jumlah_payment_kosong'] == 0) {
    echo "<h3 style='color: green;'>✅ SUKSES! Semua Data Kosong Telah Dibersihkan!</h3>";
    echo "<p style='color: green; font-weight: bold;'>Dashboard transaksi sekarang siap digunakan dengan data yang lengkap.</p>";
} else {
    echo "<h3 style='color: red;'>⚠️ Masih Ada Data Kosong!</h3>";
    echo "<p style='color: red;'>Perlu pengecekan lebih lanjut.</p>";
}

echo "<p><a href='transaksi-dashboard.php' target='_blank' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Lihat Dashboard Transaksi</a></p>";

?>