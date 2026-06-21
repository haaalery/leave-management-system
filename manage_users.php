<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/auth_check.php';
checkLogin();
checkAdmin();

$error = "";
$success = "";

// Get message from session redirect
if (isset($_SESSION['success_msg'])) {
    $success = $_SESSION['success_msg'];
    unset($_SESSION['success_msg']);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_user'])) {
    // CSRF Check
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($token)) {
        die("CSRF token validation failed.");
    }

    $first_name   = trim($_POST['first_name'] ?? '');
    $middle_name  = trim($_POST['middle_name'] ?? '');
    $last_name    = trim($_POST['last_name'] ?? '');
    $email        = trim($_POST['email'] ?? '');
    $gender       = $_POST['gender'] ?? 'Male';
    $password     = $_POST['password'] ?? '';
    $role         = $_POST['role'] ?? 'Employee';
    $dept_id      = (int)($_POST['department_id'] ?? 0);
    $pos_id       = (int)($_POST['position_id'] ?? 0);

    // Resolve text names from IDs for legacy support
    $dept_name = '';
    $pos_name  = '';
    if ($dept_id) {
        $d = $pdo->prepare("SELECT name FROM departments WHERE id=?");
        $d->execute([$dept_id]); $dept_name = $d->fetchColumn() ?: '';
    }
    if ($pos_id) {
        $p = $pdo->prepare("SELECT title FROM positions WHERE id=?");
        $p->execute([$pos_id]); $pos_name = $p->fetchColumn() ?: '';
    }

    if (empty($first_name) || empty($last_name) || empty($email) || empty($password) || empty($gender)) {
        $error = "All fields except middle name are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email address.";
    } else {
        // Check if email already exists
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetchColumn() > 0) {
            $error = "Email address is already in use.";
        } else {
            try {
                $pdo->beginTransaction();

                // Hash password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $fullname = $first_name . ($middle_name ? ' ' . $middle_name : '') . ' ' . $last_name;

                // Insert user
                $stmt = $pdo->prepare("INSERT INTO users (name, first_name, middle_name, last_name, email, password, role, status, gender, department, position, department_id, position_id) VALUES (?, ?, ?, ?, ?, ?, ?, 'Active', ?, ?, ?, ?, ?)");
                $stmt->execute([$fullname, $first_name, $middle_name, $last_name, $email, $hashed_password, $role, $gender, $dept_name, $pos_name, $dept_id ?: null, $pos_id ?: null]);
                $new_user_id = $pdo->lastInsertId();

                // If Employee, initialize default balances
                if ($role === 'Employee') {
                    $stmt = $pdo->prepare("INSERT INTO leave_balances (user_id, leave_type, total_allowed, days_used) VALUES (?, ?, ?, 0)");
                    $stmt->execute([$new_user_id, 'Vacation Leave', 15]);
                    $stmt->execute([$new_user_id, 'Sick Leave', 10]);
                    $stmt->execute([$new_user_id, 'Emergency Leave', 5]);
                    $stmt->execute([$new_user_id, 'Maternity Leave', 105]);
                    $stmt->execute([$new_user_id, 'Paternity Leave', 7]);
                    $stmt->execute([$new_user_id, 'Bereavement Leave', 5]);
                    $stmt->execute([$new_user_id, 'Study Leave', 15]);
                    $stmt->execute([$new_user_id, 'Compensatory Leave', 0]);
                    $stmt->execute([$new_user_id, 'Unpaid Leave', 30]);
                    $stmt->execute([$new_user_id, 'Special Leave', 5]);
                }

                $pdo->commit();
                $success = "User '$fullname' successfully created!";
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Failed to create user: " . $e->getMessage();
            }
        }
    }
}

