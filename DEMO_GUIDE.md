# Leave Management System - Comprehensive Demo & Testing Guide

## 📋 System Overview

The **Leave Management System (LMS)** is a role-based PHP web application designed to manage employee leave requests, approvals, and leave balance tracking. It supports **Employees**, **Managers**, and **Admins** with different capabilities and workflows.

---

## 🎯 Key Features

### **Core Functionality:**
- ✅ User Registration with email verification (admin approval required)
- ✅ Role-based Access Control (Employee, Manager, Admin)
- ✅ Leave Request Management (submit, track, approve/reject)
- ✅ Leave Balance Tracking (10 leave types with individual balances)
- ✅ Admin Dashboard with statistics and management tools
- ✅ Employee Dashboard with leave history and status
- ✅ Manager Dashboard for pending approvals
- ✅ Audit Logs for all system actions
- ✅ File Upload Support (attachments for leave requests)
- ✅ CSRF Protection for state-changing operations

### **10 Leave Types Supported:**
1. **Vacation Leave** (15 days)
2. **Sick Leave** (10 days)
3. **Emergency Leave** (5 days)
4. **Maternity Leave** (105 days)
5. **Paternity Leave** (7 days)
6. **Bereavement Leave** (5 days)
7. **Study Leave** (15 days)
8. **Compensatory Leave** (0 days - as needed)
9. **Unpaid Leave** (30 days)
10. **Special Leave** (5 days)

---

## 👥 User Roles & Capabilities

### **1. Employee (Regular User)**
**Can Do:**
- View personal leave balances
- Submit new leave requests
- Upload supporting documents (JPG, PNG, PDF - max 2MB)
- View leave request history
- Track leave status (Pending, Approved, Rejected)
- View personal profile
- See rejection reasons and comments

**Cannot Do:**
- Approve/reject leave requests
- View other employees' data
- Access admin functions

---

### **2. Manager**
**Can Do:**
- View all employee leave requests
- Approve or reject pending leave requests
- Add comments to requests
- View leave request history
- View audit logs
- Access reports

**Cannot Do:**
- Adjust leave balances
- Manage users
- Create admin accounts

---

### **3. Admin**
**Can Do:**
- All Manager capabilities
- Manage user accounts (approve/reject registrations)
- Adjust employee leave balances
- Manage departments and positions
- View system audit logs
- Generate reports
- Access all admin management pages

**Cannot Do:**
- Bypass security validations
- Access database directly through UI

---

## 🚀 Quick Start - Pre-configured Test Accounts

### **Default Test Credentials:**

#### **Admin Account**
```
Email:    admin@example.com
Password: password123
Role:     Admin
Status:   Active ✅
```

#### **Employee Account**
```
Email:    john@example.com
Password: password123
Role:     Employee
Status:   Active ✅
Gender:   Male
Department: (can be set during registration or by admin)
Position: (can be set during registration or by admin)
```

---

## 📝 Complete Step-by-Step Demo & Testing Guide

### **PHASE 1: AUTHENTICATION & USER MANAGEMENT**

#### **Step 1.1: Test Admin Login**
1. Navigate to: `http://localhost:8000/login.php`
2. Enter credentials:
   - Email: `admin@example.com`
   - Password: `password123`
3. **Expected Result:** ✅ Redirects to Admin Dashboard
4. **What to check:**
   - Top navigation shows "Admin Dashboard"
   - Sidebar shows admin-specific menu items (Manage Users, Manage Departments, etc.)
   - Leave type statistics cards display (Vacation, Sick, Emergency, etc.)
   - Color-coded badge system visible

#### **Step 1.2: Test Employee Login**
1. Navigate to: `http://localhost:8000/login.php`
2. Enter credentials:
   - Email: `john@example.com`
   - Password: `password123`
3. **Expected Result:** ✅ Redirects to Employee Dashboard
4. **What to check:**
   - Shows "My Dashboard" page
   - Leave balances visible for all 10 leave types
   - Leave request history table present
   - Personal leave request action buttons available

