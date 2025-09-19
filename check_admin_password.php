<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "Checking admin password...\n";

try {
    require_once 'config/database.php';
    
    $db = getDatabase();
    
    // Get admin user
    $admin = $db->select("SELECT * FROM users WHERE username = 'admin'", []);
    
    if (empty($admin)) {
        echo "Admin user not found!\n";
        exit;
    }
    
    $admin = $admin[0];
    echo "Admin user found:\n";
    echo "- ID: " . $admin['id'] . "\n";
    echo "- Username: " . $admin['username'] . "\n";
    echo "- Name: " . $admin['nama_lengkap'] . "\n";
    echo "- Role: " . $admin['role'] . "\n";
    echo "- Password hash: " . $admin['password'] . "\n";
    echo "- Password length: " . strlen($admin['password']) . "\n";
    
    // Test different passwords
    $test_passwords = ['admin123', 'admin', 'password', '123456', 'admin123!'];
    
    echo "\nTesting passwords:\n";
    foreach ($test_passwords as $password) {
        echo "Testing '$password': ";
        
        // Test with password_verify
        if (password_verify($password, $admin['password'])) {
            echo "SUCCESS (hashed)\n";
            break;
        }
        
        // Test with plain text
        if ($password === $admin['password']) {
            echo "SUCCESS (plain text)\n";
            break;
        }
        
        echo "FAILED\n";
    }
    
    // Check if password looks like a hash
    if (strlen($admin['password']) > 20 && (strpos($admin['password'], '$') !== false)) {
        echo "\nPassword appears to be hashed (contains $ and is long)\n";
    } else {
        echo "\nPassword appears to be plain text\n";
    }
    
} catch(Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\nDone.\n";
?>