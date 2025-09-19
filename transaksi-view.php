<?php
require_once 'config/database.php';
require_once 'config/auth.php';

// Cek autentikasi
if (!AuthStatic::isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$user = AuthStatic::getCurrentUser();
$db = getDatabase();

$error = '';
$success = $_GET['success'] ?? '';
$transaksi_id = $_GET['id'] ?? '';

if (empty($transaksi_id)) {
    header('Location: transaksi.php?error=invalid_id');
    exit;
}

// Ambil data transaksi
$transaksi = $db->select("
    SELECT t.*, d.nama_desa, d.kecamatan, d.kabupaten, d.nama_kepala_desa, d.no_hp_kepala_desa, d.nama_sekdes, d.no_hp_sekdes, d.nama_admin_it, d.no_hp_admin_it,
           u.nama_lengkap as sales_name, u.email as sales_email
    FROM transaksi t
    JOIN desa d ON t.desa_id = d.id
    JOIN users u ON t.user_id = u.id
    WHERE t.id = ?
", [$transaksi_id]);

if (empty($transaksi)) {
    header('Location: transaksi.php?error=not_found');
    exit;
}

$transaksi = $transaksi[0];

// Cek akses berdasarkan role
// Cek akses
if (!AuthStatic::hasRole(['admin', 'finance']) && $user['id'] != $transaksi['user_id']) {
    header('Location: transaksi.php?error=access_denied');
    exit;
}

// Get bank list for payment form
$bank_list = $db->select("SELECT * FROM bank WHERE status = 'aktif' ORDER BY nama_bank");

// Ambil detail transaksi
$detail_transaksi = $db->select("
    SELECT * FROM transaksi_detail 
    WHERE transaksi_id = ? 
    ORDER BY id
", [$transaksi_id]);

// Ambil data piutang jika ada
$piutang = $db->select("
    SELECT * FROM piutang 
    WHERE transaksi_id = ? AND status = 'aktif'
", [$transaksi_id]);

$piutang = !empty($piutang) ? $piutang[0] : null;

// Ambil riwayat pembayaran piutang
$pembayaran_piutang = [];
if ($piutang) {
    $pembayaran_piutang = $db->select("
        SELECT pp.*, u.nama_lengkap as user_name
        FROM pembayaran_piutang pp
        JOIN users u ON pp.user_id = u.id
        WHERE pp.piutang_id = ?
        ORDER BY pp.created_at DESC
    ", [$piutang['id']]);
}

// Process pembayaran piutang
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bayar_piutang'])) {
    if (!AuthStatic::hasRole(['admin', 'finance'])) {
        $error = 'Anda tidak memiliki akses untuk memproses pembayaran';
    } else {
        $jumlah_bayar = floatval($_POST['jumlah_bayar'] ?? 0);
        $metode_bayar = $_POST['metode_bayar'] ?? 'tunai';
        $keterangan = trim($_POST['keterangan'] ?? '');
        
        if ($jumlah_bayar <= 0) {
            $error = 'Jumlah pembayaran harus lebih dari 0';
        } elseif ($jumlah_bayar > $piutang['sisa_piutang']) {
            $error = 'Jumlah pembayaran tidak boleh melebihi sisa piutang';
        } else {
            try {
                $db->beginTransaction();
                
                // Insert pembayaran
                $db->execute("
                    INSERT INTO pembayaran_piutang (
                        piutang_id, jumlah_bayar, metode_bayar, keterangan, user_id
                    ) VALUES (?, ?, ?, ?, ?)
                ", [$piutang['id'], $jumlah_bayar, $metode_bayar, $keterangan, $user['id']]);
                
                // Update sisa piutang
                $sisa_baru = $piutang['sisa_piutang'] - $jumlah_bayar;
                $status_piutang = $sisa_baru <= 0 ? 'lunas' : 'aktif';
                
                $db->execute("
                    UPDATE piutang 
                    SET sisa_piutang = ?, status = ?, updated_at = NOW()
                    WHERE id = ?
                ", [$sisa_baru, $status_piutang, $piutang['id']]);
                
                // Update status transaksi jika lunas
                if ($sisa_baru <= 0) {
                    $db->execute("
                        UPDATE transaksi 
                        SET status_transaksi = 'selesai', updated_at = NOW()
                        WHERE id = ?
                    ", [$transaksi_id]);
                }
                
                // Catat mutasi kas masuk untuk pembayaran piutang
                $bank_id = $_POST['bank_id'] ?? null;
                $db->execute("
                    INSERT INTO mutasi_kas (
                        bank_id, jenis_mutasi, jenis_transaksi, referensi_id, referensi_tabel,
                        jumlah, keterangan, tanggal_mutasi, user_id
                    ) VALUES (?, 'masuk', 'pembayaran_piutang', ?, 'piutang', ?, ?, ?, ?)
                ", [
                    $bank_id, $piutang['id'], $jumlah_bayar, 
                    "Pembayaran piutang transaksi {$transaksi['nomor_invoice']}", 
                    date('Y-m-d'), $user['id']
                ]);
                
                $db->commit();
                
                header("Location: transaksi-view.php?id={$transaksi_id}&success=payment_added");
                exit;
            } catch (Exception $e) {
                $db->rollback();
                $error = 'Gagal memproses pembayaran: ' . $e->getMessage();
            }
        }
    }
}

// Helper functions
function getStatusBadge($status) {
    $badges = [
        'draft' => 'warning',
        'diproses' => 'info',
        'dikirim' => 'primary',
        'selesai' => 'success'
    ];
    return $badges[$status] ?? 'default';
}

function getStatusText($status) {
    $texts = [
        'draft' => 'Draft',
        'diproses' => 'Diproses',
        'dikirim' => 'Dikirim',
        'selesai' => 'Selesai'
    ];
    return $texts[$status] ?? $status;
}

function getPaymentTypeText($type) {
    $texts = [
        'tunai' => 'Tunai',
        'dp' => 'DP (Down Payment)',
        'tempo' => 'Tempo'
    ];
    return $texts[$type] ?? $type;
}

function formatRupiah($angka) {
    return 'Rp ' . number_format($angka, 0, ',', '.');
}
?>
<?php 
$page_title = 'Detail Transaksi';
require_once 'layouts/header.php'; 
?>

<style>
    @media print {
        .no-print {
            display: none !important;
        }
    }
</style>

<!-- Loading Overlay -->
<div id="loading-overlay" class="fixed inset-0 bg-white bg-opacity-90 hidden items-center justify-center z-50">
    <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-primary-600"></div>
</div>

    <!-- Page Header -->
    <div class="mb-8">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Detail Transaksi</h1>
                <p class="text-sm text-gray-600 mt-1">Informasi lengkap transaksi <?= htmlspecialchars($transaksi['nomor_invoice']) ?></p>
            </div>
            <div class="mt-4 sm:mt-0 flex space-x-2">
                <?php if ($transaksi['status_transaksi'] !== 'selesai' && $transaksi['status_transaksi'] !== 'dibatalkan' && (AuthStatic::hasRole(['admin']) || $user['id'] == $transaksi['user_id'])): ?>
                <a href="transaksi-edit.php?id=<?= $transaksi_id ?>" class="inline-flex items-center px-4 py-2 bg-yellow-600 border border-transparent rounded-lg text-sm font-medium text-white hover:bg-yellow-700 focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:ring-offset-2 transition-colors">
                    <i class="fas fa-edit mr-2"></i>
                    Edit Transaksi
                </a>
                <a href="transaksi-cancel.php?id=<?= $transaksi_id ?>" class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-lg text-sm font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition-colors" onclick="return confirm('Apakah Anda yakin ingin membatalkan transaksi ini?')">
                    <i class="fas fa-times mr-2"></i>
                    Batalkan
                </a>
                <?php endif; ?>
                <button onclick="window.print()" class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-lg text-sm font-medium text-white hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition-colors">
                    <i class="fas fa-print mr-2"></i>
                    Cetak
                </button>
                <a href="transaksi.php" class="inline-flex items-center px-4 py-2 bg-primary-600 border border-transparent rounded-lg text-sm font-medium text-white hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 transition-colors">
                    <i class="fas fa-list mr-2"></i>
                    Daftar Transaksi
                </a>
            </div>
        </div>
    </div>

    <!-- Main Container -->
    <div class="max-w-7xl ml-0 px-4 sm:px-6 lg:px-8 py-8">
                <?php if ($error): ?>
                    <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-md mb-4 no-print">
                        <i class="fa fa-exclamation-triangle mr-2"></i> <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-md mb-4 no-print">
                        <i class="fa fa-check mr-2"></i> 
                        <?php if ($success === 'created'): ?>
                            Transaksi berhasil dibuat!
                        <?php elseif ($success === 'payment_added'): ?>
                            Pembayaran piutang berhasil diproses!
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <!-- Invoice Header -->
                <div class="text-center mb-8 p-6 bg-white rounded-lg shadow-sm border">
                    <div class="flex justify-center mb-4">
                        <img src="img/clasnet.png" alt="Clasnet Logo" class="h-16 w-auto">
                    </div>
                    <h2 class="text-2xl font-bold text-gray-800 mb-2">INVOICE TRANSAKSI</h2>
                    <h3 class="text-xl font-semibold text-blue-600 mb-2"><?= htmlspecialchars($transaksi['nomor_invoice']) ?></h3>
                    <p class="text-gray-600">Tanggal: <?= date('d/m/Y H:i', strtotime($transaksi['created_at'])) ?></p>
                </div>

                <!-- Informasi Transaksi -->
                <div class="bg-white p-6 rounded-lg shadow-sm mb-6">
                    <div class="text-lg font-bold text-gray-800 mb-4 pb-3 border-b-2 border-gray-100">
                        <i class="fa fa-info-circle text-blue-500"></i> Informasi Transaksi
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <div class="mb-3">
                                <span class="font-semibold text-gray-600">Invoice Number:</span>
                                <span class="ml-2"><?= htmlspecialchars($transaksi['nomor_invoice']) ?></span>
                            </div>
                            <div class="mb-3">
                                <span class="font-semibold text-gray-600">Tanggal Transaksi:</span>
                                <span class="ml-2"><?= date('d/m/Y H:i', strtotime($transaksi['created_at'])) ?></span>
                            </div>
                            <div class="mb-3">
                                <span class="font-semibold text-gray-600">Status:</span>
                                <span class="ml-2 px-2 py-1 rounded text-sm font-medium <?= getStatusBadge($transaksi['status_transaksi']) ?>">
                                    <?= getStatusText($transaksi['status_transaksi']) ?>
                                </span>
                            </div>
                            <div class="mb-3">
                                <span class="font-semibold text-gray-600">Metode Pembayaran:</span>
                                <span class="ml-2"><?= getPaymentTypeText($transaksi['status_pembayaran']) ?></span>
                            </div>
                        </div>
                        <div>
                            <div class="mb-3">
                                <span class="font-semibold text-gray-600">Sales:</span>
                                <span class="ml-2"><?= htmlspecialchars($transaksi['sales_name']) ?></span>
                            </div>
                            <div class="mb-3">
                                <span class="font-semibold text-gray-600">Email Sales:</span>
                                <span class="ml-2"><?= htmlspecialchars($transaksi['sales_email']) ?></span>
                            </div>
                            <div class="mb-3">
                                <span class="font-semibold text-gray-600">Total Transaksi:</span>
                                <span class="ml-2 font-bold text-green-600"><?= formatRupiah($transaksi['total_amount']) ?></span>
                            </div>
                            <?php if ($transaksi['status_pembayaran'] === 'dp'): ?>
                            <div class="mb-3">
                                <span class="font-semibold text-gray-600">Jumlah DP:</span>
                                <span class="ml-2"><?= formatRupiah($transaksi['dp_amount']) ?></span>
                            </div>
                            <div class="mb-3">
                                <span class="font-semibold text-gray-600">Sisa Hutang:</span>
                                <span class="ml-2 font-bold text-red-600"><?= formatRupiah($transaksi['total_amount'] - $transaksi['dp_amount']) ?></span>
                            </div>
                            <?php elseif ($transaksi['status_pembayaran'] === 'tempo'): ?>
                            <div class="mb-3">
                                <span class="font-semibold text-gray-600">Total Hutang:</span>
                                <span class="ml-2 font-bold text-red-600"><?= formatRupiah($transaksi['total_amount']) ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Informasi Desa -->
                <div class="bg-white p-6 rounded-lg shadow-sm mb-6">
                    <div class="text-lg font-bold text-gray-800 mb-4 pb-3 border-b-2 border-gray-100">
                        <i class="fa fa-map-marker text-green-500"></i> Informasi Desa
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <div class="mb-3">
                                <span class="font-semibold text-gray-600">Nama Desa:</span>
                                <span class="ml-2"><?= htmlspecialchars($transaksi['nama_desa']) ?></span>
                            </div>
                            <div class="mb-3">
                                <span class="font-semibold text-gray-600">Kecamatan:</span>
                                <span class="ml-2"><?= htmlspecialchars($transaksi['kecamatan']) ?></span>
                            </div>
                            <div class="mb-3">
                                <span class="font-semibold text-gray-600">Kabupaten:</span>
                                <span class="ml-2"><?= htmlspecialchars($transaksi['kabupaten']) ?></span>
                            </div>
                        </div>
                        <div>
                            <div class="mb-3">
                                <span class="font-semibold text-gray-600">Kontak Person:</span>
                                <span class="ml-2"><?= htmlspecialchars($transaksi['nama_kepala_desa'] ?? '') ?></span>
                            </div>
                            <div class="mb-3">
                                <span class="font-semibold text-gray-600">No. Telepon:</span>
                                <span class="ml-2"><?= htmlspecialchars($transaksi['no_hp_kepala_desa'] ?? '') ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Detail Item Transaksi -->
                <div class="bg-white p-6 rounded-lg shadow-sm mb-6">
                    <div class="text-lg font-bold text-gray-800 mb-4 pb-3 border-b-2 border-gray-100">
                        <i class="fa fa-list text-purple-500"></i> Detail Item Transaksi
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="w-full border-collapse mt-4">
                            <thead>
                                <tr class="bg-gray-50">
                                    <th class="p-3 text-left font-bold border-b border-gray-200">No</th>
                                    <th class="p-3 text-left font-bold border-b border-gray-200">Tipe</th>
                                    <th class="p-3 text-left font-bold border-b border-gray-200">Nama Item</th>
                                    <th class="p-3 text-left font-bold border-b border-gray-200">Quantity</th>
                                    <th class="p-3 text-left font-bold border-b border-gray-200">Harga Satuan</th>
                                    <th class="p-3 text-left font-bold border-b border-gray-200">Total</th>
                                </tr>
                            </thead>
                        <tbody>
                            <?php $no = 1; foreach ($detail_transaksi as $detail): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="p-3 border-b border-gray-200"><?= $no++ ?></td>
                                <td class="p-3 border-b border-gray-200">
                                    <span class="px-2 py-1 rounded text-sm font-medium <?= !empty($detail['produk_id']) ? 'bg-blue-100 text-blue-800' : 'bg-cyan-100 text-cyan-800' ?>">
                                        <?= !empty($detail['produk_id']) ? 'Produk' : 'Layanan' ?>
                                    </span>
                                </td>
                                <td class="p-3 border-b border-gray-200"><?= htmlspecialchars($detail['nama_item']) ?></td>
                                <td class="p-3 border-b border-gray-200"><?= number_format($detail['quantity'], 2) ?></td>
                                <td class="p-3 border-b border-gray-200"><?= formatRupiah($detail['harga_satuan']) ?></td>
                                <td class="p-3 border-b border-gray-200"><?= formatRupiah($detail['subtotal']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                            <tfoot>
                                <tr class="bg-gray-50 font-bold">
                                    <td colspan="5" class="p-3 text-right border-b border-gray-200">TOTAL:</td>
                                    <td class="p-3 border-b border-gray-200 text-green-600 font-bold"><?= formatRupiah($transaksi['total_amount']) ?></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                <!-- Informasi Piutang -->
                <?php if ($piutang): ?>
                <div class="bg-white p-6 rounded-lg shadow-sm mb-6">
                    <div class="text-lg font-bold text-gray-800 mb-4 pb-3 border-b-2 border-gray-100">
                        <i class="fa fa-money text-orange-500"></i> Informasi Piutang
                    </div>
                    
                    <?php if ($piutang['status'] === 'lunas'): ?>
                        <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                            <h4 class="text-green-800 font-semibold mb-2"><i class="fa fa-check-circle"></i> Piutang Telah Lunas</h4>
                            <p class="text-green-700">Piutang untuk transaksi ini telah dilunasi pada <?= date('d/m/Y H:i', strtotime($piutang['updated_at'])) ?></p>
                        </div>
                    <?php else: ?>
                        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                            <h4 class="text-yellow-800 font-semibold mb-4"><i class="fa fa-exclamation-triangle"></i> Piutang Belum Lunas</h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <p class="mb-2"><span class="font-semibold text-gray-600">Jumlah Piutang:</span> <span class="ml-2"><?= formatRupiah($piutang['jumlah_piutang']) ?></span></p>
                                    <p class="mb-2"><span class="font-semibold text-gray-600">Sisa Piutang:</span> <span class="ml-2 font-bold text-red-600"><?= formatRupiah($piutang['sisa_piutang']) ?></span></p>
                                </div>
                                <div>
                                    <?php if ($piutang['jatuh_tempo']): ?>
                                    <p class="mb-2"><span class="font-semibold text-gray-600">Jatuh Tempo:</span> <span class="ml-2"><?= date('d/m/Y', strtotime($piutang['jatuh_tempo'])) ?></span></p>
                                    <?php 
                                    $days_left = ceil((strtotime($piutang['jatuh_tempo']) - time()) / (60 * 60 * 24));
                                    if ($days_left < 0): 
                                    ?>
                                    <p class="text-red-600 font-semibold"><span class="font-semibold text-gray-600">Status:</span> <span class="ml-2">Terlambat <?= abs($days_left) ?> hari</span></p>
                                    <?php elseif ($days_left <= 7): ?>
                                    <p class="text-yellow-600 font-semibold"><span class="font-semibold text-gray-600">Status:</span> <span class="ml-2">Jatuh tempo dalam <?= $days_left ?> hari</span></p>
                                    <?php else: ?>
                                    <p class="text-blue-600 font-semibold"><span class="font-semibold text-gray-600">Status:</span> <span class="ml-2"><?= $days_left ?> hari lagi</span></p>
                                    <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Riwayat Pembayaran -->
                    <?php if (!empty($pembayaran_piutang)): ?>
                    <h5 class="text-lg font-semibold text-gray-800 mt-6 mb-4">Riwayat Pembayaran</h5>
                    <div class="overflow-x-auto">
                        <table class="w-full border-collapse">
                            <thead>
                                <tr class="bg-gray-50">
                                    <th class="p-3 text-left font-bold border-b border-gray-200">Tanggal</th>
                                    <th class="p-3 text-left font-bold border-b border-gray-200">Jumlah Bayar</th>
                                    <th class="p-3 text-left font-bold border-b border-gray-200">Metode</th>
                                    <th class="p-3 text-left font-bold border-b border-gray-200">Keterangan</th>
                                    <th class="p-3 text-left font-bold border-b border-gray-200">Diproses Oleh</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pembayaran_piutang as $bayar): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="p-3 border-b border-gray-200"><?= date('d/m/Y H:i', strtotime($bayar['created_at'])) ?></td>
                                    <td class="p-3 border-b border-gray-200"><?= formatRupiah($bayar['jumlah_bayar']) ?></td>
                                    <td class="p-3 border-b border-gray-200"><?= ucfirst($bayar['metode_bayar']) ?></td>
                                    <td class="p-3 border-b border-gray-200"><?= htmlspecialchars($bayar['keterangan']) ?></td>
                                    <td class="p-3 border-b border-gray-200"><?= htmlspecialchars($bayar['user_name']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Form Pembayaran Piutang -->
                    <?php if ($piutang['status'] === 'aktif' && AuthStatic::hasRole(['admin', 'finance'])): ?>
                    <div class="mt-6 p-4 bg-gray-50 rounded-lg no-print">
                        <h5 class="text-lg font-semibold text-gray-800 mb-4">Proses Pembayaran Piutang</h5>
                        <form method="POST">
                            <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                                <div>
                                    <label for="jumlah_bayar" class="block text-sm font-medium text-gray-700 mb-2">Jumlah Bayar</label>
                                    <input type="number" id="jumlah_bayar" name="jumlah_bayar" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" 
                                           max="<?= $piutang['sisa_piutang'] ?>" min="1" step="1000" required>
                                    <small class="text-gray-500 text-sm">Maksimal: <?= formatRupiah($piutang['sisa_piutang']) ?></small>
                                </div>
                                <div>
                                    <label for="metode_bayar" class="block text-sm font-medium text-gray-700 mb-2">Metode</label>
                                    <select id="metode_bayar" name="metode_bayar" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                                        <option value="tunai">Tunai</option>
                                        <option value="transfer">Transfer</option>
                                        <option value="cek">Cek</option>
                                    </select>
                                </div>
                                <div>
                                    <label for="bank_id" class="block text-sm font-medium text-gray-700 mb-2">Bank</label>
                                    <select id="bank_id" name="bank_id" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                                        <option value="">Pilih Bank</option>
                                        <?php foreach ($bank_list as $bank): ?>
                                        <option value="<?= $bank['id'] ?>"><?= htmlspecialchars($bank['nama_bank']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label for="keterangan" class="block text-sm font-medium text-gray-700 mb-2">Keterangan</label>
                                    <input type="text" id="keterangan" name="keterangan" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" 
                                           placeholder="Keterangan pembayaran">
                                </div>
                                <div class="flex items-end">
                                    <button type="submit" name="bayar_piutang" class="w-full bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-md transition duration-200">
                                        <i class="fa fa-money"></i> Proses Pembayaran
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- Catatan -->
                <?php if ($transaksi['catatan']): ?>
                <div class="bg-white p-6 rounded-lg shadow-sm mb-6">
                    <div class="text-lg font-bold text-gray-800 mb-4 pb-3 border-b-2 border-gray-100">
                        <i class="fa fa-sticky-note text-yellow-500"></i> Catatan
                    </div>
                    <p class="text-gray-700 leading-relaxed"><?= nl2br(htmlspecialchars($transaksi['catatan'])) ?></p>
                </div>
                <?php endif; ?>

                <!-- Action Buttons -->
                <div class="flex flex-wrap gap-3 mt-6 no-print">
                    <a href="transaksi.php" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-md transition duration-200">
                        <i class="fa fa-arrow-left"></i> Kembali ke Daftar
                    </a>
                    
                    <?php if (AuthStatic::hasRole(['admin']) || $user['id'] == $transaksi['user_id']): ?>
                    <a href="transaksi-edit.php?id=<?= $transaksi['id'] ?>" class="bg-yellow-500 hover:bg-yellow-600 text-white px-4 py-2 rounded-md transition duration-200">
                        <i class="fa fa-edit"></i> Edit Transaksi
                    </a>
                    <?php endif; ?>
                    
                    <button onclick="window.print()" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-md transition duration-200">
                        <i class="fa fa-print"></i> Cetak Invoice
                    </button>
                    
                    <a href="generate-pdf-invoice.php?id=<?= $transaksi['id'] ?>" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-md transition duration-200" target="_blank">
                        <i class="fa fa-file-pdf"></i> Simpan PDF
                    </a>
                    
                    <button onclick="downloadPDF()" class="bg-purple-500 hover:bg-purple-600 text-white px-4 py-2 rounded-md transition duration-200">
                        <i class="fa fa-download"></i> Cetak PDF
                    </button>
                    
                    <?php if ($transaksi['status_transaksi'] === 'draft' && AuthStatic::hasRole(['admin'])): ?>
                    <a href="transaksi-cancel.php?id=<?= $transaksi['id'] ?>" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-md transition duration-200" 
                       onclick="return confirm('Apakah Anda yakin ingin membatalkan transaksi ini?')">
                        <i class="fa fa-times"></i> Batalkan Transaksi
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </main>
        
        <!-- Main Container End -->
    </div>

    <!-- JavaScript -->
    <script src="js/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script>
        // Auto-fill full payment amount
        $('#jumlah_bayar').focus(function() {
            if (!$(this).val()) {
                $(this).val(<?= $piutang['sisa_piutang'] ?? 0 ?>);
            }
        });

        // Download PDF function
        function downloadPDF() {
            // Redirect to PDF generator
            window.open('generate-pdf-invoice.php?id=<?= $transaksi['id'] ?>', '_blank');
        }
        
        // Export to PDF function (legacy)
        function exportToPDF() {
            const { jsPDF } = window.jspdf;
            
            // Hide no-print elements
            const noPrintElements = document.querySelectorAll('.no-print');
            noPrintElements.forEach(el => el.style.display = 'none');
            
            // Get the main content
            const element = document.getElementById('main-content');
            
            html2canvas(element, {
                scale: 2,
                useCORS: true,
                allowTaint: true,
                backgroundColor: '#ffffff'
            }).then(canvas => {
                const imgData = canvas.toDataURL('image/png');
                const pdf = new jsPDF('p', 'mm', 'a4');
                
                const imgWidth = 210;
                const pageHeight = 295;
                const imgHeight = (canvas.height * imgWidth) / canvas.width;
                let heightLeft = imgHeight;
                
                let position = 0;
                
                pdf.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight);
                heightLeft -= pageHeight;
                
                while (heightLeft >= 0) {
                    position = heightLeft - imgHeight;
                    pdf.addPage();
                    pdf.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight);
                    heightLeft -= pageHeight;
                }
                
                pdf.save('Invoice-<?= htmlspecialchars($transaksi['nomor_invoice']) ?>.pdf');
                
                // Show no-print elements again
                noPrintElements.forEach(el => el.style.display = '');
            }).catch(error => {
                console.error('Error generating PDF:', error);
                alert('Terjadi kesalahan saat membuat PDF. Silakan coba lagi.');
                
                // Show no-print elements again
                noPrintElements.forEach(el => el.style.display = '');
            });
        }

        // Sidebar toggle functionality
        document.addEventListener('DOMContentLoaded', function() {
            const toggleButton = document.getElementById('toggleSidebarMobile');
            const sidebar = document.getElementById('logo-sidebar');
            const backdrop = document.getElementById('sidebarBackdrop');
            
            if (toggleButton && sidebar) {
                toggleButton.addEventListener('click', function() {
                    sidebar.classList.toggle('-translate-x-full');
                    if (backdrop) {
                        backdrop.classList.toggle('hidden');
                    }
                });
            }
            
            if (backdrop) {
                backdrop.addEventListener('click', function() {
                    sidebar.classList.add('-translate-x-full');
                    backdrop.classList.add('hidden');
                });
            }

            // Dropdown functionality
            const userMenuButton = document.getElementById('user-menu-button');
            const userDropdown = document.getElementById('dropdown-user');
            
            if (userMenuButton && userDropdown) {
                userMenuButton.addEventListener('click', function() {
                    userDropdown.classList.toggle('hidden');
                });
                
                // Close dropdown when clicking outside
                document.addEventListener('click', function(event) {
                    if (!userMenuButton.contains(event.target) && !userDropdown.contains(event.target)) {
                        userDropdown.classList.add('hidden');
                    }
                });
            }
        });
    </script>

<?php require_once 'layouts/footer.php'; ?>
