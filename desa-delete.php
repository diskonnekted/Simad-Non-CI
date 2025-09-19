<?php
require_once 'config/database.php';
require_once 'config/auth.php';

// Cek autentikasi dan role (hanya admin yang bisa menghapus)
if (!AuthStatic::isLoggedIn()) {
    header('Location: login.php');
    exit;
}

if (!AuthStatic::hasRole(['admin'])) {
    header('Location: desa.php?error=access_denied');
    exit;
}

$user = AuthStatic::getCurrentUser();
$db = getDatabase();
/** @var Database $db */

// Ambil ID desa dari parameter
$desa_id = $_GET['id'] ?? 0;

if (!$desa_id) {
    header('Location: desa.php?error=invalid_id');
    exit;
}

try {
    // Ambil data desa untuk validasi
    $desa = $db->select(
        "SELECT * FROM desa WHERE id = ? AND status != 'deleted'",
        [$desa_id]
    );
    
    if (empty($desa)) {
        header('Location: desa.php?error=not_found');
        exit;
    }
    
    $desa = $desa[0];
    
    // Cek apakah ada transaksi yang terkait (tanpa filter status)
    $transaksi_count = $db->select(
        "SELECT COUNT(*) as count FROM transaksi WHERE desa_id = ?",
        [$desa_id]
    )[0]['count'];
    
    // Cek apakah ada piutang yang masih aktif
    $piutang_count = $db->select(
        "SELECT COUNT(*) as count FROM piutang WHERE desa_id = ? AND status = 'aktif'",
        [$desa_id]
    )[0]['count'];
    
    // Cek apakah ada jadwal kunjungan yang akan datang
    $jadwal_count = $db->select(
        "SELECT COUNT(*) as count FROM jadwal_kunjungan WHERE desa_id = ? AND tanggal >= CURDATE() AND status != 'dibatalkan'",
        [$desa_id]
    )[0]['count'];
    
    // Mulai transaksi database
    $db->beginTransaction();
    
    if ($transaksi_count > 0 || $piutang_count > 0 || $jadwal_count > 0) {
        // Jika ada data terkait, ubah status menjadi nonaktif daripada menghapus
        $db->execute(
            "UPDATE desa SET status = 'nonaktif', updated_at = NOW() WHERE id = ?",
            [$desa_id]
        );
        
        // Batalkan jadwal kunjungan yang akan datang
        if ($jadwal_count > 0) {
            $db->execute(
                "UPDATE jadwal_kunjungan SET status = 'dibatalkan', catatan = CONCAT(COALESCE(catatan, ''), '\n[SISTEM] Dibatalkan karena desa dinonaktifkan') WHERE desa_id = ? AND tanggal >= CURDATE() AND status != 'dibatalkan'",
                [$desa_id]
            );
        }
        
        $db->commit();
        header('Location: desa.php?success=deactivated&name=' . urlencode($desa['nama_desa']));
    } else {
        // Jika tidak ada data terkait, hapus permanen
        $db->execute(
            "UPDATE desa SET status = 'deleted', updated_at = NOW() WHERE id = ?",
            [$desa_id]
        );
        
        $db->commit();
        header('Location: desa.php?success=deleted&name=' . urlencode($desa['nama_desa']));
    }
    
} catch (Exception $e) {
    $db->rollback();
    header('Location: desa.php?error=delete_failed&message=' . urlencode($e->getMessage()));
}

exit;
?>
