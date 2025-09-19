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
$transaksi_id = $_GET['id'] ?? '';

if (empty($transaksi_id)) {
    header('Location: transaksi.php?error=invalid_id');
    exit;
}

// Ambil data transaksi
$transaksi = $db->select("
    SELECT t.*, d.nama_desa
    FROM transaksi t
    JOIN desa d ON t.desa_id = d.id
    WHERE t.id = ?
", [$transaksi_id]);

if (empty($transaksi)) {
    header('Location: transaksi.php?error=not_found');
    exit;
}

$transaksi = $transaksi[0];

// Cek akses berdasarkan role
if (!AuthStatic::hasRole(['admin']) && $user['id'] != $transaksi['user_id']) {
    header('Location: transaksi.php?error=access_denied');
    exit;
}

// Cek apakah transaksi bisa dibatalkan
if ($transaksi['status_transaksi'] === 'selesai') {
    header('Location: transaksi-view.php?id=' . $transaksi_id . '&error=cannot_cancel_completed');
    exit;
}

if ($transaksi['status_transaksi'] === 'dibatalkan') {
    header('Location: transaksi-view.php?id=' . $transaksi_id . '&error=already_cancelled');
    exit;
}

// Proses pembatalan
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $alasan_batal = trim($_POST['alasan_batal'] ?? '');
    
    if (empty($alasan_batal)) {
        $error = 'Alasan pembatalan harus diisi';
    } else {
        try {
            $db->beginTransaction();
            
            // Update status transaksi
            $db->execute("
                UPDATE transaksi 
                SET status_transaksi = 'dibatalkan', 
                    catatan = CONCAT(COALESCE(catatan, ''), '\n\n[DIBATALKAN] ', ?, ' - ', NOW()),
                    updated_at = NOW()
                WHERE id = ?
            ", [$alasan_batal, $transaksi_id]);
            
            // Kembalikan stok produk jika ada
            $detail_transaksi = $db->select("
                SELECT * FROM transaksi_detail 
                WHERE transaksi_id = ? AND produk_id IS NOT NULL
            ", [$transaksi_id]);
            
            foreach ($detail_transaksi as $detail) {
                $db->execute("
                    UPDATE produk 
                    SET stok_tersedia = stok_tersedia + ?
                    WHERE id = ?
                ", [$detail['quantity'], $detail['produk_id']]);
            }
            
            // Batalkan piutang jika ada
            $db->execute("
                UPDATE piutang 
                SET status = 'dibatalkan', updated_at = NOW()
                WHERE transaksi_id = ? AND status = 'aktif'
            ", [$transaksi_id]);
            
            $db->commit();
            
            header("Location: transaksi-view.php?id={$transaksi_id}&success=cancelled");
            exit;
        } catch (Exception $e) {
            $db->rollback();
            $error = 'Gagal membatalkan transaksi: ' . $e->getMessage();
        }
    }
}

$page_title = 'Batalkan Transaksi';
require_once 'layouts/header.php';
?>

<div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="bg-white shadow rounded-lg">
        <div class="px-6 py-4 border-b border-gray-200">
            <h1 class="text-xl font-semibold text-gray-900">
                <i class="fas fa-times-circle mr-2 text-red-500"></i>
                Batalkan Transaksi
            </h1>
            <p class="text-sm text-gray-600 mt-1">Konfirmasi pembatalan transaksi <?= htmlspecialchars($transaksi['nomor_invoice']) ?></p>
        </div>
        
        <div class="p-6">
            <?php if ($error): ?>
                <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-md mb-4">
                    <i class="fa fa-exclamation-triangle mr-2"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            
            <!-- Informasi Transaksi -->
            <div class="bg-gray-50 p-4 rounded-md mb-6">
                <h3 class="text-sm font-medium text-gray-700 mb-3">Informasi Transaksi</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                    <div>
                        <span class="font-medium text-gray-600">Invoice:</span>
                        <span class="ml-2"><?= htmlspecialchars($transaksi['nomor_invoice']) ?></span>
                    </div>
                    <div>
                        <span class="font-medium text-gray-600">Desa:</span>
                        <span class="ml-2"><?= htmlspecialchars($transaksi['nama_desa']) ?></span>
                    </div>
                    <div>
                        <span class="font-medium text-gray-600">Status:</span>
                        <span class="ml-2 px-2 py-1 rounded text-xs font-medium bg-blue-100 text-blue-800">
                            <?= ucfirst($transaksi['status_transaksi']) ?>
                        </span>
                    </div>
                    <div>
                        <span class="font-medium text-gray-600">Total:</span>
                        <span class="ml-2 font-bold">Rp <?= number_format($transaksi['total_amount'], 0, ',', '.') ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Peringatan -->
            <div class="bg-yellow-50 border border-yellow-200 p-4 rounded-md mb-6">
                <div class="flex items-start">
                    <i class="fas fa-exclamation-triangle text-yellow-500 mt-1 mr-3"></i>
                    <div>
                        <h4 class="text-sm font-medium text-yellow-800">Peringatan</h4>
                        <p class="text-sm text-yellow-700 mt-1">
                            Pembatalan transaksi akan:
                        </p>
                        <ul class="text-sm text-yellow-700 mt-2 list-disc list-inside space-y-1">
                            <li>Mengubah status transaksi menjadi "Dibatalkan"</li>
                            <li>Mengembalikan stok produk yang sudah dikurangi</li>
                            <li>Membatalkan piutang yang terkait (jika ada)</li>
                            <li>Tidak dapat diubah kembali setelah dibatalkan</li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <!-- Form Pembatalan -->
            <form method="POST" class="space-y-6">
                <div>
                    <label for="alasan_batal" class="block text-sm font-medium text-gray-700 mb-2">
                        Alasan Pembatalan <span class="text-red-500">*</span>
                    </label>
                    <textarea 
                        id="alasan_batal" 
                        name="alasan_batal" 
                        rows="4" 
                        required
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500"
                        placeholder="Jelaskan alasan pembatalan transaksi ini..."
                    ><?= htmlspecialchars($_POST['alasan_batal'] ?? '') ?></textarea>
                    <p class="text-xs text-gray-500 mt-1">Alasan ini akan dicatat dalam catatan transaksi</p>
                </div>
                
                <div class="flex space-x-4">
                    <button 
                        type="submit" 
                        class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2"
                        onclick="return confirm('Apakah Anda yakin ingin membatalkan transaksi ini? Tindakan ini tidak dapat dibatalkan.')"
                    >
                        <i class="fas fa-times mr-2"></i>
                        Batalkan Transaksi
                    </button>
                    
                    <a 
                        href="transaksi-view.php?id=<?= $transaksi_id ?>" 
                        class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-medium text-white hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2"
                    >
                        <i class="fas fa-arrow-left mr-2"></i>
                        Kembali
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once 'layouts/footer.php'; ?>