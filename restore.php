<?php
require_once 'config/auth.php';
require_once 'config/database.php';

// Cek autentikasi dan role admin
if (!AuthStatic::isLoggedIn()) {
    header('Location: login.php');
    exit;
}

if (!AuthStatic::hasRole(['admin'])) {
    header('Location: dashboard.php');
    exit;
}

$message = '';
$error = '';

// Handle restore process
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['backup_file'])) {
    try {
        $upload_file = $_FILES['backup_file'];
        
        // Validasi file
        if ($upload_file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Error uploading file');
        }
        
        if (pathinfo($upload_file['name'], PATHINFO_EXTENSION) !== 'zip') {
            throw new Exception('File harus berformat ZIP');
        }
        
        // Create temp directory if not exists
        $temp_dir = 'tmp/restore/';
        if (!is_dir($temp_dir)) {
            mkdir($temp_dir, 0755, true);
        }
        
        $temp_file = $temp_dir . 'restore_' . time() . '.zip';
        
        // Move uploaded file
        if (!move_uploaded_file($upload_file['tmp_name'], $temp_file)) {
            throw new Exception('Gagal menyimpan file upload');
        }
        
        // Extract ZIP file
        $zip = new ZipArchive();
        if ($zip->open($temp_file) !== TRUE) {
            throw new Exception('Gagal membuka file ZIP');
        }
        
        $extract_dir = $temp_dir . 'extracted_' . time() . '/';
        if (!$zip->extractTo($extract_dir)) {
            $zip->close();
            throw new Exception('Gagal mengekstrak file ZIP');
        }
        $zip->close();
        
        // Find SQL file
        $sql_file = null;
        $files = scandir($extract_dir);
        foreach ($files as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) === 'sql') {
                $sql_file = $extract_dir . $file;
                break;
            }
        }
        
        if (!$sql_file || !file_exists($sql_file)) {
            throw new Exception('File SQL tidak ditemukan dalam backup');
        }
        
        // Read and execute SQL
        $sql_content = file_get_contents($sql_file);
        if ($sql_content === false) {
            throw new Exception('Gagal membaca file SQL');
        }
        
        // Split SQL into individual queries
        $queries = array_filter(array_map('trim', explode(';', $sql_content)));
        
        // Execute SQL queries
        $pdo = Database::getConnection();
        $pdo->beginTransaction();
        
        try {
            foreach ($queries as $query) {
                if (!empty($query)) {
                    $pdo->exec($query);
                }
            }
            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollback();
            throw new Exception('Gagal mengeksekusi SQL: ' . $e->getMessage());
        }
        
        // Restore images if exists
        $uploads_backup = $extract_dir . 'uploads/';
        if (is_dir($uploads_backup)) {
            // Create uploads directory if not exists
            if (!is_dir('uploads/')) {
                mkdir('uploads/', 0755, true);
            }
            
            // Copy files recursively
            function copyDirectory($src, $dst) {
                $dir = opendir($src);
                if (!is_dir($dst)) {
                    mkdir($dst, 0755, true);
                }
                
                while (($file = readdir($dir)) !== false) {
                    if ($file != '.' && $file != '..') {
                        $src_file = $src . '/' . $file;
                        $dst_file = $dst . '/' . $file;
                        
                        if (is_dir($src_file)) {
                            copyDirectory($src_file, $dst_file);
                        } else {
                            copy($src_file, $dst_file);
                        }
                    }
                }
                closedir($dir);
            }
            
            copyDirectory($uploads_backup, 'uploads/');
        }
        
        // Clean up temporary files
        function deleteDirectory($dir) {
            if (!is_dir($dir)) return;
            
            $files = array_diff(scandir($dir), array('.', '..'));
            foreach ($files as $file) {
                $path = $dir . '/' . $file;
                is_dir($path) ? deleteDirectory($path) : unlink($path);
            }
            rmdir($dir);
        }
        
        deleteDirectory($extract_dir);
        unlink($temp_file);
        
        $message = 'Database dan file berhasil direstore!';
        
    } catch (Exception $e) {
        $error = $e->getMessage();
        
        // Clean up on error
        if (isset($temp_file) && file_exists($temp_file)) {
            unlink($temp_file);
        }
        if (isset($extract_dir) && is_dir($extract_dir)) {
            deleteDirectory($extract_dir);
        }
    }
}

