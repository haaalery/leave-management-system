<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/auth_check.php';
checkLogin();
checkAdmin();

$success = $error = '';
$filter_dept = (int)($_GET['dept'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($token)) die("CSRF token validation failed.");
    $action = $_POST['form_action'] ?? '';

    if ($action === 'add') {
        $dept_id = (int)($_POST['department_id'] ?? 0);
        $title   = trim($_POST['title'] ?? '');
        $jd      = trim($_POST['job_description'] ?? '');
        if (!$dept_id || !$title) {
            $error = "Department and position title are required.";
        } else {
            try {
                $pdo->prepare("INSERT INTO positions (department_id, title, job_description) VALUES (?,?,?)")
                    ->execute([$dept_id, $title, $jd]);
                $success = "Position \"$title\" created.";
            } catch (PDOException $e) {
                $error = "A position with that title already exists in this department.";
            }
        }
    } elseif ($action === 'edit') {
        $id      = (int)($_POST['id'] ?? 0);
        $dept_id = (int)($_POST['department_id'] ?? 0);
        $title   = trim($_POST['title'] ?? '');
        $jd      = trim($_POST['job_description'] ?? '');
        if ($id && $dept_id && $title) {
            $pdo->prepare("UPDATE positions SET department_id=?, title=?, job_description=? WHERE id=?")
                ->execute([$dept_id, $title, $jd, $id]);
            $success = "Position updated.";
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $cnt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE position_id=?");
        $cnt->execute([$id]);
        if ($cnt->fetchColumn() > 0) {
            $error = "Cannot delete: employees are still assigned to this position.";
        } else {
            $pdo->prepare("DELETE FROM positions WHERE id=?")->execute([$id]);
            $success = "Position deleted.";
        }
    }
}

$departments = $pdo->query("SELECT * FROM departments ORDER BY name ASC")->fetchAll();

$posQuery = "
    SELECT p.*, d.name AS dept_name,
        (SELECT COUNT(*) FROM users u WHERE u.position_id = p.id) AS employee_count
    FROM positions p
    JOIN departments d ON p.department_id = d.id
";
$posParams = [];
if ($filter_dept) {
    $posQuery  .= " WHERE p.department_id = ?";
    $posParams[] = $filter_dept;
}
$posQuery .= " ORDER BY d.name ASC, p.title ASC";
$stmt = $pdo->prepare($posQuery);
$stmt->execute($posParams);
$positions = $stmt->fetchAll();

// Calculate counts for summary cards
$total_positions = count($positions);
$total_departments = $pdo->query("SELECT COUNT(*) FROM departments")->fetchColumn();
$total_employees_assigned = $pdo->query("SELECT COUNT(*) FROM users WHERE position_id IS NOT NULL")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Positions - LMS</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .jd-preview { font-size: 0.83rem; color: var(--text-muted); max-width: 280px; white-space: pre-wrap; word-break: break-word; }
        .lms-overlay { display: none; position: fixed; inset: 0; background: rgba(15,23,42,0.5); backdrop-filter: blur(5px); z-index: 2000; align-items: center; justify-content: center; }
        .lms-overlay.show { display: flex; }
        .lms-modal-box { background: #fff; border-radius: 18px; padding: 36px 32px; max-width: 540px; width: 90%; box-shadow: 0 20px 40px rgba(0,0,0,0.15); animation: mPop 0.3s cubic-bezier(0.34,1.56,0.64,1); max-height: 90vh; overflow-y: auto; }
        @keyframes mPop { from { transform: scale(0.8); opacity: 0; } to { transform: scale(1); opacity: 1; } }
        .lms-modal-box h3 { font-size: 1.2rem; font-weight: 700; margin-bottom: 20px; }
        .modal-footer-btns { display: flex; gap: 10px; margin-top: 22px; }
        .modal-footer-btns button { flex: 1; }
        .filter-bar { display: flex; align-items: center; gap: 12px; margin-bottom: 18px; }
    </style>
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>
    <div class="main-content">
        <div class="top-nav">
            <h2><i class="fas fa-briefcase"></i> Manage Positions</h2>
            <div class="user-info"><span>Logged in as: <strong><?php echo e($_SESSION['name']); ?></strong></span></div>
        </div>

        <div class="content-wrapper">
            <?php if ($success): ?><p class="success"><?php echo e($success); ?></p><?php endif; ?>
            <?php if ($error):   ?><p class="error"><?php echo e($error); ?></p><?php endif; ?>

            <!-- Summary Stats -->
            <div class="stats-container" style="margin-bottom: 24px;">
                <div class="stat-card">
                    <div class="stat-info">
                        <strong>Total Positions</strong>
                        <div class="value"><?php echo $total_positions; ?></div>
                    </div>
                    <div class="stat-icon" style="background: rgba(59, 130, 246, 0.08); color: #3b82f6;">
                        <i class="fas fa-briefcase"></i>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-info">
                        <strong>Departments</strong>
                        <div class="value"><?php echo $total_departments; ?></div>
                    </div>
                    <div class="stat-icon" style="background: rgba(16, 185, 129, 0.08); color: #10b981;">
                        <i class="fas fa-building"></i>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-info">
                        <strong>Employees Assigned</strong>
                        <div class="value"><?php echo $total_employees_assigned; ?></div>
                    </div>
                    <div class="stat-icon" style="background: rgba(245, 158, 11, 0.08); color: var(--warning);">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
            </div>

            <!-- Add Position -->
            <div class="card" style="margin-bottom: 28px;">
                <div class="card-header"><h3><i class="fas fa-plus-circle"></i> Add New Position</h3></div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                        <input type="hidden" name="form_action" value="add">
                        <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                            <div class="form-group" style="margin: 0;">
                                <label>Department <span style="color:var(--danger)">*</span></label>
                                <select name="department_id" required>
                                    <option value="">Select Department</option>
                                    <?php foreach ($departments as $d): ?>
                                    <option value="<?php echo $d['id']; ?>" <?php echo $filter_dept == $d['id'] ? 'selected' : ''; ?>><?php echo e($d['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group" style="margin: 0;">
                                <label>Position Title <span style="color:var(--danger)">*</span></label>
                                <input type="text" name="title" required placeholder="e.g. Software Developer">
                            </div>
                        </div>
                        <div class="form-group" style="margin-top: 14px;">
                            <label>Job Description <span style="font-size:0.8rem; color:var(--text-muted)">(Optional)</span></label>
                            <textarea name="job_description" rows="4" placeholder="Describe responsibilities, requirements, and qualifications..."></textarea>
                        </div>
                        <button type="submit" style="width: auto; padding: 10px 24px;">
                            <i class="fas fa-plus"></i> Create Position
                        </button>
                    </form>
                </div>
            </div>

            <!-- Positions Table -->
            <div class="card">
                <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
                    <h3><i class="fas fa-list"></i> All Positions (<?php echo count($positions); ?>)</h3>
                    <div class="filter-bar" style="margin: 0;">
                        <form method="GET" style="display: flex; gap: 10px; align-items: center;">
                            <select name="dept" onchange="this.form.submit()" style="padding: 7px 12px; font-size: 0.88rem; border: 1px solid var(--border); border-radius: 8px;">
                                <option value="">All Departments</option>
                                <?php foreach ($departments as $d): ?>
                                <option value="<?php echo $d['id']; ?>" <?php echo $filter_dept == $d['id'] ? 'selected' : ''; ?>><?php echo e($d['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($filter_dept): ?>
                            <a href="manage_positions.php" style="color: var(--text-muted); font-size: 0.88rem; text-decoration: none;"><i class="fas fa-times"></i> Clear</a>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Position Title</th>
                                    <th>Department</th>
                                    <th>Job Description</th>
                                    <th>Employees</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($positions)): ?>
                                <tr><td colspan="5" style="text-align: center; padding: 30px; color: var(--text-muted);">No positions found.</td></tr>
                                <?php else: ?>
                                <?php foreach ($positions as $p): ?>
                                <tr>
                                    <td><strong><?php echo e($p['title']); ?></strong></td>
                                    <td>
                                        <span class="badge" style="background: rgba(79,70,229,0.1); color: #4f46e5;">
                                            <i class="fas fa-building"></i> <?php echo e($p['dept_name']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="jd-preview">
                                            <?php echo $p['job_description']
                                                ? (strlen($p['job_description']) > 100 ? e(substr($p['job_description'], 0, 100)) . '…' : e($p['job_description']))
                                                : '<em style="color:#cbd5e1">No description</em>'; ?>
                                        </div>
                                    </td>
                                    <td style="text-align: center;">
                                        <span class="badge" style="background: rgba(16,185,129,0.1); color: #059669;">
                                            <?php echo $p['employee_count']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div style="display: flex; gap: 8px;">
                                            <button onclick="openEdit(<?php echo $p['id']; ?>,<?php echo $p['department_id']; ?>,'<?php echo e(addslashes($p['title'])); ?>','<?php echo e(addslashes($p['job_description'] ?? '')); ?>')"
                                                style="width: auto; padding: 6px 12px; font-size: 0.82rem; background: var(--primary);">
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                            <?php if ($p['employee_count'] == 0): ?>
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this position?')">
                                                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                                <input type="hidden" name="form_action" value="delete">
                                                <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                                                <button type="submit" style="width: auto; padding: 6px 12px; font-size: 0.82rem; background: var(--danger);">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                            <?php endif; ?>
                                        </div>
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

    <!-- Edit Modal -->
    <div class="lms-overlay" id="editOverlay">
        <div class="lms-modal-box">
            <h3><i class="fas fa-edit" style="color: var(--primary);"></i> Edit Position</h3>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                <input type="hidden" name="form_action" value="edit">
                <input type="hidden" name="id" id="edit-id">
                <div class="form-group">
                    <label>Department <span style="color:var(--danger)">*</span></label>
                    <select name="department_id" id="edit-dept" required>
                        <?php foreach ($departments as $d): ?>
                        <option value="<?php echo $d['id']; ?>"><?php echo e($d['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Position Title <span style="color:var(--danger)">*</span></label>
                    <input type="text" name="title" id="edit-title" required>
                </div>
                <div class="form-group">
                    <label>Job Description</label>
                    <textarea name="job_description" id="edit-jd" rows="5"></textarea>
                </div>
                <div class="modal-footer-btns">
                    <button type="button" style="background: #f1f5f9; color: var(--text);" onclick="closeEdit()"><i class="fas fa-times"></i> Cancel</button>
                    <button type="submit"><i class="fas fa-save"></i> Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    function openEdit(id, deptId, title, jd) {
        document.getElementById('edit-id').value     = id;
        document.getElementById('edit-dept').value   = deptId;
        document.getElementById('edit-title').value  = title;
        document.getElementById('edit-jd').value     = jd;
        document.getElementById('editOverlay').classList.add('show');
    }
    function closeEdit() { document.getElementById('editOverlay').classList.remove('show'); }
    document.getElementById('editOverlay').addEventListener('click', function(e) {
        if (e.target === this) closeEdit();
    });
    </script>
</body>
</html>
