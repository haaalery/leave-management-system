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
                     WHERE lr.status = 'Pending' 
                     ORDER BY lr.created_at ASC");
$pending_requests = $stmt->fetchAll();

// Fetch all requests (for history)
$stmt = $pdo->query("SELECT lr.*, u.name as employee_name 
                     FROM leave_requests lr 
                     JOIN users u ON lr.user_id = u.id 
                     ORDER BY lr.created_at DESC LIMIT 20");
$all_requests = $stmt->fetchAll();

// 1. Total pending requests
$stmt = $pdo->query("SELECT COUNT(*) FROM leave_requests WHERE status = 'Pending'");
$pending_count = $stmt->fetchColumn();

// 2. Active leaves today (start_date <= today <= end_date AND Approved)
$stmt = $pdo->query("SELECT COUNT(*) FROM leave_requests WHERE status = 'Approved' AND CURDATE() BETWEEN start_date AND end_date");
$active_today_count = $stmt->fetchColumn();

// 3. Requests submitted today
$stmt = $pdo->query("SELECT COUNT(*) FROM leave_requests WHERE DATE(created_at) = CURDATE()");
$submitted_today_count = $stmt->fetchColumn();

// 4. Vacation requests total
$stmt = $pdo->query("SELECT COUNT(*) FROM leave_requests WHERE leave_type = 'Vacation'");
$vacation_count = $stmt->fetchColumn();

// 5. Sick Leave requests total
$stmt = $pdo->query("SELECT COUNT(*) FROM leave_requests WHERE leave_type = 'Sick Leave'");
$sick_count = $stmt->fetchColumn();

// 6. Unpaid requests total
$stmt = $pdo->query("SELECT COUNT(*) FROM leave_requests WHERE leave_type = 'Unpaid'");
$unpaid_count = $stmt->fetchColumn();

// 7. Pending user registrations
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
            <h2><i class="fas fa-tachometer-alt"></i> Admin Control Panel</h2>
            <div class="user-info">
                <span>Logged in as: <strong><?php echo e($_SESSION['name']); ?></strong></span>
            </div>
        </div>

        <div class="content-wrapper">
            <!-- Numerical Analytics Section -->
            <div class="stats-container" style="margin-bottom: 24px;">
                <div class="stat-card">
                    <div class="stat-info">
                        <strong>Active Leaves Today</strong>
                        <div class="value"><?php echo $active_today_count; ?></div>
                    </div>
                    <div class="stat-icon" style="background: rgba(16, 185, 129, 0.08); color: var(--success);">
                        <i class="fas fa-user-check"></i>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-info">
                        <strong>Requests Today</strong>
                        <div class="value"><?php echo $submitted_today_count; ?></div>
                    </div>
                    <div class="stat-icon" style="background: rgba(79, 70, 229, 0.08); color: var(--primary);">
                        <i class="fas fa-paper-plane"></i>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-info">
                        <strong>Pending Approval</strong>
                        <div class="value"><?php echo $pending_count; ?></div>
                    </div>
                    <div class="stat-icon" style="background: rgba(245, 158, 11, 0.08); color: var(--warning);">
                        <i class="fas fa-clock"></i>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-info">
                        <strong>Pending Registrations</strong>
                        <div class="value"><?php echo $pending_users_count; ?></div>
                    </div>
                    <div class="stat-icon" style="background: rgba(139, 92, 246, 0.08); color: #8b5cf6;">
                        <i class="fas fa-user-clock"></i>
                    </div>
                </div>
            </div>

            <div class="stats-container" style="margin-bottom: 35px;">
                <div class="stat-card">
                    <div class="stat-info">
                        <strong>Total Vacation Leaves</strong>
                        <div class="value"><?php echo $vacation_count; ?></div>
                    </div>
                    <div class="stat-icon" style="background: rgba(59, 130, 246, 0.08); color: #3b82f6;">
                        <i class="fas fa-plane"></i>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-info">
                        <strong>Total Sick Leaves</strong>
                        <div class="value"><?php echo $sick_count; ?></div>
                    </div>
                    <div class="stat-icon" style="background: rgba(239, 68, 68, 0.08); color: var(--danger);">
                        <i class="fas fa-briefcase-medical"></i>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-info">
                        <strong>Total Unpaid Leaves</strong>
                        <div class="value"><?php echo $unpaid_count; ?></div>
                    </div>
                    <div class="stat-icon" style="background: rgba(100, 116, 139, 0.08); color: var(--text-muted);">
                        <i class="fas fa-user-clock"></i>
                    </div>
                </div>
            </div>

            <!-- Pending User Registrations -->
            <div class="card" style="margin-bottom: 30px;">
                <div class="card-header" style="background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%); color: white;">
                    <h3><i class="fas fa-user-clock"></i> Pending User Registrations</h3>
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
                                    <th style="width: 250px;">Actions</th>
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
                                                <form action="activate_user.php" method="POST" style="display: flex; gap: 8px;">
                                                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                                    <input type="hidden" name="user_id" value="<?php echo $pu['id']; ?>">
                                                    <button type="submit" name="action" value="Active" class="btn btn-sm" style="background: var(--success); padding: 6px 12px; font-size: 0.85rem;">
                                                        <i class="fas fa-check"></i> Activate
                                                    </button>
                                                    <button type="submit" name="action" value="Rejected" class="btn btn-sm" style="background: var(--danger); padding: 6px 12px; font-size: 0.85rem;">
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

            <!-- Pending Requests -->
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
                                    <th>Type</th>
                                    <th>Dates</th>
                                    <th>Details</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($pending_requests)): ?>
                                    <tr><td colspan="5" style="text-align: center;">No pending requests.</td></tr>
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
                                                <form action="approve_deny.php" method="POST" style="display: inline-flex; gap: 8px; align-items: center; width: 100%;">
                                                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                                    <input type="hidden" name="id" value="<?php echo $r['id']; ?>">
                                                    <input type="text" name="admin_comment" placeholder="Comment (optional)" style="padding: 6px 10px; font-size: 0.82rem; width: 160px; border: 1px solid var(--border); border-radius: 4px;">
                                                    <button type="submit" name="action" value="Approved" style="width: auto; background: var(--success); padding: 6px 12px; font-size: 11px;">Approve</button>
                                                    <button type="submit" name="action" value="Rejected" style="width: auto; background: var(--danger); padding: 6px 12px; font-size: 11px;">Reject</button>
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

            <!-- Recent Activity -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-history"></i> Recent Activity</h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Employee</th>
                                    <th>Type</th>
                                    <th>Dates</th>
                                    <th>Details</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($all_requests as $r): ?>
                                    <tr>
                                        <td><strong><?php echo e($r['employee_name']); ?></strong></td>
                                        <td><?php echo e($r['leave_type']); ?></td>
                                        <td><?php echo e($r['start_date'] . ' to ' . $r['end_date']); ?></td>
                                        <td>
                                            <div><strong>Reason:</strong> <?php echo e($r['reason']); ?></div>
                                            <?php if ($r['admin_comment']): ?>
                                                <div style="margin-top: 5px; font-size: 0.85rem; color: var(--text-muted);">
                                                    <strong>Comment:</strong> <em><?php echo e($r['admin_comment']); ?></em>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($r['attachment']): ?>
                                                <div style="margin-top: 5px;">
                                                    <a href="<?php echo e($r['attachment']); ?>" target="_blank" style="color: var(--primary); text-decoration: none; font-size: 0.85rem;">
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
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
