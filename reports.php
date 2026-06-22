<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/auth_check.php';
checkLogin();
checkManagerOrAdmin();

$role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];

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

// Get manager department details if applicable
$mgr_dept_id = 0;
if ($role === 'Manager') {
    $stmt = $pdo->prepare("SELECT id, name FROM departments WHERE manager_id = ?");
    $stmt->execute([$user_id]);
    $mgr_dept = $stmt->fetch();
    $mgr_dept_id = $mgr_dept ? (int)$mgr_dept['id'] : -1; // -1 means they manage no department
}

// ── 1. Gather Filter Data ───────────────────────────────────────────────────
// A. Departments
if ($role === 'Admin') {
    $departments_list = $pdo->query("SELECT id, name FROM departments ORDER BY name ASC")->fetchAll();
} else {
    $departments_list = $mgr_dept ? [$mgr_dept] : [];
}

// B. Employees
if ($role === 'Admin') {
    $employees_list = $pdo->query("SELECT id, name FROM users WHERE role = 'Employee' AND status = 'Active' ORDER BY name ASC")->fetchAll();
} else {
    if ($mgr_dept_id > 0) {
        $stmt = $pdo->prepare("SELECT id, name FROM users WHERE department_id = ? AND role = 'Employee' AND status = 'Active' ORDER BY name ASC");
        $stmt->execute([$mgr_dept_id]);
        $employees_list = $stmt->fetchAll();
    } else {
        $employees_list = [];
    }
}

// ── 2. Handle Filters & Build Query ──────────────────────────────────────────
$f_dept  = isset($_GET['dept_id']) ? (int)$_GET['dept_id'] : 0;
$f_emp   = isset($_GET['employee_id']) ? (int)$_GET['employee_id'] : 0;
$f_type  = isset($_GET['leave_type']) ? trim($_GET['leave_type']) : '';
$f_status= isset($_GET['status']) ? trim($_GET['status']) : '';
$f_start = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
$f_end   = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';

// Enforcement for Managers
if ($role === 'Manager') {
    $f_dept = $mgr_dept_id;
}

$where = ["1=1"];
$params = [];

if ($f_dept > 0) {
    $where[] = "u.department_id = ?";
    $params[] = $f_dept;
} elseif ($f_dept === -1) {
    $where[] = "1=0"; // Return nothing if they are a manager with no department
}

if ($f_emp > 0) {
    $where[] = "lr.user_id = ?";
    $params[] = $f_emp;
}

if (!empty($f_type)) {
    $where[] = "lr.leave_type = ?";
    $params[] = $f_type;
}

if (!empty($f_status)) {
    $where[] = "lr.status = ?";
    $params[] = $f_status;
}

if (!empty($f_start)) {
    $where[] = "lr.start_date >= ?";
    $params[] = $f_start;
}

if (!empty($f_end)) {
    $where[] = "lr.end_date <= ?";
    $params[] = $f_end;
}

$where_clause = implode(" AND ", $where);

// Fetch records
$query_string = "
    SELECT lr.*, u.name AS employee_name, d.name AS dept_name
    FROM leave_requests lr
    JOIN users u ON lr.user_id = u.id
    LEFT JOIN departments d ON u.department_id = d.id
    WHERE $where_clause
    ORDER BY lr.created_at DESC
";

$stmt = $pdo->prepare($query_string);
$stmt->execute($params);
$records = $stmt->fetchAll();

