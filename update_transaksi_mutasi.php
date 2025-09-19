<?php
require_once 'config/database.php';
require_once 'config/auth.php';

// Check authentication
if (!AuthStatic::isLoggedIn()) {
    die('Unauthorized access');
}

$user = AuthStatic::getCurrentUser();
if (!AuthStatic::hasRole(['admin'])) {
    die('Access denied - Admin only');
}

$db = getDatabase();

echo "<h2>Update Transaksi dan Mutasi Kas</h2>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .success { color: green; }
    .error { color: red; }
    .info { color: blue; }
    .warning { color: orange; }
    table { border-collapse: collapse; width: 100%; margin: 10px 0; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    th { background-color: #f2f2f2; }
    .btn { padding: 10px 20px; margin: 10px 5px; background: #007cba; color: white; border: none; cursor: pointer; }
    .btn:hover { background: #005a87; }
</style>";

try {
    // 1. Analisis transaksi penjualan yang belum ada di mutasi kas
    echo "<h3>1. Analisis Transaksi Penjualan</h3>";
    
    $transaksi_belum_mutasi = $db->select("
        SELECT t.id, t.nomor_invoice, t.tanggal_transaksi, t.total_amount, 
               t.metode_pembayaran, t.dp_amount, t.bank_id,
               d.nama_desa
        FROM transaksi t
        LEFT JOIN desa d ON t.desa_id = d.id
        LEFT JOIN mutasi_kas mk ON (mk.referensi_id = t.id AND mk.referensi_tabel = 'transaksi' AND mk.jenis_transaksi = 'penjualan')
        WHERE mk.id IS NULL 
        AND t.metode_pembayaran IN ('tunai', 'dp_pelunasan')
        ORDER BY t.tanggal_transaksi DESC
    ");
    
    echo "<div class='info'>Ditemukan " . count($transaksi_belum_mutasi) . " transaksi penjualan yang belum tercatat di mutasi kas</div>";
    
    if (!empty($transaksi_belum_mutasi)) {
        echo "<table>";
        echo "<tr><th>Invoice</th><th>Tanggal</th><th>Desa</th><th>Metode</th><th>Subtotal</th><th>DP Amount</th><th>Bank ID</th></tr>";
        foreach ($transaksi_belum_mutasi as $t) {
            echo "<tr>";
            echo "<td>{$t['nomor_invoice']}</td>";
            echo "<td>" . date('d/m/Y', strtotime($t['tanggal_transaksi'])) . "</td>";
            echo "<td>{$t['nama_desa']}</td>";
            echo "<td>{$t['metode_pembayaran']}</td>";
            echo "<td>Rp " . number_format($t['total_amount'], 0, ',', '.') . "</td>";
            echo "<td>Rp " . number_format($t['dp_amount'], 0, ',', '.') . "</td>";
            echo "<td>{$t['bank_id']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // 2. Analisis pembayaran piutang yang belum ada di mutasi kas
    echo "<h3>2. Analisis Pembayaran Piutang</h3>";
    
    $pembayaran_belum_mutasi = $db->select("
        SELECT pp.id, pp.piutang_id, pp.jumlah_bayar, pp.tanggal_bayar, pp.metode_bayar,
               pp.bank_id, t.nomor_invoice, d.nama_desa
        FROM pembayaran_piutang pp
        LEFT JOIN piutang p ON pp.piutang_id = p.id
        LEFT JOIN transaksi t ON p.transaksi_id = t.id
        LEFT JOIN desa d ON t.desa_id = d.id
        LEFT JOIN mutasi_kas mk ON (mk.referensi_id = pp.piutang_id AND mk.referensi_tabel = 'piutang' AND mk.jenis_transaksi = 'pembayaran_piutang')
        WHERE mk.id IS NULL
        ORDER BY pp.tanggal_bayar DESC
    ");
    
    echo "<div class='info'>Ditemukan " . count($pembayaran_belum_mutasi) . " pembayaran piutang yang belum tercatat di mutasi kas</div>";
    
    if (!empty($pembayaran_belum_mutasi)) {
        echo "<table>";
        echo "<tr><th>ID Bayar</th><th>Invoice</th><th>Tanggal</th><th>Desa</th><th>Jumlah</th><th>Metode</th><th>Bank ID</th></tr>";
        foreach ($pembayaran_belum_mutasi as $p) {
            echo "<tr>";
            echo "<td>{$p['id']}</td>";
            echo "<td>{$p['nomor_invoice']}</td>";
            echo "<td>" . date('d/m/Y', strtotime($p['tanggal_bayar'])) . "</td>";
            echo "<td>{$p['nama_desa']}</td>";
            echo "<td>Rp " . number_format($p['jumlah_bayar'], 0, ',', '.') . "</td>";
            echo "<td>{$p['metode_bayar']}</td>";
            echo "<td>{$p['bank_id']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // 3. Form untuk eksekusi update
    if (!empty($transaksi_belum_mutasi) || !empty($pembayaran_belum_mutasi)) {
        echo "<h3>3. Eksekusi Update</h3>";
        echo "<form method='POST'>";
        echo "<button type='submit' name='update_mutasi' class='btn' onclick='return confirm(\"Apakah Anda yakin ingin mengupdate semua mutasi kas?\")'>Update Semua Mutasi Kas</button>";
        echo "</form>";
    }
    
    // 4. Proses update jika diminta
    if (isset($_POST['update_mutasi'])) {
        echo "<h3>4. Proses Update Mutasi Kas</h3>";
        
        $db->beginTransaction();
        $success_count = 0;
        $error_count = 0;
        
        try {
            // Update transaksi penjualan
            foreach ($transaksi_belum_mutasi as $t) {
                try {
                    $jumlah_masuk = $t['metode_pembayaran'] === 'tunai' ? $t['total_amount'] : $t['dp_amount'];
                    
                    if ($jumlah_masuk > 0) {
                        $keterangan = "Penjualan {$t['nomor_invoice']} - {$t['nama_desa']}";
                        if ($t['metode_pembayaran'] === 'dp_pelunasan') {
                            $keterangan .= " (DP: Rp " . number_format($t['dp_amount'], 0, ',', '.') . ")";
                        }
                        
                        $db->execute("
                            INSERT INTO mutasi_kas (
                                bank_id, jenis_mutasi, jenis_transaksi, referensi_id, referensi_tabel,
                                jumlah, keterangan, tanggal_mutasi, user_id
                            ) VALUES (?, 'masuk', 'penjualan', ?, 'transaksi', ?, ?, ?, ?)
                        ", [
                            $t['bank_id'], $t['id'], $jumlah_masuk, $keterangan, 
                            $t['tanggal_transaksi'], $user['id']
                        ]);
                        
                        echo "<div class='success'>✅ Transaksi {$t['nomor_invoice']} berhasil ditambahkan ke mutasi kas</div>";
                        $success_count++;
                    }
                } catch (Exception $e) {
                    echo "<div class='error'>❌ Error transaksi {$t['nomor_invoice']}: " . $e->getMessage() . "</div>";
                    $error_count++;
                }
            }
            
            // Update pembayaran piutang
            foreach ($pembayaran_belum_mutasi as $p) {
                try {
                    $keterangan = "Pembayaran piutang transaksi {$p['nomor_invoice']} - {$p['nama_desa']}";
                    
                    $db->execute("
                        INSERT INTO mutasi_kas (
                            bank_id, jenis_mutasi, jenis_transaksi, referensi_id, referensi_tabel,
                            jumlah, keterangan, tanggal_mutasi, user_id
                        ) VALUES (?, 'masuk', 'pembayaran_piutang', ?, 'piutang', ?, ?, ?, ?)
                    ", [
                        $p['bank_id'], $p['piutang_id'], $p['jumlah_bayar'], $keterangan,
                        $p['tanggal_bayar'], $user['id']
                    ]);
                    
                    echo "<div class='success'>✅ Pembayaran piutang {$p['nomor_invoice']} berhasil ditambahkan ke mutasi kas</div>";
                    $success_count++;
                } catch (Exception $e) {
                    echo "<div class='error'>❌ Error pembayaran {$p['nomor_invoice']}: " . $e->getMessage() . "</div>";
                    $error_count++;
                }
            }
            
            $db->commit();
            echo "<div class='success'><strong>Update selesai: {$success_count} berhasil, {$error_count} error</strong></div>";
            
            if ($success_count > 0) {
                echo "<div class='info'>Saldo bank akan otomatis terupdate melalui trigger database.</div>";
                echo "<a href='saldo-bank.php' class='btn'>Lihat Saldo Bank</a>";
            }
            
        } catch (Exception $e) {
            $db->rollback();
            echo "<div class='error'>❌ Error saat update: " . $e->getMessage() . "</div>";
        }
    }
    
    // 5. Informasi saldo bank saat ini
    echo "<h3>5. Saldo Bank Saat Ini</h3>";
    $saldo_banks = $db->select("
        SELECT b.nama_bank, sb.saldo_masuk, sb.saldo_keluar, sb.saldo_akhir,
               sb.periode_bulan, sb.periode_tahun, sb.updated_at
        FROM saldo_bank sb
        JOIN bank b ON sb.bank_id = b.id
        WHERE sb.periode_bulan = MONTH(CURDATE()) AND sb.periode_tahun = YEAR(CURDATE())
        ORDER BY b.nama_bank
    ");
    
    if (!empty($saldo_banks)) {
        echo "<table>";
        echo "<tr><th>Bank</th><th>Saldo Masuk</th><th>Saldo Keluar</th><th>Saldo Akhir</th><th>Periode</th><th>Update Terakhir</th></tr>";
        foreach ($saldo_banks as $sb) {
            echo "<tr>";
            echo "<td>{$sb['nama_bank']}</td>";
            echo "<td>Rp " . number_format($sb['saldo_masuk'], 0, ',', '.') . "</td>";
            echo "<td>Rp " . number_format($sb['saldo_keluar'], 0, ',', '.') . "</td>";
            echo "<td>Rp " . number_format($sb['saldo_akhir'], 0, ',', '.') . "</td>";
            echo "<td>{$sb['periode_bulan']}/{$sb['periode_tahun']}</td>";
            echo "<td>" . date('d/m/Y H:i', strtotime($sb['updated_at'])) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<div class='warning'>Tidak ada data saldo bank untuk periode ini</div>";
    }
    
} catch (Exception $e) {
    echo "<div class='error'>Error: " . $e->getMessage() . "</div>";
}
?>

<script>
// Auto refresh setelah update
if (window.location.search.includes('updated=1')) {
    setTimeout(function() {
        window.location.href = window.location.pathname;
    }, 3000);
}
</script>