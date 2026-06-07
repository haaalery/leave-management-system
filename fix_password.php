<?php
require_once 'includes/config.php';

$newPassword = 'password123';
$hash = password_hash($newPassword, PASSWORD_DEFAULT);

try {
    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email IN ('admin@example.com', 'john@example.com')");
    $stmt->execute([$hash]);
    
    echo "<h2 style='color: green;'>Passwords Fixed!</h2>";
    echo "<p>Both <strong>admin@example.com</strong> and <strong>john@example.com</strong> now use the password: <code>password123</code></p>";
    echo "<p>Go try logging in now: <a href='login.php'>login.php</a></p>";
} catch (Exception $e) {
    echo "<h2 style='color: red;'>Error fixing passwords:</h2> " . $e->getMessage();
}
?>
