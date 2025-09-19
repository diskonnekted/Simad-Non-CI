<?php
require_once '../config/database.php';
require_once '../config/auth.php';

// Set header untuk JSON response
header('Content-Type: application/json');

// Cek autentikasi dan role
if (!AuthStatic::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Cek role akses
if (!AuthStatic::hasRole(['admin', 'akunting', 'supervisor'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

// Hanya terima POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$user = AuthStatic::getCurrentUser();
$db = getDatabase();

try {
    // Ambil data dari request
    $input = json_decode(file_get_contents('php://input'), true);
    $pembelian_id = intval($input['pembelian_id'] ?? 0);
    
    if (!$pembelian_id) {
        throw new Exception('ID pembelian tidak valid');
    }
    
    // Cek apakah PO ada dan statusnya 'dikirim'
    $pembelian = $db->select("
        SELECT p.*, v.nama_vendor 
        FROM pembelian p 
        JOIN vendor v ON p.vendor_id = v.id 
        WHERE p.id = ? AND p.status_pembelian = 'dikirim'
    ", [$pembelian_id]);
    
    if (empty($pembelian)) {
        throw new Exception('PO tidak ditemukan atau status bukan dikirim');
    }
    
    $po = $pembelian[0];
    
    // Ambil detail pembelian yang belum diterima lengkap
    $detail_items = $db->select("
        SELECT pd.*, pr.nama_produk, pr.satuan,
               (pd.quantity_pesan - pd.quantity_terima) as sisa_quantity
        FROM pembelian_detail pd
        LEFT JOIN produk pr ON pd.produk_id = pr.id
        WHERE pd.pembelian_id = ? AND pd.quantity_pesan > pd.quantity_terima
        ORDER BY pd.id
    ", [$pembelian_id]);
    
    if (empty($detail_items)) {
        throw new Exception('Semua item sudah diterima lengkap');
    }
    
    $db->beginTransaction();
    
    // Generate nomor penerimaan otomatis
    $today = date('Ymd');
    $last_gr = $db->select("
        SELECT nomor_penerimaan 
        FROM penerimaan_barang 
        WHERE nomor_penerimaan LIKE 'GR-{$today}-%' 
        ORDER BY nomor_penerimaan DESC 
        LIMIT 1
    ");
    
    if (!empty($last_gr)) {
        $last_number = intval(substr($last_gr[0]['nomor_penerimaan'], -3));
        $new_number = $last_number + 1;
    } else {
        $new_number = 1;
    }
    
    $nomor_penerimaan = 'GR-' . $today . '-' . str_pad($new_number, 3, '0', STR_PAD_LEFT);
    
    // Insert penerimaan_barang
    $penerimaan_query = "
        INSERT INTO penerimaan_barang (
            pembelian_id, nomor_penerimaan, tanggal_terima, user_id, catatan
        ) VALUES (?, ?, ?, ?, ?)
    ";
    
    $catatan = 'Konfirmasi penerimaan cepat - semua item diterima dalam kondisi baik';
    $tanggal_terima = date('Y-m-d');
    
    $db->execute($penerimaan_query, [
        $pembelian_id, $nomor_penerimaan, $tanggal_terima, $user['id'], $catatan
    ]);
    
    $penerimaan_id = $db->lastInsertId();
    
    $total_items_processed = 0;
    $total_stok_added = 0;
    
    // Process setiap item yang belum diterima
    foreach ($detail_items as $item) {
        $sisa_quantity = $item['sisa_quantity'];
        
        if ($sisa_quantity > 0) {
            // Insert penerimaan_detail
            $detail_query = "
                INSERT INTO penerimaan_detail (
                    penerimaan_id, pembelian_detail_id, quantity_terima, kondisi, catatan
                ) VALUES (?, ?, ?, ?, ?)
            ";
            
            $db->execute($detail_query, [
                $penerimaan_id, 
                $item['id'], 
                $sisa_quantity, 
                'baik', 
                'Konfirmasi cepat - kondisi baik'
            ]);
            
            $total_items_processed++;
            $total_stok_added += $sisa_quantity;
        }
    }
    
    // Update status pembelian menjadi diterima_lengkap
    $db->execute("
        UPDATE pembelian 
        SET status_pembelian = 'diterima_lengkap'
        WHERE id = ?
    ", [$pembelian_id]);
    
    $db->commit();
    
    // Response sukses
    echo json_encode([
        'success' => true,
        'message' => 'Penerimaan berhasil dikonfirmasi',
        'data' => [
            'nomor_penerimaan' => $nomor_penerimaan,
            'nomor_po' => $po['nomor_po'],
            'vendor' => $po['nama_vendor'],
            'total_items' => $total_items_processed,
            'total_stok_added' => $total_stok_added,
            'tanggal_terima' => date('d/m/Y', strtotime($tanggal_terima))
        ]
    ]);
    
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollback();
    }
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>