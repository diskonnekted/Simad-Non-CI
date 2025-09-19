<?php
require_once 'config/database.php';
require_once 'config/auth.php';

// Cek autentikasi dan role
if (!AuthStatic::isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Cek role akses
if (!AuthStatic::hasRole(['admin', 'akunting'])) {
    header('Location: index.php?error=access_denied');
    exit;
}

$user = AuthStatic::getCurrentUser();
$db = getDatabase();
/** @var Database $db */

$error = '';
$success = '';

$pembelian_id = intval($_GET['pembelian_id'] ?? 0);

if (!$pembelian_id) {
    header('Location: pembelian.php?error=invalid_id');
    exit;
}

// Ambil data pembelian
$pembelian = $db->select("
    SELECT p.*, v.nama_vendor, v.kode_vendor
    FROM pembelian p
    LEFT JOIN vendor v ON p.vendor_id = v.id
    WHERE p.id = ? AND p.status_pembelian IN ('dikirim', 'diterima_sebagian')
", [$pembelian_id]);

if (empty($pembelian)) {
    header('Location: pembelian.php?error=not_found_or_invalid_status');
    exit;
}

$pembelian = $pembelian[0];

// Ambil detail pembelian yang belum diterima lengkap
$detail_pembelian = $db->select("
    SELECT pd.*, p.nama_produk, p.kode_produk,
           (pd.quantity_pesan - pd.quantity_terima) as sisa_quantity
    FROM pembelian_detail pd
    LEFT JOIN produk p ON pd.produk_id = p.id
    WHERE pd.pembelian_id = ? AND (pd.quantity_pesan > pd.quantity_terima)
    ORDER BY pd.id
", [$pembelian_id]);

if (empty($detail_pembelian)) {
    header('Location: pembelian.php?error=all_items_received');
    exit;
}

// Proses form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tanggal_terima = $_POST['tanggal_terima'] ?? date('Y-m-d');
    $catatan = trim($_POST['catatan'] ?? '');
    $items = $_POST['items'] ?? [];
    
    // Validasi input
    if (empty($tanggal_terima)) {
        $error = 'Tanggal terima harus diisi';
    } elseif (empty($items)) {
        $error = 'Minimal satu item harus diterima';
    } else {
        try {
            $db->beginTransaction();
            
            // Generate nomor penerimaan
            $today = date('Ymd');
            $last_number = $db->select("
                SELECT nomor_penerimaan 
                FROM penerimaan_barang 
                WHERE nomor_penerimaan LIKE 'GR-{$today}-%' 
                ORDER BY nomor_penerimaan DESC 
                LIMIT 1
            ");
            
            if (!empty($last_number)) {
                $last_num = intval(substr($last_number[0]['nomor_penerimaan'], -3));
                $new_number = str_pad($last_num + 1, 3, '0', STR_PAD_LEFT);
            } else {
                $new_number = '001';
            }
            
            $nomor_penerimaan = "GR-{$today}-{$new_number}";
            
            // Insert penerimaan_barang
            $penerimaan_query = "
                INSERT INTO penerimaan_barang (
                    pembelian_id, nomor_penerimaan, tanggal_terima, user_id, catatan
                ) VALUES (?, ?, ?, ?, ?)
            ";
            
            $db->execute($penerimaan_query, [
                $pembelian_id, $nomor_penerimaan, $tanggal_terima, $user['id'], $catatan
            ]);
            
            $penerimaan_id = $db->lastInsertId();
            
            $total_items_received = 0;
            $all_items_complete = true;
            
            // Process each item
            foreach ($items as $detail_id => $item_data) {
                $quantity_terima = intval($item_data['quantity_terima'] ?? 0);
                $kondisi = $item_data['kondisi'] ?? 'baik';
                $catatan_item = trim($item_data['catatan'] ?? '');
                
                if ($quantity_terima > 0) {
                    // Insert penerimaan_detail
                    $detail_query = "
                        INSERT INTO penerimaan_detail (
                            penerimaan_id, pembelian_detail_id, quantity_terima, kondisi, catatan
                        ) VALUES (?, ?, ?, ?, ?)
                    ";
                    
                    $db->execute($detail_query, [
                        $penerimaan_id, $detail_id, $quantity_terima, $kondisi, $catatan_item
                    ]);
                    
                    $total_items_received++;
                }
            }
            
            // Check if all items are fully received
            $remaining_items = $db->select("
                SELECT COUNT(*) as count
                FROM pembelian_detail pd
                WHERE pd.pembelian_id = ? AND (pd.quantity_pesan > pd.quantity_terima)
            ", [$pembelian_id]);
            
            $new_status = ($remaining_items[0]['count'] == 0) ? 'diterima_lengkap' : 'diterima_sebagian';
            
            // Update status pembelian
            $db->execute("
                UPDATE pembelian 
                SET status_pembelian = ?
                WHERE id = ?
            ", [$new_status, $pembelian_id]);
            
            $db->commit();
            
            $success = 'Penerimaan barang berhasil dicatat dengan nomor: ' . $nomor_penerimaan;
            
            // Redirect to prevent form resubmission
            header('Location: pembelian.php?success=received');
            exit;
            
        } catch (Exception $e) {
            $db->rollback();
            $error = 'Terjadi kesalahan: ' . $e->getMessage();
        }
    }
}

// Helper functions
function formatRupiah($amount) {
    return 'Rp ' . number_format($amount, 0, ',', '.');
}

$page_title = 'Penerimaan Barang - ' . $pembelian['nomor_po'];
require_once 'layouts/header.php';
?>

<!-- Page Header -->
<div class="mb-8">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Penerimaan Barang</h1>
            <p class="text-sm text-gray-600 mt-1">Purchase Order: <?= htmlspecialchars($pembelian['nomor_po']) ?></p>
        </div>
        <div class="mt-4 sm:mt-0">
            <a href="pembelian.php" class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-lg text-sm font-medium text-white hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition-colors">
                <i class="fas fa-arrow-left mr-2"></i>
                Kembali
            </a>
        </div>
    </div>
</div>

<!-- Main Container -->
<div class="max-w-7xl ml-0 px-4 sm:px-6 lg:px-8 py-8">
    <!-- Messages -->
    <?php if ($success): ?>
        <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
            <div class="flex items-center">
                <i class="fa fa-check text-green-500 mr-3"></i>
                <span class="text-green-800"><?= htmlspecialchars($success) ?></span>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
            <div class="flex items-center">
                <i class="fa fa-exclamation-triangle text-red-500 mr-3"></i>
                <span class="text-red-800"><?= htmlspecialchars($error) ?></span>
            </div>
        </div>
    <?php endif; ?>

    <!-- Purchase Order Info -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Informasi Purchase Order</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700">Nomor PO</label>
                <p class="text-sm text-gray-900"><?= htmlspecialchars($pembelian['nomor_po']) ?></p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Vendor</label>
                <p class="text-sm text-gray-900"><?= htmlspecialchars($pembelian['nama_vendor']) ?></p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Tanggal Pembelian</label>
                <p class="text-sm text-gray-900"><?= date('d/m/Y', strtotime($pembelian['tanggal_pembelian'])) ?></p>
            </div>
        </div>
    </div>

    <!-- Form Penerimaan -->
    <form method="POST" class="space-y-6">
        <!-- Header Form -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Detail Penerimaan</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="tanggal_terima" class="block text-sm font-medium text-gray-700 mb-2">Tanggal Terima <span class="text-red-500">*</span></label>
                    <input type="date" name="tanggal_terima" id="tanggal_terima" required 
                           value="<?= htmlspecialchars($_POST['tanggal_terima'] ?? date('Y-m-d')) ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                </div>
                <div>
                    <label for="catatan" class="block text-sm font-medium text-gray-700 mb-2">Catatan</label>
                    <textarea name="catatan" id="catatan" rows="3" 
                              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                              placeholder="Catatan penerimaan barang..."><?= htmlspecialchars($_POST['catatan'] ?? '') ?></textarea>
                </div>
            </div>
        </div>

        <!-- Items List -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Daftar Barang yang Diterima</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Produk</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Qty Pesan</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Qty Sudah Terima</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sisa</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Qty Terima Sekarang</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kondisi</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Catatan</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($detail_pembelian as $detail): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($detail['nama_item']) ?></div>
                                <div class="text-sm text-gray-500"><?= htmlspecialchars($detail['kode_produk'] ?? '') ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?= $detail['quantity_pesan'] ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?= $detail['quantity_terima'] ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?= $detail['sisa_quantity'] ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <input type="number" 
                                       name="items[<?= $detail['id'] ?>][quantity_terima]" 
                                       min="0" 
                                       max="<?= $detail['sisa_quantity'] ?>"
                                       value="<?= htmlspecialchars($_POST['items'][$detail['id']]['quantity_terima'] ?? $detail['sisa_quantity']) ?>"
                                       class="w-20 px-2 py-1 border border-gray-300 rounded focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <select name="items[<?= $detail['id'] ?>][kondisi]" 
                                        class="px-2 py-1 border border-gray-300 rounded focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                                    <option value="baik" <?= ($_POST['items'][$detail['id']]['kondisi'] ?? 'baik') === 'baik' ? 'selected' : '' ?>>Baik</option>
                                    <option value="rusak" <?= ($_POST['items'][$detail['id']]['kondisi'] ?? '') === 'rusak' ? 'selected' : '' ?>>Rusak</option>
                                    <option value="cacat" <?= ($_POST['items'][$detail['id']]['kondisi'] ?? '') === 'cacat' ? 'selected' : '' ?>>Cacat</option>
                                </select>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <input type="text" 
                                       name="items[<?= $detail['id'] ?>][catatan]" 
                                       value="<?= htmlspecialchars($_POST['items'][$detail['id']]['catatan'] ?? '') ?>"
                                       placeholder="Catatan item..."
                                       class="w-32 px-2 py-1 border border-gray-300 rounded focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Submit Button -->
        <div class="flex justify-end space-x-4">
            <a href="pembelian.php" class="px-6 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 transition-colors">
                Batal
            </a>
            <button type="submit" class="px-6 py-2 bg-primary-600 border border-transparent rounded-lg text-sm font-medium text-white hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 transition-colors">
                <i class="fas fa-save mr-2"></i>
                Simpan Penerimaan
            </button>
        </div>
    </form>
</div>

<?php require_once 'layouts/footer.php'; ?>