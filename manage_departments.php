<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/auth_check.php';
checkLogin();
checkAdmin();

$success = $error = '';

// ── Handle POST actions ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($token)) die("CSRF token validation failed.");

    $action = $_POST['form_action'] ?? '';

    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $mgr_id = (int)($_POST['manager_id'] ?? 0) ?: null;
        if (empty($name)) {
            $error = "Department name is required.";
        } else {
            try {
                $pdo->prepare("INSERT INTO departments (name, description, manager_id) VALUES (?,?,?)")->execute([$name, $desc, $mgr_id]);
                $success = "Department \"$name\" created successfully.";
            } catch (PDOException $e) {
                $error = "Department name already exists.";
            }
        }
    } elseif ($action === 'edit') {
        $id     = (int)($_POST['id'] ?? 0);
        $name   = trim($_POST['name'] ?? '');
        $desc   = trim($_POST['description'] ?? '');
        $mgr_id = (int)($_POST['manager_id'] ?? 0) ?: null;
        if ($id && $name) {
            try {
                $pdo->prepare("UPDATE departments SET name=?, description=?, manager_id=? WHERE id=?")->execute([$name, $desc, $mgr_id, $id]);
                $success = "Department updated.";
            } catch (PDOException $e) {
                $error = "Department name already exists.";
            }
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        // Check if any users are assigned
        $cnt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE department_id=?");
        $cnt->execute([$id]);
        if ($cnt->fetchColumn() > 0) {
            $error = "Cannot delete: employees are still assigned to this department.";
        } else {
            $pdo->prepare("DELETE FROM departments WHERE id=?")->execute([$id]);
            $success = "Department deleted.";
        }
    }
}

// ── Fetch data ────────────────────────────────────────────────────────────
$managers_list = $pdo->query("SELECT id, name FROM users WHERE role = 'Manager' ORDER BY name ASC")->fetchAll();

