-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Feb 24, 2026 at 06:25 AM
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
-- Database: `myhr`
--

-- --------------------------------------------------------

--
-- Table structure for table `announcements`
--

CREATE TABLE `announcements` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `posted_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `announcements`
--

INSERT INTO `announcements` (`id`, `title`, `message`, `posted_by`, `created_at`, `updated_at`) VALUES
(1, 'Foundation', 'FOUNDATION SHIRT ORDER\r\nFor those who have not yet filled out the form for the Foundation Shirt,\r\nPLEASE DO SO TODAY.', 3, '2026-02-24 02:12:25', NULL),
(2, 'Edsa people power', 'walang pasok', 3, '2026-02-24 02:49:35', NULL),
(3, 'birthday ni enteng', 'happy birthday', 3, '2026-02-24 03:48:54', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `calendar_events`
--

CREATE TABLE `calendar_events` (
  `id` int(11) NOT NULL,
  `event_date` date NOT NULL,
  `title` varchar(255) NOT NULL,
  `type` enum('holiday','event') NOT NULL DEFAULT 'holiday',
  `description` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `calendar_events`
--

INSERT INTO `calendar_events` (`id`, `event_date`, `title`, `type`, `description`, `created_by`, `created_at`) VALUES
(1, '2026-02-25', 'EDSA', 'holiday', NULL, 3, '2026-02-24 02:20:24'),
(3, '2026-02-26', 'birthday ni enteng', 'holiday', NULL, 3, '2026-02-24 03:49:10');

-- --------------------------------------------------------

--
-- Table structure for table `department_heads`
--

CREATE TABLE `department_heads` (
  `id` int(11) NOT NULL,
  `department` varchar(100) NOT NULL,
  `head_user_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `leave_requests`
--

CREATE TABLE `leave_requests` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `department` varchar(255) NOT NULL,
  `leave_type` enum('sick','incentive','emergency') NOT NULL,
  `date_from` date NOT NULL,
  `date_to` date NOT NULL,
  `days` int(11) NOT NULL,
  `reason` text NOT NULL,
  `med_cert_file` varchar(255) DEFAULT NULL,
  `status` enum('pending_head','rejected_head','approved_head','pending_hr_receive','rejected_hr','received') NOT NULL DEFAULT 'pending_head',
  `head_id` int(11) DEFAULT NULL,
  `head_action_at` datetime DEFAULT NULL,
  `head_note` varchar(255) DEFAULT NULL,
  `hr_id` int(11) DEFAULT NULL,
  `hr_received_at` datetime DEFAULT NULL,
  `hr_note` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `leave_requests`
--

INSERT INTO `leave_requests` (`id`, `user_id`, `department`, `leave_type`, `date_from`, `date_to`, `days`, `reason`, `med_cert_file`, `status`, `head_id`, `head_action_at`, `head_note`, `hr_id`, `hr_received_at`, `hr_note`, `created_at`) VALUES
(1, 6, 'Administration', 'sick', '2026-02-25', '2026-02-25', 1, 'katam', NULL, 'received', 3, '2026-02-24 12:51:47', '', 3, '2026-02-24 12:54:14', NULL, '2026-02-24 12:48:55');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `google_id` varchar(50) NOT NULL,
  `email` varchar(150) NOT NULL,
  `full_name` varchar(150) DEFAULT NULL,
  `emp_id` varchar(50) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `role` enum('employee','head','hr') DEFAULT 'employee',
  `profile_completed` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `approval_status` enum('pending_head','pending_hr','approved','rejected') DEFAULT 'pending_head',
  `approved_by_head` int(11) DEFAULT NULL,
  `approved_by_hr` int(11) DEFAULT NULL,
  `approved_head_at` datetime DEFAULT NULL,
  `approved_hr_at` datetime DEFAULT NULL,
  `rejection_reason` varchar(255) DEFAULT NULL,
  `sick_leave_balance` int(11) NOT NULL DEFAULT 5,
  `incentive_leave_balance` int(11) NOT NULL DEFAULT 5,
  `emergency_leave_balance` int(11) NOT NULL DEFAULT 5
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `google_id`, `email`, `full_name`, `emp_id`, `department`, `role`, `profile_completed`, `created_at`, `approval_status`, `approved_by_head`, `approved_by_hr`, `approved_head_at`, `approved_hr_at`, `rejection_reason`, `sick_leave_balance`, `incentive_leave_balance`, `emergency_leave_balance`) VALUES
(3, '103549179565560747870', 'elmersulitas@immaculada.edu.ph', 'Elmer Jr. Sulitas', '24-0078', 'Administration', 'hr', 1, '2026-02-23 14:54:44', 'approved', 0, 1, '2026-02-23 22:57:38', '2026-02-23 22:58:46', NULL, 5, 5, 5),
(5, '113384780449715213453', 'mariamesulitas@immaculada.edu.ph', 'Maria Me Sulitas', '24-001', 'Administration', 'employee', 1, '2026-02-23 15:03:27', 'approved', 3, 3, '2026-02-24 09:57:30', '2026-02-24 09:59:24', NULL, 5, 5, 5),
(6, '110892760875160041440', 'madriagajohnvincent@immaculada.edu.ph', 'John Vincent Madriaga', '23-005', 'Administration', 'employee', 1, '2026-02-24 01:27:17', 'approved', 3, 3, '2026-02-24 10:54:09', '2026-02-24 12:42:31', NULL, 4, 5, 5);

