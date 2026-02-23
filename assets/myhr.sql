-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Feb 23, 2026 at 04:08 PM
-- Server version: 10.4.27-MariaDB
-- PHP Version: 7.4.33

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
-- Table structure for table `department_heads`
--

CREATE TABLE `department_heads` (
  `id` int(11) NOT NULL,
  `department` varchar(100) NOT NULL,
  `head_user_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  `rejection_reason` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `google_id`, `email`, `full_name`, `emp_id`, `department`, `role`, `profile_completed`, `created_at`, `approval_status`, `approved_by_head`, `approved_by_hr`, `approved_head_at`, `approved_hr_at`, `rejection_reason`) VALUES
(3, '103549179565560747870', 'elmersulitas@immaculada.edu.ph', 'Elmer Jr. Sulitas', '24-0078', 'Administration', 'head', 1, '2026-02-23 14:54:44', 'approved', 0, 1, '2026-02-23 22:57:38', '2026-02-23 22:58:46', NULL),
(5, '113384780449715213453', 'mariamesulitas@immaculada.edu.ph', 'Maria Me Sulitas', '24-001', 'Administration', 'employee', 1, '2026-02-23 15:03:27', 'pending_head', NULL, NULL, NULL, NULL, NULL);

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
(6, 5, 'sss', 'HR system workflow infographic.png', '5_52eea7d901f4f9c6e3cc58f7.png', 'image/png', 516452, '2026-02-23 15:04:17');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `department_heads`
--
ALTER TABLE `department_heads`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `department` (`department`),
  ADD KEY `head_user_id` (`head_user_id`);

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
-- AUTO_INCREMENT for table `department_heads`
--
ALTER TABLE `department_heads`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `user_files`
--
ALTER TABLE `user_files`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `department_heads`
--
ALTER TABLE `department_heads`
  ADD CONSTRAINT `department_heads_ibfk_1` FOREIGN KEY (`head_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_files`
--
ALTER TABLE `user_files`
  ADD CONSTRAINT `user_files_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