$departments = $pdo->query("
    SELECT d.*, u.name AS manager_name,
        (SELECT COUNT(*) FROM users u2 WHERE u2.department_id = d.id) AS employee_count,
        (SELECT COUNT(*) FROM positions p WHERE p.department_id = d.id) AS position_count
    FROM departments d
    LEFT JOIN users u ON d.manager_id = u.id
    ORDER BY d.name ASC
")->fetchAll();

// Calculate counts for summary cards
$total_departments = count($departments);
$total_positions = $pdo->query("SELECT COUNT(*) FROM positions")->fetchColumn();
$total_assigned_users = $pdo->query("SELECT COUNT(*) FROM users WHERE department_id IS NOT NULL")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Departments - LMS</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .dept-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 18px;
            margin-bottom: 30px;
        }
        .dept-card {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 20px 22px;
            box-shadow: var(--shadow);
            position: relative;
            transition: box-shadow 0.2s;
        }
        .dept-card:hover { box-shadow: 0 8px 24px rgba(0,0,0,0.1); }
        .dept-card-title { font-size: 1.05rem; font-weight: 700; color: var(--text); margin-bottom: 6px; }
        .dept-card-desc  { font-size: 0.85rem; color: var(--text-muted); margin-bottom: 14px; min-height: 36px; }
        .dept-meta { display: flex; gap: 14px; font-size: 0.82rem; color: var(--text-muted); margin-bottom: 14px; }
        .dept-meta span { display: flex; align-items: center; gap: 5px; }
        .dept-actions { display: flex; gap: 8px; }
        .dept-actions button { width: auto; padding: 7px 14px; font-size: 0.82rem; }

        /* Edit/Add Modal */
        .lms-overlay { display: none; position: fixed; inset: 0; background: rgba(15,23,42,0.5); backdrop-filter: blur(5px); z-index: 2000; align-items: center; justify-content: center; }
        .lms-overlay.show { display: flex; }
        .lms-modal-box { background: #fff; border-radius: 18px; padding: 36px 32px; max-width: 480px; width: 90%; box-shadow: 0 20px 40px rgba(0,0,0,0.15); animation: mPop 0.3s cubic-bezier(0.34,1.56,0.64,1); }
        @keyframes mPop { from { transform: scale(0.8); opacity: 0; } to { transform: scale(1); opacity: 1; } }
        .lms-modal-box h3 { font-size: 1.2rem; font-weight: 700; margin-bottom: 20px; }
        .modal-footer-btns { display: flex; gap: 10px; margin-top: 22px; }
        .modal-footer-btns button { flex: 1; }
    </style>
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>
    <div class="main-content">
        <div class="top-nav">
            <h2><i class="fas fa-building"></i> Manage Departments</h2>
            <div class="user-info"><span>Logged in as: <strong><?php echo e($_SESSION['name']); ?></strong></span></div>
        </div>

        <div class="content-wrapper">
            <?php if ($success): ?><p class="success"><?php echo e($success); ?></p><?php endif; ?>
            <?php if ($error):   ?><p class="error"><?php echo e($error); ?></p><?php endif; ?>

            <!-- Add Department Card -->
            <div class="card" style="margin-bottom: 28px;">
                <div class="card-header"><h3><i class="fas fa-plus-circle"></i> Add New Department</h3></div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                        <input type="hidden" name="form_action" value="add">
                        <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px;">
                            <div class="form-group" style="margin: 0;">
                                <label>Department Name <span style="color:var(--danger)">*</span></label>
                                <input type="text" name="name" required placeholder="e.g. Engineering">
                            </div>
                            <div class="form-group" style="margin: 0;">
                                <label>Department Manager <span style="font-size:0.8rem; color:var(--text-muted)">(Optional)</span></label>
                                <select name="manager_id" style="width:100%; padding:12px; border:1px solid var(--border); border-radius:8px; background:#fff; font-size:0.95rem;">
                                    <option value="">Select Manager</option>
                                    <?php foreach ($managers_list as $m): ?>
                                        <option value="<?php echo $m['id']; ?>"><?php echo e($m['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group" style="margin: 0;">
                                <label>Description <span style="font-size:0.8rem; color:var(--text-muted)">(Optional)</span></label>
                                <input type="text" name="description" placeholder="Brief description of this department">
                            </div>
                        </div>
                        <button type="submit" style="width: auto; margin-top: 16px; padding: 10px 24px;">
                            <i class="fas fa-plus"></i> Create Department
                        </button>
                    </form>
                </div>
            </div>

            <!-- Departments List -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-th-large"></i> All Departments (<?php echo count($departments); ?>)</h3>
                </div>
                <div class="card-body">
                    <?php if (empty($departments)): ?>
                        <p style="text-align: center; color: var(--text-muted); padding: 30px 0;">
                            <i class="fas fa-building" style="font-size: 2rem; opacity: 0.3; display: block; margin-bottom: 10px;"></i>
                            No departments yet. Create your first one above.
                        </p>
                    <?php else: ?>
                    <div class="dept-grid">
                        <?php foreach ($departments as $d): ?>
                        <div class="dept-card">
                            <div class="dept-card-title"><i class="fas fa-building" style="color: var(--primary); margin-right: 7px;"></i><?php echo e($d['name']); ?></div>
                            <div class="dept-card-desc"><?php echo $d['description'] ? e($d['description']) : '<em style="color:#cbd5e1">No description</em>'; ?></div>
                            <div class="dept-meta" style="margin-bottom: 6px;">
                                <span><i class="fas fa-user-tie"></i> Head: <strong><?php echo $d['manager_name'] ? e($d['manager_name']) : '<span style="color:#cbd5e1; font-weight:normal;">Unassigned</span>'; ?></strong></span>
                            </div>
                            <div class="dept-meta">
                                <span><i class="fas fa-users"></i> <?php echo $d['employee_count']; ?> Employee<?php echo $d['employee_count'] != 1 ? 's' : ''; ?></span>
                                <span><i class="fas fa-briefcase"></i> <?php echo $d['position_count']; ?> Position<?php echo $d['position_count'] != 1 ? 's' : ''; ?></span>
                            </div>
                            <div class="dept-actions">
                                <button onclick="openEdit(<?php echo $d['id']; ?>,'<?php echo e(addslashes($d['name'])); ?>','<?php echo e(addslashes($d['description'] ?? '')); ?>',<?php echo (int)$d['manager_id']; ?>)"
                                    style="background: var(--primary);">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <a href="manage_positions.php?dept=<?php echo $d['id']; ?>" style="text-decoration:none;">
                                    <button style="background: #8b5cf6;"><i class="fas fa-briefcase"></i> Positions</button>
                                </a>
                                <?php if ($d['employee_count'] == 0): ?>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this department?')">
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                    <input type="hidden" name="form_action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo $d['id']; ?>">
                                    <button type="submit" style="background: var(--danger);"><i class="fas fa-trash"></i></button>
                                </form>
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

    <!-- Edit Modal -->
    <div class="lms-overlay" id="editOverlay">
        <div class="lms-modal-box">
            <h3><i class="fas fa-edit" style="color: var(--primary);"></i> Edit Department</h3>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                <input type="hidden" name="form_action" value="edit">
                <input type="hidden" name="id" id="edit-id">
                <div class="form-group">
                    <label>Department Name <span style="color:var(--danger)">*</span></label>
                    <input type="text" name="name" id="edit-name" required>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" id="edit-desc" rows="3" placeholder="Brief description..."></textarea>
                </div>
                <div class="form-group">
                    <label>Department Manager <span style="font-size:0.8rem; color:var(--text-muted)">(Optional)</span></label>
                    <select name="manager_id" id="edit-manager_id" style="width:100%; padding:12px; border:1px solid var(--border); border-radius:8px; background:#fff; font-size:0.95rem;">
                        <option value="">Select Manager</option>
                        <?php foreach ($managers_list as $m): ?>
                            <option value="<?php echo $m['id']; ?>"><?php echo e($m['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="modal-footer-btns">
                    <button type="button" style="background: #f1f5f9; color: var(--text);" onclick="closeEdit()"><i class="fas fa-times"></i> Cancel</button>
                    <button type="submit"><i class="fas fa-save"></i> Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    function openEdit(id, name, desc, mgrId) {
        document.getElementById('edit-id').value   = id;
        document.getElementById('edit-name').value = name;
        document.getElementById('edit-desc').value = desc;
        document.getElementById('edit-manager_id').value = mgrId || '';
        document.getElementById('editOverlay').classList.add('show');
    }
    function closeEdit() {
        document.getElementById('editOverlay').classList.remove('show');
    }
    document.getElementById('editOverlay').addEventListener('click', function(e) {
        if (e.target === this) closeEdit();
    });
    </script>
</body>
</html>
