1# Improvement Plan — Leave Management System

Five features to implement, ordered by complexity & dependency.

---

## Table of Contents

1. [Database Schema Updates](#1-database-schema-updates)
2. [Days Duration Tracking](#2-days-duration-tracking)
3. [Rejection Notes (Prominent)](#3-rejection-notes-prominent)
4. [Department & Job Description](#4-department--job-description)
5. [Leave Types with Levels](#5-leave-types-with-levels)
6. [Audit Logs](#6-audit-logs)

---

## 1. Database Schema Updates

### New table: `leave_type_configs`

```sql
CREATE TABLE leave_type_configs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type_name VARCHAR(50) NOT NULL,
    level_name VARCHAR(50) NOT NULL,
    default_allowed INT NOT NULL DEFAULT 15,
    description VARCHAR(255) DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    UNIQUE KEY unique_type_level (type_name, level_name)
);

INSERT INTO leave_type_configs (type_name, level_name, default_allowed, description) VALUES
('Vacation', 'Standard', 15, 'Regular vacation leave'),
('Vacation', 'Premium',  20, 'Premium vacation leave for senior staff'),
('Sick Leave', 'Standard', 10, 'Regular sick leave'),
('Sick Leave', 'Extended', 15, 'Extended sick leave with documentation'),
('Unpaid', 'Standard', 30, 'Standard unpaid leave');
```

### New column: `leave_requests.duration_days`

```sql
ALTER TABLE leave_requests ADD COLUMN duration_days INT NOT NULL DEFAULT 0 AFTER end_date;
```

### Column changes for type+level support

```sql
ALTER TABLE leave_requests
  MODIFY COLUMN leave_type VARCHAR(50) NOT NULL,
  ADD COLUMN level_name VARCHAR(50) NOT NULL DEFAULT 'Standard' AFTER leave_type;

ALTER TABLE leave_balances
  MODIFY COLUMN leave_type VARCHAR(50) NOT NULL,
  ADD COLUMN level_name VARCHAR(50) NOT NULL DEFAULT 'Standard' AFTER leave_type;
```

### New table: `audit_logs`

```sql
CREATE TABLE audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    user_name VARCHAR(100) NOT NULL,
    action_type VARCHAR(50) NOT NULL,
    description TEXT NOT NULL,
    target_type VARCHAR(50) DEFAULT NULL,
    target_id INT DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

### Execution

One atomic SQL script (`database.sql` updated) containing all of the above. Run against the development database.

---

## 2. Days Duration Tracking

**Goal**: Persist the computed day count in `leave_requests.duration_days` instead of recalculating on every page load.

| File | Change |
|------|--------|
| `request_leave.php` | After `$days = $interval->days + 1`, include `duration_days` in the INSERT statement |
| `leave_requests.php` | Read `duration_days` from DB instead of computing inline (line 232-234 can be removed) |
| `employee_dashboard.php` | Show `duration_days` in the request history table |
| `admin_dashboard.php` | Show `duration_days` in pending & recent activity tables |

---

## 3. Rejection Notes (Prominent)

**Goal**: Make the admin's rejection reason clearly visible to employees. `admin_comment` field already exists; no schema change needed.

| File | Change |
|------|--------|
| `approve_deny.php` | Add validation: `admin_comment` is **required** when `$action == 'Rejected'`. If empty, throw an error: "A rejection reason is required." |
| `employee_dashboard.php` | When `status === 'Rejected'`, render a prominent red-styled rejection note box showing the admin comment, instead of the small text-only line |

---

## 4. Department & Job Description

**Goal**: Surface `department` and `position` (already in DB) more prominently across the system.

| File | Change |
|------|--------|
| `employee_dashboard.php` | Show department & position in the dashboard header next to the welcome message |
| `manage_users.php` | Already shows Dept / Position in the table header (col 4). Add an **inline edit modal** to update department and position for each user |
| `leave_requests.php` | Already shows `employee_dept` in modal. Add employee position alongside it |

---

## 5. Leave Types with Levels

**Goal**: Replace flat ENUM with a normalized config table. Each leave type (Vacation, Sick Leave, Unpaid) can have multiple levels (e.g., Standard, Premium, Extended) with different default day allocations.

### Files to modify

| File | Change |
|------|--------|
| `database.sql` | Add `leave_type_configs` table + seed data. Alter `leave_requests` and `leave_balances` columns |
| `request_leave.php` | Fetch leave type configs from DB; render two dropdowns (type → level) with cascading. Pass both `leave_type` and `level_name` to INSERT |
| `employee_dashboard.php` | Display **type + level** in balances (e.g., "Vacation – Standard") and in request history |
| `leave_requests.php` | Show type + level in table column and detail modal. Update query to include level_name |
| `admin_dashboard.php` | Show type + level in pending requests, recent activity, and stat cards |
| `manage_users.php` | Balance adjustment form: fetch level dropdown from DB per type. Show type + level in balance display |
| `adjust_balance.php` | Accept `level_name` from form, include it in the UPDATE query |
| `activate_user.php` | Create leave_balances for each **active** `leave_type_configs` row (type+level combo), not just the 3 hardcoded rows |
| `register.php` | No change needed |

### New file

| File | Purpose |
|------|---------|
| `manage_leave_types.php` | Admin CRUD page to add/edit/deactivate leave type levels. Lists all rows from `leave_type_configs` with inline forms |

---

## 6. Audit Logs

**Goal**: Track all state-changing operations with who, what, when, and target.

### New helper file

| File | Purpose |
|------|---------|
| `includes/audit_helper.php` | Single function `logAudit($pdo, $action_type, $description, $target_type = null, $target_id = null)` — reads `$_SESSION` for user info, gets IP, inserts into `audit_logs` |

### Files to instrument (add `logAudit()` after each state change)

| File | Actions to log |
|------|----------------|
| `approve_deny.php` | `approve_leave`, `reject_leave` — include request ID and employee name |
| `adjust_balance.php` | `adjust_balance` — include user, leave type, and adjustment value |
| `manage_users.php` | `create_user` — include user name, email, and role |
| `activate_user.php` | `activate_user`, `reject_user` — include user ID and name |
| `login.php` | `login` — include user name and role |

### New admin page

| File | Purpose |
|------|---------|
| `audit_logs.php` | Admin-only page. Table with search/filter by action type, user, date range. Paginated |

### Sidebar update

| File | Change |
|------|--------|
| `includes/sidebar.php` | Add "Audit Logs" link in the admin menu section |

---

## Execution Order

| Step | What | Why this order |
|------|------|----------------|
| 1 | **Database schema** — run all DDL/DML at once | Foundation for every feature |
| 2 | **Days duration** — simple column + 4 file edits | Small, low-risk intro change |
| 3 | **Rejection notes** — 2 file edits, no schema | Quick UI win |
| 4 | **Department & Job** — 3 file edits, no schema | Mostly display tweaks |
| 5 | **Leave types with levels** — 8 files + 1 new file | Biggest change; touches everything but builds on schema already in place |
| 6 | **Audit logs** — 1 helper + 5 instrumentation edits + 2 new files | Captures all prior actions once they exist |

---

