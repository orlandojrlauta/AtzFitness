-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 24, 2026 at 08:55 AM
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
-- Database: `atz_fitness_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `username` varchar(50) NOT NULL,
  `role` varchar(20) NOT NULL,
  `action` varchar(100) NOT NULL,
  `description` text NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `activity_logs`
--

INSERT INTO `activity_logs` (`id`, `user_id`, `username`, `role`, `action`, `description`, `ip_address`, `created_at`) VALUES
(1, 1, 'admin', 'Administrator', 'User Login', 'User successfully logged in', '::1', '2026-08-03 10:01:01'),
(2, 1, 'admin', 'Administrator', 'Password Change', 'User changed password', '::1', '2026-08-03 10:01:27'),
(3, 1, 'admin', 'Administrator', 'Walk-in Entry', 'Registered walk-in guest: aasdda (₱150)', '::1', '2026-08-03 10:20:00'),
(4, 1, 'admin', 'Administrator', 'User Login', 'User successfully logged in', '::1', '2026-08-04 04:23:00'),
(5, 1, 'admin', 'Administrator', 'User Login', 'User successfully logged in', '::1', '2026-08-04 04:54:45'),
(6, 1, 'admin', 'Administrator', 'User Logout', 'User logged out of the system', '::1', '2026-08-04 05:39:41'),
(7, 1, 'admin', 'Administrator', 'User Login', 'User successfully logged in', '::1', '2026-08-04 05:55:57'),
(8, 1, 'admin', 'Administrator', 'Password Change', 'User changed password', '::1', '2026-08-04 06:00:59'),
(9, 1, 'admin', 'Administrator', 'User Logout', 'User logged out of the system', '::1', '2026-08-04 06:01:03'),
(10, 1, 'admin', 'Administrator', 'User Login', 'User successfully logged in', '::1', '2026-08-04 06:01:22'),
(11, 1, 'admin', 'Administrator', 'Create Staff', 'Created staff account for orlandojr', '::1', '2026-08-04 06:05:15'),
(12, 1, 'admin', 'Administrator', 'Toggle Staff Status', 'Updated status for Staff ID #2', '::1', '2026-08-04 06:05:20'),
(13, 1, 'admin', 'Administrator', 'Toggle Staff Status', 'Updated status for Staff ID #2', '::1', '2026-08-04 06:05:27'),
(14, 1, 'admin', 'Administrator', 'User Logout', 'User logged out of the system', '::1', '2026-08-04 06:05:44'),
(15, 2, 'orlandojr', 'Staff', 'User Login', 'User successfully logged in', '::1', '2026-08-04 06:06:10'),
(16, 2, 'orlandojr', 'Staff', 'Password Change', 'User changed password', '::1', '2026-08-04 06:07:01'),
(17, 2, 'orlandojr', 'Staff', 'User Logout', 'User logged out of the system', '::1', '2026-08-04 06:33:05'),
(18, 1, 'admin', 'Administrator', 'User Login', 'User successfully logged in', '::1', '2026-08-04 06:34:03'),
(19, 1, 'admin', 'Administrator', 'User Logout', 'User logged out of the system', '::1', '2026-08-04 06:36:18'),
(20, 2, 'orlandojr', 'Staff', 'User Login', 'User successfully logged in', '::1', '2026-08-04 06:36:41'),
(21, 2, 'orlandojr', 'Staff', 'User Logout', 'User logged out of the system', '::1', '2026-08-04 06:41:03'),
(22, 1, 'admin', 'Administrator', 'User Login', 'User successfully logged in', '::1', '2026-08-04 06:41:18'),
(23, 1, 'admin', 'Administrator', 'User Logout', 'User logged out of the system', '::1', '2026-08-04 06:44:37'),
(24, 2, 'orlandojr', 'Staff', 'User Login', 'User successfully logged in', '::1', '2026-08-04 06:44:50'),
(25, 2, 'orlandojr', 'Staff', 'User Logout', 'User logged out of the system', '::1', '2026-08-04 06:51:58'),
(26, 1, 'admin', 'Administrator', 'User Login', 'User successfully logged in', '::1', '2026-08-04 06:52:11'),
(27, 1, 'admin', 'Administrator', 'Add Member', 'Registered new member ATZ-96658 (name lastname)', '::1', '2026-08-04 07:05:56'),
(28, 1, 'admin', 'Administrator', 'Add Member', 'Registered new member ATZ-57530 (john loyd pacinio)', '::1', '2026-08-04 07:19:17'),
(29, 1, 'admin', 'Administrator', 'Add Member', 'Registered new member ATZ-73482 (john loyd pacinio)', '::1', '2026-08-04 07:25:37'),
(30, 1, 'admin', 'Administrator', 'Add Plan', 'Created membership plan: Monthly Regular Pass (₱500)', '::1', '2026-08-04 07:39:37'),
(31, 1, 'admin', 'Administrator', 'Delete Plan', 'Deleted membership plan: Monthly Regular Pass', '::1', '2026-08-04 07:39:45'),
(32, 1, 'admin', 'Administrator', 'Delete Plan', 'Deleted membership plan: Yearly Elite VIP Membership', '::1', '2026-08-04 07:39:56'),
(33, 1, 'admin', 'Administrator', 'Delete Plan', 'Deleted membership plan: Quarterly VIP Membership', '::1', '2026-08-04 07:40:00'),
(34, 1, 'admin', 'Administrator', 'Delete Plan', 'Deleted membership plan: Student Saver Promo', '::1', '2026-08-04 07:40:10'),
(35, 1, 'admin', 'Administrator', 'Add Plan', 'Created membership plan: Monthly Student Saver Promo (₱450)', '::1', '2026-08-04 07:41:10'),
(36, 1, 'admin', 'Administrator', 'User Logout', 'User logged out of the system', '::1', '2026-08-04 07:46:13'),
(37, 2, 'orlandojr', 'Staff', 'User Login', 'User successfully logged in', '::1', '2026-08-04 07:46:46'),
(38, 2, 'orlandojr', 'Staff', 'User Logout', 'User logged out of the system', '::1', '2026-08-04 07:48:04'),
(39, 1, 'admin', 'Administrator', 'User Login', 'User successfully logged in', '::1', '2026-08-04 07:48:26'),
(40, 1, 'admin', 'Administrator', 'User Logout', 'User logged out of the system', '::1', '2026-08-04 07:56:11'),
(41, 2, 'orlandojr', 'Staff', 'User Login', 'User successfully logged in', '::1', '2026-08-04 07:56:22'),
(42, 2, 'orlandojr', 'Staff', 'User Logout', 'User logged out of the system', '::1', '2026-08-04 07:57:42'),
(43, 1, 'admin', 'Administrator', 'User Login', 'User successfully logged in', '::1', '2026-08-04 07:57:55'),
(44, 1, 'admin', 'Administrator', 'Toggle Staff Status', 'Updated status for Staff ID #2', '::1', '2026-08-04 07:58:53'),
(45, 1, 'admin', 'Administrator', 'User Logout', 'User logged out of the system', '::1', '2026-08-04 07:58:56'),
(46, 1, 'admin', 'Administrator', 'User Login', 'User successfully logged in', '::1', '2026-08-04 07:59:47'),
(47, 1, 'admin', 'Administrator', 'Update Settings', 'Updated gym settings and GCash parameters', '::1', '2026-08-04 08:14:50'),
(48, 1, 'admin', 'Administrator', 'Toggle Staff Status', 'Updated status for Staff ID #2', '::1', '2026-08-04 08:15:02'),
(49, 1, 'admin', 'Administrator', 'Update Settings', 'Updated gym settings and GCash parameters', '::1', '2026-08-04 08:17:09'),
(50, 1, 'admin', 'Administrator', 'Update Settings', 'Updated gym settings and GCash parameters', '::1', '2026-08-04 08:17:20'),
(51, 1, 'admin', 'Administrator', 'Update Settings', 'Updated gym settings and GCash parameters', '::1', '2026-08-04 08:25:45'),
(52, 1, 'admin', 'Administrator', 'Assign Membership', 'Assigned plan Monthly Student Saver Promo to Member ID #1', '::1', '2026-08-04 09:00:22'),
(53, 1, 'admin', 'Administrator', 'Assign Membership', 'Assigned plan Monthly Student Saver Promo to Member ID #2', '::1', '2026-08-04 09:19:33'),
(54, 1, 'admin', 'Administrator', 'Assign Membership', 'Assigned plan Monthly Student Saver Promo to Member ID #3', '::1', '2026-08-04 09:20:29'),
(55, 1, 'admin', 'Administrator', 'Attendance Check-In', 'Checked in member ATZ-73482', '::1', '2026-08-04 09:23:57'),
(56, 1, 'admin', 'Administrator', 'User Login', 'User successfully logged in', '::1', '2026-08-05 05:55:19'),
(57, 1, 'admin', 'Administrator', 'User Logout', 'User logged out of the system', '::1', '2026-08-05 06:05:11'),
(58, 1, 'admin', 'Administrator', 'User Login', 'User successfully logged in', '::1', '2026-08-05 06:23:09'),
(59, 1, 'admin', 'Administrator', 'Update Settings', 'Updated gym settings and GCash parameters', '::1', '2026-08-05 06:26:26'),
(60, 1, 'admin', 'Administrator', 'Walk-in Entry', 'Registered walk-in guest: Agilang (₱40)', '::1', '2026-08-05 06:27:14'),
(61, 1, 'admin', 'Administrator', 'Reset Staff Password', 'Reset password for orlandojr', '::1', '2026-08-05 07:15:28'),
(62, 1, 'admin', 'Administrator', 'Reset Staff Password', 'Reset password for orlandojr', '::1', '2026-08-05 07:15:36'),
(63, 1, 'admin', 'Administrator', 'User Logout', 'User logged out of the system', '::1', '2026-08-05 07:15:55'),
(64, 1, 'admin', 'Administrator', 'User Login', 'User successfully logged in', '::1', '2026-08-05 07:16:51'),
(65, 1, 'admin', 'Administrator', 'Reset Staff Password', 'Reset password for orlandojr', '::1', '2026-08-05 07:16:58'),
(66, 1, 'admin', 'Administrator', 'User Logout', 'User logged out of the system', '::1', '2026-08-05 07:17:32'),
(67, 2, 'orlandojr', 'Staff', 'User Login', 'User successfully logged in', '::1', '2026-08-05 07:17:38'),
(68, 2, 'orlandojr', 'Staff', 'Password Change', 'User changed password', '::1', '2026-08-05 07:18:17'),
(69, 2, 'orlandojr', 'Staff', 'Assign Membership', 'Assigned plan Monthly Student Saver Promo to Member ID #2', '::1', '2026-09-05 07:20:31'),
(70, 2, 'orlandojr', 'Staff', 'User Logout', 'User logged out of the system', '::1', '2026-08-05 07:37:45'),
(71, 1, 'admin', 'Administrator', 'User Login', 'User successfully logged in', '::1', '2026-08-05 07:37:56'),
(72, 1, 'admin', 'Administrator', 'Attendance Check-In', 'Checked in member ATZ-73482', '::1', '2026-08-05 07:44:49'),
(73, 1, 'admin', 'Administrator', 'Add Member', 'Registered new member ATZ-58743 (Arnold Schwarenegger)', '::1', '2026-08-05 08:05:42'),
(74, 1, 'admin', 'Administrator', 'Walk-in Entry', 'Registered walk-in guest: d11123141341411 (₱40)', '::1', '2026-08-05 08:34:30'),
(75, 1, 'admin', 'Administrator', 'Attendance Check-Out', 'Checked out member ATZ-73482', '::1', '2026-08-05 09:17:09'),
(76, 1, 'admin', 'Administrator', 'Toggle Staff Status', 'Updated status for Staff ID #2', '::1', '2026-08-05 09:18:45'),
(77, 1, 'admin', 'Administrator', 'Toggle Staff Status', 'Updated status for Staff ID #2', '::1', '2026-08-05 09:18:47'),
(78, 1, 'admin', 'Administrator', 'Member Status Change', 'Set Arnold Schwarenegger to Inactive', '::1', '2026-08-05 09:28:45'),
(79, 1, 'admin', 'Administrator', 'Member Status Change', 'Set Arnold Schwarenegger to Active', '::1', '2026-08-05 09:29:13'),
(80, 1, 'admin', 'Administrator', 'Member Status Change', 'Set john loyd pacinio to Inactive', '::1', '2026-08-05 09:29:27'),
(81, 1, 'admin', 'Administrator', 'Update Profile', 'Updated own profile details', '::1', '2026-08-05 09:46:55'),
(82, 1, 'admin', 'Administrator', 'Update Settings', 'Updated gym settings and GCash parameters', '::1', '2026-08-05 09:50:03'),
(83, 1, 'admin', 'Administrator', 'Member Status Change', 'Set john loyd pacinio to Active.', '::1', '2026-08-05 09:53:46'),
(84, 1, 'admin', 'Administrator', 'Member Status Change', 'Set john loyd pacinio to Inactive. Membership paused with 29 day(s) remaining.', '::1', '2026-08-05 09:55:35'),
(85, 1, 'admin', 'Administrator', 'Member Status Change', 'Set john loyd pacinio to Active. Membership resumed - new expiration date: 2026-09-25.', '::1', '2026-08-27 09:56:19'),
(86, 1, 'admin', 'Administrator', 'User Login', 'User successfully logged in', '::1', '2026-08-06 05:22:05'),
(87, 1, 'admin', 'Administrator', 'Walk-in Entry', 'Registered walk-in guest: newcustomer (₱40)', '::1', '2026-08-06 05:33:15'),
(88, 1, 'admin', 'Administrator', 'Attendance Check-In', 'Checked in member ATZ-57530', '::1', '2026-08-06 06:25:23'),
(89, 1, 'admin', 'Administrator', 'Attendance Check-In', 'Checked in member ATZ-96658', '::1', '2026-08-06 06:25:42'),
(90, 1, 'admin', 'Administrator', 'Add Member', 'Registered new member ATZ-23283 (James Bond)', '::1', '2026-08-06 06:31:55'),
(91, 1, 'admin', 'Administrator', 'Add Member', 'Registered new member ATZ-22949 (Kuya Bhie)', '::1', '2026-08-06 06:42:28'),
(92, 1, 'admin', 'Administrator', 'Add Member', 'Registered new member ATZ-11068 (sdas asd)', '::1', '2026-08-06 07:11:26'),
(93, 1, 'admin', 'Administrator', 'Update Profile', 'Updated own profile details', '::1', '2026-08-06 07:22:01'),
(94, 1, 'admin', 'Administrator', 'Update Profile', 'Updated own profile details', '::1', '2026-08-06 07:49:21'),
(95, 1, 'admin', 'Administrator', 'Password Change', 'User changed password', '::1', '2026-08-06 07:50:28'),
(96, 1, 'admin', 'Administrator', 'User Logout', 'User logged out of the system', '::1', '2026-08-06 08:16:19'),
(97, 1, 'admin', 'Administrator', 'User Login', 'User successfully logged in', '::1', '2026-08-06 08:24:41'),
(98, 1, 'admin', 'Administrator', 'User Logout', 'User logged out of the system', '::1', '2026-08-06 08:25:28'),
(99, 1, 'admin', 'Administrator', 'User Login', 'User successfully logged in', '::1', '2026-08-06 08:25:53'),
(100, 1, 'admin', 'Administrator', 'Reset Staff Password', 'Reset password for orlandojr', '::1', '2026-08-06 08:26:12'),
(101, 1, 'admin', 'Administrator', 'User Logout', 'User logged out of the system', '::1', '2026-08-06 08:26:23'),
(102, 2, 'orlandojr', 'Staff', 'User Login', 'User successfully logged in', '::1', '2026-08-06 08:26:28'),
(103, 2, 'orlandojr', 'Staff', 'Password Change', 'User changed password', '::1', '2026-08-06 08:26:53'),
(104, 2, 'orlandojr', 'Staff', 'Add Member', 'Registered new member ATZ-62996 (Arnold Schwarenegger)', '::1', '2026-08-06 08:35:06'),
(105, 2, 'orlandojr', 'Staff', 'User Logout', 'User logged out of the system', '::1', '2026-08-06 08:39:16'),
(106, 1, 'admin', 'Administrator', 'User Login', 'User successfully logged in', '::1', '2026-08-06 08:39:50'),
(107, 1, 'admin', 'Administrator', 'User Logout', 'User logged out of the system', '::1', '2026-08-06 09:15:50'),
(108, 1, 'bossing', 'Administrator', 'User Login', 'User successfully logged in', '::1', '2026-08-06 09:16:02'),
(109, 1, 'bossing', 'Administrator', 'User Logout', 'User logged out of the system', '::1', '2026-08-06 09:46:34'),
(110, 1, 'bossing1', 'Administrator', 'User Login', 'User successfully logged in', '::1', '2026-08-06 09:46:56'),
(111, 1, 'bossing1', 'Administrator', 'User Logout', 'User logged out of the system', '::1', '2026-08-06 09:52:33'),
(112, 1, 'bossing1', 'Administrator', 'User Login', 'User successfully logged in', '::1', '2026-08-06 09:52:58'),
(113, 1, 'bossing1', 'Administrator', 'User Login', 'User successfully logged in', '::1', '2026-08-22 07:19:49'),
(114, 1, 'bossing1', 'Administrator', 'User Logout', 'User logged out of the system', '::1', '2026-08-22 07:35:12'),
(115, 1, 'bossing1', 'Administrator', 'User Login', 'User successfully logged in', '::1', '2026-08-24 05:05:08'),
(116, 1, 'bossing1', 'Administrator', 'User Logout', 'User logged out of the system', '::1', '2026-08-24 06:05:53'),
(117, 1, 'bossing', 'Administrator', 'Initial Admin Setup', 'Administrator account created', '::1', '2026-08-24 06:14:57'),
(118, 1, 'bossing', 'Administrator', 'User Login', 'User successfully logged in', '::1', '2026-08-24 06:15:14'),
(119, 1, 'bossing', 'Administrator', 'User Logout', 'User logged out of the system', '::1', '2026-08-24 06:20:37'),
(120, 2, 'bossing', 'Administrator', 'Initial Admin Setup', 'Administrator account created', '::1', '2026-08-24 06:24:19'),
(121, 2, 'bossing', 'Administrator', 'User Login', 'User successfully logged in', '::1', '2026-08-24 06:25:28');

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `check_in_time` datetime NOT NULL,
  `check_out_time` datetime DEFAULT NULL,
  `date` date NOT NULL,
  `status` enum('Checked-In','Checked-Out') NOT NULL DEFAULT 'Checked-In',
  `notes` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `attendance`
--

INSERT INTO `attendance` (`id`, `member_id`, `check_in_time`, `check_out_time`, `date`, `status`, `notes`) VALUES
(3, 2, '2026-08-06 08:25:23', NULL, '2026-08-06', 'Checked-In', NULL),
(4, 1, '2026-08-06 08:25:42', NULL, '2026-08-06', 'Checked-In', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `members`
--

CREATE TABLE `members` (
  `id` int(11) NOT NULL,
  `member_code` varchar(20) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `gender` enum('Male','Female','Other') NOT NULL,
  `birthdate` date NOT NULL,
  `age` int(11) NOT NULL,
  `email` varchar(100) NOT NULL,
  `contact_no` varchar(15) NOT NULL,
  `member_type` enum('Regular','Student','VIP') NOT NULL DEFAULT 'Regular',
  `profile_picture` varchar(255) DEFAULT NULL,
  `status` enum('Active','Inactive','Expired') NOT NULL DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `members`
--

INSERT INTO `members` (`id`, `member_code`, `first_name`, `last_name`, `gender`, `birthdate`, `age`, `email`, `contact_no`, `member_type`, `profile_picture`, `status`, `created_at`, `updated_at`) VALUES
(1, 'ATZ-96658', 'name', 'lastname', 'Other', '2006-08-04', 20, 'name123@gmail.com', '09654314187', 'Student', 'default_avatar.png', 'Active', '2026-08-04 07:05:56', '2026-08-04 07:05:56'),
(2, 'ATZ-57530', 'john loyd', 'pacinio', 'Male', '2006-08-21', 19, 'jonhloyd@gmail.com', '09427361738', 'Student', 'default_avatar.png', 'Active', '2026-08-04 07:19:17', '2026-08-04 07:19:17'),
(8, 'ATZ-62996', 'Arnold', 'Schwarenegger', 'Male', '1999-09-05', 26, 'arnold@gmail.com', '09436328434', 'Regular', 'default_avatar.png', 'Active', '2026-08-06 08:35:06', '2026-08-06 08:35:06');

-- --------------------------------------------------------

--
-- Table structure for table `memberships`
--

CREATE TABLE `memberships` (
  `id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `plan_id` int(11) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `price_paid` decimal(10,2) NOT NULL,
  `status` enum('Active','Expiring Soon','Expired','Cancelled','Inactive') NOT NULL DEFAULT 'Active',
  `paused_remaining_days` int(11) DEFAULT NULL,
  `paused_at` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `memberships`
