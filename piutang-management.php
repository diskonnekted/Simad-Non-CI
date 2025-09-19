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

// Fungsi helper untuk format tanggal
function formatTanggal($tanggal) {
    return date('d/m/Y', strtotime($tanggal));
}

// Handle pembayaran piutang
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        if ($_POST['action'] === 'bayar_piutang') {
            $piutang_id = (int)$_POST['piutang_id'];
            $jumlah_bayar = (float)$_POST['jumlah_bayar'];
            $metode_pembayaran = $_POST['metode_pembayaran'];
            $bank_id = !empty($_POST['bank_id']) ? (int)$_POST['bank_id'] : null;
            $keterangan = $_POST['keterangan'] ?? '';
            
            // Validasi piutang
            $piutang = $db->select("SELECT * FROM piutang WHERE id = ? AND status = 'aktif'", [$piutang_id]);
            if (empty($piutang)) {
                throw new Exception('Piutang tidak ditemukan atau sudah lunas');
            }
            
            $piutang = $piutang[0];
            
            if ($jumlah_bayar <= 0) {
                throw new Exception('Jumlah pembayaran harus lebih dari 0');
            }
            
            if ($jumlah_bayar > $piutang['sisa_piutang']) {
                throw new Exception('Jumlah pembayaran tidak boleh melebihi sisa piutang');
            }
            
            $db->beginTransaction();
            
            // Insert pembayaran piutang
            $db->insert('pembayaran_piutang', [
                'piutang_id' => $piutang_id,
                'jumlah_bayar' => $jumlah_bayar,
                'metode_pembayaran' => $metode_pembayaran,
                'bank_id' => $bank_id,
                'keterangan' => $keterangan,
                'tanggal_bayar' => date('Y-m-d'),
                'user_id' => $user['id'],
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            // Update sisa piutang
            $sisa_baru = $piutang['sisa_piutang'] - $jumlah_bayar;
            $status_baru = $sisa_baru <= 0 ? 'lunas' : 'aktif';
            
            $db->update('piutang', [
                'sisa_piutang' => $sisa_baru,
                'status' => $status_baru,
                'updated_at' => date('Y-m-d H:i:s')
            ], 'id = ?', [$piutang_id]);
            
            // Catat mutasi kas masuk untuk pembayaran piutang
            $transaksi = $db->select("SELECT nomor_invoice FROM transaksi WHERE id = ?", [$piutang['transaksi_id']]);
            $nomor_invoice = $transaksi[0]['nomor_invoice'] ?? 'Unknown';
            
            $db->insert('mutasi_kas', [
                'bank_id' => $bank_id,
                'jenis_mutasi' => 'masuk',
                'jenis_transaksi' => 'pembayaran_piutang',
                'referensi_id' => $piutang_id,
                'referensi_tabel' => 'piutang',
                'jumlah' => $jumlah_bayar,
                'keterangan' => "Pembayaran piutang transaksi {$nomor_invoice}",
                'tanggal_mutasi' => date('Y-m-d'),
                'user_id' => $user['id']
            ]);
            
            $db->commit();
            
            $_SESSION['success'] = 'Pembayaran piutang berhasil dicatat';
            header('Location: piutang-management.php');
            exit;
            
        } elseif ($_POST['action'] === 'update_status') {
            $transaksi_id = (int)$_POST['transaksi_id'];
            $status_baru = $_POST['status_baru'];
            
            // Validasi status
            $valid_status = ['draft', 'proses', 'selesai'];
            if (!in_array($status_baru, $valid_status)) {
                throw new Exception('Status tidak valid');
            }
            
            // Update status transaksi
            $db->update('transaksi', [
                'status_transaksi' => $status_baru,
                'updated_at' => date('Y-m-d H:i:s')
            ], 'id = ?', [$transaksi_id]);
            
            $_SESSION['success'] = 'Status transaksi berhasil diperbarui';
            header('Location: piutang-management.php');
            exit;
        }
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollback();
        }
        $_SESSION['error'] = $e->getMessage();
    }
}

