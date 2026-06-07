<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/auth_check.php';
checkLogin();
checkEmployee();

$user_id = $_SESSION['user_id'];

// Fetch leave balances
$stmt = $pdo->prepare("SELECT * FROM leave_balances WHERE user_id = ?");
$stmt->execute([$user_id]);
$balances = $stmt->fetchAll();

// Fetch leave requests
$stmt = $pdo->prepare("SELECT * FROM leave_requests WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$user_id]);
$requests = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Dashboard - LMS</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="dashboard-container">
            <header style="margin-bottom: 30px; border-bottom: 1px solid #eee; padding-bottom: 10px;">
                <h2>Employee Dashboard</h2>
                <p>Welcome back, <strong><?php echo $_SESSION['name']; ?></strong></p>
            </header>

            <section>
                <h3>Your Leave Balances</h3>
                <div class="stats-container">
                    <?php foreach ($balances as $b): ?>
                        <div class="stat-card">
                            <strong><?php echo $b['leave_type']; ?></strong>
                            <div class="value">
                                <?php echo $b['total_allowed'] - $b['days_used']; ?> / <?php echo $b['total_allowed']; ?>
                            </div>
                            <small>Days Remaining</small>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

        <section style="margin-top: 30px;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <h3>My Leave Requests</h3>
                <a href="request_leave.php"><button style="width: auto;">Request Leave</button></a>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Start Date</th>
                        <th>End Date</th>
                        <th>Reason</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($requests)): ?>
                        <tr><td colspan="5" style="text-align: center;">No requests found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($requests as $r): ?>
                            <tr>
                                <td><?php echo $r['leave_type']; ?></td>
                                <td><?php echo $r['start_date']; ?></td>
                                <td><?php echo $r['end_date']; ?></td>
                                <td><?php echo $r['reason']; ?></td>
                                <td>
                                    <span class="badge badge-<?php echo strtolower($r['status']); ?>">
                                        <?php echo $r['status']; ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </section>
    </div>
</body>
</html>