--

INSERT INTO `memberships` (`id`, `member_id`, `plan_id`, `start_date`, `end_date`, `price_paid`, `status`, `paused_remaining_days`, `paused_at`, `created_at`) VALUES
(1, 1, 6, '2026-08-04', '2026-09-03', 450.00, 'Active', NULL, NULL, '2026-08-04 09:00:22'),
(2, 2, 6, '2026-08-04', '2026-09-03', 450.00, 'Active', NULL, NULL, '2026-08-04 09:19:33');

-- --------------------------------------------------------

--
-- Table structure for table `membership_plans`
--

CREATE TABLE `membership_plans` (
  `id` int(11) NOT NULL,
  `plan_name` varchar(100) NOT NULL,
  `category` enum('Regular','Student','VIP') NOT NULL DEFAULT 'Regular',
  `duration_days` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `membership_plans`
--

INSERT INTO `membership_plans` (`id`, `plan_name`, `category`, `duration_days`, `price`, `description`, `status`, `created_at`) VALUES
(5, 'Monthly Regular Pass', 'Regular', 30, 500.00, '', 'Active', '2026-08-04 07:39:37'),
(6, 'Monthly Student Saver Promo', 'Student', 30, 450.00, '', 'Active', '2026-08-04 07:41:10');

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `transaction_no` varchar(50) NOT NULL,
  `member_id` int(11) DEFAULT NULL,
  `walkin_id` int(11) DEFAULT NULL,
  `membership_id` int(11) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `amount_tendered` decimal(10,2) DEFAULT NULL,
  `change_amount` decimal(10,2) DEFAULT NULL,
  `payment_method` enum('Cash','GCash') NOT NULL,
  `gcash_ref_no` varchar(50) DEFAULT NULL,
  `payment_for` enum('Membership','Walk-in','Renewal') NOT NULL,
  `payment_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('Paid','Pending','Refunded') DEFAULT 'Paid',
  `processed_by` int(11) NOT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `transaction_no`, `member_id`, `walkin_id`, `membership_id`, `amount`, `amount_tendered`, `change_amount`, `payment_method`, `gcash_ref_no`, `payment_for`, `payment_date`, `status`, `processed_by`, `notes`) VALUES
(1, 'TXN-WALK-1785752400-744', NULL, 1, NULL, 150.00, 150.00, 0.00, 'Cash', '', 'Walk-in', '2026-08-03 10:20:00', 'Paid', 1, NULL),
(2, 'TXN-1785834022-536', 1, NULL, 1, 450.00, 500.00, 50.00, 'Cash', '', 'Membership', '2026-08-04 09:00:22', 'Paid', 1, NULL),
(3, 'TXN-1785835173-844', 2, NULL, 2, 450.00, 500.00, 50.00, 'Cash', '', 'Membership', '2026-08-04 09:19:33', 'Paid', 1, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `updated_at`) VALUES
(1, 'gym_name', 'ATZ FITNESS', '2026-08-03 10:00:15'),
(2, 'gym_tagline', 'Transform Your Body, Elevate Your Life', '2026-08-03 10:00:15'),
(3, 'gym_address', 'Mancilang, Madriejos, Cebu', '2026-08-05 09:50:03'),
(4, 'gym_contact', '09171234567', '2026-08-03 10:00:15'),
(5, 'gym_email', 'bossing@gmail.com', '2026-08-05 09:50:03'),
(6, 'operating_hours', '9:00 AM - 10:00 PM (Mon-Sun)', '2026-08-05 09:50:03'),
(7, 'gcash_account_name', 'ATZ FITNESS GYM', '2026-08-03 10:00:15'),
(8, 'gcash_account_no', '09171234567', '2026-08-03 10:00:15'),
(9, 'gcash_qr_image', 'gcash_qr_1785831945.jpg', '2026-08-04 08:25:45'),
(10, 'currency_symbol', '₱', '2026-08-03 10:00:15'),
(11, 'walkin_rate', '40', '2026-08-05 06:26:26');

-- --------------------------------------------------------

--
-- Table structure for table `student_documents`
--

CREATE TABLE `student_documents` (
  `id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `document_type` varchar(100) NOT NULL DEFAULT 'School ID / Registration Proof',
  `file_path` varchar(255) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `status` enum('Pending','Approved','Rejected') DEFAULT 'Approved',
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `student_documents`
--

INSERT INTO `student_documents` (`id`, `member_id`, `document_type`, `file_path`, `file_name`, `status`, `uploaded_at`) VALUES
(1, 1, 'School ID', 'student_1785827156_7859.jpg', 'logo.jpg', 'Approved', '2026-08-04 07:05:56'),
(2, 2, 'School ID', 'student_1785827957_4125.jpg', 'logo.jpg', 'Approved', '2026-08-04 07:19:17');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `contact_no` varchar(20) NOT NULL,
  `role` enum('Administrator','Staff') NOT NULL DEFAULT 'Staff',
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `force_password_change` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `full_name`, `email`, `contact_no`, `role`, `status`, `force_password_change`, `created_at`, `updated_at`) VALUES
(2, 'bossing', '$2y$10$i0e42/HEYr5Qzjs6YpbuXeOVt6cZo2zP5tlN2eXaQodbyUQGFa4Jq', 'Bossing The Boss', 'bossing@gmail.com', '09515215232', 'Administrator', 'Active', 0, '2026-08-24 06:24:19', '2026-08-24 06:24:19');

