-- Gatepass System Database Schema
CREATE DATABASE IF NOT EXISTS `gatepass_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `gatepass_db`;

-- Table structure for `users` (Admin Accounts)
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `email` VARCHAR(100) NOT NULL,
  `full_name` VARCHAR(100) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table structure for `gatepasses`
CREATE TABLE IF NOT EXISTS `gatepasses` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `gatepass_no` VARCHAR(20) NOT NULL UNIQUE,
  `visitor_name` VARCHAR(100) NOT NULL,
  `visitor_email` VARCHAR(100) NOT NULL,
  `visitor_phone` VARCHAR(20) NOT NULL,
  `company_org` VARCHAR(100) DEFAULT NULL,
  `purpose` VARCHAR(255) NOT NULL,
  `host_name` VARCHAR(100) NOT NULL,
  `department` VARCHAR(100) NOT NULL,
  `visit_date` DATE NOT NULL,
  `time_in` TIME DEFAULT NULL,
  `time_out` TIME DEFAULT NULL,
  `status` ENUM('Pending', 'Approved', 'Rejected', 'Checked In', 'Checked Out') DEFAULT 'Pending',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table structure for `settings`
CREATE TABLE IF NOT EXISTS `settings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `setting_key` VARCHAR(50) NOT NULL UNIQUE,
  `setting_value` TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed default admin user (username: admin, password: password123)
-- Hash generated via password_hash('password123', PASSWORD_DEFAULT)
INSERT INTO `users` (`username`, `password`, `email`, `full_name`) 
VALUES ('admin', '$2y$10$wU05ZpYfC1UeL7v96B1Wxe1hX2rU6o3iL9w9FjT5fU.mPZ5s07/K.', 'admin@example.com', 'System Administrator')
ON DUPLICATE KEY UPDATE `username`=`username`;

-- Seed default settings
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('system_name', 'GatePass Pro'),
('server_ip', 'localhost'),
('smtp_host', 'smtp.gmail.com'),
('smtp_port', '587'),
('smtp_secure', 'tls'),
('smtp_user', ''),
('smtp_pass', ''),
('admin_email', 'admin@example.com')
ON DUPLICATE KEY UPDATE `setting_key`=`setting_key`;
