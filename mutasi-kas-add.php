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

$error_message = '';
$success_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bank_id = (int)$_POST['bank_id'];
    $jenis_mutasi = $_POST['jenis_mutasi'];
    $jenis_transaksi = $_POST['jenis_transaksi'];
    $jumlah = (float)str_replace(['.', ','], ['', '.'], $_POST['jumlah']);
    $tanggal_mutasi = $_POST['tanggal_mutasi'];
    
    // Validasi
    if (empty($bank_id) || empty($jenis_mutasi) || empty($jenis_transaksi) || $jumlah <= 0 || empty($tanggal_mutasi)) {
        $error_message = 'Semua field harus diisi dengan benar!';
    } else {
        try {
            $db->beginTransaction();
            
            // Insert mutasi kas
            $query = "
                INSERT INTO mutasi_kas (
                    bank_id, jenis_mutasi, jenis_transaksi, 
                    jumlah, tanggal_mutasi, user_id
                ) VALUES (?, ?, ?, ?, ?, ?)
            ";
            
            $params = [
                $bank_id, $jenis_mutasi, $jenis_transaksi,
                $jumlah, $tanggal_mutasi, $user['id']
            ];
            
            $db->execute($query, $params);
            
            $db->commit();
            
            $success_message = 'Mutasi kas berhasil ditambahkan!';
            
            // Reset form
            $_POST = [];
            
        } catch (Exception $e) {
            $db->rollback();
            $error_message = 'Terjadi kesalahan: ' . $e->getMessage();
        }
    }
}

// Ambil daftar bank
$bank_list = $db->select("SELECT id, nama_bank, jenis_bank, kode_bank FROM bank WHERE status = 'aktif' ORDER BY nama_bank");

$page_title = 'Tambah Mutasi Kas';
require_once 'layouts/header.php';
?>

<!-- Page Header -->
<div class="bg-white shadow-sm border-b border-gray-200 mb-6">
    <div class="px-6 py-4">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Tambah Mutasi Kas</h1>
                <p class="text-sm text-gray-600 mt-1">Tambahkan mutasi kas masuk atau keluar secara manual</p>
            </div>
            <div class="mt-4 sm:mt-0">
                <a href="saldo-bank.php" class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-lg text-sm font-medium text-white hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition-colors">
                    <i class="fas fa-arrow-left mr-2"></i>
                    Kembali
                </a>
            </div>
        </div>
    </div>
</div>

