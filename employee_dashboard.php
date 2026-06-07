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
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Dashboard - LMS</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="top-nav">
            <h2><i class="fas fa-home"></i> My Dashboard</h2>
            <div class="user-info">
                <span>Welcome back, <strong><?php echo $_SESSION['name']; ?></strong></span>
            </div>
        </div>

        <div class="content-wrapper">
            <div class="stats-container">
                <?php 
                $icons = ['Vacation' => 'fa-plane', 'Sick Leave' => 'fa-briefcase-medical', 'Unpaid' => 'fa-user-clock'];
                foreach ($balances as $b): 
                    $icon = $icons[$b['leave_type']] ?? 'fa-calendar';
                ?>
                    <div class="stat-card">
                        <div class="stat-info">
                            <strong><?php echo $b['leave_type']; ?></strong>
                            <div class="value">
                                <?php echo $b['total_allowed'] - $b['days_used']; ?> / <?php echo $b['total_allowed']; ?>
                            </div>
                        </div>
                        <div class="stat-icon">
                            <i class="fas <?php echo $icon; ?>"></i>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-list"></i> My Leave Requests</h3>
                    <a href="request_leave.php" style="text-decoration: none;">
                        <button style="width: auto; padding: 10px 20px; background: #5cb85c; font-size: 0.9rem;">
                            <i class="fas fa-plus"></i> Request Leave
                        </button>
                    </a>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table>
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Dates</th>
                                <th>Reason</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($requests)): ?>
                                <tr><td colspan="4" style="text-align: center;">No requests found.</td></tr>
                            <?php else: ?>
                                <?php foreach ($requests as $r): ?>
                                    <tr>
                                        <td><strong><?php echo $r['leave_type']; ?></strong></td>
                                        <td><?php echo $r['start_date'] . ' to ' . $r['end_date']; ?></td>
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
                </div>
            </div>
        </div>
    </div>
</body>
</html>
