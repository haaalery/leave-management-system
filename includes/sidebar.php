<?php
// Determine the current page to highlight active link
$current_page = basename($_SERVER['PHP_SELF']);
$role = $_SESSION['role'] ?? '';
?>
<div class="sidebar">
    <div class="sidebar-header">
        <h3>LMS</h3>
    </div>
    <ul class="sidebar-menu">
        <?php if ($role === 'Admin'): ?>
            <li class="<?php echo $current_page == 'admin_dashboard.php' ? 'active' : ''; ?>">
                <a href="admin_dashboard.php"><i class="fas fa-fw fa-tachometer-alt"></i> Dashboard</a>
            </li>
            <li class="<?php echo $current_page == 'manage_users.php' ? 'active' : ''; ?>">
                <a href="manage_users.php"><i class="fas fa-fw fa-users"></i> Manage Users</a>
            </li>
            <li class="<?php echo $current_page == 'manage_departments.php' ? 'active' : ''; ?>">
                <a href="manage_departments.php"><i class="fas fa-fw fa-building"></i> Departments</a>
            </li>
            <li class="<?php echo $current_page == 'manage_positions.php' ? 'active' : ''; ?>">
                <a href="manage_positions.php"><i class="fas fa-fw fa-briefcase"></i> Positions</a>
            </li>
            <li class="<?php echo $current_page == 'pending_registrations.php' ? 'active' : ''; ?>">
                <a href="pending_registrations.php"><i class="fas fa-fw fa-user-clock"></i> Registrations</a>
            </li>
            <li class="<?php echo $current_page == 'leave_requests.php' ? 'active' : ''; ?>">
                <a href="leave_requests.php"><i class="fas fa-fw fa-calendar-alt"></i> Leave Requests</a>
            </li>
            <li class="<?php echo $current_page == 'calendar.php' ? 'active' : ''; ?>">
                <a href="calendar.php"><i class="fas fa-fw fa-calendar-alt"></i> Calendar</a>
            </li>
            <li class="<?php echo $current_page == 'reports.php' ? 'active' : ''; ?>">
                <a href="reports.php"><i class="fas fa-fw fa-file-invoice"></i> Reports</a>
            </li>
            <li class="<?php echo $current_page == 'audit_logs.php' ? 'active' : ''; ?>">
                <a href="audit_logs.php"><i class="fas fa-fw fa-history"></i> Audit Logs</a>
            </li>
        <?php elseif ($role === 'Manager'): ?>
            <li class="<?php echo $current_page == 'manager_dashboard.php' ? 'active' : ''; ?>">
                <a href="manager_dashboard.php"><i class="fas fa-fw fa-user-tie"></i> Dashboard</a>
            </li>
            <li class="<?php echo $current_page == 'calendar.php' ? 'active' : ''; ?>">
                <a href="calendar.php"><i class="fas fa-fw fa-calendar-alt"></i> Calendar</a>
            </li>
            <li class="<?php echo $current_page == 'reports.php' ? 'active' : ''; ?>">
                <a href="reports.php"><i class="fas fa-fw fa-file-invoice"></i> Reports</a>
            </li>
        <?php else: ?>
            <li class="<?php echo $current_page == 'employee_dashboard.php' ? 'active' : ''; ?>">
                <a href="employee_dashboard.php"><i class="fas fa-fw fa-home"></i> My Dashboard</a>
            </li>
            <li class="<?php echo $current_page == 'request_leave.php' ? 'active' : ''; ?>">
                <a href="request_leave.php"><i class="fas fa-fw fa-paper-plane"></i> Request Leave</a>
            </li>
            <li class="<?php echo $current_page == 'calendar.php' ? 'active' : ''; ?>">
                <a href="calendar.php"><i class="fas fa-fw fa-calendar-alt"></i> Calendar</a>
            </li>
            <li class="<?php echo $current_page == 'my_profile.php' ? 'active' : ''; ?>">
                <a href="my_profile.php"><i class="fas fa-fw fa-user-circle"></i> My Profile</a>
            </li>
        <?php endif; ?>
        
        <li class="logout-link">
            <a href="logout.php"><i class="fas fa-fw fa-sign-out-alt"></i> Logout</a>
        </li>
    </ul>
</div>
