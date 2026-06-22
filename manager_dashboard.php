<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/auth_check.php';
checkLogin();
checkManagerOrAdmin();

$manager_id = $_SESSION['user_id'];

// Leave type icons configuration
$leave_icons = [
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
$dept_employees   = [];

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

    // 5. Fetch all active employees in department and check their current leave status
    $stmt = $pdo->prepare("
        SELECT u.id, u.name, u.email,
               (SELECT lr.leave_type 
                FROM leave_requests lr 
                WHERE lr.user_id = u.id 
                  AND lr.status = 'Approved' 
                  AND CURDATE() BETWEEN lr.start_date AND lr.end_date
                LIMIT 1) as current_leave_type,
               (SELECT lr.end_date 
                FROM leave_requests lr 
                WHERE lr.user_id = u.id 
                  AND lr.status = 'Approved' 
                  AND CURDATE() BETWEEN lr.start_date AND lr.end_date
                LIMIT 1) as leave_end_date
        FROM users u
        WHERE u.department_id = ? AND u.role = 'Employee' AND u.status = 'Active'
        ORDER BY u.name ASC
    ");
    $stmt->execute([$dept_id]);
    $dept_employees = $stmt->fetchAll();
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
        /* Modals & Details styling */
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
        .leave-type-badge {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 6px 12px; border-radius: 8px; font-size: 0.85rem; font-weight: 600;
        }
        .leave-type-badge i { font-size: 0.9rem; }

        /* Two-column Dashboard Grid */
        .dashboard-grid {
            display: grid;
            grid-template-columns: 1.8fr 1.2fr;
            gap: 30px;
            margin-top: 24px;
            align-items: start;
        }
        @media (max-width: 1200px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
        }
        
        /* Widget Styling */
        .widget-card {
            background: var(--card-bg);
            border-radius: var(--radius-lg);
            border: 1px solid var(--border);
            box-shadow: var(--shadow-sm);
            margin-bottom: 30px;
            overflow: hidden;
        }
        .widget-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #fafbfc;
        }
        .widget-header h3 {
            font-size: 1.05rem;
            font-weight: 700;
            color: var(--text-main);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .widget-header h3 i {
            color: var(--primary);
        }
        .widget-body {
            padding: 24px;
        }
        
        /* Dept Stats List */
        .dept-stat-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px;
            border-radius: var(--radius-md);
            background: #f8fafc;
            border: 1px solid var(--border);
            margin-bottom: 12px;
            transition: var(--transition);
        }
        .dept-stat-item:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-sm);
            border-color: rgba(99, 102, 241, 0.2);
        }
        .dept-stat-info {
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .dept-stat-icon {
            width: 44px;
            height: 44px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }
        .dept-stat-label h4 {
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--text-main);
        }
        .dept-stat-label p {
            font-size: 0.8rem;
            color: var(--text-muted);
            margin: 0;
        }
        .dept-stat-val {
            font-size: 1.4rem;
            font-weight: 800;
            color: var(--text-main);
        }
        .dept-stat-val.highlight {
            color: var(--primary);
        }
        
        /* Staff Status List */
        .staff-status-list {
            display: flex;
            flex-direction: column;
            gap: 14px;
        }
        .staff-status-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 16px;
            border-radius: var(--radius-md);
            border: 1px solid var(--border);
            background: #ffffff;
            transition: var(--transition);
        }
        .staff-status-item:hover {
            border-color: var(--border);
            background: #fafbfc;
        }
        .staff-member {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .staff-avatar {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: var(--primary-light);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.9rem;
            border: 1px solid rgba(99, 102, 241, 0.15);
        }
        .staff-details h4 {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text-main);
            margin: 0 0 2px 0;
        }
        .staff-details p {
            font-size: 0.78rem;
            color: var(--text-muted);
            margin: 0;
        }
        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .status-pill.active {
            background: rgba(16, 185, 129, 0.08);
            color: #10b981;
        }
        .status-pill.on-leave {
            background: rgba(99, 102, 241, 0.08);
            color: var(--primary);
        }
        .status-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            display: inline-block;
        }
        .status-pill.active .status-dot {
            background: #10b981;
        }
        .status-pill.on-leave .status-dot {
            background: var(--primary);
        }
    </style>
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="top-nav">
            <h2><i class="fas fa-user-tie"></i> Manager Dashboard</h2>
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

                <div class="dashboard-grid">
                    
                    <!-- Left Column: Work Area -->
                    <div class="work-area">
                        
                        <!-- Pending Leaves -->
                        <div class="card" style="margin-bottom: 30px;">
                            <div class="card-header">
                                <h3><i class="fas fa-clock" style="color: var(--primary);"></i> Pending Department Leave Requests</h3>
                            </div>
                            <div class="card-body" style="padding: 20px 24px;">
                                <?php if (empty($pending_requests)): ?>
                                    <p style="text-align: center; color: var(--text-muted); padding: 40px 0;">No pending requests from your department.</p>
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
                                                    <th style="text-align: right;">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($pending_requests as $r): ?>
                                                    <tr>
                                                        <td><strong><?php echo e($r['employee_name']); ?></strong></td>
                                                        <td>
                                                            <?php 
                                                            $meta = $leave_icons[$r['leave_type']] ?? ['icon' => 'fa-calendar', 'color' => '#64748b', 'bg' => 'rgba(100, 116, 139, 0.08)'];
                                                            ?>
                                                            <span class="leave-type-badge" style="background: <?php echo $meta['bg']; ?>; color: <?php echo $meta['color']; ?>;">
                                                                <i class="fas <?php echo $meta['icon']; ?>"></i>
                                                                <?php echo e($r['leave_type']); ?>
                                                            </span>
                                                        </td>
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
                                                        <td class="action-buttons" style="text-align: right;">
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
                        <div class="card">
                            <div class="card-header">
                                <h3><i class="fas fa-history" style="color: var(--text-muted);"></i> Recent Department Leave History</h3>
                            </div>
                            <div class="card-body" style="padding: 20px 24px;">
                                <?php if (empty($history_requests)): ?>
                                    <p style="text-align: center; color: var(--text-muted); padding: 40px 0;">No history records found.</p>
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
                                                        <td>
                                                            <?php 
                                                            $meta = $leave_icons[$r['leave_type']] ?? ['icon' => 'fa-calendar', 'color' => '#64748b', 'bg' => 'rgba(100, 116, 139, 0.08)'];
                                                            ?>
                                                            <span class="leave-type-badge" style="background: <?php echo $meta['bg']; ?>; color: <?php echo $meta['color']; ?>;">
                                                                <i class="fas <?php echo $meta['icon']; ?>"></i>
                                                                <?php echo e($r['leave_type']); ?>
                                                            </span>
                                                        </td>
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
                                                            <span class="badge" style="background: <?php echo $color; ?>; color:#ffffff;"><?php echo e($r['status']); ?></span>
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

                    </div>

                    <!-- Right Column: Sidebar Widgets -->
                    <div class="sidebar-widgets">
                        
                        <!-- Department Summary -->
                        <div class="widget-card">
                            <div class="widget-header">
                                <h3><i class="fas fa-building"></i> Department Summary</h3>
                            </div>
                            <div class="widget-body">
                                <div class="dept-stat-item">
                                    <div class="dept-stat-info">
                                        <div class="dept-stat-icon" style="background: rgba(99, 102, 241, 0.1); color: var(--primary);">
                                            <i class="fas fa-users"></i>
                                        </div>
                                        <div class="dept-stat-label">
                                            <h4>Active Staff</h4>
                                            <p>Members in department</p>
                                        </div>
                                    </div>
                                    <div class="dept-stat-val"><?php echo $employee_count; ?></div>
                                </div>

                                <div class="dept-stat-item">
                                    <div class="dept-stat-info">
                                        <div class="dept-stat-icon" style="background: rgba(245, 158, 11, 0.1); color: #f59e0b;">
                                            <i class="fas fa-clock"></i>
                                        </div>
                                        <div class="dept-stat-label">
                                            <h4>Pending Approvals</h4>
                                            <p>Awaiting endorsement</p>
                                        </div>
                                    </div>
                                    <div class="dept-stat-val <?php echo count($pending_requests) > 0 ? 'highlight' : ''; ?>"><?php echo count($pending_requests); ?></div>
                                </div>

                                <div class="dept-stat-item">
                                    <div class="dept-stat-info">
                                        <div class="dept-stat-icon" style="background: rgba(16, 185, 129, 0.1); color: #10b981;">
                                            <i class="fas fa-plane-departure"></i>
                                        </div>
                                        <div class="dept-stat-label">
                                            <h4>On Leave Today</h4>
                                            <p>Out of office</p>
                                        </div>
                                    </div>
                                    <div class="dept-stat-val"><?php echo $active_today; ?></div>
                                </div>
                            </div>
                        </div>

                        <!-- Staff Status Today -->
                        <div class="widget-card">
                            <div class="widget-header">
                                <h3><i class="fas fa-user-check"></i> Staff Status Today</h3>
                            </div>
                            <div class="widget-body">
                                <?php if (empty($dept_employees)): ?>
                                    <p style="text-align: center; color: var(--text-muted); font-size: 0.9rem;">No employees in your department.</p>
                                <?php else: ?>
                                    <div class="staff-status-list">
                                        <?php foreach ($dept_employees as $emp): ?>
                                            <?php 
                                            // Generate initials for avatar
                                            $names = explode(' ', $emp['name']);
                                            $initials = '';
                                            foreach ($names as $n) {
                                                if (!empty($n)) $initials .= strtoupper($n[0]);
                                            }
                                            $initials = substr($initials, 0, 2);
                                            ?>
                                            <div class="staff-status-item">
                                                <div class="staff-member">
                                                    <div class="staff-avatar">
                                                        <?php echo $initials; ?>
                                                    </div>
                                                    <div class="staff-details">
                                                        <h4><?php echo e($emp['name']); ?></h4>
                                                        <p><?php echo e($emp['email']); ?></p>
                                                    </div>
                                                </div>
                                                <div>
                                                    <?php if ($emp['current_leave_type']): ?>
                                                        <span class="status-pill on-leave" title="Until <?php echo date('M d', strtotime($emp['leave_end_date'])); ?>">
                                                            <span class="status-dot"></span> On Leave
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="status-pill active">
                                                            <span class="status-dot"></span> Active
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

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
