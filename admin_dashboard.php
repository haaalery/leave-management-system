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

// 4. Dynamic leave type requests total
$stmt = $pdo->query("SELECT leave_type, COUNT(*) as cnt FROM leave_requests GROUP BY leave_type");
$type_counts = $stmt->fetchAll();
$leave_counts = [];
foreach ($type_counts as $tc) {
    $leave_counts[$tc['leave_type']] = $tc['cnt'];
}

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
        .modal-icon.warning { background: linear-gradient(135deg,#f59e0b,#d97706); box-shadow: 0 0 0 12px rgba(245,158,11,0.12); }
        .lms-modal h2 { font-size:1.4rem; font-weight:700; margin-bottom:8px; color:var(--text); }
        .lms-modal p  { color:var(--text-muted); font-size:0.92rem; margin-bottom:0; line-height:1.6; }
        .lms-modal .modal-detail {
            background:#f8fafc; border:1px solid var(--border); border-radius:10px;
            padding:12px 16px; margin:18px 0; font-size:0.87rem; text-align:left;
        }
        .lms-modal .modal-detail div { display:flex; justify-content:space-between; padding:3px 0; }
        .lms-modal .modal-detail div span:first-child { color:var(--text-muted); }
        .modal-btn-row { display:flex; gap:10px; margin-top:22px; }
        .modal-btn-row button, .modal-btn-row a {
            flex:1; padding:11px; border-radius:10px; font-size:0.9rem;
            font-weight:600; cursor:pointer; border:none; transition:opacity 0.2s; text-decoration:none;
            text-align:center; display:inline-flex; align-items:center; justify-content:center; gap:6px;
        }
        .modal-btn-row button:hover, .modal-btn-row a:hover { opacity:0.85; }
        .btn-confirm-approve { background:var(--success); color:#fff; }
        .btn-confirm-reject  { background:var(--danger);  color:#fff; }
        .btn-confirm-activate{ background:#4f46e5; color:#fff; }
        .btn-cancel-modal    { background:#f1f5f9; color:var(--text); border:1px solid var(--border) !important; }
        .btn-close-result    { background:var(--primary); color:#fff; }
    </style>
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>

    <?php
    // Read and clear flash session
    $flash_action = $_SESSION['flash_action'] ?? null;
    $flash_type   = $_SESSION['flash_type']   ?? null;
    $flash_name   = $_SESSION['flash_name']   ?? null;
    unset($_SESSION['flash_action'], $_SESSION['flash_type'], $_SESSION['flash_name']);
    ?>

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

            <div class="stats-container" style="margin-bottom: 35px; display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px;">
                <?php 
                $icons = [
                    'Vacation Leave' => ['icon' => 'fa-plane', 'color' => '#3b82f6', 'bg' => 'rgba(59, 130, 246, 0.08)'],
                    'Sick Leave' => ['icon' => 'fa-briefcase-medical', 'color' => 'var(--danger)', 'bg' => 'rgba(239, 68, 68, 0.08)'],
                    'Emergency Leave' => ['icon' => 'fa-exclamation-triangle', 'color' => '#f59e0b', 'bg' => 'rgba(245, 158, 11, 0.08)'],
                    'Maternity Leave' => ['icon' => 'fa-baby', 'color' => '#ec4899', 'bg' => 'rgba(236, 72, 153, 0.08)'],
                    'Paternity Leave' => ['icon' => 'fa-baby-carriage', 'color' => '#06b6d4', 'bg' => 'rgba(6, 182, 212, 0.08)'],
                    'Bereavement Leave' => ['icon' => 'fa-heart', 'color' => '#ef4444', 'bg' => 'rgba(239, 68, 68, 0.08)'],
                    'Study Leave' => ['icon' => 'fa-graduation-cap', 'color' => '#8b5cf6', 'bg' => 'rgba(139, 92, 246, 0.08)'],
                    'Compensatory Leave' => ['icon' => 'fa-clock', 'color' => '#10b981', 'bg' => 'rgba(16, 185, 129, 0.08)'],
                    'Unpaid Leave' => ['icon' => 'fa-user-clock', 'color' => 'var(--text-muted)', 'bg' => 'rgba(100, 116, 139, 0.08)'],
                    'Special Leave' => ['icon' => 'fa-star', 'color' => '#eab308', 'bg' => 'rgba(234, 179, 8, 0.08)']
                ];
                foreach ($icons as $type => $meta):
                    $count = $leave_counts[$type] ?? 0;
                ?>
                    <div class="stat-card" style="margin-bottom: 0;">
                        <div class="stat-info">
                            <strong>Total <?php echo e($type); ?></strong>
                            <div class="value"><?php echo $count; ?></div>
                        </div>
                        <div class="stat-icon" style="background: <?php echo $meta['bg']; ?>; color: <?php echo $meta['color']; ?>;">
                            <i class="fas <?php echo $meta['icon']; ?>"></i>
                        </div>
                    </div>
                <?php endforeach; ?>
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
                                                    <input type="hidden" name="action" value="">
                                                    <button type="button" onclick="confirmRegistration(this.closest('form'),'Active','<?php echo e($pu['name']); ?>')" class="btn btn-sm" style="background: var(--success); padding: 6px 12px; font-size: 0.85rem;">
                                                        <i class="fas fa-check"></i> Activate
                                                    </button>
                                                    <button type="button" onclick="confirmRegistration(this.closest('form'),'Rejected','<?php echo e($pu['name']); ?>')" class="btn btn-sm" style="background: var(--danger); padding: 6px 12px; font-size: 0.85rem;">
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
                                                    <button type="button" onclick="confirmLeave(this,'Approved','<?php echo e($r['employee_name']); ?>','<?php echo e($r['leave_type']); ?>')" style="width: auto; background: var(--success); padding: 6px 12px; font-size: 11px;">Approve</button>
                                                    <button type="button" onclick="confirmLeave(this,'Rejected','<?php echo e($r['employee_name']); ?>','<?php echo e($r['leave_type']); ?>')" style="width: auto; background: var(--danger); padding: 6px 12px; font-size: 11px;">Reject</button>
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

    <!-- ===== CONFIRMATION MODAL ===== -->
    <div class="lms-modal-overlay" id="confirmModal">
        <div class="lms-modal">
            <div class="modal-icon" id="confirmIcon"><i id="confirmIconI" class="fas fa-question"></i></div>
            <h2 id="confirmTitle">Confirm Action</h2>
            <p id="confirmMsg">Are you sure?</p>
            <div class="modal-detail" id="confirmDetail" style="display:none;">
                <div><span>Employee</span><span id="cd-name"></span></div>
                <div><span id="cd-label">Type</span><span id="cd-value"></span></div>
            </div>
            <div class="modal-btn-row">
                <button class="btn-cancel-modal" onclick="closeConfirmModal()"><i class="fas fa-times"></i> Cancel</button>
                <button id="confirmDoBtn" class="btn-confirm-approve"><i class="fas fa-check"></i> <span id="confirmBtnLabel">Confirm</span></button>
            </div>
        </div>
    </div>

    <!-- ===== RESULT / FLASH MODAL ===== -->
    <?php if ($flash_action): ?>
    <div class="lms-modal-overlay show" id="resultModal">
        <div class="lms-modal">
            <?php if ($flash_type === 'leave'): ?>
                <?php if ($flash_action === 'Approved'): ?>
                    <div class="modal-icon success"><i class="fas fa-check"></i></div>
                    <h2>Leave Approved!</h2>
                    <p>The leave request has been <strong>approved</strong> and the employee's balance has been updated.</p>
                <?php else: ?>
                    <div class="modal-icon danger"><i class="fas fa-times"></i></div>
                    <h2>Leave Rejected</h2>
                    <p>The leave request has been <strong>rejected</strong> and the employee will be notified.</p>
                <?php endif; ?>
            <?php elseif ($flash_type === 'registration'): ?>
                <?php if ($flash_action === 'Active'): ?>
                    <div class="modal-icon success"><i class="fas fa-user-check"></i></div>
                    <h2>Account Activated!</h2>
                    <p><strong><?php echo e($flash_name); ?></strong>'s account has been approved and activated. They can now log in.</p>
                <?php else: ?>
                    <div class="modal-icon danger"><i class="fas fa-user-times"></i></div>
                    <h2>Registration Rejected</h2>
                    <p><strong><?php echo e($flash_name); ?></strong>'s registration has been rejected.</p>
                <?php endif; ?>
            <?php endif; ?>
            <div class="modal-btn-row">
                <button class="btn-close-result" onclick="document.getElementById('resultModal').classList.remove('show')">
                    <i class="fas fa-check"></i> Got it
                </button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script>
    // ── Confirmation modal for leave approve/reject ──────────────────────
    let _pendingForm = null;
    let _pendingAction = null;

    function confirmLeave(btn, action, employee, leaveType) {
        _pendingForm   = btn.closest('form');
        _pendingAction = action;

        const isApprove = action === 'Approved';
        document.getElementById('confirmIcon').className    = 'modal-icon ' + (isApprove ? 'success' : 'danger');
        document.getElementById('confirmIconI').className   = 'fas ' + (isApprove ? 'fa-check' : 'fa-times');
        document.getElementById('confirmTitle').textContent = isApprove ? 'Approve Leave Request?' : 'Reject Leave Request?';
        document.getElementById('confirmMsg').textContent   = isApprove
            ? 'This will approve the request and deduct from the employee\'s leave balance.'
            : 'This will reject the leave request. The employee will see this decision.';
        document.getElementById('cd-name').textContent    = employee;
        document.getElementById('cd-label').textContent   = 'Leave Type';
        document.getElementById('cd-value').textContent   = leaveType;
        document.getElementById('confirmDetail').style.display = 'block';

        const doBtn = document.getElementById('confirmDoBtn');
        doBtn.className = isApprove ? 'btn-confirm-approve' : 'btn-confirm-reject';
        doBtn.innerHTML = '<i class="fas ' + (isApprove ? 'fa-check' : 'fa-times') + '"></i> ' + (isApprove ? 'Yes, Approve' : 'Yes, Reject');
        doBtn.onclick = function() {
            // Set the hidden action value and submit the form
            let actionInput = _pendingForm.querySelector('input[name="action"]');
            if (!actionInput) {
                actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                _pendingForm.appendChild(actionInput);
            }
            actionInput.value = _pendingAction;
            _pendingForm.submit();
        };

        document.getElementById('confirmModal').classList.add('show');
    }

    // ── Confirmation modal for user registration activate/reject ─────────
    function confirmRegistration(formEl, action, userName) {
        _pendingForm   = formEl;
        _pendingAction = action;

        const isActivate = action === 'Active';
        document.getElementById('confirmIcon').className    = 'modal-icon ' + (isActivate ? 'success' : 'danger');
        document.getElementById('confirmIconI').className   = 'fas ' + (isActivate ? 'fa-user-check' : 'fa-user-times');
        document.getElementById('confirmTitle').textContent = isActivate ? 'Activate Account?' : 'Reject Registration?';
        document.getElementById('confirmMsg').textContent   = isActivate
            ? 'This will activate the account and allow the employee to log in.'
            : 'This will permanently reject this registration request.';
        document.getElementById('cd-name').textContent    = userName;
        document.getElementById('cd-label').textContent   = 'Action';
        document.getElementById('cd-value').textContent   = isActivate ? 'Approve & Activate' : 'Reject';
        document.getElementById('confirmDetail').style.display = 'block';

        const doBtn = document.getElementById('confirmDoBtn');
        doBtn.className = isActivate ? 'btn-confirm-activate' : 'btn-confirm-reject';
        doBtn.innerHTML = '<i class="fas ' + (isActivate ? 'fa-user-check' : 'fa-user-times') + '"></i> ' + (isActivate ? 'Yes, Activate' : 'Yes, Reject');
        doBtn.onclick = function() {
            // Set the hidden action field value
            const actionInput = _pendingForm.querySelector('input[name="action"]');
            if (actionInput) actionInput.value = _pendingAction;
            _pendingForm.submit();
        };

        document.getElementById('confirmModal').classList.add('show');
    }

    function closeConfirmModal() {
        document.getElementById('confirmModal').classList.remove('show');
        _pendingForm = null;
        _pendingAction = null;
    }

    // Close modal if clicking the dark backdrop
    document.getElementById('confirmModal').addEventListener('click', function(e) {
        if (e.target === this) closeConfirmModal();
    });
    </script>
</body>
</html>
