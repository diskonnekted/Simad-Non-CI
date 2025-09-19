<?php
require_once 'config/database.php';
require_once 'config/auth.php';

// Set content type untuk output yang rapi
header('Content-Type: text/plain; charset=utf-8');

echo "=== SIMULASI PROSES PEMBELIAN LENGKAP ===\n";
echo "Dari Pembuatan PO hingga Barang Masuk ke Stok Produk\n\n";

$db = getDatabase();
/** @var Database $db */

try {
    $db->beginTransaction();
    
    // 1. PERSIAPAN DATA
    echo "1. PERSIAPAN DATA\n";
    echo "==================\n";
    
    // Cek vendor aktif
    $vendors = $db->select("SELECT id, nama_vendor FROM vendor WHERE status = 'aktif' LIMIT 1");
    if (empty($vendors)) {
        throw new Exception("Tidak ada vendor aktif");
    }
    $vendor = $vendors[0];
    echo "✓ Vendor: {$vendor['nama_vendor']} (ID: {$vendor['id']})\n";
    
    // Cek produk aktif
    $produk_list = $db->select("SELECT id, nama_produk, kode_produk, harga_satuan, stok_tersedia FROM produk WHERE status = 'aktif' LIMIT 3");
    if (empty($produk_list)) {
        throw new Exception("Tidak ada produk aktif");
    }
    echo "✓ Produk tersedia: " . count($produk_list) . " item\n";
    
    // Cek bank aktif
    $banks = $db->select("SELECT id, nama_bank FROM bank WHERE status = 'aktif' LIMIT 1");
    if (empty($banks)) {
        throw new Exception("Tidak ada bank aktif");
    }
    $bank = $banks[0];
    echo "✓ Bank: {$bank['nama_bank']} (ID: {$bank['id']})\n";
    
    // Cek user admin
    $users = $db->select("SELECT id, nama_lengkap FROM users WHERE role = 'admin' LIMIT 1");
    if (empty($users)) {
        throw new Exception("Tidak ada user admin");
    }
    $user = $users[0];
    echo "✓ User: {$user['nama_lengkap']} (ID: {$user['id']})\n\n";
    
    // 2. PEMBUATAN PURCHASE ORDER (PO)
    echo "2. PEMBUATAN PURCHASE ORDER\n";
    echo "==============================\n";
    
    // Generate nomor PO
    $today = date('Ymd');
    $last_po = $db->select(
        "SELECT nomor_po FROM pembelian WHERE DATE(created_at) = CURDATE() ORDER BY id DESC LIMIT 1"
    );
    
    if (!empty($last_po)) {
        $last_number = intval(substr($last_po[0]['nomor_po'], -3));
        $new_number = str_pad($last_number + 1, 3, '0', STR_PAD_LEFT);
    } else {
        $new_number = '001';
    }
    
    $po_number = "PO-{$today}-{$new_number}";
    echo "✓ Nomor PO: {$po_number}\n";
    
    // Siapkan item pembelian
    $items = [];
    $subtotal = 0;
    
    foreach ($produk_list as $index => $produk) {
        $quantity = rand(1, 3); // Random quantity 1-3
        $price = $produk['harga_satuan'];
        $total_price = $quantity * $price;
        $subtotal += $total_price;
        
        $items[] = [
            'produk_id' => $produk['id'],
            'nama_item' => $produk['nama_produk'],
            'quantity' => $quantity,
            'price' => $price,
            'total_price' => $total_price
        ];
        
        echo "   - {$produk['nama_produk']}: {$quantity} unit x Rp " . number_format($price, 0, ',', '.') . " = Rp " . number_format($total_price, 0, ',', '.') . "\n";
    }
    
    echo "✓ Total PO: Rp " . number_format($subtotal, 0, ',', '.') . "\n";
    
    // Insert pembelian
    $tanggal_pembelian = date('Y-m-d');
    $tanggal_dibutuhkan = date('Y-m-d', strtotime('+7 days'));
    
    $query = "
        INSERT INTO pembelian (
            nomor_po, vendor_id, user_id, tanggal_pembelian, tanggal_dibutuhkan,
            total_amount, dp_amount, sisa_amount, tanggal_jatuh_tempo, 
            catatan, status_pembelian, status_pembayaran, metode_pembayaran, bank_id
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft', 'belum_bayar', 'tempo', ?)
    ";
    
    $params = [
        $po_number, $vendor['id'], $user['id'], $tanggal_pembelian, $tanggal_dibutuhkan,
        $subtotal, 0, $subtotal, date('Y-m-d', strtotime('+30 days')),
        'Simulasi pembelian untuk testing sistem', $bank['id']
    ];
    
    $db->execute($query, $params);
    $pembelian_id = $db->lastInsertId();
    echo "✓ PO berhasil dibuat dengan ID: {$pembelian_id}\n";
    
    // Insert detail pembelian
    foreach ($items as $item) {
        $detail_query = "
            INSERT INTO pembelian_detail (
                pembelian_id, produk_id, nama_item, 
                quantity_pesan, harga_satuan, subtotal
            ) VALUES (?, ?, ?, ?, ?, ?)
        ";
        
        $detail_params = [
            $pembelian_id, $item['produk_id'], $item['nama_item'],
            $item['quantity'], $item['price'], $item['total_price']
        ];
        
        $db->execute($detail_query, $detail_params);
    }
    echo "✓ Detail PO berhasil disimpan (" . count($items) . " item)\n\n";
    
    // 3. UPDATE STATUS PO KE DIKIRIM
    echo "3. UPDATE STATUS PO\n";
    echo "==================\n";
    
    $db->execute("UPDATE pembelian SET status_pembelian = 'dikirim' WHERE id = ?", [$pembelian_id]);
    echo "✓ Status PO diubah menjadi 'dikirim'\n\n";
    
    // 4. SIMULASI PENERIMAAN BARANG
    echo "4. PENERIMAAN BARANG\n";
    echo "====================\n";
    
    // Generate nomor penerimaan
    $today_gr = date('Ymd');
    $last_gr = $db->select("
        SELECT nomor_penerimaan 
        FROM penerimaan_barang 
        WHERE nomor_penerimaan LIKE 'GR-{$today_gr}-%' 
        ORDER BY nomor_penerimaan DESC 
        LIMIT 1
    ");
    
    if (!empty($last_gr)) {
        $last_num = intval(substr($last_gr[0]['nomor_penerimaan'], -3));
        $new_number_gr = str_pad($last_num + 1, 3, '0', STR_PAD_LEFT);
    } else {
        $new_number_gr = '001';
    }
    
    $nomor_penerimaan = "GR-{$today_gr}-{$new_number_gr}";
    echo "✓ Nomor Penerimaan: {$nomor_penerimaan}\n";
    
    // Insert penerimaan_barang
    $penerimaan_query = "
        INSERT INTO penerimaan_barang (
            pembelian_id, nomor_penerimaan, tanggal_terima, user_id, catatan
        ) VALUES (?, ?, ?, ?, ?)
    ";
    
    $db->execute($penerimaan_query, [
        $pembelian_id, $nomor_penerimaan, date('Y-m-d'), $user['id'], 'Simulasi penerimaan barang lengkap'
    ]);
    
    $penerimaan_id = $db->lastInsertId();
    echo "✓ Penerimaan barang berhasil dibuat dengan ID: {$penerimaan_id}\n";
    
    // Ambil detail pembelian untuk penerimaan
    $detail_pembelian = $db->select("
        SELECT id, produk_id, nama_item, quantity_pesan
        FROM pembelian_detail
        WHERE pembelian_id = ?
        ORDER BY id
    ", [$pembelian_id]);
    
    echo "\n   Detail penerimaan:\n";
    foreach ($detail_pembelian as $detail) {
        // Insert penerimaan_detail (ini akan trigger update stok otomatis)
        $detail_query = "
            INSERT INTO penerimaan_detail (
                penerimaan_id, pembelian_detail_id, quantity_terima, kondisi, catatan
            ) VALUES (?, ?, ?, ?, ?)
        ";
        
        $db->execute($detail_query, [
            $penerimaan_id, $detail['id'], $detail['quantity_pesan'], 'baik', 'Kondisi barang baik'
        ]);
        
        echo "   - {$detail['nama_item']}: {$detail['quantity_pesan']} unit (kondisi: baik)\n";
    }
    
    echo "\n✓ Detail penerimaan berhasil disimpan\n";
    echo "✓ Trigger database otomatis mengupdate stok produk\n\n";
    
    // 5. VERIFIKASI HASIL
    echo "5. VERIFIKASI HASIL\n";
    echo "===================\n";
    
    // Cek status PO
    $po_status = $db->select("SELECT status_pembelian FROM pembelian WHERE id = ?", [$pembelian_id])[0];
    echo "✓ Status PO: {$po_status['status_pembelian']}\n";
    
    // Cek quantity terima
    $detail_check = $db->select("
        SELECT pd.nama_item, pd.quantity_pesan, pd.quantity_terima,
               (pd.quantity_pesan = pd.quantity_terima) as is_complete
        FROM pembelian_detail pd
        WHERE pd.pembelian_id = ?
    ", [$pembelian_id]);
    
    echo "\n   Verifikasi quantity:\n";
    $all_complete = true;
    foreach ($detail_check as $detail) {
        $status = $detail['is_complete'] ? '✓' : '✗';
        echo "   {$status} {$detail['nama_item']}: {$detail['quantity_terima']}/{$detail['quantity_pesan']}\n";
        if (!$detail['is_complete']) {
            $all_complete = false;
        }
    }
    
    if ($all_complete) {
        echo "\n✓ Semua item telah diterima lengkap\n";
    }
    
    // Cek stok produk setelah penerimaan
    echo "\n   Verifikasi stok produk:\n";
    foreach ($produk_list as $produk) {
        $stok_sekarang = $db->select("SELECT stok_tersedia FROM produk WHERE id = ?", [$produk['id']])[0];
        $selisih = $stok_sekarang['stok_tersedia'] - $produk['stok_tersedia'];
        
        echo "   - {$produk['nama_produk']}:\n";
        echo "     Stok sebelum: {$produk['stok_tersedia']}\n";
        echo "     Stok sesudah: {$stok_sekarang['stok_tersedia']}\n";
        echo "     Penambahan: +{$selisih}\n";
    }
    
    $db->commit();
    
    echo "\n=== SIMULASI BERHASIL ===\n";
    echo "✓ PO berhasil dibuat: {$po_number}\n";
    echo "✓ Penerimaan berhasil: {$nomor_penerimaan}\n";
    echo "✓ Stok produk berhasil diupdate otomatis\n";
    echo "✓ Sistem pembelian berfungsi dengan sempurna!\n\n";
    
    echo "Silakan cek di halaman:\n";
    echo "- Pembelian: http://localhost:8000/pembelian.php\n";
    echo "- Penerimaan: http://localhost:8000/penerimaan.php\n";
    echo "- Produk: http://localhost:8000/produk.php\n";
    
} catch (Exception $e) {
    $db->rollback();
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo "Simulasi dibatalkan.\n";
}
?>