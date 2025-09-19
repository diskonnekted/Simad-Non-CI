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

$user = AuthStatic::getCurrentUser();
$db = getDatabase();
/** @var Database $db */

// Fungsi helper untuk format tanggal Indonesia
function formatTanggalIndonesia($tanggal) {
    if (!$tanggal) return '-';
    
    $bulan = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
    ];
    
    $timestamp = strtotime($tanggal);
    $hari = date('d', $timestamp);
    $bulan_num = (int)date('m', $timestamp);
    $tahun = date('Y', $timestamp);
    
    return $hari . ' ' . $bulan[$bulan_num] . ' ' . $tahun;
}

// Fungsi helper untuk format rupiah
function formatRupiah($angka) {
    return 'Rp ' . number_format($angka, 0, ',', '.');
}

// Parameter DataTables
$draw = intval($_POST['draw'] ?? 1);
$start = intval($_POST['start'] ?? 0);
$length = intval($_POST['length'] ?? 10);
$search_value = $_POST['search']['value'] ?? '';
$order_column = intval($_POST['order'][0]['column'] ?? 0);
$order_dir = $_POST['order'][0]['dir'] ?? 'desc';

// Kolom yang bisa diurutkan
$columns = [
    'nomor_invoice',
    'tanggal_transaksi', 
    'nama_desa',
    'total_amount',
    'nama_bank',
    'metode_pembayaran',
    'status_transaksi',
    'sales_name',
    'total_piutang'
];

$order_column_name = $columns[$order_column] ?? 'created_at';
if ($order_column_name === 'sales_name') {
    $order_column_name = 'u.nama_lengkap';
} elseif ($order_column_name === 'nama_desa') {
    $order_column_name = 'd.nama_desa';
} elseif ($order_column_name === 'nama_bank') {
    $order_column_name = 'b.nama_bank';
} else {
    $order_column_name = 't.' . $order_column_name;
}

// Build query conditions
$conditions = ["1=1"];
$params = [];

// Filter berdasarkan role
if ($user['role'] === 'sales') {
    $conditions[] = "t.user_id = ?";
    $params[] = $user['id'];
}

// Filter dari form
$status_filter = $_POST['status_filter'] ?? '';
$desa_filter = $_POST['desa_filter'] ?? '';
$date_from = $_POST['date_from'] ?? '';
$date_to = $_POST['date_to'] ?? '';
$payment_type = $_POST['payment_type'] ?? '';

if ($status_filter) {
    $conditions[] = "t.status_transaksi = ?";
    $params[] = $status_filter;
}

if ($desa_filter) {
    $conditions[] = "t.desa_id = ?";
    $params[] = $desa_filter;
}

if ($date_from) {
    $conditions[] = "DATE(t.created_at) >= ?";
    $params[] = $date_from;
}

if ($date_to) {
    $conditions[] = "DATE(t.created_at) <= ?";
    $params[] = $date_to;
}

if ($payment_type) {
    $conditions[] = "t.metode_pembayaran = ?";
    $params[] = $payment_type;
}

