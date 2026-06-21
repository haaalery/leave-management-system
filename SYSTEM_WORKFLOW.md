# Leave Management System (LMS) - Functional & Workflow Specification

This document provides a comprehensive guide to the features, system architecture, workflows, and security frameworks of the Leave Management System.

---

## 1. System Overview & Stack
*   **Backend:** PHP (OOP-style PDO for safe database interactions).
*   **Database:** MySQL (MariaDB).
*   **Frontend:** Semantic HTML5, CSS3 Custom Properties (variables), and FontAwesome icons.
*   **Analytics:** Chart.js (CDN-integrated) for administrative dashboards.
*   **Security Features:** Anti-CSRF tokens, HTML Output Sanitization, SQL Injection protection, and password hashing (`bcrypt`).

---

## 2. Database Schema

### `users` Table
Stores authentication and authorization credentials.
*   `id` (INT, Primary Key, Auto Increment)
*   `name` (VARCHAR)
*   `email` (VARCHAR, Unique Index)
*   `password` (VARCHAR, Hashed using `PASSWORD_DEFAULT`)
*   `role` (ENUM: `'Employee'`, `'Admin'`)

### `leave_requests` Table
Tracks all submitted leave requests.
*   `id` (INT, Primary Key, Auto Increment)
*   `user_id` (INT, Foreign Key referencing `users(id)`)
*   `leave_type` (ENUM: `'Vacation'`, `'Sick Leave'`, `'Unpaid'`)
*   `start_date` (DATE)
*   `end_date` (DATE)
*   `reason` (TEXT)
*   `status` (ENUM: `'Pending'`, `'Approved'`, `'Rejected'`)
*   `admin_comment` (TEXT, Optional comment added during approval/rejection)
*   `attachment` (VARCHAR, File path to uploaded document)
*   `created_at` (TIMESTAMP)

### `leave_balances` Table
Manages allowance counters per leave type per employee.
*   `id` (INT, Primary Key, Auto Increment)
*   `user_id` (INT, Foreign Key referencing `users(id)`)
*   `leave_type` (ENUM: `'Vacation'`, `'Sick Leave'`, `'Unpaid'`)
*   `total_allowed` (DECIMAL(5,2)) - Supports fractional balances (e.g. `16.25`)
*   `days_used` (DECIMAL(5,2))

---

## 3. Security Architecture

### Cross-Site Scripting (XSS) Mitigation
Every user-supplied output is passed through a global sanitization helper:
```php
function e($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}
```
This sanitizes full names, emails, leave reasons, admin comments, and badge statuses.

### Cross-Site Request Forgery (CSRF) Protection
State-changing operations (submitting leave requests, approving/rejecting requests, adding users, adjusting balances, and running accruals) validate a secure, cryptographically random session token:
*   **Generation:** `generateCsrfToken()` binds a hexadecimal token to `$_SESSION['csrf_token']`.
*   **Validation:** `verifyCsrfToken()` validates POST-submitted tokens against the session using `hash_equals()`.

---

## 4. Workflows & Functional Modules

### A. Authentication & Gatekeeping
*   **Login (`login.php`):** Validates credentials using `password_verify()`. Directs administrators to the Admin Panel and employees to the Employee Dashboard.
*   **Authorization (`includes/auth_check.php`):** Checks roles on each page access. Basic employees attempting to access admin directories are redirected, and vice versa.

### B. Employee Workflows

#### 1. Dashboard Overview (`employee_dashboard.php`)
*   Displays remaining vs. total allowance balances for Vacation, Sick Leave, and Unpaid leave types.
*   Lists the employee's historical requests with dates, reason description, admin comment notes, attachment links, and color-coded status badges.

#### 2. Requesting Leave (`request_leave.php`)
*   **Submission Form:** Employees enter Leave Type, Start Date, End Date, Reason, and an optional File Attachment (accepts PDF/PNG/JPG/JPEG up to 2MB).
*   **Date Checks:** Blocks requests with start dates in the past, or where end date occurs before start date.
*   **Overlap Verification:** Runs database queries to ensure the employee doesn't have an existing approved or pending request overlapping with the selected dates.
*   **Balance & Double-Spending Checks:** Subtracts both current `days_used` and other already *Pending* requested days from `total_allowed`. If the available days are less than the request size, submission is blocked.

---

### C. Admin Workflows

#### 1. Dashboard Analytics (`admin_dashboard.php`)
The Admin Control Panel displays **six numerical stat cards** drawing live counts directly from the database:

**Row 1 — Workforce Snapshot**
| Card | Source Query | Description |
| :--- | :--- | :--- |
| Active Leaves Today | `WHERE status = 'Approved' AND CURDATE() BETWEEN start_date AND end_date` | How many approved employees are currently on leave right now. |
| Requests Today | `WHERE DATE(created_at) = CURDATE()` | Total new leave requests filed today. |
| Pending Approval | `WHERE status = 'Pending'` | Total requests waiting for an admin decision. |

**Row 2 — Breakdown by Leave Type**
| Card | Source Query | Description |
| :--- | :--- | :--- |
| Total Vacation Leaves | `WHERE leave_type = 'Vacation'` | Total vacation requests of all time. |
| Total Sick Leaves | `WHERE leave_type = 'Sick Leave'` | Total sick leave requests of all time. |
| Total Unpaid Leaves | `WHERE leave_type = 'Unpaid'` | Total unpaid leave requests of all time. |
*   Displays a table of all pending requests, showing employee name, type, date span, and attachment links.

#### 2. Processing Requests (`approve_deny.php`)
*   **Action & Comment:** Admins approve or reject leaves, optionally adding a comment.
*   **Approval Lock:** Uses database transactions (`FOR UPDATE`) to lock leave balances. It re-verifies that the employee still has sufficient remaining allowance at the precise moment of approval before incrementing `days_used` and changing the request status.

#### 3. User Management (`manage_users.php`)
*   **Add Users:** Admins can create new system accounts.
*   **Auto-initialization:** Creating an 'Employee' automatically registers three default leave balances (Vacation: 15, Sick Leave: 10, Unpaid: 30) inside the database.
*   **Manual Balance Adjustments (`adjust_balance.php`):** Admins can manually increment or decrement (e.g. `+5` or `-2` days) individual employee balances.
*   **Accrual Actions (`accrual_actions.php`):** Exposes two global controls:
    *   *Run Accrual:* Increments Vacation `total_allowed` balances by `+1.25` days for all employees. This simulates employees "earning" leave gradually over the course of a year (1.25 days per month $\times$ 12 months = 15 vacation days per year).
    *   *Reset Balances:* Resets all employee leave counters by setting `days_used` back to `0.00` for all leave types, allowing employees to start a new fiscal year fresh while keeping their allowed limits intact.

    ##### Detailed Accrual & Reset Mechanics:
    
    | Column Name | Description | Modified by "Run Accrual" | Modified by "Reset Balances" |
    | :--- | :--- | :--- | :--- |
    | `total_allowed` | The maximum days an employee is allowed to take. | **Increases by `1.25`** | *Unchanged* |
    | `days_used` | The number of days the employee has actually taken. | *Unchanged* | **Sets to `0.00`** |
