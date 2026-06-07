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
    $email = trim($_POST['email']); // Added trim
    $password = $_POST['password'];

    echo "<!-- DEBUG: Trying to login with Email: [$email] -->\n";

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        echo "<!-- DEBUG: User found in DB. Comparing passwords... -->\n";
        if (password_verify($password, $user['password'])) {
            echo "<!-- DEBUG: Password Verify PASSED. -->\n";
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
            echo "<!-- DEBUG: Password Verify FAILED. -->\n";
            $error = "Invalid email or password. (Debug: Password mismatch)";
        }
    } else {
        echo "<!-- DEBUG: User NOT found in DB for email [$email]. -->\n";
        $error = "Invalid email or password. (Debug: User not found)";
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
<body>
    <div class="login-container">
        <h2>LMS Login</h2>
        <?php if (isset($error)): ?>
            <p class="error"><?php echo $error; ?></p>
        <?php endif; ?>
        <form method="POST">
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email" required placeholder="admin@example.com">
                <small style="color: #888;">* You must use the full email address.</small>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" required placeholder="password123">
            </div>
            <button type="submit">Login</button>
        </form>
        <p style="margin-top: 10px; font-size: 0.8em; color: #666;">
            Demo Admin: admin@example.com / password123<br>
            Demo Employee: john@example.com / password123
        </p>
    </div>
</body>
</html>
