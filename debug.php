<?php
require_once 'includes/config.php';

echo "<h2>System Debug Info</h2>";

// 1. Check Connection
try {
    $pdo->query("SELECT 1");
    echo "<p style='color: green;'>✔ Database Connection: OK</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>✘ Database Connection Failed: " . $e->getMessage() . "</p>";
}

// 2. Check Tables
$tables = ['users', 'leave_requests', 'leave_balances'];
foreach ($tables as $table) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM $table");
        $count = $stmt->fetchColumn();
        echo "<p style='color: green;'>✔ Table '$table' exists and has $count records.</p>";
    } catch (Exception $e) {
        echo "<p style='color: red;'>✘ Table '$table' error: " . $e->getMessage() . "</p>";
    }
}

// 3. Check Admin User Specifically
$email = 'admin@example.com';
$stmt = $pdo->prepare("SELECT id, email, password, role FROM users WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch();

if ($user) {
    echo "<p style='color: green;'>✔ User '$email' found in database.</p>";
    echo "<ul>
            <li>ID: {$user['id']}</li>
            <li>Role: {$user['role']}</li>
            <li>Hash starts with: " . substr($user['password'], 0, 10) . "...</li>
          </ul>";
    
    // 4. Test Password Hash manually
    $testPass = 'password123';
    if (password_verify($testPass, $user['password'])) {
        echo "<p style='color: green;'>✔ Password verification for 'password123' works!</p>";
    } else {
        echo "<p style='color: red;'>✘ Password verification failed for 'password123'. The hash in DB doesn't match this password.</p>";
    }
} else {
    echo "<p style='color: red;'>✘ User '$email' NOT found. Please run setup.php again.</p>";
}

echo "<hr><p>Please delete this <code>debug.php</code> file after checking.</p>";
?>
