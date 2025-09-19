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
/** @var Database $db */

// Fungsi helper untuk format rupiah
function formatRupiah($angka) {
    return 'Rp ' . number_format($angka, 0, ',', '.');
}

// Filter berdasarkan role
$role_condition = "";
$role_params = [];
if ($user['role'] === 'sales') {
    $role_condition = "AND t.user_id = ?";
    $role_params[] = $user['id'];
}

// Statistik Umum
$stats = [];

// Total Transaksi Hari Ini
$today_stats = $db->select("
    SELECT 
        COUNT(*) as total_transaksi,
        COALESCE(SUM(total_amount), 0) as total_nilai,
        COALESCE(SUM(CASE WHEN status_transaksi = 'selesai' THEN total_amount ELSE 0 END), 0) as nilai_selesai
    FROM transaksi t
    WHERE DATE(t.created_at) = CURDATE() {$role_condition}
", $role_params);

$stats['today'] = $today_stats[0];

// Total Transaksi Bulan Ini
$month_stats = $db->select("
    SELECT 
        COUNT(*) as total_transaksi,
        COALESCE(SUM(total_amount), 0) as total_nilai,
        COALESCE(SUM(CASE WHEN status_transaksi = 'selesai' THEN total_amount ELSE 0 END), 0) as nilai_selesai
    FROM transaksi t
    WHERE MONTH(t.created_at) = MONTH(CURDATE()) 
    AND YEAR(t.created_at) = YEAR(CURDATE()) {$role_condition}
", $role_params);

$stats['month'] = $month_stats[0];

// Status Transaksi
$status_stats = $db->select("
    SELECT 
        status_transaksi,
        COUNT(*) as jumlah,
        COALESCE(SUM(total_amount), 0) as total_nilai
    FROM transaksi t
    WHERE 1=1 {$role_condition}
    GROUP BY status_transaksi
    ORDER BY jumlah DESC
", $role_params);

// Piutang
$piutang_stats = $db->select("
    SELECT 
        COUNT(*) as total_piutang,
        COALESCE(SUM(p.jumlah_piutang), 0) as total_nilai_piutang,
        COALESCE(SUM(p.jumlah_piutang - COALESCE(pembayaran.total_bayar, 0)), 0) as total_sisa_piutang
    FROM piutang p
    JOIN transaksi t ON p.transaksi_id = t.id
    LEFT JOIN (
        SELECT piutang_id, SUM(jumlah_bayar) as total_bayar
        FROM pembayaran
        WHERE piutang_id IS NOT NULL
        GROUP BY piutang_id
    ) pembayaran ON p.id = pembayaran.piutang_id
    WHERE p.status IN ('belum_jatuh_tempo', 'mendekati_jatuh_tempo') {$role_condition}
", $role_params);

$stats['piutang'] = $piutang_stats[0];

// Transaksi per Metode Pembayaran
$payment_stats = $db->select("
    SELECT 
        metode_pembayaran,
        COUNT(*) as jumlah,
        COALESCE(SUM(total_amount), 0) as total_nilai
    FROM transaksi t
    WHERE MONTH(t.created_at) = MONTH(CURDATE()) 
    AND YEAR(t.created_at) = YEAR(CURDATE()) {$role_condition}
    GROUP BY metode_pembayaran
    ORDER BY jumlah DESC
", $role_params);

// Top 5 Desa dengan Transaksi Terbanyak
$top_desa = $db->select("
    SELECT 
        d.nama_desa,
        d.kecamatan,
        COUNT(t.id) as total_transaksi,
        COALESCE(SUM(t.total_amount), 0) as total_nilai
    FROM transaksi t
    JOIN desa d ON t.desa_id = d.id
    WHERE MONTH(t.created_at) = MONTH(CURDATE()) 
    AND YEAR(t.created_at) = YEAR(CURDATE()) {$role_condition}
    GROUP BY d.id, d.nama_desa, d.kecamatan
    ORDER BY total_transaksi DESC
    LIMIT 5
", $role_params);

// Transaksi Terbaru
$recent_transactions = $db->select("
    SELECT 
        t.id,
        t.nomor_invoice,
        t.tanggal_transaksi,
        t.total_amount,
        t.status_transaksi,
        t.metode_pembayaran,
        d.nama_desa,
        u.nama_lengkap as sales_name
    FROM transaksi t
    JOIN desa d ON t.desa_id = d.id
    JOIN users u ON t.user_id = u.id
    WHERE 1=1 {$role_condition}
    ORDER BY t.created_at DESC
    LIMIT 10
", $role_params);

// Data untuk Chart (3 bulan terakhir)
$chart_data = $db->select("
    SELECT 
        YEAR(t.created_at) as tahun,
        MONTH(t.created_at) as bulan,
        COUNT(*) as jumlah_transaksi,
        COALESCE(SUM(t.total_amount), 0) as total_nilai
    FROM transaksi t
    WHERE t.created_at >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH) {$role_condition}
    GROUP BY YEAR(t.created_at), MONTH(t.created_at)
    ORDER BY tahun ASC, bulan ASC
", $role_params);

// Statistik Penjualan per Kategori (Semua Waktu)
$kategori_stats = $db->select("
    SELECT 
        COALESCE(kp.nama_kategori, 'Tanpa Kategori') as kategori,
        COUNT(DISTINCT t.id) as total_transaksi,
        SUM(td.quantity) as total_quantity,
        COALESCE(SUM(td.subtotal), 0) as total_nilai
    FROM transaksi_detail td
    JOIN transaksi t ON td.transaksi_id = t.id
    JOIN produk p ON td.produk_id = p.id
    LEFT JOIN kategori_produk kp ON p.kategori_id = kp.id
    WHERE td.produk_id IS NOT NULL {$role_condition}
    GROUP BY kp.id, kp.nama_kategori
    ORDER BY total_nilai DESC
", $role_params);

// Produk Terlaris Bulan Ini
$top_products = $db->select("
    SELECT 
        p.nama_produk,
        SUM(td.quantity) as total_terjual,
        COALESCE(SUM(td.subtotal), 0) as total_nilai
    FROM transaksi_detail td
    JOIN transaksi t ON td.transaksi_id = t.id
    JOIN produk p ON td.produk_id = p.id
    WHERE MONTH(t.created_at) = MONTH(CURDATE()) 
    AND YEAR(t.created_at) = YEAR(CURDATE()) 
    AND td.produk_id IS NOT NULL {$role_condition}
    GROUP BY p.id, p.nama_produk
    ORDER BY total_terjual DESC
    LIMIT 5
", $role_params);

$page_title = 'Dashboard Transaksi';
require_once 'layouts/header.php';
?>

    <!-- Page Header -->
    <div class="mb-8">
        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between">
            <div class="flex-1">
                <div class="flex flex-col sm:flex-row sm:items-center sm:space-x-4">
                    <div class="ml-8">
                        <h1 class="text-2xl font-bold text-gray-900">Dashboard Transaksi</h1>
                        <p class="text-sm text-gray-600 mt-1">Ringkasan dan statistik transaksi</p>
                    </div>
                    <div class="mt-4 sm:mt-0 flex space-x-2">
                        <a href="transaksi-add.php" class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-lg text-sm font-medium text-white hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition-colors">
                            <i class="fas fa-plus mr-2"></i>
                            Transaksi Baru
                        </a>
                        <a href="transaksi.php" class="inline-flex items-center px-4 py-2 bg-primary-600 border border-transparent rounded-lg text-sm font-medium text-white hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 transition-colors">
                            <i class="fas fa-list mr-2"></i>
                            Daftar Transaksi
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Container -->
    <div class="ml-0 px-2 sm:px-4 lg:px-6 py-8 max-w-screen-xl">

        <!-- Statistik Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">

            <!-- Transaksi Bulan Ini -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-calendar-alt text-green-600"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Bulan Ini</p>
                        <p class="text-2xl font-semibold text-gray-900"><?= $stats['month']['total_transaksi'] ?></p>
                        <p class="text-sm text-gray-600"><?= formatRupiah($stats['month']['total_nilai']) ?></p>
                    </div>
                </div>
            </div>

            <!-- Total Piutang -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-yellow-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-credit-card text-yellow-600"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Piutang Aktif</p>
                        <p class="text-2xl font-semibold text-gray-900"><?= $stats['piutang']['total_piutang'] ?></p>
                        <p class="text-sm text-gray-600"><?= formatRupiah($stats['piutang']['total_sisa_piutang']) ?></p>
                    </div>
                </div>
            </div>

            <!-- Transaksi Selesai -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-purple-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-check-circle text-purple-600"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Selesai (Bulan)</p>
                        <p class="text-2xl font-semibold text-gray-900"><?= formatRupiah($stats['month']['nilai_selesai']) ?></p>
                        <p class="text-sm text-gray-600">Nilai transaksi selesai</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
            <!-- Chart Transaksi 3 Bulan -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Transaksi 3 Bulan Terakhir</h3>
                <canvas id="transactionChart" width="400" height="200"></canvas>
            </div>

            <!-- Penjualan per Kategori Produk -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Penjualan per Kategori Produk</h3>
                    <div class="flex items-center">
                        <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-tags text-blue-600"></i>
                        </div>
                    </div>
                </div>
                <div class="space-y-4">
                    <?php if (!empty($kategori_stats)): ?>
                        <?php foreach ($kategori_stats as $kategori): ?>
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                            <div class="flex items-center">
                                <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center mr-3">
                                    <i class="fas fa-cube text-blue-600"></i>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-900 capitalize"><?= htmlspecialchars($kategori['kategori'] ?? 'Tanpa Kategori') ?></p>
                                    <p class="text-xs text-gray-500"><?= $kategori['total_transaksi'] ?> transaksi</p>
                                </div>
                            </div>
                            <div class="text-right">
                                <p class="text-lg font-semibold text-gray-900"><?= $kategori['total_quantity'] ?></p>
                                <p class="text-xs text-gray-500">unit terjual</p>
                                <p class="text-sm font-medium text-blue-600"><?= formatRupiah($kategori['total_nilai']) ?></p>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-8">
                            <i class="fas fa-chart-bar text-gray-300 text-4xl mb-3"></i>
                            <p class="text-gray-500">Belum ada data kategori bulan ini</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
            <!-- Status Transaksi -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Status Transaksi</h3>
                <div class="space-y-3">
                    <?php foreach ($status_stats as $status): ?>
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <span class="w-3 h-3 rounded-full mr-3 <?php 
                                echo $status['status_transaksi'] === 'selesai' ? 'bg-green-500' : 
                                    ($status['status_transaksi'] === 'draft' ? 'bg-gray-500' : 
                                    ($status['status_transaksi'] === 'proses' ? 'bg-blue-500' : 'bg-yellow-500'));
                            ?>"></span>
                            <span class="text-sm font-medium text-gray-900 capitalize"><?= $status['status_transaksi'] ?></span>
                        </div>
                        <div class="text-right">
                            <p class="text-sm font-semibold text-gray-900"><?= $status['jumlah'] ?></p>
                            <p class="text-xs text-gray-500"><?= formatRupiah($status['total_nilai']) ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

        <!-- Top Desa -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-8">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Top 5 Desa (Bulan Ini)</h3>
            <div class="space-y-3">
                <?php foreach ($top_desa as $index => $desa): ?>
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <span class="w-6 h-6 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center text-xs font-semibold mr-3">
                            <?= $index + 1 ?>
                        </span>
                        <div>
                            <p class="text-sm font-medium text-gray-900"><?= htmlspecialchars($desa['nama_desa']) ?></p>
                            <p class="text-xs text-gray-500"><?= htmlspecialchars($desa['kecamatan']) ?></p>
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="text-sm font-semibold text-gray-900"><?= $desa['total_transaksi'] ?> transaksi</p>
                        <p class="text-xs text-gray-500"><?= formatRupiah($desa['total_nilai']) ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Produk Terlaris -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-8">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Produk Terlaris (Bulan Ini)</h3>
            <div class="space-y-3">
                <?php foreach ($top_products as $index => $product): ?>
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <span class="w-6 h-6 bg-green-100 text-green-600 rounded-full flex items-center justify-center text-xs font-semibold mr-3">
                            <?= $index + 1 ?>
                        </span>
                        <div>
                            <p class="text-sm font-medium text-gray-900"><?= htmlspecialchars($product['nama_produk']) ?></p>
                            <p class="text-xs text-gray-500"><?= number_format($product['total_terjual']) ?> unit</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="text-sm font-semibold text-gray-900"><?= formatRupiah($product['total_nilai']) ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Transaksi Terbaru -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">Transaksi Terbaru</h3>
            </div>
            <div>
                <table class="w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Invoice</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Desa</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sales</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($recent_transactions as $transaction): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <a href="transaksi-view.php?id=<?= $transaction['id'] ?>" class="text-blue-600 hover:text-blue-800 font-medium">
                                    <?= htmlspecialchars($transaction['nomor_invoice']) ?>
                                </a>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?= date('d/m/Y', strtotime($transaction['tanggal_transaksi'])) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?= htmlspecialchars($transaction['nama_desa']) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?= formatRupiah($transaction['total_amount']) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php
                                    echo $transaction['status_transaksi'] === 'selesai' ? 'bg-green-100 text-green-800' :
                                        ($transaction['status_transaksi'] === 'draft' ? 'bg-gray-100 text-gray-800' :
                                        ($transaction['status_transaksi'] === 'proses' ? 'bg-blue-100 text-blue-800' : 'bg-yellow-100 text-yellow-800'));
                                ?>">
                                    <?= ucfirst($transaction['status_transaksi']) ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?= htmlspecialchars($transaction['sales_name']) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <a href="transaksi-view.php?id=<?= $transaction['id'] ?>" class="text-blue-600 hover:text-blue-900 mr-3">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <?php if (AuthStatic::hasRole(['admin', 'sales']) && $transaction['status_transaksi'] === 'draft'): ?>
                                <a href="transaksi-edit.php?id=<?= $transaction['id'] ?>" class="text-green-600 hover:text-green-900">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Chart Transaksi 3 Bulan
const ctx = document.getElementById('transactionChart').getContext('2d');
const chartData = <?= json_encode($chart_data) ?>;

// Prepare data for chart
const labels = [];
const dataValues = [];
const valueAmounts = [];

// Nama bulan dalam bahasa Indonesia
const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 
                   'Jul', 'Ags', 'Sep', 'Okt', 'Nov', 'Des'];

// Fill missing months (3 bulan terakhir)
for (let i = 2; i >= 0; i--) {
    const date = new Date();
    date.setMonth(date.getMonth() - i);
    const year = date.getFullYear();
    const month = date.getMonth() + 1;
    
    const found = chartData.find(item => 
        parseInt(item.tahun) === year && parseInt(item.bulan) === month
    );
    
    labels.push(monthNames[month - 1] + ' ' + year);
    dataValues.push(found ? parseInt(found.jumlah_transaksi) : 0);
    valueAmounts.push(found ? parseInt(found.total_nilai) : 0);
}

const transactionChart = new Chart(ctx, {
    type: 'line',
    data: {
        labels: labels,
        datasets: [{
            label: 'Jumlah Transaksi',
            data: dataValues,
            borderColor: 'rgb(59, 130, 246)',
            backgroundColor: 'rgba(59, 130, 246, 0.1)',
            tension: 0.1,
            yAxisID: 'y'
        }, {
            label: 'Nilai Transaksi (Juta)',
            data: valueAmounts.map(val => val / 1000000),
            borderColor: 'rgb(16, 185, 129)',
            backgroundColor: 'rgba(16, 185, 129, 0.1)',
            tension: 0.1,
            yAxisID: 'y1'
        }]
    },
    options: {
        responsive: true,
        interaction: {
            mode: 'index',
            intersect: false,
        },
        scales: {
            x: {
                display: true,
                title: {
                    display: true,
                    text: 'Bulan'
                }
            },
            y: {
                type: 'linear',
                display: true,
                position: 'left',
                title: {
                    display: true,
                    text: 'Jumlah Transaksi'
                }
            },
            y1: {
                type: 'linear',
                display: true,
                position: 'right',
                title: {
                    display: true,
                    text: 'Nilai (Juta Rupiah)'
                },
                grid: {
                    drawOnChartArea: false,
                },
            }
        }
    }
});
</script>

<!-- Main Container End -->
</div>

<?php require_once 'layouts/footer.php'; ?>