CREATE DATABASE IF NOT EXISTS leave_management;
USE leave_management;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    middle_name VARCHAR(100) NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('Employee', 'Admin') DEFAULT 'Employee',
    status ENUM('Pending', 'Active', 'Rejected') DEFAULT 'Pending',
    gender ENUM('Male', 'Female') NOT NULL DEFAULT 'Male',
    department VARCHAR(100) NULL,
    position VARCHAR(100) NULL
);

CREATE TABLE leave_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    leave_type VARCHAR(100) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    reason TEXT,
    status ENUM('Pending', 'Approved', 'Rejected') DEFAULT 'Pending',
    admin_comment TEXT NULL,
    attachment VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE leave_balances (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    leave_type VARCHAR(100) NOT NULL,
    total_allowed DECIMAL(5,2) DEFAULT 15.00,
    days_used DECIMAL(5,2) DEFAULT 0.00,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Insert dummy Admin and Employee for testing
-- Password is 'password123' hashed
INSERT INTO users (name, first_name, last_name, email, password, role, status, gender) VALUES 
('Admin User', 'Admin', 'User', 'admin@example.com', '$2y$10$ncJHiNsDIyMizzWDIw4Xsex3k7r5YUXsEhoxixoQavhshDzDH51yC', 'Admin', 'Active', 'Male'),
('John Doe', 'John', 'Doe', 'john@example.com', '$2y$10$ncJHiNsDIyMizzWDIw4Xsex3k7r5YUXsEhoxixoQavhshDzDH51yC', 'Employee', 'Active', 'Male');

-- Initialize balances for John Doe (10 leave types)
INSERT INTO leave_balances (user_id, leave_type, total_allowed, days_used) VALUES 
(2, 'Vacation Leave', 15.00, 0.00),
(2, 'Sick Leave', 10.00, 0.00),
(2, 'Emergency Leave', 5.00, 0.00),
(2, 'Maternity Leave', 105.00, 0.00),
(2, 'Paternity Leave', 7.00, 0.00),
(2, 'Bereavement Leave', 5.00, 0.00),
(2, 'Study Leave', 15.00, 0.00),
(2, 'Compensatory Leave', 0.00, 0.00),
(2, 'Unpaid Leave', 30.00, 0.00),
(2, 'Special Leave', 5.00, 0.00);
