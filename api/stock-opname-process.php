<?php
require_once '../config/database.php';
require_once '../config/auth.php';

header('Content-Type: application/json');

// Check authentication
if (!AuthStatic::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Check role permission
if (!AuthStatic::hasRole(['admin', 'sales'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

// Only allow POST method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $user = AuthStatic::getCurrentUser();
    $db = getDatabase();
    
    // Get stock data from request
    $stock_data_json = $_POST['stock_data'] ?? '';
    
    if (empty($stock_data_json)) {
        throw new Exception('Data stock opname tidak ditemukan');
    }
    
    $stock_data = json_decode($stock_data_json, true);
    
    if (!is_array($stock_data) || empty($stock_data)) {
        throw new Exception('Format data stock opname tidak valid');
    }
    
    // Start database transaction
    $pdo = $db->getConnection();
    $pdo->beginTransaction();
    
    $opname_date = date('Y-m-d H:i:s');
    $updated_products = [];
    
    foreach ($stock_data as $item) {
        // Validate required fields
        if (!isset($item['produk_id']) || !isset($item['stok_sistem']) || !isset($item['stok_fisik'])) {
            throw new Exception('Data produk tidak lengkap');
        }
        
        $produk_id = (int)$item['produk_id'];
        $stok_sistem = (int)$item['stok_sistem'];
        $stok_fisik = (int)$item['stok_fisik'];
        $selisih = (int)$item['selisih'];
        $keterangan = trim($item['keterangan'] ?? '');
        
        // Validate product exists and get current data
        $produk = $db->select(
            "SELECT id, nama_produk, kode_produk, stok_tersedia FROM produk WHERE id = ? AND status = 'aktif'",
            [$produk_id]
        );
        
        if (empty($produk)) {
            throw new Exception("Produk dengan ID {$produk_id} tidak ditemukan atau tidak aktif");
        }
        
        $produk = $produk[0];
        
        // Verify current stock matches system stock (in case of concurrent updates)
        if ($produk['stok_tersedia'] != $stok_sistem) {
            throw new Exception("Stok sistem untuk produk '{$produk['nama_produk']}' telah berubah. Silakan refresh halaman dan coba lagi.");
        }
        
        // Validate stock fisik is not negative
        if ($stok_fisik < 0) {
            throw new Exception("Stok fisik untuk produk '{$produk['nama_produk']}' tidak boleh negatif");
        }
        
        // Insert stock opname record
        $insert_opname = "
            INSERT INTO stock_opname (
                produk_id, user_id, tanggal_opname, stok_sistem, stok_fisik, 
                selisih, keterangan, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ";
        
        $db->execute($insert_opname, [
            $produk_id, $user['id'], $opname_date, $stok_sistem, 
            $stok_fisik, $selisih, $keterangan, $opname_date
        ]);
        
        // Update product stock
        $update_stock = "UPDATE produk SET stok_tersedia = ?, updated_at = ? WHERE id = ?";
        $db->execute($update_stock, [$stok_fisik, $opname_date, $produk_id]);
        
        $updated_products[] = [
            'id' => $produk_id,
            'nama_produk' => $produk['nama_produk'],
            'kode_produk' => $produk['kode_produk'],
            'stok_lama' => $stok_sistem,
            'stok_baru' => $stok_fisik,
            'selisih' => $selisih
        ];
    }
    
    // Commit transaction
    $pdo->commit();
    
    // Log activity
    $total_products = count($updated_products);
    error_log("Stock opname completed by user {$user['id']} ({$user['nama_lengkap']}) for {$total_products} products at {$opname_date}");
    
    echo json_encode([
        'success' => true,
        'message' => "Stock opname berhasil disimpan untuk {$total_products} produk",
        'data' => [
            'total_products' => $total_products,
            'opname_date' => $opname_date,
            'updated_products' => $updated_products
        ]
    ]);
    
} catch (Exception $e) {
    // Rollback transaction if it was started
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollback();
    }
    
    error_log("Stock opname error: " . $e->getMessage());
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>