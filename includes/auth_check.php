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
?>
