<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/auth_check.php';
checkLogin();
checkEmployee();

$user_id = $_SESSION['user_id'];
$message = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $leave_type = $_POST['leave_type'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $reason = $_POST['reason'];

    // Simple date validation
    $start = new DateTime($start_date);
    $end = new DateTime($end_date);
    $interval = $start->diff($end);
    $days = $interval->days + 1;

    if ($start > $end) {
        $error = "End date cannot be before start date.";
    } else {
        // Check balance
        $stmt = $pdo->prepare("SELECT * FROM leave_balances WHERE user_id = ? AND leave_type = ?");
        $stmt->execute([$user_id, $leave_type]);
        $balance = $stmt->fetch();

        if ($balance && ($balance['total_allowed'] - $balance['days_used']) >= $days) {
            $stmt = $pdo->prepare("INSERT INTO leave_requests (user_id, leave_type, start_date, end_date, reason) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $leave_type, $start_date, $end_date, $reason]);
            $message = "Leave request submitted successfully!";
        } else {
            $error = "Insufficient leave balance. You requested $days days, but only " . ($balance['total_allowed'] - $balance['days_used']) . " remain.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Leave - LMS</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="dashboard-container" style="max-width: 600px;">
            <header style="margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 10px;">
                <h2>Request Leave</h2>
            </header>

        <?php if ($message): ?>
            <p class="success"><?php echo $message; ?></p>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <p class="error"><?php echo $error; ?></p>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Leave Type</label>
                <select name="leave_type" required>
                    <option value="Vacation">Vacation</option>
                    <option value="Sick Leave">Sick Leave</option>
                    <option value="Unpaid">Unpaid</option>
                </select>
            </div>
            <div class="form-group">
                <label>Start Date</label>
                <input type="date" name="start_date" required>
            </div>
            <div class="form-group">
                <label>End Date</label>
                <input type="date" name="end_date" required>
            </div>
            <div class="form-group">
                <label>Reason (Optional)</label>
                <textarea name="reason" rows="3"></textarea>
            </div>
            <button type="submit">Submit Request</button>
        </form>
    </div>
</body>
</html>
