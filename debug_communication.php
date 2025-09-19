<?php
require_once 'config/database.php';
require_once 'config/auth.php';

try {
    echo "Debug Communication for Maintenance ID 7...\n\n";
    
    // Check current user session
    if (isset($_SESSION['user_id'])) {
        echo "Current User ID: {$_SESSION['user_id']}\n";
        echo "Current User Role: {$_SESSION['role']}\n";
    } else {
        echo "No user session found\n";
    }
    
    // Get maintenance details
    $stmt = $pdo->prepare("
        SELECT wm.*, d.nama_desa as desa_name,
               u1.nama_lengkap as penanggung_jawab_nama,
               u2.nama_lengkap as programmer_nama, u2.id as programmer_id
        FROM website_maintenance wm 
        LEFT JOIN desa d ON wm.desa_id = d.id 
        LEFT JOIN users u1 ON wm.penanggung_jawab_id = u1.id
        LEFT JOIN users u2 ON wm.programmer_id = u2.id
        WHERE wm.id = ?
    ");
    $stmt->execute([7]);
    $maintenance = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($maintenance) {
        echo "\nMaintenance Details:\n";
        echo "- Programmer ID: {$maintenance['programmer_id']}\n";
        echo "- Programmer Name: {$maintenance['programmer_nama']}\n";
    }
    
    // Test the exact query from website-maintenance-detail.php
    $messages_query = "
        SELECT am.id as admin_message_id, am.message as admin_message, 
               am.created_at as admin_created_at, am.admin_id,
               u_admin.nama_lengkap as admin_name,
               pr.id as reply_id, pr.reply as reply_message, 
               pr.updated_at as reply_created_at, pr.programmer_id,
               u_programmer.nama_lengkap as programmer_name
        FROM admin_messages am
        LEFT JOIN users u_admin ON am.admin_id = u_admin.id
        LEFT JOIN programmer_replies pr ON am.id = pr.admin_message_id
        LEFT JOIN users u_programmer ON pr.programmer_id = u_programmer.id
        WHERE am.maintenance_id = ?
    ";
    
    echo "\nExecuting query: $messages_query\n";
    echo "With parameter: 7\n\n";
    
    $messages_stmt = $pdo->prepare($messages_query);
    $messages_stmt->execute([7]);
    $messages_raw = $messages_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Raw query results (" . count($messages_raw) . " rows):\n";
    foreach ($messages_raw as $i => $row) {
        echo "Row $i:\n";
        echo "  Admin Message ID: {$row['admin_message_id']}\n";
        echo "  Admin Message: {$row['admin_message']}\n";
        echo "  Admin Name: {$row['admin_name']}\n";
        echo "  Reply ID: {$row['reply_id']}\n";
        echo "  Reply Message: {$row['reply_message']}\n";
        echo "  Programmer Name: {$row['programmer_name']}\n";
        echo "  ---\n";
    }
    
    // Test grouping logic
    $messages = [];
    foreach ($messages_raw as $row) {
        $admin_msg_id = $row['admin_message_id'];
        
        if (!isset($messages[$admin_msg_id])) {
            $messages[$admin_msg_id] = [
                'id' => $admin_msg_id,
                'message' => $row['admin_message'],
                'created_at' => $row['admin_created_at'],
                'admin_name' => $row['admin_name'],
                'replies' => []
            ];
        }
        
        if ($row['reply_id']) {
            $messages[$admin_msg_id]['replies'][] = [
                'id' => $row['reply_id'],
                'message' => $row['reply_message'],
                'created_at' => $row['reply_created_at'],
                'programmer_name' => $row['programmer_name']
            ];
        }
    }
    
    echo "\nGrouped messages (" . count($messages) . " admin messages):\n";
    foreach ($messages as $msg) {
        echo "Admin Message: {$msg['message']} (by {$msg['admin_name']})\n";
        echo "  Replies: " . count($msg['replies']) . "\n";
        foreach ($msg['replies'] as $reply) {
            echo "    - {$reply['message']} (by {$reply['programmer_name']})\n";
        }
        echo "\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>