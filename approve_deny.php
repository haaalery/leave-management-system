<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/auth_check.php';
checkLogin();
checkAdmin();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Verify CSRF token
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($token)) {
        die("CSRF token validation failed.");
    }

    $id = $_POST['id'];
    $action = $_POST['action']; // 'Approved' or 'Rejected'

    try {
        $pdo->beginTransaction();

        // Get request details
        $stmt = $pdo->prepare("SELECT * FROM leave_requests WHERE id = ?");
        $stmt->execute([$id]);
        $request = $stmt->fetch();

        if ($request && $request['status'] == 'Pending') {
            if ($action == 'Approved') {
                // Calculate days
                $start = new DateTime($request['start_date']);
                $end = new DateTime($request['end_date']);
                $days = $start->diff($end)->days + 1;

                // Re-verify that the user has sufficient remaining days before finalizing the approval
                $stmt = $pdo->prepare("SELECT * FROM leave_balances WHERE user_id = ? AND leave_type = ? FOR UPDATE");
                $stmt->execute([$request['user_id'], $request['leave_type']]);
                $balance = $stmt->fetch();

                if (!$balance || ($balance['total_allowed'] - $balance['days_used']) < $days) {
                    throw new Exception("Insufficient leave balance for this employee. Cannot approve.");
                }

                // Update balance
                $stmt = $pdo->prepare("UPDATE leave_balances SET days_used = days_used + ? WHERE user_id = ? AND leave_type = ?");
                $stmt->execute([$days, $request['user_id'], $request['leave_type']]);
            }

            // Update request status and admin comment
            $admin_comment = trim($_POST['admin_comment'] ?? '');
            $stmt = $pdo->prepare("UPDATE leave_requests SET status = ?, admin_comment = ? WHERE id = ?");
            $stmt->execute([$action, $admin_comment, $id]);

            $pdo->commit();
        } else {
            $pdo->rollBack();
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        die("Error processing request: " . $e->getMessage());
    }
    $redirect = $_POST['redirect'] ?? 'admin_dashboard.php';
    header("Location: " . $redirect);
} else {
    header("Location: admin_dashboard.php");
}
exit();
?>
