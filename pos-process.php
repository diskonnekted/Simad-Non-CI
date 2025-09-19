<?php
require_once 'config/database.php';
require_once 'config/auth.php';

// Set header untuk JSON response
header('Content-Type: application/json');

// Cek autentikasi
if (!AuthStatic::isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Handle GET request for generating invoice number
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'generate_invoice') {
    $db = getDatabase();
    
    // Generate nomor invoice baru
    function generateInvoiceNumber($db) {
        $today = date('Ymd');
        $prefix = 'INV' . $today;
        
        $last_invoice = $db->select("
            SELECT nomor_invoice 
            FROM transaksi 
            WHERE nomor_invoice LIKE ? 
            ORDER BY nomor_invoice DESC 
            LIMIT 1
        ", [$prefix . '%']);
        
        if (empty($last_invoice)) {
            return $prefix . '001';
        }
        
        $last_number = intval(substr($last_invoice[0]['nomor_invoice'], -3));
        $new_number = str_pad($last_number + 1, 3, '0', STR_PAD_LEFT);
        
        return $prefix . $new_number;
    }
    
    $new_invoice_number = generateInvoiceNumber($db);
    echo json_encode(['success' => true, 'invoice_number' => $new_invoice_number]);
    exit;
}

// Cek method POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Ambil data JSON
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON data']);
    exit;
}

$db = getDatabase();
$user = AuthStatic::getCurrentUser();

