<?php
require_once 'config/auth.php';

// Logout user
AuthStatic::logout();

// Redirect ke halaman login dengan pesan
header('Location: login.php?message=logout_success');
exit;
?>
