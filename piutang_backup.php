<?php
require_once 'config/database.php';
require_once 'config/auth.php';

// Pastikan user sudah login
if (!AuthStatic::isLoggedIn()) {
    header("Location: login.php");
    exit;
}


// Proses pembayaran piutang
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['bayar_piutang'])) {
    $piutang_id = $_POST['piutang_id'];
    $jumlah_bayar = $_POST['jumlah_bayar'];
    $tanggal_bayar = $_POST['tanggal_bayar'];
    $keterangan = $_POST['keterangan'] ?? '';
    
    try {
        $pdo->beginTransaction();
        
        // Ambil data piutang dengan join ke desa untuk mendapatkan nama desa
        $piutang_stmt = $pdo->prepare("
            SELECT p.*, d.nama_desa, t.nomor_invoice 
            FROM piutang p 
            LEFT JOIN desa d ON p.desa_id = d.id 
            LEFT JOIN transaksi t ON p.transaksi_id = t.id 
            WHERE p.id = ?
        ");
        $piutang_stmt->execute([$piutang_id]);
        $piutang = $piutang_stmt->fetch();
        
        if (!$piutang) {
            throw new Exception("Data piutang tidak ditemukan");
        }
        
        if ($jumlah_bayar <= 0) {
            throw new Exception("Jumlah pembayaran harus lebih dari 0");
        }
        
        if ($jumlah_bayar > $piutang['sisa_piutang']) {
            throw new Exception("Jumlah pembayaran tidak boleh melebihi sisa piutang (Rp " . number_format($piutang['sisa_piutang'] ?? 0, 0, ',', '.') . ")");
        }
        
        // Insert ke tabel pembayaran_piutang
        $insert_pembayaran = $pdo->prepare("
            INSERT INTO pembayaran_piutang (piutang_id, tanggal_bayar, jumlah_bayar, keterangan, created_at) 
            VALUES (?, ?, ?, ?, NOW())
        ");
        $insert_pembayaran->execute([$piutang_id, $tanggal_bayar, $jumlah_bayar, $keterangan]);
        
        // Update sisa piutang
        $sisa_baru = $piutang['sisa_piutang'] - $jumlah_bayar;
        $status_baru = $sisa_baru <= 0 ? 'lunas' : 'belum_lunas';
        
        $update_piutang = $pdo->prepare("
            UPDATE piutang 
            SET sisa_piutang = ?, status = ?, updated_at = NOW() 
            WHERE id = ?
        ");
        $update_piutang->execute([$sisa_baru, $status_baru, $piutang_id]);
        
        // Update status pembayaran di tabel transaksi jika piutang lunas
        if ($status_baru == 'lunas') {
            $update_transaksi = $pdo->prepare("
                UPDATE transaksi 
                SET status_pembayaran = 'lunas', updated_at = NOW() 
                WHERE id = ?
            ");
            $update_transaksi->execute([$piutang['transaksi_id']]);
        }
        
        $pdo->commit();
        $_SESSION['success'] = "Pembayaran piutang berhasil dicatat. Sisa piutang: Rp " . number_format($sisa_baru, 0, ',', '.');
        header("Location: piutang.php");
        exit;
        
    } catch (Exception $e) {
        $pdo->rollback();
        $_SESSION['error'] = "Error: " . $e->getMessage();
    }
}

// Ambil daftar desa untuk filter (menggunakan tabel desa, bukan customers)
$desa_sql = "SELECT DISTINCT d.id, d.nama_desa 
             FROM desa d 
             INNER JOIN piutang p ON d.id = p.desa_id 
             WHERE d.nama_desa IS NOT NULL AND d.nama_desa != '' 
             ORDER BY d.nama_desa";
$desa_stmt = $pdo->prepare($desa_sql);
$desa_stmt->execute();
$desa_list = $desa_stmt->fetchAll();

// Ambil data piutang dengan informasi desa
$filter_desa = $_GET['desa'] ?? '';
$filter_status = $_GET['status'] ?? '';

$where_conditions = [];
$params = [];

if (!empty($filter_desa)) {
    $where_conditions[] = "d.id = ?";
    $params[] = $filter_desa;
}

if (!empty($filter_status)) {
    $where_conditions[] = "p.status = ?";
    $params[] = $filter_status;
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Query untuk menghitung total records
$count_sql = "SELECT COUNT(*) as total 
              FROM piutang p 
              LEFT JOIN desa d ON p.desa_id = d.id 
              LEFT JOIN transaksi t ON p.transaksi_id = t.id 
              $where_clause";
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_records = $count_stmt->fetch()['total'];

// Pagination
$records_per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $records_per_page;
$total_pages = ceil($total_records / $records_per_page);

// Query utama untuk mengambil data piutang
$sql = "SELECT p.*, d.nama_desa, d.nama_kepala_desa, d.no_hp_kepala_desa, 
               t.nomor_invoice, t.tanggal_transaksi,
               (SELECT SUM(jumlah_bayar) FROM pembayaran_piutang WHERE piutang_id = p.id) as total_dibayar
        FROM piutang p 
        LEFT JOIN desa d ON p.desa_id = d.id 
        LEFT JOIN transaksi t ON p.transaksi_id = t.id 
        $where_clause
        ORDER BY p.tanggal_jatuh_tempo ASC, p.created_at DESC 
        LIMIT $records_per_page OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$piutang_list = $stmt->fetchAll();

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Piutang - SMD</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <?php include 'layouts/header.php'; ?>
    
    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-money-bill-wave"></i> Manajemen Piutang</h2>
                    <div>
                        <button class="btn btn-success" onclick="exportData()">
                            <i class="fas fa-download"></i> Export Excel
                        </button>
                    </div>
                </div>

                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <?= $_SESSION['success'] ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['success']); ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <?= $_SESSION['error'] ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['error']); ?>
                <?php endif; ?>

                <!-- Statistik Cards -->
                <div class="row mb-4">
                    <?php
                    $stats_sql = "SELECT 
                        COUNT(*) as total_piutang,
                        SUM(CASE WHEN status = 'lunas' THEN 1 ELSE 0 END) as lunas,
                        SUM(CASE WHEN status = 'belum_lunas' THEN 1 ELSE 0 END) as belum_lunas,
                        COALESCE(SUM(sisa_piutang), 0) as total_sisa_piutang
                        FROM piutang";
                    $stats_stmt = $pdo->query($stats_sql);
                    $stats = $stats_stmt->fetch();
                    ?>
                    <div class="col-md-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4><?= $stats['total_piutang'] ?></h4>
                                        <p class="mb-0">Total Piutang</p>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-file-invoice fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4><?= $stats['lunas'] ?></h4>
                                        <p class="mb-0">Lunas</p>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-check-circle fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-warning text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4><?= $stats['belum_lunas'] ?></h4>
                                        <p class="mb-0">Belum Lunas</p>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-clock fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-danger text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4>Rp <?= number_format($stats['total_sisa_piutang'] ?? 0, 0, ',', '.') ?></h4>
                                        <p class="mb-0">Total Sisa Piutang</p>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-money-bill-wave fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filter Section -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Filter Desa</label>
                                <select name="desa" class="form-select">
                                    <option value="">Semua Desa</option>
                                    <?php foreach ($desa_list as $desa): ?>
                                        <option value="<?= $desa['id'] ?>" <?= $filter_desa == $desa['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($desa['nama_desa']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Filter Status</label>
                                <select name="status" class="form-select">
                                    <option value="">Semua Status</option>
                                    <option value="belum_lunas" <?= $filter_status == 'belum_lunas' ? 'selected' : '' ?>>Belum Lunas</option>
                                    <option value="lunas" <?= $filter_status == 'lunas' ? 'selected' : '' ?>>Lunas</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">&nbsp;</label>
                                <div>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search"></i> Filter
                                    </button>
                                    <a href="piutang.php" class="btn btn-secondary">
                                        <i class="fas fa-refresh"></i> Reset
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Tabel Data Piutang -->
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>No</th>
                                        <th>Invoice</th>
                                        <th>Desa</th>
                                        <th>Kepala Desa</th>
                                        <th>Jumlah Piutang</th>
                                        <th>Sisa Piutang</th>
                                        <th>Jatuh Tempo</th>
                                        <th>Status</th>
                                        <th>Kontak</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($piutang_list)): ?>
                                        <tr>
                                            <td colspan="10" class="text-center">Tidak ada data piutang</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($piutang_list as $index => $piutang): ?>
                                            <tr>
                                                <td><?= $offset + $index + 1 ?></td>
                                                <td><?= htmlspecialchars($piutang['nomor_invoice'] ?? '-') ?></td>
                                                <td><?= htmlspecialchars($piutang['nama_desa'] ?? '-') ?></td>
                                                <td><?= htmlspecialchars($piutang['nama_kepala_desa'] ?? '-') ?></td>
                                                <td>Rp <?= number_format($piutang['jumlah_piutang'] ?? 0, 0, ',', '.') ?></td>
                                                <td>
                                                    <span class="badge <?= $piutang['sisa_piutang'] <= 0 ? 'bg-success' : 'bg-warning' ?>">
                                                        Rp <?= number_format($piutang['sisa_piutang'] ?? 0, 0, ',', '.') ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php
                                                    $jatuh_tempo = new DateTime($piutang['tanggal_jatuh_tempo']);
                                                    $today = new DateTime();
                                                    $diff = $today->diff($jatuh_tempo);
                                                    $class = '';
                                                    if ($today > $jatuh_tempo) {
                                                        $class = 'text-danger';
                                                    } elseif ($diff->days <= 7) {
                                                        $class = 'text-warning';
                                                    }
                                                    ?>
                                                    <span class="<?= $class ?>">
                                                        <?= $jatuh_tempo->format('d/m/Y') ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php
                                                    $status_class = '';
                                                    $status_text = '';
                                                    switch ($piutang['status']) {
                                                        case 'lunas':
                                                            $status_class = 'bg-success';
                                                            $status_text = 'Lunas';
                                                            break;
                                                        case 'belum_lunas':
                                                            $status_class = 'bg-warning';
                                                            $status_text = 'Belum Lunas';
                                                            break;
                                                        default:
                                                            $status_class = 'bg-secondary';
                                                            $status_text = ucfirst(str_replace('_', ' ', $piutang['status']));
                                                    }
                                                    ?>
                                                    <span class="badge <?= $status_class ?>"><?= $status_text ?></span>
                                                </td>
                                                <td>
                                                    <?php if (!empty($piutang['no_hp_kepala_desa'])): ?>
                                                        <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $piutang['no_hp_kepala_desa']) ?>" 
                                                           target="_blank" class="btn btn-sm btn-success">
                                                            <i class="fab fa-whatsapp"></i>
                                                        </a>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($piutang['status'] != 'lunas'): ?>
                                                        <button class="btn btn-sm btn-primary" 
                                                                onclick="showPaymentModal(<?= $piutang['id'] ?>, '<?= htmlspecialchars($piutang['nama_desa']) ?>', <?= $piutang['sisa_piutang'] ?>)">
                                                            <i class="fas fa-money-bill"></i> Bayar
                                                        </button>
                                                    <?php endif; ?>
                                                    <button class="btn btn-sm btn-info" 
                                                            onclick="showPaymentHistory(<?= $piutang['id'] ?>)">
                                                        <i class="fas fa-history"></i> Riwayat
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <nav aria-label="Page navigation">
                                <ul class="pagination justify-content-center">
                                    <?php if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?= $page - 1 ?>&desa=<?= $filter_desa ?>&status=<?= $filter_status ?>">Previous</a>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                        <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                            <a class="page-link" href="?page=<?= $i ?>&desa=<?= $filter_desa ?>&status=<?= $filter_status ?>"><?= $i ?></a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <?php if ($page < $total_pages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?= $page + 1 ?>&desa=<?= $filter_desa ?>&status=<?= $filter_status ?>">Next</a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Pembayaran -->
    <div class="modal fade" id="paymentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Pembayaran Piutang</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="piutang_id" id="payment_piutang_id">
                        <input type="hidden" name="bayar_piutang" value="1">
                        
                        <div class="mb-3">
                            <label class="form-label">Desa</label>
                            <input type="text" class="form-control" id="payment_desa" readonly>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Sisa Piutang</label>
                            <input type="text" class="form-control" id="payment_sisa" readonly>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Tanggal Bayar</label>
                            <input type="date" class="form-control" name="tanggal_bayar" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Jumlah Bayar</label>
                            <input type="number" class="form-control" name="jumlah_bayar" step="0.01" min="0" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Keterangan</label>
                            <textarea class="form-control" name="keterangan" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Simpan Pembayaran</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Riwayat Pembayaran -->
    <div class="modal fade" id="historyModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Riwayat Pembayaran</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="historyContent">
                    <!-- Content will be loaded via AJAX -->
                </div>
            </div>
        </div>
    </div>

    <?php include 'layouts/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function showPaymentModal(piutangId, desaName, sisaPiutang) {
            document.getElementById('payment_piutang_id').value = piutangId;
            document.getElementById('payment_desa').value = desaName;
            document.getElementById('payment_sisa').value = 'Rp ' + new Intl.NumberFormat('id-ID').format(sisaPiutang);
            
            const modal = new bootstrap.Modal(document.getElementById('paymentModal'));
            modal.show();
        }

        function showPaymentHistory(piutangId) {
            const modal = new bootstrap.Modal(document.getElementById('historyModal'));
            
            // Load payment history via AJAX
            fetch('ajax/get_payment_history.php?piutang_id=' + piutangId)
                .then(response => response.text())
                .then(data => {
                    document.getElementById('historyContent').innerHTML = data;
                    modal.show();
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('historyContent').innerHTML = '<p class="text-danger">Error loading payment history</p>';
                    modal.show();
                });
        }

        function exportData() {
            window.location.href = 'export/piutang_excel.php';
        }
    </script>
</body>
</html>



