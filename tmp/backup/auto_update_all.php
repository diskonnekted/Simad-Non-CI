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

echo "<h2>Auto Update Semua Transaksi dan Saldo Bank</h2>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .success { color: green; }
    .error { color: red; }
    .info { color: blue; }
    .warning { color: orange; }
    .step { background: #f8f9fa; padding: 15px; margin: 10px 0; border-left: 4px solid #007cba; }
    .btn { padding: 10px 20px; margin: 10px 5px; background: #007cba; color: white; border: none; cursor: pointer; }
    .btn:hover { background: #005a87; }
    .btn-success { background: #28a745; }
    .btn-success:hover { background: #218838; }
    .progress { background: #e9ecef; height: 20px; border-radius: 10px; margin: 10px 0; }
    .progress-bar { background: #007cba; height: 100%; border-radius: 10px; transition: width 0.3s; }
</style>";

if (!isset($_POST['execute_update'])) {
    // Tampilkan preview dan konfirmasi
    echo "<div class='step'>";
    echo "<h3>🔍 Preview Update yang Akan Dilakukan</h3>";
    
    try {
        // 1. Cek transaksi penjualan yang belum ada di mutasi kas
        $transaksi_belum_mutasi = $db->select("
            SELECT COUNT(*) as total
            FROM transaksi t
            LEFT JOIN mutasi_kas mk ON (mk.referensi_id = t.id AND mk.referensi_tabel = 'transaksi' AND mk.jenis_transaksi = 'penjualan')
            WHERE mk.id IS NULL 
            AND t.metode_pembayaran IN ('tunai', 'dp_pelunasan')
        ");
        
        $count_transaksi = $transaksi_belum_mutasi[0]['total'];
        
        // 2. Cek pembayaran piutang yang belum ada di mutasi kas
        $pembayaran_belum_mutasi = $db->select("
            SELECT COUNT(*) as total
            FROM pembayaran_piutang pp
            LEFT JOIN mutasi_kas mk ON (mk.referensi_id = pp.piutang_id AND mk.referensi_tabel = 'piutang' AND mk.jenis_transaksi = 'pembayaran_piutang')
            WHERE mk.id IS NULL
        ");
        
        $count_pembayaran = $pembayaran_belum_mutasi[0]['total'];
        
        // 3. Cek saldo bank yang perlu diupdate
        $banks = $db->select("SELECT COUNT(*) as total FROM bank WHERE status = 'aktif'");
        $count_banks = $banks[0]['total'];
        
        echo "<div class='info'>";
        echo "<h4>Yang Akan Diupdate:</h4>";
        echo "<ul>";
        echo "<li>📊 {$count_transaksi} transaksi penjualan yang belum tercatat di mutasi kas</li>";
        echo "<li>💰 {$count_pembayaran} pembayaran piutang yang belum tercatat di mutasi kas</li>";
        echo "<li>🏦 {$count_banks} bank yang akan direcalculate saldo-nya</li>";
        echo "</ul>";
        echo "</div>";
        
        if ($count_transaksi > 0 || $count_pembayaran > 0) {
            echo "<form method='POST'>";
            echo "<button type='submit' name='execute_update' class='btn btn-success' onclick='return confirm(\"Apakah Anda yakin ingin mengupdate semua transaksi dan saldo bank?\")'>🚀 Eksekusi Update Semua</button>";
            echo "</form>";
        } else {
            echo "<div class='success'>✅ Semua transaksi sudah tercatat dengan benar!</div>";
            echo "<form method='POST'>";
            echo "<button type='submit' name='recalc_only' class='btn'>🔄 Recalculate Saldo Bank Saja</button>";
            echo "</form>";
        }
        
    } catch (Exception $e) {
        echo "<div class='error'>Error: " . $e->getMessage() . "</div>";
    }
    
    echo "</div>";
} else {
    // Eksekusi update
    echo "<div class='step'>";
    echo "<h3>🚀 Eksekusi Update</h3>";
    echo "<div class='progress'><div class='progress-bar' id='progressBar' style='width: 0%'></div></div>";
    echo "<div id='status'>Memulai update...</div>";
    echo "</div>";
    
    echo "<script>
        function updateProgress(percent, message) {
            document.getElementById('progressBar').style.width = percent + '%';
            document.getElementById('status').innerHTML = message;
        }
    </script>";
    
    $db->beginTransaction();
    $total_success = 0;
    $total_error = 0;
    
    try {
        echo "<script>updateProgress(10, 'Menganalisis transaksi penjualan...');</script>";
        echo str_repeat(' ', 1024); // Force flush
        flush();
        
        // STEP 1: Update transaksi penjualan
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
        
        echo "<div class='info'>📊 Memproses " . count($transaksi_belum_mutasi) . " transaksi penjualan...</div>";
        
        foreach ($transaksi_belum_mutasi as $index => $t) {
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
                    
                    echo "<div class='success'>✅ Transaksi {$t['nomor_invoice']} berhasil ditambahkan</div>";
                    $total_success++;
                }
                
                $progress = 20 + (($index + 1) / count($transaksi_belum_mutasi)) * 30;
                echo "<script>updateProgress({$progress}, 'Memproses transaksi " . ($index + 1) . "/" . count($transaksi_belum_mutasi) . "');</script>";
                echo str_repeat(' ', 1024);
                flush();
                
            } catch (Exception $e) {
                echo "<div class='error'>❌ Error transaksi {$t['nomor_invoice']}: " . $e->getMessage() . "</div>";
                $total_error++;
            }
        }
        
        echo "<script>updateProgress(50, 'Menganalisis pembayaran piutang...');</script>";
        echo str_repeat(' ', 1024);
        flush();
        
        // STEP 2: Update pembayaran piutang
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
        
        echo "<div class='info'>💰 Memproses " . count($pembayaran_belum_mutasi) . " pembayaran piutang...</div>";
        
        foreach ($pembayaran_belum_mutasi as $index => $p) {
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
                
                echo "<div class='success'>✅ Pembayaran piutang {$p['nomor_invoice']} berhasil ditambahkan</div>";
                $total_success++;
                
                $progress = 50 + (($index + 1) / count($pembayaran_belum_mutasi)) * 30;
                echo "<script>updateProgress({$progress}, 'Memproses pembayaran " . ($index + 1) . "/" . count($pembayaran_belum_mutasi) . "');</script>";
                echo str_repeat(' ', 1024);
                flush();
                
            } catch (Exception $e) {
                echo "<div class='error'>❌ Error pembayaran {$p['nomor_invoice']}: " . $e->getMessage() . "</div>";
                $total_error++;
            }
        }
        
        echo "<script>updateProgress(80, 'Recalculating saldo bank...');</script>";
        echo str_repeat(' ', 1024);
        flush();
        
        // STEP 3: Recalculate saldo bank
        $banks = $db->select("SELECT * FROM bank WHERE status = 'aktif' ORDER BY nama_bank");
        $current_month = date('n');
        $current_year = date('Y');
        
        echo "<div class='info'>🏦 Recalculating saldo untuk " . count($banks) . " bank...</div>";
        
        foreach ($banks as $index => $bank) {
            try {
                // Hitung ulang saldo
                $mutasi_masuk = $db->selectOne("
                    SELECT COALESCE(SUM(jumlah), 0) as total
                    FROM mutasi_kas 
                    WHERE bank_id = ? AND jenis_mutasi = 'masuk' 
                    AND MONTH(tanggal_mutasi) = ? AND YEAR(tanggal_mutasi) = ?
                ", [$bank['id'], $current_month, $current_year]);
                
                $mutasi_keluar = $db->selectOne("
                    SELECT COALESCE(SUM(jumlah), 0) as total
                    FROM mutasi_kas 
                    WHERE bank_id = ? AND jenis_mutasi = 'keluar' 
                    AND MONTH(tanggal_mutasi) = ? AND YEAR(tanggal_mutasi) = ?
                ", [$bank['id'], $current_month, $current_year]);
                
                // Saldo awal
                $prev_month = $current_month == 1 ? 12 : $current_month - 1;
                $prev_year = $current_month == 1 ? $current_year - 1 : $current_year;
                
                $saldo_awal = $db->selectOne("
                    SELECT saldo_akhir 
                    FROM saldo_bank 
                    WHERE bank_id = ? AND periode_bulan = ? AND periode_tahun = ?
                ", [$bank['id'], $prev_month, $prev_year]);
                
                $saldo_awal_value = $saldo_awal ? $saldo_awal['saldo_akhir'] : $bank['saldo_awal'];
                $saldo_akhir = $saldo_awal_value + $mutasi_masuk['total'] - $mutasi_keluar['total'];
                
                // Update atau insert saldo bank
                $existing = $db->selectOne("
                    SELECT id FROM saldo_bank 
                    WHERE bank_id = ? AND periode_bulan = ? AND periode_tahun = ?
                ", [$bank['id'], $current_month, $current_year]);
                
                if ($existing) {
                    $db->execute("
                        UPDATE saldo_bank SET 
                            saldo_masuk = ?, saldo_keluar = ?, saldo_akhir = ?, updated_at = NOW()
                        WHERE id = ?
                    ", [$mutasi_masuk['total'], $mutasi_keluar['total'], $saldo_akhir, $existing['id']]);
                } else {
                    $db->execute("
                        INSERT INTO saldo_bank (
                            bank_id, periode_bulan, periode_tahun, 
                            saldo_masuk, saldo_keluar, saldo_akhir, created_at, updated_at
                        ) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
                    ", [$bank['id'], $current_month, $current_year, $mutasi_masuk['total'], $mutasi_keluar['total'], $saldo_akhir]);
                }
                
                echo "<div class='success'>✅ {$bank['nama_bank']}: Saldo berhasil diupdate ke Rp " . number_format($saldo_akhir, 0, ',', '.') . "</div>";
                
                $progress = 80 + (($index + 1) / count($banks)) * 20;
                echo "<script>updateProgress({$progress}, 'Updating bank " . ($index + 1) . "/" . count($banks) . "');</script>";
                echo str_repeat(' ', 1024);
                flush();
                
            } catch (Exception $e) {
                echo "<div class='error'>❌ Error {$bank['nama_bank']}: " . $e->getMessage() . "</div>";
                $total_error++;
            }
        }
        
        $db->commit();
        
        echo "<script>updateProgress(100, 'Update selesai!');</script>";
        echo str_repeat(' ', 1024);
        flush();
        
        echo "<div class='step'>";
        echo "<h3>🎉 Update Berhasil Diselesaikan!</h3>";
        echo "<div class='success'>";
        echo "<h4>Ringkasan:</h4>";
        echo "<ul>";
        echo "<li>✅ {$total_success} transaksi berhasil diupdate</li>";
        echo "<li>❌ {$total_error} error</li>";
        echo "<li>🏦 " . count($banks) . " bank berhasil direcalculate</li>";
        echo "</ul>";
        echo "</div>";
        echo "</div>";
        
        echo "<div style='margin-top: 20px;'>";
        echo "<a href='saldo-bank.php' class='btn'>Lihat Saldo Bank</a>";
        echo "<a href='mutasi-kas.php' class='btn'>Lihat Mutasi Kas</a>";
        echo "<a href='transaksi.php' class='btn'>Lihat Transaksi</a>";
        echo "</div>";
        
    } catch (Exception $e) {
        $db->rollback();
        echo "<div class='error'>❌ Error saat update: " . $e->getMessage() . "</div>";
        echo "<script>updateProgress(0, 'Update gagal!');</script>";
    }
}

// Handle recalc only
if (isset($_POST['recalc_only'])) {
    echo "<div class='step'>";
    echo "<h3>🔄 Recalculate Saldo Bank</h3>";
    
    $db->beginTransaction();
    try {
        $banks = $db->select("SELECT * FROM bank WHERE status = 'aktif' ORDER BY nama_bank");
        $current_month = date('n');
        $current_year = date('Y');
        
        foreach ($banks as $bank) {
            // Hitung ulang saldo (sama seperti di atas)
            $mutasi_masuk = $db->selectOne("
                SELECT COALESCE(SUM(jumlah), 0) as total
                FROM mutasi_kas 
                WHERE bank_id = ? AND jenis_mutasi = 'masuk' 
                AND MONTH(tanggal_mutasi) = ? AND YEAR(tanggal_mutasi) = ?
            ", [$bank['id'], $current_month, $current_year]);
            
            $mutasi_keluar = $db->selectOne("
                SELECT COALESCE(SUM(jumlah), 0) as total
                FROM mutasi_kas 
                WHERE bank_id = ? AND jenis_mutasi = 'keluar' 
                AND MONTH(tanggal_mutasi) = ? AND YEAR(tanggal_mutasi) = ?
            ", [$bank['id'], $current_month, $current_year]);
            
            $prev_month = $current_month == 1 ? 12 : $current_month - 1;
            $prev_year = $current_month == 1 ? $current_year - 1 : $current_year;
            
            $saldo_awal = $db->selectOne("
                SELECT saldo_akhir 
                FROM saldo_bank 
                WHERE bank_id = ? AND periode_bulan = ? AND periode_tahun = ?
            ", [$bank['id'], $prev_month, $prev_year]);
            
            $saldo_awal_value = $saldo_awal ? $saldo_awal['saldo_akhir'] : $bank['saldo_awal'];
            $saldo_akhir = $saldo_awal_value + $mutasi_masuk['total'] - $mutasi_keluar['total'];
            
            $existing = $db->selectOne("
                SELECT id FROM saldo_bank 
                WHERE bank_id = ? AND periode_bulan = ? AND periode_tahun = ?
            ", [$bank['id'], $current_month, $current_year]);
            
            if ($existing) {
                $db->execute("
                    UPDATE saldo_bank SET 
                        saldo_masuk = ?, saldo_keluar = ?, saldo_akhir = ?, updated_at = NOW()
                    WHERE id = ?
                ", [$mutasi_masuk['total'], $mutasi_keluar['total'], $saldo_akhir, $existing['id']]);
            } else {
                $db->execute("
                    INSERT INTO saldo_bank (
                        bank_id, periode_bulan, periode_tahun, 
                        saldo_masuk, saldo_keluar, saldo_akhir, created_at, updated_at
                    ) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
                ", [$bank['id'], $current_month, $current_year, $mutasi_masuk['total'], $mutasi_keluar['total'], $saldo_akhir]);
            }
            
            echo "<div class='success'>✅ {$bank['nama_bank']}: Saldo berhasil diupdate</div>";
        }
        
        $db->commit();
        echo "<div class='success'>Recalculate saldo bank berhasil!</div>";
        
    } catch (Exception $e) {
        $db->rollback();
        echo "<div class='error'>Error: " . $e->getMessage() . "</div>";
    }
    
    echo "</div>";
}
?>