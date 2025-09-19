<?php
require_once 'config/database.php';
require_once 'config/auth.php';

header('Content-Type: text/html; charset=utf-8');

$db = getDatabase();
/** @var Database $db */

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug Halaman Produk - SMD</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">
                            <i class="fas fa-bug me-2"></i>
                            Debug Halaman Produk
                        </h4>
                    </div>
                    <div class="card-body">
                        
                        <?php
                        try {
                            // Simulasi query yang sama dengan produk.php
                            $search = '';
                            $kategori_filter = '';
                            $status_filter = '';
                            $sort_by = 'id';
                            $sort_order = 'DESC';
                            $page = 1;
                            $limit = 10;
                            $offset = ($page - 1) * $limit;
                            
                            // Build WHERE conditions
                            $where_conditions = [];
                            $params = [];
                            
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
                            
                            if (!empty($status_filter)) {
                                $where_conditions[] = "p.status = ?";
                                $params[] = $status_filter;
                            }
                            
                            $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
                            
                            // Count total records
                            $count_query = "SELECT COUNT(*) as total FROM produk p {$where_clause}";
                            $total_result = $db->select($count_query, $params);
                            $total_records = $total_result[0]['total'];
                            
                            echo "<div class='alert alert-info'>";
                            echo "<h5>Informasi Query</h5>";
                            echo "<p><strong>Total Records:</strong> {$total_records}</p>";
                            echo "<p><strong>Where Clause:</strong> " . ($where_clause ?: 'Tidak ada filter') . "</p>";
                            echo "<p><strong>Parameters:</strong> " . (empty($params) ? 'Tidak ada' : implode(', ', $params)) . "</p>";
                            echo "</div>";
                            
                            // Validate sort column
                            $allowed_sort_columns = [
                                'id' => 'p.id',
                                'nama_produk' => 'p.nama_produk',
                                'kode_produk' => 'p.kode_produk',
                                'kategori' => 'k.nama_kategori',
                                'stok_tersedia' => 'p.stok_tersedia',
                                'harga_satuan' => 'p.harga_satuan',
                                'status' => 'p.status',
                                'created_at' => 'p.created_at'
                            ];
                            
                            $sort_column = isset($allowed_sort_columns[$sort_by]) ? $allowed_sort_columns[$sort_by] : 'p.id';
                            $sort_order = strtoupper($sort_order) === 'ASC' ? 'ASC' : 'DESC';
                            
                            // Main query
                            $query = "
                                SELECT 
                                    p.id,
                                    p.kode_produk,
                                    p.nama_produk,
                                    p.deskripsi,
                                    p.kategori_id,
                                    k.nama_kategori,
                                    p.stok_tersedia,
                                    p.stok_minimum,
                                    p.harga_satuan,
                                    p.status,
                                    p.gambar,
                                    p.created_at,
                                    p.updated_at
                                FROM produk p
                                LEFT JOIN kategori k ON p.kategori_id = k.id
                                {$where_clause}
                                ORDER BY {$sort_column} {$sort_order}
                                LIMIT {$limit} OFFSET {$offset}
                            ";
                            
                            echo "<div class='alert alert-secondary'>";
                            echo "<h5>Query SQL</h5>";
                            echo "<pre>" . htmlspecialchars($query) . "</pre>";
                            echo "</div>";
                            
                            $produk_list = $db->select($query, $params);
                            
                            echo "<div class='alert alert-success'>";
                            echo "<h5>Hasil Query</h5>";
                            echo "<p><strong>Jumlah data yang ditemukan:</strong> " . count($produk_list) . "</p>";
                            echo "</div>";
                            
                            if (!empty($produk_list)) {
                                echo "<div class='table-responsive'>";
                                echo "<table class='table table-striped'>";
                                echo "<thead class='table-dark'>";
                                echo "<tr>";
                                echo "<th>ID</th>";
                                echo "<th>Kode</th>";
                                echo "<th>Nama Produk</th>";
                                echo "<th>Kategori</th>";
                                echo "<th>Stok</th>";
                                echo "<th>Harga</th>";
                                echo "<th>Status</th>";
                                echo "<th>Update Terakhir</th>";
                                echo "</tr>";
                                echo "</thead>";
                                echo "<tbody>";
                                
                                foreach ($produk_list as $produk) {
                                    $status_class = '';
                                    switch ($produk['status']) {
                                        case 'aktif': $status_class = 'bg-success'; break;
                                        case 'nonaktif': $status_class = 'bg-danger'; break;
                                        case 'draft': $status_class = 'bg-warning'; break;
                                    }
                                    
                                    $stok_class = '';
                                    if ($produk['stok_tersedia'] <= $produk['stok_minimum']) {
                                        $stok_class = 'text-danger fw-bold';
                                    }
                                    
                                    echo "<tr>";
                                    echo "<td>{$produk['id']}</td>";
                                    echo "<td><code>{$produk['kode_produk']}</code></td>";
                                    echo "<td><strong>{$produk['nama_produk']}</strong></td>";
                                    echo "<td>" . ($produk['nama_kategori'] ?: '-') . "</td>";
                                    echo "<td class='{$stok_class}'>{$produk['stok_tersedia']} unit</td>";
                                    echo "<td>Rp " . number_format($produk['harga_satuan'], 0, ',', '.') . "</td>";
                                    echo "<td><span class='badge {$status_class}'>" . ucfirst($produk['status']) . "</span></td>";
                                    echo "<td>" . date('d/m/Y H:i', strtotime($produk['updated_at'])) . "</td>";
                                    echo "</tr>";
                                }
                                
                                echo "</tbody>";
                                echo "</table>";
                                echo "</div>";
                            } else {
                                echo "<div class='alert alert-warning'>";
                                echo "<h5>Tidak Ada Data</h5>";
                                echo "<p>Tidak ada produk yang ditemukan dengan kriteria saat ini.</p>";
                                echo "</div>";
                            }
                            
                            // Cek produk yang baru diupdate
                            echo "<hr>";
                            echo "<h5>Produk yang Baru Diupdate (24 jam terakhir)</h5>";
                            
                            $recent_updates = $db->select("
                                SELECT id, nama_produk, stok_tersedia, updated_at
                                FROM produk 
                                WHERE updated_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                                ORDER BY updated_at DESC
                            ");
                            
                            if (!empty($recent_updates)) {
                                echo "<div class='table-responsive'>";
                                echo "<table class='table table-sm'>";
                                echo "<thead><tr><th>ID</th><th>Nama</th><th>Stok</th><th>Update</th></tr></thead>";
                                echo "<tbody>";
                                foreach ($recent_updates as $update) {
                                    echo "<tr>";
                                    echo "<td>{$update['id']}</td>";
                                    echo "<td>{$update['nama_produk']}</td>";
                                    echo "<td>{$update['stok_tersedia']}</td>";
                                    echo "<td>" . date('d/m/Y H:i:s', strtotime($update['updated_at'])) . "</td>";
                                    echo "</tr>";
                                }
                                echo "</tbody></table></div>";
                            } else {
                                echo "<p class='text-muted'>Tidak ada produk yang diupdate dalam 24 jam terakhir.</p>";
                            }
                            
                        } catch (Exception $e) {
                            echo "<div class='alert alert-danger'>";
                            echo "<h5>Error</h5>";
                            echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
                            echo "</div>";
                        }
                        ?>
                        
                        <hr>
                        <div class="mt-4">
                            <h6>Navigasi:</h6>
                            <div class="btn-group" role="group">
                                <a href="produk.php" class="btn btn-primary">
                                    <i class="fas fa-boxes me-1"></i>Halaman Produk Asli
                                </a>
                                <a href="analisis_proses_pembelian.php" class="btn btn-info">
                                    <i class="fas fa-chart-line me-1"></i>Analisis Pembelian
                                </a>
                                <a href="index.php" class="btn btn-secondary">
                                    <i class="fas fa-home me-1"></i>Dashboard
                                </a>
                            </div>
                        </div>
                        
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>