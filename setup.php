<?php
$host = 'localhost';
$username = 'root';
$password = ''; 

try {
    $pdo = new PDO("mysql:host=$host", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create Database
    $pdo->exec("CREATE DATABASE IF NOT EXISTS leave_management");
    $pdo->exec("USE leave_management");

    // Create Tables
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(100) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        role ENUM('Employee', 'Admin') DEFAULT 'Employee'
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS leave_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        leave_type ENUM('Vacation', 'Sick Leave', 'Unpaid') NOT NULL,
        start_date DATE NOT NULL,
        end_date DATE NOT NULL,
        reason TEXT,
        status ENUM('Pending', 'Approved', 'Rejected') DEFAULT 'Pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS leave_balances (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        leave_type ENUM('Vacation', 'Sick Leave', 'Unpaid') NOT NULL,
        total_allowed INT DEFAULT 15,
        days_used INT DEFAULT 0,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");

    // Clear old data for a fresh setup
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    $pdo->exec("TRUNCATE TABLE leave_balances");
    $pdo->exec("TRUNCATE TABLE leave_requests");
    $pdo->exec("TRUNCATE TABLE users");
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

    // Insert dummy data
    $passHash = password_hash('password123', PASSWORD_DEFAULT);
    
    $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
    $stmt->execute(['Admin User', 'admin@example.com', $passHash, 'Admin']);
    $stmt->execute(['John Doe', 'john@example.com', $passHash, 'Employee']);
    
    $johnId = $pdo->lastInsertId();

    $stmt = $pdo->prepare("INSERT INTO leave_balances (user_id, leave_type, total_allowed, days_used) VALUES (?, ?, ?, ?)");
    $stmt->execute([$johnId, 'Vacation', 15, 0]);
    $stmt->execute([$johnId, 'Sick Leave', 10, 0]);
    $stmt->execute([$johnId, 'Unpaid', 30, 0]);

    echo "<h2 style='color: green;'>Success! Database and dummy accounts created.</h2>";
    echo "<p>You can now log in at <a href='login.php'>login.php</a></p>";
    echo "<ul><li><strong>Admin:</strong> admin@example.com / password123</li>";
    echo "<li><strong>Employee:</strong> john@example.com / password123</li></ul>";
    echo "<p style='color: red;'><strong>Note:</strong> Please delete this <code>setup.php</code> file for security.</p>";

} catch (PDOException $e) {
    die("<h2 style='color: red;'>Setup Failed:</h2> " . $e->getMessage());
}
?>