// Fetch all users with resolved department and position titles
$stmt = $pdo->query("
    SELECT u.*, d.name AS dept_name, p.title AS pos_name 
    FROM users u 
    LEFT JOIN departments d ON u.department_id = d.id 
    LEFT JOIN positions p ON u.position_id = p.id 
    ORDER BY u.id ASC
");
$users = $stmt->fetchAll();

// Load departments for the dropdown
$departments_list = $pdo->query("SELECT id, name FROM departments ORDER BY name ASC")->fetchAll();

// Fetch all leave balances and map them by user_id
$stmt = $pdo->query("SELECT * FROM leave_balances");
$balances_raw = $stmt->fetchAll();
$balances = [];
foreach ($balances_raw as $b) {
    $balances[$b['user_id']][] = $b;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - LMS</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="top-nav">
            <h2><i class="fas fa-users"></i> Manage Users & Balances</h2>
            <div class="user-info">
                <span>Logged in as: <strong><?php echo e($_SESSION['name']); ?></strong></span>
            </div>
        </div>

        <div class="content-wrapper">
            <?php if ($success): ?>
                <p class="success"><?php echo e($success); ?></p>
            <?php endif; ?>
            <?php if ($error): ?>
                <p class="error"><?php echo e($error); ?></p>
            <?php endif; ?>

            <!-- Add User Form -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-user-plus"></i> Add New User</h3>
                </div>
                <div class="card-body">
                    <form method="POST" style="display: flex; flex-direction: column; gap: 15px;">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                        
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px;">
                            <div class="form-group" style="margin-bottom: 0;">
                                <label>First Name</label>
                                <input type="text" name="first_name" required placeholder="Jane">
                            </div>
                            <div class="form-group" style="margin-bottom: 0;">
                                <label>Middle Name</label>
                                <input type="text" name="middle_name" placeholder="Marie">
                            </div>
                            <div class="form-group" style="margin-bottom: 0;">
                                <label>Last Name</label>
                                <input type="text" name="last_name" required placeholder="Doe">
                            </div>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                            <div class="form-group" style="margin-bottom: 0;">
                                <label>Department <span style="font-size:0.8rem; color:var(--text-muted)">(Optional)</span></label>
                                <select name="department_id" id="add-dept" onchange="loadPositions(this.value,'add-pos')" style="width:100%;padding:12px;border:1px solid var(--border);border-radius:8px;background:#fff;font-size:0.95rem;">
                                    <option value="">Select Department</option>
                                    <?php foreach ($departments_list as $d): ?>
                                    <option value="<?php echo $d['id']; ?>"><?php echo e($d['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group" style="margin-bottom: 0;">
                                <label>Position / Job Title <span style="font-size:0.8rem; color:var(--text-muted)">(Optional)</span></label>
                                <select name="position_id" id="add-pos" style="width:100%;padding:12px;border:1px solid var(--border);border-radius:8px;background:#fff;font-size:0.95rem;">
                                    <option value="">Select Department first</option>
                                </select>
                            </div>
                        </div>

                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; align-items: end;">
                            <div class="form-group" style="margin-bottom: 0;">
                                <label>Email Address</label>
                                <input type="email" name="email" required placeholder="jane@example.com">
                            </div>
                            <div class="form-group" style="margin-bottom: 0;">
                                <label>Password</label>
                                <input type="password" name="password" required placeholder="••••••••">
                            </div>
                            <div class="form-group" style="margin-bottom: 0;">
                                <label>Gender</label>
                                <select name="gender" required>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                </select>
                            </div>
                            <div class="form-group" style="margin-bottom: 0;">
                                <label>Role</label>
                                <select name="role" required>
                                    <option value="Employee">Employee</option>
                                    <option value="Manager">Manager</option>
                                    <option value="Admin">Admin</option>
                                </select>
                            </div>
                            <div class="form-group" style="margin-bottom: 0;">
                                <button type="submit" name="add_user" style="width: 100%; height: 44px;">Create User</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Users Table -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-user-shield"></i> System Users</h3>
                    <div style="display: flex; gap: 10px;">
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Dept / Position</th>
                                    <th>Gender</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>Leave Balances (Remaining / Total) & Adjustments</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td><?php echo e($user['id']); ?></td>
                                        <td><strong><?php echo e($user['name']); ?></strong></td>
                                        <td><?php echo e($user['email']); ?></td>
                                        <td>
                                            <div style="font-size: 0.9rem; font-weight: 600;"><?php echo e($user['dept_name'] ?: ($user['department'] ?: '—')); ?></div>
                                            <div style="font-size: 0.8rem; color: var(--text-muted);"><?php echo e($user['pos_name'] ?: ($user['position'] ?: '—')); ?></div>
                                        </td>
                                        <td>
                                            <?php
                                            $gender_val = $user['gender'] ?? '';
                                            $gender_color = $gender_val === 'Female' ? '#ec4899' : '#06b6d4';
                                            $gender_icon  = $gender_val === 'Female' ? 'fa-venus' : 'fa-mars';
                                            ?>
                                            <span class="badge" style="background: <?php echo $gender_color; ?>;">
                                                <i class="fas <?php echo $gender_icon; ?>"></i>
                                                <?php echo e($gender_val ?: 'N/A'); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php
                                            $role_color = '#5bc85c'; // Employee
                                            if ($user['role'] === 'Admin') $role_color = '#0275d8';
                                            if ($user['role'] === 'Manager') $role_color = '#7c3aed'; // Purple for Manager
                                            ?>
                                            <span class="badge" style="background: <?php echo $role_color; ?>;">
                                                <?php echo e($user['role']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php 
                                            $status_color = '#f0ad4e'; // Pending
                                            if ($user['status'] === 'Active') $status_color = 'var(--success)';
                                            if ($user['status'] === 'Rejected') $status_color = 'var(--danger)';
                                            ?>
                                            <span class="badge" style="background: <?php echo $status_color; ?>;">
                                                <?php echo e($user['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if (isset($balances[$user['id']])): ?>
                                                <div style="display: flex; flex-direction: column; gap: 10px;">
                                                    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                                                        <?php foreach ($balances[$user['id']] as $b): ?>
                                                            <span style="font-size: 0.85rem; background: #f0f2f5; padding: 4px 8px; border-radius: 4px; border: 1px solid #e1e4e8;">
                                                                <strong><?php echo e($b['leave_type']); ?>:</strong> 
                                                                <?php echo (float)($b['total_allowed'] - $b['days_used']); ?>/<?php echo (float)$b['total_allowed']; ?>
                                                            </span>
                                                        <?php endforeach; ?>
                                                    </div>
                                                    
                                                    <!-- Balance Adjustment Form -->
                                                    <?php if ($user['role'] === 'Employee'): ?>
                                                        <form action="adjust_balance.php" method="POST" style="display: flex; gap: 8px; align-items: center; margin-top: 5px;">
                                                            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                            <select name="leave_type" required style="padding: 6px 10px; font-size: 0.8rem; width: auto; height: 32px; border: 1px solid var(--border); border-radius: 4px;">
                                                                <option value="Vacation Leave">Vacation Leave</option>
                                                                <option value="Sick Leave">Sick Leave</option>
                                                                <option value="Emergency Leave">Emergency Leave</option>
                                                                <option value="Maternity Leave">Maternity Leave</option>
                                                                <option value="Paternity Leave">Paternity Leave</option>
                                                                <option value="Bereavement Leave">Bereavement Leave</option>
                                                                <option value="Study Leave">Study Leave</option>
                                                                <option value="Compensatory Leave">Compensatory Leave</option>
                                                                <option value="Unpaid Leave">Unpaid Leave</option>
                                                                <option value="Special Leave">Special Leave</option>
                                                            </select>
                                                            <input type="number" name="adjustment" placeholder="+/- days" required style="padding: 6px 10px; font-size: 0.8rem; width: 90px; height: 32px; border: 1px solid var(--border); border-radius: 4px;">
                                                            <button type="submit" style="padding: 0 12px; font-size: 0.8rem; height: 32px;"><i class="fas fa-edit"></i> Adjust</button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                            <?php else: ?>
                                                <span style="color: var(--text-muted); font-style: italic; font-size: 0.85rem;">No balance records</span>
                                            <?php endif; ?>
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
</body>
</html>
<script>
async function loadPositions(deptId, targetId) {
    const sel = document.getElementById(targetId);
    sel.innerHTML = '<option value="">Loading...</option>';
    if (!deptId) { sel.innerHTML = '<option value="">Select Department first</option>'; return; }
    const res  = await fetch('get_positions.php?dept_id=' + deptId);
    const data = await res.json();
    sel.innerHTML = '<option value="">Select Position</option>';
    data.forEach(p => {
        const opt = document.createElement('option');
        opt.value = p.id; opt.textContent = p.title;
        sel.appendChild(opt);
    });
    if (!data.length) sel.innerHTML = '<option value="">No positions in this department</option>';
}
</script>
