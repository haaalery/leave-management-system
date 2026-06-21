<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/auth_check.php';
checkLogin();
checkManagerOrAdmin();

$manager_id = $_SESSION['user_id'];

// Get the department managed by this manager
$dept_stmt = $pdo->prepare("SELECT * FROM departments WHERE manager_id = ?");
$dept_stmt->execute([$manager_id]);
$department = $dept_stmt->fetch();

$dept_id   = $department ? (int)$department['id'] : 0;
$dept_name = $department ? $department['name'] : '';

$pending_requests = [];
$history_requests = [];
$employee_count   = 0;
$active_today     = 0;

if ($dept_id) {
    // 1. Fetch pending requests for this manager's department
    $stmt = $pdo->prepare("
        SELECT lr.*, u.name as employee_name 
        FROM leave_requests lr 
        JOIN users u ON lr.user_id = u.id 
        WHERE u.department_id = ? AND lr.status = 'Pending Manager Approval' 
        ORDER BY lr.created_at ASC
    ");
    $stmt->execute([$dept_id]);
    $pending_requests = $stmt->fetchAll();

    // 2. Fetch recent history for this department
    $stmt = $pdo->prepare("
        SELECT lr.*, u.name as employee_name 
        FROM leave_requests lr 
        JOIN users u ON lr.user_id = u.id 
        WHERE u.department_id = ? AND lr.status != 'Pending Manager Approval' 
        ORDER BY lr.created_at DESC LIMIT 15
    ");
    $stmt->execute([$dept_id]);
    $history_requests = $stmt->fetchAll();

    // 3. Department Employee count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE department_id = ? AND role = 'Employee' AND status = 'Active'");
    $stmt->execute([$dept_id]);
    $employee_count = $stmt->fetchColumn();

    // 4. Active leaves today in department
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM leave_requests lr
        JOIN users u ON lr.user_id = u.id
        WHERE u.department_id = ? AND lr.status = 'Approved' AND CURDATE() BETWEEN lr.start_date AND lr.end_date
    ");
    $stmt->execute([$dept_id]);
    $active_today = $stmt->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manager Dashboard - LMS</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .lms-modal-overlay {
            display: none; position: fixed; inset: 0;
            background: rgba(15,23,42,0.55); backdrop-filter: blur(6px);
            z-index: 2000; align-items: center; justify-content: center;
        }
        .lms-modal-overlay.show { display: flex; }
        .lms-modal {
            background: #fff; border-radius: 20px; padding: 40px 36px;
            max-width: 420px; width: 90%; text-align: center;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.18);
            animation: lmsPop 0.35s cubic-bezier(0.34,1.56,0.64,1);
        }
        @keyframes lmsPop {
            from { transform: scale(0.75); opacity: 0; }
            to   { transform: scale(1);    opacity: 1; }
        }
        .lms-modal .modal-icon {
            width: 72px; height: 72px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 20px; font-size: 2rem; color: #fff;
        }
        .modal-icon.success { background: linear-gradient(135deg,#10b981,#059669); box-shadow: 0 0 0 12px rgba(16,185,129,0.12); }
        .modal-icon.danger  { background: linear-gradient(135deg,#ef4444,#dc2626); box-shadow: 0 0 0 12px rgba(239,68,68,0.12); }
        .lms-modal h2 { font-size:1.4rem; font-weight:700; margin-bottom:8px; color:var(--text); }
        .lms-modal p  { color:var(--text-muted); font-size:0.92rem; margin-bottom:0; line-height:1.6; }
        .lms-modal .modal-detail {
            background:#f8fafc; border:1px solid var(--border); border-radius:10px;
            padding:12px 16px; margin:18px 0; font-size:0.87rem; text-align:left;
        }
        .lms-modal .modal-detail div { display:flex; justify-content:space-between; padding:3px 0; }
        .lms-modal .modal-detail div span:first-child { color:var(--text-muted); }
        .modal-btn-row { display:flex; gap:10px; margin-top:22px; }
        .modal-btn-row button {
            flex:1; padding:11px; border-radius:10px; font-size:0.9rem;
            font-weight:600; cursor:pointer; border:none; transition:opacity 0.2s;
            text-align:center; display:inline-flex; align-items:center; justify-content:center; gap:6px;
        }
        .modal-btn-row button:hover { opacity:0.85; }
        .btn-confirm-approve { background:var(--success); color:#fff; }
        .btn-confirm-reject  { background:var(--danger);  color:#fff; }
        .btn-cancel-modal    { background:#f1f5f9; color:var(--text); border:1px solid var(--border) !important; }
    </style>
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="top-nav">
            <h2><i class="fas fa-user-tie"></i> Department Head Dashboard</h2>
            <div class="user-info">
                <span>Department: <strong><?php echo $dept_name ? e($dept_name) : 'None Assigned'; ?></strong></span>
            </div>
        </div>

        <div class="content-wrapper">
            <?php if (!$dept_id): ?>
                <div class="card" style="text-align: center; padding: 40px;">
                    <i class="fas fa-exclamation-triangle" style="font-size: 3rem; color: var(--danger); margin-bottom: 20px;"></i>
                    <h2>No Department Assigned</h2>
                    <p style="color: var(--text-muted); margin-top: 10px;">You are not currently assigned as the head of any department. Please contact the administrator.</p>
                </div>
            <?php else: ?>

                <!-- Metrics Row -->
                <div class="metrics-grid">
                    <div class="metric-card">
                        <div class="metric-icon" style="background: rgba(79, 70, 229, 0.1); color: var(--primary);">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="metric-info">
                            <h3><?php echo count($pending_requests); ?></h3>
                            <p>Pending Approvals</p>
                        </div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-icon" style="background: rgba(16, 185, 129, 0.1); color: var(--success);">
                            <i class="fas fa-plane-departure"></i>
                        </div>
                        <div class="metric-info">
                            <h3><?php echo $active_today; ?></h3>
                            <p>On Leave Today</p>
                        </div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-icon" style="background: rgba(59, 130, 246, 0.1); color: #3b82f6;">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="metric-info">
                            <h3><?php echo $employee_count; ?></h3>
                            <p>Active Staff</p>
                        </div>
                    </div>
                </div>

                <!-- Pending Leaves -->
                <div class="card" style="margin-top: 24px;">
                    <div class="card-header">
                        <h3><i class="fas fa-clock" style="color: var(--primary);"></i> Pending Department Leave Requests</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($pending_requests)): ?>
                            <p style="text-align: center; color: var(--text-muted); padding: 20px 0;">No pending requests from your department.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Employee</th>
                                            <th>Leave Type</th>
                                            <th>Date Range</th>
                                            <th>Reason</th>
                                            <th>Attachment</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($pending_requests as $r): ?>
                                            <tr>
                                                <td><strong><?php echo e($r['employee_name']); ?></strong></td>
                                                <td><?php echo e($r['leave_type']); ?></td>
                                                <td>
                                                    <?php 
                                                    $start = new DateTime($r['start_date']);
                                                    $end = new DateTime($r['end_date']);
                                                    $days = $start->diff($end)->days + 1;
                                                    echo $start->format('M d, Y') . ' to ' . $end->format('M d, Y');
                                                    ?>
                                                    <span style="display:block; font-size:0.8rem; color:var(--text-muted)"><?php echo $days; ?> day(s)</span>
                                                </td>
                                                <td><?php echo e($r['reason']); ?></td>
                                                <td>
                                                    <?php if ($r['attachment']): ?>
                                                        <a href="<?php echo e($r['attachment']); ?>" target="_blank" class="badge" style="background: var(--primary); text-decoration:none;"><i class="fas fa-paperclip"></i> View File</a>
                                                    <?php else: ?>
                                                        <span style="color: var(--text-muted); font-style:italic;">None</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="action-buttons">
                                                    <button onclick="triggerApproval(<?php echo $r['id']; ?>, '<?php echo e(addslashes($r['employee_name'])); ?>', '<?php echo e($r['leave_type']); ?>', '<?php echo $start->format('M d'); ?> - <?php echo $end->format('M d'); ?>')" style="background:var(--success); color:#fff;"><i class="fas fa-check"></i> Approve</button>
                                                    <button onclick="triggerRejection(<?php echo $r['id']; ?>, '<?php echo e(addslashes($r['employee_name'])); ?>', '<?php echo e($r['leave_type']); ?>', '<?php echo $start->format('M d'); ?> - <?php echo $end->format('M d'); ?>')" style="background:var(--danger); color:#fff;"><i class="fas fa-times"></i> Reject</button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- History -->
                <div class="card" style="margin-top: 24px;">
                    <div class="card-header">
                        <h3><i class="fas fa-history" style="color: var(--text-muted);"></i> Recent Department Leave History</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($history_requests)): ?>
                            <p style="text-align: center; color: var(--text-muted); padding: 20px 0;">No history records found.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Employee</th>
                                            <th>Leave Type</th>
                                            <th>Date Range</th>
                                            <th>Reason</th>
                                            <th>Status</th>
                                            <th>Comments / Notes</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($history_requests as $r): ?>
                                            <tr>
                                                <td><strong><?php echo e($r['employee_name']); ?></strong></td>
                                                <td><?php echo e($r['leave_type']); ?></td>
                                                <td>
                                                    <?php 
                                                    $start = new DateTime($r['start_date']);
                                                    $end = new DateTime($r['end_date']);
                                                    echo $start->format('M d, Y') . ' to ' . $end->format('M d, Y');
                                                    ?>
                                                </td>
                                                <td><?php echo e($r['reason']); ?></td>
                                                <td>
                                                    <?php 
                                                    $color = 'var(--text-muted)';
                                                    if ($r['status'] === 'Approved') $color = 'var(--success)';
                                                    if ($r['status'] === 'Rejected') $color = 'var(--danger)';
                                                    if ($r['status'] === 'Pending Admin Approval') $color = '#3b82f6';
                                                    ?>
                                                    <span class="badge" style="background: <?php echo $color; ?>;"><?php echo e($r['status']); ?></span>
                                                </td>
                                                <td><span style="font-size:0.85rem; color:var(--text-muted)"><?php echo nl2br(e($r['admin_comment'] ?? '—')); ?></span></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            <?php endif; ?>
        </div>
    </div>

    <!-- Approve Modal -->
    <div class="lms-modal-overlay" id="approveOverlay">
        <div class="lms-modal">
            <div class="modal-icon success"><i class="fas fa-check-circle"></i></div>
            <h2>Approve Request</h2>
            <p>You are endorsing this leave request for administrative approval.</p>
            <div class="modal-detail">
                <div><span>Employee:</span><strong id="ap-name"></strong></div>
                <div><span>Leave Type:</span><strong id="ap-type"></strong></div>
                <div><span>Dates:</span><strong id="ap-dates"></strong></div>
            </div>
            <form action="approve_deny.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                <input type="hidden" name="redirect" value="manager_dashboard.php">
                <input type="hidden" name="id" id="ap-id">
                <input type="hidden" name="action" value="Approved">
                <div class="form-group" style="text-align: left; margin: 15px 0 0 0;">
                    <label style="font-size:0.8rem; font-weight:600;">Add Note / Remark (Optional)</label>
                    <input type="text" name="admin_comment" placeholder="e.g. Recommended for approval" style="padding:10px; border-radius:8px;">
                </div>
                <div class="modal-btn-row">
                    <button type="button" class="btn-cancel-modal" onclick="closeModals()">Cancel</button>
                    <button type="submit" class="btn-confirm-approve">Confirm Approval</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Reject Modal -->
    <div class="lms-modal-overlay" id="rejectOverlay">
        <div class="lms-modal">
            <div class="modal-icon danger"><i class="fas fa-times-circle"></i></div>
            <h2>Reject Request</h2>
            <p>Please provide a reason why this request is being rejected.</p>
            <div class="modal-detail">
                <div><span>Employee:</span><strong id="rj-name"></strong></div>
                <div><span>Leave Type:</span><strong id="rj-type"></strong></div>
                <div><span>Dates:</span><strong id="rj-dates"></strong></div>
            </div>
            <form action="approve_deny.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                <input type="hidden" name="redirect" value="manager_dashboard.php">
                <input type="hidden" name="id" id="rj-id">
                <input type="hidden" name="action" value="Rejected">
                <div class="form-group" style="text-align: left; margin: 15px 0 0 0;">
                    <label style="font-size:0.8rem; font-weight:600;">Rejection Reason <span style="color:var(--danger)">*</span></label>
                    <input type="text" name="admin_comment" required placeholder="e.g. Peak project season" style="padding:10px; border-radius:8px;">
                </div>
                <div class="modal-btn-row">
                    <button type="button" class="btn-cancel-modal" onclick="closeModals()">Cancel</button>
                    <button type="submit" class="btn-confirm-reject">Confirm Rejection</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    function triggerApproval(id, name, type, dates) {
        document.getElementById('ap-id').value = id;
        document.getElementById('ap-name').textContent = name;
        document.getElementById('ap-type').textContent = type;
        document.getElementById('ap-dates').textContent = dates;
        document.getElementById('approveOverlay').classList.add('show');
    }
    function triggerRejection(id, name, type, dates) {
        document.getElementById('rj-id').value = id;
        document.getElementById('rj-name').textContent = name;
        document.getElementById('rj-type').textContent = type;
        document.getElementById('rj-dates').textContent = dates;
        document.getElementById('rejectOverlay').classList.add('show');
    }
    function closeModals() {
        document.getElementById('approveOverlay').classList.remove('show');
        document.getElementById('rejectOverlay').classList.remove('show');
    }
    </script>
</body>
</html>
