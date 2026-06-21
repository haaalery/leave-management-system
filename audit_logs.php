<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/auth_check.php';
checkLogin();
checkAdmin();

// Filters
$filter_action = trim($_GET['action'] ?? '');
$filter_actor  = trim($_GET['actor']  ?? '');
$filter_date   = trim($_GET['date']   ?? '');
$filter_search = trim($_GET['search'] ?? '');

$where  = [];
$params = [];

if ($filter_action) {
    $where[]  = "action LIKE ?";
    $params[] = '%' . $filter_action . '%';
}
if ($filter_actor) {
    $where[]  = "actor_name LIKE ?";
    $params[] = '%' . $filter_actor . '%';
}
if ($filter_date) {
    $where[]  = "DATE(created_at) = ?";
    $params[] = $filter_date;
}
if ($filter_search) {
    $where[]  = "(action LIKE ? OR actor_name LIKE ? OR details LIKE ?)";
    $params[] = '%' . $filter_search . '%';
    $params[] = '%' . $filter_search . '%';
    $params[] = '%' . $filter_search . '%';
}

$sql = "SELECT * FROM audit_logs";
if ($where) $sql .= " WHERE " . implode(' AND ', $where);
$sql .= " ORDER BY created_at DESC LIMIT 200";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Summary counts
$total_stmt = $pdo->query("SELECT action, COUNT(*) as cnt FROM audit_logs GROUP BY action");
$action_counts = [];
foreach ($total_stmt->fetchAll() as $row) {
    $action_counts[$row['action']] = $row['cnt'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit Logs - LMS</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .audit-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 0.78rem;
            font-weight: 700;
            letter-spacing: 0.3px;
        }
        .audit-badge.approved  { background: rgba(16,185,129,0.12); color: #059669; }
        .audit-badge.rejected  { background: rgba(239,68,68,0.12);  color: #dc2626; }
        .audit-badge.reg-approved { background: rgba(79,70,229,0.12); color: #4338ca; }
        .audit-badge.reg-rejected { background: rgba(239,68,68,0.12); color: #dc2626; }
        .audit-badge.other     { background: rgba(100,116,139,0.1);  color: #475569; }

        .filter-bar {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            align-items: flex-end;
            margin-bottom: 20px;
            padding: 16px 18px;
            background: #f8fafc;
            border: 1px solid var(--border);
            border-radius: 12px;
        }
        .filter-bar .form-group {
            flex: 1;
            min-width: 160px;
            margin: 0;
        }
        .filter-bar label {
            font-size: 0.82rem;
            font-weight: 600;
            color: var(--text-muted);
            margin-bottom: 4px;
            display: block;
        }
        .filter-bar input, .filter-bar select {
            padding: 8px 12px;
            font-size: 0.88rem;
        }
        .details-cell {
            font-size: 0.82rem;
            color: var(--text-muted);
            max-width: 260px;
            word-break: break-word;
        }
        .log-time {
            font-size: 0.82rem;
            color: var(--text-muted);
            white-space: nowrap;
        }
        .empty-state {
            text-align: center;
            padding: 50px 20px;
            color: var(--text-muted);
        }
        .empty-state i { font-size: 3rem; margin-bottom: 12px; opacity: 0.3; display: block; }
    </style>
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="top-nav">
            <h2><i class="fas fa-history"></i> Audit Logs</h2>
            <div class="user-info">
                <span>Logged in as: <strong><?php echo e($_SESSION['name']); ?></strong></span>
            </div>
        </div>

        <div class="content-wrapper">

            <!-- Summary Cards -->
            <div class="stats-container" style="margin-bottom: 28px;">
                <?php
                $summary = [
                    ['label' => 'Leave Approved',        'key' => 'Leave Approved',            'icon' => 'fa-check-circle',    'color' => '#10b981', 'bg' => 'rgba(16,185,129,0.08)'],
                    ['label' => 'Leave Rejected',         'key' => 'Leave Rejected',             'icon' => 'fa-times-circle',    'color' => '#ef4444', 'bg' => 'rgba(239,68,68,0.08)'],
                    ['label' => 'Registrations Approved', 'key' => 'Registration Approved',      'icon' => 'fa-user-check',      'color' => '#4f46e5', 'bg' => 'rgba(79,70,229,0.08)'],
                    ['label' => 'Registrations Rejected', 'key' => 'Registration Rejected',      'icon' => 'fa-user-times',      'color' => '#f59e0b', 'bg' => 'rgba(245,158,11,0.08)'],
                ];
                foreach ($summary as $s):
                    $cnt = $action_counts[$s['key']] ?? 0;
                ?>
                <div class="stat-card">
                    <div class="stat-info">
                        <strong><?php echo $s['label']; ?></strong>
                        <div class="value"><?php echo $cnt; ?></div>
                    </div>
                    <div class="stat-icon" style="background: <?php echo $s['bg']; ?>; color: <?php echo $s['color']; ?>;">
                        <i class="fas <?php echo $s['icon']; ?>"></i>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="card">
                <div class="card-header">
                    <div>
                        <h3><i class="fas fa-list-alt"></i> Activity Log</h3>
                        <p style="font-size: 0.85rem; color: var(--text-muted); margin-top: 4px;">
                            All admin actions are recorded here. Showing last 200 entries.
                        </p>
                    </div>
                </div>
                <div class="card-body">

                    <!-- Filters -->
                    <form method="GET" class="filter-bar">
                        <div class="form-group">
                            <label><i class="fas fa-search"></i> General Search</label>
                            <input type="text" name="search" placeholder="Search keywords..." value="<?php echo e($filter_search); ?>">
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-tag"></i> Action Type</label>
                            <select name="action">
                                <option value="">All Actions</option>
                                <option value="Leave Approved"       <?php echo $filter_action === 'Leave Approved'       ? 'selected' : ''; ?>>Leave Approved</option>
                                <option value="Leave Rejected"        <?php echo $filter_action === 'Leave Rejected'        ? 'selected' : ''; ?>>Leave Rejected</option>
                                <option value="Registration Approved" <?php echo $filter_action === 'Registration Approved' ? 'selected' : ''; ?>>Registration Approved</option>
                                <option value="Registration Rejected" <?php echo $filter_action === 'Registration Rejected' ? 'selected' : ''; ?>>Registration Rejected</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-user-shield"></i> Admin Name</label>
                            <input type="text" name="actor" placeholder="Search admin..." value="<?php echo e($filter_actor); ?>">
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-calendar"></i> Date</label>
                            <input type="date" name="date" value="<?php echo e($filter_date); ?>">
                        </div>
                        <div style="display: flex; gap: 8px; align-items: flex-end;">
                            <button type="submit" style="width: auto; padding: 9px 18px; font-size: 0.88rem;">
                                <i class="fas fa-filter"></i> Filter
                            </button>
                            <a href="audit_logs.php" style="
                                display: inline-flex; align-items: center; gap: 6px;
                                padding: 9px 14px; border-radius: 8px;
                                background: #f1f5f9; color: var(--text); font-size: 0.88rem;
                                font-weight: 600; text-decoration: none;
                            "><i class="fas fa-times"></i> Clear</a>
                        </div>
                    </form>

                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th style="width: 40px;">#</th>
                                    <th>Action</th>
                                    <th>Performed By</th>
                                    <th>Details</th>
                                    <th>Timestamp</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($logs)): ?>
                                    <tr>
                                        <td colspan="5">
                                            <div class="empty-state">
                                                <i class="fas fa-clipboard-list"></i>
                                                <strong>No audit logs found</strong>
                                                <p style="margin-top: 6px; font-size: 0.88rem;">Actions will be recorded here as admins use the system.</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($logs as $i => $log):
                                        // Pick badge class
                                        $badgeClass = 'other';
                                        $icon = 'fa-cog';
                                        if ($log['action'] === 'Leave Approved')       { $badgeClass = 'approved';     $icon = 'fa-check'; }
                                        if ($log['action'] === 'Leave Rejected')        { $badgeClass = 'rejected';     $icon = 'fa-times'; }
                                        if ($log['action'] === 'Registration Approved') { $badgeClass = 'reg-approved'; $icon = 'fa-user-check'; }
                                        if ($log['action'] === 'Registration Rejected') { $badgeClass = 'reg-rejected'; $icon = 'fa-user-times'; }

                                        // Parse details JSON
                                        $details = json_decode($log['details'], true) ?? [];
                                    ?>
                                    <tr>
                                        <td style="color: var(--text-muted); font-size: 0.82rem;"><?php echo $i + 1; ?></td>
                                        <td>
                                            <span class="audit-badge <?php echo $badgeClass; ?>">
                                                <i class="fas <?php echo $icon; ?>"></i>
                                                <?php echo e($log['action']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div style="font-weight: 600; font-size: 0.9rem;"><?php echo e($log['actor_name']); ?></div>
                                            <div style="font-size: 0.78rem; color: var(--text-muted);">ID #<?php echo $log['actor_id']; ?></div>
                                        </td>
                                        <td class="details-cell">
                                            <?php if (!empty($details['leave_type'])): ?>
                                                <div><strong><?php echo e($details['leave_type']); ?></strong></div>
                                                <div><?php echo e($details['start_date'] ?? ''); ?> → <?php echo e($details['end_date'] ?? ''); ?></div>
                                                <?php if (!empty($details['comment'])): ?>
                                                <div style="margin-top: 3px;"><em>"<?php echo e($details['comment']); ?>"</em></div>
                                                <?php endif; ?>
                                            <?php elseif (!empty($details['user_name'])): ?>
                                                <div><strong><?php echo e($details['user_name']); ?></strong></div>
                                                <div><?php echo e($details['email'] ?? ''); ?></div>
                                            <?php else: ?>
                                                <span style="font-style: italic;">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="log-time">
                                            <div><?php echo date('M d, Y', strtotime($log['created_at'])); ?></div>
                                            <div><?php echo date('h:i A', strtotime($log['created_at'])); ?></div>
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
