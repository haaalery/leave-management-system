# Project Presentation & Demo Script: Leave Management System (LMS)

This guide provides a step-by-step workflow for demoing the LMS to your professor, along with a speaking script for what to say during each step, and expected Q&A topics.

---

## 📅 Presentation Outline & Timeline
1.  **Introduction (1-2 mins):** The problem and our solution.
2.  **Step-by-Step System Demo (6-8 mins):**
    *   *Phase 1:* Public Self-Registration (Split names, optional middle name, role pending).
    *   *Phase 2:* Admin Login & Registration Review (Sidebar tab, Details Modal popup, Approve/Reject).
    *   *Phase 3:* Admin Dashboard & Real-Time Metrics (6 numerical cards).
    *   *Phase 4:* Employee Dashboard, Overlap Checks, and File Uploads.
    *   *Phase 5:* Admin Approval, Transactional Locking, and Comments.
    *   *Phase 6:* Balance adjustments & Accrual triggers.
3.  **Security & Technical Pillars (2 mins):** CSRF, XSS, and DB design.
4.  **Conclusion & Q&A (2-3 mins).**

---

## 🎤 Step-by-Step Demo Script & Actions

### 🔑 Stage 1: Introduction
*   **Action:** Open the login page (`login.php`) on your screen.
*   **What to Say:**
    > *"Good day, Professor! Today, I am excited to present the Leave Management System (LMS). Traditional leave systems often suffer from security vulnerabilities like XSS, or logic flaws like double-spending leave days. Our system was designed from the ground up to solve these issues using a modern, responsive interface, real-time database transactions, and strict validation checks. Let me show you our new public signup feature."*

---

### 📝 Stage 1B: Public Self-Registration
*   **Action:** Click the **Register here** link on the login page. Fill out the registration form: First Name: `Jane`, Middle Name: `Marie` (optional), Last Name: `Doe`, Email: `jane@example.com`, Password: `password123`, Department: `Engineering`, Position: `Software Engineer`. Click **Submit Registration**. Attempt to log in immediately as `jane@example.com` to show the blocked state.
*   **What to Say:**
    > *"Instead of relying solely on admins to add users manually, we have implemented a secure public self-registration system. The form takes separate fields for First Name, Middle Name, and Last Name. 
    > 
    > When submitted, the account defaults to 'Pending' status. If I attempt to log in immediately, the system blocks access and informs me that the account is pending administrator approval. This ensures unauthorized users cannot access the platform."*

---

### 👥 Stage 2: Admin Registration Approval & Metrics Dashboard
*   **Action:** Log in as `admin@example.com` (password: `password123`). Navigate to the new **Registrations** tab in the sidebar. Click on the row for `Jane Marie Doe`. In the modal popup that appears, review the details, and click **Approve & Activate**.
*   **What to Say:**
    > *"Now let's log in as the Administrator. In the sidebar, we have a dedicated 'Registrations' tab. Clicking it shows all pending user signups. 
    > 
    > To make validation efficient for HR, we built an interactive modal interface. Clicking on any row pops up the user's complete profile. I will click 'Approve & Activate' for Jane. The system updates her status to Active and instantly initializes her default leave balances in the database.
    > 
    > If we return to the Admin Dashboard, we see our real-time numerical stat cards. The top row tracks active leaves, requests filed today, and pending approvals, while the bottom row breaks down total requests by leave type."*

---

### 👤 Stage 3: Adding Users & Auto-Balance Setup
*   **Action:** Click **Manage Users** on the sidebar. Fill out the "Add New User" form (e.g., First Name: `Bob`, Middle Name: `James`, Last Name: `Smith`, Email: `bob@example.com`, Password: `password123`, Role: `Employee`) and click **Create User**.
*   **What to Say:**
    > *"In addition to user self-registration, admins can still create users manually. The admin creation form also supports separate First, Middle, and Last Name values. When I submit this form, it sets the user's status to Active immediately and initializes their leave balances inside a secure transaction. We can see Bob James Smith on our list with 15 Vacation, 10 Sick, and 30 Unpaid days assigned."*

