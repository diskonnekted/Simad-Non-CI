<?php
require_once 'config/database.php';
require_once 'config/auth.php';

// Cek autentikasi
if (!AuthStatic::isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$db = getDatabase();
$user = AuthStatic::getCurrentUser();

// Ambil ID transaksi dari parameter
$transaction_id = $_GET['id'] ?? null;

if (!$transaction_id) {
    header('Location: pos.php');
    exit;
}

// Ambil data transaksi
$transaksi = $db->select("
    SELECT t.*, d.nama_desa, d.kecamatan, d.alamat as alamat_desa, d.kode_pos,
           u.nama_lengkap as nama_user, u.email as email_user
    FROM transaksi t
    LEFT JOIN desa d ON t.desa_id = d.id
    LEFT JOIN users u ON t.user_id = u.id
    WHERE t.id = ?
", [$transaction_id]);

if (empty($transaksi)) {
    header('Location: pos.php');
    exit;
}

$transaksi = $transaksi[0];

// Ambil detail transaksi
$detail_transaksi = $db->select("
    SELECT td.*, 
           CASE 
               WHEN td.produk_id IS NOT NULL THEN p.kode_produk
               WHEN td.layanan_id IS NOT NULL THEN l.kode_layanan
           END as kode_item,
           CASE 
               WHEN td.produk_id IS NOT NULL THEN kp.nama_kategori
               WHEN td.layanan_id IS NOT NULL THEN l.jenis_layanan
           END as kategori_item,
           CASE 
               WHEN td.produk_id IS NOT NULL THEN 'produk'
               WHEN td.layanan_id IS NOT NULL THEN 'layanan'
           END as jenis_item
    FROM transaksi_detail td
    LEFT JOIN produk p ON td.produk_id = p.id
    LEFT JOIN kategori_produk kp ON p.kategori_id = kp.id
    LEFT JOIN layanan l ON td.layanan_id = l.id
    WHERE td.transaksi_id = ?
    ORDER BY td.id
", [$transaction_id]);

// Ambil data pembayaran
$pembayaran = $db->select("
    SELECT p.*, b.nama_bank, b.jenis_bank
    FROM pembayaran p
    LEFT JOIN bank b ON p.bank_id = b.id
    WHERE p.transaksi_id = ?
    ORDER BY p.tanggal_bayar
", [$transaction_id]);

// Ambil data piutang jika ada
$piutang = $db->select("
    SELECT * FROM piutang 
    WHERE transaksi_id = ? AND status = 'belum_lunas'
", [$transaction_id]);

$page_title = 'Invoice #' . $transaksi['nomor_invoice'];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        @media print {
            .no-print { display: none !important; }
            body { font-size: 12px; }
            .print-break { page-break-after: always; }
        }
        
        .invoice-header {
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
        }
        
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-lunas { background: #dcfce7; color: #166534; }
        .status-dp { background: #fef3c7; color: #92400e; }
        .status-tempo { background: #fee2e2; color: #991b1b; }
    </style>
</head>
<body class="bg-gray-50">
    <div class="max-w-4xl mx-auto p-6">
        <!-- Action Buttons -->
        <div class="no-print mb-6 flex justify-between items-center">
            <a href="pos.php" class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600 transition-colors">
                <i class="fas fa-arrow-left mr-2"></i>Kembali ke POS
            </a>
            <div class="space-x-2">
                <button onclick="window.print()" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                    <i class="fas fa-print mr-2"></i>Cetak Invoice
                </button>
                <button onclick="downloadPDF()" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition-colors">
                    <i class="fas fa-download mr-2"></i>Download PDF
                </button>
            </div>
        </div>

        <!-- Invoice Content -->
        <div class="bg-white rounded-lg shadow-lg overflow-hidden">
            <!-- Header -->
            <div class="invoice-header text-white p-8">
                <div class="flex justify-between items-start">
                    <div>
                        <h1 class="text-3xl font-bold mb-2">INVOICE</h1>
                        <p class="text-blue-100">Sistem Manajemen Desa</p>
                        <p class="text-blue-100 text-sm">Jl. Contoh No. 123, Kota Contoh</p>
                        <p class="text-blue-100 text-sm">Telp: (021) 1234-5678 | Email: info@smd.com</p>
                    </div>
                    <div class="text-right">
                        <div class="text-2xl font-bold">#<?= htmlspecialchars($transaksi['nomor_invoice']) ?></div>
                        <div class="text-blue-100 mt-2">
                            <div>Tanggal: <?= date('d/m/Y', strtotime($transaksi['tanggal_transaksi'])) ?></div>
                            <div>Waktu: <?= date('H:i', strtotime($transaksi['tanggal_transaksi'])) ?> WIB</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Customer & Transaction Info -->
            <div class="p-8">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
                    <!-- Customer Info -->
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-4 border-b border-gray-200 pb-2">
                            <i class="fas fa-map-marker-alt text-blue-600 mr-2"></i>
                            Informasi Customer
                        </h3>
                        <div class="space-y-2">
                            <div><strong>Desa:</strong> <?= htmlspecialchars($transaksi['nama_desa']) ?></div>
                            <div><strong>Kecamatan:</strong> <?= htmlspecialchars($transaksi['kecamatan']) ?></div>
                            <?php if ($transaksi['alamat_desa']): ?>
                            <div><strong>Alamat:</strong> <?= htmlspecialchars($transaksi['alamat_desa']) ?></div>
                            <?php endif; ?>
                            <?php if ($transaksi['kode_pos']): ?>
                            <div><strong>Kode Pos:</strong> <?= htmlspecialchars($transaksi['kode_pos']) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Transaction Info -->
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-4 border-b border-gray-200 pb-2">
                            <i class="fas fa-receipt text-green-600 mr-2"></i>
                            Informasi Transaksi
                        </h3>
                        <div class="space-y-2">
                            <div><strong>Kasir:</strong> <?= htmlspecialchars($transaksi['nama_user']) ?></div>
                            <div><strong>Metode Pembayaran:</strong> 
                                <?php
                                $metode_text = [
                                    'tunai' => 'Tunai',
                                    'dp_pelunasan' => 'DP + Pelunasan',
                                    'tempo' => 'Tempo'
                                ];
                                echo $metode_text[$transaksi['metode_pembayaran']] ?? $transaksi['metode_pembayaran'];
                                ?>
                            </div>
                            <div><strong>Status Pembayaran:</strong> 
                                <span class="status-badge status-<?= $transaksi['status_pembayaran'] ?>">
                                    <?= ucfirst($transaksi['status_pembayaran']) ?>
                                </span>
                            </div>
                            <?php if ($transaksi['catatan']): ?>
                            <div><strong>Catatan:</strong> <?= htmlspecialchars($transaksi['catatan']) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Items Table -->
                <div class="mb-8">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4 border-b border-gray-200 pb-2">
                        <i class="fas fa-list text-purple-600 mr-2"></i>
                        Detail Pembelian
                    </h3>
                    <div class="overflow-x-auto">
                        <table class="w-full border-collapse border border-gray-300">
                            <thead>
                                <tr class="bg-gray-50">
                                    <th class="border border-gray-300 px-4 py-3 text-left">No</th>
                                    <th class="border border-gray-300 px-4 py-3 text-left">Kode</th>
                                    <th class="border border-gray-300 px-4 py-3 text-left">Nama Item</th>
                                    <th class="border border-gray-300 px-4 py-3 text-left">Jenis</th>
                                    <th class="border border-gray-300 px-4 py-3 text-center">Qty</th>
                                    <th class="border border-gray-300 px-4 py-3 text-right">Harga Satuan</th>
                                    <th class="border border-gray-300 px-4 py-3 text-right">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($detail_transaksi as $index => $detail): ?>
                                <tr class="<?= $index % 2 === 0 ? 'bg-white' : 'bg-gray-50' ?>">
                                    <td class="border border-gray-300 px-4 py-3"><?= $index + 1 ?></td>
                                    <td class="border border-gray-300 px-4 py-3 font-mono text-sm">
                                        <?= htmlspecialchars($detail['kode_item'] ?? '-') ?>
                                    </td>
                                    <td class="border border-gray-300 px-4 py-3">
                                        <div class="font-medium"><?= htmlspecialchars($detail['nama_item']) ?></div>
                                        <?php if ($detail['kategori_item']): ?>
                                        <div class="text-sm text-gray-500"><?= htmlspecialchars($detail['kategori_item']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="border border-gray-300 px-4 py-3">
                                        <span class="inline-block px-2 py-1 text-xs rounded-full <?= $detail['jenis_item'] === 'produk' ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800' ?>">
                                            <?= ucfirst($detail['jenis_item']) ?>
                                        </span>
                                    </td>
                                    <td class="border border-gray-300 px-4 py-3 text-center font-medium">
                                        <?= $detail['quantity'] ?>
                                    </td>
                                    <td class="border border-gray-300 px-4 py-3 text-right">
                                        Rp <?= number_format($detail['harga_satuan'], 0, ',', '.') ?>
                                    </td>
                                    <td class="border border-gray-300 px-4 py-3 text-right font-medium">
                                        Rp <?= number_format($detail['subtotal'], 0, ',', '.') ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr class="bg-gray-100 font-semibold">
                                    <td colspan="6" class="border border-gray-300 px-4 py-3 text-right">TOTAL:</td>
                                    <td class="border border-gray-300 px-4 py-3 text-right text-lg">
                                        Rp <?= number_format($transaksi['total_amount'], 0, ',', '.') ?>
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                <!-- Payment Information -->
                <?php if (!empty($pembayaran)): ?>
                <div class="mb-8">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4 border-b border-gray-200 pb-2">
                        <i class="fas fa-credit-card text-yellow-600 mr-2"></i>
                        Riwayat Pembayaran
                    </h3>
                    <div class="overflow-x-auto">
                        <table class="w-full border-collapse border border-gray-300">
                            <thead>
                                <tr class="bg-gray-50">
                                    <th class="border border-gray-300 px-4 py-3 text-left">Tanggal</th>
                                    <th class="border border-gray-300 px-4 py-3 text-left">Metode</th>
                                    <th class="border border-gray-300 px-4 py-3 text-left">Bank</th>
                                    <th class="border border-gray-300 px-4 py-3 text-left">Jenis</th>
                                    <th class="border border-gray-300 px-4 py-3 text-right">Jumlah</th>
                                    <th class="border border-gray-300 px-4 py-3 text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pembayaran as $bayar): ?>
                                <tr>
                                    <td class="border border-gray-300 px-4 py-3">
                                        <?= date('d/m/Y', strtotime($bayar['tanggal_bayar'])) ?>
                                    </td>
                                    <td class="border border-gray-300 px-4 py-3">
                                        <?= ucfirst($bayar['metode_bayar']) ?>
                                    </td>
                                    <td class="border border-gray-300 px-4 py-3">
                                        <?= $bayar['nama_bank'] ? htmlspecialchars($bayar['nama_bank']) . ' (' . $bayar['jenis_bank'] . ')' : '-' ?>
                                    </td>
                                    <td class="border border-gray-300 px-4 py-3 text-right font-medium">
                                        Rp <?= number_format($bayar['jumlah_bayar'], 0, ',', '.') ?>
                                    </td>
                                    <td class="border border-gray-300 px-4 py-3">
                                        <?= htmlspecialchars($bayar['catatan']) ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Outstanding Payment -->
                <?php if (!empty($piutang)): ?>
                <div class="mb-8">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4 border-b border-gray-200 pb-2">
                        <i class="fas fa-exclamation-triangle text-red-600 mr-2"></i>
                        Sisa Pembayaran
                    </h3>
                    <?php foreach ($piutang as $hutang): ?>
                    <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                        <div class="flex justify-between items-center">
                            <div>
                                <div class="font-medium text-red-900">
                                    Sisa Pembayaran: Rp <?= number_format($hutang['sisa_piutang'], 0, ',', '.') ?>
                                </div>
                                <div class="text-sm text-red-700">
                                    Jatuh Tempo: <?= date('d/m/Y', strtotime($hutang['tanggal_jatuh_tempo'])) ?>
                                </div>
                                <?php if ($hutang['catatan']): ?>
                                <div class="text-sm text-red-600 mt-1">
                                    <?= htmlspecialchars($hutang['catatan']) ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="text-right">
                                <span class="inline-block px-3 py-1 text-sm rounded-full bg-red-100 text-red-800 font-medium">
                                    Belum Lunas
                                </span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <!-- Summary -->
                <div class="bg-gray-50 rounded-lg p-6">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 text-center">
                        <div>
                            <div class="text-2xl font-bold text-blue-600">
                                Rp <?= number_format($transaksi['total_amount'], 0, ',', '.') ?>
                            </div>
                            <div class="text-sm text-gray-600">Total Transaksi</div>
                        </div>
                        <div>
                            <div class="text-2xl font-bold text-green-600">
                                Rp <?= number_format($transaksi['total_amount'] - ($transaksi['sisa_amount'] ?? 0), 0, ',', '.') ?>
                            </div>
                            <div class="text-sm text-gray-600">Sudah Dibayar</div>
                        </div>
                        <div>
                            <div class="text-2xl font-bold text-red-600">
                                Rp <?= number_format($transaksi['sisa_amount'] ?? 0, 0, ',', '.') ?>
                            </div>
                            <div class="text-sm text-gray-600">Sisa Pembayaran</div>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="mt-8 pt-6 border-t border-gray-200 text-center text-sm text-gray-500">
                    <p>Terima kasih atas kepercayaan Anda menggunakan layanan kami.</p>
                    <p class="mt-2">Invoice ini digenerate otomatis pada <?= date('d/m/Y H:i:s') ?> WIB</p>
                    <p class="mt-1">Untuk pertanyaan, hubungi: info@smd.com | (021) 1234-5678</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        function downloadPDF() {
            // Open PDF generator in new window to download the invoice
            window.open('generate-pdf-invoice.php?id=<?= $transaction_id ?>', '_blank');
        }
    </script>
</body>
</html>