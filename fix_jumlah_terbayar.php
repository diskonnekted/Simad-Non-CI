<?php
require_once 'config/database.php';

echo "=== MEMPERBAIKI MASALAH KOLOM JUMLAH_TERBAYAR ===\n\n";

$db = getDatabase();

try {
    // Cek struktur tabel pembelian
    echo "1. Struktur tabel pembelian saat ini:\n";
    $result = $db->select('DESCRIBE pembelian');
    
    $has_jumlah_terbayar = false;
    foreach($result as $row) {
        echo "   {$row['Field']} | {$row['Type']} | {$row['Null']} | {$row['Key']}\n";
        if ($row['Field'] === 'jumlah_terbayar') {
            $has_jumlah_terbayar = true;
        }
    }
    
    if (!$has_jumlah_terbayar) {
        echo "\n❌ Kolom 'jumlah_terbayar' tidak ditemukan di tabel pembelian\n";
        echo "\n2. Menambahkan kolom jumlah_terbayar...\n";
        
        $db->execute("
            ALTER TABLE pembelian 
            ADD COLUMN jumlah_terbayar DECIMAL(15,2) DEFAULT 0 
            AFTER sisa_amount
        ");
        
        echo "✅ Kolom jumlah_terbayar berhasil ditambahkan\n";
        
        // Update jumlah_terbayar berdasarkan data pembayaran yang ada
        echo "\n3. Menghitung ulang jumlah_terbayar dari data pembayaran...\n";
        
        $pembelian_list = $db->select("
            SELECT id, total_amount 
            FROM pembelian 
            WHERE status_pembelian != 'draft'
        ");
        
        foreach ($pembelian_list as $pembelian) {
            $pembayaran = $db->select("
                SELECT COALESCE(SUM(jumlah_bayar), 0) as total_bayar
                FROM pembayaran_pembelian 
                WHERE pembelian_id = ?
            ", [$pembelian['id']]);
            
            $jumlah_terbayar = $pembayaran[0]['total_bayar'] ?? 0;
            
            $db->execute("
                UPDATE pembelian 
                SET jumlah_terbayar = ? 
                WHERE id = ?
            ", [$jumlah_terbayar, $pembelian['id']]);
            
            echo "   PO ID {$pembelian['id']}: Rp " . number_format($jumlah_terbayar, 0, ',', '.') . "\n";
        }
        
        echo "\n✅ Update jumlah_terbayar selesai\n";
        
    } else {
        echo "\n✅ Kolom 'jumlah_terbayar' sudah ada\n";
    }
    
    // Cek apakah ada trigger untuk update otomatis
    echo "\n4. Membuat trigger untuk update otomatis jumlah_terbayar...\n";
    
    // Drop trigger jika sudah ada
    try {
        $db->execute("DROP TRIGGER IF EXISTS update_jumlah_terbayar_after_payment");
    } catch (Exception $e) {
        // Ignore error jika trigger tidak ada
    }
    
    // Buat trigger baru
    $db->execute("
        CREATE TRIGGER update_jumlah_terbayar_after_payment
        AFTER INSERT ON pembayaran_pembelian
        FOR EACH ROW
        BEGIN
            UPDATE pembelian 
            SET jumlah_terbayar = (
                SELECT COALESCE(SUM(jumlah_bayar), 0) 
                FROM pembayaran_pembelian 
                WHERE pembelian_id = NEW.pembelian_id
            ),
            status_pembayaran = CASE 
                WHEN (
                    SELECT COALESCE(SUM(jumlah_bayar), 0) 
                    FROM pembayaran_pembelian 
                    WHERE pembelian_id = NEW.pembelian_id
                ) >= total_amount THEN 'lunas'
                WHEN (
                    SELECT COALESCE(SUM(jumlah_bayar), 0) 
                    FROM pembayaran_pembelian 
                    WHERE pembelian_id = NEW.pembelian_id
                ) > 0 THEN 'dp'
                ELSE 'belum_bayar'
            END
            WHERE id = NEW.pembelian_id;
        END
    ");
    
    echo "✅ Trigger update_jumlah_terbayar_after_payment berhasil dibuat\n";
    
    echo "\n=== PERBAIKAN SELESAI ===\n";
    echo "✅ Kolom jumlah_terbayar sudah tersedia\n";
    echo "✅ Data jumlah_terbayar sudah diupdate\n";
    echo "✅ Trigger otomatis sudah dibuat\n";
    echo "\n🌐 Silakan test kembali halaman pembelian-view.php\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>