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
<body>
    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="dashboard-container">
            <header style="margin-bottom: 30px; border-bottom: 1px solid #eee; padding-bottom: 10px;">
                <h2>Admin Control Panel</h2>
                <p>Logged in as: <strong><?php echo $_SESSION['name']; ?></strong></p>
            </header>

            <section>
                <h3>Pending Leave Requests</h3>
                <table>
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Type</th>
                        <th>Start Date</th>
                        <th>End Date</th>
                        <th>Reason</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($pending_requests)): ?>
                        <tr><td colspan="6" style="text-align: center;">No pending requests.</td></tr>
                    <?php else: ?>
                        <?php foreach ($pending_requests as $r): ?>
                            <tr>
                                <td><?php echo $r['employee_name']; ?></td>
                                <td><?php echo $r['leave_type']; ?></td>
                                <td><?php echo $r['start_date']; ?></td>
                                <td><?php echo $r['end_date']; ?></td>
                                <td title="<?php echo $r['reason']; ?>"><?php echo substr($r['reason'], 0, 20) . '...'; ?></td>
                                <td>
                                    <form action="approve_deny.php" method="POST" style="display: inline;">
                                        <input type="hidden" name="id" value="<?php echo $r['id']; ?>">
                                        <button type="submit" name="action" value="Approved" style="width: auto; background: #5cb85c; padding: 5px 10px; font-size: 12px;">Approve</button>
                                        <button type="submit" name="action" value="Rejected" style="width: auto; background: #d9534f; padding: 5px 10px; font-size: 12px;">Reject</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </section>

        <section style="margin-top: 30px;">
            <h3>Recent Activity</h3>
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
        </section>
    </div>
</body>
</html>
