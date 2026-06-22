-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 22, 2026 at 07:39 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `leave_management`
--

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL,
  `actor_id` int(11) NOT NULL,
  `actor_name` varchar(255) NOT NULL,
  `action` varchar(100) NOT NULL,
  `target_type` varchar(50) NOT NULL,
  `target_id` int(11) NOT NULL,
  `details` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `actor_id`, `actor_name`, `action`, `target_type`, `target_id`, `details`, `created_at`) VALUES
(1, 0, 'System (Historical)', 'Leave Approved', 'leave_request', 1, '{\"employee\":2,\"leave_type\":\"Sick Leave\",\"start_date\":\"2026-06-21\",\"end_date\":\"2026-06-28\",\"comment\":\"\"}', '2026-06-15 12:10:27'),
(2, 0, 'System (Historical)', 'Leave Rejected', 'leave_request', 2, '{\"employee\":2,\"leave_type\":\"Vacation Leave\",\"start_date\":\"2026-06-15\",\"end_date\":\"2026-06-20\",\"comment\":\"\"}', '2026-06-22 09:58:04'),
(3, 0, 'System (Historical)', 'Registration Approved', 'user', 2, '{\"user_name\":\"John Doe\",\"email\":\"john@example.com\"}', '2026-06-22 09:58:04'),
(4, 0, 'System (Historical)', 'Registration Approved', 'user', 3, '{\"user_name\":\"ANDIE MAHINAY ENTONG\",\"email\":\"entong@gmail.com\"}', '2026-06-22 09:58:04'),
(5, 0, 'System (Historical)', 'Registration Rejected', 'user', 4, '{\"user_name\":\"JOHN ROZILLER GOMISONG\",\"email\":\"gomisong@gmail.com\"}', '2026-06-22 09:58:04'),
(6, 1, 'Admin User', 'Registration Approved', 'user', 6, '{\"user_name\":\"KITT HARLEY BILLONES SY\",\"email\":\"kittharleysy@gmail.com\"}', '2026-06-22 10:04:53'),
(7, 1, 'Admin User', 'Registration Approved', 'user', 7, '{\"user_name\":\"JOBERT SINTOY\",\"email\":\"sintoy@gmail.com\"}', '2026-06-22 13:18:34');

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `manager_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`id`, `name`, `description`, `manager_id`, `created_at`) VALUES
(1, 'Engineering', 'Office 1', 8, '2026-06-22 09:58:04'),
(2, 'IT', NULL, NULL, '2026-06-22 09:58:04'),
(3, 'CS', NULL, NULL, '2026-06-22 09:58:04');

-- --------------------------------------------------------

--
-- Table structure for table `leave_balances`
--

CREATE TABLE `leave_balances` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `leave_type` varchar(100) NOT NULL,
  `total_allowed` decimal(5,2) DEFAULT 15.00,
  `days_used` decimal(5,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `leave_balances`
--

INSERT INTO `leave_balances` (`id`, `user_id`, `leave_type`, `total_allowed`, `days_used`) VALUES
(1, 2, 'Vacation Leave', 16.25, 0.00),
(2, 2, 'Sick Leave', 10.00, 0.00),
(3, 2, 'Unpaid Leave', 30.00, 0.00),
(4, 3, 'Vacation Leave', 15.00, 0.00),
(5, 3, 'Sick Leave', 10.00, 0.00),
(6, 3, 'Unpaid Leave', 30.00, 0.00),
(7, 2, 'Emergency Leave', 5.00, 0.00),
(8, 2, 'Maternity Leave', 105.00, 0.00),
(9, 2, 'Paternity Leave', 7.00, 0.00),
(10, 2, 'Bereavement Leave', 5.00, 0.00),
(11, 2, 'Study Leave', 15.00, 0.00),
(12, 2, 'Compensatory Leave', 0.00, 0.00),
(13, 2, 'Special Leave', 5.00, 0.00),
(14, 3, 'Emergency Leave', 5.00, 0.00),
(15, 3, 'Maternity Leave', 105.00, 0.00),
(16, 3, 'Paternity Leave', 7.00, 0.00),
(17, 3, 'Bereavement Leave', 5.00, 0.00),
(18, 3, 'Study Leave', 15.00, 0.00),
(19, 3, 'Compensatory Leave', 0.00, 0.00),
(20, 3, 'Special Leave', 5.00, 0.00),
(21, 4, 'Vacation Leave', 15.00, 0.00),
(22, 4, 'Sick Leave', 10.00, 0.00),
(23, 4, 'Emergency Leave', 5.00, 0.00),
(24, 4, 'Maternity Leave', 105.00, 0.00),
(25, 4, 'Paternity Leave', 7.00, 0.00),
(26, 4, 'Bereavement Leave', 5.00, 0.00),
(27, 4, 'Study Leave', 15.00, 0.00),
(28, 4, 'Compensatory Leave', 0.00, 0.00),
(29, 4, 'Unpaid Leave', 30.00, 0.00),
(30, 4, 'Special Leave', 5.00, 0.00),
(31, 5, 'Vacation Leave', 15.00, 0.00),
(32, 5, 'Sick Leave', 10.00, 0.00),
(33, 5, 'Emergency Leave', 5.00, 0.00),
(34, 5, 'Maternity Leave', 105.00, 0.00),
(35, 5, 'Paternity Leave', 7.00, 0.00),
(36, 5, 'Bereavement Leave', 5.00, 0.00),
(37, 5, 'Study Leave', 15.00, 0.00),
(38, 5, 'Compensatory Leave', 0.00, 0.00),
(39, 5, 'Unpaid Leave', 30.00, 0.00),
(40, 5, 'Special Leave', 5.00, 0.00),
(41, 6, 'Vacation Leave', 15.00, 0.00),
(42, 6, 'Sick Leave', 10.00, 0.00),
(43, 6, 'Emergency Leave', 5.00, 0.00),
(44, 6, 'Maternity Leave', 105.00, 0.00),
(45, 6, 'Paternity Leave', 7.00, 0.00),
(46, 6, 'Bereavement Leave', 5.00, 0.00),
(47, 6, 'Study Leave', 15.00, 0.00),
(48, 6, 'Compensatory Leave', 0.00, 0.00),
(49, 6, 'Unpaid Leave', 30.00, 0.00),
(50, 6, 'Special Leave', 5.00, 0.00),
(51, 7, 'Vacation Leave', 15.00, 0.00),
(52, 7, 'Sick Leave', 10.00, 0.00),
(53, 7, 'Emergency Leave', 5.00, 0.00),
(54, 7, 'Maternity Leave', 105.00, 0.00),
(55, 7, 'Paternity Leave', 7.00, 0.00),
(56, 7, 'Bereavement Leave', 5.00, 0.00),
(57, 7, 'Study Leave', 15.00, 0.00),
(58, 7, 'Compensatory Leave', 0.00, 0.00),
(59, 7, 'Unpaid Leave', 30.00, 0.00),
(60, 7, 'Special Leave', 5.00, 0.00);

-- --------------------------------------------------------

--
-- Table structure for table `leave_requests`
--

CREATE TABLE `leave_requests` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `leave_type` varchar(100) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `reason` text DEFAULT NULL,
  `status` enum('Pending','Pending Manager Approval','Pending Admin Approval','Approved','Rejected') DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `admin_comment` text DEFAULT NULL,
  `attachment` varchar(255) DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `leave_requests`
--

INSERT INTO `leave_requests` (`id`, `user_id`, `leave_type`, `start_date`, `end_date`, `reason`, `status`, `created_at`, `admin_comment`, `attachment`, `updated_at`) VALUES
(1, 2, 'Sick Leave', '2026-06-21', '2026-06-28', '', 'Approved', '2026-06-15 04:10:27', NULL, NULL, '2026-06-15 12:10:27'),
(2, 2, 'Vacation Leave', '2026-06-15', '2026-06-20', '', 'Rejected', '2026-06-15 05:51:21', '', NULL, '2026-06-22 09:58:04');

-- --------------------------------------------------------

--
-- Table structure for table `positions`
--

CREATE TABLE `positions` (
  `id` int(11) NOT NULL,
  `department_id` int(11) NOT NULL,
  `title` varchar(100) NOT NULL,
  `job_description` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `positions`
--

INSERT INTO `positions` (`id`, `department_id`, `title`, `job_description`, `created_at`) VALUES
(1, 1, 'Developer', NULL, '2026-06-22 09:58:04'),
(2, 2, 'Networking', NULL, '2026-06-22 09:58:04'),
(3, 3, 'Developer', NULL, '2026-06-22 09:58:04'),
(4, 2, 'Software Developer', '', '2026-06-22 13:15:19');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('Employee','Manager','Admin') DEFAULT 'Employee',
  `status` enum('Pending','Active','Rejected') NOT NULL DEFAULT 'Pending',
  `department` varchar(100) DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL,
  `position` varchar(100) DEFAULT NULL,
  `position_id` int(11) DEFAULT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `gender` enum('Male','Female') NOT NULL DEFAULT 'Male'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `status`, `department`, `department_id`, `position`, `position_id`, `first_name`, `middle_name`, `last_name`, `gender`) VALUES
(1, 'Admin User', 'admin@example.com', '$2y$10$n4swR2UpCNbnlz1jIkdEZuX7uJjlCYHJ.AT7kPdWquJZ1jCrFYwde', 'Admin', 'Active', NULL, NULL, NULL, NULL, 'Admin', NULL, 'User', 'Male'),
(2, 'John Doe', 'john@example.com', '$2y$10$n4swR2UpCNbnlz1jIkdEZuX7uJjlCYHJ.AT7kPdWquJZ1jCrFYwde', 'Employee', 'Active', NULL, NULL, NULL, NULL, 'John', NULL, 'Doe', 'Male'),
(3, 'ANDIE MAHINAY ENTONG', 'entong@gmail.com', '$2y$10$AqMGKFBriF55rxwSo1Fuj.8Xu7.wOp7s4/O.MpYN.wDGyzKMY8zNC', 'Employee', 'Active', 'Engineering', 1, 'Developer', 1, 'ANDIE', 'MAHINAY', 'ENTONG', 'Male'),
(4, 'JOHN ROZILLER GOMISONG', 'gomisong@gmail.com', '$2y$10$f97Sp86Vm26KCwdPBWGzk.rE27bDFM6Xf89SZc6CNHIMnp0n15Gn6', 'Employee', 'Rejected', 'IT', 2, 'Networking', 2, 'JOHN', 'ROZILLER', 'GOMISONG', 'Male'),
(5, 'RHON JAMES G BARCELONA', 'rhon@gmail.com', '$2y$10$1/hMdhYkXsGk/X6DGkPiw.NIM7sx3EHZhWeO0OzUsPb5iYC1OmRAO', 'Employee', 'Pending', 'CS', 3, 'Developer', 3, 'RHON JAMES', 'G', 'BARCELONA', 'Male'),
(6, 'KITT HARLEY BILLONES SY', 'kittharleysy@gmail.com', '$2y$10$6WCUJcVmi07EYt8dp5zTNOIuzxM/XneIJhQUBGl..MOSINfDLR89u', 'Employee', 'Active', 'CS', 3, '', NULL, 'KITT HARLEY', 'BILLONES', 'SY', 'Male'),
(7, 'JOBERT SINTOY', 'sintoy@gmail.com', '$2y$10$dklY7KHGFy1a0V9qEO2UHOGyraKxrXnxOSaj50LifHT7RDWixWBfK', 'Employee', 'Active', 'Engineering', 1, 'Developer', 1, 'JOBERT', '', 'SINTOY', 'Male'),
(8, 'AXL JAY SALAZAR', 'manager01@example.com', '$2y$10$HU3CrzMTkj1nzxjtaGvuku4u4lblJJT96iiZvc5TDQ4.xftnEbAJK', 'Manager', 'Active', 'Engineering', 1, 'Developer', 1, 'AXL', 'JAY', 'SALAZAR', 'Male'),
(9, 'ENGINEER CIVIL', 'engineer@gmail.com', '$2y$10$7VlnBuiXKlYf4zlF6mqA4uzg.77DHc7Yufle1mejs0UnG1q.YBCKi', 'Employee', 'Pending', '', NULL, '', NULL, 'ENGINEER', '', 'CIVIL', 'Male');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_actor` (`actor_id`),
  ADD KEY `idx_target` (`target_type`,`target_id`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`),
  ADD KEY `manager_id` (`manager_id`);

--
-- Indexes for table `leave_balances`
--
ALTER TABLE `leave_balances`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `leave_requests`
--
ALTER TABLE `leave_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `positions`
--
ALTER TABLE `positions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `department_id` (`department_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `leave_balances`
--
ALTER TABLE `leave_balances`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=61;

--
-- AUTO_INCREMENT for table `leave_requests`
--
ALTER TABLE `leave_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `positions`
--
ALTER TABLE `positions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `departments`
--
ALTER TABLE `departments`
  ADD CONSTRAINT `departments_ibfk_1` FOREIGN KEY (`manager_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `leave_balances`
--
ALTER TABLE `leave_balances`
  ADD CONSTRAINT `leave_balances_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `leave_requests`
--
ALTER TABLE `leave_requests`
  ADD CONSTRAINT `leave_requests_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `positions`
--
ALTER TABLE `positions`
  ADD CONSTRAINT `positions_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
