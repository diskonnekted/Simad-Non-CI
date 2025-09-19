<?php
require_once 'config/database.php';
require_once 'config/auth.php';

// Cek autentikasi
if (!AuthStatic::isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$user = AuthStatic::getCurrentUser();
$db = getDatabase();

echo "<h2>Create Test Piutang</h2>";

try {
    $db->beginTransaction();
    
    // Ambil desa pertama yang aktif
    $desa = $db->select("SELECT id, nama_desa FROM desa WHERE status = 'aktif' LIMIT 1");
    if (empty($desa)) {
        throw new Exception('Tidak ada desa aktif');
    }
    $desa_id = $desa[0]['id'];
    
    // Buat transaksi test dengan tempo
    $invoice_number = 'INV-TEST-' . date('YmdHis');
    $total_amount = 500000;
    $tempo_days = 30;
    $tanggal_jatuh_tempo = date('Y-m-d', strtotime("+{$tempo_days} days"));
    
    $transaksi_query = "
        INSERT INTO transaksi (
            nomor_invoice, desa_id, user_id, tanggal_transaksi, jenis_transaksi,
            metode_pembayaran, total_amount, dp_amount, sisa_amount, tanggal_jatuh_tempo, 
            catatan, status_transaksi, status_pembayaran
        ) VALUES (?, ?, ?, CURDATE(), 'campuran', 'tempo', ?, 0, ?, ?, 'Test transaksi tempo', 'draft', 'belum_bayar')
    ";
    
    $transaksi_params = [
        $invoice_number, $desa_id, $user['id'], 
        $total_amount, $total_amount, $tanggal_jatuh_tempo
    ];
    
    $db->execute($transaksi_query, $transaksi_params);
    $transaksi_id = $db->lastInsertId();
    
    echo "<p>Transaksi created with ID: {$transaksi_id}</p>";
    
    // Buat piutang
    $piutang_query = "
        INSERT INTO piutang (
            transaksi_id, desa_id, jumlah_piutang, tanggal_jatuh_tempo, status
        ) VALUES (?, ?, ?, ?, 'belum_jatuh_tempo')
    ";
    
    $piutang_params = [
        $transaksi_id, $desa_id, $total_amount, $tanggal_jatuh_tempo
    ];
    
    $db->execute($piutang_query, $piutang_params);
    $piutang_id = $db->lastInsertId();
    
    echo "<p>Piutang created with ID: {$piutang_id}</p>";
    
    // Buat transaksi test dengan DP
    $invoice_number2 = 'INV-TEST-DP-' . date('YmdHis');
    $total_amount2 = 1000000;
    $dp_amount = 300000;
    $sisa_amount = $total_amount2 - $dp_amount;
    $tanggal_jatuh_tempo2 = date('Y-m-d', strtotime('+30 days'));
    
    $transaksi_query2 = "
        INSERT INTO transaksi (
            nomor_invoice, desa_id, user_id, tanggal_transaksi, jenis_transaksi,
            metode_pembayaran, total_amount, dp_amount, sisa_amount, tanggal_jatuh_tempo, 
            catatan, status_transaksi, status_pembayaran
        ) VALUES (?, ?, ?, CURDATE(), 'campuran', 'dp_pelunasan', ?, ?, ?, ?, 'Test transaksi DP', 'draft', 'dp')
    ";
    
    $transaksi_params2 = [
        $invoice_number2, $desa_id, $user['id'], 
        $total_amount2, $dp_amount, $sisa_amount, $tanggal_jatuh_tempo2
    ];
    
    $db->execute($transaksi_query2, $transaksi_params2);
    $transaksi_id2 = $db->lastInsertId();
    
    echo "<p>Transaksi DP created with ID: {$transaksi_id2}</p>";
    
    // Buat piutang untuk DP
    $piutang_query2 = "
        INSERT INTO piutang (
            transaksi_id, desa_id, jumlah_piutang, tanggal_jatuh_tempo, status
        ) VALUES (?, ?, ?, ?, 'belum_jatuh_tempo')
    ";
    
    $piutang_params2 = [
        $transaksi_id2, $desa_id, $sisa_amount, $tanggal_jatuh_tempo2
    ];
    
    $db->execute($piutang_query2, $piutang_params2);
    $piutang_id2 = $db->lastInsertId();
    
    echo "<p>Piutang DP created with ID: {$piutang_id2}</p>";
    
    $db->commit();
    
    echo "<p style='color: green;'><strong>Test data berhasil dibuat!</strong></p>";
    echo "<p><a href='piutang.php'>Lihat Halaman Piutang</a></p>";
    echo "<p><a href='debug_piutang.php'>Debug Piutang</a></p>";
    
} catch (Exception $e) {
    $db->rollback();
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?>