// Search global
if ($search_value) {
    $conditions[] = "(d.nama_desa LIKE ? OR t.nomor_invoice LIKE ? OR t.catatan LIKE ? OR u.nama_lengkap LIKE ?)";
    $search_param = "%$search_value%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_clause = implode(' AND ', $conditions);

// Query untuk menghitung total records
$count_query = "
    SELECT COUNT(*) as total
    FROM transaksi t
    LEFT JOIN desa d ON t.desa_id = d.id
    LEFT JOIN users u ON t.user_id = u.id
    LEFT JOIN bank b ON t.bank_id = b.id
    WHERE $where_clause
";
$total_records = $db->select($count_query, $params)[0]['total'];

// Query utama dengan pagination
$query = "
    SELECT 
        t.id,
        t.nomor_invoice,
        t.desa_id,
        t.user_id,
        t.tanggal_transaksi,
        t.jenis_transaksi,
        t.metode_pembayaran,
        t.bank_id,
        t.dp_amount,
        t.tanggal_jatuh_tempo,
        t.total_amount,
        t.catatan,
        t.status_transaksi,
        t.created_at,
        t.updated_at,
        d.nama_desa,
        d.kecamatan,
        d.kabupaten,
        u.nama_lengkap as sales_name,
        b.nama_bank,
        b.kode_bank,
        b.jenis_bank,
        COALESCE(SUM(p.jumlah_piutang), 0) as total_piutang
    FROM transaksi t
    LEFT JOIN desa d ON t.desa_id = d.id
    LEFT JOIN users u ON t.user_id = u.id
    LEFT JOIN bank b ON t.bank_id = b.id
    LEFT JOIN piutang p ON t.id = p.transaksi_id AND p.status = 'aktif'
    WHERE $where_clause
    GROUP BY t.id
    ORDER BY $order_column_name $order_dir
    LIMIT $length OFFSET $start
";

$transaksi = $db->select($query, $params);

// Format data untuk DataTables
$data = [];
foreach ($transaksi as $t) {
    // Format status badge
    $status_colors = [
        'draft' => 'bg-gray-100 text-gray-800',
        'diproses' => 'bg-blue-100 text-blue-800',
        'dikirim' => 'bg-yellow-100 text-yellow-800',
        'selesai' => 'bg-green-100 text-green-800'
    ];
    $status_color = $status_colors[$t['status_transaksi']] ?? 'bg-gray-100 text-gray-800';
    $status_badge = '<span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full ' . $status_color . '">' . strtoupper($t['status_transaksi']) . '</span>';
    
    // Format payment badge
    $payment_colors = [
        'tunai' => 'bg-green-100 text-green-800',
        'dp' => 'bg-yellow-100 text-yellow-800',
        'tempo' => 'bg-red-100 text-red-800'
    ];
    $payment_color = $payment_colors[$t['metode_pembayaran']] ?? 'bg-gray-100 text-gray-800';
    $payment_badge = '<span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full ' . $payment_color . '">' . strtoupper($t['metode_pembayaran']) . '</span>';
    if ($t['metode_pembayaran'] === 'dp') {
        $payment_badge .= '<div class="text-xs text-gray-500 mt-1">DP: ' . formatRupiah($t['dp_amount']) . '</div>';
    }
    
    // Format bank info
    $bank_info = '-';
    if ($t['nama_bank']) {
        $bank_badge_color = 'bg-purple-100 text-purple-800';
        if ($t['jenis_bank'] === 'cash') {
            $bank_badge_color = 'bg-green-100 text-green-800';
        } elseif ($t['jenis_bank'] === 'bkk') {
            $bank_badge_color = 'bg-blue-100 text-blue-800';
        }
        $bank_info = '<div class="font-medium text-gray-900">' . htmlspecialchars($t['nama_bank']) . '</div>';
        $bank_info .= '<span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full ' . $bank_badge_color . '">' . strtoupper($t['jenis_bank']) . '</span>';
    }
    
    // Format piutang
    $piutang_display = $t['total_piutang'] > 0 ? 
        '<span class="text-red-600 font-medium">' . formatRupiah($t['total_piutang']) . '</span>' : 
        '<span class="text-gray-400">-</span>';
    
    // Format actions
    $actions = '<div class="flex justify-start space-x-2">';
    $actions .= '<a href="transaksi-view.php?id=' . $t['id'] . '" class="bg-blue-500 hover:bg-blue-600 text-white p-2 rounded-lg" title="Lihat Detail"><i class="fa fa-eye"></i></a>';
    
    if (AuthStatic::hasRole(['admin', 'sales']) && $t['status_transaksi'] === 'draft') {
        $actions .= '<a href="transaksi-edit.php?id=' . $t['id'] . '" class="bg-yellow-500 hover:bg-yellow-600 text-white p-2 rounded-lg" title="Edit"><i class="fa fa-edit"></i></a>';
    }
    
    if ($t['total_piutang'] > 0 && AuthStatic::hasRole(['admin', 'finance'])) {
        $actions .= '<a href="pembayaran-add.php?transaksi_id=' . $t['id'] . '" class="bg-green-500 hover:bg-green-600 text-white p-2 rounded-lg" title="Bayar Piutang"><i class="fa fa-money"></i></a>';
    }
    
    if (AuthStatic::hasRole(['admin']) && $t['status_transaksi'] === 'draft') {
        $actions .= '<button onclick="showDeleteModal(' . $t['id'] . ', \'' . htmlspecialchars($t['nomor_invoice']) . '\')" class="bg-red-500 hover:bg-red-600 text-white p-2 rounded-lg" title="Hapus Transaksi"><i class="fa fa-trash"></i></button>';
    }
    
    $actions .= '</div>';
    
    $data[] = [
        '<div class="font-medium text-gray-900">' . htmlspecialchars($t['nomor_invoice']) . '</div>' . 
        ($t['catatan'] ? '<div class="text-sm text-gray-500">' . htmlspecialchars(substr($t['catatan'], 0, 50)) . '...</div>' : ''),
        formatTanggalIndonesia($t['tanggal_transaksi']),
        '<div class="font-medium text-gray-900">' . htmlspecialchars($t['nama_desa']) . '</div>' .
        '<div class="text-sm text-gray-500">' . htmlspecialchars($t['kecamatan']) . ', ' . htmlspecialchars($t['kabupaten']) . '</div>',
        '<span class="font-medium text-gray-900">' . formatRupiah($t['total_amount']) . '</span>',
        $bank_info,
        $payment_badge,
        $status_badge,
        htmlspecialchars($t['sales_name']),
        $piutang_display,
        $actions
    ];
}

// Response untuk DataTables
$response = [
    'draw' => $draw,
    'recordsTotal' => $total_records,
    'recordsFiltered' => $total_records,
    'data' => $data
];

echo json_encode($response);
?>