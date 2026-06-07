# Leave Management System (LMS) - Project Plan

A simple, minimalist Leave Management System built with PHP and MySQL for XAMPP.

## 1. Core Features
*   **Leave Request Form:** Submit leave requests (Type, Dates, Reason).
*   **Approver Dashboard:** Admin view to Approve/Deny requests.
*   **Leave Balance Tracker:** View remaining leave days.
*   **Status Notifications:** Simple alerts/status updates for employees.

## 2. Database Schema (MySQL)

### Users Table
| Column | Type | Description |
| :--- | :--- | :--- |
| id | INT (PK) | Unique User ID |
| name | VARCHAR | Full Name |
| email | VARCHAR | Email address (Login) |
| password | VARCHAR | Hashed password |
| role | ENUM | 'Employee' or 'Admin' |

### Leave Requests Table
| Column | Type | Description |
| :--- | :--- | :--- |
| id | INT (PK) | Unique Request ID |
| user_id | INT (FK) | ID of the employee |
| leave_type | VARCHAR | e.g., Vacation, Sick, Unpaid |
| start_date | DATE | Start of leave |
| end_date | DATE | End of leave |
| status | ENUM | 'Pending', 'Approved', 'Rejected' |
| reason | TEXT | Optional reason |

### Leave Balances Table
| Column | Type | Description |
| :--- | :--- | :--- |
| id | INT (PK) | Unique ID |
| user_id | INT (FK) | ID of the employee |
| leave_type | VARCHAR | Type of leave |
| total_allowed | INT | Max days allowed per year |
| days_used | INT | Total days taken |

---

## 3. Implementation Checklist

### Phase 1: Environment & Database Setup
- [x] Initialize project directory structure.
- [x] Create MySQL database and tables.
- [x] Set up database connection configuration (PHP).

### Phase 2: Authentication System
- [x] Create Login page.
- [x] Implement Session management.
- [x] Create Logout functionality.

### Phase 3: Employee Features
- [x] Create Leave Request Form.
- [x] Implement logic to calculate leave days and check balance.
- [x] Display Leave Balance on the employee dashboard.
- [x] List user's previous requests and their statuses.

### Phase 4: Admin Features
- [x] Create Admin Dashboard to view all pending requests.
- [x] Implement Approve/Deny functionality.
- [x] Add simple user management (view users).

### Phase 5: UI/UX & Final Touches
- [x] Apply basic CSS styling for a clean look.
- [x] Add simple notifications/alerts for status changes.
- [x] Final testing and bug fixes.
