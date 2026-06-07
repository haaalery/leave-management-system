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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - LMS</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
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
                <span>Logged in as: <strong><?php echo $_SESSION['name']; ?></strong></span>
            </div>
        </div>

        <div class="content-wrapper">
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
                                <th>Reason</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($pending_requests)): ?>
                                <tr><td colspan="5" style="text-align: center;">No pending requests.</td></tr>
                            <?php else: ?>
                                <?php foreach ($pending_requests as $r): ?>
                                    <tr>
                                        <td><strong><?php echo $r['employee_name']; ?></strong></td>
                                        <td><?php echo $r['leave_type']; ?></td>
                                        <td><?php echo $r['start_date'] . ' to ' . $r['end_date']; ?></td>
                                        <td title="<?php echo $r['reason']; ?>"><?php echo substr($r['reason'], 0, 30) . (strlen($r['reason']) > 30 ? '...' : ''); ?></td>
                                        <td>
                                            <form action="approve_deny.php" method="POST" style="display: inline;">
                                                <input type="hidden" name="id" value="<?php echo $r['id']; ?>">
                                                <button type="submit" name="action" value="Approved" style="width: auto; background: var(--success); padding: 5px 12px; font-size: 11px;">Approve</button>
                                                <button type="submit" name="action" value="Rejected" style="width: auto; background: var(--danger); padding: 5px 12px; font-size: 11px;">Reject</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-history"></i> Recent Activity</h3>
                </div>
                <div class="card-body">
                    <table>
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Type</th>
                                <th>Dates</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($all_requests as $r): ?>
                                <tr>
                                    <td><?php echo $r['employee_name']; ?></td>
                                    <td><?php echo $r['leave_type']; ?></td>
                                    <td><?php echo $r['start_date'] . ' to ' . $r['end_date']; ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo strtolower($r['status']); ?>">
                                            <?php echo $r['status']; ?>
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
</body>
</html>
