# Developer Agent Guide (AGENTS.md)

This repository is a traditional, vanilla PHP web application. Use this guide to avoid common mistakes and navigate implementation details quickly.

---

## 🗺️ Ground Truth vs. Documentation Discrepancies
Previous system analysis and documentation contain several inaccuracies. Trust the actual codebase over `SYSTEM_ANALYSIS.md` and `SYSTEM_WORKFLOW.md`:

*   **No `routes/` or `admin/` Directories:** All pages and action files (e.g., `approve_deny.php`, `manage_users.php`, `adjust_balance.php`) reside directly in the workspace root. The system uses **file-based routing** at the root.
*   **No Accrual Actions:** `accrual_actions.php` is described in documentation but **does not exist** in the codebase. Do not try to reference, import, or call it.
*   **Database Types:** `total_allowed` and `days_used` in the `leave_balances` table are **`INT`** columns (not `DECIMAL` as described in docs).
*   **File Uploads:** Uploaded documents go to `/uploads` at root. File verification (2MB limit, JPG/JPEG/PNG/PDF) is handled natively.

---

## 🛠️ Security & Coding Conventions

*   **Session Management:** `session_start()` must be declared at the absolute top of every main PHP file before any HTML output, whitespaces, or includes are processed.
*   **Helper Functions (`includes/auth_check.php`):**
    *   **XSS Protection:** Wrap all dynamic HTML output with `e($value)` (shortcut for `htmlspecialchars()`).
    *   **CSRF Protection:** State-changing `POST` operations must include a CSRF input field and call `verifyCsrfToken($_POST['csrf_token'])`.
*   **Access Control:** Every page must enforce authorization checks directly after imports:
    *   `checkLogin()`
    *   `checkAdmin()` (for admin pages)
    *   `checkEmployee()` (for employee-only pages)

---

## 🚀 Environment & Verification

*   **Local Server:** Run via XAMPP Apache or use PHP's built-in server from the root directory:
    ```bash
    php -S localhost:8000
    ```
*   **Database Connection:** Configured in `includes/config.php`. Default credentials are host `localhost`, dbname `leave_management`, user `root`, empty password `""`.
*   **Schema & Seed:** Use `database.sql` to initialize or reset database tables. Default test credentials:
    *   Admin: `admin@example.com` / `password123`
    *   Employee: `john@example.com` / `password123`