// Parameter filter
$status_filter = $_GET['status'] ?? 'aktif';
$desa_filter = $_GET['desa_id'] ?? '';
$search = $_GET['search'] ?? '';

// Filter berdasarkan role
$role_condition = "";
$role_params = [];
if ($user['role'] === 'sales') {
    $role_condition = "AND t.user_id = ?";
    $role_params[] = $user['id'];
}

// Build WHERE conditions untuk piutang
$where_conditions = [];
$params = $role_params;

if (!empty($status_filter)) {
    $where_conditions[] = "p.status = ?";
    $params[] = $status_filter;
}

if (!empty($desa_filter)) {
    $where_conditions[] = "t.desa_id = ?";
    $params[] = $desa_filter;
}

if (!empty($search)) {
    $where_conditions[] = "(t.nomor_invoice LIKE ? OR d.nama_desa LIKE ? OR u.nama_lengkap LIKE ?)";
    $search_param = "%{$search}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_clause = "WHERE 1=1";
if (!empty($where_conditions)) {
    $where_clause .= " AND " . implode(' AND ', $where_conditions);
}
if (!empty($role_condition)) {
    $where_clause .= " " . $role_condition;
}

// Query piutang
$piutang_query = "
    SELECT 
        p.*,
        t.nomor_invoice,
        t.tanggal_transaksi,
        t.total_amount,
        t.status_transaksi,
        d.nama_desa,
        d.kecamatan,
        u.nama_lengkap as sales_name,
        COALESCE(SUM(pp.jumlah_bayar), 0) as total_dibayar
    FROM piutang p
    JOIN transaksi t ON p.transaksi_id = t.id
    JOIN desa d ON t.desa_id = d.id
    JOIN users u ON t.user_id = u.id
    LEFT JOIN pembayaran_piutang pp ON p.id = pp.piutang_id
    {$where_clause}
    GROUP BY p.id
    ORDER BY p.created_at DESC
";

$piutang_data = $db->select($piutang_query, $params);

// Statistik piutang
$stats_query = "
    SELECT 
        COUNT(CASE WHEN p.status = 'aktif' THEN 1 END) as piutang_aktif,
        COUNT(CASE WHEN p.status = 'lunas' THEN 1 END) as piutang_lunas,
        COALESCE(SUM(CASE WHEN p.status = 'aktif' THEN (p.jumlah_piutang - COALESCE(pembayaran.total_bayar, 0)) ELSE 0 END), 0) as total_sisa_piutang,
        COALESCE(SUM(p.jumlah_piutang), 0) as total_piutang,
        COALESCE(SUM(CASE WHEN p.status = 'lunas' THEN p.jumlah_piutang ELSE 0 END), 0) as total_lunas
    FROM piutang p
    JOIN transaksi t ON p.transaksi_id = t.id
    LEFT JOIN (
        SELECT piutang_id, SUM(jumlah_bayar) as total_bayar
        FROM pembayaran_piutang
        GROUP BY piutang_id
    ) pembayaran ON p.id = pembayaran.piutang_id
    WHERE 1=1 {$role_condition}
";

$stats = $db->select($stats_query, $role_params)[0];

// Data untuk dropdown
$desa_list = $db->select("SELECT id, nama_desa, kecamatan FROM desa WHERE status = 'aktif' ORDER BY nama_desa");
$bank_list = $db->select("SELECT id, nama_bank FROM bank WHERE status = 'aktif' ORDER BY nama_bank");

$page_title = 'Manajemen Piutang';
require_once 'layouts/header.php';
?>

<div class="min-h-screen bg-gray-50 py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Page Header -->
        <div class="mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Manajemen Piutang</h1>
                    <p class="mt-2 text-gray-600">Kelola status pembayaran dan piutang pelanggan</p>
                </div>
                <div class="flex space-x-3">
                    <a href="transaksi-dashboard.php" class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 transition duration-200">
                        <i class="fas fa-chart-bar mr-2"></i>Dashboard
                    </a>
                    <a href="transaksi.php" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition duration-200">
                        <i class="fas fa-list mr-2"></i>Transaksi
                    </a>
                </div>
            </div>
        </div>

        <!-- Statistik Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-red-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-exclamation-circle text-red-600"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Piutang Aktif</p>
                        <p class="text-2xl font-semibold text-gray-900"><?= $stats['piutang_aktif'] ?></p>
                        <p class="text-sm text-gray-600"><?= formatRupiah($stats['total_sisa_piutang']) ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-check-circle text-green-600"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Piutang Lunas</p>
                        <p class="text-2xl font-semibold text-gray-900"><?= $stats['piutang_lunas'] ?></p>
                        <p class="text-sm text-gray-600"><?= formatRupiah($stats['total_lunas']) ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-credit-card text-blue-600"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Total Piutang</p>
                        <p class="text-2xl font-semibold text-gray-900"><?= formatRupiah($stats['total_piutang']) ?></p>
                        <p class="text-sm text-gray-600">Keseluruhan</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-yellow-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-percentage text-yellow-600"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Tingkat Pelunasan</p>
                        <p class="text-2xl font-semibold text-gray-900">
                            <?= $stats['total_piutang'] > 0 ? number_format(($stats['total_lunas'] / $stats['total_piutang']) * 100, 1) : 0 ?>%
                        </p>
                        <p class="text-sm text-gray-600">Persentase lunas</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-8">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Filter Data</h3>
            <form method="GET" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div>
                        <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status Piutang</label>
                        <select id="status" name="status" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="aktif" <?= $status_filter === 'aktif' ? 'selected' : '' ?>>Aktif</option>
                            <option value="lunas" <?= $status_filter === 'lunas' ? 'selected' : '' ?>>Lunas</option>
                            <option value="" <?= $status_filter === '' ? 'selected' : '' ?>>Semua</option>
                        </select>
                    </div>

                    <div>
                        <label for="desa_id" class="block text-sm font-medium text-gray-700 mb-1">Desa</label>
                        <select id="desa_id" name="desa_id" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Semua Desa</option>
                            <?php foreach ($desa_list as $desa): ?>
                            <option value="<?= $desa['id'] ?>" <?= $desa_filter == $desa['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($desa['nama_desa']) ?> - <?= htmlspecialchars($desa['kecamatan']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Pencarian</label>
                        <input type="text" id="search" name="search" value="<?= htmlspecialchars($search) ?>"
                               placeholder="Invoice, desa, atau sales..."
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>

                    <div class="flex items-end">
                        <button type="submit" class="w-full bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition duration-200">
                            <i class="fas fa-search mr-2"></i>Filter
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Tabel Piutang -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">
                    Data Piutang (<?= number_format(count($piutang_data)) ?> piutang)
                </h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Invoice</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Desa</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sales</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Jumlah Piutang</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sisa Piutang</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($piutang_data)): ?>
                        <tr>
                            <td colspan="8" class="px-6 py-12 text-center text-gray-500">
                                <i class="fas fa-inbox text-4xl mb-4"></i>
                                <p>Tidak ada data piutang</p>
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($piutang_data as $row): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <a href="transaksi-view.php?id=<?= $row['transaksi_id'] ?>" class="text-blue-600 hover:text-blue-800 font-medium">
                                    <?= htmlspecialchars($row['nomor_invoice']) ?>
                                </a>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?= formatTanggal($row['tanggal_transaksi']) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <div>
                                    <div class="font-medium"><?= htmlspecialchars($row['nama_desa']) ?></div>
                                    <div class="text-gray-500"><?= htmlspecialchars($row['kecamatan']) ?></div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?= htmlspecialchars($row['sales_name']) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-medium">
                                <?= formatRupiah($row['jumlah_piutang']) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <span class="<?= $row['sisa_piutang'] > 0 ? 'text-red-600' : 'text-green-600' ?>">
                                    <?= formatRupiah($row['sisa_piutang']) ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $row['status'] === 'lunas' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                    <?= ucfirst($row['status']) ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <div class="flex space-x-2">
                                    <a href="transaksi-view.php?id=<?= $row['transaksi_id'] ?>" class="text-blue-600 hover:text-blue-900" title="Lihat Detail">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <?php if ($row['status'] === 'aktif'): ?>
                                    <button onclick="openPaymentModal(<?= $row['id'] ?>, '<?= htmlspecialchars($row['nomor_invoice']) ?>', <?= $row['sisa_piutang'] ?>)" 
                                            class="text-green-600 hover:text-green-900" title="Bayar Piutang">
                                        <i class="fas fa-money-bill-wave"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Pembayaran Piutang -->
<div id="paymentModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Pembayaran Piutang</h3>
            <form method="POST">
                <input type="hidden" name="action" value="bayar_piutang">
                <input type="hidden" name="piutang_id" id="modal_piutang_id">
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Invoice</label>
                    <input type="text" id="modal_invoice" readonly class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-50">
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Sisa Piutang</label>
                    <input type="text" id="modal_sisa_piutang" readonly class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-50">
                </div>
                
                <div class="mb-4">
                    <label for="jumlah_bayar" class="block text-sm font-medium text-gray-700 mb-1">Jumlah Bayar *</label>
                    <input type="number" name="jumlah_bayar" id="jumlah_bayar" required min="1" step="0.01"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div class="mb-4">
                    <label for="metode_pembayaran" class="block text-sm font-medium text-gray-700 mb-1">Metode Pembayaran *</label>
                    <select name="metode_pembayaran" id="metode_pembayaran" required onchange="toggleBankField()"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">Pilih Metode</option>
                        <option value="tunai">Tunai</option>
                        <option value="transfer">Transfer Bank</option>
                    </select>
                </div>
                
                <div class="mb-4 hidden" id="bank_field">
                    <label for="bank_id" class="block text-sm font-medium text-gray-700 mb-1">Bank</label>
                    <select name="bank_id" id="bank_id" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">Pilih Bank</option>
                        <?php foreach ($bank_list as $bank): ?>
                        <option value="<?= $bank['id'] ?>"><?= htmlspecialchars($bank['nama_bank']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="mb-4">
                    <label for="keterangan" class="block text-sm font-medium text-gray-700 mb-1">Keterangan</label>
                    <textarea name="keterangan" id="keterangan" rows="3" 
                              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closePaymentModal()" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                        Batal
                    </button>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                        Simpan Pembayaran
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openPaymentModal(piutangId, invoice, sisaPiutang) {
    document.getElementById('modal_piutang_id').value = piutangId;
    document.getElementById('modal_invoice').value = invoice;
    document.getElementById('modal_sisa_piutang').value = 'Rp ' + new Intl.NumberFormat('id-ID').format(sisaPiutang);
    document.getElementById('jumlah_bayar').max = sisaPiutang;
    document.getElementById('paymentModal').classList.remove('hidden');
}

function closePaymentModal() {
    document.getElementById('paymentModal').classList.add('hidden');
    document.getElementById('jumlah_bayar').value = '';
    document.getElementById('metode_pembayaran').value = '';
    document.getElementById('bank_id').value = '';
    document.getElementById('keterangan').value = '';
    document.getElementById('bank_field').classList.add('hidden');
}

function toggleBankField() {
    const metode = document.getElementById('metode_pembayaran').value;
    const bankField = document.getElementById('bank_field');
    
    if (metode === 'transfer') {
        bankField.classList.remove('hidden');
        document.getElementById('bank_id').required = true;
    } else {
        bankField.classList.add('hidden');
        document.getElementById('bank_id').required = false;
        document.getElementById('bank_id').value = '';
    }
}

// Close modal when clicking outside
document.getElementById('paymentModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closePaymentModal();
    }
});
</script>

<?php require_once 'layouts/footer.php'; ?>