// ── 3. Handle CSV Export Action ──────────────────────────────────────────────
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=leave_report_' . date('Ymd_His') . '.csv');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Employee Name', 'Department', 'Leave Type', 'Start Date', 'End Date', 'Days', 'Reason', 'Status', 'Applied At']);
    
    foreach ($records as $r) {
        $start = new DateTime($r['start_date']);
        $end = new DateTime($r['end_date']);
        $days = $start->diff($end)->days + 1;
        
        fputcsv($output, [
            $r['id'],
            $r['employee_name'],
            $r['dept_name'] ?: 'None',
            $r['leave_type'],
            $r['start_date'],
            $r['end_date'],
            $days,
            $r['reason'],
            $r['status'],
            $r['created_at']
        ]);
    }
    fclose($output);
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave Reports - LMS</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .filter-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
            align-items: end;
        }
        .filter-row .form-group { margin: 0; }
        .filter-row select, .filter-row input {
            padding: 10px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 0.9rem;
            width: 100%;
            background: #fff;
        }
        .btn-group {
            display: flex;
            gap: 10px;
            margin-bottom: 24px;
        }
        .btn-group button, .btn-group a {
            width: auto;
            padding: 10px 20px;
            font-size: 0.9rem;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border-radius: 8px;
        }
        
        /* Printing Styles */
        @media print {
            body { background: #fff; color: #000; }
            .sidebar, .top-nav, .btn-group, .card-header, form, footer { display: none !important; }
            .main-content { margin-left: 0 !important; padding: 0 !important; width: 100% !important; }
            .card { border: none !important; box-shadow: none !important; padding: 0 !important; }
            .card-body { padding: 0 !important; }
            .table-responsive { overflow: visible !important; }
            table { width: 100% !important; border-collapse: collapse !important; }
            th, td { border: 1px solid #ddd !important; padding: 8px !important; font-size: 0.85rem !important; }
            .print-header { display: block !important; margin-bottom: 30px; text-align: center; }
            .print-header h1 { font-size: 1.6rem; font-weight: 800; margin-bottom: 5px; }
            .badge { background: none !important; color: #000 !important; border: 1px solid #999; padding: 2px 6px !important; border-radius: 4px; }
        }
        .print-header { display: none; }
        .leave-type-badge {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 6px 12px; border-radius: 8px; font-size: 0.85rem; font-weight: 600;
        }
        .leave-type-badge i { font-size: 0.9rem; }
    </style>
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <!-- Print Header -->
        <div class="print-header">
            <h1>Leave Management System</h1>
            <h3>Leave Request Summary Report</h3>
            <p>Generated on: <?php echo date('F d, Y h:i A'); ?></p>
        </div>

        <div class="top-nav">
            <h2><i class="fas fa-file-invoice"></i> Leave Reports & Analytics</h2>
            <div class="user-info">
                <span>Logged in as: <strong><?php echo e($_SESSION['name']); ?></strong></span>
            </div>
        </div>

        <div class="content-wrapper">
            <!-- Filter Card -->
            <div class="card" style="margin-bottom: 24px;">
                <div class="card-header">
                    <h3><i class="fas fa-filter"></i> Filter Parameters</h3>
                </div>
                <div class="card-body">
                    <form method="GET">
                        <div class="filter-row">
                            <?php if ($role === 'Admin'): ?>
                                <div class="form-group">
                                    <label>Department</label>
                                    <select name="dept_id">
                                        <option value="0">All Departments</option>
                                        <?php foreach ($departments_list as $d): ?>
                                            <option value="<?php echo $d['id']; ?>" <?php echo $f_dept == $d['id'] ? 'selected' : ''; ?>><?php echo e($d['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php endif; ?>

                            <div class="form-group">
                                <label>Employee</label>
                                <select name="employee_id">
                                    <option value="0">All Employees</option>
                                    <?php foreach ($employees_list as $e): ?>
                                        <option value="<?php echo $e['id']; ?>" <?php echo $f_emp == $e['id'] ? 'selected' : ''; ?>><?php echo e($e['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Leave Type</label>
                                <select name="leave_type">
                                    <option value="">All Types</option>
                                    <option value="Vacation Leave" <?php echo $f_type === 'Vacation Leave' ? 'selected' : ''; ?>>Vacation Leave</option>
                                    <option value="Sick Leave" <?php echo $f_type === 'Sick Leave' ? 'selected' : ''; ?>>Sick Leave</option>
                                    <option value="Emergency Leave" <?php echo $f_type === 'Emergency Leave' ? 'selected' : ''; ?>>Emergency Leave</option>
                                    <option value="Maternity Leave" <?php echo $f_type === 'Maternity Leave' ? 'selected' : ''; ?>>Maternity Leave</option>
                                    <option value="Paternity Leave" <?php echo $f_type === 'Paternity Leave' ? 'selected' : ''; ?>>Paternity Leave</option>
                                    <option value="Bereavement Leave" <?php echo $f_type === 'Bereavement Leave' ? 'selected' : ''; ?>>Bereavement Leave</option>
                                    <option value="Study Leave" <?php echo $f_type === 'Study Leave' ? 'selected' : ''; ?>>Study Leave</option>
                                    <option value="Compensatory Leave" <?php echo $f_type === 'Compensatory Leave' ? 'selected' : ''; ?>>Compensatory Leave</option>
                                    <option value="Unpaid Leave" <?php echo $f_type === 'Unpaid Leave' ? 'selected' : ''; ?>>Unpaid Leave</option>
                                    <option value="Special Leave" <?php echo $f_type === 'Special Leave' ? 'selected' : ''; ?>>Special Leave</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Status</label>
                                <select name="status">
                                    <option value="">All Statuses</option>
                                    <option value="Pending Manager Approval" <?php echo $f_status === 'Pending Manager Approval' ? 'selected' : ''; ?>>Pending Manager Approval</option>
                                    <option value="Pending Admin Approval" <?php echo $f_status === 'Pending Admin Approval' ? 'selected' : ''; ?>>Pending Admin Approval</option>
                                    <option value="Approved" <?php echo $f_status === 'Approved' ? 'selected' : ''; ?>>Approved</option>
                                    <option value="Rejected" <?php echo $f_status === 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>From Date</label>
                                <input type="date" name="start_date" value="<?php echo e($f_start); ?>">
                            </div>

                            <div class="form-group">
                                <label>To Date</label>
                                <input type="date" name="end_date" value="<?php echo e($f_end); ?>">
                            </div>
                        </div>

                        <div style="margin-top: 15px; display: flex; gap: 10px;">
                            <button type="submit" style="width: auto; padding: 10px 24px;"><i class="fas fa-search"></i> Generate Report</button>
                            <a href="reports.php" style="background:#f1f5f9; color:var(--text); border:1px solid var(--border); border-radius:8px; display:inline-flex; align-items:center; padding:10px 20px; font-weight:600; text-decoration:none;"><i class="fas fa-undo"></i> Reset</a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="btn-group">
                <a href="?<?php echo http_build_query(array_merge($_GET, ['export' => 'csv'])); ?>" style="background: #10b981; color: #fff;"><i class="fas fa-file-excel"></i> Export CSV</a>
                <button onclick="window.print()" style="background: #3b82f6; color: #fff; border:none; cursor:pointer;"><i class="fas fa-print"></i> Print Report / Save PDF</button>
            </div>

            <!-- Results Card -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-table"></i> Report Results (<?php echo count($records); ?> records)</h3>
                </div>
                <div class="card-body">
                    <?php if (empty($records)): ?>
                        <p style="text-align: center; color: var(--text-muted); padding: 30px 0;">No matching records found for the selected filters.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table>
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Employee</th>
                                        <th>Department</th>
                                        <th>Leave Type</th>
                                        <th>Start Date</th>
                                        <th>End Date</th>
                                        <th>Days</th>
                                        <th>Status</th>
                                        <th>Applied At</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($records as $r): ?>
                                        <tr>
                                            <td><?php echo $r['id']; ?></td>
                                            <td><strong><?php echo e($r['employee_name']); ?></strong></td>
                                            <td><?php echo e($r['dept_name'] ?: 'None'); ?></td>
                                            <td>
                                                <?php 
                                                $meta = $leave_icons[$r['leave_type']] ?? ['icon' => 'fa-calendar', 'color' => '#64748b', 'bg' => 'rgba(100, 116, 139, 0.08)'];
                                                ?>
                                                <span class="leave-type-badge" style="background: <?php echo $meta['bg']; ?>; color: <?php echo $meta['color']; ?>;">
                                                    <i class="fas <?php echo $meta['icon']; ?>"></i>
                                                    <?php echo e($r['leave_type']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($r['start_date'])); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($r['end_date'])); ?></td>
                                            <td>
                                                <?php 
                                                $start = new DateTime($r['start_date']);
                                                $end = new DateTime($r['end_date']);
                                                echo $start->diff($end)->days + 1;
                                                ?>
                                            </td>
                                            <td>
                                                <?php 
                                                $color = 'var(--text-muted)';
                                                if ($r['status'] === 'Approved') $color = 'var(--success)';
                                                if ($r['status'] === 'Rejected') $color = 'var(--danger)';
                                                if ($r['status'] === 'Pending Manager Approval') $color = '#f59e0b';
                                                if ($r['status'] === 'Pending Admin Approval') $color = '#3b82f6';
                                                ?>
                                                <span class="badge" style="background: <?php echo $color; ?>;"><?php echo e($r['status']); ?></span>
                                            </td>
                                            <td><?php echo date('M d, Y h:i A', strtotime($r['created_at'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
