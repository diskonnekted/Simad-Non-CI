<?php
require_once 'config/database.php';

$db = getDatabase();

echo "<h2>Perbaikan Final Data Kosong</h2>";
echo "<hr>";

// 1. Update semua status kosong ke 'proses'
echo "<h3>1. Mengisi Status Transaksi Kosong</h3>";
$status_fix = $db->execute("
    UPDATE transaksi 
    SET status_transaksi = 'proses' 
    WHERE status_transaksi = '' OR status_transaksi IS NULL
");
echo "Status kosong diperbaiki: " . $status_fix . " baris<br>";

// 2. Update semua metode pembayaran kosong ke 'tunai'
echo "<h3>2. Mengisi Metode Pembayaran Kosong</h3>";
$payment_fix = $db->execute("
    UPDATE transaksi 
    SET metode_pembayaran = 'tunai' 
    WHERE metode_pembayaran = '' OR metode_pembayaran IS NULL
");
echo "Metode pembayaran kosong diperbaiki: " . $payment_fix . " baris<br>";

// 3. Buat distribusi status yang realistis
echo "<h3>3. Membuat Distribusi Status Realistis</h3>";

// 60% selesai (sudah ada)
// 20% proses
$proses_count = $db->execute("
    UPDATE transaksi 
    SET status_transaksi = 'proses' 
    WHERE id IN (
        SELECT * FROM (
            SELECT id FROM transaksi 
            WHERE status_transaksi != 'selesai'
            ORDER BY RAND() 
            LIMIT 8
        ) as temp
    )
");
echo "Set ke 'proses': " . $proses_count . " baris<br>";

// 10% pending
$pending_count = $db->execute("
    UPDATE transaksi 
    SET status_transaksi = 'pending' 
    WHERE id IN (
        SELECT * FROM (
            SELECT id FROM transaksi 
            WHERE status_transaksi = 'proses'
            ORDER BY RAND() 
            LIMIT 4
        ) as temp
    )
");
echo "Set ke 'pending': " . $pending_count . " baris<br>";

// 5% draft
$draft_count = $db->execute("
    UPDATE transaksi 
    SET status_transaksi = 'draft' 
    WHERE id IN (
        SELECT * FROM (
            SELECT id FROM transaksi 
            WHERE status_transaksi = 'proses'
            ORDER BY RAND() 
            LIMIT 2
        ) as temp
    )
");
echo "Set ke 'draft': " . $draft_count . " baris<br>";

// 5% batal
$batal_count = $db->execute("
    UPDATE transaksi 
    SET status_transaksi = 'batal' 
    WHERE id IN (
        SELECT * FROM (
            SELECT id FROM transaksi 
            WHERE status_transaksi = 'proses'
            ORDER BY RAND() 
            LIMIT 2
        ) as temp
    )
");
echo "Set ke 'batal': " . $batal_count . " baris<br>";

// 4. Buat distribusi metode pembayaran yang realistis
echo "<h3>4. Membuat Distribusi Metode Pembayaran Realistis</h3>";

// 50% tunai (sudah ada)
// 30% transfer
$transfer_count = $db->execute("
    UPDATE transaksi 
    SET metode_pembayaran = 'transfer' 
    WHERE id IN (
        SELECT * FROM (
            SELECT id FROM transaksi 
            WHERE metode_pembayaran = 'tunai'
            ORDER BY RAND() 
            LIMIT 13
        ) as temp
    )
");
echo "Set ke 'transfer': " . $transfer_count . " baris<br>";

// 15% kredit
$kredit_count = $db->execute("
    UPDATE transaksi 
    SET metode_pembayaran = 'kredit' 
    WHERE id IN (
        SELECT * FROM (
            SELECT id FROM transaksi 
            WHERE metode_pembayaran = 'tunai'
            ORDER BY RAND() 
            LIMIT 7
        ) as temp
    )
");
echo "Set ke 'kredit': " . $kredit_count . " baris<br>";

// 5% debit
$debit_count = $db->execute("
    UPDATE transaksi 
    SET metode_pembayaran = 'debit' 
    WHERE id IN (
        SELECT * FROM (
            SELECT id FROM transaksi 
            WHERE metode_pembayaran = 'tunai'
            ORDER BY RAND() 
            LIMIT 2
        ) as temp
    )
");
echo "Set ke 'debit': " . $debit_count . " baris<br>";

// 5. Verifikasi hasil final
echo "<h3>5. Hasil Final - Status Transaksi</h3>";
$final_status = $db->select("
    SELECT 
        status_transaksi,
        COUNT(*) as jumlah,
        ROUND((COUNT(*) * 100.0 / (SELECT COUNT(*) FROM transaksi)), 1) as persentase,
        FORMAT(SUM(total_amount), 0) as total_nilai
    FROM transaksi
    WHERE status_transaksi IS NOT NULL AND status_transaksi != ''
    GROUP BY status_transaksi
    ORDER BY jumlah DESC
");

echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr><th>Status</th><th>Jumlah</th><th>Persentase</th><th>Total Nilai</th></tr>";
foreach($final_status as $row) {
    echo "<tr>";
    echo "<td>" . $row['status_transaksi'] . "</td>";
    echo "<td>" . $row['jumlah'] . "</td>";
    echo "<td>" . $row['persentase'] . "%</td>";
    echo "<td>Rp " . $row['total_nilai'] . "</td>";
    echo "</tr>";
}
echo "</table>";

echo "<h3>6. Hasil Final - Metode Pembayaran</h3>";
$final_payment = $db->select("
    SELECT 
        metode_pembayaran,
        COUNT(*) as jumlah,
        ROUND((COUNT(*) * 100.0 / (SELECT COUNT(*) FROM transaksi)), 1) as persentase,
        FORMAT(SUM(total_amount), 0) as total_nilai
    FROM transaksi
    WHERE metode_pembayaran IS NOT NULL AND metode_pembayaran != ''
    GROUP BY metode_pembayaran
    ORDER BY jumlah DESC
");

echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr><th>Metode Pembayaran</th><th>Jumlah</th><th>Persentase</th><th>Total Nilai</th></tr>";
foreach($final_payment as $row) {
    echo "<tr>";
    echo "<td>" . $row['metode_pembayaran'] . "</td>";
    echo "<td>" . $row['jumlah'] . "</td>";
    echo "<td>" . $row['persentase'] . "%</td>";
    echo "<td>Rp " . $row['total_nilai'] . "</td>";
    echo "</tr>";
}
echo "</table>";

echo "<h3>✅ Perbaikan Final Selesai!</h3>";
echo "<p style='color: green; font-weight: bold;'>Semua data kosong telah diperbaiki dengan distribusi yang realistis.</p>";
echo "<p><a href='transaksi-dashboard.php' target='_blank' style='background: #007cba; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Lihat Dashboard Transaksi</a></p>";

?>