-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 27, 2026 at 08:18 AM
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
-- Database: `infoconnect`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `action` varchar(50) NOT NULL,
  `module` varchar(100) NOT NULL,
  `reference_id` int(10) UNSIGNED DEFAULT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `system_settings` (
  `setting_key` varchar(100) NOT NULL,
  `setting_value` varchar(255) NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `system_login_attempts` (
  `account_id` varchar(50) NOT NULL,
  `failed_attempts` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `locked_until` datetime DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`account_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `admin_profiles`
--

CREATE TABLE `admin_profiles` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `employee_id` varchar(50) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) NOT NULL,
  `suffix` varchar(20) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `contact_number` varchar(30) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `announcements`
--

CREATE TABLE `announcements` (
  `id` int(10) UNSIGNED NOT NULL,
  `created_by` int(10) UNSIGNED NOT NULL,
  `title` varchar(200) NOT NULL,
  `content` text NOT NULL,
  `status` enum('draft','pending','approved','published','rejected','archived') NOT NULL DEFAULT 'draft',
  `published_at` datetime DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `approval_requests`
--

CREATE TABLE `approval_requests` (
  `id` int(10) UNSIGNED NOT NULL,
  `request_type` enum('senior','event','announcement','password_reset') NOT NULL,
  `reference_id` int(10) UNSIGNED NOT NULL,
  `requested_by` int(10) UNSIGNED NOT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `reviewed_by` int(10) UNSIGNED DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `civil_statuses`
--

CREATE TABLE `civil_statuses` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(50) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `civil_statuses`
--

INSERT INTO `civil_statuses` (`id`, `name`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Single', 1, '2026-09-25 01:28:47', '2026-09-25 01:28:47'),
(2, 'Married', 1, '2026-09-25 01:28:47', '2026-09-25 01:28:47'),
(3, 'Widowed', 1, '2026-09-25 01:28:47', '2026-09-25 01:28:47');

-- --------------------------------------------------------

--
-- Table structure for table `barangays`
--

CREATE TABLE `barangays` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `barangays`
--

INSERT INTO `barangays` (`id`, `name`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Addition Hills', 1, '2026-09-25 01:28:47', '2026-09-25 01:28:47'),
(2, 'Bagong Silang', 1, '2026-09-25 01:28:47', '2026-09-25 01:28:47'),
(3, 'Barangka Drive', 1, '2026-09-25 01:28:47', '2026-09-25 01:28:47'),
(4, 'Barangka Ibaba', 1, '2026-09-25 01:28:47', '2026-09-25 01:28:47'),
(5, 'Barangka Ilaya', 1, '2026-09-25 01:28:47', '2026-09-25 01:28:47'),
(6, 'Barangka Itaas', 1, '2026-09-25 01:28:47', '2026-09-25 01:28:47'),
(7, 'Buayang Bato', 1, '2026-09-25 01:28:47', '2026-09-25 01:28:47'),
(8, 'Burol', 1, '2026-09-25 01:28:47', '2026-09-25 01:28:47'),
(9, 'Daang Bakal', 1, '2026-09-25 01:28:47', '2026-09-25 01:28:47'),
(10, 'Hagdang Bato Itaas', 1, '2026-09-25 01:28:47', '2026-09-25 01:28:47'),
(11, 'Hagdang Bato Libis', 1, '2026-09-25 01:28:47', '2026-09-25 01:28:47'),
(12, 'Harapin ang Bukas', 1, '2026-09-25 01:28:47', '2026-09-25 01:28:47'),
(13, 'Highway Hills', 1, '2026-09-25 01:28:47', '2026-09-25 01:28:47'),
(14, 'Hulo', 1, '2026-09-25 01:28:47', '2026-09-25 01:28:47'),
(15, 'Mabini–J. Rizal', 1, '2026-09-25 01:28:47', '2026-09-25 01:28:47'),
(16, 'Malamig', 1, '2026-09-25 01:28:47', '2026-09-25 01:28:47'),
(17, 'Mauway', 1, '2026-09-25 01:28:47', '2026-09-25 01:28:47'),
(18, 'Namayan', 1, '2026-09-25 01:28:47', '2026-09-25 01:28:47'),
(19, 'New Zañiga', 1, '2026-09-25 01:28:47', '2026-09-25 01:28:47'),
(20, 'Old Zañiga', 1, '2026-09-25 01:28:47', '2026-09-25 01:28:47'),
(21, 'Pag-asa', 1, '2026-09-25 01:28:47', '2026-09-25 01:28:47'),
(22, 'Plainview', 1, '2026-09-25 01:28:47', '2026-09-25 01:28:47'),
(23, 'Pleasant Hills', 1, '2026-09-25 01:28:47', '2026-09-25 01:28:47'),
(24, 'Poblacion', 1, '2026-09-25 01:28:47', '2026-09-25 01:28:47'),
(25, 'San Jose', 1, '2026-09-25 01:28:47', '2026-09-25 01:28:47'),
(26, 'Vergara', 1, '2026-09-25 01:28:47', '2026-09-25 01:28:47'),
(27, 'Wack-Wack Greenhills East', 1, '2026-09-25 01:28:47', '2026-09-25 01:28:47');

-- --------------------------------------------------------

--
-- Table structure for table `educational_attainments`
--

CREATE TABLE `educational_attainments` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `educational_attainments`
--

INSERT INTO `educational_attainments` (`id`, `name`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Elementary', 1, '2026-09-25 01:28:47', '2026-09-25 01:28:47'),
(2, 'High School', 1, '2026-09-25 01:28:47', '2026-09-25 01:28:47'),
(3, 'College Undergraduate', 1, '2026-09-25 01:28:47', '2026-09-25 01:28:47'),
(4, 'College Graduate', 1, '2026-09-25 01:28:47', '2026-09-25 01:28:47');

-- --------------------------------------------------------

--
-- Table structure for table `events`
--

CREATE TABLE `events` (
  `id` int(10) UNSIGNED NOT NULL,
  `created_by` int(10) UNSIGNED NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `event_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time DEFAULT NULL,
  `location` varchar(255) NOT NULL,
  `poster_path` varchar(500) DEFAULT NULL,
  `status` enum('draft','pending','approved','published','rejected','cancelled','completed','archived') NOT NULL DEFAULT 'draft',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `event_attendance`
--

CREATE TABLE `event_attendance` (
  `id` int(10) UNSIGNED NOT NULL,
  `event_id` int(10) UNSIGNED NOT NULL,
  `registration_id` int(10) UNSIGNED NOT NULL,
  `senior_id` int(10) UNSIGNED NOT NULL,
  `attendance_status` enum('present','absent') NOT NULL DEFAULT 'present',
  `permit_received` tinyint(1) NOT NULL DEFAULT 0,
  `checked_in_by` int(10) UNSIGNED DEFAULT NULL,
  `checked_in_at` datetime DEFAULT NULL,
  `remarks` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `event_registrations`
--

CREATE TABLE `event_registrations` (
  `id` int(10) UNSIGNED NOT NULL,
  `event_id` int(10) UNSIGNED NOT NULL,
  `senior_id` int(10) UNSIGNED NOT NULL,
  `status` enum('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'approved',
  `permit_number` varchar(100) DEFAULT NULL,
  `permit_issued_at` datetime DEFAULT NULL,
  `registered_at` datetime NOT NULL DEFAULT current_timestamp(),
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `import_batches`
--

CREATE TABLE `import_batches` (
  `id` int(10) UNSIGNED NOT NULL,
  `imported_by` int(10) UNSIGNED NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `total_records` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `successful_records` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `failed_records` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `duplicate_records` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `status` enum('reviewing','completed','cancelled') NOT NULL DEFAULT 'reviewing',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `completed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `import_records`
--

CREATE TABLE `import_records` (
  `id` int(10) UNSIGNED NOT NULL,
  `import_batch_id` int(10) UNSIGNED NOT NULL,
  `row_number` int(10) UNSIGNED NOT NULL,
  `senior_citizen_id` varchar(50) DEFAULT NULL,
  `status` enum('valid','imported','duplicate','updated','skipped','failed') NOT NULL DEFAULT 'valid',
  `error_message` text DEFAULT NULL,
  `senior_id` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `type` varchar(50) NOT NULL,
  `title` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `reference_id` int(10) UNSIGNED DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `read_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `occupations`
--

CREATE TABLE `occupations` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `occupations`
--

INSERT INTO `occupations` (`id`, `name`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Private Employee', 1, '2026-09-25 01:28:47', '2026-09-25 01:28:47'),
(2, 'Government Employee', 1, '2026-09-25 01:28:47', '2026-09-25 01:28:47'),
(3, 'Self-Employed', 1, '2026-09-25 01:28:47', '2026-09-25 01:28:47'),
(4, 'Unemployed', 1, '2026-09-25 01:28:47', '2026-09-25 01:28:47');

-- --------------------------------------------------------

--
-- Table structure for table `pension_types`
--

CREATE TABLE `pension_types` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `pension_types`
--

INSERT INTO `pension_types` (`id`, `name`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Social Pension', 1, '2026-09-25 01:28:47', '2026-09-25 01:28:47'),
(2, 'GSIS', 1, '2026-09-25 01:28:47', '2026-09-25 01:28:47'),
(3, 'SSS', 1, '2026-09-25 01:28:47', '2026-09-25 01:28:47'),
(4, 'Survivor', 1, '2026-09-25 01:28:47', '2026-09-25 01:28:47');

-- --------------------------------------------------------

--
-- Table structure for table `senior_profiles`
--

CREATE TABLE `senior_profiles` (
  `id` int(10) UNSIGNED NOT NULL,
  `senior_citizen_id` varchar(50) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `suffix` varchar(20) DEFAULT NULL,
  `sex` enum('male','female') NOT NULL,
  `birth_date` date NOT NULL,
  `civil_status_id` int(10) UNSIGNED NOT NULL,
  `place_of_birth` varchar(255) DEFAULT NULL,
  `address` text NOT NULL,
  `barangay` varchar(150) NOT NULL,
  `contact_number` varchar(30) DEFAULT NULL,
  `residence_since` year(4) DEFAULT NULL,
  `educational_attainment_id` int(10) UNSIGNED DEFAULT NULL,
  `former_occupation_id` int(10) UNSIGNED DEFAULT NULL,
  `company_name` varchar(150) DEFAULT NULL,
  `position` varchar(150) DEFAULT NULL,
  `pension_type_id` int(10) UNSIGNED DEFAULT NULL,
  `is_pwd` tinyint(1) NOT NULL DEFAULT 0,
  `is_bedridden` tinyint(1) NOT NULL DEFAULT 0,
  `remarks` text DEFAULT NULL,
  `signature_path` varchar(500) DEFAULT NULL,
  `thumb_mark_path` varchar(500) DEFAULT NULL,
  `status` enum('active','pending','unlocated','transferred','unclaimed','deceased') NOT NULL DEFAULT 'pending',
  `approval_status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `submitted_by` int(10) UNSIGNED DEFAULT NULL,
  `reviewed_by` int(10) UNSIGNED DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `senior_requirements`
--

CREATE TABLE `senior_requirements` (
  `id` int(10) UNSIGNED NOT NULL,
  `senior_id` int(10) UNSIGNED NOT NULL,
  `requirement_type` enum('birth_certificate','valid_id') NOT NULL,
  `status` enum('missing','submitted','approved','rejected') NOT NULL DEFAULT 'missing',
  `remarks` text DEFAULT NULL,
  `submitted_at` datetime DEFAULT NULL,
  `reviewed_by` int(10) UNSIGNED DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sms_messages`
--

CREATE TABLE `sms_messages` (
  `id` int(10) UNSIGNED NOT NULL,
  `created_by` int(10) UNSIGNED NOT NULL,
  `message` text NOT NULL,
  `sms_type` enum('event','announcement','important','general') NOT NULL DEFAULT 'general',
  `recipient_type` enum('all_active','selected','event_registrants') NOT NULL,
  `event_id` int(10) UNSIGNED DEFAULT NULL,
  `status` enum('draft','scheduled','sending','sent','failed','cancelled') NOT NULL DEFAULT 'draft',
  `scheduled_at` datetime DEFAULT NULL,
  `sent_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sms_recipients`
--

CREATE TABLE `sms_recipients` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `sms_message_id` int(10) UNSIGNED NOT NULL,
  `senior_id` int(10) UNSIGNED NOT NULL,
  `contact_number` varchar(30) NOT NULL,
  `delivery_status` enum('pending','sent','delivered','failed') NOT NULL DEFAULT 'pending',
  `sent_at` datetime DEFAULT NULL,
  `delivered_at` datetime DEFAULT NULL,
  `error_message` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `staff_profiles`
--

CREATE TABLE `staff_profiles` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `employee_id` varchar(50) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) NOT NULL,
  `suffix` varchar(20) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `contact_number` varchar(30) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `account_id` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','staff','senior') NOT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `role_changed_at` datetime DEFAULT NULL,
  `must_change_password` tinyint(1) NOT NULL DEFAULT 0,
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `support_requests`
--

CREATE TABLE `support_requests` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `requested_by` int(10) UNSIGNED NOT NULL,
  `subject` varchar(150) NOT NULL,
  `concern_type` varchar(40) NOT NULL,
  `message` text NOT NULL,
  `status` enum('open','in_progress','resolved') NOT NULL DEFAULT 'open',
  `handled_by` int(10) UNSIGNED DEFAULT NULL,
  `handled_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_support_requests_requester` (`requested_by`),
  KEY `idx_support_requests_status_created` (`status`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `admin_notification_reads` (
  `admin_user_id` int(10) unsigned NOT NULL,
  `notification_key` varchar(100) NOT NULL,
  `read_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`admin_user_id`, `notification_key`),
  KEY `idx_admin_notification_reads_read_at` (`admin_user_id`, `read_at`),
  CONSTRAINT `fk_admin_notification_reads_user` FOREIGN KEY (`admin_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `password_history`
--

CREATE TABLE `password_history` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int(10) UNSIGNED NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_password_history_user` (`user_id`),
  CONSTRAINT `fk_password_history_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `account_id`, `password`, `role`, `status`, `must_change_password`, `last_login`, `created_at`, `updated_at`) VALUES
(1, 'ADM-01', '$2y$10$xFnbtQsGt5ukk3CzuIJCCO9IGbc4wAVQHpV4JMuIVkmongQAI14Da', 'admin', 'active', 0, '2026-09-27 13:14:49', '2026-09-25 09:16:00', '2026-09-27 05:14:49');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_activity_user` (`user_id`),
  ADD KEY `idx_activity_module` (`module`),
  ADD KEY `idx_activity_reference` (`module`,`reference_id`),
  ADD KEY `idx_activity_created` (`created_at`);

--
-- Indexes for table `admin_profiles`
--
ALTER TABLE `admin_profiles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD UNIQUE KEY `employee_id` (`employee_id`);

--
-- Indexes for table `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_announcement_creator` (`created_by`),
  ADD KEY `idx_announcement_status` (`status`),
  ADD KEY `idx_announcement_expiry` (`expires_at`);

--
-- Indexes for table `approval_requests`
--
ALTER TABLE `approval_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_approval_requested_by` (`requested_by`),
  ADD KEY `fk_approval_reviewed_by` (`reviewed_by`),
  ADD KEY `idx_approval_status` (`status`),
  ADD KEY `idx_approval_reference` (`request_type`,`reference_id`);

--
-- Indexes for table `civil_statuses`
--
ALTER TABLE `civil_statuses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `barangays`
--
ALTER TABLE `barangays`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `educational_attainments`
--
ALTER TABLE `educational_attainments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `events`
--
ALTER TABLE `events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_event_creator` (`created_by`),
  ADD KEY `idx_event_date` (`event_date`),
  ADD KEY `idx_event_status` (`status`);

--
-- Indexes for table `event_attendance`
--
ALTER TABLE `event_attendance`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `registration_id` (`registration_id`),
  ADD KEY `fk_attendance_checker` (`checked_in_by`),
  ADD KEY `idx_attendance_event` (`event_id`),
  ADD KEY `idx_attendance_senior` (`senior_id`);

--
-- Indexes for table `event_registrations`
--
ALTER TABLE `event_registrations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_event_senior_registration` (`event_id`,`senior_id`),
  ADD UNIQUE KEY `permit_number` (`permit_number`),
  ADD KEY `fk_registration_senior` (`senior_id`),
  ADD KEY `idx_registration_status` (`status`);

--
-- Indexes for table `import_batches`
--
ALTER TABLE `import_batches`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_import_user` (`imported_by`),
  ADD KEY `idx_import_status` (`status`),
  ADD KEY `idx_import_created` (`created_at`);

--
-- Indexes for table `import_records`
--
ALTER TABLE `import_records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_import_record_senior` (`senior_id`),
  ADD KEY `idx_import_record_batch` (`import_batch_id`),
  ADD KEY `idx_import_record_status` (`status`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_notification_user_read` (`user_id`,`is_read`),
  ADD KEY `idx_notification_created` (`created_at`);

--
-- Indexes for table `occupations`
--
ALTER TABLE `occupations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `pension_types`
--
ALTER TABLE `pension_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `senior_profiles`
--
ALTER TABLE `senior_profiles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `senior_citizen_id` (`senior_citizen_id`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD KEY `fk_senior_civil_status` (`civil_status_id`),
  ADD KEY `fk_senior_education` (`educational_attainment_id`),
  ADD KEY `fk_senior_occupation` (`former_occupation_id`),
  ADD KEY `fk_senior_pension` (`pension_type_id`),
  ADD KEY `fk_senior_submitted_by` (`submitted_by`),
  ADD KEY `fk_senior_reviewed_by` (`reviewed_by`),
  ADD KEY `idx_senior_name` (`last_name`,`first_name`),
  ADD KEY `idx_senior_barangay` (`barangay`),
  ADD KEY `idx_senior_status` (`status`),
  ADD KEY `idx_senior_birth_date` (`birth_date`);

--
-- Indexes for table `senior_requirements`
--
ALTER TABLE `senior_requirements`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_senior_requirement` (`senior_id`,`requirement_type`),
  ADD KEY `fk_requirement_reviewer` (`reviewed_by`);

--
-- Indexes for table `sms_messages`
--
ALTER TABLE `sms_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_sms_creator` (`created_by`),
  ADD KEY `fk_sms_event` (`event_id`),
  ADD KEY `idx_sms_status` (`status`),
  ADD KEY `idx_sms_created` (`created_at`);

--
-- Indexes for table `sms_recipients`
--
ALTER TABLE `sms_recipients`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_sms_recipient_message` (`sms_message_id`),
  ADD KEY `idx_sms_recipient_senior` (`senior_id`),
  ADD KEY `idx_sms_delivery_status` (`delivery_status`);

--
-- Indexes for table `staff_profiles`
--
ALTER TABLE `staff_profiles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD UNIQUE KEY `employee_id` (`employee_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `account_id` (`account_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `admin_profiles`
--
ALTER TABLE `admin_profiles`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `approval_requests`
--
ALTER TABLE `approval_requests`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `civil_statuses`
--
ALTER TABLE `civil_statuses`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `barangays`
--
ALTER TABLE `barangays`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `educational_attainments`
--
ALTER TABLE `educational_attainments`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `events`
--
ALTER TABLE `events`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `event_attendance`
--
ALTER TABLE `event_attendance`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `event_registrations`
--
ALTER TABLE `event_registrations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `import_batches`
--
ALTER TABLE `import_batches`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `import_records`
--
ALTER TABLE `import_records`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `occupations`
--
ALTER TABLE `occupations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `pension_types`
--
ALTER TABLE `pension_types`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `senior_profiles`
--
ALTER TABLE `senior_profiles`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `senior_requirements`
--
ALTER TABLE `senior_requirements`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sms_messages`
--
ALTER TABLE `sms_messages`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sms_recipients`
--
ALTER TABLE `sms_recipients`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `staff_profiles`
--
ALTER TABLE `staff_profiles`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD CONSTRAINT `fk_activity_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `admin_profiles`
--
ALTER TABLE `admin_profiles`
  ADD CONSTRAINT `fk_admin_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `announcements`
--
ALTER TABLE `announcements`
  ADD CONSTRAINT `fk_announcement_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `approval_requests`
--
ALTER TABLE `approval_requests`
  ADD CONSTRAINT `fk_approval_requested_by` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_approval_reviewed_by` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `events`
--
ALTER TABLE `events`
  ADD CONSTRAINT `fk_event_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `event_attendance`
--
ALTER TABLE `event_attendance`
  ADD CONSTRAINT `fk_attendance_checker` FOREIGN KEY (`checked_in_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_attendance_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_attendance_registration` FOREIGN KEY (`registration_id`) REFERENCES `event_registrations` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_attendance_senior` FOREIGN KEY (`senior_id`) REFERENCES `senior_profiles` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `event_registrations`
--
ALTER TABLE `event_registrations`
  ADD CONSTRAINT `fk_registration_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_registration_senior` FOREIGN KEY (`senior_id`) REFERENCES `senior_profiles` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `import_batches`
--
ALTER TABLE `import_batches`
  ADD CONSTRAINT `fk_import_user` FOREIGN KEY (`imported_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `import_records`
--
ALTER TABLE `import_records`
  ADD CONSTRAINT `fk_import_record_batch` FOREIGN KEY (`import_batch_id`) REFERENCES `import_batches` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_import_record_senior` FOREIGN KEY (`senior_id`) REFERENCES `senior_profiles` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notification_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `senior_profiles`
--
ALTER TABLE `senior_profiles`
  ADD CONSTRAINT `fk_senior_civil_status` FOREIGN KEY (`civil_status_id`) REFERENCES `civil_statuses` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_senior_education` FOREIGN KEY (`educational_attainment_id`) REFERENCES `educational_attainments` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_senior_occupation` FOREIGN KEY (`former_occupation_id`) REFERENCES `occupations` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_senior_pension` FOREIGN KEY (`pension_type_id`) REFERENCES `pension_types` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_senior_reviewed_by` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_senior_submitted_by` FOREIGN KEY (`submitted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_senior_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `senior_requirements`
--
ALTER TABLE `senior_requirements`
  ADD CONSTRAINT `fk_requirement_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_requirement_senior` FOREIGN KEY (`senior_id`) REFERENCES `senior_profiles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `sms_messages`
--
ALTER TABLE `sms_messages`
  ADD CONSTRAINT `fk_sms_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_sms_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `sms_recipients`
--
ALTER TABLE `sms_recipients`
  ADD CONSTRAINT `fk_sms_recipient_message` FOREIGN KEY (`sms_message_id`) REFERENCES `sms_messages` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_sms_recipient_senior` FOREIGN KEY (`senior_id`) REFERENCES `senior_profiles` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `staff_profiles`
--
ALTER TABLE `staff_profiles`
  ADD CONSTRAINT `fk_staff_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `support_requests`
--
ALTER TABLE `support_requests`
  ADD CONSTRAINT `fk_support_requests_requester` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_support_requests_handler` FOREIGN KEY (`handled_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