<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
    <!-- Alert Messages -->
    <?php if ($error_message): ?>
    <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
        <div class="flex">
            <div class="flex-shrink-0">
                <i class="fas fa-exclamation-circle text-red-400"></i>
            </div>
            <div class="ml-3">
                <p class="text-sm text-red-800"><?= htmlspecialchars($error_message) ?></p>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if ($success_message): ?>
    <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
        <div class="flex">
            <div class="flex-shrink-0">
                <i class="fas fa-check-circle text-green-400"></i>
            </div>
            <div class="ml-3">
                <p class="text-sm text-green-800"><?= htmlspecialchars($success_message) ?></p>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Form Tambah Mutasi -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                <i class="fas fa-plus-circle mr-2 text-primary-600"></i>
                Form Tambah Mutasi Kas
            </h3>
        </div>
        
        <form method="POST" class="p-6 space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Bank -->
                <div>
                    <label for="bank_id" class="block text-sm font-medium text-gray-700 mb-2">
                        Bank/Kas <span class="text-red-500">*</span>
                    </label>
                    <select name="bank_id" id="bank_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        <option value="">Pilih Bank/Kas</option>
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
                </div>
                
                <!-- Jenis Mutasi -->
                <div>
                    <label for="jenis_mutasi" class="block text-sm font-medium text-gray-700 mb-2">
                        Jenis Mutasi <span class="text-red-500">*</span>
                    </label>
                    <select name="jenis_mutasi" id="jenis_mutasi" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        <option value="">Pilih Jenis Mutasi</option>
                        <option value="masuk" <?= (($_POST['jenis_mutasi'] ?? '') == 'masuk') ? 'selected' : '' ?>>Kas Masuk</option>
                        <option value="keluar" <?= (($_POST['jenis_mutasi'] ?? '') == 'keluar') ? 'selected' : '' ?>>Kas Keluar</option>
                    </select>
                </div>
                
                <!-- Jenis Transaksi -->
                <div>
                    <label for="jenis_transaksi" class="block text-sm font-medium text-gray-700 mb-2">
                        Jenis Transaksi <span class="text-red-500">*</span>
                    </label>
                    <select name="jenis_transaksi" id="jenis_transaksi" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        <option value="">Pilih Jenis Transaksi</option>
                        <option value="penjualan" <?= (($_POST['jenis_transaksi'] ?? '') == 'penjualan') ? 'selected' : '' ?>>Penjualan</option>
                        <option value="pembelian" <?= (($_POST['jenis_transaksi'] ?? '') == 'pembelian') ? 'selected' : '' ?>>Pembelian</option>
                        <option value="pembayaran_piutang" <?= (($_POST['jenis_transaksi'] ?? '') == 'pembayaran_piutang') ? 'selected' : '' ?>>Pembayaran Piutang</option>
                        <option value="pembayaran_hutang" <?= (($_POST['jenis_transaksi'] ?? '') == 'pembayaran_hutang') ? 'selected' : '' ?>>Pembayaran Hutang</option>
                        <option value="lainnya" <?= (($_POST['jenis_transaksi'] ?? '') == 'lainnya') ? 'selected' : '' ?>>Lainnya</option>
                    </select>
                </div>
                
                <!-- Tanggal Mutasi -->
                <div>
                    <label for="tanggal_mutasi" class="block text-sm font-medium text-gray-700 mb-2">
                        Tanggal Mutasi <span class="text-red-500">*</span>
                    </label>
                    <input type="date" name="tanggal_mutasi" id="tanggal_mutasi" required 
                           value="<?= $_POST['tanggal_mutasi'] ?? date('Y-m-d') ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                </div>
            </div>
            
            <!-- Jumlah -->
            <div>
                <label for="jumlah" class="block text-sm font-medium text-gray-700 mb-2">
                    Jumlah <span class="text-red-500">*</span>
                </label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <span class="text-gray-500 sm:text-sm">Rp</span>
                    </div>
                    <input type="text" name="jumlah" id="jumlah" required 
                           value="<?= $_POST['jumlah'] ?? '' ?>"
                           placeholder="0"
                           class="w-full pl-12 pr-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                           oninput="formatRupiah(this)">
                </div>
                <p class="text-sm text-gray-500 mt-1">Masukkan jumlah dalam rupiah</p>
            </div>
            

            
            <!-- Submit Button -->
            <div class="flex justify-end space-x-3 pt-6 border-t border-gray-200">
                <a href="saldo-bank.php" class="px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition-colors">
                    Batal
                </a>
                <button type="submit" class="px-4 py-2 bg-primary-600 border border-transparent rounded-lg text-sm font-medium text-white hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 transition-colors">
                    <i class="fas fa-save mr-2"></i>
                    Simpan Mutasi
                </button>
            </div>
        </form>
    </div>
    
    <!-- Info Panel -->
    <div class="mt-6 bg-blue-50 border border-blue-200 rounded-lg p-4">
        <div class="flex">
            <div class="flex-shrink-0">
                <i class="fas fa-info-circle text-blue-400"></i>
            </div>
            <div class="ml-3">
                <h3 class="text-sm font-medium text-blue-800">Informasi Mutasi Kas</h3>
                <div class="mt-2 text-sm text-blue-700">
                    <ul class="list-disc list-inside space-y-1">
                        <li><strong>Kas Masuk:</strong> Untuk mencatat pemasukan dana (penjualan, pembayaran piutang, dll)</li>
                        <li><strong>Kas Keluar:</strong> Untuk mencatat pengeluaran dana (pembelian, pembayaran hutang, dll)</li>
                        <li><strong>Jenis Transaksi:</strong> Pilih sesuai dengan sumber atau tujuan mutasi</li>
                        <li>Mutasi akan otomatis mempengaruhi saldo bank yang dipilih</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Format input rupiah
function formatRupiah(input) {
    let value = input.value.replace(/[^\d]/g, '');
    
    if (value) {
        value = parseInt(value).toLocaleString('id-ID');
        input.value = value;
    }
}

// Auto focus pada field pertama
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('bank_id').focus();
});
</script>

<?php require_once 'layouts/footer.php'; ?>