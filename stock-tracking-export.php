<?php
require_once 'config/database.php';
require_once 'config/auth.php';

// Cek autentikasi
if (!AuthStatic::isLoggedIn()) {
    header('Location: login.php');
    exit;
}

if (!AuthStatic::hasRole(['admin', 'manager'])) {
    header('Location: index.php?error=' . urlencode('Anda tidak memiliki akses ke halaman ini.'));
    exit;
}

$user = AuthStatic::getCurrentUser();
$db = getDatabase();
/** @var Database $db */

// Filter parameters (same as stock-tracking.php)
$produk_filter = $_GET['produk_id'] ?? '';
$tanggal_dari = $_GET['tanggal_dari'] ?? date('Y-m-01');
$tanggal_sampai = $_GET['tanggal_sampai'] ?? date('Y-m-d');
$jenis_movement = $_GET['jenis_movement'] ?? '';
$search = $_GET['search'] ?? '';

// Build query conditions
$where_conditions = [];
$params = [];

if (!empty($produk_filter)) {
    $where_conditions[] = "sm.produk_id = ?";
    $params[] = $produk_filter;
}

if (!empty($tanggal_dari)) {
    $where_conditions[] = "DATE(sm.created_at) >= ?";
    $params[] = $tanggal_dari;
}

if (!empty($tanggal_sampai)) {
    $where_conditions[] = "DATE(sm.created_at) <= ?";
    $params[] = $tanggal_sampai;
}

if (!empty($jenis_movement)) {
    $where_conditions[] = "sm.jenis_movement = ?";
    $params[] = $jenis_movement;
}

if (!empty($search)) {
    $where_conditions[] = "(p.nama_produk LIKE ? OR sm.keterangan LIKE ? OR t.nomor_invoice LIKE ?)";
    $search_param = "%{$search}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Query stock movements untuk export
$movements_query = "
    SELECT 
        sm.created_at,
        p.nama_produk,
        sm.jenis_movement,
        sm.quantity,
        sm.stok_sebelum,
        sm.stok_sesudah,
        t.nomor_invoice,
        t.tanggal_transaksi,
        d.nama_desa,
        d.kecamatan,
        u.nama_lengkap as user_name,
        sm.keterangan
    FROM stock_movement sm
    JOIN produk p ON sm.produk_id = p.id
    LEFT JOIN transaksi t ON sm.transaksi_id = t.id
    LEFT JOIN desa d ON t.desa_id = d.id
    LEFT JOIN users u ON sm.user_id = u.id
    {$where_clause}
    ORDER BY sm.created_at DESC
";

$movements_data = $db->select($movements_query, $params);

// Set headers untuk download CSV
$filename = 'stock_movement_' . date('Y-m-d_H-i-s') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

// Output CSV
$output = fopen('php://output', 'w');

// Add BOM for UTF-8
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Header informasi
fputcsv($output, ['LAPORAN STOCK MOVEMENT']);
fputcsv($output, ['Tanggal Export:', date('d/m/Y H:i:s')]);
fputcsv($output, ['User:', $user['nama_lengkap']]);
fputcsv($output, ['Periode:', ($tanggal_dari ? date('d/m/Y', strtotime($tanggal_dari)) : 'Semua') . ' - ' . ($tanggal_sampai ? date('d/m/Y', strtotime($tanggal_sampai)) : 'Semua')]);
fputcsv($output, ['Filter Produk:', $produk_filter ? 'Ya' : 'Semua Produk']);
fputcsv($output, ['Filter Jenis:', $jenis_movement ? ucfirst($jenis_movement) : 'Semua Jenis']);
fputcsv($output, []);

// Header kolom
fputcsv($output, [
    'Tanggal & Waktu',
    'Nama Produk',
    'Jenis Movement',
    'Quantity',
    'Stok Sebelum',
    'Stok Sesudah',
    'No. Invoice',
    'Tanggal Transaksi',
    'Desa',
    'Kecamatan',
    'User',
    'Keterangan'
]);

// Data rows
foreach ($movements_data as $row) {
    fputcsv($output, [
        date('d/m/Y H:i:s', strtotime($row['created_at'])),
        $row['nama_produk'],
        ucfirst($row['jenis_movement']),
        number_format($row['quantity']),
        number_format($row['stok_sebelum']),
        number_format($row['stok_sesudah']),
        $row['nomor_invoice'] ?? '-',
        $row['tanggal_transaksi'] ? date('d/m/Y', strtotime($row['tanggal_transaksi'])) : '-',
        $row['nama_desa'] ?? '-',
        $row['kecamatan'] ?? '-',
        $row['user_name'] ?? '-',
        $row['keterangan']
    ]);
}

// Summary
fputcsv($output, []);
fputcsv($output, ['RINGKASAN']);

$total_masuk = 0;
$total_keluar = 0;
foreach ($movements_data as $row) {
    if ($row['jenis_movement'] === 'masuk') {
        $total_masuk += $row['quantity'];
    } else {
        $total_keluar += $row['quantity'];
    }
}

fputcsv($output, ['Total Movement:', count($movements_data)]);
fputcsv($output, ['Total Stok Masuk:', number_format($total_masuk)]);
fputcsv($output, ['Total Stok Keluar:', number_format($total_keluar)]);
fputcsv($output, ['Net Movement:', number_format($total_masuk - $total_keluar)]);

fclose($output);
exit;
?>