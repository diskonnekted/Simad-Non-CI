<?php
require_once 'config/database.php';
require_once 'config/auth.php';

// Function to format currency
function formatRupiah($angka) {
    return 'Rp ' . number_format($angka, 0, ',', '.');
}

// Cek autentikasi
if (!AuthStatic::isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$user = AuthStatic::getCurrentUser();
$db = getDatabase();
/** @var Database $db */

// Get layanan ID
$layanan_id = intval($_GET['id'] ?? 0);
if (!$layanan_id) {
    header('Location: layanan.php?error=' . urlencode('ID layanan tidak valid.'));
    exit;
}

// Get layanan data
$layanan = $db->select(
    "SELECT * FROM layanan WHERE id = ?",
    [$layanan_id]
);

if (empty($layanan)) {
    header('Location: layanan.php?error=' . urlencode('Layanan tidak ditemukan.'));
    exit;
}

$layanan = $layanan[0];

// Get transaction statistics for this service
$stats = $db->select(
    "SELECT 
        COUNT(dt.id) as total_transaksi,
        SUM(dt.quantity) as total_unit,
        SUM(dt.subtotal) as total_pendapatan,
        AVG(dt.harga_satuan) as harga_rata_rata
     FROM transaksi_detail dt
     JOIN transaksi t ON dt.transaksi_id = t.id
     WHERE dt.layanan_id = ?",
    [$layanan_id]
)[0];

// Get recent transactions using this service
$recent_transactions = $db->select(
    "SELECT t.id, t.nomor_invoice, t.tanggal_transaksi, t.total_amount, t.status_transaksi as status,
            d.nama_desa, u.nama_lengkap as sales_name,
            dt.quantity, dt.harga_satuan, dt.subtotal
     FROM transaksi_detail dt
     JOIN transaksi t ON dt.transaksi_id = t.id
     JOIN desa d ON t.desa_id = d.id
     JOIN users u ON t.user_id = u.id
     WHERE dt.layanan_id = ?
     ORDER BY t.tanggal_transaksi DESC
     LIMIT 10",
    [$layanan_id]
);

// Helper functions
function getStatusBadge($status) {
    $badges = [
        'aktif' => 'success',
        'nonaktif' => 'warning',
        'deleted' => 'danger'
    ];
    return $badges[$status] ?? 'default';
}

function getStatusText($status) {
    $texts = [
        'aktif' => 'Aktif',
        'nonaktif' => 'Nonaktif',
        'deleted' => 'Dihapus'
    ];
    return $texts[$status] ?? $status;
}

function getTransactionStatusBadge($status) {
    $badges = [
        'draft' => 'warning',
        'diproses' => 'info',
        'dikirim' => 'primary',
        'selesai' => 'success'
    ];
    return $badges[$status] ?? 'default';
}

function getTransactionStatusText($status) {
    $texts = [
        'draft' => 'Draft',
        'diproses' => 'Diproses',
        'dikirim' => 'Dikirim',
        'selesai' => 'Selesai'
    ];
    return $texts[$status] ?? $status;
}
?>
<?php
$page_title = 'Detail Layanan';
require_once 'layouts/header.php';
?>

<style>
    @media print {
        .bg-gradient-to-r {
            background: #3b82f6 !important;
            -webkit-print-color-adjust: exact;
        }
    }
    
    .layanan-image {
        width: 100%;
        height: 200px;
        object-fit: cover;
        border-radius: 8px;
        border: 2px solid rgba(255, 255, 255, 0.2);
    }
    
    .no-image {
        width: 100%;
        height: 200px;
        background: rgba(255, 255, 255, 0.1);
        border: 2px dashed rgba(255, 255, 255, 0.3);
        border-radius: 8px;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        color: rgba(255, 255, 255, 0.8);
        text-align: center;
    }
    
    .no-image i {
        margin-bottom: 8px;
        opacity: 0.6;
    }
    
    .no-image p {
        margin: 0;
        font-size: 14px;
        opacity: 0.8;
    }
</style>
<!-- Page Header -->
<div class="bg-white shadow-sm border-b border-gray-200 mb-6">
    <div class="px-6 py-4">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Detail Layanan</h1>
                <p class="text-sm text-gray-600 mt-1">Informasi lengkap layanan</p>
            </div>
        </div>
    </div>
</div>

<!-- Breadcrumb -->
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mb-6">
    <nav class="flex items-center space-x-2 text-sm text-gray-500">
        <a href="index.php" class="hover:text-gray-700 font-medium">Dashboard</a>
        <i class="fa fa-chevron-right text-gray-400 text-xs"></i>
        <a href="layanan.php" class="hover:text-gray-700 font-medium">Layanan</a>
        <i class="fa fa-chevron-right text-gray-400 text-xs"></i>
        <span class="text-gray-900 font-medium"><?= htmlspecialchars($layanan['nama_layanan']) ?></span>
    </nav>
</div>

<!-- Main Container -->
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <!-- Success/Error Messages -->
                <?php if (isset($_GET['success'])): ?>
                <div class="alert bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg mb-6 flex items-center justify-between">
                    <div class="flex items-center">
                        <i class="fa fa-check-circle mr-2 text-green-600"></i>
                        <span><?= htmlspecialchars($_GET['success']) ?></span>
                    </div>
                    <button type="button" class="text-green-600 hover:text-green-800 focus:outline-none" onclick="this.parentElement.style.display='none'">
                        <i class="fa fa-times"></i>
                    </button>
                </div>
                <?php endif; ?>

                <?php if (isset($_GET['error'])): ?>
                <div class="alert bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg mb-6 flex items-center justify-between">
                    <div class="flex items-center">
                        <i class="fa fa-exclamation-circle mr-2 text-red-600"></i>
                        <span><?= htmlspecialchars($_GET['error']) ?></span>
                    </div>
                    <button type="button" class="text-red-600 hover:text-red-800 focus:outline-none" onclick="this.parentElement.style.display='none'">
                        <i class="fa fa-times"></i>
                    </button>
                </div>
                <?php endif; ?>

                <!-- Layanan Header -->
                <div class="bg-gradient-to-r from-primary-500 to-secondary-500 text-white p-8 rounded-lg mb-6">
                    <div class="flex flex-col lg:flex-row gap-6">
                        <div class="lg:w-1/4">
                            <?php if (isset($layanan['gambar']) && $layanan['gambar'] && file_exists($layanan['gambar'])): ?>
                                <img src="<?= htmlspecialchars($layanan['gambar']) ?>" 
                                     alt="<?= htmlspecialchars($layanan['nama_layanan']) ?>" 
                                     class="layanan-image">
                            <?php else: ?>
                                <div class="no-image layanan-image">
                                    <i class="fa fa-image fa-3x"></i>
                                    <p>Tidak ada gambar</p>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="lg:w-3/4">
                            <h2 style="margin-top: 0;"><?= htmlspecialchars($layanan['nama_layanan']) ?></h2>
                            <p class="lead"><?= htmlspecialchars($layanan['deskripsi'] ?: 'Tidak ada deskripsi') ?></p>
                            
                            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mt-6">
                                <div>
                                    <strong class="block text-sm opacity-90">Kode:</strong>
                                    <span class="inline-block bg-blue-500 text-white px-3 py-1 rounded-full text-sm font-medium"><?= htmlspecialchars($layanan['kode_layanan']) ?></span>
                                </div>
                                <div>
                                    <strong class="block text-sm opacity-90">Kategori:</strong>
                                    <span class="text-white"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $layanan['jenis_layanan']))) ?></span>
                                </div>
                                <div>
                                    <strong class="block text-sm opacity-90">Harga:</strong>
                                    <span class="text-lg font-bold text-white">
                                        <?= formatRupiah($layanan['harga']) ?>
                                    </span>
                                </div>
                                <div>
                                    <strong class="block text-sm opacity-90">Status:</strong>
                                    <span class="inline-block px-3 py-1 rounded-full text-sm font-medium <?= getStatusBadge($layanan['status']) === 'success' ? 'bg-green-500' : (getStatusBadge($layanan['status']) === 'warning' ? 'bg-yellow-500' : (getStatusBadge($layanan['status']) === 'info' ? 'bg-blue-500' : 'bg-gray-500')) ?> text-white">
                                        <?= getStatusText($layanan['status']) ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Statistics -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
                    <div class="bg-white p-6 rounded-lg shadow-md border-l-4 border-green-500">
                        <div class="text-2xl font-bold text-green-600"><?= number_format($stats['total_transaksi'] ?: 0) ?></div>
                        <div class="text-gray-600 text-sm mt-1">Total Transaksi</div>
                    </div>
                    <div class="bg-white p-6 rounded-lg shadow-md border-l-4 border-yellow-500">
                        <div class="text-2xl font-bold text-yellow-600"><?= number_format($stats['total_unit'] ?: 0) ?></div>
                        <div class="text-gray-600 text-sm mt-1">Unit Terjual</div>
                    </div>
                    <div class="bg-white p-6 rounded-lg shadow-md border-l-4 border-green-500">
                        <div class="text-2xl font-bold text-green-600"><?= formatRupiah($stats['total_pendapatan'] ?: 0) ?></div>
                        <div class="text-gray-600 text-sm mt-1">Total Pendapatan</div>
                    </div>
                    <div class="bg-white p-6 rounded-lg shadow-md border-l-4 border-blue-500">
                        <div class="text-2xl font-bold text-blue-600"><?= formatRupiah($stats['harga_rata_rata'] ?: 0) ?></div>
                        <div class="text-gray-600 text-sm mt-1">Harga Rata-rata</div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="bg-white p-6 rounded-lg shadow-md text-center mb-6">
                    <div class="flex flex-wrap justify-center gap-3">
                        <a href="layanan.php" class="inline-flex items-center px-4 py-2 bg-gray-500 hover:bg-gray-600 text-white rounded-lg transition-colors">
                            <i class="fa fa-arrow-left mr-2"></i> Kembali ke Daftar
                        </a>
                        
                        <?php if (AuthStatic::hasRole(['admin', 'sales'])): ?>
                        <a href="layanan-edit.php?id=<?= $layanan['id'] ?>" class="inline-flex items-center px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg transition-colors">
                            <i class="fa fa-edit mr-2"></i> Edit Layanan
                        </a>
                        <?php endif; ?>
                        
                        <a href="transaksi-add.php?layanan_id=<?= $layanan['id'] ?>" class="inline-flex items-center px-4 py-2 bg-green-500 hover:bg-green-600 text-white rounded-lg transition-colors">
                            <i class="fa fa-plus mr-2"></i> Buat Transaksi
                        </a>
                        
                        <button onclick="window.print()" class="inline-flex items-center px-4 py-2 bg-cyan-500 hover:bg-cyan-600 text-white rounded-lg transition-colors">
                            <i class="fa fa-print mr-2"></i> Cetak
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Detail Informasi -->
                    <div>
                        <div class="bg-white p-6 rounded-lg shadow-md border-l-4 border-blue-500 mb-6">
                            <h3 class="text-lg font-semibold text-gray-800 mb-5 pb-3 border-b border-gray-200"><i class="fa fa-info-circle mr-2"></i> Informasi Layanan</h3>
                            
                            <div class="flex justify-between items-center py-3 border-b border-gray-100 last:border-b-0">
                                <span class="font-semibold text-gray-600">Nama Layanan:</span>
                                <span class="text-gray-800"><?= htmlspecialchars($layanan['nama_layanan']) ?></span>
                            </div>
                            
                            <div class="flex justify-between items-center py-3 border-b border-gray-100 last:border-b-0">
                                <span class="font-semibold text-gray-600">Kode Layanan:</span>
                                <span class="text-gray-800"><?= htmlspecialchars($layanan['kode_layanan']) ?></span>
                            </div>
                            
                            <div class="flex justify-between items-center py-3 border-b border-gray-100 last:border-b-0">
                                <span class="font-semibold text-gray-600">Kategori:</span>
                                <span class="text-gray-800"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $layanan['jenis_layanan']))) ?></span>
                            </div>
                            
                            <div class="flex justify-between items-center py-3 border-b border-gray-100 last:border-b-0">
                                <span class="font-semibold text-gray-600">Harga:</span>
                                <span class="text-gray-800"><?= formatRupiah($layanan['harga']) ?></span>
                            </div>
                            
                            <?php if (isset($layanan['durasi_hari']) && $layanan['durasi_hari']): ?>
                            <div class="flex justify-between items-center py-3 border-b border-gray-100 last:border-b-0">
                                <span class="font-semibold text-gray-600">Durasi:</span>
                                <span class="text-gray-800"><?= $layanan['durasi_hari'] ?> hari</span>
                            </div>
                            <?php endif; ?>
                            
                            <div class="flex justify-between items-center py-3 border-b border-gray-100 last:border-b-0">
                                <span class="font-semibold text-gray-600">Status:</span>
                                <span class="text-gray-800">
                                    <span class="inline-block px-3 py-1 rounded-full text-sm font-medium <?= getStatusBadge($layanan['status']) === 'success' ? 'bg-green-500' : (getStatusBadge($layanan['status']) === 'warning' ? 'bg-yellow-500' : (getStatusBadge($layanan['status']) === 'info' ? 'bg-blue-500' : 'bg-gray-500')) ?> text-white">
                                        <?= getStatusText($layanan['status']) ?>
                                    </span>
                                </span>
                            </div>
                        </div>
                        
                        <!-- Detail Tambahan -->
                        <div class="bg-white p-6 rounded-lg shadow-md border-l-4 border-blue-500">
                            <h3 class="text-lg font-semibold text-gray-800 mb-5 pb-3 border-b border-gray-200"><i class="fa fa-file-text mr-2"></i> Detail Tambahan</h3>
                            
                            <?php if ($layanan['deskripsi']): ?>
                            <div class="flex justify-between items-start py-3 border-b border-gray-100 last:border-b-0">
                                <span class="font-semibold text-gray-600 w-1/3">Deskripsi:</span>
                                <span class="text-gray-800 w-2/3"><?= nl2br(htmlspecialchars($layanan['deskripsi'])) ?></span>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (isset($layanan['persyaratan']) && $layanan['persyaratan']): ?>
                            <div class="flex justify-between items-start py-3 border-b border-gray-100 last:border-b-0">
                                <span class="font-semibold text-gray-600 w-1/3">Persyaratan:</span>
                                <span class="text-gray-800 w-2/3"><?= nl2br(htmlspecialchars($layanan['persyaratan'])) ?></span>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (isset($layanan['garansi']) && $layanan['garansi']): ?>
                            <div class="flex justify-between items-center py-3 border-b border-gray-100 last:border-b-0">
                                <span class="font-semibold text-gray-600">Garansi:</span>
                                <span class="text-gray-800"><?= htmlspecialchars($layanan['garansi']) ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Informasi Sistem -->
                    <div>
                        <div class="bg-white p-6 rounded-lg shadow-md border-l-4 border-blue-500">
                            <h3 class="text-lg font-semibold text-gray-800 mb-5 pb-3 border-b border-gray-200"><i class="fa fa-cog mr-2"></i> Informasi Sistem</h3>
                            
                            <!-- Informasi pembuat tidak tersedia di tabel layanan -->
                            
                            <div class="flex justify-between items-center py-3 border-b border-gray-100 last:border-b-0">
                                <span class="font-semibold text-gray-600">Tanggal Dibuat:</span>
                                <span class="text-gray-800"><?= date('d/m/Y H:i', strtotime($layanan['created_at'])) ?></span>
                            </div>
                            
                            <?php if ($layanan['updated_at']): ?>
                            <div class="flex justify-between items-center py-3 border-b border-gray-100 last:border-b-0">
                                <span class="font-semibold text-gray-600">Terakhir Diubah:</span>
                                <span class="text-gray-800"><?= date('d/m/Y H:i', strtotime($layanan['updated_at'])) ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Recent Transactions -->
                <?php if (!empty($recent_transactions)): ?>
                <div class="bg-white rounded-lg shadow-md overflow-hidden">
                    <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                        <h4 class="text-lg font-semibold text-gray-800">Transaksi Terbaru (<?= count($recent_transactions) ?> transaksi)</h4>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Invoice</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Desa</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quantity</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Harga Satuan</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subtotal</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sales</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($recent_transactions as $transaction): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <a href="transaksi-view.php?id=<?= $transaction['id'] ?>" class="text-blue-600 hover:text-blue-800">
                                            <strong><?= htmlspecialchars($transaction['nomor_invoice']) ?></strong>
                                        </a>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?= date('d/m/Y', strtotime($transaction['tanggal_transaksi'])) ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?= htmlspecialchars($transaction['nama_desa']) ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?= number_format($transaction['quantity']) ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?= formatRupiah($transaction['harga_satuan']) ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?= formatRupiah($transaction['subtotal']) ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?= getTransactionStatusBadge($transaction['status']) === 'success' ? 'bg-green-100 text-green-800' : (getTransactionStatusBadge($transaction['status']) === 'warning' ? 'bg-yellow-100 text-yellow-800' : (getTransactionStatusBadge($transaction['status']) === 'info' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800')) ?>">
                                            <?= getTransactionStatusText($transaction['status']) ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?= htmlspecialchars($transaction['sales_name']) ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <a href="transaksi-view.php?id=<?= $transaction['id'] ?>" 
                                           class="inline-flex items-center px-3 py-1 bg-cyan-500 hover:bg-cyan-600 text-white rounded-md transition-colors" title="Lihat Detail">
                                            <i class="fa fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="px-6 py-4 border-t border-gray-200 text-center">
                        <a href="transaksi.php?layanan_id=<?= $layanan['id'] ?>" class="inline-flex items-center px-4 py-2 bg-gray-500 hover:bg-gray-600 text-white rounded-lg transition-colors">
                            <i class="fa fa-list mr-2"></i> Lihat Semua Transaksi
                        </a>
                    </div>
                </div>
                <?php endif; ?>
</div>

<script>
    // Auto-hide alerts
    setTimeout(function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(function(alert) {
            alert.style.transition = 'opacity 0.5s ease-out';
            alert.style.opacity = '0';
            setTimeout(function() {
                alert.style.display = 'none';
            }, 500);
        });
    }, 5000);
    
    // Print styles
    const printStyles = `
        <style media="print">
            .navbar, .sidebar, .breadcrumb, .action-buttons { display: none !important; }
            .content { margin: 0 !important; padding: 20px !important; }
            .layanan-header { background: #f8f9fa !important; color: #333 !important; }
            .info-card, .stat-card { border: 1px solid #ddd !important; }
            @page { margin: 1cm; }
        </style>
    `;
    
    window.addEventListener('beforeprint', function() {
        document.head.insertAdjacentHTML('beforeend', printStyles);
    });
</script>

        </main>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        // Toggle sidebar for mobile
        document.getElementById('toggleSidebarMobile').addEventListener('click', function() {
            const sidebar = document.getElementById('sidebar');
            const backdrop = document.getElementById('sidebarBackdrop');
            
            sidebar.classList.toggle('hidden');
            backdrop.classList.toggle('hidden');
        });
        
        // Close sidebar when clicking backdrop
        document.getElementById('sidebarBackdrop').addEventListener('click', function() {
            const sidebar = document.getElementById('sidebar');
            const backdrop = document.getElementById('sidebarBackdrop');
            
            sidebar.classList.add('hidden');
            backdrop.classList.add('hidden');
        });
        
        // User dropdown toggle
        document.getElementById('user-menu-button').addEventListener('click', function() {
            const dropdown = document.getElementById('dropdown-user');
            dropdown.classList.toggle('hidden');
        });
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const dropdown = document.getElementById('dropdown-user');
            const button = document.getElementById('user-menu-button');
            
            if (!button.contains(event.target) && !dropdown.contains(event.target)) {
                dropdown.classList.add('hidden');
            }
        });
    </script>
</body>
</html>
