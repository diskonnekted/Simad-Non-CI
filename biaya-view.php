<?php
require_once 'config/database.php';
require_once 'config/auth.php';

// Cek autentikasi dan role
if (!AuthStatic::isLoggedIn()) {
    header('Location: login.php');
    exit;
}

if (!AuthStatic::hasRole(['admin', 'akunting', 'supervisor'])) {
    header('Location: index.php?error=access_denied');
    exit;
}

$user = AuthStatic::getCurrentUser();
/** @var Database $db */
$db = getDatabase();

$biaya_id = intval($_GET['id'] ?? 0);

if (!$biaya_id) {
    header('Location: biaya.php?error=invalid_id');
    exit;
}

// Ambil data biaya operasional
try {
    $biaya = $db->select(
        "SELECT * FROM biaya_operasional WHERE id = ?",
        [$biaya_id]
    );
    
    if (empty($biaya)) {
        header('Location: biaya.php?error=not_found');
        exit;
    }
    
    $biaya = $biaya[0];
    
    // Ambil statistik penggunaan biaya
    $usage_stats = $db->select(
        "SELECT 
            COUNT(*) as total_penggunaan,
            SUM(jumlah * tarif_aktual) as total_nilai,
            AVG(tarif_aktual) as rata_rata_tarif,
            MIN(tarif_aktual) as tarif_terendah,
            MAX(tarif_aktual) as tarif_tertinggi
         FROM jadwal_biaya 
         WHERE biaya_id = ?",
        [$biaya_id]
    );
    
    $stats = $usage_stats[0] ?? [
        'total_penggunaan' => 0,
        'total_nilai' => 0,
        'rata_rata_tarif' => 0,
        'tarif_terendah' => 0,
        'tarif_tertinggi' => 0
    ];
    
    // Ambil riwayat penggunaan terbaru
    $recent_usage = $db->select(
        "SELECT jb.*, j.nama_jadwal, j.tanggal_mulai, j.tanggal_selesai
         FROM jadwal_biaya jb
         JOIN jadwal j ON jb.jadwal_id = j.id
         WHERE jb.biaya_id = ?
         ORDER BY j.tanggal_mulai DESC
         LIMIT 10",
        [$biaya_id]
    );
    
} catch (Exception $e) {
    header('Location: biaya.php?error=database_error');
    exit;
}

// Helper functions
function formatRupiah($amount) {
    return $amount ? 'Rp ' . number_format($amount, 0, ',', '.') : 'Rp 0';
}

function getKategoriBadge($kategori) {
    $badges = [
        'transportasi' => 'primary',
        'konsumsi' => 'success',
        'peralatan' => 'info',
        'administrasi' => 'warning',
        'lainnya' => 'secondary'
    ];
    return $badges[$kategori] ?? 'secondary';
}

function formatTanggal($date) {
    return $date ? date('d/m/Y', strtotime($date)) : '-';
}

$page_title = 'Detail Biaya Operasional - ' . $biaya['nama_biaya'];
require_once 'layouts/header.php';
?>

