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

echo "<h2>Recalculate Saldo Bank</h2>";
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
    .btn-danger { background: #dc3545; }
    .btn-danger:hover { background: #c82333; }
</style>";

try {
    // 1. Analisis saldo bank saat ini vs perhitungan dari mutasi kas
    echo "<h3>1. Analisis Saldo Bank</h3>";
    
    $banks = $db->select("SELECT * FROM bank WHERE status = 'aktif' ORDER BY nama_bank");
    $current_month = date('n');
    $current_year = date('Y');
    
    echo "<table>";
    echo "<tr><th>Bank</th><th>Saldo Tercatat</th><th>Saldo Seharusnya</th><th>Selisih</th><th>Status</th></tr>";
    
    $total_selisih = 0;
    $banks_need_update = [];
    
    foreach ($banks as $bank) {
        // Ambil saldo tercatat
        $saldo_tercatat = $db->selectOne("
            SELECT saldo_akhir 
            FROM saldo_bank 
            WHERE bank_id = ? AND periode_bulan = ? AND periode_tahun = ?
        ", [$bank['id'], $current_month, $current_year]);
        
        $saldo_tercatat_value = $saldo_tercatat ? $saldo_tercatat['saldo_akhir'] : 0;
        
        // Hitung saldo seharusnya dari mutasi kas
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
        
        // Ambil saldo awal (dari bulan sebelumnya atau saldo_awal)
        $prev_month = $current_month == 1 ? 12 : $current_month - 1;
        $prev_year = $current_month == 1 ? $current_year - 1 : $current_year;
        
        $saldo_awal = $db->selectOne("
            SELECT saldo_akhir 
            FROM saldo_bank 
            WHERE bank_id = ? AND periode_bulan = ? AND periode_tahun = ?
        ", [$bank['id'], $prev_month, $prev_year]);
        
        $saldo_awal_value = $saldo_awal ? $saldo_awal['saldo_akhir'] : $bank['saldo_awal'];
        
        $saldo_seharusnya = $saldo_awal_value + $mutasi_masuk['total'] - $mutasi_keluar['total'];
        $selisih = $saldo_tercatat_value - $saldo_seharusnya;
        
        $status = $selisih == 0 ? "✅ OK" : "❌ Tidak Sinkron";
        $status_class = $selisih == 0 ? "success" : "error";
        
        echo "<tr>";
        echo "<td>{$bank['nama_bank']}</td>";
        echo "<td>Rp " . number_format($saldo_tercatat_value, 0, ',', '.') . "</td>";
        echo "<td>Rp " . number_format($saldo_seharusnya, 0, ',', '.') . "</td>";
        echo "<td class='{$status_class}'>Rp " . number_format($selisih, 0, ',', '.') . "</td>";
        echo "<td class='{$status_class}'>{$status}</td>";
        echo "</tr>";
        
        if ($selisih != 0) {
            $banks_need_update[] = [
                'bank' => $bank,
                'saldo_tercatat' => $saldo_tercatat_value,
                'saldo_seharusnya' => $saldo_seharusnya,
                'saldo_masuk' => $mutasi_masuk['total'],
                'saldo_keluar' => $mutasi_keluar['total'],
                'selisih' => $selisih
            ];
            $total_selisih += abs($selisih);
        }
    }
    
    echo "</table>";
    
    if ($total_selisih > 0) {
        echo "<div class='warning'>Total selisih: Rp " . number_format($total_selisih, 0, ',', '.') . "</div>";
    } else {
        echo "<div class='success'>Semua saldo bank sudah sinkron!</div>";
    }
    
    // 2. Detail mutasi kas per bank
    echo "<h3>2. Detail Mutasi Kas Bulan Ini</h3>";
    
    foreach ($banks as $bank) {
        echo "<h4>{$bank['nama_bank']}</h4>";
        
        $mutasi_detail = $db->select("
            SELECT jenis_mutasi, jenis_transaksi, jumlah, keterangan, tanggal_mutasi,
                   referensi_id, referensi_tabel
            FROM mutasi_kas 
            WHERE bank_id = ? 
            AND MONTH(tanggal_mutasi) = ? AND YEAR(tanggal_mutasi) = ?
            ORDER BY tanggal_mutasi DESC, id DESC
            LIMIT 10
        ", [$bank['id'], $current_month, $current_year]);
        
        if (!empty($mutasi_detail)) {
            echo "<table style='font-size: 12px;'>";
            echo "<tr><th>Tanggal</th><th>Jenis</th><th>Transaksi</th><th>Jumlah</th><th>Keterangan</th></tr>";
            
            foreach ($mutasi_detail as $m) {
                $jenis_class = $m['jenis_mutasi'] == 'masuk' ? 'success' : 'error';
                $jumlah_display = ($m['jenis_mutasi'] == 'masuk' ? '+' : '-') . 'Rp ' . number_format($m['jumlah'], 0, ',', '.');
                
                echo "<tr>";
                echo "<td>" . date('d/m/Y', strtotime($m['tanggal_mutasi'])) . "</td>";
                echo "<td>{$m['jenis_mutasi']}</td>";
                echo "<td>{$m['jenis_transaksi']}</td>";
                echo "<td class='{$jenis_class}'>{$jumlah_display}</td>";
                echo "<td>{$m['keterangan']}</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<div class='info'>Tidak ada mutasi kas untuk bank ini bulan ini</div>";
        }
    }
    
    // 3. Form untuk recalculate
    if (!empty($banks_need_update)) {
        echo "<h3>3. Perbaikan Saldo Bank</h3>";
        echo "<form method='POST'>";
        echo "<button type='submit' name='recalculate_saldo' class='btn' onclick='return confirm(\"Apakah Anda yakin ingin recalculate saldo bank?\")'>Recalculate Saldo Bank</button>";
        echo "<button type='submit' name='reset_saldo' class='btn btn-danger' onclick='return confirm(\"PERINGATAN: Ini akan mereset semua saldo bank berdasarkan mutasi kas. Lanjutkan?\")'>Reset Semua Saldo Bank</button>";
        echo "</form>";
    }
    
    // 4. Proses recalculate
    if (isset($_POST['recalculate_saldo']) || isset($_POST['reset_saldo'])) {
        echo "<h3>4. Proses Recalculate Saldo Bank</h3>";
        
        $db->beginTransaction();
        $success_count = 0;
        $error_count = 0;
        
        try {
            $banks_to_process = isset($_POST['reset_saldo']) ? $banks : $banks_need_update;
            
            foreach ($banks_to_process as $bank_data) {
                $bank = isset($bank_data['bank']) ? $bank_data['bank'] : $bank_data;
                
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
                    $success_count++;
                    
                } catch (Exception $e) {
                    echo "<div class='error'>❌ Error {$bank['nama_bank']}: " . $e->getMessage() . "</div>";
                    $error_count++;
                }
            }
            
            $db->commit();
            echo "<div class='success'><strong>Recalculate selesai: {$success_count} berhasil, {$error_count} error</strong></div>";
            echo "<div class='info'>Halaman akan refresh dalam 3 detik...</div>";
            echo "<script>setTimeout(function(){ window.location.reload(); }, 3000);</script>";
            
        } catch (Exception $e) {
            $db->rollback();
            echo "<div class='error'>❌ Error saat recalculate: " . $e->getMessage() . "</div>";
        }
    }
    
} catch (Exception $e) {
    echo "<div class='error'>Error: " . $e->getMessage() . "</div>";
}
?>

<div style="margin-top: 30px;">
    <a href="saldo-bank.php" class="btn">Lihat Saldo Bank</a>
    <a href="mutasi-kas.php" class="btn">Lihat Mutasi Kas</a>
    <a href="update_transaksi_mutasi.php" class="btn">Update Transaksi</a>
</div>