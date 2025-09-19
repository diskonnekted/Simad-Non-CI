<?php
require_once 'config/database.php';
require_once 'config/auth.php';

// Cek autentikasi dan role
if (!AuthStatic::isLoggedIn()) {
    header('Location: login.php');
    exit;
}

if (!AuthStatic::hasRole(['admin', 'sales'])) {
    header('Location: index.php?error=access_denied');
    exit;
}

$user = AuthStatic::getCurrentUser();
$db = getDatabase();
/** @var Database $db */

$error = '';
$success = '';

// Ambil desa yang dipilih dari parameter (jika ada)
$selected_desa_id = $_GET['desa_id'] ?? '';

// Ambil daftar desa aktif
$desa_list = $db->select("
    SELECT id, nama_desa, kecamatan, kabupaten, nama_kepala_desa, no_hp_kepala_desa, nama_sekdes, no_hp_sekdes, nama_admin_it, no_hp_admin_it
    FROM desa 
    WHERE status = 'aktif' 
    ORDER BY nama_desa
");

// Ambil daftar produk dan layanan
$produk_list = $db->select("
    SELECT id, nama_produk, kategori_id, harga_satuan as harga, stok_tersedia as stok, satuan
    FROM produk 
    WHERE status = 'aktif' 
    ORDER BY nama_produk
");

$layanan_list = $db->select("
    SELECT id, nama_layanan, harga as harga_per_unit, deskripsi
    FROM layanan 
    WHERE status = 'aktif' 
    ORDER BY nama_layanan
");

// Ambil kategori produk
$kategori_list = $db->select("
    SELECT id, nama_kategori 
    FROM kategori_produk 
    ORDER BY nama_kategori
");

// Ambil daftar bank aktif
$bank_list = $db->select("
    SELECT id, kode_bank, nama_bank, jenis_bank 
    FROM bank 
    WHERE status = 'aktif' 
    ORDER BY jenis_bank, nama_bank
");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $desa_id = $_POST['desa_id'] ?? '';
    $payment_type = $_POST['payment_type'] ?? 'tunai';
    $bank_id = $_POST['bank_id'] ?? null;
    $dp_amount = floatval($_POST['dp_amount'] ?? 0);
    $tempo_days = intval($_POST['tempo_days'] ?? 0);
    $catatan = trim($_POST['catatan'] ?? '');
    $items = $_POST['items'] ?? [];
    
    // Validasi
    if (empty($desa_id)) {
        $error = 'Desa harus dipilih';
    } elseif (empty($items)) {
        $error = 'Minimal harus ada 1 item transaksi';
    } elseif ($payment_type === 'dp' && $dp_amount <= 0) {
        $error = 'Jumlah DP harus lebih dari 0';
    } elseif ($payment_type === 'tempo' && $tempo_days <= 0) {
        $error = 'Jangka waktu tempo harus lebih dari 0 hari';
    } else {
        try {
            $db->beginTransaction();
            
            // Generate invoice number
            $today = date('Ymd');
            $last_invoice = $db->select(
                "SELECT nomor_invoice FROM transaksi WHERE DATE(created_at) = CURDATE() ORDER BY id DESC LIMIT 1"
            );
            
            if (!empty($last_invoice)) {
                $last_number = intval(substr($last_invoice[0]['nomor_invoice'], -3));
                $new_number = str_pad($last_number + 1, 3, '0', STR_PAD_LEFT);
            } else {
                $new_number = '001';
            }
            
            $invoice_number = "INV-{$today}-{$new_number}";
            
            // Hitung total
            $subtotal = 0;
            $valid_items = [];
            
            foreach ($items as $item) {
                $type = $item['type'] ?? '';
                $item_id = intval($item['item_id'] ?? 0);
                $quantity = floatval($item['quantity'] ?? 0);
                $price = floatval($item['price'] ?? 0);
                
                if ($type && $item_id && $quantity > 0 && $price > 0) {
                    // Validasi item exists
                    if ($type === 'produk') {
                        $check = $db->select("SELECT id, nama_produk, stok_tersedia as stok FROM produk WHERE id = ? AND status = 'aktif'", [$item_id]);
                        if (empty($check)) continue;
                        
                        // Cek stok
                        if ($check[0]['stok'] < $quantity) {
                            $error = "Stok {$check[0]['nama_produk']} tidak mencukupi (tersedia: {$check[0]['stok']})";
                            break;
                        }
                        
                        $item_name = $check[0]['nama_produk'];
                    } else {
                        $check = $db->select("SELECT id, nama_layanan FROM layanan WHERE id = ? AND status = 'aktif'", [$item_id]);
                        if (empty($check)) continue;
                        
                        $item_name = $check[0]['nama_layanan'];
                    }
                    
                    $total_price = $quantity * $price;
                    $subtotal += $total_price;
                    
                    $valid_items[] = [
                        'type' => $type,
                        'item_id' => $item_id,
                        'item_name' => $item_name,
                        'quantity' => $quantity,
                        'price' => $price,
                        'total_price' => $total_price
                    ];
                }
            }
            
            if ($error) {
                $db->rollback();
            } elseif (empty($valid_items)) {
                $error = 'Tidak ada item valid untuk diproses';
                $db->rollback();
            } else {
                // Hitung sisa amount
                $sisa_amount = $payment_type === 'dp' ? $subtotal - $dp_amount : ($payment_type === 'tempo' ? $subtotal : 0);
                
                // Tentukan status pembayaran
                $status_pembayaran = $payment_type === 'tunai' ? 'lunas' : ($payment_type === 'dp' ? 'dp' : 'belum_bayar');
                
                // Insert transaksi
                $query = "
                    INSERT INTO transaksi (
                        nomor_invoice, desa_id, user_id, tanggal_transaksi, jenis_transaksi,
                        metode_pembayaran, bank_id, total_amount, dp_amount, sisa_amount, tanggal_jatuh_tempo, 
                        catatan, status_transaksi, status_pembayaran
                    ) VALUES (?, ?, ?, CURDATE(), 'campuran', ?, ?, ?, ?, ?, ?, ?, 'draft', ?)
                ";
                
                $params = [
                    $invoice_number, $desa_id, $user['id'], 
                    $payment_type === 'dp' ? 'dp_pelunasan' : ($payment_type === 'tempo' ? 'tempo' : 'tunai'),
                    $bank_id, $subtotal, $dp_amount, $sisa_amount,
                    $payment_type === 'tempo' ? date('Y-m-d', strtotime("+{$tempo_days} days")) : null, 
                    $catatan, $status_pembayaran
                ];
                
                $db->execute($query, $params);
                $transaksi_id = $db->lastInsertId();
                
                // Insert detail transaksi
                foreach ($valid_items as $item) {
                    if ($item['type'] === 'produk') {
                        $detail_query = "
                            INSERT INTO transaksi_detail (
                                transaksi_id, produk_id, nama_item, 
                                quantity, harga_satuan, subtotal
                            ) VALUES (?, ?, ?, ?, ?, ?)
                        ";
                        
                        $detail_params = [
                            $transaksi_id, $item['item_id'], $item['item_name'],
                            $item['quantity'], $item['price'], $item['total_price']
                        ];
                    } else {
                        $detail_query = "
                            INSERT INTO transaksi_detail (
                                transaksi_id, layanan_id, nama_item, 
                                quantity, harga_satuan, subtotal
                            ) VALUES (?, ?, ?, ?, ?, ?)
                        ";
                        
                        $detail_params = [
                            $transaksi_id, $item['item_id'], $item['item_name'],
                            $item['quantity'], $item['price'], $item['total_price']
                        ];
                    }
                    
                    $db->execute($detail_query, $detail_params);
                    
                    // Update stok produk
                    if ($item['type'] === 'produk') {
                        $db->execute(
                            "UPDATE produk SET stok_tersedia = stok_tersedia - ? WHERE id = ?",
                            [$item['quantity'], $item['item_id']]
                        );
                    }
                }
                
                // Buat piutang jika perlu
                if (($payment_type === 'dp' || $payment_type === 'tempo') && !empty($desa_id)) {
                    $jumlah_piutang = $payment_type === 'dp' ? ($subtotal - $dp_amount) : $subtotal;
                    $tanggal_jatuh_tempo = $payment_type === 'tempo' ? date('Y-m-d', strtotime("+{$tempo_days} days")) : date('Y-m-d', strtotime('+30 days'));
                    
                    $piutang_query = "
                        INSERT INTO piutang (
                            transaksi_id, desa_id, jumlah_piutang, tanggal_jatuh_tempo, status
                        ) VALUES (?, ?, ?, ?, 'belum_jatuh_tempo')
                    ";
                    
                    $piutang_params = [
                        $transaksi_id, $desa_id, $jumlah_piutang, $tanggal_jatuh_tempo
                    ];
                    
                    $db->execute($piutang_query, $piutang_params);
                }
                
                // Catat mutasi kas masuk untuk penjualan (jika tunai atau DP)
                if ($payment_type === 'tunai' || ($payment_type === 'dp' && $dp_amount > 0)) {
                    $jumlah_masuk = $payment_type === 'tunai' ? $subtotal : $dp_amount;
                    
                    $mutasi_query = "
                        INSERT INTO mutasi_kas (
                            bank_id, jenis_mutasi, jenis_transaksi, referensi_id, referensi_tabel,
                            jumlah, keterangan, tanggal_mutasi, user_id
                        ) VALUES (?, 'masuk', 'penjualan', ?, 'transaksi', ?, ?, ?, ?)
                    ";
                    
                    $keterangan = "Penjualan {$invoice_number}";
                    if ($payment_type === 'dp') {
                        $keterangan .= " (DP: Rp " . number_format($dp_amount, 0, ',', '.') . ")";
                    }
                    
                    $mutasi_params = [
                        $bank_id, $transaksi_id, $jumlah_masuk, $keterangan, date('Y-m-d'), $user['id']
                    ];
                    
                    $db->execute($mutasi_query, $mutasi_params);
                }
                
                $db->commit();
                
                header("Location: transaksi-view.php?id={$transaksi_id}&success=created");
                exit;
            }
        } catch (Exception $e) {
            $db->rollback();
            $error = 'Gagal membuat transaksi: ' . $e->getMessage();
        }
    }
}
?>
<?php
$page_title = 'Buat Transaksi';
require_once 'layouts/header.php';
?>
    <!-- Page Header -->
    <div class="bg-white shadow-sm border-b border-gray-200 mb-6">
        <div class="px-6 py-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Buat Transaksi Baru</h1>
                    <p class="text-sm text-gray-600 mt-1">Tambahkan transaksi untuk desa</p>
                </div>
                <div class="mt-4 sm:mt-0 flex space-x-2">
                    <a href="transaksi.php" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 transition-colors">
                        <i class="fas fa-arrow-left mr-2"></i>
                        Kembali
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Container -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <?php if ($error): ?>
            <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-exclamation-triangle text-red-400"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-red-800"><?= htmlspecialchars($error) ?></p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <form method="POST" id="transaksiForm" class="space-y-6">
            <!-- Informasi Desa -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <div class="flex items-center mb-6">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-map-marker-alt text-blue-600"></i>
                        </div>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-lg font-medium text-gray-900">Informasi Desa</h3>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div>
                        <label for="desa_id" class="block text-sm font-medium text-gray-700 mb-2">
                            Pilih Desa <span class="text-red-500">*</span>
                        </label>
                        <select id="desa_id" name="desa_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500" required>
                            <option value="">-- Pilih Desa --</option>
                            <?php foreach ($desa_list as $desa): ?>
                            <option value="<?= $desa['id'] ?>" 
                                    <?= $selected_desa_id == $desa['id'] ? 'selected' : '' ?>
                                    data-kontak="<?= htmlspecialchars($desa['nama_kepala_desa'] ?? '') ?>"
                                    data-telepon="<?= htmlspecialchars($desa['no_hp_kepala_desa'] ?? '') ?>">
                                <?= htmlspecialchars($desa['nama_desa']) ?> - <?= htmlspecialchars($desa['kecamatan']) ?>, <?= htmlspecialchars($desa['kabupaten']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <div id="desa-info" class="bg-gray-50 p-4 rounded-lg hidden">
                            <h5 class="font-medium text-gray-900 mb-3">Informasi Kontak</h5>
                            <p class="text-sm text-gray-600 mb-2"><strong>Kontak Person:</strong> <span id="kontak-person">-</span></p>
                            <p class="text-sm text-gray-600"><strong>Telepon:</strong> <span id="telepon">-</span></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Item Transaksi -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                        <i class="fa fa-list mr-2 text-primary-600"></i> Item Transaksi
                    </h3>
                    <button type="button" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors duration-200 flex items-center" onclick="addItem()">
                        <i class="fa fa-plus mr-2"></i> Tambah Item
                    </button>
                </div>
                
                <div id="items-container" class="mb-6">
                    <!-- Items will be added here dynamically -->
                </div>
                
                <div class="border-t border-gray-200 pt-4">
                    <div class="flex justify-between items-center">
                        <div>
                            <h4 class="text-lg font-medium text-gray-900">Total Items: <span id="total-items" class="text-primary-600">0</span></h4>
                        </div>
                        <div>
                            <h3 class="text-xl font-semibold text-gray-900">Subtotal: <span id="subtotal" class="text-primary-600">Rp 0</span></h3>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Metode Pembayaran -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
                <h3 class="text-lg font-semibold text-gray-900 flex items-center mb-6">
                    <i class="fa fa-money mr-2 text-primary-600"></i> Metode Pembayaran
                </h3>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="border border-gray-200 rounded-lg p-4 hover:border-primary-300 transition-colors duration-200">
                        <label class="flex items-start cursor-pointer">
                            <input type="radio" name="payment_type" value="tunai" checked onchange="togglePaymentOptions()" class="mt-1 mr-3 text-primary-600 focus:ring-primary-500">
                            <div>
                                <div class="font-medium text-gray-900">Tunai</div>
                                <p class="text-sm text-gray-500 mt-1">Pembayaran langsung lunas</p>
                            </div>
                        </label>
                    </div>
                    <div class="border border-gray-200 rounded-lg p-4 hover:border-primary-300 transition-colors duration-200">
                        <label class="flex items-start cursor-pointer">
                            <input type="radio" name="payment_type" value="dp" onchange="togglePaymentOptions()" class="mt-1 mr-3 text-primary-600 focus:ring-primary-500">
                            <div>
                                <div class="font-medium text-gray-900">DP (Down Payment)</div>
                                <p class="text-sm text-gray-500 mt-1">Bayar sebagian, sisanya piutang</p>
                            </div>
                        </label>
                    </div>
                    <div class="border border-gray-200 rounded-lg p-4 hover:border-primary-300 transition-colors duration-200">
                        <label class="flex items-start cursor-pointer">
                            <input type="radio" name="payment_type" value="tempo" onchange="togglePaymentOptions()" class="mt-1 mr-3 text-primary-600 focus:ring-primary-500">
                            <div>
                                <div class="font-medium text-gray-900">Tempo</div>
                                <p class="text-sm text-gray-500 mt-1">Bayar nanti dengan jatuh tempo</p>
                            </div>
                        </label>
                    </div>
                </div>
                
                <!-- Pilihan Bank -->
                <div class="mt-6">
                    <label for="bank_id" class="block text-sm font-medium text-gray-700 mb-2">
                        Pilih Bank <span class="text-red-500">*</span>
                    </label>
                    <select id="bank_id" name="bank_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500" required>
                        <option value="">-- Pilih Bank --</option>
                        <?php foreach ($bank_list as $bank): ?>
                            <option value="<?= $bank['id'] ?>" <?= (($_POST['bank_id'] ?? '') == $bank['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($bank['nama_bank']) ?> 
                                <?php if ($bank['jenis_bank'] === 'cash'): ?>
                                    <span class="text-green-600">(Cash)</span>
                                <?php elseif ($bank['jenis_bank'] === 'bkk'): ?>
                                    <span class="text-blue-600">(BKK)</span>
                                <?php else: ?>
                                    <span class="text-purple-600">(Bank Umum)</span>
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="text-sm text-gray-500 mt-1">Pilih bank atau metode pembayaran yang akan digunakan</p>
                </div>
                
                <div class="mt-6">
                    <div id="dp-options" class="hidden">
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                            <label for="dp_amount" class="block text-sm font-medium text-gray-700 mb-2">
                                Jumlah DP <span class="text-red-500">*</span>
                            </label>
                            <input type="number" id="dp_amount" name="dp_amount" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500" 
                                   placeholder="Masukkan jumlah DP" min="0" step="1000" onchange="calculateSisaHutang()">
                            <div class="mt-3 p-3 bg-white border border-blue-300 rounded-lg">
                                <div class="flex justify-between items-center text-sm">
                                    <span class="text-gray-600">Total Transaksi:</span>
                                    <span id="dp-total-transaksi" class="font-medium text-gray-900">Rp 0</span>
                                </div>
                                <div class="flex justify-between items-center text-sm mt-1">
                                    <span class="text-gray-600">Jumlah DP:</span>
                                    <span id="dp-jumlah-dp" class="font-medium text-blue-600">Rp 0</span>
                                </div>
                                <div class="border-t border-gray-200 mt-2 pt-2">
                                    <div class="flex justify-between items-center">
                                        <span class="text-sm font-medium text-gray-700">Sisa Hutang:</span>
                                        <span id="dp-sisa-hutang" class="font-bold text-red-600">Rp 0</span>
                                    </div>
                                </div>
                            </div>
                            <p class="text-sm text-gray-500 mt-2">Sisa akan menjadi piutang yang harus dibayar</p>
                        </div>
                    </div>
                    
                    <div id="tempo-options" class="hidden">
                        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                            <label for="tempo_days" class="block text-sm font-medium text-gray-700 mb-2">
                                Jangka Waktu Tempo (Hari) <span class="text-red-500">*</span>
                            </label>
                            <select id="tempo_days" name="tempo_days" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                                <option value="">-- Pilih Jangka Waktu --</option>
                                <option value="7">7 Hari</option>
                                <option value="14">14 Hari</option>
                                <option value="30">30 Hari</option>
                                <option value="60">60 Hari</option>
                                <option value="90">90 Hari</option>
                            </select>
                            <div class="mt-3 p-3 bg-white border border-yellow-300 rounded-lg">
                                <div class="flex justify-between items-center text-sm">
                                    <span class="text-gray-600">Total Transaksi:</span>
                                    <span id="tempo-total-transaksi" class="font-medium text-gray-900">Rp 0</span>
                                </div>
                                <div class="border-t border-gray-200 mt-2 pt-2">
                                    <div class="flex justify-between items-center">
                                        <span class="text-sm font-medium text-gray-700">Total Hutang:</span>
                                        <span id="tempo-total-hutang" class="font-bold text-red-600">Rp 0</span>
                                    </div>
                                </div>
                            </div>
                            <p class="text-sm text-gray-500 mt-2">Jatuh tempo: <span id="jatuh-tempo" class="font-medium">-</span></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Catatan -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
                <h3 class="text-lg font-semibold text-gray-900 flex items-center mb-6">
                    <i class="fa fa-sticky-note mr-2 text-primary-600"></i> Catatan Transaksi
                </h3>
                
                <div>
                    <label for="catatan" class="block text-sm font-medium text-gray-700 mb-2">Catatan</label>
                    <textarea id="catatan" name="catatan" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500" rows="4" 
                              placeholder="Catatan tambahan untuk transaksi ini (opsional)"><?= htmlspecialchars($_POST['catatan'] ?? '') ?></textarea>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <div class="flex flex-wrap gap-4">
                    <button type="submit" class="bg-primary-600 hover:bg-primary-700 text-white px-6 py-3 rounded-lg font-medium transition-colors duration-200 flex items-center">
                        <i class="fa fa-save mr-2"></i> Simpan Transaksi
                    </button>
                    <a href="transaksi.php" class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-3 rounded-lg font-medium transition-colors duration-200 flex items-center text-decoration-none">
                        <i class="fa fa-arrow-left mr-2"></i> Kembali ke Daftar
                    </a>
                    <button type="button" class="bg-yellow-500 hover:bg-yellow-600 text-white px-6 py-3 rounded-lg font-medium transition-colors duration-200 flex items-center" onclick="resetForm()">
                        <i class="fa fa-refresh mr-2"></i> Reset Form
                    </button>
                </div>
            </div>
                </form>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script src="js/jquery.min.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script>
        let itemCounter = 0;
        
        // Data produk dan layanan
        const produkData = <?= json_encode($produk_list) ?>;
        const layananData = <?= json_encode($layanan_list) ?>;
        
        // Show desa info when selected
        $('#desa_id').change(function() {
            const selected = $(this).find(':selected');
            if (selected.val()) {
                const kontak = selected.data('kontak') || '-';
                const telepon = selected.data('telepon') || '-';
                $('#kontak-person').text(kontak);
                $('#telepon').text(telepon);
                $('#desa-info').removeClass('hidden');
            } else {
                $('#desa-info').addClass('hidden');
            }
        });
        
        // Add item function
        function addItem() {
            itemCounter++;
            const itemHtml = `
                <div class="item-row border border-gray-200 rounded-lg p-4 mb-4" id="item-${itemCounter}">
                    <div class="grid grid-cols-1 md:grid-cols-6 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Tipe Item</label>
                            <select name="items[${itemCounter}][type]" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500 item-type" onchange="loadItems(${itemCounter})" required>
                                <option value="">-- Pilih --</option>
                                <option value="produk">Produk</option>
                                <option value="layanan">Layanan</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Item</label>
                            <select name="items[${itemCounter}][item_id]" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500 item-select" onchange="setPrice(${itemCounter})" required>
                                <option value="">-- Pilih Item --</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Quantity</label>
                            <input type="number" name="items[${itemCounter}][quantity]" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500 item-quantity" 
                                   min="1" step="0.01" onchange="calculateItemTotal(${itemCounter})" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Harga Satuan</label>
                            <input type="number" name="items[${itemCounter}][price]" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500 item-price" 
                                   min="0" step="1000" onchange="calculateItemTotal(${itemCounter})" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Total</label>
                            <div class="w-full px-3 py-2 bg-gray-50 border border-gray-300 rounded-lg text-gray-900 item-total" id="total-${itemCounter}">Rp 0</div>
                        </div>
                        <div class="flex items-end">
                            <button type="button" class="bg-red-500 hover:bg-red-600 text-white px-3 py-2 rounded-lg transition-colors duration-200" onclick="removeItem(${itemCounter})">
                                <i class="fa fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            `;
            
            $('#items-container').append(itemHtml);
        }
        
        // Load items based on type
        function loadItems(counter) {
            const type = $(`select[name="items[${counter}][type]"]`).val();
            const itemSelect = $(`select[name="items[${counter}][item_id]"]`);
            
            itemSelect.empty().append('<option value="">-- Pilih Item --</option>');
            
            if (type === 'produk') {
                produkData.forEach(item => {
                    const stok = item.stok || 0;
                    const satuan = item.satuan || 'unit';
                    const harga = item.harga || 0;
                    const nama = item.nama_produk || 'Produk';
                    
                    itemSelect.append(`<option value="${item.id}" data-price="${harga}" data-stok="${stok}">
                        ${nama} (Stok: ${stok} ${satuan})
                    </option>`);
                });
            } else if (type === 'layanan') {
                layananData.forEach(item => {
                    const harga = item.harga_per_unit || 0;
                    const nama = item.nama_layanan || 'Layanan';
                    const deskripsi = item.deskripsi || '';
                    
                    itemSelect.append(`<option value="${item.id}" data-price="${harga}">
                        ${nama} ${deskripsi ? '(' + deskripsi + ')' : ''}
                    </option>`);
                });
            }
        }
        
        // Set price when item selected
        function setPrice(counter) {
            const selected = $(`select[name="items[${counter}][item_id]"] :selected`);
            const price = selected.data('price') || 0;
            $(`input[name="items[${counter}][price]"]`).val(price);
            calculateItemTotal(counter);
        }
        
        // Calculate item total
        function calculateItemTotal(counter) {
            const quantity = parseFloat($(`input[name="items[${counter}][quantity]"]`).val()) || 0;
            const price = parseFloat($(`input[name="items[${counter}][price]"]`).val()) || 0;
            const total = quantity * price;
            
            $(`#total-${counter}`).text(formatRupiah(total));
            calculateSubtotal();
        }
        
        // Remove item
        function removeItem(counter) {
            $(`#item-${counter}`).remove();
            calculateSubtotal();
        }
        
        // Calculate subtotal
        function calculateSubtotal() {
            let subtotal = 0;
            let itemCount = 0;
            
            $('.item-row').each(function() {
                const quantity = parseFloat($(this).find('input[name*="[quantity]"]').val()) || 0;
                const price = parseFloat($(this).find('input[name*="[price]"]').val()) || 0;
                const itemId = $(this).find('select[name*="[item_id]"]').val();
                
                if (quantity > 0 && price > 0 && itemId) {
                    subtotal += quantity * price;
                    itemCount++;
                }
            });
            
            $('#subtotal').text(formatRupiah(subtotal));
            $('#total-items').text(itemCount);
            
            // Update payment info
            updatePaymentInfo(subtotal);
        }
        
        // Update payment information
        function updatePaymentInfo(subtotal) {
            // Update DP section
            $('#dp-total-transaksi').text(formatRupiah(subtotal));
            $('#tempo-total-transaksi').text(formatRupiah(subtotal));
            $('#tempo-total-hutang').text(formatRupiah(subtotal));
            
            // Calculate sisa hutang for DP
            calculateSisaHutang();
        }
        
        // Calculate sisa hutang for DP
        function calculateSisaHutang() {
            const subtotal = getCurrentSubtotal();
            const dpAmount = parseFloat($('#dp_amount').val()) || 0;
            const sisaHutang = subtotal - dpAmount;
            
            $('#dp-jumlah-dp').text(formatRupiah(dpAmount));
            $('#dp-sisa-hutang').text(formatRupiah(Math.max(0, sisaHutang)));
            
            // Validate DP amount
            if (dpAmount > subtotal) {
                $('#dp_amount').addClass('border-red-500');
                $('#dp-sisa-hutang').text('DP tidak boleh melebihi total transaksi').addClass('text-red-500');
            } else {
                $('#dp_amount').removeClass('border-red-500');
                $('#dp-sisa-hutang').removeClass('text-red-500').addClass('font-bold text-red-600');
            }
        }
        
        // Get current subtotal
        function getCurrentSubtotal() {
            let subtotal = 0;
            
            $('.item-row').each(function() {
                const quantity = parseFloat($(this).find('input[name*="[quantity]"]').val()) || 0;
                const price = parseFloat($(this).find('input[name*="[price]"]').val()) || 0;
                const itemId = $(this).find('select[name*="[item_id]"]').val();
                
                if (quantity > 0 && price > 0 && itemId) {
                    subtotal += quantity * price;
                }
            });
            
            return subtotal;
        }
        
        // Toggle payment options
        function togglePaymentOptions() {
            const paymentType = $('input[name="payment_type"]:checked').val();
            
            $('#dp-options, #tempo-options').addClass('hidden');
            
            if (paymentType === 'dp') {
                $('#dp-options').removeClass('hidden');
                updatePaymentInfo(getCurrentSubtotal());
            } else if (paymentType === 'tempo') {
                $('#tempo-options').removeClass('hidden');
                updatePaymentInfo(getCurrentSubtotal());
            }
        }
        
        // Calculate jatuh tempo
        $('#tempo_days').change(function() {
            const days = parseInt($(this).val());
            if (days) {
                const jatuhTempo = new Date();
                jatuhTempo.setDate(jatuhTempo.getDate() + days);
                $('#jatuh-tempo').text(jatuhTempo.toLocaleDateString('id-ID'));
            } else {
                $('#jatuh-tempo').text('-');
            }
        });
        
        // Format rupiah
        function formatRupiah(amount) {
            return 'Rp ' + amount.toLocaleString('id-ID');
        }
        
        // Reset form
        function resetForm() {
            if (confirm('Apakah Anda yakin ingin mereset form?')) {
                $('#transaksiForm')[0].reset();
                $('#items-container').empty();
                $('#desa-info').addClass('hidden');
                itemCounter = 0;
                calculateSubtotal();
            }
        }
        
        // Initialize
        $(document).ready(function() {
            // Trigger desa info if pre-selected
            if ($('#desa_id').val()) {
                $('#desa_id').trigger('change');
            }
            
            // Add first item automatically
            addItem();
            
            // Initialize payment options
            togglePaymentOptions();
        });
    </script>

<?php include 'layouts/footer.php'; ?>
