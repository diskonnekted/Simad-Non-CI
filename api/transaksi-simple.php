<?php
require_once '../config/database.php';
require_once '../config/auth.php';

header('Content-Type: application/json');

// Simulasi login untuk testing
session_start();
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
    $_SESSION['role'] = 'admin';
    $_SESSION['nama_lengkap'] = 'Test User';
}

try {
    // Parameter DataTables
    $draw = intval($_POST['draw'] ?? 1);
    $start = intval($_POST['start'] ?? 0);
    $length = intval($_POST['length'] ?? 10);
    
    // Query sederhana
    $query = "SELECT 
        t.id,
        t.nomor_invoice,
        t.tanggal_transaksi,
        t.total_amount,
        t.status_transaksi,
        d.nama_desa,
        u.nama_lengkap as sales_name
    FROM transaksi t
    LEFT JOIN desa d ON t.desa_id = d.id
    LEFT JOIN users u ON t.user_id = u.id
    ORDER BY t.created_at DESC
    LIMIT $length OFFSET $start";
    
    $transaksi = $db->select($query);
    
    // Count total
    $count_query = "SELECT COUNT(*) as total FROM transaksi";
    $total_records = $db->select($count_query)[0]['total'];
    
    // Format data
    $data = [];
    foreach ($transaksi as $t) {
        $data[] = [
            $t['nomor_invoice'],
            $t['tanggal_transaksi'],
            $t['nama_desa'] ?? '-',
            'Rp ' . number_format($t['total_amount'], 0, ',', '.'),
            '-', // Bank
            strtoupper($t['status_transaksi']),
            '-', // Status
            $t['sales_name'] ?? '-',
            '-', // Piutang
            '<button class="btn btn-sm btn-primary">View</button>' // Actions
        ];
    }
    
    $response = [
        'draw' => $draw,
        'recordsTotal' => $total_records,
        'recordsFiltered' => $total_records,
        'data' => $data
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => true,
        'message' => $e->getMessage()
    ]);
}
?>