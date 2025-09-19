<?php
require_once 'config/database.php';
require_once 'config/auth.php';

// Cek autentikasi dan role
if (!AuthStatic::isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Cek role akses
if (!AuthStatic::hasRole(['admin', 'finance'])) {
    header('Location: index.php?error=access_denied');
    exit;
}

$user = AuthStatic::getCurrentUser();
$db = getDatabase();
/** @var Database $db */

// Filter
$bank_filter = $_GET['bank_id'] ?? '';
$periode_bulan = $_GET['periode_bulan'] ?? date('n');
$periode_tahun = $_GET['periode_tahun'] ?? date('Y');

// Ambil daftar bank
$bank_list = $db->select("SELECT id, nama_bank, jenis_bank FROM bank WHERE status = 'aktif' ORDER BY nama_bank");

// Query saldo bank
$where_conditions = [];
$params = [];

if (!empty($bank_filter)) {
    $where_conditions[] = "sb.bank_id = ?";
    $params[] = $bank_filter;
}

$where_conditions[] = "sb.periode_bulan = ?";
$where_conditions[] = "sb.periode_tahun = ?";
$params[] = $periode_bulan;
$params[] = $periode_tahun;

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

$query = "
    SELECT 
        sb.*,
        b.nama_bank,
        b.jenis_bank,
        b.kode_bank
    FROM saldo_bank sb
    LEFT JOIN bank b ON sb.bank_id = b.id
    {$where_clause}
    ORDER BY b.nama_bank
";

$saldo_list = $db->select($query, $params);

// Ambil mutasi kas untuk periode ini
$mutasi_query = "
    SELECT 
        mk.*,
        b.nama_bank,
        u.nama_lengkap as user_nama
    FROM mutasi_kas mk
    LEFT JOIN bank b ON mk.bank_id = b.id
    LEFT JOIN users u ON mk.user_id = u.id
    WHERE MONTH(mk.tanggal_mutasi) = ? AND YEAR(mk.tanggal_mutasi) = ?
";

$mutasi_params = [$periode_bulan, $periode_tahun];

if (!empty($bank_filter)) {
    $mutasi_query .= " AND mk.bank_id = ?";
    $mutasi_params[] = $bank_filter;
}

$mutasi_query .= " ORDER BY mk.tanggal_mutasi DESC, mk.created_at DESC LIMIT 50";

$mutasi_list = $db->select($mutasi_query, $mutasi_params);

$page_title = 'Saldo Bank & Mutasi Kas';
require_once 'layouts/header.php';
?>

<!-- Page Header -->
<div class="mb-8">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Saldo Bank & Mutasi Kas</h1>
            <p class="text-sm text-gray-600 mt-1">Kelola dan pantau saldo bank serta mutasi kas</p>
        </div>

    </div>
</div>

<!-- Main Container -->
<div class="max-w-7xl ml-0 px-4 sm:px-6 lg:px-8 py-8">
    <!-- Filter -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-6 gap-4">
            <div class="md:col-span-2">
                <label for="bank_id" class="block text-sm font-medium text-gray-700 mb-2">Bank</label>
                <select name="bank_id" id="bank_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                    <option value="">Semua Bank</option>
                    <?php foreach ($bank_list as $bank): ?>
                    <option value="<?= $bank['id'] ?>" <?= $bank_filter == $bank['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($bank['nama_bank'] ?? '') ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="periode_bulan" class="block text-sm font-medium text-gray-700 mb-2">Bulan</label>
                <select name="periode_bulan" id="periode_bulan" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                    <?php for ($i = 1; $i <= 12; $i++): ?>
                    <option value="<?= $i ?>" <?= $periode_bulan == $i ? 'selected' : '' ?>>
                        <?= date('F', mktime(0, 0, 0, $i, 1)) ?>
                    </option>
                    <?php endfor; ?>
                </select>
            </div>
            <div>
                <label for="periode_tahun" class="block text-sm font-medium text-gray-700 mb-2">Tahun</label>
                <select name="periode_tahun" id="periode_tahun" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                    <?php for ($year = date('Y') - 2; $year <= date('Y') + 1; $year++): ?>
                    <option value="<?= $year ?>" <?= $periode_tahun == $year ? 'selected' : '' ?>>
                        <?= $year ?>
                    </option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="md:col-span-2 flex items-end space-x-2">
                <button type="submit" class="bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-lg font-medium transition-colors">
                    <i class="fas fa-search mr-2"></i>Filter
                </button>
                <a href="mutasi-kas-add.php" class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-lg font-medium text-white hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition-colors">
                    <i class="fas fa-plus mr-2"></i>
                    Tambah Mutasi
                </a>
            </div>
        </form>
    </div>

    <!-- Saldo Bank -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-8">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                <i class="fas fa-university mr-2 text-primary-600"></i>
                Saldo Bank - <?= date('F Y', mktime(0, 0, 0, $periode_bulan, 1, $periode_tahun)) ?>
            </h3>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Bank</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Jenis</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Saldo Awal</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Pemasukan</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Pengeluaran</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Saldo Akhir</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($saldo_list)): ?>
                    <tr>
                        <td colspan="6" class="px-6 py-4 text-center text-gray-500">
                            <i class="fas fa-info-circle mr-2"></i>
                            Tidak ada data saldo untuk periode ini
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php 
                        $total_saldo_awal = 0;
                        $total_pemasukan = 0;
                        $total_pengeluaran = 0;
                        $total_saldo_akhir = 0;
                        ?>
                        <?php foreach ($saldo_list as $saldo): ?>
                            <?php 
                            $total_saldo_awal += $saldo['saldo_awal'];
                            $total_pemasukan += $saldo['saldo_masuk'];
                            $total_pengeluaran += $saldo['saldo_keluar'];
                            $total_saldo_akhir += $saldo['saldo_akhir'];
                            ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($saldo['nama_bank'] ?? '') ?></div>
                                <div class="text-sm text-gray-500"><?= htmlspecialchars($saldo['kode_bank'] ?? '') ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                                    <?= ucfirst($saldo['jenis_bank']) ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-900">
                                Rp <?= number_format($saldo['saldo_awal'], 0, ',', '.') ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-green-600">
                                Rp <?= number_format($saldo['saldo_masuk'], 0, ',', '.') ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-red-600">
                                Rp <?= number_format($saldo['saldo_keluar'], 0, ',', '.') ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-semibold <?= $saldo['saldo_akhir'] >= 0 ? 'text-green-600' : 'text-red-600' ?>">
                                Rp <?= number_format($saldo['saldo_akhir'], 0, ',', '.') ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <!-- Total Row -->
                        <tr class="bg-gray-100 font-semibold">
                            <td colspan="2" class="px-6 py-4 text-sm text-gray-900">TOTAL</td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-900">
                                Rp <?= number_format($total_saldo_awal, 0, ',', '.') ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-green-600">
                                Rp <?= number_format($total_pemasukan, 0, ',', '.') ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-red-600">
                                Rp <?= number_format($total_pengeluaran, 0, ',', '.') ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-bold <?= $total_saldo_akhir >= 0 ? 'text-green-600' : 'text-red-600' ?>">
                                Rp <?= number_format($total_saldo_akhir, 0, ',', '.') ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Mutasi Kas Terbaru -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                <i class="fas fa-exchange-alt mr-2 text-primary-600"></i>
                Mutasi Kas Terbaru - <?= date('F Y', mktime(0, 0, 0, $periode_bulan, 1, $periode_tahun)) ?>
            </h3>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Bank</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Jenis</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Keterangan</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Jumlah</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($mutasi_list)): ?>
                    <tr>
                        <td colspan="6" class="px-6 py-4 text-center text-gray-500">
                            <i class="fas fa-info-circle mr-2"></i>
                            Tidak ada mutasi kas untuk periode ini
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($mutasi_list as $mutasi): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?= date('d/m/Y', strtotime($mutasi['tanggal_mutasi'])) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?= htmlspecialchars($mutasi['nama_bank'] ?? '') ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?= $mutasi['jenis_mutasi'] === 'masuk' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                    <?= $mutasi['jenis_mutasi'] === 'masuk' ? 'Masuk' : 'Keluar' ?>
                                </span>
                                <div class="text-xs text-gray-500 mt-1"><?= ucfirst($mutasi['jenis_transaksi']) ?></div>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-900">
                                <?= htmlspecialchars($mutasi['keterangan'] ?? '') ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium <?= $mutasi['jenis_mutasi'] === 'masuk' ? 'text-green-600' : 'text-red-600' ?>">
                                <?= $mutasi['jenis_mutasi'] === 'masuk' ? '+' : '-' ?> Rp <?= number_format($mutasi['jumlah'], 0, ',', '.') ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?= htmlspecialchars($mutasi['user_nama'] ?? '') ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

<!-- Main Container End -->
</div>

<?php require_once 'layouts/footer.php'; ?>