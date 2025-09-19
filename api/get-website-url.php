<?php
require_once '../config/database.php';
require_once '../config/auth.php';

// Set header untuk JSON response
header('Content-Type: application/json');

// Cek autentikasi
if (!AuthStatic::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Cek method
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Ambil desa_id dari parameter
$desa_id = $_GET['desa_id'] ?? null;

if (!$desa_id || !is_numeric($desa_id)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid desa_id parameter']);
    exit;
}

try {
    // Initialize database using Database class
    $db = new Database();
    $pdo = $db->getConnection();
    
    // Query untuk mengambil website_url berdasarkan desa_id
    $stmt = $pdo->prepare("SELECT website_url FROM website_desa WHERE desa_id = ? LIMIT 1");
    $stmt->execute([$desa_id]);
    $website = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($website) {
        echo json_encode([
            'success' => true,
            'website_url' => $website['website_url']
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Website tidak ditemukan untuk desa ini'
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
?>