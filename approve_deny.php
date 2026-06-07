<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/auth_check.php';
checkLogin();
checkAdmin();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
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

                // Update balance
                $stmt = $pdo->prepare("UPDATE leave_balances SET days_used = days_used + ? WHERE user_id = ? AND leave_type = ?");
                $stmt->execute([$days, $request['user_id'], $request['leave_type']]);
            }

            // Update request status
            $stmt = $pdo->prepare("UPDATE leave_requests SET status = ? WHERE id = ?");
            $stmt->execute([$action, $id]);

            $pdo->commit();
        } else {
            $pdo->rollBack();
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        die("Error processing request: " . $e->getMessage());
    }
}

header("Location: admin_dashboard.php");
exit();
?>
