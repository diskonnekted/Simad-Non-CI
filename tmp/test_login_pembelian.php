<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "Testing login and pembelian.php with correct password...\n";

try {
    // Start session
    session_start();
    
    require_once 'config/database.php';
    require_once 'config/auth.php';
    
    $db = getDatabase();
    
    // Login as admin with correct password
    $username = 'admin';
    $password = 'password'; // Correct password
    
    echo "Attempting to login as: $username\n";
    
    // Check if user exists
    $user = $db->select("SELECT * FROM users WHERE username = ?", [$username]);
    
    if (empty($user)) {
        echo "User not found!\n";
        exit;
    }
    
    $user = $user[0];
    echo "User found: " . $user['nama_lengkap'] . " (Role: " . $user['role'] . ")\n";
    
    // Verify password
    if (password_verify($password, $user['password'])) {
        echo "Password verified successfully\n";
        
        // Set session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
        
        echo "Session set successfully\n";
        echo "Session data: " . json_encode($_SESSION) . "\n";
        
        // Now test if AuthStatic works
        echo "\nTesting AuthStatic...\n";
        echo "Is logged in: " . (AuthStatic::isLoggedIn() ? 'Yes' : 'No') . "\n";
        echo "Has admin role: " . (AuthStatic::hasRole(['admin']) ? 'Yes' : 'No') . "\n";
        
        if (AuthStatic::isLoggedIn() && AuthStatic::hasRole(['admin', 'akunting', 'supervisor'])) {
            echo "\nAccess granted! Now testing pembelian query...\n";
            
            // Test pembelian query exactly like in pembelian.php
            $search = '';
            $vendor_filter = '';
            $status_filter = '';
            $payment_filter = '';
            $date_from = '';
            $date_to = '';
            $sort_by = 'tanggal_pembelian';
            $sort_order = 'DESC';
            $page = 1;
            $limit = 20;
            $offset = 0;
            
            // Build query conditions
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
            
            // Query utama untuk mengambil data pembelian
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
            
            echo "Executing query...\n";
            $pembelian_list = $db->select($query, $params);
            echo "Found " . count($pembelian_list) . " records\n";
            
            if (!empty($pembelian_list)) {
                echo "\nFirst 5 records:\n";
                for ($i = 0; $i < min(5, count($pembelian_list)); $i++) {
                    $item = $pembelian_list[$i];
                    echo "- PO: " . $item['nomor_po'] . ", Vendor: " . $item['nama_vendor'] . ", Total: Rp " . number_format($item['total_amount'], 0, ',', '.') . "\n";
                }
                
                echo "\n=== CONCLUSION ===\n";
                echo "The query works perfectly and returns data!\n";
                echo "The issue must be in the web interface or session handling.\n";
                echo "Data should appear on the pembelian.php page after proper login.\n";
            } else {
                echo "\nNo records found - this explains the empty page!\n";
            }
        } else {
            echo "Access denied!\n";
        }
        
    } else {
        echo "Password verification failed!\n";
    }
    
} catch(Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}

echo "\nTest completed.\n";
?>