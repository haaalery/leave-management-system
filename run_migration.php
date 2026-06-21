<?php
require_once 'includes/config.php';

try {
    // 0. Create audit_logs table if it doesn't exist
    $pdo->exec("CREATE TABLE IF NOT EXISTS audit_logs (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        actor_id    INT NOT NULL,
        actor_name  VARCHAR(255) NOT NULL,
        action      VARCHAR(100) NOT NULL,
        target_type VARCHAR(50)  NOT NULL,
        target_id   INT          NOT NULL,
        details     TEXT         NULL,
        created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_actor (actor_id),
        INDEX idx_target (target_type, target_id),
        INDEX idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "<p>Ensured 'audit_logs' table exists.</p>";
    // 1. Add gender column to users if it doesn't exist
    $cols = $pdo->query("SHOW COLUMNS FROM users LIKE 'gender'")->fetchAll();
    if (empty($cols)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN gender ENUM('Male', 'Female') NOT NULL DEFAULT 'Male'");
        echo "<p>Added 'gender' column to 'users' table.</p>";
    }

    // 2. Modify leave_requests.leave_type to VARCHAR(100)
    $pdo->exec("ALTER TABLE leave_requests MODIFY COLUMN leave_type VARCHAR(100) NOT NULL");
    echo "<p>Modified 'leave_requests.leave_type' to VARCHAR(100).</p>";

    // 3. Modify leave_balances.leave_type to VARCHAR(100)
    $pdo->exec("ALTER TABLE leave_balances MODIFY COLUMN leave_type VARCHAR(100) NOT NULL");
    echo "<p>Modified 'leave_balances.leave_type' to VARCHAR(100).</p>";

    // Add 'admin_comment' column to leave_requests if missing
    $col_check = $pdo->query("SHOW COLUMNS FROM leave_requests LIKE 'admin_comment'")->fetchAll();
    if (empty($col_check)) {
        $pdo->exec("ALTER TABLE leave_requests ADD COLUMN admin_comment TEXT NULL AFTER status");
        echo "<p>Added 'admin_comment' column to 'leave_requests' table.</p>";
    } else {
        echo "<p>'admin_comment' column already exists. Skipped.</p>";
    }

    // Add 'attachment' column to leave_requests if missing
    $col_check = $pdo->query("SHOW COLUMNS FROM leave_requests LIKE 'attachment'")->fetchAll();
    if (empty($col_check)) {
        $pdo->exec("ALTER TABLE leave_requests ADD COLUMN attachment VARCHAR(255) NULL AFTER admin_comment");
        echo "<p>Added 'attachment' column to 'leave_requests' table.</p>";
    } else {
        echo "<p>'attachment' column already exists. Skipped.</p>";
    }

    // Add 'updated_at' column to leave_requests if missing
    $col_check = $pdo->query("SHOW COLUMNS FROM leave_requests LIKE 'updated_at'")->fetchAll();
    if (empty($col_check)) {
        $pdo->exec("ALTER TABLE leave_requests ADD COLUMN updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER attachment");
        $pdo->exec("UPDATE leave_requests SET updated_at = created_at WHERE updated_at IS NULL");
        echo "<p>Added 'updated_at' column to 'leave_requests' table.</p>";
    } else {
        echo "<p>'updated_at' column already exists. Skipped.</p>";
    }

    // 4. Update existing records from 'Vacation' to 'Vacation Leave'
    $pdo->exec("UPDATE leave_requests SET leave_type = 'Vacation Leave' WHERE leave_type = 'Vacation'");
    $pdo->exec("UPDATE leave_balances SET leave_type = 'Vacation Leave' WHERE leave_type = 'Vacation'");
    
    // 5. Update existing records from 'Unpaid' to 'Unpaid Leave'
    $pdo->exec("UPDATE leave_requests SET leave_type = 'Unpaid Leave' WHERE leave_type = 'Unpaid'");
    $pdo->exec("UPDATE leave_balances SET leave_type = 'Unpaid Leave' WHERE leave_type = 'Unpaid'");
    echo "<p>Migrated existing 'Vacation' and 'Unpaid' leaves to 'Vacation Leave' and 'Unpaid Leave'.</p>";

    // 6. Ensure existing active users have all 10 leave balances
    $users = $pdo->query("SELECT id, gender FROM users WHERE role = 'Employee'")->fetchAll();
    
    $leave_types = [
        'Vacation Leave' => 15,
        'Sick Leave' => 10,
        'Emergency Leave' => 5,
        'Maternity Leave' => 105,
        'Paternity Leave' => 7,
        'Bereavement Leave' => 5,
        'Study Leave' => 15,
        'Compensatory Leave' => 0,
        'Unpaid Leave' => 30,
        'Special Leave' => 5
    ];

    foreach ($users as $user) {
        $userId = $user['id'];
        foreach ($leave_types as $type => $allowed) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM leave_balances WHERE user_id = ? AND leave_type = ?");
            $stmt->execute([$userId, $type]);
            if ($stmt->fetchColumn() == 0) {
                $stmt = $pdo->prepare("INSERT INTO leave_balances (user_id, leave_type, total_allowed, days_used) VALUES (?, ?, ?, 0)");
                $stmt->execute([$userId, $type, $allowed]);
            }
        }
    }
    echo "<p>Initialized missing leave balances for all existing employees.</p>";

    // ── DEPARTMENTS & POSITIONS MIGRATION ────────────────────────────────

    // A. Create departments table
    $pdo->exec("CREATE TABLE IF NOT EXISTS departments (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        name        VARCHAR(100) NOT NULL UNIQUE,
        description TEXT NULL,
        created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "<p>Ensured 'departments' table exists.</p>";

    // B. Create positions table (linked to department)
    $pdo->exec("CREATE TABLE IF NOT EXISTS positions (
        id              INT AUTO_INCREMENT PRIMARY KEY,
        department_id   INT NOT NULL,
        title           VARCHAR(100) NOT NULL,
        job_description TEXT NULL,
        created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "<p>Ensured 'positions' table exists.</p>";

    // C. Add department_id FK column to users if missing
    $col = $pdo->query("SHOW COLUMNS FROM users LIKE 'department_id'")->fetchAll();
    if (empty($col)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN department_id INT NULL AFTER department");
        echo "<p>Added 'department_id' column to 'users' table.</p>";
    }

    // D. Add position_id FK column to users if missing
    $col = $pdo->query("SHOW COLUMNS FROM users LIKE 'position_id'")->fetchAll();
    if (empty($col)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN position_id INT NULL AFTER position");
        echo "<p>Added 'position_id' column to 'users' table.</p>";
    }

    // E. Auto-match: read all distinct (department, position) combos from users
    $combos = $pdo->query("
        SELECT DISTINCT department, position FROM users
        WHERE department IS NOT NULL AND department != ''
    ")->fetchAll();

    $deptMap = [];  // dept name => dept id
    $posMap  = [];  // "dept_id:title" => position id

    foreach ($combos as $row) {
        $deptName = trim($row['department']);
        $posTitle = trim($row['position']);

        // Create department if not exists
        if (!isset($deptMap[$deptName])) {
            $pdo->prepare("INSERT IGNORE INTO departments (name) VALUES (?)")->execute([$deptName]);
            $did = $pdo->query("SELECT id FROM departments WHERE name = " . $pdo->quote($deptName))->fetchColumn();
            $deptMap[$deptName] = $did;
            echo "<p>Department: <strong>" . htmlspecialchars($deptName) . "</strong> → ID $did</p>";
        }
        $dept_id = $deptMap[$deptName];

        // Create position if not exists (scoped to department)
        if ($posTitle && !isset($posMap["$dept_id:$posTitle"])) {
            $pdo->prepare("INSERT IGNORE INTO positions (department_id, title) VALUES (?,?)")->execute([$dept_id, $posTitle]);
            $pid = $pdo->query("SELECT id FROM positions WHERE department_id=$dept_id AND title=" . $pdo->quote($posTitle))->fetchColumn();
            $posMap["$dept_id:$posTitle"] = $pid;
            echo "<p>&nbsp;&nbsp;Position: <strong>" . htmlspecialchars($posTitle) . "</strong> → ID $pid</p>";
        }
    }

    // F. Update users.department_id and position_id based on matched records
    $users_to_link = $pdo->query("SELECT id, department, position FROM users WHERE department IS NOT NULL AND department != ''")->fetchAll();
    $linked = 0;
    foreach ($users_to_link as $u) {
        $deptName = trim($u['department']);
        $posTitle = trim($u['position']);
        $dept_id  = $deptMap[$deptName] ?? null;
        $pos_id   = ($dept_id && $posTitle) ? ($posMap["$dept_id:$posTitle"] ?? null) : null;
        if ($dept_id) {
            $pdo->prepare("UPDATE users SET department_id=?, position_id=? WHERE id=?")
                ->execute([$dept_id, $pos_id, $u['id']]);
            $linked++;
        }
    }
    echo "<p>Linked department/position IDs for <strong>$linked</strong> existing user(s).</p>";

    // 7. Backfill audit_logs with historical leave request decisions
    $past_leaves = $pdo->query("
        SELECT lr.id, lr.leave_type, lr.start_date, lr.end_date, lr.status, lr.admin_comment,
               lr.user_id, lr.created_at, lr.updated_at
        FROM leave_requests lr
        WHERE lr.status IN ('Approved','Rejected')
    ")->fetchAll();

    $backfill_stmt = $pdo->prepare("
        INSERT IGNORE INTO audit_logs (actor_id, actor_name, action, target_type, target_id, details, created_at)
        VALUES (0, 'System (Historical)', ?, 'leave_request', ?, ?, ?)
    ");

    $skip = 0; $inserted = 0;
    foreach ($past_leaves as $lr) {
        // Skip if already logged (target_type + target_id already in table)
        $exists = $pdo->prepare("SELECT COUNT(*) FROM audit_logs WHERE target_type='leave_request' AND target_id=?");
        $exists->execute([$lr['id']]);
        if ($exists->fetchColumn() > 0) { $skip++; continue; }

        $action  = 'Leave ' . $lr['status'];
        $ts      = $lr['updated_at'] ?? $lr['created_at'];
        $details = json_encode([
            'employee'   => $lr['user_id'],
            'leave_type' => $lr['leave_type'],
            'start_date' => $lr['start_date'],
            'end_date'   => $lr['end_date'],
            'comment'    => $lr['admin_comment'] ?? ''
        ]);
        $backfill_stmt->execute([$action, $lr['id'], $details, $ts]);
        $inserted++;
    }
    echo "<p>Backfilled <strong>$inserted</strong> historical leave decisions into audit_logs ($skip already existed).</p>";

    // 8. Backfill audit_logs with historical user registration decisions
    $past_users = $pdo->query("
        SELECT id, name, email, status FROM users WHERE status IN ('Active','Rejected') AND role = 'Employee'
    ")->fetchAll();

    $backfill_user = $pdo->prepare("
        INSERT IGNORE INTO audit_logs (actor_id, actor_name, action, target_type, target_id, details, created_at)
        VALUES (0, 'System (Historical)', ?, 'user', ?, ?, NOW())
    ");

    $skip2 = 0; $inserted2 = 0;
    foreach ($past_users as $u) {
        $exists = $pdo->prepare("SELECT COUNT(*) FROM audit_logs WHERE target_type='user' AND target_id=?");
        $exists->execute([$u['id']]);
        if ($exists->fetchColumn() > 0) { $skip2++; continue; }

        $action  = 'Registration ' . ($u['status'] === 'Active' ? 'Approved' : 'Rejected');
        $details = json_encode(['user_name' => $u['name'], 'email' => $u['email']]);
        $backfill_user->execute([$action, $u['id'], $details]);
        $inserted2++;
    }
    echo "<p>Backfilled <strong>$inserted2</strong> historical registration decisions into audit_logs ($skip2 already existed).</p>";

    // ── WORKFLOW SCHEMA MODIFICATIONS ────────────────────────────────────
    $pdo->exec("ALTER TABLE users MODIFY COLUMN role ENUM('Employee', 'Manager', 'Admin') DEFAULT 'Employee'");
    echo "<p>Modified 'users.role' to support 'Manager'.</p>";

    $col = $pdo->query("SHOW COLUMNS FROM departments LIKE 'manager_id'")->fetchAll();
    if (empty($col)) {
        $pdo->exec("ALTER TABLE departments ADD COLUMN manager_id INT NULL AFTER description");
        $pdo->exec("ALTER TABLE departments ADD FOREIGN KEY (manager_id) REFERENCES users(id) ON DELETE SET NULL");
        echo "<p>Added 'manager_id' column to 'departments' table.</p>";
    }

    $pdo->exec("ALTER TABLE leave_requests MODIFY COLUMN status ENUM('Pending', 'Pending Manager Approval', 'Pending Admin Approval', 'Approved', 'Rejected') DEFAULT 'Pending'");
    echo "<p>Modified 'leave_requests.status' to support multi-stage approval workflow.</p>";

    echo "<h3>Migration completed successfully!</h3>";
    echo "<p>Please delete this file (<code>run_migration.php</code>) now.</p>";
} catch (PDOException $e) {
    echo "<h3>Migration Error:</h3>" . $e->getMessage();
}
?>
