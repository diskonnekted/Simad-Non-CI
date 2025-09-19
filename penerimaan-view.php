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

$penerimaan_id = intval($_GET['id'] ?? 0);

if (!$penerimaan_id) {
    header('Location: penerimaan.php?error=invalid_id');
    exit;
}

// Ambil data penerimaan
$penerimaan = $db->select("
    SELECT pb.*, p.nomor_po, p.tanggal_pembelian, p.status_pembelian,
           v.nama_vendor, v.kode_vendor, v.alamat as alamat_vendor,
           u.nama_lengkap as user_nama
    FROM penerimaan_barang pb
    LEFT JOIN pembelian p ON pb.pembelian_id = p.id
    LEFT JOIN vendor v ON p.vendor_id = v.id
    LEFT JOIN users u ON pb.user_id = u.id
    WHERE pb.id = ?
", [$penerimaan_id]);

if (empty($penerimaan)) {
    header('Location: penerimaan.php?error=not_found');
    exit;
}

$penerimaan = $penerimaan[0];

// Ambil detail penerimaan
$detail_penerimaan = $db->select("
    SELECT pd.*, p.nama_produk, p.kode_produk, p.satuan,
           pbd.quantity_pesan, pbd.harga_satuan,
           (pbd.quantity_pesan * pbd.harga_satuan) as subtotal
    FROM penerimaan_detail pd
    LEFT JOIN pembelian_detail pbd ON pd.pembelian_detail_id = pbd.id
    LEFT JOIN produk p ON pbd.produk_id = p.id
    WHERE pd.penerimaan_id = ?
    ORDER BY pd.id
", [$penerimaan_id]);

// Helper functions
function formatRupiah($amount) {
    return 'Rp ' . number_format($amount, 0, ',', '.');
}

function getKondisiBadge($kondisi) {
    switch ($kondisi) {
        case 'baik':
            return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Baik</span>';
        case 'rusak':
            return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">Rusak</span>';
        case 'cacat':
            return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">Cacat</span>';
        default:
            return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">' . ucfirst($kondisi) . '</span>';
    }
}

function getStatusBadge($status) {
    switch ($status) {
        case 'diterima_lengkap':
            return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Diterima Lengkap</span>';
        case 'diterima_sebagian':
            return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">Diterima Sebagian</span>';
        default:
            return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">' . ucfirst($status) . '</span>';
    }
}

$page_title = 'Detail Penerimaan - ' . $penerimaan['nomor_penerimaan'];
require_once 'layouts/header.php';
?>

<!-- Page Header -->
<div class="bg-white shadow-sm border-b border-gray-200 mb-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 flex items-center">
                    <i class="fa fa-truck text-primary-600 mr-3"></i>
                    Detail Penerimaan Barang
                </h1>
                <p class="text-sm text-gray-600 mt-1">Nomor: <?= htmlspecialchars($penerimaan['nomor_penerimaan']) ?></p>
            </div>
            <div class="mt-4 sm:mt-0 flex space-x-2">
                <a href="penerimaan.php" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 transition-colors">
                    <i class="fas fa-arrow-left mr-2"></i>
                    Kembali
                </a>
                <button onclick="window.print()" class="inline-flex items-center px-4 py-2 bg-primary-600 border border-transparent rounded-lg text-sm font-medium text-white hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 transition-colors">
                    <i class="fas fa-print mr-2"></i>
                    Cetak
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Purchase Process Indicator -->
<?php
require_once 'components/purchase_process_indicator.php';

// Ambil data pembelian lengkap untuk purchase process indicator
$pembelian_data = $db->select("
    SELECT * FROM pembelian WHERE id = ?
", [$penerimaan['pembelian_id']]);

if (!empty($pembelian_data)) {
    renderPurchaseProcessIndicator($pembelian_data[0], $penerimaan);
}
?>

<!-- Main Container -->
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Informasi Penerimaan -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Informasi Penerimaan</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div>
                <label class="block text-sm font-medium text-gray-700">Nomor Penerimaan</label>
                <p class="text-sm text-gray-900 font-medium"><?= htmlspecialchars($penerimaan['nomor_penerimaan']) ?></p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Tanggal Terima</label>
                <p class="text-sm text-gray-900"><?= date('d/m/Y', strtotime($penerimaan['tanggal_terima'])) ?></p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Diterima Oleh</label>
                <p class="text-sm text-gray-900"><?= htmlspecialchars($penerimaan['user_nama']) ?></p>
            </div>
        </div>
        
        <?php if (!empty($penerimaan['catatan'])): ?>
        <div class="mt-4">
            <label class="block text-sm font-medium text-gray-700">Catatan</label>
            <p class="text-sm text-gray-900 bg-gray-50 p-3 rounded-lg"><?= htmlspecialchars($penerimaan['catatan']) ?></p>
        </div>
        <?php endif; ?>
    </div>

    <!-- Informasi Purchase Order -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Informasi Purchase Order</h3>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
            <div>
                <label class="block text-sm font-medium text-gray-700">Nomor PO</label>
                <p class="text-sm text-gray-900 font-medium"><?= htmlspecialchars($penerimaan['nomor_po']) ?></p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Tanggal Pembelian</label>
                <p class="text-sm text-gray-900"><?= date('d/m/Y', strtotime($penerimaan['tanggal_pembelian'])) ?></p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Status Pembelian</label>
                <p class="text-sm"><?= getStatusBadge($penerimaan['status_pembelian']) ?></p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Vendor</label>
                <p class="text-sm text-gray-900"><?= htmlspecialchars($penerimaan['nama_vendor']) ?></p>
                <p class="text-xs text-gray-500"><?= htmlspecialchars($penerimaan['kode_vendor']) ?></p>
            </div>
        </div>
        
        <?php if (!empty($penerimaan['alamat_vendor'])): ?>
        <div class="mt-4">
            <label class="block text-sm font-medium text-gray-700">Alamat Vendor</label>
            <p class="text-sm text-gray-900"><?= htmlspecialchars($penerimaan['alamat_vendor']) ?></p>
        </div>
        <?php endif; ?>
    </div>

    <!-- Detail Barang yang Diterima -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-800">Detail Barang yang Diterima</h3>
        </div>
        
        <?php if (empty($detail_penerimaan)): ?>
            <div class="text-center py-12">
                <i class="fas fa-box text-gray-400 text-4xl mb-4"></i>
                <h3 class="text-lg font-medium text-gray-900 mb-2">Tidak ada detail barang</h3>
                <p class="text-gray-500">Detail barang yang diterima tidak ditemukan.</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Produk</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Qty Pesan</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Qty Terima</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Harga Satuan</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subtotal</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kondisi</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Catatan</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php 
                        $total_nilai = 0;
                        $total_qty_pesan = 0;
                        $total_qty_terima = 0;
                        
                        foreach ($detail_penerimaan as $detail): 
                            $nilai_terima = $detail['quantity_terima'] * $detail['harga_satuan'];
                            $total_nilai += $nilai_terima;
                            $total_qty_pesan += $detail['quantity_pesan'];
                            $total_qty_terima += $detail['quantity_terima'];
                        ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($detail['nama_produk']) ?></div>
                                <div class="text-sm text-gray-500"><?= htmlspecialchars($detail['kode_produk']) ?></div>
                                <div class="text-xs text-gray-400"><?= htmlspecialchars($detail['satuan']) ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?= number_format($detail['quantity_pesan']) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <span class="font-medium"><?= number_format($detail['quantity_terima']) ?></span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?= formatRupiah($detail['harga_satuan']) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?= formatRupiah($nilai_terima) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?= getKondisiBadge($detail['kondisi']) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?= !empty($detail['catatan']) ? htmlspecialchars($detail['catatan']) : '-' ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="bg-gray-50">
                        <tr>
                            <td class="px-6 py-3 text-sm font-medium text-gray-900">Total</td>
                            <td class="px-6 py-3 text-sm font-medium text-gray-900"><?= number_format($total_qty_pesan) ?></td>
                            <td class="px-6 py-3 text-sm font-medium text-gray-900"><?= number_format($total_qty_terima) ?></td>
                            <td class="px-6 py-3 text-sm font-medium text-gray-900">-</td>
                            <td class="px-6 py-3 text-sm font-medium text-gray-900"><?= formatRupiah($total_nilai) ?></td>
                            <td class="px-6 py-3 text-sm font-medium text-gray-900">-</td>
                            <td class="px-6 py-3 text-sm font-medium text-gray-900">-</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Ringkasan -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-6">
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-box text-blue-600"></i>
                    </div>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500">Total Items</p>
                    <p class="text-2xl font-semibold text-gray-900"><?= count($detail_penerimaan) ?></p>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-green-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-cubes text-green-600"></i>
                    </div>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500">Total Quantity</p>
                    <p class="text-2xl font-semibold text-gray-900"><?= number_format($total_qty_terima ?? 0) ?></p>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-yellow-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-dollar-sign text-yellow-600"></i>
                    </div>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500">Total Nilai</p>
                    <p class="text-2xl font-semibold text-gray-900"><?= formatRupiah($total_nilai ?? 0) ?></p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Print Styles -->
<style media="print">
    .no-print {
        display: none !important;
    }
    
    body {
        font-size: 12px;
    }
    
    .print-title {
        text-align: center;
        margin-bottom: 20px;
    }
    
    table {
        width: 100%;
        border-collapse: collapse;
    }
    
    table, th, td {
        border: 1px solid #000;
    }
    
    th, td {
        padding: 8px;
        text-align: left;
    }
</style>

<!-- Purchase Process JavaScript -->
<?php renderPurchaseProcessJS(); ?>

<?php require_once 'layouts/footer.php'; ?>