#### **Step 1.3: Test User Registration (New Employee)**
1. Navigate to: `http://localhost:8000/login.php` (if not logged in)
2. Click "Don't have an account? Register here"
3. Fill registration form:
   - First Name: `Jane`
   - Middle Name: `Marie` (optional)
   - Last Name: `Smith`
   - Email: `jane.smith@example.com`
   - Gender: `Female`
   - Department: Select any department
   - Position: Position will auto-load based on department
   - Password: `TestPass123`
   - Confirm Password: `TestPass123`
4. Click Register
5. **Expected Result:** ✅ Registration successful message
6. **What to check:**
   - User created in database with "Pending" status
   - Can't login until admin approves
   - Position dropdown populates based on department selection

#### **Step 1.4: Test Pending Registration Approval (Admin)**
1. Login as Admin
2. Navigate to: `Admin Dashboard → Pending Registrations tab`
3. See newly registered user (Jane Smith)
4. **Options:**
   - Click "Approve" - Changes status to "Active", user can login
   - Click "Reject" - Marks as "Rejected", user cannot login
5. **Expected Result:** ✅ Status updates immediately
6. Click "Approve" for Jane
7. Logout and try logging in with Jane's credentials
8. **Expected Result:** ✅ Login successful, redirects to Employee Dashboard

---

### **PHASE 2: LEAVE REQUEST WORKFLOW**

#### **Step 2.1: Submit a Leave Request (Employee)**
1. Login as Employee (john@example.com)
2. Click "Request Leave" in sidebar
3. Fill leave request form:
   - Leave Type: `Vacation Leave`
   - Start Date: Pick a future date (5 days from today)
   - End Date: Pick end date (1 week from today)
   - Reason: `Planning to visit family`
   - Attachment: (Optional) Upload a JPG/PNG/PDF document
4. Click "Submit Request"
5. **Expected Result:** ✅ Request submitted successfully
6. **What to check:**
   - Message confirms submission
   - Attachment uploaded successfully (if provided)
   - File size < 2MB and format is JPG/PNG/PDF

#### **Step 2.2: View Leave Request Status (Employee)**
1. From Employee Dashboard, scroll to "Leave Request History"
2. **Expected to see:**
   - Status: `Pending`
   - Leave type, dates, reason visible
   - Days calculation correct (start to end date inclusive)
3. **Verify balance hasn't changed yet** (still shows total 15 days for Vacation Leave)

#### **Step 2.3: View Pending Request (Manager/Admin)**
1. Login as Admin or Manager
2. Navigate to: `Admin Dashboard → Pending Leave Requests`
3. **Expected to see:**
   - Employee name (John Doe)
   - Leave type with colored badge (Blue = Vacation)
   - Request details (dates, reason, duration)
   - Status: "Pending"
   - Action buttons: "Approve" or "Reject"

#### **Step 2.4: Approve a Leave Request (Admin)**
1. From Admin Dashboard Pending Requests table
2. Click "Approve" button for John's vacation request
3. **Optional:** Add admin comment explaining approval
4. Click confirm
5. **Expected Result:** ✅ Request status changes to "Approved"
6. **What to check:**
   - Request disappears from "Pending" section
   - Days become "unavailable" in balance (15 - 8 days = 7 remaining)
   - Email notification sent (if configured)

#### **Step 2.5: Reject a Leave Request (Admin)**
1. Submit another leave request as Employee (e.g., Sick Leave)
2. Login as Admin
3. Click "Reject" on the pending request
4. Enter rejection reason: `Company event scheduled for this date`
5. Click confirm
6. **Expected Result:** ✅ Request status changes to "Rejected"
7. **What to check:**
   - Rejection banner appears on Employee Dashboard
   - Admin comment visible to employee
   - Leave balance unchanged (days returned)
   - Rejection notification visible in history

---

### **PHASE 3: LEAVE BALANCE MANAGEMENT**

