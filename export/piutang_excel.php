<?php
require_once '../config/database.php';
require_once '../config/auth.php';

// Pastikan user sudah login
if (!AuthStatic::isLoggedIn()) {
    header(\"Location: ../login.php\");
    exit;
}

try {
    // Query untuk mengambil semua data piutang
    $sql = \"SELECT p.*, d.nama_desa, d.nama_kepala_desa, d.no_hp_kepala_desa, 
                   t.nomor_invoice, t.tanggal_transaksi,
                   (SELECT SUM(jumlah_bayar) FROM pembayaran_piutang WHERE piutang_id = p.id) as total_dibayar
            FROM piutang p 
            LEFT JOIN desa d ON p.desa_id = d.id 
            LEFT JOIN transaksi t ON p.transaksi_id = t.id 
            ORDER BY p.tanggal_jatuh_tempo ASC, p.created_at DESC\";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $piutang_list = $stmt->fetchAll();
    
    // Set header untuk download Excel
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment;filename=\"Data_Piutang_\" . date('Y-m-d') . \".xls\"');
    header('Cache-Control: max-age=0');
    
    echo \"<table border='1'>\";
    echo \"<tr>\";
    echo \"<th>No</th>\";
    echo \"<th>Invoice</th>\";
    echo \"<th>Desa</th>\";
    echo \"<th>Kepala Desa</th>\";
    echo \"<th>No HP</th>\";
    echo \"<th>Tanggal Transaksi</th>\";
    echo \"<th>Jumlah Piutang</th>\";
    echo \"<th>Total Dibayar</th>\";
    echo \"<th>Sisa Piutang</th>\";
    echo \"<th>Jatuh Tempo</th>\";
    echo \"<th>Status</th>\";
    echo \"<th>Denda</th>\";
    echo \"<th>Catatan</th>\";
    echo \"</tr>\";
    
    foreach ($piutang_list as $index => $piutang) {
        echo \"<tr>\";
        echo \"<td>\" . ($index + 1) . \"</td>\";
        echo \"<td>\" . htmlspecialchars($piutang['nomor_invoice'] ?? '-') . \"</td>\";
        echo \"<td>\" . htmlspecialchars($piutang['nama_desa'] ?? '-') . \"</td>\";
        echo \"<td>\" . htmlspecialchars($piutang['nama_kepala_desa'] ?? '-') . \"</td>\";
        echo \"<td>\" . htmlspecialchars($piutang['no_hp_kepala_desa'] ?? '-') . \"</td>\";
        echo \"<td>\" . ($piutang['tanggal_transaksi'] ? date('d/m/Y', strtotime($piutang['tanggal_transaksi'])) : '-') . \"</td>\";
        echo \"<td>\" . number_format($piutang['jumlah_piutang'], 0, ',', '.') . \"</td>\";
        echo \"<td>\" . number_format($piutang['total_dibayar'] ?? 0, 0, ',', '.') . \"</td>\";
        echo \"<td>\" . number_format($piutang['sisa_piutang'], 0, ',', '.') . \"</td>\";
        echo \"<td>\" . date('d/m/Y', strtotime($piutang['tanggal_jatuh_tempo'])) . \"</td>\";
        echo \"<td>\" . ucfirst(str_replace('_', ' ', $piutang['status'])) . \"</td>\";
        echo \"<td>\" . number_format($piutang['denda'] ?? 0, 0, ',', '.') . \"</td>\";
        echo \"<td>\" . htmlspecialchars($piutang['catatan'] ?? '-') . \"</td>\";
        echo \"</tr>\";
    }
    
    echo \"</table>\";
    
} catch (Exception $e) {
    echo \"Error: \" . $e->getMessage();
}
?>
