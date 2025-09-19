<?php
require_once 'config/database.php';
require_once 'config/auth.php';

// Check authentication
AuthStatic::requireLogin();

// Check if user has permission to delete transactions
if (!AuthStatic::hasRole(['admin'])) {
    $_SESSION['error'] = 'Anda tidak memiliki izin untuk menghapus transaksi.';
    header('Location: transaksi.php');
    exit;
}

// Check if ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = 'ID transaksi tidak valid.';
    header('Location: transaksi.php');
    exit;
}

$transaksi_id = (int)$_GET['id'];

try {
    $db = getDatabase();
    
    // Start transaction
    $db->beginTransaction();
    
    // Get complete transaction data for logging
    $transaksi_query = "
        SELECT 
            t.*,
            d.nama_desa,
            d.kecamatan,
            d.kabupaten,
            u.nama_lengkap as sales_name,
            b.nama_bank,
            b.kode_bank
        FROM transaksi t
        LEFT JOIN desa d ON t.desa_id = d.id
        LEFT JOIN users u ON t.user_id = u.id
        LEFT JOIN bank b ON t.bank_id = b.id
        WHERE t.id = ?
    ";
    
    $transaksi_data = $db->select($transaksi_query, [$transaksi_id]);
    
    if (empty($transaksi_data)) {
        throw new Exception('Transaksi tidak ditemukan.');
    }
    
    $transaksi = $transaksi_data[0];
    
    // Check if transaction can be deleted (allow all status for admin)
    // Admin can delete any transaction, but we'll log the reason
    $alasan_hapus = 'Dihapus oleh admin: ' . $transaksi['status_transaksi'];
    
    // Get current user for logging
    $current_user = AuthStatic::getCurrentUser();
    
    // Save to log_transaksi before deleting
    $log_query = "
        INSERT INTO log_transaksi (
            transaksi_id, nomor_invoice, desa_id, nama_desa, user_id, nama_user,
            tanggal_transaksi, jenis_transaksi, metode_pembayaran, bank_id, nama_bank,
            dp_amount, tanggal_jatuh_tempo, total_amount, catatan, status_transaksi,
            data_transaksi_json, alasan_hapus, deleted_by, deleted_by_name,
            created_at, updated_at
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
        )
    ";
    
    $log_params = [
        $transaksi['id'],
        $transaksi['nomor_invoice'],
        $transaksi['desa_id'],
        $transaksi['nama_desa'],
        $transaksi['user_id'],
        $transaksi['sales_name'],
        $transaksi['tanggal_transaksi'],
        $transaksi['jenis_transaksi'],
        $transaksi['metode_pembayaran'],
        $transaksi['bank_id'],
        $transaksi['nama_bank'],
        $transaksi['dp_amount'],
        $transaksi['tanggal_jatuh_tempo'],
        $transaksi['total_amount'],
        $transaksi['catatan'],
        $transaksi['status_transaksi'],
        json_encode($transaksi), // Backup lengkap data dalam JSON
        $alasan_hapus,
        $current_user['id'],
        $current_user['nama_lengkap'],
        $transaksi['created_at'],
        $transaksi['updated_at']
    ];
    
    $db->execute($log_query, $log_params);
    
    // Delete related records in correct order
    
    // 1. Delete pembayaran records related to piutang
    $db->execute("DELETE FROM pembayaran WHERE piutang_id IN (SELECT id FROM piutang WHERE transaksi_id = ?)", [$transaksi_id]);
    
    // 2. Delete piutang records
    $db->execute("DELETE FROM piutang WHERE transaksi_id = ?", [$transaksi_id]);
    
    // 3. Delete pembayaran records directly related to transaction
    $db->execute("DELETE FROM pembayaran WHERE transaksi_id = ?", [$transaksi_id]);
    
    // 4. Delete transaction details
    $db->execute("DELETE FROM transaksi_detail WHERE transaksi_id = ?", [$transaksi_id]);
    
    // 5. Delete transaction
    $db->execute("DELETE FROM transaksi WHERE id = ?", [$transaksi_id]);
    
    // Commit transaction
    $db->commit();
    
    $_SESSION['success'] = 'Transaksi ' . $transaksi['nomor_invoice'] . ' berhasil dihapus.';
    
} catch (Exception $e) {
    // Rollback transaction
    if (isset($db)) {
        $db->rollback();
    }
    $_SESSION['error'] = 'Gagal menghapus transaksi: ' . $e->getMessage();
}

header('Location: transaksi.php');
exit;
?>