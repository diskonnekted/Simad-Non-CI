<?php
require_once 'config/auth.php';
require_once 'config/database.php';

// Set header untuk JSON response
header('Content-Type: application/json');

// Cek autentikasi
$auth = new Auth();
if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode([
        'status' => 'error',
        'message' => 'Unauthorized access'
    ]);
    exit;
}

// Cek method POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'status' => 'error',
        'message' => 'Method not allowed'
    ]);
    exit;
}

// Ambil data dari POST
$pembelian_id = $_POST['pembelian_id'] ?? null;
$action = $_POST['action'] ?? null;

if (!$pembelian_id || $action !== 'konfirmasi_penerimaan') {
    echo json_encode([
        'status' => 'error',
        'message' => 'Parameter tidak valid'
    ]);
    exit;
}

$db = getDatabase();

try {
    $db->beginTransaction();
    
    // Ambil data pembelian
    $pembelian = $db->select("
        SELECT p.*, v.nama_vendor
        FROM pembelian p
        JOIN vendor v ON p.vendor_id = v.id
        WHERE p.id = ? AND p.status_pembelian = 'dikirim'
    ", [$pembelian_id]);
    
    if (empty($pembelian)) {
        throw new Exception('PO tidak ditemukan atau status tidak valid');
    }
    
    $po = $pembelian[0];
    
    // Ambil detail pembelian
    $details = $db->select("
        SELECT pd.*, pr.nama_produk
        FROM pembelian_detail pd
        JOIN produk pr ON pd.produk_id = pr.id
        WHERE pd.pembelian_id = ?
    ", [$pembelian_id]);
    
    if (empty($details)) {
        throw new Exception('Detail pembelian tidak ditemukan');
    }
    
    // Generate nomor penerimaan
    $nomor_penerimaan = 'RCV-' . date('Ymd') . '-' . str_pad($pembelian_id, 4, '0', STR_PAD_LEFT);
    
    // Insert penerimaan barang
    $db->execute("
        INSERT INTO penerimaan_barang (
            pembelian_id, nomor_penerimaan, tanggal_terima, user_id, catatan
        ) VALUES (?, ?, ?, ?, ?)
    ", [
        $pembelian_id,
        $nomor_penerimaan,
        date('Y-m-d'),
        $_SESSION['user_id'],
        'Konfirmasi penerimaan otomatis'
    ]);
    
    $penerimaan_id = $db->lastInsertId();
    
    // Update detail pembelian dan insert detail penerimaan
    foreach ($details as $detail) {
        // Update quantity_terima di pembelian_detail
        $db->execute("
            UPDATE pembelian_detail 
            SET quantity_terima = quantity_pesan 
            WHERE id = ?
        ", [$detail['id']]);
        
        // Insert detail penerimaan
        $db->execute("
            INSERT INTO penerimaan_detail (
                penerimaan_id, pembelian_detail_id, quantity_terima, kondisi, catatan
            ) VALUES (?, ?, ?, 'baik', 'Diterima lengkap')
        ", [
            $penerimaan_id,
            $detail['id'],
            $detail['quantity_pesan']
        ]);
        
        // Update stok produk
        $db->execute("
            UPDATE produk 
            SET stok_tersedia = stok_tersedia + ? 
            WHERE id = ?
        ", [$detail['quantity_pesan'], $detail['produk_id']]);
    }
    
    // Update status pembelian
    $db->execute("
        UPDATE pembelian 
        SET status_pembelian = 'diterima_lengkap',
            updated_at = NOW()
        WHERE id = ?
    ", [$pembelian_id]);
    
    $db->commit();
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Penerimaan barang berhasil dikonfirmasi',
        'data' => [
            'nomor_penerimaan' => $nomor_penerimaan,
            'nomor_po' => $po['nomor_po'],
            'vendor' => $po['nama_vendor'],
            'total_items' => count($details)
        ]
    ]);
    
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollback();
    }
    
    echo json_encode([
        'status' => 'error',
        'message' => 'Gagal memproses konfirmasi: ' . $e->getMessage()
    ]);
}
?>