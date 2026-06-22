<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/auth_check.php';
checkLogin();
checkAdmin();

// Fetch all pending requests with user names
$stmt = $pdo->query("SELECT lr.*, u.name as employee_name 
                     FROM leave_requests lr 
                     JOIN users u ON lr.user_id = u.id 
                     WHERE lr.status IN ('Pending', 'Pending Manager Approval', 'Pending Admin Approval')
                     ORDER BY lr.created_at ASC");
$pending_requests = $stmt->fetchAll();

// 1. Total pending requests
$stmt = $pdo->query("SELECT COUNT(*) FROM leave_requests WHERE status IN ('Pending', 'Pending Manager Approval', 'Pending Admin Approval')");
$pending_count = $stmt->fetchColumn();

// 2. Active leaves today (start_date <= today <= end_date AND Approved)
$stmt = $pdo->query("SELECT COUNT(*) FROM leave_requests WHERE status = 'Approved' AND CURDATE() BETWEEN start_date AND end_date");
$active_today_count = $stmt->fetchColumn();

// 3. Requests submitted today
$stmt = $pdo->query("SELECT COUNT(*) FROM leave_requests WHERE DATE(created_at) = CURDATE()");
$submitted_today_count = $stmt->fetchColumn();

// 4. Dynamic leave type requests total
$stmt = $pdo->query("SELECT leave_type, COUNT(*) as cnt FROM leave_requests GROUP BY leave_type");
$type_counts = $stmt->fetchAll();
$leave_counts = [];
foreach ($type_counts as $tc) {
    $leave_counts[$tc['leave_type']] = $tc['cnt'];
}

// 5. Pending user registrations
$stmt = $pdo->query("SELECT * FROM users WHERE status = 'Pending' ORDER BY id ASC");
$pending_users = $stmt->fetchAll();
$pending_users_count = count($pending_users);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - LMS</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="top-nav">
            <h2><i class="fas fa-tachometer-alt"></i> Admin Dashboard</h2>
            <div class="user-info">
                <span>Welcome back, <strong><?php echo e($_SESSION['name']); ?></strong></span>
            </div>
        </div>

        <div class="content-wrapper">
            
            <!-- Leave Type Statistics (Colorful Cards) -->
            <div class="stats-container">
                <?php 
                $icons = [
                    'Vacation Leave' => ['icon' => 'fa-plane', 'color' => '#3b82f6', 'bg' => 'rgba(59, 130, 246, 0.08)'],
                    'Sick Leave' => ['icon' => 'fa-briefcase-medical', 'color' => '#ef4444', 'bg' => 'rgba(239, 68, 68, 0.08)'],
                    'Emergency Leave' => ['icon' => 'fa-exclamation-triangle', 'color' => '#f59e0b', 'bg' => 'rgba(245, 158, 11, 0.08)'],
                    'Maternity Leave' => ['icon' => 'fa-baby', 'color' => '#ec4899', 'bg' => 'rgba(236, 72, 153, 0.08)'],
                    'Paternity Leave' => ['icon' => 'fa-baby-carriage', 'color' => '#06b6d4', 'bg' => 'rgba(6, 182, 212, 0.08)'],
                    'Bereavement Leave' => ['icon' => 'fa-heart', 'color' => '#ef4444', 'bg' => 'rgba(239, 68, 68, 0.08)'],
                    'Study Leave' => ['icon' => 'fa-graduation-cap', 'color' => '#8b5cf6', 'bg' => 'rgba(139, 92, 246, 0.08)'],
                    'Compensatory Leave' => ['icon' => 'fa-clock', 'color' => '#10b981', 'bg' => 'rgba(16, 185, 129, 0.08)'],
                    'Unpaid Leave' => ['icon' => 'fa-user-clock', 'color' => '#64748b', 'bg' => 'rgba(100, 116, 139, 0.08)'],
                    'Special Leave' => ['icon' => 'fa-star', 'color' => '#eab308', 'bg' => 'rgba(234, 179, 8, 0.08)']
                ];
                foreach ($icons as $type => $meta):
                    $count = $leave_counts[$type] ?? 0;
                ?>
                    <div class="stat-card">
                        <div class="stat-info">
                            <strong><?php echo e($type); ?></strong>
                            <div class="value"><?php echo $count; ?></div>
                        </div>
                        <div class="stat-icon" style="background: <?php echo $meta['bg']; ?>; color: <?php echo $meta['color']; ?>;">
                            <i class="fas <?php echo $meta['icon']; ?>"></i>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Pending Leave Requests Section -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-clock"></i> Pending Leave Requests</h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Employee</th>
                                    <th>Leave Type</th>
                                    <th>Dates</th>
                                    <th>Details</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($pending_requests)): ?>
                                    <tr><td colspan="6" style="text-align: center;">No pending requests.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($pending_requests as $r): ?>
                                        <tr>
                                            <td><strong><?php echo e($r['employee_name']); ?></strong></td>
                                            <td><?php echo e($r['leave_type']); ?></td>
                                            <td><?php echo e($r['start_date'] . ' to ' . $r['end_date']); ?></td>
                                            <td>
                                                <div><?php echo e($r['reason']); ?></div>
                                                <?php if ($r['attachment']): ?>
                                                    <div style="margin-top: 5px;">
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
                                            <td>
                                                <form action="approve_deny.php" method="POST" style="display: inline-flex; gap: 5px;">
                                                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                                    <input type="hidden" name="id" value="<?php echo $r['id']; ?>">
                                                    <button type="submit" name="action" value="approve" style="background: var(--success); color: #fff; padding: 6px 12px; font-size: 0.8rem; border-radius: 4px; border: none; cursor: pointer;">
                                                        <i class="fas fa-check"></i> Approve
                                                    </button>
                                                    <button type="submit" name="action" value="reject" style="background: var(--danger); color: #fff; padding: 6px 12px; font-size: 0.8rem; border-radius: 4px; border: none; cursor: pointer;">
                                                        <i class="fas fa-times"></i> Reject
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Pending User Registrations Section -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-user-check"></i> Pending User Registrations</h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Department</th>
                                    <th>Position</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($pending_users)): ?>
                                    <tr><td colspan="5" style="text-align: center;">No pending user registrations.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($pending_users as $pu): ?>
                                        <tr>
                                            <td><strong><?php echo e($pu['name']); ?></strong></td>
                                            <td><?php echo e($pu['email']); ?></td>
                                            <td><?php echo e($pu['department'] ?: 'N/A'); ?></td>
                                            <td><?php echo e($pu['position'] ?: 'N/A'); ?></td>
                                            <td>
                                                <form action="activate_user.php" method="POST" style="display: inline-flex; gap: 5px;">
                                                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                                    <input type="hidden" name="user_id" value="<?php echo $pu['id']; ?>">
                                                    <button type="submit" name="action" value="activate" style="background: var(--success); color: #fff; padding: 6px 12px; font-size: 0.8rem; border-radius: 4px; border: none; cursor: pointer;">
                                                        <i class="fas fa-check"></i> Activate
                                                    </button>
                                                    <button type="submit" name="action" value="reject" style="background: var(--danger); color: #fff; padding: 6px 12px; font-size: 0.8rem; border-radius: 4px; border: none; cursor: pointer;">
                                                        <i class="fas fa-times"></i> Reject
                                                    </button>
                                                </form>
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
    </div>
</body>
</html>
