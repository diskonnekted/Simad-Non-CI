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
/** @var Database $db */
$db = getDatabase();

$error = $_GET['error'] ?? '';
$success = $_GET['success'] ?? '';

// Parameter pencarian dan filter
$search = $_GET['search'] ?? '';
$kategori_filter = $_GET['kategori'] ?? '';
$sort = $_GET['sort'] ?? 'nama_produk';
$order = $_GET['order'] ?? 'ASC';

// Ambil daftar kategori untuk filter
$kategori_list = $db->select("
    SELECT id, nama_kategori 
    FROM kategori_produk 
    ORDER BY nama_kategori
");

// Build query untuk produk aktif
$where_conditions = ['p.status = ?'];
$params = ['aktif'];

if (!empty($search)) {
    $where_conditions[] = "(p.nama_produk LIKE ? OR p.kode_produk LIKE ? OR p.deskripsi LIKE ?)";
    $search_param = "%{$search}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if (!empty($kategori_filter)) {
    $where_conditions[] = "p.kategori_id = ?";
    $params[] = $kategori_filter;
}

$where_clause = implode(' AND ', $where_conditions);

// Query untuk mengambil data produk dengan harga rata-rata pembelian
$query = "
    SELECT 
        p.id,
        p.kode_produk,
        p.nama_produk,
        p.stok_tersedia,
        p.stok_minimal,
        p.harga_satuan,
        k.nama_kategori,
        COALESCE(AVG(pd.harga_satuan), p.harga_satuan) as harga_average,
        COUNT(pd.id) as total_pembelian
    FROM produk p
    LEFT JOIN kategori_produk k ON p.kategori_id = k.id
    LEFT JOIN pembelian_detail pd ON p.id = pd.produk_id
    LEFT JOIN pembelian pb ON pd.pembelian_id = pb.id AND pb.status_pembelian IN ('diterima_sebagian', 'diterima_lengkap')
    WHERE {$where_clause}
    GROUP BY p.id, p.kode_produk, p.nama_produk, p.stok_tersedia, p.stok_minimal, p.harga_satuan, k.nama_kategori
    ORDER BY {$sort} {$order}
";

$produk_list = $db->select($query, $params);

// Helper functions
function formatRupiah($amount) {
    return 'Rp ' . number_format($amount, 0, ',', '.');
}

function getStokStatus($stok_tersedia, $stok_minimal) {
    if ($stok_tersedia <= 0) {
        return ['class' => 'bg-red-100 text-red-800', 'text' => 'Habis'];
    } elseif ($stok_tersedia <= $stok_minimal) {
        return ['class' => 'bg-yellow-100 text-yellow-800', 'text' => 'Rendah'];
    } else {
        return ['class' => 'bg-green-100 text-green-800', 'text' => 'Aman'];
    }
}

$page_title = 'Stock Opname';
require_once 'layouts/header.php';
?>

<!-- Loading Overlay -->
<div id="loadingOverlay" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen">
        <div class="bg-white p-6 rounded-lg shadow-lg">
            <div class="flex items-center">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600 mr-3"></div>
                <span class="text-gray-700">Memproses stock opname...</span>
            </div>
        </div>
    </div>
</div>

<!-- Page Header -->
<div class="mb-8">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Stock Opname</h1>
            <p class="text-sm text-gray-600 mt-1">Pengecekan dan penyesuaian stok produk</p>
        </div>
        <div class="mt-4 sm:mt-0 flex space-x-2">
            <button id="saveStockOpname" class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-lg text-sm font-medium text-white hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition-colors">
                <i class="fas fa-save mr-2"></i>
                Simpan Stock Opname
            </button>
            <a href="transaksi.php" class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-lg text-sm font-medium text-white hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition-colors">
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
                <span class="text-green-800">
                    <?php if ($success === 'stock_updated'): ?>
                        Stock opname berhasil disimpan
                    <?php endif; ?>
                </span>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
            <div class="flex items-center">
                <i class="fa fa-exclamation-triangle text-red-500 mr-3"></i>
                <span class="text-red-800">
                    <?php if ($error === 'invalid_data'): ?>
                        Data stock opname tidak valid
                    <?php elseif ($error === 'access_denied'): ?>
                        Anda tidak memiliki akses untuk melakukan stock opname
                    <?php else: ?>
                        Terjadi kesalahan: <?= htmlspecialchars($error) ?>
                    <?php endif; ?>
                </span>
            </div>
        </div>
    <?php endif; ?>

    <!-- Filter Section -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
        <form method="GET" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label for="search" class="block text-sm font-medium text-gray-700 mb-2">Pencarian</label>
                    <input type="text" id="search" name="search" value="<?= htmlspecialchars($search) ?>" 
                           placeholder="Cari nama produk, kode produk..." 
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary-500 focus:border-primary-500">
                </div>
                <div>
                    <label for="kategori" class="block text-sm font-medium text-gray-700 mb-2">Kategori</label>
                    <select id="kategori" name="kategori" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary-500 focus:border-primary-500">
                        <option value="">Semua Kategori</option>
                        <?php foreach ($kategori_list as $kategori): ?>
                            <option value="<?= $kategori['id'] ?>" <?= $kategori_filter == $kategori['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($kategori['nama_kategori']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="flex items-end space-x-2">
                    <button type="submit" class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 transition-colors">
                        <i class="fas fa-search mr-2"></i>
                        Filter
                    </button>
                    <a href="api/stock-opname-pdf.php?<?= http_build_query(['search' => $search, 'kategori' => $kategori_filter, 'tanggal_opname' => date('Y-m-d')]) ?>" 
                       target="_blank"
                       class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition-colors">
                        <i class="fas fa-file-pdf mr-2"></i>Cetak PDF
                    </a>
                </div>
            </div>
        </form>
    </div>

    <!-- Stock Opname Form -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-medium text-gray-900">Daftar Produk untuk Stock Opname</h3>
            <p class="text-sm text-gray-600 mt-1">Masukkan stok fisik yang ditemukan untuk setiap produk</p>
        </div>
        
        <form id="stockOpnameForm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Produk</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kategori</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Harga Avg</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Stok Sistem</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Stok Fisik</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Selisih</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Keterangan</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($produk_list)): ?>
                            <tr>
                                <td colspan="7" class="px-6 py-4 text-center text-gray-500">
                                    Tidak ada produk yang ditemukan
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($produk_list as $produk): ?>
                                <?php $stok_status = getStokStatus($produk['stok_tersedia'], $produk['stok_minimal']); ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4">
                                        <div>
                                            <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($produk['nama_produk']) ?></div>
                                            <div class="text-sm text-gray-500"><?= htmlspecialchars($produk['kode_produk']) ?></div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900">
                                        <?= htmlspecialchars($produk['nama_kategori'] ?? '-') ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm text-gray-900"><?= formatRupiah($produk['harga_average']) ?></div>
                                        <div class="text-xs text-gray-500"><?= $produk['total_pembelian'] ?> pembelian</div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center">
                                            <span class="text-sm font-medium text-gray-900 mr-2"><?= number_format($produk['stok_tersedia']) ?></span>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $stok_status['class'] ?>">
                                                <?= $stok_status['text'] ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <input type="number" 
                                               name="stok_fisik[<?= $produk['id'] ?>]" 
                                               data-produk-id="<?= $produk['id'] ?>" 
                                               data-stok-sistem="<?= $produk['stok_tersedia'] ?>" 
                                               class="stok-fisik-input w-24 px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary-500 focus:border-primary-500 text-sm" 
                                               placeholder="0" 
                                               min="0">
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="selisih-display text-sm font-medium" data-produk-id="<?= $produk['id'] ?>">-</span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <input type="text" 
                                               name="keterangan[<?= $produk['id'] ?>]" 
                                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary-500 focus:border-primary-500 text-sm" 
                                               placeholder="Keterangan (opsional)">
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle stok fisik input changes
    document.querySelectorAll('.stok-fisik-input').forEach(function(input) {
        input.addEventListener('input', function() {
            const produkId = this.dataset.produkId;
            const stokSistem = parseInt(this.dataset.stokSistem);
            const stokFisik = parseInt(this.value) || 0;
            const selisih = stokFisik - stokSistem;
            
            const selisihDisplay = document.querySelector(`.selisih-display[data-produk-id="${produkId}"]`);
            
            if (this.value === '') {
                selisihDisplay.textContent = '-';
                selisihDisplay.className = 'selisih-display text-sm font-medium';
            } else {
                selisihDisplay.textContent = selisih > 0 ? `+${selisih}` : selisih.toString();
                
                if (selisih > 0) {
                    selisihDisplay.className = 'selisih-display text-sm font-medium text-green-600';
                } else if (selisih < 0) {
                    selisihDisplay.className = 'selisih-display text-sm font-medium text-red-600';
                } else {
                    selisihDisplay.className = 'selisih-display text-sm font-medium text-gray-600';
                }
            }
        });
    });
    
    // Handle save stock opname
    document.getElementById('saveStockOpname').addEventListener('click', function() {
        const formData = new FormData();
        const stockData = [];
        
        document.querySelectorAll('.stok-fisik-input').forEach(function(input) {
            if (input.value !== '') {
                const produkId = input.dataset.produkId;
                const stokSistem = parseInt(input.dataset.stokSistem);
                const stokFisik = parseInt(input.value);
                const keteranganInput = document.querySelector(`input[name="keterangan[${produkId}]"]`);
                
                stockData.push({
                    produk_id: produkId,
                    stok_sistem: stokSistem,
                    stok_fisik: stokFisik,
                    selisih: stokFisik - stokSistem,
                    keterangan: keteranganInput.value
                });
            }
        });
        
        if (stockData.length === 0) {
            alert('Silakan masukkan stok fisik untuk minimal satu produk.');
            return;
        }
        
        if (confirm('Apakah Anda yakin ingin menyimpan stock opname ini? Stok produk akan diperbarui sesuai dengan stok fisik yang dimasukkan.')) {
            document.getElementById('loadingOverlay').classList.remove('hidden');
            
            formData.append('stock_data', JSON.stringify(stockData));
            
            fetch('api/stock-opname-process.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById('loadingOverlay').classList.add('hidden');
                
                if (data.success) {
                    window.location.href = 'stock-opname.php?success=stock_updated';
                } else {
                    alert('Error: ' + (data.message || 'Terjadi kesalahan'));
                }
            })
            .catch(error => {
                document.getElementById('loadingOverlay').classList.add('hidden');
                console.error('Error:', error);
                alert('Terjadi kesalahan saat menyimpan stock opname.');
            });
        }
    });
});
</script>

<?php require_once 'layouts/footer.php'; ?>