<div class="container-fluid px-4">
    <!-- Header Section -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800">Detail Biaya Operasional</h1>
            <p class="mb-0 text-gray-600"><?php echo htmlspecialchars($biaya['nama_biaya']); ?></p>
        </div>
        <div class="d-flex gap-2">
            <a href="biaya.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-2"></i>Kembali
            </a>
            <?php if (AuthStatic::hasRole(['admin', 'akunting'])): ?>
            <a href="biaya-edit.php?id=<?php echo $biaya['id']; ?>" class="btn btn-warning">
                <i class="fas fa-edit me-2"></i>Edit
            </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="row">
        <!-- Informasi Utama -->
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Informasi Biaya Operasional</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label text-muted">Kode Biaya</label>
                                <div class="fw-bold">
                                    <code class="fs-5"><?php echo htmlspecialchars($biaya['kode_biaya']); ?></code>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label text-muted">Nama Biaya</label>
                                <div class="fw-bold fs-5"><?php echo htmlspecialchars($biaya['nama_biaya']); ?></div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label text-muted">Kategori</label>
                                <div>
                                    <span class="badge bg-<?php echo getKategoriBadge($biaya['kategori']); ?> fs-6">
                                        <?php echo ucfirst($biaya['kategori']); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label text-muted">Tarif Standar</label>
                                <div class="fw-bold fs-4 text-success">
                                    <?php echo formatRupiah($biaya['tarif_standar']); ?>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label text-muted">Satuan</label>
                                <div class="fw-bold"><?php echo htmlspecialchars($biaya['satuan']); ?></div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label text-muted">Tanggal Dibuat</label>
                                <div><?php echo formatTanggal($biaya['created_at']); ?></div>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (!empty($biaya['deskripsi'])): ?>
                    <div class="mt-4">
                        <label class="form-label text-muted">Deskripsi</label>
                        <div class="p-3 bg-light rounded">
                            <?php echo nl2br(htmlspecialchars($biaya['deskripsi'])); ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Riwayat Penggunaan -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Riwayat Penggunaan Terbaru</h6>
                </div>
                <div class="card-body">
                    <?php if (empty($recent_usage)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-history fa-3x text-gray-300 mb-3"></i>
                        <p class="text-gray-500">Belum ada riwayat penggunaan untuk biaya operasional ini.</p>
                    </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Jadwal</th>
                                    <th>Periode</th>
                                    <th>Jumlah</th>
                                    <th>Tarif Aktual</th>
                                    <th>Total Nilai</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_usage as $usage): ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold"><?php echo htmlspecialchars($usage['nama_jadwal']); ?></div>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            <?php echo formatTanggal($usage['tanggal_mulai']); ?> - 
                                            <?php echo formatTanggal($usage['tanggal_selesai']); ?>
                                        </small>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-info">
                                            <?php echo number_format($usage['jumlah'], 2); ?> <?php echo htmlspecialchars($biaya['satuan']); ?>
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <?php echo formatRupiah($usage['tarif_aktual']); ?>
                                        <?php if ($usage['tarif_aktual'] != $biaya['tarif_standar']): ?>
                                        <br><small class="text-muted">
                                            (Standar: <?php echo formatRupiah($biaya['tarif_standar']); ?>)
                                        </small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <strong><?php echo formatRupiah($usage['jumlah'] * $usage['tarif_aktual']); ?></strong>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Sidebar Statistik -->
        <div class="col-lg-4">
            <!-- Statistik Penggunaan -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-success">Statistik Penggunaan</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-muted">Total Penggunaan</span>
                            <span class="fw-bold"><?php echo number_format($stats['total_penggunaan']); ?>x</span>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-muted">Total Nilai</span>
                            <span class="fw-bold text-success"><?php echo formatRupiah($stats['total_nilai']); ?></span>
                        </div>
                    </div>
                    
                    <?php if ($stats['total_penggunaan'] > 0): ?>
                    <hr>
                    
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-muted">Rata-rata Tarif</span>
                            <span class="fw-bold"><?php echo formatRupiah($stats['rata_rata_tarif']); ?></span>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-muted">Tarif Terendah</span>
                            <span class="fw-bold text-info"><?php echo formatRupiah($stats['tarif_terendah']); ?></span>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-muted">Tarif Tertinggi</span>
                            <span class="fw-bold text-warning"><?php echo formatRupiah($stats['tarif_tertinggi']); ?></span>
                        </div>
                    </div>
                    
                    <!-- Perbandingan dengan tarif standar -->
                    <?php if ($stats['rata_rata_tarif'] != $biaya['tarif_standar']): ?>
                    <hr>
                    <div class="alert alert-info">
                        <small>
                            <i class="fas fa-info-circle me-1"></i>
                            <strong>Perbandingan:</strong><br>
                            Rata-rata tarif aktual 
                            <?php if ($stats['rata_rata_tarif'] > $biaya['tarif_standar']): ?>
                                <span class="text-danger">lebih tinggi</span>
                            <?php else: ?>
                                <span class="text-success">lebih rendah</span>
                            <?php endif; ?>
                            dari tarif standar sebesar 
                            <?php echo formatRupiah(abs($stats['rata_rata_tarif'] - $biaya['tarif_standar'])); ?>
                        </small>
                    </div>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Informasi Tambahan -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-info">Informasi Tambahan</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <h6 class="text-primary">Status Penggunaan</h6>
                        <?php if ($stats['total_penggunaan'] > 0): ?>
                        <span class="badge bg-success">Aktif Digunakan</span>
                        <p class="small text-muted mt-2">
                            Biaya operasional ini telah digunakan dalam <?php echo $stats['total_penggunaan']; ?> jadwal.
                        </p>
                        <?php else: ?>
                        <span class="badge bg-secondary">Belum Digunakan</span>
                        <p class="small text-muted mt-2">
                            Biaya operasional ini belum pernah digunakan dalam jadwal manapun.
                        </p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mb-3">
                        <h6 class="text-primary">Kategori Info</h6>
                        <?php
                        $kategori_info = [
                            'transportasi' => 'Biaya yang berkaitan dengan transportasi dan perjalanan',
                            'konsumsi' => 'Biaya untuk makanan, minuman, dan konsumsi lainnya',
                            'peralatan' => 'Biaya sewa, maintenance, atau penggunaan peralatan',
                            'administrasi' => 'Biaya administrasi, ATK, dan dokumen',
                            'lainnya' => 'Biaya operasional lain yang tidak masuk kategori di atas'
                        ];
                        ?>
                        <p class="small text-muted">
                            <?php echo $kategori_info[$biaya['kategori']] ?? 'Kategori biaya operasional'; ?>
                        </p>
                    </div>
                    
                    <?php if (AuthStatic::hasRole(['admin'])): ?>
                    <div class="alert alert-warning">
                        <small>
                            <i class="fas fa-exclamation-triangle me-1"></i>
                            <strong>Perhatian:</strong> Biaya operasional yang sudah digunakan dalam jadwal tidak dapat dihapus.
                        </small>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

<?php require_once 'layouts/footer.php'; ?>