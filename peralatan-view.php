<?php
require_once 'config/database.php';
require_once 'config/auth.php';

// Cek autentikasi dan role
if (!AuthStatic::isLoggedIn()) {
    header('Location: login.php');
    exit;
}

if (!AuthStatic::hasRole(['admin', 'supervisor', 'teknisi', 'programmer'])) {
    header('Location: index.php?error=access_denied');
    exit;
}

$user = AuthStatic::getCurrentUser();
/** @var Database $db */
$db = getDatabase();

$id = intval($_GET['id'] ?? 0);

if ($id <= 0) {
    header('Location: peralatan.php?error=invalid_id');
    exit;
}

// Ambil data peralatan
$peralatan = $db->select(
    "SELECT * FROM peralatan WHERE id = ?",
    [$id]
);

if (empty($peralatan)) {
    header('Location: peralatan.php?error=not_found');
    exit;
}

$peralatan = $peralatan[0];

// Ambil riwayat penggunaan peralatan
$riwayat_penggunaan = $db->select("
    SELECT 
        jp.*, 
        jk.tanggal_kunjungan,
        jk.waktu_mulai,
        jk.waktu_selesai,
        jk.status as status_jadwal,
        u.nama_lengkap as nama_teknisi
    FROM jadwal_peralatan jp
    JOIN jadwal_kunjungan jk ON jp.jadwal_id = jk.id
    LEFT JOIN users u ON jk.teknisi_id = u.id
    WHERE jp.peralatan_id = ?
    ORDER BY jk.tanggal_kunjungan DESC, jk.waktu_mulai DESC
    LIMIT 10
", [$id]);

// Helper functions
function getKondisiBadge($kondisi) {
    $badges = [
        'baik' => 'success',
        'rusak' => 'danger',
        'maintenance' => 'warning'
    ];
    return $badges[$kondisi] ?? 'default';
}

function getStatusBadge($status) {
    $badges = [
        'tersedia' => 'success',
        'digunakan' => 'info',
        'tidak_tersedia' => 'danger'
    ];
    return $badges[$status] ?? 'default';
}

function formatRupiah($amount) {
    return $amount ? 'Rp ' . number_format($amount, 0, ',', '.') : '-';
}

function formatTanggal($date) {
    if (!$date) return '-';
    return date('d/m/Y', strtotime($date));
}

function formatDateTime($datetime) {
    if (!$datetime) return '-';
    return date('d/m/Y H:i', strtotime($datetime));
}

$page_title = 'Detail Peralatan - ' . $peralatan['nama_peralatan'];
require_once 'layouts/header.php';
?>

<!-- Page Header -->
<div class="bg-white shadow-sm border-b border-gray-200 mb-6">
    <div class="px-6 py-4">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Detail Peralatan</h1>
                <p class="text-sm text-gray-600 mt-1"><?php echo htmlspecialchars($peralatan['nama_peralatan']); ?></p>
            </div>
            <div class="mt-4 sm:mt-0 flex space-x-2">
                <a href="peralatan.php" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 transition-colors">
                    <i class="fas fa-arrow-left mr-2"></i>Kembali
                </a>
                <?php if (AuthStatic::hasRole(['admin', 'supervisor'])): ?>
                <a href="peralatan-edit.php?id=<?php echo $peralatan['id']; ?>" class="inline-flex items-center px-4 py-2 bg-yellow-500 border border-transparent rounded-lg text-sm font-medium text-white hover:bg-yellow-600 focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:ring-offset-2 transition-colors">
                    <i class="fas fa-edit mr-2"></i>Edit
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Main Container -->
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

    <!-- Content Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Informasi Utama -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-6">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                        <i class="fas fa-info-circle text-primary-600 mr-2"></i>
                        Informasi Peralatan
                    </h3>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-4">
                            <div class="flex justify-between py-2 border-b border-gray-100">
                                <span class="font-medium text-gray-700">Kode Peralatan:</span>
                                <span class="bg-gray-100 px-2 py-1 rounded text-sm font-mono"><?php echo htmlspecialchars($peralatan['kode_peralatan']); ?></span>
                            </div>
                            <div class="flex justify-between py-2 border-b border-gray-100">
                                <span class="font-medium text-gray-700">Nama Peralatan:</span>
                                <span class="text-gray-900"><?php echo htmlspecialchars($peralatan['nama_peralatan']); ?></span>
                            </div>
                            <div class="flex justify-between py-2 border-b border-gray-100">
                                <span class="font-medium text-gray-700">Kategori:</span>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800"><?php echo htmlspecialchars($peralatan['kategori']); ?></span>
                            </div>
                            <div class="flex justify-between py-2 border-b border-gray-100">
                                <span class="font-medium text-gray-700">Kondisi:</span>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php 
                                    echo $peralatan['kondisi'] == 'baik' ? 'bg-green-100 text-green-800' : 
                                         ($peralatan['kondisi'] == 'rusak' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800'); 
                                ?>">
                                    <?php echo ucfirst($peralatan['kondisi']); ?>
                                </span>
                            </div>
                            <div class="flex justify-between py-2 border-b border-gray-100">
                                <span class="font-medium text-gray-700">Status:</span>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php 
                                    echo $peralatan['status'] == 'tersedia' ? 'bg-green-100 text-green-800' : 
                                         ($peralatan['status'] == 'digunakan' ? 'bg-blue-100 text-blue-800' : 'bg-red-100 text-red-800'); 
                                ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $peralatan['status'])); ?>
                                </span>
                            </div>
                            <div class="flex justify-between py-2 border-b border-gray-100">
                                <span class="font-medium text-gray-700">Lokasi Penyimpanan:</span>
                                <span class="text-gray-900"><?php echo htmlspecialchars($peralatan['lokasi_penyimpanan']); ?></span>
                            </div>
                        </div>
                        <div class="space-y-4">
                            <div class="flex justify-between py-2 border-b border-gray-100">
                                <span class="font-medium text-gray-700">Tanggal Beli:</span>
                                <span class="text-gray-900"><?php echo formatTanggal($peralatan['tanggal_beli']); ?></span>
                            </div>
                            <div class="flex justify-between py-2 border-b border-gray-100">
                                <span class="font-medium text-gray-700">Harga Beli:</span>
                                <span class="text-gray-900"><?php echo formatRupiah($peralatan['harga_beli']); ?></span>
                            </div>
                            <div class="flex justify-between py-2 border-b border-gray-100">
                                <span class="font-medium text-gray-700">Masa Garansi:</span>
                                <span class="text-gray-900"><?php echo htmlspecialchars($peralatan['masa_garansi'] ?: '-'); ?></span>
                            </div>
                            <div class="flex justify-between py-2 border-b border-gray-100">
                                <span class="font-medium text-gray-700">Dibuat:</span>
                                <span class="text-gray-900"><?php echo formatDateTime($peralatan['created_at']); ?></span>
                            </div>
                            <div class="flex justify-between py-2 border-b border-gray-100">
                                <span class="font-medium text-gray-700">Diperbarui:</span>
                                <span class="text-gray-900"><?php echo formatDateTime($peralatan['updated_at']); ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (!empty($peralatan['deskripsi'])): ?>
                    <div class="mt-6 pt-6 border-t border-gray-200">
                        <h6 class="text-lg font-semibold text-gray-900 mb-3">Deskripsi:</h6>
                        <p class="text-gray-600 leading-relaxed"><?php echo nl2br(htmlspecialchars($peralatan['deskripsi'])); ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Statistik dan Info Tambahan -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-6">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                        <i class="fas fa-chart-bar text-primary-600 mr-2"></i>
                        Statistik Penggunaan
                    </h3>
                </div>
                <div class="p-6">
                    <?php
                    $stats = $db->select("
                        SELECT 
                            COUNT(*) as total_penggunaan,
                            COUNT(CASE WHEN jk.status = 'selesai' THEN 1 END) as penggunaan_selesai,
                            COUNT(CASE WHEN jk.status = 'berlangsung' THEN 1 END) as penggunaan_aktif
                        FROM jadwal_peralatan jp
                        JOIN jadwal_kunjungan jk ON jp.jadwal_id = jk.id
                        WHERE jp.peralatan_id = ?
                    ", [$id]);
                    
                    $stat = $stats[0] ?? ['total_penggunaan' => 0, 'penggunaan_selesai' => 0, 'penggunaan_aktif' => 0];
                    ?>
                    
                    <div class="space-y-4">
                        <div class="text-center p-4 border border-gray-200 rounded-lg">
                            <div class="text-3xl font-bold text-blue-600 mb-2"><?php echo $stat['total_penggunaan']; ?></div>
                            <div class="text-sm font-medium text-gray-600">Total Penggunaan</div>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="text-center p-4 border border-gray-200 rounded-lg">
                                <div class="text-2xl font-bold text-green-600 mb-2"><?php echo $stat['penggunaan_selesai']; ?></div>
                                <div class="text-sm font-medium text-gray-600">Selesai</div>
                            </div>
                            <div class="text-center p-4 border border-gray-200 rounded-lg">
                                <div class="text-2xl font-bold text-blue-500 mb-2"><?php echo $stat['penggunaan_aktif']; ?></div>
                                <div class="text-sm font-medium text-gray-600">Aktif</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- QR Code -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-6">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                        <i class="fas fa-qrcode text-blue-600 mr-2"></i>
                        QR Code
                    </h3>
                </div>
                <div class="p-6 text-center">
                    <div id="qrcode" class="mb-4 flex justify-center"></div>
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <i class="fas fa-qrcode text-4xl text-gray-400 mb-3"></i>
                        <p class="text-gray-500 text-sm mb-3">QR Code untuk peralatan ini</p>
                        <button type="button" class="inline-flex items-center px-4 py-2 border border-blue-300 text-sm font-medium rounded-md text-blue-700 bg-white hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500" onclick="generateQR()">
                            <i class="fas fa-download mr-2"></i>
                            Generate QR
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Riwayat Penggunaan -->
    <div class="bg-white shadow-sm border border-gray-200 rounded-lg mb-6">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                <i class="fas fa-history text-blue-600 mr-2"></i>
                Riwayat Penggunaan
            </h3>
        </div>
        <div class="p-6">
            <?php if (empty($riwayat_penggunaan)): ?>
            <div class="text-center py-8">
                <i class="fas fa-history text-4xl text-gray-300 mb-4"></i>
                <p class="text-gray-500">Belum ada riwayat penggunaan untuk peralatan ini.</p>
            </div>
            <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Waktu</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Teknisi</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kondisi Awal</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kondisi Akhir</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Catatan</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($riwayat_penggunaan as $riwayat): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900"><?php echo formatTanggal($riwayat['tanggal_kunjungan']); ?></td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                <?php echo date('H:i', strtotime($riwayat['waktu_mulai'])); ?>
                                <?php if ($riwayat['waktu_selesai']): ?>
                                - <?php echo date('H:i', strtotime($riwayat['waktu_selesai'])); ?>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($riwayat['nama_teknisi'] ?: '-'); ?></td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm">
                                <?php if ($riwayat['kondisi_awal']): ?>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php 
                                    echo $riwayat['kondisi_awal'] == 'baik' ? 'bg-green-100 text-green-800' : 
                                         ($riwayat['kondisi_awal'] == 'rusak' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800'); 
                                ?>">
                                    <?php echo ucfirst($riwayat['kondisi_awal']); ?>
                                </span>
                                <?php else: ?>
                                <span class="text-gray-500">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm">
                                <?php if ($riwayat['kondisi_akhir']): ?>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php 
                                    echo $riwayat['kondisi_akhir'] == 'baik' ? 'bg-green-100 text-green-800' : 
                                         ($riwayat['kondisi_akhir'] == 'rusak' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800'); 
                                ?>">
                                    <?php echo ucfirst($riwayat['kondisi_akhir']); ?>
                                </span>
                                <?php else: ?>
                                <span class="text-gray-500">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm">
                                <?php
                                $status_badges = [
                                    'dijadwalkan' => 'bg-gray-100 text-gray-800',
                                    'berlangsung' => 'bg-blue-100 text-blue-800',
                                    'selesai' => 'bg-green-100 text-green-800',
                                    'dibatalkan' => 'bg-red-100 text-red-800'
                                ];
                                $badge_class = $status_badges[$riwayat['status_jadwal']] ?? 'bg-gray-100 text-gray-800';
                                ?>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $badge_class; ?>">
                                    <?php echo ucfirst($riwayat['status_jadwal']); ?>
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-900">
                                <?php if ($riwayat['catatan']): ?>
                                <span class="text-gray-600" title="<?php echo htmlspecialchars($riwayat['catatan']); ?>">
                                    <?php echo htmlspecialchars(substr($riwayat['catatan'], 0, 30)) . (strlen($riwayat['catatan']) > 30 ? '...' : ''); ?>
                                </span>
                                <?php else: ?>
                                <span class="text-gray-500">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if (count($riwayat_penggunaan) >= 10): ?>
            <div class="text-center mt-4">
                <p class="text-gray-500 text-sm">Menampilkan 10 riwayat terbaru</p>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function generateQR() {
    // Implementasi generate QR code
    const peralatanData = {
        id: <?php echo $peralatan['id']; ?>,
        kode: '<?php echo htmlspecialchars($peralatan['kode_peralatan']); ?>',
        nama: '<?php echo htmlspecialchars($peralatan['nama_peralatan']); ?>'
    };
    
    // Untuk sementara, tampilkan alert
    alert('Fitur generate QR Code akan segera tersedia.\n\nData peralatan:\n' + 
          'Kode: ' + peralatanData.kode + '\n' +
          'Nama: ' + peralatanData.nama);
}

// Tooltip untuk catatan yang terpotong (vanilla JS)
document.addEventListener('DOMContentLoaded', function() {
    const tooltipElements = document.querySelectorAll('[title]');
    
    tooltipElements.forEach(function(element) {
        element.addEventListener('mouseenter', function() {
            // Simple tooltip implementation using title attribute
            // The browser's native tooltip will handle this
        });
    });
});
</script>

<?php require_once 'layouts/footer.php'; ?>