<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/auth_check.php';

// Already logged in? Redirect away
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$error = "";
$success = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // CSRF
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($token)) {
        die("CSRF token validation failed.");
    }

    $first_name  = trim($_POST['first_name'] ?? '');
    $middle_name = trim($_POST['middle_name'] ?? '');
    $last_name   = trim($_POST['last_name'] ?? '');
    $email       = trim($_POST['email'] ?? '');
    $password    = $_POST['password'] ?? '';
    $confirm     = $_POST['confirm_password'] ?? '';
    $department  = trim($_POST['department'] ?? '');
    $position    = trim($_POST['position'] ?? '');

    if (empty($first_name) || empty($last_name) || empty($email) || empty($password) || empty($confirm)) {
        $error = "All required fields must be filled.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif ($password !== $confirm) {
        $error = "Passwords do not match.";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters long.";
    } else {
        // Check email uniqueness
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetchColumn() > 0) {
            $error = "This email address is already registered.";
        } else {
            // Concatenate name
            $fullname = $first_name . ($middle_name ? ' ' . $middle_name : '') . ' ' . $last_name;
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (name, first_name, middle_name, last_name, email, password, role, status, department, position) VALUES (?, ?, ?, ?, ?, ?, 'Employee', 'Pending', ?, ?)");
            $stmt->execute([$fullname, $first_name, $middle_name, $last_name, $email, $hashed, $department, $position]);
            $success = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Leave Management System</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .register-page-body { display: block; background: #f8fafc; }
        .register-wrapper {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
        }
        .register-card {
            background: #fff;
            border-radius: 16px;
            border: 1px solid var(--border);
            box-shadow: var(--shadow-premium);
            width: 100%;
            max-width: 560px;
            padding: 48px 40px;
        }
        .register-logo {
            font-size: 1.6rem;
            font-weight: 800;
            color: var(--primary);
            letter-spacing: 2px;
            margin-bottom: 6px;
        }
        .register-logo::before {
            content: '';
            display: inline-block;
            width: 10px;
            height: 10px;
            background: var(--primary);
            border-radius: 50%;
            box-shadow: 0 0 8px var(--primary);
            margin-right: 8px;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        .register-footer {
            text-align: center;
            margin-top: 24px;
            font-size: 0.88rem;
            color: var(--text-muted);
        }
        .register-footer a {
            color: var(--primary);
            font-weight: 600;
            text-decoration: none;
        }
        .success-box {
            text-align: center;
            padding: 20px 0;
        }
        .success-box i {
            font-size: 3rem;
            color: var(--success);
            margin-bottom: 16px;
        }
        .success-box h3 { margin-bottom: 10px; }
        .success-box p { color: var(--text-muted); font-size: 0.92rem; line-height: 1.6; }
    </style>
</head>
<body class="register-page-body">
    <div class="register-wrapper">
        <div class="register-card">
            <div class="register-logo">LMS.</div>
            <h2 style="font-size: 1.6rem; font-weight: 700; margin-bottom: 6px;">Create an Account</h2>
            <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 28px;">Submit your registration for admin review.</p>

            <?php if ($success): ?>
                <div class="success-box">
                    <i class="fas fa-check-circle"></i>
                    <h3>Registration Submitted!</h3>
                    <p>Your account is pending administrator approval.<br>You will be able to log in once your account has been activated.</p>
                    <a href="login.php" style="display: inline-block; margin-top: 20px; color: var(--primary); font-weight: 600; text-decoration: none;">
                        <i class="fas fa-arrow-left"></i> Back to Login
                    </a>
                </div>
            <?php else: ?>
                <?php if ($error): ?>
                    <p class="error"><?php echo e($error); ?></p>
                <?php endif; ?>

                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">

                    <div class="form-row">
                        <div class="form-group">
                            <label>First Name <span style="color:var(--danger)">*</span></label>
                            <input type="text" name="first_name" required placeholder="John" value="<?php echo e($_POST['first_name'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label>Middle Name <span style="font-size:0.8rem; color:var(--text-muted);">(Optional)</span></label>
                            <input type="text" name="middle_name" placeholder="Doe" value="<?php echo e($_POST['middle_name'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Last Name <span style="color:var(--danger)">*</span></label>
                        <input type="text" name="last_name" required placeholder="Smith" value="<?php echo e($_POST['last_name'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label>Email Address <span style="color:var(--danger)">*</span></label>
                        <input type="email" name="email" required placeholder="john@example.com" value="<?php echo e($_POST['email'] ?? ''); ?>">
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Department</label>
                            <input type="text" name="department" placeholder="e.g. Engineering" value="<?php echo e($_POST['department'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label>Position / Job Title</label>
                            <input type="text" name="position" placeholder="e.g. Developer" value="<?php echo e($_POST['position'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Password <span style="color:var(--danger)">*</span></label>
                            <input type="password" name="password" required placeholder="Min. 8 characters">
                        </div>
                        <div class="form-group">
                            <label>Confirm Password <span style="color:var(--danger)">*</span></label>
                            <input type="password" name="confirm_password" required placeholder="Repeat password">
                        </div>
                    </div>

                    <button type="submit" style="width: 100%; margin-top: 8px;">
                        <i class="fas fa-user-plus"></i> Submit Registration
                    </button>
                </form>

                <div class="register-footer">
                    Already have an account? <a href="login.php">Sign in</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
