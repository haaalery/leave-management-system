<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/auth_check.php';
checkLogin();

header('Content-Type: application/json');

$role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];

$dept_id = 0;
if ($role === 'Manager') {
    $stmt = $pdo->prepare("SELECT id FROM departments WHERE manager_id = ?");
    $stmt->execute([$user_id]);
    $dept_id = (int)$stmt->fetchColumn();
} elseif ($role === 'Employee') {
    $stmt = $pdo->prepare("SELECT department_id FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $dept_id = (int)$stmt->fetchColumn();
}

if ($role === 'Admin') {
    $stmt = $pdo->query("
        SELECT lr.leave_type, lr.start_date, lr.end_date, u.name AS employee_name
        FROM leave_requests lr
        JOIN users u ON lr.user_id = u.id
        WHERE lr.status = 'Approved'
    ");
} else {
    if ($dept_id) {
        $stmt = $pdo->prepare("
            SELECT lr.leave_type, lr.start_date, lr.end_date, u.name AS employee_name
            FROM leave_requests lr
            JOIN users u ON lr.user_id = u.id
            WHERE lr.status = 'Approved' AND u.department_id = ?
        ");
        $stmt->execute([$dept_id]);
    } else {
        echo json_encode([]);
        exit();
    }
}

$leaves = $stmt->fetchAll();
$events = [];

$colors = [
    'Vacation Leave' => '#10b981',
    'Sick Leave' => '#ef4444',
    'Emergency Leave' => '#f59e0b',
    'Maternity Leave' => '#ec4899',
    'Paternity Leave' => '#06b6d4',
    'Bereavement Leave' => '#6b7280',
    'Study Leave' => '#8b5cf6',
    'Compensatory Leave' => '#3b82f6',
    'Unpaid Leave' => '#64748b',
    'Special Leave' => '#a855f7'
];

foreach ($leaves as $l) {
    $end = new DateTime($l['end_date']);
    $end->modify('+1 day');
    
    $events[] = [
        'title' => $l['employee_name'] . ' (' . $l['leave_type'] . ')',
        'start' => $l['start_date'],
        'end' => $end->format('Y-m-d'),
        'backgroundColor' => $colors[$l['leave_type']] ?? '#4f46e5',
        'borderColor' => $colors[$l['leave_type']] ?? '#4f46e5',
        'allDay' => true
    ];
}

echo json_encode($events);
exit();
?>