---

### 📎 Stage 4: Employee Leave Request, File Uploads, & Validation
*   **Action:** Logout, then log in as the newly created user (`jane@example.com` / `password123`). Click **Request Leave**. Select **Vacation**, choose dates (e.g., June 20 to June 25), write a reason, upload a test PDF or image in the attachment field, and submit.
*   **What to Say:**
    > *"I am now logged in as the new employee, Jane Doe. On my dashboard, I can see my remaining leave days. I will submit a leave request. Note that we support secure file attachments, like medical certificates or itineraries, limited to 2MB. 
    > 
    > Our request system has strict validation:
    > 1. It blocks leaves scheduled in the past.
    > 2. It prevents overlapping leave requests. If I try to request another leave on these same dates, the system will block me.
    > 3. It prevents 'double-spending' balances. The system calculates available days by subtracting both already-used days and any active pending requests, preventing employees from overdrafting their allowance."*

---

### 🔒 Stage 5: Admin Approval, Comments, & Transactional Locking
*   **Action:** Logout, log back in as `admin@example.com`. Go to the pending table, locate Jane Doe's request. Click the **View Attachment** paperclip link. Type a comment in the comment input box (e.g., *"Approved, cover plan approved"*), and click **Approve**.
*   **What to Say:**
    > *"Back on the admin dashboard, Jane's pending request appears. I can open and inspect her uploaded attachment directly. When I approve this request, the backend initiates a SQL transaction using `FOR UPDATE` row locking. 
    > 
    > This ensures that even if two admins try to approve requests for the same employee simultaneously, the database locks the records and processes them sequentially, re-verifying the balance right before approval to avoid concurrency errors. I will add a comment and click Approve."*

---

### ⚙️ Stage 6: Balance Adjustments & Accrual Triggers
*   **Action:** Go to **Manage Users**. Point to the adjustment forms next to Jane Doe's record. Type `+5` and click **Adjust**. Then, click the **Run Accrual** button at the top card header.
*   **What to Say:**
    > *"Finally, we have global administrative controls. On the Manage Users screen, I can manually adjust an employee's balance—for example, adding 5 days to Jane's Vacation allowance for working overtime. 
    > 
    > We also support a 'Run Accrual' action, which increments vacation allowances for all employees by +1.25 days. To support this fractional accrual, we upgraded our database balance columns to double decimal floats. We also provide a one-click 'Reset Balances' tool to clear used leave records at the start of a new fiscal year."*

---

## 🙋 Expected Q&A Preparation (Be Ready!)

### Q1: How do you prevent SQL Injection in your PHP code?
*   **Your Answer:** *"We exclusively use PDO (PHP Data Objects) with prepared statements. Instead of concatenating variables directly into SQL strings, we use placeholders (like `?`) and bind inputs. This separates the SQL command logic from the user data, neutralizing SQL injection attempts."*

### Q2: How did you implement CSRF protection?
*   **Your Answer:** *"Every time a session starts, we generate a cryptographically secure random token and store it in `$_SESSION['csrf_token']`. In our forms, we embed this token as a hidden input. When the form is POSTed, we use `hash_equals()` to compare the submitted token against the session token. If they don't match, the request is rejected immediately."*

### Q3: Why did you use `FOR UPDATE` in database transactions?
*   **Your Answer:** *"If an employee has 2 remaining days, and submits two separate requests for 2 days each, two admins reviewing them at the same time could both see 2 days remaining and approve both. By using `SELECT ... FOR UPDATE` inside a database transaction, we lock that row. The second approval query is forced to wait until the first transaction commits and updates the balance, thereby preventing overdrafts."*

### Q4: How is XSS (Cross-Site Scripting) prevented?
*   **Your Answer:** *"We sanitize all dynamic outputs using `htmlspecialchars()` via our global helper function `e()`. This converts special characters like `<` and `>` into HTML entities, preventing any injected scripts in text fields (like leave reasons) from executing in the browser."*