try {
    // Validasi data
    if (empty($data['desa_id']) || empty($data['payment_method']) || empty($data['items']) || $data['total_amount'] <= 0) {
        throw new Exception('Data transaksi tidak lengkap');
    }

    // Mulai transaksi database
    $db->beginTransaction();

    // Insert ke tabel transaksi
    $transaksi_data = [
        'nomor_invoice' => $data['invoice_number'],
        'desa_id' => $data['desa_id'],
        'user_id' => $user['id'],
        'tanggal_transaksi' => date('Y-m-d H:i:s'),
        'total_amount' => $data['total_amount'],
        'status_transaksi' => 'selesai',
        'metode_pembayaran' => $data['payment_method'],
        'bank_id' => $data['bank_id'],
        'catatan' => $data['notes'] ?? null,
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ];

    $transaksi_id = $db->insert('transaksi', $transaksi_data);

    if (!$transaksi_id) {
        throw new Exception('Gagal menyimpan data transaksi');
    }

    // Insert detail transaksi
    foreach ($data['items'] as $item) {
        $detail_data = [
            'transaksi_id' => $transaksi_id,
            'produk_id' => $item['type'] === 'produk' ? $item['id'] : null,
            'layanan_id' => $item['type'] === 'layanan' ? $item['id'] : null,
            'nama_item' => $item['name'],
            'harga_satuan' => $item['price'],
            'quantity' => $item['quantity'],
            'subtotal' => $item['subtotal']
        ];

        $detail_id = $db->insert('transaksi_detail', $detail_data);
        
        if (!$detail_id) {
            throw new Exception('Gagal menyimpan detail transaksi');
        }

        // Update stok produk jika item adalah produk
        if ($item['type'] === 'produk') {
            $current_stock = $db->select("SELECT stok_tersedia FROM produk WHERE id = ?", [$item['id']]);
            
            if (empty($current_stock)) {
                throw new Exception('Produk tidak ditemukan: ' . $item['name']);
            }

            $new_stock = $current_stock[0]['stok_tersedia'] - $item['quantity'];
            
            if ($new_stock < 0) {
                throw new Exception('Stok tidak mencukupi untuk produk: ' . $item['name']);
            }

            $update_stock = $db->update('produk', 
                ['stok_tersedia' => $new_stock, 'updated_at' => date('Y-m-d H:i:s')], 
                ['id' => $item['id']]
            );

            if (!$update_stock) {
                throw new Exception('Gagal update stok produk: ' . $item['name']);
            }
        }
    }

    // Handle pembayaran berdasarkan metode
    $payment_status = 'lunas';
    $remaining_amount = 0;

    if ($data['payment_method'] === 'dp_pelunasan') {
        $dp_amount = $data['dp_amount'];
        $remaining_amount = $data['total_amount'] - $dp_amount;
        $payment_status = 'dp';

        // Insert pembayaran DP
        $pembayaran_data = [
            'transaksi_id' => $transaksi_id,
            'tanggal_bayar' => date('Y-m-d'),
            'jumlah_bayar' => $dp_amount,
            'metode_bayar' => 'transfer',
            'bank_id' => $data['bank_id'] ?? null,
            'catatan' => 'Pembayaran DP',
            'user_id' => $_SESSION['user_id']
        ];

        $pembayaran_id = $db->insert('pembayaran', $pembayaran_data);

        if (!$pembayaran_id) {
            throw new Exception('Gagal menyimpan data pembayaran DP');
        }

        // Insert piutang untuk sisa pembayaran
        $piutang_data = [
            'transaksi_id' => $transaksi_id,
            'desa_id' => $data['desa_id'],
            'jumlah_piutang' => $remaining_amount,
            'sisa_piutang' => $remaining_amount,
            'tanggal_jatuh_tempo' => date('Y-m-d', strtotime('+30 days')), // Default 30 hari
            'status' => 'belum_lunas',
            'catatan' => 'Sisa pembayaran setelah DP',
            'created_at' => date('Y-m-d H:i:s')
        ];

        $piutang_id = $db->insert('piutang', $piutang_data);

        if (!$piutang_id) {
            throw new Exception('Gagal menyimpan data piutang');
        }

    } elseif ($data['payment_method'] === 'tempo') {
        $payment_status = 'tempo';
        $remaining_amount = $data['total_amount'];

        // Insert piutang
        $piutang_data = [
            'transaksi_id' => $transaksi_id,
            'desa_id' => $data['desa_id'],
            'jumlah_piutang' => $data['total_amount'],
            'sisa_piutang' => $data['total_amount'],
            'tanggal_jatuh_tempo' => $data['tempo_date'],
            'status' => 'belum_lunas',
            'catatan' => 'Pembayaran tempo',
            'created_at' => date('Y-m-d H:i:s')
        ];

        $piutang_id = $db->insert('piutang', $piutang_data);

        if (!$piutang_id) {
            throw new Exception('Gagal menyimpan data piutang');
        }

    } else { // tunai
        $payment_status = 'lunas';

        // Insert pembayaran tunai
        $pembayaran_data = [
            'transaksi_id' => $transaksi_id,
            'tanggal_bayar' => date('Y-m-d'),
            'jumlah_bayar' => $data['total_amount'],
            'metode_bayar' => 'tunai',
            'bank_id' => $data['bank_id'],
            'catatan' => 'Pembayaran tunai',
            'user_id' => $_SESSION['user_id']
        ];

        $pembayaran_id = $db->insert('pembayaran', $pembayaran_data);

        if (!$pembayaran_id) {
            throw new Exception('Gagal menyimpan data pembayaran');
        }
    }

    // Update status pembayaran di transaksi
    $update_transaksi = $db->update('transaksi', 
        [
            'status_pembayaran' => $payment_status,
            'sisa_amount' => $remaining_amount,
            'updated_at' => date('Y-m-d H:i:s')
        ], 
        ['id' => $transaksi_id]
    );

    if (!$update_transaksi) {
        throw new Exception('Gagal update status pembayaran transaksi');
    }

    // Catat mutasi kas masuk untuk penjualan POS
    if ($data['payment_method'] === 'tunai' || ($data['payment_method'] === 'dp_pelunasan' && $data['dp_amount'] > 0)) {
        $jumlah_masuk = $data['payment_method'] === 'tunai' ? $data['total_amount'] : $data['dp_amount'];
        
        $mutasi_data = [
            'bank_id' => $data['bank_id'],
            'jenis_mutasi' => 'masuk',
            'jenis_transaksi' => 'penjualan_pos',
            'referensi_id' => $transaksi_id,
            'referensi_tabel' => 'transaksi',
            'jumlah' => $jumlah_masuk,
            'keterangan' => $data['payment_method'] === 'tunai' 
                ? "Penjualan POS {$data['invoice_number']}"
                : "Penjualan POS {$data['invoice_number']} (DP: Rp " . number_format($data['dp_amount'], 0, ',', '.') . ")",
            'tanggal_mutasi' => date('Y-m-d'),
            'user_id' => $user['id'],
            'created_at' => date('Y-m-d H:i:s')
        ];

        $mutasi_insert = $db->insert('mutasi_kas', $mutasi_data);
        if (!$mutasi_insert) {
            throw new Exception('Gagal menyimpan mutasi kas');
        }
    }

    // Log aktivitas (sebelum commit)
    $log_data = [
        'user_id' => $user['id'],
        'activity_type' => 'Transaksi POS',
        'description' => "Transaksi baru #{$data['invoice_number']} - Total: Rp " . number_format($data['total_amount'], 0, ',', '.'),
        'target_table' => 'transaksi',
        'target_id' => $transaksi_id,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'created_at' => date('Y-m-d H:i:s')
    ];

    $log_insert = $db->insert('activity_logs', $log_data);
    if (!$log_insert) {
        throw new Exception('Gagal menyimpan log aktivitas');
    }

    // Commit transaksi
    $db->commit();

    // Response sukses
    echo json_encode([
        'success' => true,
        'message' => 'Transaksi berhasil disimpan',
        'transaction_id' => $transaksi_id,
        'invoice_number' => $data['invoice_number'],
        'payment_status' => $payment_status,
        'total_amount' => $data['total_amount'],
        'remaining_amount' => $remaining_amount
    ]);

} catch (Exception $e) {
    // Rollback jika ada error
    $db->rollback();
    
    // Log error
    error_log("POS Transaction Error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>