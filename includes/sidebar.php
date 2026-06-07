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
                <a href="admin_dashboard.php">Dashboard</a>
            </li>
            <li class="<?php echo $current_page == 'manage_users.php' ? 'active' : ''; ?>">
                <a href="#">Manage Users</a> <!-- Placeholder for now -->
            </li>
        <?php else: ?>
            <li class="<?php echo $current_page == 'employee_dashboard.php' ? 'active' : ''; ?>">
                <a href="employee_dashboard.php">My Dashboard</a>
            </li>
            <li class="<?php echo $current_page == 'request_leave.php' ? 'active' : ''; ?>">
                <a href="request_leave.php">Request Leave</a>
            </li>
        <?php endif; ?>
        
        <li class="logout-link">
            <a href="logout.php">Logout</a>
        </li>
    </ul>
</div>
