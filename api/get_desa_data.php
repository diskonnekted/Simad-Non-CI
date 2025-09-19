<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'get_kecamatan':
            // Ambil daftar kecamatan unik
            $stmt = $pdo->prepare("SELECT DISTINCT kecamatan FROM desa WHERE status = 'aktif' ORDER BY kecamatan ASC");
            $stmt->execute();
            $kecamatan = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            echo json_encode([
                'success' => true,
                'data' => $kecamatan
            ]);
            break;
            
        case 'get_desa':
            $kecamatan = $_GET['kecamatan'] ?? '';
            
            if (empty($kecamatan)) {
                // Ambil semua desa jika tidak ada kecamatan dipilih
                $stmt = $pdo->prepare("SELECT id, nama_desa, kecamatan FROM desa WHERE status = 'aktif' ORDER BY nama_desa ASC");
                $stmt->execute();
            } else {
                // Ambil desa berdasarkan kecamatan
                $stmt = $pdo->prepare("SELECT id, nama_desa, kecamatan FROM desa WHERE kecamatan = ? AND status = 'aktif' ORDER BY nama_desa ASC");
                $stmt->execute([$kecamatan]);
            }
            
            $desa = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'data' => $desa
            ]);
            break;
            
        case 'get_all':
            // Ambil semua data desa dan kecamatan
            $stmt = $pdo->prepare("SELECT id, nama_desa, kecamatan FROM desa WHERE status = 'aktif' ORDER BY kecamatan ASC, nama_desa ASC");
            $stmt->execute();
            $allDesa = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Ambil kecamatan unik
            $stmt = $pdo->prepare("SELECT DISTINCT kecamatan FROM desa WHERE status = 'aktif' ORDER BY kecamatan ASC");
            $stmt->execute();
            $kecamatan = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'desa' => $allDesa,
                    'kecamatan' => $kecamatan
                ]
            ]);
            break;
            
        default:
            echo json_encode([
                'success' => false,
                'message' => 'Action tidak valid'
            ]);
            break;
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Terjadi kesalahan: ' . $e->getMessage()
    ]);
}
?>