<?php
require_once '../config/database.php';
require_once '../config/auth.php';

// Pastikan user sudah login
if (!AuthStatic::isLoggedIn()) {
    http_response_code(401);
    echo '<p class=\"text-danger\">Unauthorized access</p>';
    exit;
}

$piutang_id = $_GET['piutang_id'] ?? 0;

if (!$piutang_id) {
    echo '<p class=\"text-danger\">ID Piutang tidak valid</p>';
    exit;
}

try {
    // Ambil informasi piutang
    $piutang_sql = \"SELECT p.*, d.nama_desa, t.nomor_invoice 
                    FROM piutang p 
                    LEFT JOIN desa d ON p.desa_id = d.id 
                    LEFT JOIN transaksi t ON p.transaksi_id = t.id 
                    WHERE p.id = ?\";
    $piutang_stmt = $pdo->prepare($piutang_sql);
    $piutang_stmt->execute([$piutang_id]);
    $piutang = $piutang_stmt->fetch();
    
    if (!$piutang) {
        echo '<p class=\"text-danger\">Data piutang tidak ditemukan</p>';
        exit;
    }
    
    // Ambil riwayat pembayaran
    $history_sql = \"SELECT * FROM pembayaran_piutang 
                    WHERE piutang_id = ? 
                    ORDER BY tanggal_bayar DESC, created_at DESC\";
    $history_stmt = $pdo->prepare($history_sql);
    $history_stmt->execute([$piutang_id]);
    $payments = $history_stmt->fetchAll();
    
    ?>
    <div class=\"row mb-3\">
        <div class=\"col-md-6\">
            <strong>Desa:</strong> <?= htmlspecialchars($piutang['nama_desa']) ?><br>
            <strong>Invoice:</strong> <?= htmlspecialchars($piutang['nomor_invoice'] ?? '-') ?><br>
            <strong>Jumlah Piutang:</strong> Rp <?= number_format($piutang['jumlah_piutang'], 0, ',', '.') ?>
        </div>
        <div class=\"col-md-6\">
            <strong>Sisa Piutang:</strong> Rp <?= number_format($piutang['sisa_piutang'], 0, ',', '.') ?><br>
            <strong>Status:</strong> 
            <span class=\"badge <?= $piutang['status'] == 'lunas' ? 'bg-success' : 'bg-warning' ?>\">
                <?= ucfirst(str_replace('_', ' ', $piutang['status'])) ?>
            </span><br>
            <strong>Jatuh Tempo:</strong> <?= date('d/m/Y', strtotime($piutang['tanggal_jatuh_tempo'])) ?>
        </div>
    </div>
    
    <hr>
    
    <h6>Riwayat Pembayaran</h6>
    
    <?php if (empty($payments)): ?>
        <div class=\"alert alert-info\">
            <i class=\"fas fa-info-circle\"></i> Belum ada pembayaran untuk piutang ini.
        </div>
    <?php else: ?>
        <div class=\"table-responsive\">
            <table class=\"table table-sm table-striped\">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Tanggal Bayar</th>
                        <th>Jumlah Bayar</th>
                        <th>Keterangan</th>
                        <th>Tanggal Input</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $total_dibayar = 0;
                    foreach ($payments as $index => $payment): 
                        $total_dibayar += $payment['jumlah_bayar'];
                    ?>
                        <tr>
                            <td><?= $index + 1 ?></td>
                            <td><?= date('d/m/Y', strtotime($payment['tanggal_bayar'])) ?></td>
                            <td>Rp <?= number_format($payment['jumlah_bayar'], 0, ',', '.') ?></td>
                            <td><?= htmlspecialchars($payment['keterangan'] ?: '-') ?></td>
                            <td><?= date('d/m/Y H:i', strtotime($payment['created_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class=\"table-info\">
                        <th colspan=\"2\">Total Dibayar</th>
                        <th>Rp <?= number_format($total_dibayar, 0, ',', '.') ?></th>
                        <th colspan=\"2\"></th>
                    </tr>
                </tfoot>
            </table>
        </div>
        
        <div class=\"row mt-3\">
            <div class=\"col-md-6\">
                <div class=\"card bg-light\">
                    <div class=\"card-body\">
                        <h6 class=\"card-title\">Ringkasan Pembayaran</h6>
                        <p class=\"mb-1\"><strong>Jumlah Piutang:</strong> Rp <?= number_format($piutang['jumlah_piutang'], 0, ',', '.') ?></p>
                        <p class=\"mb-1\"><strong>Total Dibayar:</strong> Rp <?= number_format($total_dibayar, 0, ',', '.') ?></p>
                        <p class=\"mb-0\"><strong>Sisa Piutang:</strong> 
                            <span class=\"<?= $piutang['sisa_piutang'] <= 0 ? 'text-success' : 'text-warning' ?>\">
                                Rp <?= number_format($piutang['sisa_piutang'], 0, ',', '.') ?>
                            </span>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
    
    <?php
    
} catch (Exception $e) {
    echo '<p class=\"text-danger\">Error: ' . htmlspecialchars($e->getMessage()) . '</p>';
}
?>
