<?php
/**
 * SIMAD - Sistem Informasi Manajemen Desa
 * Installation Script for Production Hosting
 * 
 * This script will:
 * 1. Check system requirements
 * 2. Create database tables
 * 3. Insert initial data
 * 4. Configure application settings
 */

// Security: Only allow installation if not already installed
if (file_exists('config/installed.lock')) {
    die('Application is already installed. Delete config/installed.lock to reinstall.');
}

// Start session for installation process
ini_set('session.gc_maxlifetime', 28800); // 8 hours
ini_set('session.cookie_lifetime', 28800); // 8 hours
session_start();

// Debug session (remove in production)
if (isset($_GET['debug']) && $_GET['debug'] == '1') {
    echo '<pre>Session Data: ' . print_r($_SESSION, true) . '</pre>';
    echo '<pre>POST Data: ' . print_r($_POST, true) . '</pre>';
    echo '<pre>GET Data: ' . print_r($_GET, true) . '</pre>';
}

// Installation steps
$step = $_GET['step'] ?? 1;
$errors = [];
$success = [];

// Handle form submissions
if ($_POST) {
    switch ($step) {
        case 2:
            // Database configuration
            $dbHost = $_POST['db_host'] ?? '';
            $dbName = $_POST['db_name'] ?? '';
            $dbUser = $_POST['db_user'] ?? '';
            $dbPass = $_POST['db_pass'] ?? '';
            $appUrl = $_POST['app_url'] ?? '';
            
            // Validate inputs
            if (empty($dbHost) || empty($dbName) || empty($dbUser) || empty($appUrl)) {
                $errors[] = 'Semua field wajib diisi kecuali password database.';
            }
            
            if (empty($errors)) {
                // Test database connection
                try {
                    $dsn = "mysql:host=$dbHost;charset=utf8mb4";
                    $pdo = new PDO($dsn, $dbUser, $dbPass);
                    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    
                    // Check if database exists, create if not
                    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                    $pdo->exec("USE `$dbName`");
                    
                    // Store configuration in session and temporary file
                    $config = [
                        'db_host' => $dbHost,
                        'db_name' => $dbName,
                        'db_user' => $dbUser,
                        'db_pass' => $dbPass,
                        'app_url' => rtrim($appUrl, '/')
                    ];
                    
                    $_SESSION['install_config'] = $config;
                    
                    // Also save to temporary file as backup
                    file_put_contents('config/install_temp.json', json_encode($config));
                    
                    $success[] = 'Koneksi database berhasil!';
                    $step = 3;
                } catch (PDOException $e) {
                    $errors[] = 'Koneksi database gagal: ' . $e->getMessage();
                }
            }
            break;
            
        case 3:
            // Install database tables
            if (!isset($_SESSION['install_config'])) {
                // Try to load from temporary file
                if (file_exists('config/install_temp.json')) {
                    $tempConfig = json_decode(file_get_contents('config/install_temp.json'), true);
                    if ($tempConfig) {
                        $_SESSION['install_config'] = $tempConfig;
                    } else {
                        $errors[] = 'Konfigurasi database hilang. Silakan ulangi konfigurasi database.';
                        $step = 2;
                        break;
                    }
                } else {
                    $errors[] = 'Konfigurasi database hilang. Silakan ulangi konfigurasi database.';
                    $step = 2;
                    break;
                }
            }
            
            $config = $_SESSION['install_config'];
            
            try {
                $dsn = "mysql:host={$config['db_host']};dbname={$config['db_name']};charset=utf8mb4";
                $pdo = new PDO($dsn, $config['db_user'], $config['db_pass']);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                
                // Read and execute database.sql
                $sqlFile = 'database/database.sql';
                if (file_exists($sqlFile)) {
                    $sql = file_get_contents($sqlFile);
                    
                    // Split SQL into individual statements
                    $statements = array_filter(array_map('trim', explode(';', $sql)));
                    
                    foreach ($statements as $statement) {
                        if (!empty($statement)) {
                            $pdo->exec($statement);
                        }
                    }
                    
                    $success[] = 'Database tables berhasil dibuat!';
                } else {
                    $errors[] = 'File database.sql tidak ditemukan!';
                }
                
                if (empty($errors)) {
                    $step = 4;
                }
            } catch (PDOException $e) {
                $errors[] = 'Error creating tables: ' . $e->getMessage();
            }
            break;
            
        case 4:
            // Create admin user
            if (!isset($_SESSION['install_config'])) {
                // Try to load from temporary file
                if (file_exists('config/install_temp.json')) {
                    $tempConfig = json_decode(file_get_contents('config/install_temp.json'), true);
                    if ($tempConfig) {
                        $_SESSION['install_config'] = $tempConfig;
                    } else {
                        $errors[] = 'Konfigurasi database hilang. Silakan ulangi konfigurasi database.';
                        $step = 2;
                        break;
                    }
                } else {
                    $errors[] = 'Konfigurasi database hilang. Silakan ulangi konfigurasi database.';
                    $step = 2;
                    break;
                }
            }
            
            $adminUsername = $_POST['admin_username'] ?? '';
            $adminPassword = $_POST['admin_password'] ?? '';
            $adminEmail = $_POST['admin_email'] ?? '';
            
            if (empty($adminUsername) || empty($adminPassword) || empty($adminEmail)) {
                $errors[] = 'Semua field admin wajib diisi.';
            }
            
            if (strlen($adminPassword) < 6) {
                $errors[] = 'Password minimal 6 karakter.';
            }
            
            if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Format email tidak valid.';
            }
            
            if (empty($errors)) {
                $config = $_SESSION['install_config'];
                
                try {
                    $dsn = "mysql:host={$config['db_host']};dbname={$config['db_name']};charset=utf8mb4";
                    $pdo = new PDO($dsn, $config['db_user'], $config['db_pass']);
                    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    
                    // Create admin user
                    $hashedPassword = password_hash($adminPassword, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO users (username, password, email, role, created_at) VALUES (?, ?, ?, 'admin', NOW())");
                    $stmt->execute([$adminUsername, $hashedPassword, $adminEmail]);
                    
                    $success[] = 'Admin user berhasil dibuat!';
                    $step = 5;
                } catch (PDOException $e) {
                    $errors[] = 'Error creating admin user: ' . $e->getMessage();
                }
            }
            break;
            
        case 5:
            // Finalize installation
            if (!isset($_SESSION['install_config'])) {
                // Try to load from temporary file
                if (file_exists('config/install_temp.json')) {
                    $tempConfig = json_decode(file_get_contents('config/install_temp.json'), true);
                    if ($tempConfig) {
                        $_SESSION['install_config'] = $tempConfig;
                    } else {
                        $errors[] = 'Konfigurasi database hilang. Silakan ulangi konfigurasi database.';
                        $step = 2;
                        break;
                    }
                } else {
                    $errors[] = 'Konfigurasi database hilang. Silakan ulangi konfigurasi database.';
                    $step = 2;
                    break;
                }
            }
            
            $config = $_SESSION['install_config'];
            
            // Create production config file
            $configContent = "<?php\n";
            $configContent .= "// Production Database Configuration\n";
            $configContent .= "define('DB_HOST', '{$config['db_host']}');\n";
            $configContent .= "define('DB_NAME', '{$config['db_name']}');\n";
            $configContent .= "define('DB_USER', '{$config['db_user']}');\n";
            $configContent .= "define('DB_PASS', '{$config['db_pass']}');\n";
            $configContent .= "define('DB_CHARSET', 'utf8mb4');\n\n";
            $configContent .= "// Application Configuration\n";
            $configContent .= "define('APP_URL', '{$config['app_url']}');\n";
            $configContent .= "define('APP_NAME', 'SIMAD - Sistem Informasi Manajemen Desa');\n";
            $configContent .= "define('APP_VERSION', '1.0.0');\n\n";
            $configContent .= "// Security Configuration\n";
            $configContent .= "define('SESSION_LIFETIME', 3600);\n";
            $configContent .= "define('CSRF_TOKEN_EXPIRE', 1800);\n\n";
            $configContent .= "// File Upload Configuration\n";
            $configContent .= "define('UPLOAD_PATH', __DIR__ . '/../uploads/');\n";
            $configContent .= "define('MAX_FILE_SIZE', 10 * 1024 * 1024);\n";
            $configContent .= "define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx']);\n\n";
            $configContent .= "// Error Reporting for Production\n";
            $configContent .= "ini_set('display_errors', 0);\n";
            $configContent .= "ini_set('log_errors', 1);\n";
            $configContent .= "ini_set('error_log', __DIR__ . '/../logs/error.log');\n";
            $configContent .= "error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);\n\n";
            $configContent .= "// Timezone\n";
            $configContent .= "date_default_timezone_set('Asia/Jakarta');\n";
            $configContent .= "?>";
            
            // Write config file
            if (file_put_contents('config/database_production.php', $configContent)) {
                // Create uploads directory
                if (!is_dir('uploads')) {
                    mkdir('uploads', 0755, true);
                }
                
                // Create logs directory
                if (!is_dir('logs')) {
                    mkdir('logs', 0755, true);
                }
                
                // Create installation lock file
                file_put_contents('config/installed.lock', date('Y-m-d H:i:s'));
                
                // Clean up temporary files
                if (file_exists('config/install_temp.json')) {
                    unlink('config/install_temp.json');
                }
                
                // Clear session
                unset($_SESSION['install_config']);
                session_destroy();
                
                $success[] = 'Instalasi berhasil diselesaikan!';
                $step = 6;
            } else {
                $errors[] = 'Gagal menulis file konfigurasi. Periksa permission folder config.';
            }
            break;
    }
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SIMAD Installation</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            color: #333;
        }
        .step-indicator {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
        }
        .step {
            flex: 1;
            text-align: center;
            padding: 10px;
            background: #e9ecef;
            margin: 0 2px;
            border-radius: 4px;
        }
        .step.active {
            background: #007bff;
            color: white;
        }
        .step.completed {
            background: #28a745;
            color: white;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input[type="text"], input[type="password"], input[type="email"], input[type="url"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .btn {
            background: #007bff;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        .btn:hover {
            background: #0056b3;
        }
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        .requirements {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .req-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        .req-status {
            font-weight: bold;
        }
        .req-ok { color: #28a745; }
        .req-error { color: #dc3545; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>SIMAD Installation</h1>
            <p>Sistem Informasi Manajemen Desa</p>
        </div>
        
        <div class="step-indicator">
            <div class="step <?= $step >= 1 ? ($step == 1 ? 'active' : 'completed') : '' ?>">1. Requirements</div>
            <div class="step <?= $step >= 2 ? ($step == 2 ? 'active' : 'completed') : '' ?>">2. Database</div>
            <div class="step <?= $step >= 3 ? ($step == 3 ? 'active' : 'completed') : '' ?>">3. Install</div>
            <div class="step <?= $step >= 4 ? ($step == 4 ? 'active' : 'completed') : '' ?>">4. Admin</div>
            <div class="step <?= $step >= 5 ? ($step == 5 ? 'active' : 'completed') : '' ?>">5. Finish</div>
            <div class="step <?= $step >= 6 ? 'active' : '' ?>">6. Complete</div>
        </div>
        
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <?php foreach ($errors as $error): ?>
                    <div><?= htmlspecialchars($error) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="alert alert-success">
                <?php foreach ($success as $msg): ?>
                    <div><?= htmlspecialchars($msg) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($step == 1): ?>
            <h2>System Requirements Check</h2>
            <div class="requirements">
                <?php
                $requirements = [
                    'PHP Version >= 7.4' => version_compare(PHP_VERSION, '7.4.0', '>='),
                    'PDO Extension' => extension_loaded('pdo'),
                    'PDO MySQL Extension' => extension_loaded('pdo_mysql'),
                    'Config Directory Writable' => is_writable('config'),
                    'Database Directory Exists' => is_dir('database'),
                    'Database SQL File Exists' => file_exists('database/database.sql')
                ];
                
                $allOk = true;
                foreach ($requirements as $req => $status) {
                    $allOk = $allOk && $status;
                    echo '<div class="req-item">';
                    echo '<span>' . $req . '</span>';
                    echo '<span class="req-status ' . ($status ? 'req-ok' : 'req-error') . '">' . ($status ? 'OK' : 'FAILED') . '</span>';
                    echo '</div>';
                }
                ?>
            </div>
            
            <?php if ($allOk): ?>
                <a href="install.php?step=2" class="btn">Continue to Database Configuration</a>
            <?php else: ?>
                <p style="color: #dc3545;">Please fix the requirements above before continuing.</p>
            <?php endif; ?>
            
        <?php elseif ($step == 2): ?>
            <h2>Database Configuration</h2>
            
            <?php if (isset($_GET['debug']) && $_GET['debug'] == '1'): ?>
                <div class="alert alert-info">
                    <strong>Debug Mode:</strong><br>
                    Session ID: <?= session_id() ?><br>
                    Session Config Exists: <?= isset($_SESSION['install_config']) ? 'Yes' : 'No' ?><br>
                    Temp File Exists: <?= file_exists('config/install_temp.json') ? 'Yes' : 'No' ?><br>
                </div>
            <?php endif; ?>
            
            <form method="post">
                <div class="form-group">
                    <label for="db_host">Database Host:</label>
                    <input type="text" id="db_host" name="db_host" value="<?= $_POST['db_host'] ?? 'localhost' ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="db_name">Database Name:</label>
                    <input type="text" id="db_name" name="db_name" value="<?= $_POST['db_name'] ?? 'simad_database' ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="db_user">Database Username:</label>
                    <input type="text" id="db_user" name="db_user" value="<?= $_POST['db_user'] ?? '' ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="db_pass">Database Password:</label>
                    <input type="password" id="db_pass" name="db_pass" value="<?= $_POST['db_pass'] ?? '' ?>">
                </div>
                
                <div class="form-group">
                    <label for="app_url">Application URL:</label>
                    <input type="url" id="app_url" name="app_url" value="<?= $_POST['app_url'] ?? 'https://simad.sistemdata.id' ?>" required>
                </div>
                
                <button type="submit" class="btn">Test Connection & Continue</button>
            </form>
            
        <?php elseif ($step == 3): ?>
            <h2>Installing Database Tables</h2>
            <p>Creating database tables and initial data...</p>
            <form method="post">
                <button type="submit" class="btn">Install Database</button>
            </form>
            
        <?php elseif ($step == 4): ?>
            <h2>Create Admin User</h2>
            <form method="post">
                <div class="form-group">
                    <label for="admin_username">Admin Username:</label>
                    <input type="text" id="admin_username" name="admin_username" value="<?= $_POST['admin_username'] ?? 'admin' ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="admin_password">Admin Password:</label>
                    <input type="password" id="admin_password" name="admin_password" required>
                </div>
                
                <div class="form-group">
                    <label for="admin_email">Admin Email:</label>
                    <input type="email" id="admin_email" name="admin_email" value="<?= $_POST['admin_email'] ?? '' ?>" required>
                </div>
                
                <button type="submit" class="btn">Create Admin User</button>
            </form>
            
        <?php elseif ($step == 5): ?>
            <h2>Finalizing Installation</h2>
            <p>Creating configuration files and setting up directories...</p>
            <form method="post">
                <button type="submit" class="btn">Complete Installation</button>
            </form>
            
        <?php elseif ($step == 6): ?>
            <h2>Installation Complete!</h2>
            <div class="alert alert-success">
                <h3>SIMAD has been successfully installed!</h3>
                <p>Your application is now ready to use.</p>
            </div>
            
            <h3>Next Steps:</h3>
            <ol>
                <li>Delete this install.php file for security</li>
                <li>Login to your application using the admin credentials you created</li>
                <li>Configure your application settings</li>
                <li>Start using SIMAD!</li>
            </ol>
            
            <a href="index.php" class="btn">Go to Application</a>
        <?php endif; ?>
    </div>
</body>
</html>