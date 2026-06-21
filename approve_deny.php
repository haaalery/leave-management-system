<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/auth_check.php';
checkLogin();
checkManagerOrAdmin();

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

        // Get request details with department manager info
        $stmt = $pdo->prepare("
            SELECT lr.*, u.department_id, d.manager_id 
            FROM leave_requests lr
            JOIN users u ON lr.user_id = u.id
            LEFT JOIN departments d ON u.department_id = d.id
            WHERE lr.id = ?
        ");
        $stmt->execute([$id]);
        $request = $stmt->fetch();

        if (!$request) {
            throw new Exception("Leave request not found.");
        }

        $user_role = $_SESSION['role'];
        $user_id = $_SESSION['user_id'];

        // Role validation
        if ($user_role === 'Manager') {
            if ($request['manager_id'] != $user_id) {
                throw new Exception("Unauthorized: You do not manage this employee's department.");
            }
            if ($request['status'] !== 'Pending Manager Approval') {
                throw new Exception("This request is not pending manager approval.");
            }
        } elseif ($user_role === 'Admin') {
            if ($request['status'] !== 'Pending Admin Approval' && $request['status'] !== 'Pending') {
                throw new Exception("This request is not pending admin approval.");
            }
        }

        // Determine target status
        $target_status = '';
        if ($action === 'Rejected') {
            $target_status = 'Rejected';
        } else { // Approved
            if ($user_role === 'Manager') {
                $target_status = 'Pending Admin Approval';
            } else { // Admin
                $target_status = 'Approved';
            }
        }

        // Balance reduction (only on final Admin approval)
        if ($target_status === 'Approved') {
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
        $comment_prefix = $user_role === 'Manager' ? '[Manager]: ' : '[Admin]: ';
        $comment_text   = $comment_prefix . trim($_POST['admin_comment'] ?? '');

        // If comment already exists, append new comments
        if (!empty($request['admin_comment'])) {
            $comment_text = $request['admin_comment'] . "\n" . $comment_text;
        }

        $stmt = $pdo->prepare("UPDATE leave_requests SET status = ?, admin_comment = ? WHERE id = ?");
        $stmt->execute([$target_status, $comment_text, $id]);

        $pdo->commit();

        // Write audit log
        $actor_id   = $_SESSION['user_id'];
        $actor_name = $_SESSION['name'];
        $details    = json_encode([
            'employee'   => $request['user_id'],
            'leave_type' => $request['leave_type'],
            'start_date' => $request['start_date'],
            'end_date'   => $request['end_date'],
            'comment'    => $comment_text
        ]);
        $log_action = 'Leave ' . ($action === 'Approved' ? ($user_role === 'Manager' ? 'Mgr Approved' : 'Approved') : 'Rejected');
        $log = $pdo->prepare("INSERT INTO audit_logs (actor_id, actor_name, action, target_type, target_id, details) VALUES (?,?,?,?,?,?)");
        $log->execute([$actor_id, $actor_name, $log_action, 'leave_request', $id, $details]);

        $_SESSION['flash_action'] = $action;
        $_SESSION['flash_type']   = 'leave';

    } catch (Exception $e) {
        $pdo->rollBack();
        die("Error processing request: " . $e->getMessage());
    }

    $redirect = $_POST['redirect'] ?? ($user_role === 'Manager' ? 'manager_dashboard.php' : 'admin_dashboard.php');
    header("Location: " . $redirect);
} else {
    header("Location: index.php");
}
exit();
?>
