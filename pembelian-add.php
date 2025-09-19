<?php
require_once 'config/database.php';
require_once 'config/auth.php';

// Cek autentikasi dan role
if (!AuthStatic::isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Cek role akses
if (!AuthStatic::hasRole(['admin', 'akunting', 'supervisor'])) {
    header('Location: index.php?error=access_denied');
    exit;
}

$user = AuthStatic::getCurrentUser();
$db = getDatabase();
/** @var Database $db */

$error = '';
$success = '';

// Ambil daftar vendor aktif
$vendor_list = $db->select("SELECT id, nama_vendor, kode_vendor FROM vendor WHERE status = 'aktif' ORDER BY nama_vendor");

// Ambil daftar produk aktif
$produk_list = $db->select("SELECT id, nama_produk, kode_produk, harga_satuan FROM produk WHERE status = 'aktif' ORDER BY nama_produk");

// Ambil daftar bank aktif
$bank_list = $db->select("SELECT id, nama_bank, jenis_bank FROM bank WHERE status = 'aktif' ORDER BY nama_bank");

// Ambil daftar desa aktif
$desa_list = $db->select("SELECT id, nama_desa FROM desa ORDER BY nama_desa");

// Proses form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $vendor_id = intval($_POST['vendor_id'] ?? 0);
    $desa_id = intval($_POST['desa_id'] ?? 0);
    $tanggal_pembelian = $_POST['tanggal_pembelian'] ?? date('Y-m-d');
    $tanggal_dibutuhkan = $_POST['tanggal_dibutuhkan'] ?? '';
    $payment_type = $_POST['payment_type'] ?? 'tempo';
    $dp_amount = floatval($_POST['dp_amount'] ?? 0);
    $tempo_days = intval($_POST['tempo_days'] ?? 30);
    $bank_id = intval($_POST['bank_id'] ?? 0);
    $catatan = trim($_POST['catatan'] ?? '');
    $items = $_POST['items'] ?? [];
    
    // Validasi
    if (empty($vendor_id)) {
        $error = 'Vendor harus dipilih';
    } elseif (empty($tanggal_pembelian)) {
        $error = 'Tanggal pembelian harus diisi';
    } elseif (empty($bank_id)) {
        $error = 'Bank harus dipilih';
    } elseif (empty($items)) {
        $error = 'Minimal harus ada 1 item pembelian';
    } elseif ($payment_type === 'dp' && $dp_amount <= 0) {
        $error = 'Jumlah DP harus lebih dari 0';
    } elseif ($payment_type === 'tempo' && $tempo_days <= 0) {
        $error = 'Jangka waktu tempo harus lebih dari 0 hari';
    } else {
        try {
            $db->beginTransaction();
            
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
            
            // Hitung total
            $subtotal = 0;
            $valid_items = [];
            
            foreach ($items as $item) {
                $produk_id = intval($item['produk_id'] ?? 0);
                $quantity = intval($item['quantity'] ?? 0);
                $price = floatval($item['price'] ?? 0);
                
                if ($produk_id > 0 && $quantity > 0 && $price > 0) {
                    // Ambil data produk
                    $produk = $db->select("SELECT nama_produk FROM produk WHERE id = ?", [$produk_id]);
                    
                    if (!empty($produk)) {
                        $total_price = $quantity * $price;
                        $subtotal += $total_price;
                        
                        $valid_items[] = [
                            'produk_id' => $produk_id,
                            'nama_item' => $produk[0]['nama_produk'],
                            'quantity' => $quantity,
                            'price' => $price,
                            'total_price' => $total_price
                        ];
                    }
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
                
                // Tentukan tanggal jatuh tempo
                $tanggal_jatuh_tempo = null;
                if ($payment_type === 'tempo') {
                    $tanggal_jatuh_tempo = date('Y-m-d', strtotime($tanggal_pembelian . " +{$tempo_days} days"));
                } elseif ($payment_type === 'dp') {
                    $tanggal_jatuh_tempo = date('Y-m-d', strtotime($tanggal_pembelian . ' +30 days'));
                }
                
                // Insert pembelian
                $query = "
                    INSERT INTO pembelian (
                        nomor_po, vendor_id, desa_id, user_id, tanggal_pembelian, tanggal_dibutuhkan,
                        total_amount, dp_amount, sisa_amount, tanggal_jatuh_tempo, 
                        catatan, status_pembelian, status_pembayaran, metode_pembayaran, bank_id
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft', ?, ?, ?)
                ";
                
                $params = [
                    $po_number, $vendor_id, $desa_id ?: null, $user['id'], $tanggal_pembelian, 
                    !empty($tanggal_dibutuhkan) ? $tanggal_dibutuhkan : null,
                    $subtotal, $dp_amount, $sisa_amount, $tanggal_jatuh_tempo,
                    $catatan, $status_pembayaran, $payment_type, $bank_id
                ];
                
                $db->execute($query, $params);
                $pembelian_id = $db->lastInsertId();
                
                // Insert detail pembelian
                foreach ($valid_items as $item) {
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
                
                // Buat hutang jika perlu
                if ($payment_type === 'dp' || $payment_type === 'tempo') {
                    $jumlah_hutang = $payment_type === 'dp' ? ($subtotal - $dp_amount) : $subtotal;
                    
                    $hutang_query = "
                        INSERT INTO hutang (
                            pembelian_id, vendor_id, jumlah_hutang, tanggal_jatuh_tempo, status
                        ) VALUES (?, ?, ?, ?, 'belum_lunas')
                    ";
                    
                    $hutang_params = [
                        $pembelian_id, $vendor_id, $jumlah_hutang, $tanggal_jatuh_tempo
                    ];
                    
                    $db->execute($hutang_query, $hutang_params);
                }
                
                // Catat mutasi kas keluar untuk pembelian (jika tunai atau DP)
                if ($payment_type === 'tunai' || ($payment_type === 'dp' && $dp_amount > 0)) {
                    $jumlah_keluar = $payment_type === 'tunai' ? $subtotal : $dp_amount;
                    
                    $mutasi_query = "
                        INSERT INTO mutasi_kas (
                            bank_id, jenis_mutasi, jenis_transaksi, referensi_id, referensi_tabel,
                            jumlah, keterangan, tanggal_mutasi, user_id
                        ) VALUES (?, 'keluar', 'pembelian', ?, 'pembelian', ?, ?, ?, ?)
                    ";
                    
                    $keterangan = "Pembayaran pembelian {$po_number}";
                    if ($payment_type === 'dp') {
                        $keterangan .= " (DP: Rp " . number_format($dp_amount, 0, ',', '.') . ")";
                    }
                    
                    $mutasi_params = [
                        $bank_id, $pembelian_id, $jumlah_keluar, $keterangan, $tanggal_pembelian, $user['id']
                    ];
                    
                    $db->execute($mutasi_query, $mutasi_params);
                }
                
                $db->commit();
                
                header("Location: pembelian-view.php?id={$pembelian_id}&success=created");
                exit;
            }
        } catch (Exception $e) {
            $db->rollback();
            $error = 'Gagal membuat purchase order: ' . $e->getMessage();
        }
    }
}

$page_title = 'Buat Purchase Order';
require_once 'layouts/header.php';
?>
    <!-- Page Header -->
    <div class="max-w-7xl ml-0 px-4 sm:px-6 lg:px-8 mb-8">
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Buat Purchase Order</h1>
                    <p class="text-gray-600 mt-1">Tambahkan pembelian baru dari vendor</p>
                </div>
                <div class="flex space-x-3">
                    <a href="pembelian.php" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 transition-colors">
                        <i class="fas fa-arrow-left mr-2"></i>
                        Kembali
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Container -->
    <div class="max-w-7xl ml-0 px-4 sm:px-6 lg:px-8 py-8">
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

        <form method="POST" id="pembelianForm" class="space-y-8">
            <!-- Informasi Vendor -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
                <div class="flex items-center mb-6">
                    <div class="flex-shrink-0">
                        <i class="fa fa-building text-primary-600 text-xl"></i>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-lg font-semibold text-gray-900">Informasi Vendor</h3>
                        <p class="text-sm text-gray-600">Pilih vendor dan atur tanggal pembelian</p>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div>
                        <label for="vendor_id" class="block text-sm font-medium text-gray-700 mb-2">Vendor <span class="text-red-500">*</span></label>
                        <select name="vendor_id" id="vendor_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                            <option value="">Pilih Vendor</option>
                            <?php foreach ($vendor_list as $vendor): ?>
                            <option value="<?= $vendor['id'] ?>" <?= ($_POST['vendor_id'] ?? '') == $vendor['id'] ? 'selected' : '' ?>
                                    data-kode="<?= htmlspecialchars($vendor['kode_vendor']) ?>"
                                    data-nama="<?= htmlspecialchars($vendor['nama_vendor']) ?>">
                                <?= htmlspecialchars($vendor['nama_vendor']) ?> (<?= htmlspecialchars($vendor['kode_vendor']) ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <div id="vendor-info" class="bg-gray-50 p-4 rounded-lg hidden">
                            <h5 class="font-medium text-gray-900 mb-3">Informasi Vendor</h5>
                            <p class="text-sm text-gray-600 mb-2"><strong>Kode:</strong> <span id="vendor-kode">-</span></p>
                            <p class="text-sm text-gray-600"><strong>Nama:</strong> <span id="vendor-nama">-</span></p>
                        </div>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mt-6">
                    <div>
                        <label for="desa_id" class="block text-sm font-medium text-gray-700 mb-2">Desa Pemesan</label>
                        <select name="desa_id" id="desa_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                            <option value="0">Belum Ada</option>
                            <?php foreach ($desa_list as $desa): ?>
                            <option value="<?= $desa['id'] ?>" <?= ($_POST['desa_id'] ?? '') == $desa['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($desa['nama_desa']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="tanggal_pembelian" class="block text-sm font-medium text-gray-700 mb-2">Tanggal Pembelian <span class="text-red-500">*</span></label>
                        <input type="date" name="tanggal_pembelian" id="tanggal_pembelian" required 
                               value="<?= htmlspecialchars($_POST['tanggal_pembelian'] ?? date('Y-m-d')) ?>"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                    </div>
                    <div>
                        <label for="tanggal_dibutuhkan" class="block text-sm font-medium text-gray-700 mb-2">Tanggal Dibutuhkan</label>
                        <input type="date" name="tanggal_dibutuhkan" id="tanggal_dibutuhkan" 
                               value="<?= htmlspecialchars($_POST['tanggal_dibutuhkan'] ?? '') ?>"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                    </div>
                </div>
            </div>

            <!-- Item Pembelian -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                        <i class="fa fa-list mr-2 text-primary-600"></i> Item Pembelian
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
                            <input type="radio" name="payment_type" value="tunai" class="mt-1 mr-3 text-primary-600 focus:ring-primary-500" <?= ($_POST['payment_type'] ?? 'tempo') === 'tunai' ? 'checked' : '' ?>>
                            <div>
                                <div class="font-medium text-gray-900">Tunai</div>
                                <p class="text-sm text-gray-500 mt-1">Pembayaran langsung lunas</p>
                            </div>
                        </label>
                    </div>
                    
                    <div class="border border-gray-200 rounded-lg p-4 hover:border-primary-300 transition-colors duration-200">
                        <label class="flex items-start cursor-pointer">
                            <input type="radio" name="payment_type" value="dp" class="mt-1 mr-3 text-primary-600 focus:ring-primary-500" <?= ($_POST['payment_type'] ?? 'tempo') === 'dp' ? 'checked' : '' ?>>
                            <div>
                                <div class="font-medium text-gray-900">DP (Down Payment)</div>
                                <p class="text-sm text-gray-500 mt-1">Bayar sebagian, sisanya piutang</p>
                            </div>
                        </label>
                    </div>
                    
                    <div class="border border-gray-200 rounded-lg p-4 hover:border-primary-300 transition-colors duration-200">
                        <label class="flex items-start cursor-pointer">
                            <input type="radio" name="payment_type" value="tempo" class="mt-1 mr-3 text-primary-600 focus:ring-primary-500" <?= ($_POST['payment_type'] ?? 'tempo') === 'tempo' ? 'checked' : '' ?>>
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
                    <select name="bank_id" id="bank_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        <option value="">-- Pilih Bank --</option>
                        <?php foreach ($bank_list as $bank): ?>
                            <option value="<?= $bank['id'] ?>" <?= ($_POST['bank_id'] ?? '') == $bank['id'] ? 'selected' : '' ?>>
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
                    <div id="dp-options" class="<?= ($_POST['payment_type'] ?? 'tempo') === 'dp' ? '' : 'hidden' ?>">
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                            <label for="dp_amount" class="block text-sm font-medium text-gray-700 mb-2">
                                Jumlah DP <span class="text-red-500">*</span>
                            </label>
                            <input type="number" id="dp_amount" name="dp_amount" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500" 
                                   placeholder="Masukkan jumlah DP" min="0" step="1000" value="<?= htmlspecialchars($_POST['dp_amount'] ?? '') ?>">
                            <p class="text-sm text-gray-500 mt-2">Sisa akan menjadi piutang yang harus dibayar</p>
                        </div>
                    </div>
                    
                    <div id="tempo-options" class="<?= ($_POST['payment_type'] ?? 'tempo') === 'tempo' ? '' : 'hidden' ?>">
                        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                            <label for="tempo_days" class="block text-sm font-medium text-gray-700 mb-2">
                                Jangka Waktu Tempo (Hari) <span class="text-red-500">*</span>
                            </label>
                            <select id="tempo_days" name="tempo_days" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                                <option value="">-- Pilih Jangka Waktu --</option>
                                <option value="7" <?= ($_POST['tempo_days'] ?? '30') == '7' ? 'selected' : '' ?>>7 Hari</option>
                                <option value="14" <?= ($_POST['tempo_days'] ?? '30') == '14' ? 'selected' : '' ?>>14 Hari</option>
                                <option value="30" <?= ($_POST['tempo_days'] ?? '30') == '30' ? 'selected' : '' ?>>30 Hari</option>
                                <option value="60" <?= ($_POST['tempo_days'] ?? '30') == '60' ? 'selected' : '' ?>>60 Hari</option>
                                <option value="90" <?= ($_POST['tempo_days'] ?? '30') == '90' ? 'selected' : '' ?>>90 Hari</option>
                            </select>
                            <p class="text-sm text-gray-500 mt-2">Pilih jangka waktu pembayaran tempo</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Catatan -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
                <h3 class="text-lg font-semibold text-gray-900 flex items-center mb-4">
                    <i class="fa fa-sticky-note mr-2 text-primary-600"></i> Catatan
                </h3>
                <div>
                    <label for="catatan" class="block text-sm font-medium text-gray-700 mb-2">Catatan Tambahan</label>
                    <textarea name="catatan" id="catatan" rows="3" 
                              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                              placeholder="Catatan tambahan untuk purchase order ini (opsional)"><?= htmlspecialchars($_POST['catatan'] ?? '') ?></textarea>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <div class="flex flex-wrap gap-4">
                    <button type="submit" class="bg-primary-600 hover:bg-primary-700 text-white px-6 py-3 rounded-lg font-medium transition-colors duration-200 flex items-center">
                        <i class="fa fa-save mr-2"></i> Simpan Purchase Order
                    </button>
                    <a href="pembelian.php" class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-3 rounded-lg font-medium transition-colors duration-200 flex items-center text-decoration-none">
                        <i class="fa fa-arrow-left mr-2"></i> Kembali ke Daftar
                    </a>
                    <button type="button" class="bg-yellow-500 hover:bg-yellow-600 text-white px-6 py-3 rounded-lg font-medium transition-colors duration-200 flex items-center" onclick="resetForm()">
                        <i class="fa fa-refresh mr-2"></i> Reset Form
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- JavaScript -->
    <script src="js/jquery.min.js"></script>
    <script>
    let itemCounter = 0;
    const produkData = <?= json_encode($produk_list) ?>;
    
    // Vendor selection handler
    $('#vendor_id').change(function() {
        const vendorId = $(this).val();
        if (vendorId) {
            const selectedOption = $(this).find('option:selected');
            const vendorKode = selectedOption.data('kode');
            const vendorNama = selectedOption.data('nama');
            
            if (vendorKode && vendorNama) {
                $('#vendor-kode').text(vendorKode);
                $('#vendor-nama').text(vendorNama);
                $('#vendor-info').removeClass('hidden');
            }
        } else {
            $('#vendor-info').addClass('hidden');
        }
    });
    
    // Initialize vendor info on page load if vendor is already selected
    $(document).ready(function() {
        const selectedVendor = $('#vendor_id').val();
        if (selectedVendor) {
            $('#vendor_id').trigger('change');
        }
    });
    
    // Payment type handlers
    $('input[name="payment_type"]').change(function() {
        const paymentType = $(this).val();
        
        $('#dp-options').toggleClass('hidden', paymentType !== 'dp');
        $('#tempo-options').toggleClass('hidden', paymentType !== 'tempo');
        
        if (paymentType === 'dp') {
            $('#dp_amount').attr('required', true);
            $('#tempo_days').removeAttr('required');
        } else if (paymentType === 'tempo') {
            $('#tempo_days').attr('required', true);
            $('#dp_amount').removeAttr('required');
        } else {
            $('#dp_amount').removeAttr('required');
            $('#tempo_days').removeAttr('required');
        }
    });
    
    // Initialize payment options on page load
    $(document).ready(function() {
        const selectedPaymentType = $('input[name="payment_type"]:checked').val();
        if (selectedPaymentType) {
            $('#dp-options').toggleClass('hidden', selectedPaymentType !== 'dp');
            $('#tempo-options').toggleClass('hidden', selectedPaymentType !== 'tempo');
        }
    });
    
    function addItem() {
        itemCounter++;
        const itemHtml = `
            <div class="item-row bg-gray-50 p-4 rounded-lg border border-gray-200" data-item="${itemCounter}">
                <div class="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Produk</label>
                        <select name="items[${itemCounter}][produk_id]" class="produk-select w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500" required>
                            <option value="">Pilih Produk</option>
                            ${produkData.map(p => `<option value="${p.id}" data-price="${p.harga_satuan}">${p.nama_produk} (${p.kode_produk})</option>`).join('')}
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Quantity</label>
                        <input type="number" name="items[${itemCounter}][quantity]" class="quantity-input w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500" min="1" step="1" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Harga Satuan</label>
                        <input type="number" name="items[${itemCounter}][price]" class="price-input w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500" min="0" step="0.01" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Total</label>
                        <input type="text" class="total-display w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-100" readonly value="Rp 0">
                    </div>
                    <div>
                        <button type="button" class="bg-red-500 hover:bg-red-600 text-white px-3 py-2 rounded-lg" onclick="removeItem(${itemCounter})">
                            <i class="fa fa-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
        `;
        
        $('#items-container').append(itemHtml);
        updateCalculations();
    }
    
    function removeItem(itemId) {
        $(`.item-row[data-item="${itemId}"]`).remove();
        updateCalculations();
    }
    
    function updateCalculations() {
        let totalItems = 0;
        let subtotal = 0;
        
        $('.item-row').each(function() {
            const quantity = parseFloat($(this).find('.quantity-input').val()) || 0;
            const price = parseFloat($(this).find('.price-input').val()) || 0;
            const total = quantity * price;
            
            $(this).find('.total-display').val('Rp ' + total.toLocaleString('id-ID'));
            
            if (quantity > 0 && price > 0) {
                totalItems++;
                subtotal += total;
            }
        });
        
        $('#total-items').text(totalItems);
        $('#subtotal').text('Rp ' + subtotal.toLocaleString('id-ID'));
    }
    
    // Event handlers
    $(document).on('change', '.produk-select', function() {
        const selectedOption = $(this).find('option:selected');
        const price = selectedOption.data('price') || 0;
        $(this).closest('.item-row').find('.price-input').val(price);
        updateCalculations();
    });
    
    $(document).on('input', '.quantity-input, .price-input', function() {
        updateCalculations();
    });
    
    function resetForm() {
        if (confirm('Apakah Anda yakin ingin mereset form? Semua data yang telah diisi akan hilang.')) {
            $('#pembelianForm')[0].reset();
            $('#items-container').empty();
            $('#vendor-info').addClass('hidden');
            $('#dp-amount-container').addClass('hidden');
            $('#tempo-days-container').removeClass('hidden');
            itemCounter = 0;
            updateCalculations();
        }
    }
    
    // Initialize
    $(document).ready(function() {
        // Trigger vendor change if already selected
        if ($('#vendor_id').val()) {
            $('#vendor_id').trigger('change');
        }
        
        // Trigger payment type change if already selected
        $('input[name="payment_type"]:checked').trigger('change');
        
        // Add first item
        addItem();
    });
    </script>

<?php require_once 'layouts/footer.php'; ?>