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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="top-nav">
            <h2><i class="fas fa-home"></i> My Dashboard</h2>
            <div class="user-info">
                <span>Welcome back, <strong><?php echo e($_SESSION['name']); ?></strong></span>
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
                            <strong><?php echo e($b['leave_type']); ?></strong>
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
                        <button style="width: auto; padding: 10px 20px; background: var(--success); font-size: 0.9rem;">
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
                                <th>Details</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($requests)): ?>
                                <tr><td colspan="4" style="text-align: center;">No requests found.</td></tr>
                            <?php else: ?>
                                <?php foreach ($requests as $r): ?>
                                    <tr>
                                        <td><strong><?php echo e($r['leave_type']); ?></strong></td>
                                        <td><?php echo e($r['start_date'] . ' to ' . $r['end_date']); ?></td>
                                        <td>
                                            <div><strong>Reason:</strong> <?php echo e($r['reason']); ?></div>
                                            <?php if ($r['admin_comment']): ?>
                                                <div style="margin-top: 6px; font-size: 0.85rem; color: var(--text-muted);">
                                                    <strong>Admin Comment:</strong> <em><?php echo e($r['admin_comment']); ?></em>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($r['attachment']): ?>
                                                <div style="margin-top: 6px;">
                                                    <a href="<?php echo e($r['attachment']); ?>" target="_blank" style="color: var(--primary); text-decoration: none; font-size: 0.85rem; font-weight: 600;">
                                                        <i class="fas fa-paperclip"></i> View Attachment
                                                    </a>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge badge-<?php echo strtolower(e($r['status'])); ?>">
                                                <?php echo e($r['status']); ?>
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
