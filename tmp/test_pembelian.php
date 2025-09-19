<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "Testing pembelian.php with exact conditions...\n";

try {
    // Simulate being logged in as admin
    session_start();
    $_SESSION['user_id'] = 1;
    $_SESSION['role'] = 'admin';
    
    require_once 'config/database.php';
    require_once 'config/auth.php';
    
    $db = getDatabase();
    
    // Simulate GET parameters (empty like when first loading page)
    $search = '';
    $vendor_filter = '';
    $status_filter = '';
    $payment_filter = '';
    $date_from = '';
    $date_to = '';
    $sort_by = 'tanggal_pembelian';
    $sort_order = 'DESC';
    
    // Pagination
    $page = 1;
    $limit = 20;
    $offset = 0;
    
    // Build query conditions (exactly like pembelian.php)
    $conditions = [];
    $params = [];
    
    if (!empty($search)) {
        $conditions[] = "(p.nomor_po LIKE ? OR v.nama_vendor LIKE ? OR p.catatan LIKE ?)";
        $search_param = "%{$search}%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    if (!empty($vendor_filter)) {
        $conditions[] = "p.vendor_id = ?";
        $params[] = $vendor_filter;
    }
    
    if (!empty($status_filter)) {
        $conditions[] = "p.status_pembelian = ?";
        $params[] = $status_filter;
    }
    
    if (!empty($payment_filter)) {
        $conditions[] = "p.status_pembayaran = ?";
        $params[] = $payment_filter;
    }
    
    if (!empty($date_from)) {
        $conditions[] = "p.tanggal_pembelian >= ?";
        $params[] = $date_from;
    }
    
    if (!empty($date_to)) {
        $conditions[] = "p.tanggal_pembelian <= ?";
        $params[] = $date_to;
    }
    
    $where_clause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';
    
    echo "Where clause: '$where_clause'\n";
    echo "Params: " . json_encode($params) . "\n";
    
    // Query utama untuk mengambil data pembelian (exactly like pembelian.php)
    $query = "
        SELECT 
            p.*,
            v.nama_vendor,
            v.kode_vendor,
            u.nama_lengkap as user_name,
            COALESCE(h.jumlah_hutang, 0) as total_hutang
        FROM pembelian p
        JOIN vendor v ON p.vendor_id = v.id
        JOIN users u ON p.user_id = u.id
        LEFT JOIN hutang h ON p.id = h.pembelian_id AND h.status != 'lunas'
        {$where_clause}
        ORDER BY {$sort_by} {$sort_order}
        LIMIT {$limit} OFFSET {$offset}
    ";
    
    echo "\nFull query:\n$query\n\n";
    
    $pembelian_list = $db->select($query, $params);
    echo "Query executed successfully\n";
    echo "Found " . count($pembelian_list) . " records\n";
    
    if (empty($pembelian_list)) {
        echo "\nNo records found! This explains why the page shows 'Belum ada data pembelian'\n";
    } else {
        echo "\nRecords found:\n";
        foreach($pembelian_list as $item) {
            echo "- PO: " . $item['nomor_po'] . ", Vendor: " . $item['nama_vendor'] . ", Total: " . $item['total_amount'] . "\n";
        }
    }
    
} catch(Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}

echo "\nTest completed.\n";
?>