<?php
function checkLogin() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit();
    }
}

function checkAdmin() {
    if ($_SESSION['role'] !== 'Admin') {
        header("Location: employee_dashboard.php");
        exit();
    }
}

function checkEmployee() {
    if ($_SESSION['role'] !== 'Employee') {
        header("Location: admin_dashboard.php");
        exit();
    }
}

function checkManagerOrAdmin() {
    if ($_SESSION['role'] !== 'Admin' && $_SESSION['role'] !== 'Manager') {
        header("Location: login.php");
        exit();
    }
}

function e($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken($token) {
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}
?>