-- --------------------------------------------------------

--
-- Table structure for table `walkin_customers`
--

CREATE TABLE `walkin_customers` (
  `id` int(11) NOT NULL,
  `guest_name` varchar(100) NOT NULL,
  `contact_no` varchar(15) NOT NULL,
  `rate` decimal(10,2) NOT NULL,
  `payment_method` enum('Cash','GCash') NOT NULL DEFAULT 'Cash',
  `gcash_ref_no` varchar(50) DEFAULT NULL,
  `check_in_time` datetime NOT NULL,
  `date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `walkin_customers`
--

INSERT INTO `walkin_customers` (`id`, `guest_name`, `contact_no`, `rate`, `payment_method`, `gcash_ref_no`, `check_in_time`, `date`, `created_at`) VALUES
(1, 'aasdda', '09515215232', 150.00, 'Cash', '', '2026-08-03 12:20:00', '2026-08-03', '2026-08-03 10:20:00'),
(2, 'Agilang', '09235252423', 40.00, 'Cash', '', '2026-08-05 08:27:14', '2026-08-05', '2026-08-05 06:27:14'),
(3, 'd11123141341411', '', 40.00, 'Cash', '', '2026-08-05 10:34:30', '2026-08-05', '2026-08-05 08:34:30'),
(4, 'newcustomer', '', 40.00, 'Cash', '', '2026-08-06 07:33:15', '2026-08-06', '2026-08-06 05:33:15');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_logs_user` (`user_id`),
  ADD KEY `idx_logs_action` (`action`),
  ADD KEY `idx_logs_created` (`created_at`);

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_attendance_date` (`date`),
  ADD KEY `idx_attendance_member` (`member_id`);

--
-- Indexes for table `members`
--
ALTER TABLE `members`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `member_code` (`member_code`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_member_code` (`member_code`),
  ADD KEY `idx_member_type` (`member_type`),
  ADD KEY `idx_member_status` (`status`);

--
-- Indexes for table `memberships`
--
ALTER TABLE `memberships`
  ADD PRIMARY KEY (`id`),
  ADD KEY `member_id` (`member_id`),
  ADD KEY `plan_id` (`plan_id`),
  ADD KEY `idx_memberships_status` (`status`),
  ADD KEY `idx_memberships_dates` (`start_date`,`end_date`);

--
-- Indexes for table `membership_plans`
--
ALTER TABLE `membership_plans`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_plans_category` (`category`),
  ADD KEY `idx_plans_status` (`status`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `transaction_no` (`transaction_no`),
  ADD KEY `processed_by` (`processed_by`),
  ADD KEY `idx_payments_method` (`payment_method`),
  ADD KEY `idx_payments_date` (`payment_date`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `student_documents`
--
ALTER TABLE `student_documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_student_docs_member` (`member_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_users_role` (`role`),
  ADD KEY `idx_users_status` (`status`);

--
-- Indexes for table `walkin_customers`
--
ALTER TABLE `walkin_customers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_walkin_date` (`date`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=122;

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `members`
--
ALTER TABLE `members`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `memberships`
--
ALTER TABLE `memberships`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `membership_plans`
--
ALTER TABLE `membership_plans`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=67;

--
-- AUTO_INCREMENT for table `student_documents`
--
ALTER TABLE `student_documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `walkin_customers`
--
ALTER TABLE `walkin_customers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `memberships`
--
ALTER TABLE `memberships`
  ADD CONSTRAINT `memberships_ibfk_1` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `memberships_ibfk_2` FOREIGN KEY (`plan_id`) REFERENCES `membership_plans` (`id`);

--
-- Constraints for table `student_documents`
--
ALTER TABLE `student_documents`
  ADD CONSTRAINT `student_documents_ibfk_1` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
