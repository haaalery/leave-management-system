<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/auth_check.php';
checkLogin();
checkAdmin();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($token)) {
        die("CSRF token validation failed.");
    }

    $user_id = (int)($_POST['user_id'] ?? 0);
    $action  = $_POST['action'] ?? ''; // 'Active' or 'Rejected'

    if (!in_array($action, ['Active', 'Rejected'])) {
        die("Invalid action.");
    }

    try {
        $pdo->beginTransaction();

        // Fetch the user
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND status = 'Pending'");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();

        if ($user) {
            // Update their status
            $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
            $stmt->execute([$action, $user_id]);

            // If activating an employee, initialize leave balances
            if ($action === 'Active' && $user['role'] === 'Employee') {
                // Check no balances exist yet
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM leave_balances WHERE user_id = ?");
                $stmt->execute([$user_id]);
                if ($stmt->fetchColumn() == 0) {
                    $stmt = $pdo->prepare("INSERT INTO leave_balances (user_id, leave_type, total_allowed, days_used) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$user_id, 'Vacation',   15, 0]);
                    $stmt->execute([$user_id, 'Sick Leave', 10, 0]);
                    $stmt->execute([$user_id, 'Unpaid',     30, 0]);
                }
            }
            $pdo->commit();
        } else {
            $pdo->rollBack();
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        die("Error: " . $e->getMessage());
    }
}

header("Location: admin_dashboard.php");
exit();
?>