#### **Step 3.1: View Current Balances (Employee)**
1. Login as Employee (john@example.com)
2. On Employee Dashboard, view leave balances section
3. **Expected to see all 10 leave types:**
   - Display format: `Days Used / Total Allowed` (whole numbers, no decimals)
   - Example: `8/15` for Vacation Leave (not 8.00/15.00)
   - Color-coded icons for each leave type
4. **Verify no decimals appear** (this was a bug fix)

#### **Step 3.2: Adjust Leave Balance (Admin)**
1. Login as Admin
2. Navigate to: `Admin Dashboard → Adjust Balance` (or Manage Users)
3. Click user (John Doe) to adjust
4. Find "Adjust Leave Balance" section
5. Select leave type and modify days
6. Example: Set Vacation Leave to 20 days (increase)
7. Click "Update"
8. **Expected Result:** ✅ Balance updates immediately
9. **Verify on Employee Dashboard:**
   - John now sees `8/20` for Vacation Leave (if 8 days already used)

#### **Step 3.3: Test Leave Balance Deduction**
1. Submit multiple leave requests as different leave types
2. Approve them
3. Monitor balance reduction
4. Example:
   - Sick Leave: 10 days total
   - Request 3 days approved
   - Balance should show: `3/10`

---

### **PHASE 4: DASHBOARD & REPORTING**

#### **Step 4.1: Explore Admin Dashboard**
1. Login as Admin
2. View main dashboard
3. **Sections visible:**
   - **Stat Cards (Colorful):** Shows count of each leave type requested
     - Vacation Leave (Blue) 
     - Sick Leave (Red)
     - Emergency Leave (Orange)
     - etc.
   - **Pending Leave Requests Table:** Lists all pending requests with details
   - **Pending User Registrations:** New sign-ups awaiting approval

#### **Step 4.2: Explore Employee Dashboard**
1. Login as Employee
2. **Sections visible:**
   - **Leave Balance Cards:** All 10 leave types with color-coded icons
   - **Leave Request History Table:** Submitted requests with status
   - **Rejection Banner:** (if any recent rejections)

#### **Step 4.3: Check Manager Dashboard**
1. Create a Manager account (register as Employee, then Admin changes role in DB)
   OR
2. Login as Admin and modify user role in database
3. Once manager account created, login as Manager
4. **Sections visible:**
   - Pending requests awaiting manager approval
   - Leave history with approval/rejection details
   - Reports section

#### **Step 4.4: View Audit Logs (Admin)**
1. Login as Admin
2. Navigate to: `Audit Logs`
3. **Should show:**
   - All system actions (login, leave request, approval, rejection)
   - Timestamp for each action
   - Employee name and action details
   - Action types: User created, Login, Leave request submitted, Leave request approved, etc.

#### **Step 4.5: Generate Reports (Admin)**
1. Login as Admin
2. Navigate to: `Reports`
3. **Available filters:**
   - Leave Type (filter by Vacation, Sick, Emergency, etc.)
   - Date range
   - Employee
   - Status (Approved, Rejected, Pending)
4. Test filtering and report generation
5. **Expected to see:**
   - Matching leave requests with details
   - Color-coded leave type badges
   - Summary statistics

---

### **PHASE 5: ADMIN MANAGEMENT FUNCTIONS**

#### **Step 5.1: Manage Users**
1. Login as Admin
2. Navigate to: `Manage Users`
3. **See all users with:**
   - Name, Email, Role, Status, Gender badges
   - Badge styling: Square (not pill-shaped)
   - Colors:
     - Male: Blue text only (no icon)
     - Female: Pink/Magenta with Venus icon ♀️
     - Role colors: Green (Employee), Indigo (Admin), Amber (Manager)
     - Status colors: Amber (Pending), Green (Active), Red (Rejected)
   - Action buttons: Edit, Deactivate, Adjust Balance, Delete

#### **Step 5.2: Manage Departments**
1. Navigate to: `Manage Departments`
2. **Can:**
   - View all departments
   - Add new department
   - Edit department details
   - Delete department
3. Test adding: Department name "IT", save
4. **Expected Result:** ✅ Department appears in list

