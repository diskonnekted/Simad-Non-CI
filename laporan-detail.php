<?php
require_once 'config/database.php';
require_once 'config/auth.php';

// Cek autentikasi dan otorisasi
if (!AuthStatic::isLoggedIn()) {
    header('Location: login.php');
    exit;
}

if (!AuthStatic::hasRole(['admin', 'finance', 'sales'])) {
    header('Location: index.php?error=' . urlencode('Anda tidak memiliki akses ke halaman ini.'));
    exit;
}

$user = AuthStatic::getCurrentUser();
$db = getDatabase();
/** @var Database $db */

// Get parameters
$report_type = $_GET['type'] ?? 'transaksi';
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$page = intval($_GET['page'] ?? 1);
$limit = 50;
$offset = ($page - 1) * $limit;

// Validate date range
if (strtotime($start_date) > strtotime($end_date)) {
    $temp = $start_date;
    $start_date = $end_date;
    $end_date = $temp;
}

// Get data based on report type
$data = [];
$total_records = 0;
$title = '';
$headers = [];

switch ($report_type) {
    case 'transaksi':
        $count_query = "SELECT COUNT(*) as total FROM transaksi t WHERE DATE(t.created_at) BETWEEN ? AND ?";
        $total_result = $db->select($count_query, [$start_date, $end_date]);
        $total_records = $total_result[0]['total'] ?? 0;
        
        $data = $db->select("
            SELECT 
                t.id,
                t.invoice_number,
                t.created_at,
                d.nama_desa,
                u.nama as sales_nama,
                t.payment_method,
                t.total_amount,
                t.amount_paid,
                t.remaining_amount,
                t.due_date,
                t.status_transaksi as status
            FROM transaksi t
            LEFT JOIN desa d ON t.desa_id = d.id
            LEFT JOIN users u ON t.user_id = u.id
            WHERE DATE(t.created_at) BETWEEN ? AND ?
            ORDER BY t.created_at DESC
            LIMIT ? OFFSET ?
        ", [$start_date, $end_date, $limit, $offset]);
        
        $title = 'Laporan Detail Transaksi';
        $headers = [
            'No Invoice',
            'Tanggal',
            'Desa',
            'Sales',
            'Metode Bayar',
            'Total Amount',
            'Amount Paid',
            'Remaining',
            'Due Date',
            'Status',
            'Aksi'
        ];
        break;
        
    case 'piutang':
        $count_query = "SELECT COUNT(*) as total FROM transaksi t WHERE t.payment_method = 'tempo' AND t.remaining_amount > 0";
        $total_result = $db->select($count_query);
        $total_records = $total_result[0]['total'] ?? 0;
        
        $data = $db->select("
            SELECT 
                t.id,
                t.invoice_number,
                t.created_at,
                d.nama_desa,
                u.nama as sales_nama,
                t.total_amount,
                t.amount_paid,
                t.remaining_amount,
                t.due_date,
                CASE 
                    WHEN t.due_date < CURDATE() THEN 'overdue'
                    WHEN t.due_date = CURDATE() THEN 'due_today'
                    ELSE 'pending'
                END as status_piutang
            FROM transaksi t
            LEFT JOIN desa d ON t.desa_id = d.id
            LEFT JOIN users u ON t.user_id = u.id
            WHERE t.payment_method = 'tempo' AND t.remaining_amount > 0
            ORDER BY t.due_date ASC
            LIMIT ? OFFSET ?
        ", [$limit, $offset]);
        
        $title = 'Laporan Detail Piutang';
        $headers = [
            'No Invoice',
            'Tanggal Transaksi',
            'Desa',
            'Sales',
            'Total Amount',
            'Amount Paid',
            'Sisa Piutang',
            'Due Date',
            'Status',
            'Aksi'
        ];
        break;
        
    case 'sales':
        $count_query = "SELECT COUNT(*) as total FROM users WHERE role = 'sales' AND status = 'aktif'";
        $total_result = $db->select($count_query);
        $total_records = $total_result[0]['total'] ?? 0;
        
        $data = $db->select("
            SELECT 
                u.id,
                u.nama as sales_nama,
                u.telepon,
                COUNT(t.id) as total_transaksi,
                SUM(t.total_amount) as total_penjualan,
                SUM(t.amount_paid) as total_terbayar,
                SUM(t.remaining_amount) as total_piutang,
                AVG(t.total_amount) as rata_rata_transaksi
            FROM users u
            LEFT JOIN transaksi t ON u.id = t.user_id AND DATE(t.created_at) BETWEEN ? AND ?
            WHERE u.role = 'sales' AND u.status = 'aktif'
            GROUP BY u.id, u.nama, u.telepon
            ORDER BY total_penjualan DESC
            LIMIT ? OFFSET ?
        ", [$start_date, $end_date, $limit, $offset]);
        
        $title = 'Laporan Kinerja Sales';
        $headers = [
            'Nama Sales',
            'Telepon',
            'Total Transaksi',
            'Total Penjualan',
            'Total Terbayar',
            'Total Piutang',
            'Rata-rata per Transaksi'
        ];
        break;
        
    case 'produk':
        $count_query = "SELECT COUNT(*) as total FROM produk WHERE status = 'aktif'";
        $total_result = $db->select($count_query);
        $total_records = $total_result[0]['total'] ?? 0;
        
        $data = $db->select("
            SELECT 
                p.id,
                p.kode_produk,
                p.nama_produk,
                k.nama_kategori,
                p.harga,
                p.stok_current,
                COALESCE(SUM(td.quantity), 0) as total_terjual,
                COALESCE(SUM(td.subtotal), 0) as total_pendapatan,
                COALESCE(AVG(td.price), p.harga) as harga_rata_rata
            FROM produk p
            LEFT JOIN kategori k ON p.kategori_id = k.id
            LEFT JOIN transaksi_detail td ON p.id = td.item_id AND td.item_type = 'produk'
            LEFT JOIN transaksi t ON td.transaksi_id = t.id AND DATE(t.created_at) BETWEEN ? AND ?
            WHERE p.status = 'aktif'
            GROUP BY p.id, p.kode_produk, p.nama_produk, k.nama_kategori, p.harga, p.stok_current
            ORDER BY total_terjual DESC
            LIMIT ? OFFSET ?
        ", [$start_date, $end_date, $limit, $offset]);
        
        $title = 'Laporan Produk';
        $headers = [
            'Kode Produk',
            'Nama Produk',
            'Kategori',
            'Harga',
            'Stok',
            'Total Terjual',
            'Total Pendapatan',
            'Harga Rata-rata',
            'Aksi'
        ];
        break;
        
    case 'layanan':
        $count_query = "SELECT COUNT(*) as total FROM layanan WHERE status = 'aktif'";
        $total_result = $db->select($count_query);
        $total_records = $total_result[0]['total'] ?? 0;
        
        $data = $db->select("
            SELECT 
                l.id,
                l.kode_layanan,
                l.nama_layanan,
                k.nama_kategori,
                l.harga,
                l.durasi_estimasi,
                COALESCE(SUM(td.quantity), 0) as total_terjual,
                COALESCE(SUM(td.subtotal), 0) as total_pendapatan,
                COALESCE(AVG(td.price), l.harga) as harga_rata_rata
            FROM layanan l
            LEFT JOIN kategori k ON l.kategori_id = k.id
            LEFT JOIN transaksi_detail td ON l.id = td.item_id AND td.item_type = 'layanan'
            LEFT JOIN transaksi t ON td.transaksi_id = t.id AND DATE(t.created_at) BETWEEN ? AND ?
            WHERE l.status = 'aktif'
            GROUP BY l.id, l.kode_layanan, l.nama_layanan, k.nama_kategori, l.harga, l.durasi_estimasi
            ORDER BY total_terjual DESC
            LIMIT ? OFFSET ?
        ", [$start_date, $end_date, $limit, $offset]);
        
        $title = 'Laporan Layanan';
        $headers = [
            'Kode Layanan',
            'Nama Layanan',
            'Kategori',
            'Harga',
            'Durasi (menit)',
            'Total Terjual',
            'Total Pendapatan',
            'Harga Rata-rata',
            'Aksi'
        ];
        break;
}

// Calculate pagination
$total_pages = ceil($total_records / $limit);

// Format currency function
function formatCurrency($amount) {
    return 'Rp ' . number_format($amount ?? 0, 0, ',', '.');
}

// Format number function
function formatNumber($number) {
    return number_format($number ?? 0, 0, ',', '.');
}

// Format date function
function formatDate($date) {
    return $date ? date('d/m/Y', strtotime($date)) : '-';
}

// Format datetime function
function formatDateTime($datetime) {
    return $datetime ? date('d/m/Y H:i', strtotime($datetime)) : '-';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $title ?> - Sistem Manajemen Desa</title>
    <link href="css/root.css" rel="stylesheet">
    <style>
        .filter-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .report-section {
            background: white;
            padding: 25px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .report-section h4 {
            margin-top: 0;
            color: #333;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }
        .table-responsive {
            overflow-x: auto;
        }
        .status-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .status-aktif { background: #d4edda; color: #155724; }
        .status-selesai { background: #d4edda; color: #155724; }
        .status-draft { background: #d1ecf1; color: #0c5460; }
        .status-overdue { background: #f8d7da; color: #721c24; }
        .status-due_today { background: #fff3cd; color: #856404; }
        .export-buttons {
            text-align: right;
            margin-bottom: 15px;
        }
        .pagination-info {
            margin-top: 15px;
            color: #666;
        }
        .btn-group {
            margin-bottom: 15px;
        }
        .btn-group .btn {
            margin-right: 5px;
        }
        .report-tabs {
            margin-bottom: 20px;
        }
        .report-tabs .nav-tabs {
            border-bottom: 2px solid #dee2e6;
        }
        .report-tabs .nav-tabs .nav-link {
            border: none;
            color: #666;
            font-weight: 500;
        }
        .report-tabs .nav-tabs .nav-link.active {
            color: #007bff;
            border-bottom: 2px solid #007bff;
            background: none;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="navbar">
        <div class="navbar-inner">
            <div class="navbar-container">
                <div class="navbar-header pull-left">
                    <a href="#" class="navbar-brand">
                        <small><img src="img/kode-icon.png" alt=""> Sistem Manajemen Desa</small>
                    </a>
                </div>
                <div class="navbar-header pull-right">
                    <ul class="nav navbar-nav pull-right">
                        <li class="dropdown">
                            <a href="#" class="dropdown-toggle" data-toggle="dropdown">
                                <i class="fa fa-user"></i> <?= htmlspecialchars($user['nama']) ?>
                                <span class="label label-<?= $user['role'] === 'admin' ? 'danger' : 'success' ?>">
                                    <?= strtoupper($user['role']) ?>
                                </span>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-right">
                                <li><a href="profile.php"><i class="fa fa-user"></i> Profil</a></li>
                                <li><a href="logout.php"><i class="fa fa-sign-out"></i> Logout</a></li>
                            </ul>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Container -->
    <div id="main" class="main">
        <!-- Sidebar -->
        <div id="sidebar-left" class="sidebar">
            <div class="sidebar-scroll">
                <div class="sidebar-content">
                    <ul class="sidebar-menu">
                        <li><a href="index.php"><i class="fa fa-dashboard"></i><span>Dashboard</span></a></li>
                        <li class="submenu">
                            <a href="#"><i class="fa fa-map-marker"></i><span>Manajemen Desa</span></a>
                            <ul>
                                <li><a href="desa.php">Daftar Desa</a></li>
                                <li><a href="desa-add.php">Tambah Desa</a></li>
                            </ul>
                        </li>
                        <li class="submenu">
                            <a href="#"><i class="fa fa-cube"></i><span>Produk & Layanan</span></a>
                            <ul>
                                <li><a href="produk.php">Barang IT & ATK</a></li>
                                <li><a href="layanan.php">Layanan</a></li>
                                <li><a href="kategori.php">Kategori</a></li>
                            </ul>
                        </li>
                        <li class="submenu">
                            <a href="#"><i class="fa fa-shopping-cart"></i><span>Transaksi</span></a>
                            <ul>
                                <li><a href="transaksi-add.php">Buat Transaksi</a></li>
                                <li><a href="transaksi.php">Daftar Transaksi</a></li>
                            </ul>
                        </li>
                        <li class="active submenu">
                            <a href="#"><i class="fa fa-money"></i><span>Keuangan</span></a>
                            <ul>
                                <li><a href="piutang.php">Monitoring Piutang</a></li>
                                <li><a href="laporan.php">Laporan Keuangan</a></li>
                            </ul>
                        </li>
                        <li class="submenu">
                            <a href="#"><i class="fa fa-calendar"></i><span>Jadwal & Kunjungan</span></a>
                            <ul>
                                <li><a href="jadwal.php">Jadwal Kunjungan</a></li>
                                <li><a href="jadwal-add.php">Buat Jadwal</a></li>
                            </ul>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Content -->
        <div id="content">
            <div class="content-header">
                <div class="header-section">
                    <h1>
                        <i class="fa fa-file-text"></i> <?= $title ?>
                        <small>Detail laporan dengan filter dan export</small>
                    </h1>
                    <ol class="breadcrumb">
                        <li><a href="index.php">Dashboard</a></li>
                        <li><a href="#">Keuangan</a></li>
                        <li><a href="laporan.php">Laporan</a></li>
                        <li class="active">Detail</li>
                    </ol>
                </div>
            </div>

            <div class="content-body">
                <!-- Report Tabs -->
                <div class="report-tabs">
                    <ul class="nav nav-tabs">
                        <li class="<?= $report_type === 'transaksi' ? 'active' : '' ?>">
                            <a href="?type=transaksi&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>" class="nav-link <?= $report_type === 'transaksi' ? 'active' : '' ?>">
                                <i class="fa fa-shopping-cart"></i> Transaksi
                            </a>
                        </li>
                        <li class="<?= $report_type === 'piutang' ? 'active' : '' ?>">
                            <a href="?type=piutang&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>" class="nav-link <?= $report_type === 'piutang' ? 'active' : '' ?>">
                                <i class="fa fa-money"></i> Piutang
                            </a>
                        </li>
                        <li class="<?= $report_type === 'sales' ? 'active' : '' ?>">
                            <a href="?type=sales&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>" class="nav-link <?= $report_type === 'sales' ? 'active' : '' ?>">
                                <i class="fa fa-users"></i> Sales
                            </a>
                        </li>
                        <li class="<?= $report_type === 'produk' ? 'active' : '' ?>">
                            <a href="?type=produk&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>" class="nav-link <?= $report_type === 'produk' ? 'active' : '' ?>">
                                <i class="fa fa-cube"></i> Produk
                            </a>
                        </li>
                        <li class="<?= $report_type === 'layanan' ? 'active' : '' ?>">
                            <a href="?type=layanan&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>" class="nav-link <?= $report_type === 'layanan' ? 'active' : '' ?>">
                                <i class="fa fa-cogs"></i> Layanan
                            </a>
                        </li>
                    </ul>
                </div>

                <!-- Filter Section -->
                <div class="filter-section">
                    <form method="GET" class="form-inline">
                        <input type="hidden" name="type" value="<?= htmlspecialchars($report_type) ?>">
                        <div class="form-group">
                            <label for="start_date">Dari Tanggal:</label>
                            <input type="date" name="start_date" id="start_date" class="form-control" 
                                   value="<?= htmlspecialchars($start_date) ?>">
                        </div>
                        <div class="form-group">
                            <label for="end_date">Sampai Tanggal:</label>
                            <input type="date" name="end_date" id="end_date" class="form-control" 
                                   value="<?= htmlspecialchars($end_date) ?>">
                        </div>
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">
                                <i class="fa fa-filter"></i> Filter
                            </button>
                        </div>
                    </form>
                    <small class="text-muted">
                        Periode: <?= formatDate($start_date) ?> - <?= formatDate($end_date) ?> | 
                        Total Records: <?= formatNumber($total_records) ?>
                    </small>
                </div>

                <!-- Export Buttons -->
                <div class="export-buttons">
                    <a href="laporan-export.php?type=<?= $report_type ?>&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>" 
                       class="btn btn-success" target="_blank">
                        <i class="fa fa-download"></i> Export Excel
                    </a>
                    <a href="laporan.php?start_date=<?= $start_date ?>&end_date=<?= $end_date ?>" class="btn btn-default">
                        <i class="fa fa-arrow-left"></i> Kembali ke Ringkasan
                    </a>
                </div>

                <!-- Data Table -->
                <div class="report-section">
                    <h4><i class="fa fa-table"></i> Data <?= $title ?></h4>
                    
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <?php foreach ($headers as $header): ?>
                                    <th><?= htmlspecialchars($header) ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($data)): ?>
                                <tr>
                                    <td colspan="<?= count($headers) ?>" class="text-center">
                                        <em>Tidak ada data untuk periode yang dipilih</em>
                                    </td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($data as $row): ?>
                                    <tr>
                                        <?php if ($report_type === 'transaksi'): ?>
                                            <td><?= htmlspecialchars($row['invoice_number']) ?></td>
                                            <td><?= formatDateTime($row['created_at']) ?></td>
                                            <td><?= htmlspecialchars($row['nama_desa']) ?></td>
                                            <td><?= htmlspecialchars($row['sales_nama']) ?></td>
                                            <td><?= ucfirst($row['payment_method']) ?></td>
                                            <td><?= formatCurrency($row['total_amount']) ?></td>
                                            <td><?= formatCurrency($row['amount_paid']) ?></td>
                                            <td><?= formatCurrency($row['remaining_amount']) ?></td>
                                            <td><?= $row['due_date'] ? formatDate($row['due_date']) : '-' ?></td>
                                            <td>
                                                <span class="status-badge status-<?= $row['status'] ?>">
                                                    <?= ucfirst($row['status']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="transaksi-view.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-info">
                                                    <i class="fa fa-eye"></i>
                                                </a>
                                            </td>
                                        <?php elseif ($report_type === 'piutang'): ?>
                                            <td><?= htmlspecialchars($row['invoice_number']) ?></td>
                                            <td><?= formatDateTime($row['created_at']) ?></td>
                                            <td><?= htmlspecialchars($row['nama_desa']) ?></td>
                                            <td><?= htmlspecialchars($row['sales_nama']) ?></td>
                                            <td><?= formatCurrency($row['total_amount']) ?></td>
                                            <td><?= formatCurrency($row['amount_paid']) ?></td>
                                            <td><?= formatCurrency($row['remaining_amount']) ?></td>
                                            <td><?= formatDate($row['due_date']) ?></td>
                                            <td>
                                                <span class="status-badge status-<?= $row['status_piutang'] ?>">
                                                    <?php
                                                    $status_labels = [
                                                        'overdue' => 'Terlambat',
                                                        'due_today' => 'Jatuh Tempo Hari Ini',
                                                        'pending' => 'Belum Jatuh Tempo'
                                                    ];
                                                    echo $status_labels[$row['status_piutang']] ?? $row['status_piutang'];
                                                    ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="transaksi-view.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-info">
                                                    <i class="fa fa-eye"></i>
                                                </a>
                                            </td>
                                        <?php elseif ($report_type === 'sales'): ?>
                                            <td><?= htmlspecialchars($row['sales_nama']) ?></td>
                                            <td><?= htmlspecialchars($row['telepon']) ?></td>
                                            <td><?= formatNumber($row['total_transaksi']) ?></td>
                                            <td><?= formatCurrency($row['total_penjualan']) ?></td>
                                            <td><?= formatCurrency($row['total_terbayar']) ?></td>
                                            <td><?= formatCurrency($row['total_piutang']) ?></td>
                                            <td><?= formatCurrency($row['rata_rata_transaksi']) ?></td>
                                        <?php elseif ($report_type === 'produk'): ?>
                                            <td><?= htmlspecialchars($row['kode_produk']) ?></td>
                                            <td><?= htmlspecialchars($row['nama_produk']) ?></td>
                                            <td><?= htmlspecialchars($row['nama_kategori']) ?></td>
                                            <td><?= formatCurrency($row['harga']) ?></td>
                                            <td><?= formatNumber($row['stok_current']) ?></td>
                                            <td><?= formatNumber($row['total_terjual']) ?></td>
                                            <td><?= formatCurrency($row['total_pendapatan']) ?></td>
                                            <td><?= formatCurrency($row['harga_rata_rata']) ?></td>
                                            <td>
                                                <a href="produk-view.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-info">
                                                    <i class="fa fa-eye"></i>
                                                </a>
                                            </td>
                                        <?php elseif ($report_type === 'layanan'): ?>
                                            <td><?= htmlspecialchars($row['kode_layanan']) ?></td>
                                            <td><?= htmlspecialchars($row['nama_layanan']) ?></td>
                                            <td><?= htmlspecialchars($row['nama_kategori']) ?></td>
                                            <td><?= formatCurrency($row['harga']) ?></td>
                                            <td><?= formatNumber($row['durasi_estimasi']) ?></td>
                                            <td><?= formatNumber($row['total_terjual']) ?></td>
                                            <td><?= formatCurrency($row['total_pendapatan']) ?></td>
                                            <td><?= formatCurrency($row['harga_rata_rata']) ?></td>
                                            <td>
                                                <a href="layanan-view.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-info">
                                                    <i class="fa fa-eye"></i>
                                                </a>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                    <div class="pagination-wrapper">
                        <ul class="pagination">
                            <?php if ($page > 1): ?>
                            <li>
                                <a href="?type=<?= $report_type ?>&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>&page=<?= $page - 1 ?>">
                                    <i class="fa fa-chevron-left"></i>
                                </a>
                            </li>
                            <?php endif; ?>
                            
                            <?php
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);
                            
                            for ($i = $start_page; $i <= $end_page; $i++):
                            ?>
                            <li class="<?= $i === $page ? 'active' : '' ?>">
                                <a href="?type=<?= $report_type ?>&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>&page=<?= $i ?>">
                                    <?= $i ?>
                                </a>
                            </li>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                            <li>
                                <a href="?type=<?= $report_type ?>&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>&page=<?= $page + 1 ?>">
                                    <i class="fa fa-chevron-right"></i>
                                </a>
                            </li>
                            <?php endif; ?>
                        </ul>
                        
                        <div class="pagination-info">
                            Menampilkan <?= formatNumber(($page - 1) * $limit + 1) ?> - 
                            <?= formatNumber(min($page * $limit, $total_records)) ?> dari 
                            <?= formatNumber($total_records) ?> records
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script src="js/jquery.min.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script>
        // Auto-hide alerts
        setTimeout(function() {
            $('.alert').fadeOut();
        }, 5000);
    </script>
</body>
</html>
