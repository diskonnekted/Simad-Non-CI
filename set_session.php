<?php
// Set session untuk testing
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$_SESSION['desa_id'] = 1;
$_SESSION['user_id'] = 1;
$_SESSION['username'] = 'admin';
$_SESSION['role'] = 'admin';

echo "<h3>Session berhasil di-set!</h3>";
echo "<p>desa_id: " . $_SESSION['desa_id'] . "</p>";
echo "<p>user_id: " . $_SESSION['user_id'] . "</p>";
echo "<p>username: " . $_SESSION['username'] . "</p>";
echo "<p>role: " . $_SESSION['role'] . "</p>";
echo "<br><a href='client/promo.php'>Buka Promo.php</a>";
?>