-- --------------------------------------------------------

--
-- Table structure for table `user_files`
--

CREATE TABLE `user_files` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `doc_type` varchar(50) DEFAULT NULL,
  `original_name` varchar(255) NOT NULL,
  `stored_name` varchar(255) NOT NULL,
  `mime_type` varchar(100) NOT NULL,
  `file_size` int(11) NOT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_files`
--

INSERT INTO `user_files` (`id`, `user_id`, `doc_type`, `original_name`, `stored_name`, `mime_type`, `file_size`, `uploaded_at`) VALUES
(1, 3, 'resume', '1_3fe54ecff5f3dcc1111130e0.docx', '3_2f275ccba512e357fc17102a.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 24106, '2026-02-23 14:55:44'),
(2, 3, 'sss', 'HR system workflow infographic.png', '3_64246ce954e73c31c762fa56.png', 'image/png', 516452, '2026-02-23 14:55:44'),
(5, 5, 'resume', '1_3fe54ecff5f3dcc1111130e0.docx', '5_3277610a7cb1d79878f07487.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 24106, '2026-02-23 15:04:17'),
(6, 5, 'sss', 'HR system workflow infographic.png', '5_52eea7d901f4f9c6e3cc58f7.png', 'image/png', 516452, '2026-02-23 15:04:17'),
(7, 6, 'resume', '2 BAI 2.docx', '6_eed198b9862b4000447f4f65.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 162824, '2026-02-24 02:53:48'),
(8, 6, 'sss', 'VGD  BOOKKEEPING Assessment AREA.png', '6_da616f3516f6b7d1d87276a5.png', 'image/png', 1151299, '2026-02-24 02:53:48');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `posted_by` (`posted_by`);

--
-- Indexes for table `calendar_events`
--
ALTER TABLE `calendar_events`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_date_type` (`event_date`,`type`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `department_heads`
--
ALTER TABLE `department_heads`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `department` (`department`),
  ADD KEY `head_user_id` (`head_user_id`);

--
-- Indexes for table `leave_requests`
--
ALTER TABLE `leave_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `user_files`
--
ALTER TABLE `user_files`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `calendar_events`
--
ALTER TABLE `calendar_events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `department_heads`
--
ALTER TABLE `department_heads`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `leave_requests`
--
ALTER TABLE `leave_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `user_files`
--
ALTER TABLE `user_files`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `announcements`
--
ALTER TABLE `announcements`
  ADD CONSTRAINT `announcements_ibfk_1` FOREIGN KEY (`posted_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `calendar_events`
--
ALTER TABLE `calendar_events`
  ADD CONSTRAINT `calendar_events_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `department_heads`
--
ALTER TABLE `department_heads`
  ADD CONSTRAINT `department_heads_ibfk_1` FOREIGN KEY (`head_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `leave_requests`
--
ALTER TABLE `leave_requests`
  ADD CONSTRAINT `leave_requests_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_files`
--
ALTER TABLE `user_files`
  ADD CONSTRAINT `user_files_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
