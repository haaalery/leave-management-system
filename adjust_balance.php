<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/auth_check.php';
checkLogin();
checkAdmin();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // CSRF Check
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($token)) {
        die("CSRF token validation failed.");
    }

    $user_id = (int)$_POST['user_id'];
    $leave_type = $_POST['leave_type'];
    $adjustment = (int)$_POST['adjustment'];

    // Adjust total_allowed for the user
    $stmt = $pdo->prepare("UPDATE leave_balances 
                           SET total_allowed = total_allowed + ? 
                           WHERE user_id = ? AND leave_type = ?");
    $stmt->execute([$adjustment, $user_id, $leave_type]);
}

header("Location: manage_users.php");
exit();
?>
