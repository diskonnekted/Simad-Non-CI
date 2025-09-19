<?php
require_once '../config/database.php';
require_once '../config/auth.php';

// Check authentication
AuthStatic::requireLogin();

// Check if user has permission to view logs
if (!AuthStatic::hasRole(['admin', 'supervisor'])) {
    http_response_code(403);
    echo '<div class="alert alert-danger">Anda tidak memiliki izin untuk melihat log transaksi.</div>';
    exit;
}

$log_id = $_GET['id'] ?? '';

if (empty($log_id)) {
    http_response_code(400);
    echo '<div class="alert alert-danger">ID log tidak valid.</div>';
    exit;
}

try {
    $db = getDatabase();
    
    // Get log detail
    $log_query = "
        SELECT 
            lt.*,
            DATE_FORMAT(lt.tanggal_transaksi, '%d/%m/%Y') as tanggal_transaksi_formatted,
            DATE_FORMAT(lt.tanggal_jatuh_tempo, '%d/%m/%Y') as tanggal_jatuh_tempo_formatted,
            DATE_FORMAT(lt.deleted_at, '%d/%m/%Y %H:%i:%s') as tanggal_hapus_formatted,
            DATE_FORMAT(lt.created_at, '%d/%m/%Y %H:%i:%s') as tanggal_buat_formatted,
            DATE_FORMAT(lt.updated_at, '%d/%m/%Y %H:%i:%s') as tanggal_update_formatted
        FROM log_transaksi lt
        WHERE lt.id = ?
    ";
    
    $log_data = $db->select($log_query, [$log_id]);
    
    if (empty($log_data)) {
        echo '<div class="alert alert-danger">Log transaksi tidak ditemukan.</div>';
        exit;
    }
    
    $log = $log_data[0];
    
    // Parse JSON data if available
    $transaksi_data = null;
    if (!empty($log['data_transaksi_json'])) {
        $transaksi_data = json_decode($log['data_transaksi_json'], true);
    }
    
    // Helper function for status badge
    function getStatusBadge($status) {
        switch ($status) {
            case 'draft':
                return '<span class="badge badge-secondary">Draft</span>';
            case 'pending':
                return '<span class="badge badge-warning">Pending</span>';
            case 'diproses':
                return '<span class="badge badge-info">Diproses</span>';
            case 'selesai':
                return '<span class="badge badge-success">Selesai</span>';
            case 'dibatalkan':
                return '<span class="badge badge-danger">Dibatalkan</span>';
            default:
                return '<span class="badge badge-light">' . ucfirst($status) . '</span>';
        }
    }
    
    // Helper function for payment method badge
    function getPaymentBadge($method) {
        switch ($method) {
            case 'transfer':
                return '<span class="badge badge-primary">Transfer Bank</span>';
            case 'cash':
                return '<span class="badge badge-success">Cash</span>';
            case 'tempo':
                return '<span class="badge badge-warning">Tempo</span>';
            default:
                return '<span class="badge badge-light">' . ucfirst($method) . '</span>';
        }
    }
    
    // Helper function for rupiah format
    function formatRupiah($amount) {
        return 'Rp ' . number_format($amount, 0, ',', '.');
    }
    
?>

<div class="row">
    <div class="col-md-6">
        <h6 class="text-primary">Informasi Transaksi</h6>
        <table class="table table-sm table-borderless">
            <tr>
                <td width="40%"><strong>Invoice:</strong></td>
                <td><?= htmlspecialchars($log['nomor_invoice']) ?></td>
            </tr>
            <tr>
                <td><strong>Desa:</strong></td>
                <td><?= htmlspecialchars($log['nama_desa']) ?></td>
            </tr>
            <tr>
                <td><strong>Sales:</strong></td>
                <td><?= htmlspecialchars($log['nama_user']) ?></td>
            </tr>
            <tr>
                <td><strong>Jenis Transaksi:</strong></td>
                <td><span class="badge badge-info"><?= ucfirst($log['jenis_transaksi']) ?></span></td>
            </tr>
            <tr>
                <td><strong>Metode Pembayaran:</strong></td>
                <td><?= getPaymentBadge($log['metode_pembayaran']) ?></td>
            </tr>
            <?php if (!empty($log['nama_bank'])): ?>
            <tr>
                <td><strong>Bank:</strong></td>
                <td><?= htmlspecialchars($log['nama_bank']) ?></td>
            </tr>
            <?php endif; ?>
            <tr>
                <td><strong>DP Amount:</strong></td>
                <td><?= formatRupiah($log['dp_amount']) ?></td>
            </tr>
            <tr>
                <td><strong>Total Amount:</strong></td>
                <td><strong class="text-success"><?= formatRupiah($log['total_amount']) ?></strong></td>
            </tr>
            <tr>
                <td><strong>Status:</strong></td>
                <td><?= getStatusBadge($log['status_transaksi']) ?></td>
            </tr>
        </table>
    </div>
    
    <div class="col-md-6">
        <h6 class="text-danger">Informasi Penghapusan</h6>
        <table class="table table-sm table-borderless">
            <tr>
                <td width="40%"><strong>Alasan Hapus:</strong></td>
                <td><?= htmlspecialchars($log['alasan_hapus']) ?></td>
            </tr>
            <tr>
                <td><strong>Dihapus Oleh:</strong></td>
                <td><?= htmlspecialchars($log['deleted_by_name']) ?></td>
            </tr>
            <tr>
                <td><strong>Tanggal Hapus:</strong></td>
                <td><?= $log['tanggal_hapus_formatted'] ?></td>
            </tr>
        </table>
        
        <h6 class="text-info mt-3">Informasi Waktu</h6>
        <table class="table table-sm table-borderless">
            <tr>
                <td width="40%"><strong>Tanggal Transaksi:</strong></td>
                <td><?= $log['tanggal_transaksi_formatted'] ?></td>
            </tr>
            <?php if (!empty($log['tanggal_jatuh_tempo'])): ?>
            <tr>
                <td><strong>Jatuh Tempo:</strong></td>
                <td><?= $log['tanggal_jatuh_tempo_formatted'] ?></td>
            </tr>
            <?php endif; ?>
            <tr>
                <td><strong>Dibuat:</strong></td>
                <td><?= $log['tanggal_buat_formatted'] ?></td>
            </tr>
            <tr>
                <td><strong>Terakhir Update:</strong></td>
                <td><?= $log['tanggal_update_formatted'] ?></td>
            </tr>
        </table>
    </div>
</div>

<?php if (!empty($log['catatan'])): ?>
<div class="row mt-3">
    <div class="col-12">
        <h6 class="text-secondary">Catatan</h6>
        <div class="alert alert-light">
            <?= nl2br(htmlspecialchars($log['catatan'])) ?>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($transaksi_data): ?>
<div class="row mt-3">
    <div class="col-12">
        <h6 class="text-secondary">Data Transaksi Lengkap (JSON)</h6>
        <div class="alert alert-secondary">
            <small>
                <pre style="max-height: 200px; overflow-y: auto; font-size: 11px;"><?= htmlspecialchars(json_encode($transaksi_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
            </small>
        </div>
    </div>
</div>
<?php endif; ?>

<?php
} catch (Exception $e) {
    echo '<div class="alert alert-danger">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
}
?>