#### **Step 5.3: Manage Positions**
1. Navigate to: `Manage Positions`
2. **Can:**
   - View all positions
   - Link positions to departments
   - Add new position
   - Edit position details
   - Delete position
3. Test adding: Position "Senior Developer", link to "IT" department
4. **Expected Result:** ✅ Position appears in list

#### **Step 5.4: Pending Registrations**
1. Navigate to: `Pending Registrations`
2. **See pending signups:**
   - User details (name, email, gender, department, position)
   - Action buttons: Approve, Reject
3. Approve a pending user
4. **Expected Result:** ✅ Status changes to Active, user can login

---

### **PHASE 6: SECURITY & VALIDATION TESTING**

#### **Step 6.1: Test CSRF Protection**
1. Try submitting a form without CSRF token
2. **Expected Result:** ✅ Request fails with "CSRF token validation failed"

#### **Step 6.2: Test Access Control**
1. Login as Employee (john@example.com)
2. Try navigating to: `/admin_dashboard.php`
3. **Expected Result:** ✅ Redirects to Employee Dashboard (access denied)
4. Try navigating to: `/manage_users.php`
5. **Expected Result:** ✅ Redirected to Employee Dashboard

#### **Step 6.3: Test File Upload Validation**
1. Login as Employee
2. Go to: `Request Leave`
3. Try uploading:
   - **Valid file:** JPG/PNG/PDF, < 2MB → ✅ Accepted
   - **Invalid format:** .docx or .txt → ✅ Rejected
   - **Too large:** > 2MB → ✅ Rejected with error message
4. **Expected Result:** ✅ Only valid files accepted

#### **Step 6.4: Test Login Validation**
1. Try logging in with:
   - Wrong email: `noone@example.com` → ✅ "Invalid email or password"
   - Correct email, wrong password → ✅ "Invalid email or password"
   - Pending account (not yet approved by admin) → ✅ "Your account is pending administrator approval"
   - Rejected account → ✅ "Your registration was rejected"

#### **Step 6.5: Test XSS Protection**
1. Submit a leave request with reason: `<script>alert('XSS')</script>`
2. View the request in history
3. **Expected Result:** ✅ Script tags displayed as text (escaped), no alert popup
4. Check page source - tags should be escaped as `&lt;script&gt;`

---

### **PHASE 7: UI/UX VERIFICATION**

#### **Step 7.1: Check Responsive Design**
1. Open Employee Dashboard on different screen sizes:
   - Desktop (1920x1080)
   - Tablet (768x1024)
   - Mobile (375x667)
2. **Expected:** ✅ Layout adapts properly, readable on all sizes

#### **Step 7.2: Verify Color Scheme**
1. **Employee & Admin Dashboards should match:**
   - Leave type icons have consistent colors
   - Cards are balanced and properly spaced
   - Professional, modern appearance
