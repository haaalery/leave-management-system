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
    <div class="login-wrapper">
        <!-- Left Side: Description with Photo Background -->
        <div class="login-left">
            <div class="login-left-content">
                <h1 style="font-size: 4rem; font-weight: 800; margin-bottom: 10px;">LMS.</h1>
                <p style="font-size: 1.2rem; color: #ddd; max-width: 500px;">
                    Simple and efficient way to manage employee leave requests and balances.
                </p>
                <div style="margin-top: 30px; display: flex; gap: 30px;">
                    <div>
                        <h4 style="margin: 0; color: #5cb85c;">Fast</h4>
                        <small>Apply in seconds</small>
                    </div>
                    <div>
                        <h4 style="margin: 0; color: #5cb85c;">Simple</h4>
                        <small>Clear balances</small>
                    </div>
                    <div>
                        <h4 style="margin: 0; color: #5cb85c;">Easy</h4>
                        <small>Quick approvals</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Side: Login Form -->
        <div class="login-right">
            <div class="login-form-container">
                <h2 style="font-size: 2rem; font-weight: 700; margin-bottom: 30px;">Welcome Back</h2>
                <?php if (isset($error)): ?>
                    <p class="error"><?php echo $error; ?></p>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="form-group">
                        <label>Email Address</label>
                        <input type="email" name="email" required placeholder="admin@example.com" style="padding: 12px;">
                    </div>
                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" name="password" required placeholder="••••••••" style="padding: 12px;">
                    </div>
                    <button type="submit" style="padding: 12px; font-weight: 600;">Sign In</button>
                </form>

                <div style="margin-top: 40px; border-top: 1px solid #eee; padding-top: 20px;">
                    <p style="font-size: 0.85em; color: #666;">
                        <strong>Demo:</strong> admin@example.com / password123
                    </p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
