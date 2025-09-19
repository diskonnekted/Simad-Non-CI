<?php
require_once 'config/database.php';
require_once 'config/auth.php';

// Cek autentikasi
if (!AuthStatic::isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$user = AuthStatic::getCurrentUser();
$db = getDatabase();

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

// Parameter pencarian dan filter
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$desa_filter = $_GET['desa_id'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$payment_type = $_GET['payment_type'] ?? '';

// Build query conditions
$conditions = ["1=1"];
$params = [];

// Filter berdasarkan role
if ($user['role'] === 'sales') {
    $conditions[] = "t.user_id = ?";
    $params[] = $user['id'];
}

if ($search) {
    $conditions[] = "(d.nama_desa LIKE ? OR t.nomor_invoice LIKE ? OR t.catatan LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

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

$where_clause = implode(' AND ', $conditions);

// Query untuk menghitung total
$count_query = "SELECT COUNT(*) as total FROM transaksi t LEFT JOIN desa d ON t.desa_id = d.id WHERE $where_clause";
$total_result = $db->select($count_query, $params);
$total_records = $total_result[0]['total'] ?? 0;

// Query untuk statistik
$stats_query = "SELECT 
    COUNT(*) as total_transaksi,
    COALESCE(SUM(total_amount), 0) as total_pendapatan,
    COUNT(CASE WHEN status_transaksi IN ('draft', 'diproses', 'dikirim') THEN 1 END) as pending_count,
    COUNT(CASE WHEN status_transaksi = 'selesai' THEN 1 END) as completed_count
    FROM transaksi t 
    LEFT JOIN desa d ON t.desa_id = d.id 
    WHERE $where_clause";
$stats_result = $db->select($stats_query, $params);
$stats = $stats_result[0] ?? ['total_transaksi' => 0, 'total_pendapatan' => 0, 'pending_count' => 0, 'completed_count' => 0];

// Query untuk daftar desa
$desa_list = $db->select("SELECT id, nama_desa, kecamatan FROM desa ORDER BY nama_desa");

$page_title = 'Daftar Penjualan';
require_once 'layouts/header.php';
?>

<style>
.simple-card {
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 15px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.simple-table {
    width: 100%;
    border-collapse: collapse;
    background: white;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.simple-table th {
    background: #f9fafb;
    padding: 12px;
    text-align: left;
    font-weight: 600;
    border-bottom: 1px solid #e5e7eb;
}

.simple-table td {
    padding: 12px;
    border-bottom: 1px solid #f3f4f6;
}

.simple-table tr:hover {
    background: #f9fafb;
}

.simple-btn {
    display: inline-block;
    padding: 8px 16px;
    background: #3b82f6;
    color: white;
    text-decoration: none;
    border-radius: 6px;
    border: none;
    cursor: pointer;
    font-size: 14px;
    margin: 2px;
}

.simple-btn:hover {
    background: #2563eb;
}

.simple-btn.green {
    background: #10b981;
}

.simple-btn.green:hover {
    background: #059669;
}

.simple-btn.red {
    background: #ef4444;
}

.simple-btn.red:hover {
    background: #dc2626;
}

.simple-btn.gray {
    background: #6b7280;
}

.simple-btn.gray:hover {
    background: #4b5563;
}

.simple-form {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 10px;
    margin-bottom: 15px;
}

.simple-input {
    padding: 8px 12px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-size: 14px;
}

.simple-input:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 10px;
    margin-bottom: 15px;
}

.stat-card {
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 15px;
    text-align: center;
}

.stat-number {
    font-size: 24px;
    font-weight: bold;
    color: #1f2937;
}

.stat-label {
    color: #6b7280;
    font-size: 14px;
    margin-top: 5px;
}

.status-badge {
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 500;
}

.status-selesai { background: #dcfce7; color: #166534; }
.status-diproses { background: #dbeafe; color: #1d4ed8; }
.status-dikirim { background: #fef3c7; color: #92400e; }
.status-draft { background: #f3f4f6; color: #374151; }
.status-dibatalkan { background: #fee2e2; color: #dc2626; }

.payment-tunai { background: #dcfce7; color: #166534; }
.payment-dp { background: #fef3c7; color: #92400e; }
.payment-tempo { background: #fee2e2; color: #dc2626; }
</style>

<div class="container" style="max-width: 1200px; margin: 0; padding: 10px 15px;">
    <!-- Header -->
    <div class="simple-card">
        <h1 style="margin: 0 0 10px 0; color: #1f2937;">📊 Daftar Penjualan</h1>
        <p style="margin: 0; color: #6b7280;">Kelola semua penjualan produk dan layanan</p>
        <div style="margin-top: 15px;">
            <?php if (AuthStatic::hasRole(['admin', 'finance'])): ?>
            <a href="export-transaksi.php" class="simple-btn">📥 Export Data</a>
            <?php endif; ?>
            <a href="transaksi-add.php" class="simple-btn green">➕ Buat Penjualan</a>
        </div>
    </div>

    <!-- Statistik -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-number"><?= number_format($stats['total_transaksi']) ?></div>
            <div class="stat-label">Total Penjualan</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?= formatRupiah($stats['total_pendapatan']) ?></div>
            <div class="stat-label">Total Nilai</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?= number_format($stats['pending_count']) ?></div>
            <div class="stat-label">Dalam Proses</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?= number_format($stats['completed_count']) ?></div>
            <div class="stat-label">Selesai</div>
        </div>
    </div>

    <!-- Filter -->
    <div class="simple-card">
        <h3 style="margin: 0 0 15px 0;">🔍 Filter & Pencarian</h3>
        <form method="GET" class="simple-form">
            <input type="text" name="search" class="simple-input" placeholder="Cari invoice, desa, catatan..." value="<?= htmlspecialchars($search) ?>">
            
            <select name="status" class="simple-input">
                <option value="">Semua Status</option>
                <option value="draft" <?= $status_filter === 'draft' ? 'selected' : '' ?>>Draft</option>
                <option value="diproses" <?= $status_filter === 'diproses' ? 'selected' : '' ?>>Diproses</option>
                <option value="dikirim" <?= $status_filter === 'dikirim' ? 'selected' : '' ?>>Dikirim</option>
                <option value="selesai" <?= $status_filter === 'selesai' ? 'selected' : '' ?>>Selesai</option>
                <option value="dibatalkan" <?= $status_filter === 'dibatalkan' ? 'selected' : '' ?>>Dibatalkan</option>
            </select>
            
            <select name="payment_type" class="simple-input">
                <option value="">Semua Pembayaran</option>
                <option value="tunai" <?= $payment_type === 'tunai' ? 'selected' : '' ?>>Tunai</option>
                <option value="dp" <?= $payment_type === 'dp' ? 'selected' : '' ?>>DP</option>
                <option value="tempo" <?= $payment_type === 'tempo' ? 'selected' : '' ?>>Tempo</option>
            </select>
            
            <select name="desa_id" class="simple-input">
                <option value="">Semua Desa</option>
                <?php foreach ($desa_list as $d): ?>
                <option value="<?= $d['id'] ?>" <?= $desa_filter == $d['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($d['nama_desa']) ?> - <?= htmlspecialchars($d['kecamatan']) ?>
                </option>
                <?php endforeach; ?>
            </select>
            
            <input type="date" name="date_from" class="simple-input" value="<?= htmlspecialchars($date_from) ?>">
            <input type="date" name="date_to" class="simple-input" value="<?= htmlspecialchars($date_to) ?>">
            
            <button type="submit" class="simple-btn">🔍 Cari</button>
            <a href="transaksi.php" class="simple-btn gray">🔄 Reset</a>
        </form>
    </div>

    <!-- Tabel Transaksi -->
    <div class="simple-card">
        <h3 style="margin: 0 0 15px 0;">📋 Data Transaksi (Total: <?= $total_records ?>)</h3>
        
        <div style="overflow-x: auto;">
            <table class="simple-table">
                <thead>
                    <tr>
                        <th>Invoice</th>
                        <th>Tanggal</th>
                        <th>Desa</th>
                        <th>Total</th>
                        <th>Bank</th>
                        <th>Pembayaran</th>
                        <th>Status</th>
                        <th>Piutang</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Query transaksi
                    $query = "SELECT 
                        t.id,
                        t.nomor_invoice,
                        t.tanggal_transaksi,
                        t.total_amount,
                        t.status_transaksi,
                        t.metode_pembayaran,
                        t.created_at,
                        d.nama_desa,
                        d.kecamatan,
                        u.nama_lengkap as sales_name,
                        b.nama_bank,
                        COALESCE(SUM(p.jumlah_piutang), 0) as total_piutang
                    FROM transaksi t
                    LEFT JOIN desa d ON t.desa_id = d.id
                    LEFT JOIN users u ON t.user_id = u.id
                    LEFT JOIN bank b ON t.bank_id = b.id
                    LEFT JOIN piutang p ON t.id = p.transaksi_id AND p.status = 'aktif'
                    WHERE $where_clause
                    GROUP BY t.id
                    ORDER BY t.tanggal_transaksi DESC, t.created_at DESC
                    LIMIT 50";
                    
                    try {
                        $transaksi = $db->select($query, $params);
                        
                        if (empty($transaksi)) {
                            echo '<tr><td colspan="9" style="text-align: center; padding: 40px; color: #6b7280;">Tidak ada data transaksi</td></tr>';
                        } else {
                            foreach ($transaksi as $t) {
                                $status_class = 'status-' . ($t['status_transaksi'] ?? 'draft');
                                $payment_class = 'payment-' . ($t['metode_pembayaran'] ?? 'tunai');
                                $piutang_display = $t['total_piutang'] > 0 ? formatRupiah($t['total_piutang']) : '-';
                    ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($t['nomor_invoice']) ?></strong><br>
                            <small style="color: #6b7280;">#<?= $t['id'] ?></small>
                        </td>
                        <td>
                            <?= formatTanggalIndonesia($t['tanggal_transaksi']) ?><br>
                            <small style="color: #6b7280;"><?= date('H:i', strtotime($t['created_at'] ?? $t['tanggal_transaksi'])) ?></small>
                        </td>
                        <td>
                            <?= htmlspecialchars($t['nama_desa'] ?? '-') ?><br>
                            <small style="color: #6b7280;"><?= htmlspecialchars($t['kecamatan'] ?? '') ?></small>
                        </td>
                        <td><strong><?= formatRupiah($t['total_amount']) ?></strong></td>
                        <td><?= htmlspecialchars($t['nama_bank'] ?? '-') ?></td>
                        <td><span class="status-badge <?= $payment_class ?>"><?= ucfirst($t['metode_pembayaran'] ?? 'tunai') ?></span></td>
                        <td><span class="status-badge <?= $status_class ?>"><?= ucfirst($t['status_transaksi'] ?? 'draft') ?></span></td>
                        <td><?= $piutang_display ?></td>
                        <td>
                            <a href="transaksi-view.php?id=<?= $t['id'] ?>" class="simple-btn" style="padding: 4px 8px; font-size: 12px;">👁️</a>
                            <?php if (AuthStatic::hasRole(['admin', 'supervisor']) && $t['status_transaksi'] !== 'selesai'): ?>
                            <a href="transaksi-edit.php?id=<?= $t['id'] ?>" class="simple-btn green" style="padding: 4px 8px; font-size: 12px;">✏️</a>
                            <?php endif; ?>
                            <?php if (AuthStatic::hasRole(['admin'])): ?>
                            <button onclick="if(confirm('Hapus transaksi <?= htmlspecialchars($t['nomor_invoice']) ?>?')) window.location.href='transaksi-delete.php?id=<?= $t['id'] ?>'" class="simple-btn red" style="padding: 4px 8px; font-size: 12px;">🗑️</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php
                            }
                        }
                    } catch (Exception $e) {
                        echo '<tr><td colspan="9" style="text-align: center; padding: 40px; color: #ef4444;">Error: ' . htmlspecialchars($e->getMessage()) . '</td></tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once 'layouts/footer.php'; ?>
