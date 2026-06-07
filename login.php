<?php
session_start();
require_once 'includes/config.php';

if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] == 'Admin') {
        header("Location: admin_dashboard.php");
    } else {
        header("Location: employee_dashboard.php");
    }
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['name'] = $user['name'];
        $_SESSION['role'] = $user['role'];

        if ($user['role'] == 'Admin') {
            header("Location: admin_dashboard.php");
        } else {
            header("Location: employee_dashboard.php");
        }
        exit();
    } else {
        $error = "Invalid email or password.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Leave Management System</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="login-page-body">
    <div class="login-card">
        <!-- Left Side -->
        <div class="login-card-left">
            <h1>LMS.</h1>
            <p>Simple and efficient way to manage employee leave requests and balances.</p>
            <div style="margin-top: 20px; font-size: 0.9em; color: #5cb85c;">
                ✔ Fast Apply <br>
                ✔ Clear Balances <br>
                ✔ Quick Approvals
            </div>
        </div>

        <!-- Right Side -->
        <div class="login-card-right">
            <h2>Login</h2>
            <?php if (isset($error)): ?>
                <p class="error"><?php echo $error; ?></p>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email" required placeholder="admin@example.com">
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" required placeholder="••••••••">
                </div>
                <button type="submit">Sign In</button>
            </form>

            <div style="margin-top: 25px; font-size: 0.8em; color: #666; border-top: 1px solid #eee; padding-top: 15px;">
                <strong>Demo:</strong> admin@example.com / password123
            </div>
        </div>
    </div>
</body>
</html>