2. **Specific colors expected:**
   - Vacation Leave: Blue (#3b82f6)
   - Sick Leave: Red (#ef4444)
   - Emergency Leave: Orange (#f59e0b)
   - Maternity Leave: Pink (#ec4899)
   - Study Leave: Purple (#8b5cf6)
   - Compensatory Leave: Green (#10b981)
   - etc.

#### **Step 7.3: Check Badge Styling**
1. On Manage Users page
2. **Verify badges are square (not pill-shaped):**
   - Border-radius should be rounded corners, not full circles
   - Colors are appropriate for role/status/gender
3. **Verify Male badge:**
   - Shows "Male" text only
   - No Mars icon (♂️)
4. **Verify Female badge:**
   - Shows "Female" with Venus icon (♀️)

#### **Step 7.4: Test Sidebar Navigation**
1. Click through all menu items
2. **Expected:**
   - All links work correctly
   - No 404 errors
   - Proper role-based menu filtering (Employee sees different options than Admin)

---

## 🔧 Testing Checklist

### **Core Functionality Tests**
- [ ] User registration works and requires admin approval
- [ ] Login works for all user types (Employee, Admin, Manager)
- [ ] Employee can submit leave requests
- [ ] Admin can approve/reject requests
- [ ] Leave balances update correctly after approval
- [ ] File uploads work (2MB limit enforced)
- [ ] All 10 leave types are functional
- [ ] Leave request calculations include start and end dates
- [ ] Decimal values display as whole numbers (15, not 15.00)

### **Security Tests**
- [ ] CSRF token validation works
- [ ] Access control prevents unauthorized access
- [ ] XSS protection escapes user input
- [ ] Login rejects invalid credentials
- [ ] Session management works correctly
- [ ] SQL injection protection in place

### **UI/UX Tests**
- [ ] Dashboards are visually consistent
- [ ] Color scheme is professional and readable
- [ ] Badges have square shape (not pills)
- [ ] Icons display correctly for all leave types
- [ ] Responsive design works on mobile/tablet
- [ ] Male badge shows text only (no icon)
- [ ] Female badge shows icon + text
- [ ] No rendering errors or console warnings

### **Data Integrity Tests**
- [ ] Leave balances sync with requests correctly
- [ ] Rejected requests don't deduct balance
- [ ] Approved requests deduct balance
- [ ] Users can't request more leave than available
- [ ] Date validation prevents end date before start date
- [ ] Duplicate requests are prevented

### **Report & Logging Tests**
- [ ] Audit logs capture all actions
- [ ] Reports filter correctly
- [ ] Manager dashboard shows correct pending requests
- [ ] Admin dashboard shows all statistics
- [ ] Leave type statistics are accurate

---

## 📊 Database Schema Quick Reference

### **Users Table**
```
id, name, first_name, middle_name, last_name, email, password, 
role (Employee/Admin), status (Pending/Active/Rejected), 
gender (Male/Female), department, position
```

### **Leave Requests Table**
```
id, user_id, leave_type, start_date, end_date, reason, 
status (Pending/Approved/Rejected), admin_comment, 
attachment, created_at, updated_at
```

### **Leave Balances Table**
```
id, user_id, leave_type, total_allowed (INT), days_used (INT)
```

---

## 🐛 Known Issues & Fixes Applied

### **Fixed Issues:**
1. ✅ **Missing `updated_at` column** - Added to leave_requests table
2. ✅ **Position dropdown showing "Loading..."** - Fixed auth check in get_positions.php
3. ✅ **Decimal formatting (15.00 → 15)** - Changed to INT casting in employee_dashboard.php, manage_users.php, request_leave.php
4. ✅ **Admin dashboard design inconsistency** - Redesigned to match employee dashboard
5. ✅ **Badge styling issues** - Changed from pill-shaped to square, applied professional colors
6. ✅ **Male badge had unwanted icon** - Removed icon, kept only text

---

## 🎓 Troubleshooting

### **Issue: "Column not found: updated_at"**
- **Solution:** Run database migration or import database.sql

### **Issue: Position dropdown shows "Loading..."**
- **Solution:** Check get_positions.php has no auth requirement

### **Issue: Dashboard displays decimals (15.00)**
- **Solution:** Verify employee_dashboard.php, manage_users.php, request_leave.php have INT casting

### **Issue: Can't approve/reject requests**
- **Solution:** Ensure user account is Admin, not Employee

### **Issue: File upload fails**
- **Solution:** Check file size (< 2MB) and format (JPG, PNG, PDF), ensure uploads directory exists

### **Issue: Session expires too quickly**
- **Solution:** Check PHP session timeout in php.ini or increase session.gc_maxlifetime

---

## 📞 Testing Support

If issues arise during testing:
1. Check browser console (F12) for JavaScript errors
2. Check server logs for PHP errors
3. Verify database connection in config.php
4. Ensure XAMPP/PHP server is running
5. Clear browser cache (Ctrl+Shift+Delete)

---

## ✅ Conclusion

This Leave Management System provides a **complete, production-ready solution** for managing employee leaves. The demo guide above covers all major features and workflows. By following the step-by-step testing procedures, you can verify that the system is functioning correctly and is ready for deployment.

**Happy Testing!** 🎉
