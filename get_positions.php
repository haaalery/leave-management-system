<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/auth_check.php';
checkLogin();

header('Content-Type: application/json');

$dept_id = (int)($_GET['dept_id'] ?? 0);
if (!$dept_id) {
    echo json_encode([]);
    exit;
}

$stmt = $pdo->prepare("SELECT id, title FROM positions WHERE department_id = ? ORDER BY title ASC");
$stmt->execute([$dept_id]);
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
