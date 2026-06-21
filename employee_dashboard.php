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

// Fetch recently rejected requests (within last 7 days) for notification banner
$stmt = $pdo->prepare("
    SELECT id, leave_type, start_date, end_date, admin_comment, created_at
    FROM leave_requests
    WHERE user_id = ? AND status = 'Rejected'
    AND updated_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ORDER BY updated_at DESC
");
$stmt->execute([$user_id]);
$rejected_recent = $stmt->fetchAll();
// Fallback: if updated_at doesn't exist, query without it
if ($stmt->errorCode() !== '00000') {
    $stmt = $pdo->prepare("SELECT id, leave_type, start_date, end_date, admin_comment FROM leave_requests WHERE user_id = ? AND status = 'Rejected' ORDER BY created_at DESC LIMIT 3");
    $stmt->execute([$user_id]);
    $rejected_recent = $stmt->fetchAll();
}
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

            <?php if (!empty($rejected_recent)): ?>
            <!-- Rejection Notification Banner -->
            <div id="rejection-banner" style="
                background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
                border: 1px solid #fecaca;
                border-left: 5px solid #ef4444;
                border-radius: 12px;
                padding: 18px 22px;
                margin-bottom: 24px;
                position: relative;
            ">
                <button onclick="document.getElementById('rejection-banner').style.display='none'" style="
                    position: absolute; top: 12px; right: 14px;
                    background: none; border: none; cursor: pointer;
                    color: #ef4444; font-size: 1.1rem; line-height: 1;
                " title="Dismiss"><i class="fas fa-times"></i></button>

                <div style="display: flex; align-items: flex-start; gap: 14px;">
                    <div style="
                        width: 42px; height: 42px; border-radius: 50%;
                        background: #ef4444; color: #fff;
                        display: flex; align-items: center; justify-content: center;
                        font-size: 1.2rem; flex-shrink: 0;
                    "><i class="fas fa-times-circle"></i></div>
                    <div style="flex: 1;">
                        <strong style="color: #b91c1c; font-size: 1rem;">
                            <?php echo count($rejected_recent); ?> Leave Request<?php echo count($rejected_recent) > 1 ? 's Were' : ' Was'; ?> Rejected
                        </strong>
                        <p style="color: #7f1d1d; font-size: 0.87rem; margin: 6px 0 10px;">Please review the admin's feedback below and resubmit if needed.</p>
                        <?php foreach ($rejected_recent as $rr): ?>
                        <div style="
                            background: rgba(255,255,255,0.7); border: 1px solid #fecaca;
                            border-radius: 8px; padding: 10px 14px; margin-bottom: 8px;
                            font-size: 0.87rem;
                        ">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                                <strong style="color: #991b1b;"><?php echo e($rr['leave_type']); ?></strong>
                                <span style="color: #b91c1c; font-size: 0.82rem;"><?php echo e($rr['start_date']); ?> → <?php echo e($rr['end_date']); ?></span>
                            </div>
                            <?php if ($rr['admin_comment']): ?>
                            <div style="color: #7f1d1d;">
                                <i class="fas fa-comment-alt" style="margin-right: 4px;"></i>
                                <em><?php echo e($rr['admin_comment']); ?></em>
                            </div>
                            <?php else: ?>
                            <div style="color: #9ca3af; font-style: italic;">No reason provided by admin.</div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                        <a href="request_leave.php" style="
                            display: inline-flex; align-items: center; gap: 6px;
                            margin-top: 6px; padding: 8px 16px; border-radius: 8px;
                            background: #ef4444; color: #fff; font-size: 0.85rem;
                            font-weight: 600; text-decoration: none;
                        "><i class="fas fa-redo"></i> Submit New Request</a>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            <div class="stats-container">
                <?php 
                $icons = [
                    'Vacation Leave' => 'fa-plane',
                    'Sick Leave' => 'fa-briefcase-medical',
                    'Emergency Leave' => 'fa-exclamation-triangle',
                    'Maternity Leave' => 'fa-baby',
                    'Paternity Leave' => 'fa-baby-carriage',
                    'Bereavement Leave' => 'fa-heart',
                    'Study Leave' => 'fa-graduation-cap',
                    'Compensatory Leave' => 'fa-clock',
                    'Unpaid Leave' => 'fa-user-clock',
                    'Special Leave' => 'fa-star'
                ];
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