// Get existing backup files
$backup_files = [];
if (is_dir('tmp/backup/')) {
    $files = scandir('tmp/backup/');
    foreach ($files as $file) {
        if (pathinfo($file, PATHINFO_EXTENSION) === 'zip') {
            $file_path = 'tmp/backup/' . $file;
            $backup_files[] = [
                'name' => $file,
                'size' => filesize($file_path),
                'date' => date('Y-m-d H:i:s', filemtime($file_path))
            ];
        }
    }
    
    // Sort by date descending
    usort($backup_files, function($a, $b) {
        return strtotime($b['date']) - strtotime($a['date']);
    });
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restore Database - SMD</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    
    <div class="container mt-4">
        <div class="row">
            <div class="col-md-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-upload"></i> Restore Database</h2>
                    <a href="backup.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Kembali ke Backup
                    </a>
                </div>
                
                <?php if ($message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <!-- Upload Form -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-cloud-upload-alt"></i> Upload File Backup</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data" onsubmit="return confirmRestore()">
                            <div class="mb-3">
                                <label for="backup_file" class="form-label">Pilih File Backup (ZIP)</label>
                                <input type="file" class="form-control" id="backup_file" name="backup_file" 
                                       accept=".zip" required>
                                <div class="form-text">
                                    <i class="fas fa-info-circle"></i> 
                                    File harus berformat ZIP yang berisi database SQL dan folder uploads (opsional)
                                </div>
                            </div>
                            
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle"></i>
                                <strong>Peringatan:</strong> Proses restore akan mengganti seluruh data yang ada. 
                                Pastikan Anda telah membuat backup terlebih dahulu!
                            </div>
                            
                            <button type="submit" class="btn btn-danger">
                                <i class="fas fa-upload"></i> Restore Database
                            </button>
                        </form>
                    </div>
                </div>
                
                <!-- Existing Backups -->
                <?php if (!empty($backup_files)): ?>
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-archive"></i> File Backup Tersedia</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Nama File</th>
                                        <th>Ukuran</th>
                                        <th>Tanggal</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($backup_files as $file): ?>
                                    <tr>
                                        <td>
                                            <i class="fas fa-file-archive text-primary"></i>
                                            <?= htmlspecialchars($file['name']) ?>
                                        </td>
                                        <td><?= number_format($file['size'] / 1024 / 1024, 2) ?> MB</td>
                                        <td><?= htmlspecialchars($file['date']) ?></td>
                                        <td>
                                            <a href="tmp/backup/<?= urlencode($file['name']) ?>" 
                                               class="btn btn-sm btn-primary" download>
                                                <i class="fas fa-download"></i> Download
                                            </a>
                                            <button type="button" class="btn btn-sm btn-success" 
                                                    onclick="restoreFromServer('<?= htmlspecialchars($file['name']) ?>')">
                                                <i class="fas fa-upload"></i> Restore
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Hidden form for server restore -->
    <form id="serverRestoreForm" method="POST" style="display: none;">
        <input type="hidden" id="serverBackupFile" name="server_backup_file">
    </form>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function confirmRestore() {
            return confirm('Apakah Anda yakin ingin melakukan restore? Semua data saat ini akan diganti!');
        }
        
        function restoreFromServer(filename) {
            if (confirm('Apakah Anda yakin ingin restore dari file: ' + filename + '?\nSemua data saat ini akan diganti!')) {
                // Create a temporary file input and trigger restore
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'restore-server.php';
                
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'backup_filename';
                input.value = filename;
                
                form.appendChild(input);
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>