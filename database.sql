CREATE DATABASE IF NOT EXISTS leave_management;
USE leave_management;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('Employee', 'Admin') DEFAULT 'Employee'
);

CREATE TABLE leave_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    leave_type ENUM('Vacation', 'Sick Leave', 'Unpaid') NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    reason TEXT,
    status ENUM('Pending', 'Approved', 'Rejected') DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE leave_balances (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    leave_type ENUM('Vacation', 'Sick Leave', 'Unpaid') NOT NULL,
    total_allowed INT DEFAULT 15,
    days_used INT DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Insert dummy Admin and Employee for testing
-- Password is 'password123' hashed
INSERT INTO users (name, email, password, role) VALUES 
('Admin User', 'admin@example.com', '$2y$10$ncJHiNsDIyMizzWDIw4Xsex3k7r5YUXsEhoxixoQavhshDzDH51yC', 'Admin'),
('John Doe', 'john@example.com', '$2y$10$ncJHiNsDIyMizzWDIw4Xsex3k7r5YUXsEhoxixoQavhshDzDH51yC', 'Employee');

-- Initialize balances for John Doe
INSERT INTO leave_balances (user_id, leave_type, total_allowed, days_used) VALUES 
(2, 'Vacation', 15, 0),
(2, 'Sick Leave', 10, 0),
(2, 'Unpaid', 30, 0);
