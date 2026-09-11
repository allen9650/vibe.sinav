-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 11, 2026 at 06:49 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `ptm`
--

-- --------------------------------------------------------

--
-- Table structure for table `assessments`
--

CREATE TABLE `assessments` (
  `id` int(10) UNSIGNED NOT NULL,
  `campus_id` int(10) UNSIGNED NOT NULL,
  `subject_id` int(10) UNSIGNED NOT NULL,
  `created_by` int(10) UNSIGNED NOT NULL,
  `title` varchar(200) NOT NULL,
  `assessment_type` varchar(50) NOT NULL DEFAULT 'quiz',
  `target_class` varchar(50) NOT NULL,
  `section` varchar(50) DEFAULT 'Section A',
  `description` text DEFAULT NULL,
  `duration_minutes` int(10) UNSIGNED NOT NULL DEFAULT 30,
  `start_date` date DEFAULT NULL,
  `start_time` time DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `total_marks` decimal(6,2) NOT NULL DEFAULT 0.00,
  `passing_marks` decimal(6,2) DEFAULT NULL,
  `shuffle_questions` tinyint(1) NOT NULL DEFAULT 1,
  `shuffle_options` tinyint(1) NOT NULL DEFAULT 1,
  `instructions` text DEFAULT NULL,
  `rules_guidelines` text DEFAULT NULL,
  `passing_percentage` decimal(5,2) NOT NULL DEFAULT 40.00,
  `device_limit` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `is_result_published` tinyint(1) NOT NULL DEFAULT 0,
  `status` enum('draft','published','archived') NOT NULL DEFAULT 'draft',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `assessments`
--

INSERT INTO `assessments` (`id`, `campus_id`, `subject_id`, `created_by`, `title`, `assessment_type`, `target_class`, `section`, `description`, `duration_minutes`, `start_date`, `start_time`, `end_date`, `end_time`, `total_marks`, `passing_marks`, `shuffle_questions`, `shuffle_options`, `instructions`, `rules_guidelines`, `passing_percentage`, `device_limit`, `is_result_published`, `status`, `created_at`, `updated_at`) VALUES
(46, 1, 1, 1, 'Computer Assesment Dated 9-9-26', 'class_test', 'General', 'NA', '', 10, NULL, NULL, NULL, NULL, 40.00, 25.00, 1, 1, '1. Read each question carefully before selecting or entering your answer.\r\n2. You can navigate between questions freely using the question palette on the right.\r\n3. Your answers are automatically saved in real time to the server.\r\n4. Complete all required questions before clicking \'Submit Assessment\'.\r\n5. Once submitted, answers cannot be edited or re-attempted.', '1. Fullscreen Enforcement: You must remain in fullscreen mode throughout the examination.\r\n2. Tab Switching Prohibited: Switching browser tabs, minimizing the window, or opening external apps is logged as a violation.\r\n3. Security Locks: Clipboard copy/cut/paste and right-click menus are disabled.\r\n4. Timer Rule: The test timer runs server-side and will auto-submit when time expires.\r\n5. Academic Integrity: Any detected malpractice or excessive violations will lead to immediate disqualification.', 62.50, 0, 1, 'draft', '2026-09-09 09:17:38', '2026-09-11 12:57:47'),
(48, 1, 1, 1, 'hjhj (Edited Test)', 'quiz', 'Grade 8', 'Section A', 'Test description', 6, NULL, NULL, NULL, NULL, 30.00, 22.00, 1, 1, '1. Read each question carefully before selecting or entering your answer.\r\n2. You can navigate between questions freely using the question palette on the right.\r\n3. Your answers are automatically saved in real time to the server.\r\n4. Complete all required questions before clicking \'Submit Assessment\'.\r\n5. Once submitted, answers cannot be edited or re-attempted.', '1. Fullscreen Enforcement: You must remain in fullscreen mode throughout the examination.\r\n2. Tab Switching Prohibited: Switching browser tabs, minimizing the window, or opening external apps is logged as a violation.\r\n3. Security Locks: Clipboard copy/cut/paste and right-click menus are disabled.\r\n4. Timer Rule: The test timer runs server-side and will auto-submit when time expires.\r\n5. Academic Integrity: Any detected malpractice or excessive violations will lead to immediate disqualification.', 55.00, 0, 1, 'draft', '2026-09-09 12:14:52', '2026-09-11 13:45:02');

-- --------------------------------------------------------

--
-- Table structure for table `assessment_answers`
--

CREATE TABLE `assessment_answers` (
  `id` int(10) UNSIGNED NOT NULL,
  `attempt_id` int(10) UNSIGNED NOT NULL,
  `question_id` int(10) UNSIGNED NOT NULL,
  `selected_option_ids` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Array of option IDs for single/multiple choice/true_false' CHECK (json_valid(`selected_option_ids`)),
  `text_answer` text DEFAULT NULL COMMENT 'Normalized text response for fill_blank',
  `is_correct` tinyint(1) DEFAULT NULL,
  `needs_review` tinyint(1) NOT NULL DEFAULT 0,
  `marks_obtained` decimal(4,2) NOT NULL DEFAULT 0.00,
  `teacher_comment` text DEFAULT NULL,
  `evaluated_by` int(10) UNSIGNED DEFAULT NULL,
  `evaluated_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `assessment_answers`
--

INSERT INTO `assessment_answers` (`id`, `attempt_id`, `question_id`, `selected_option_ids`, `text_answer`, `is_correct`, `needs_review`, `marks_obtained`, `teacher_comment`, `evaluated_by`, `evaluated_at`, `updated_at`) VALUES
(145, 46, 68, '[172,170,171]', NULL, 1, 0, 10.00, NULL, NULL, NULL, '2026-09-09 09:18:56'),
(148, 46, 66, '[163,162,165]', NULL, 1, 0, 10.00, NULL, NULL, NULL, '2026-09-09 09:18:56'),
(151, 46, 69, '[176,175,174]', NULL, 0, 0, 0.00, NULL, NULL, NULL, '2026-09-09 09:18:56'),
(154, 46, 67, '[166]', NULL, 1, 0, 10.00, NULL, NULL, NULL, '2026-09-09 09:18:56'),
(159, 47, 66, '[162]', NULL, 0, 0, 0.00, NULL, NULL, NULL, '2026-09-09 12:10:23'),
(160, 47, 68, '[172]', NULL, 0, 0, 0.00, NULL, NULL, NULL, '2026-09-09 12:10:23'),
(161, 47, 69, '[174]', NULL, 1, 0, 10.00, NULL, NULL, NULL, '2026-09-09 12:10:23'),
(166, 47, 67, '[167]', NULL, 0, 0, 0.00, NULL, NULL, NULL, '2026-09-09 12:10:23'),
(171, 48, 69, '[175,174,176]', NULL, 0, 0, 0.00, NULL, NULL, NULL, '2026-09-09 12:17:33'),
(174, 48, 66, '[165,163,162]', NULL, 1, 0, 10.00, NULL, NULL, NULL, '2026-09-09 12:17:33'),
(177, 48, 67, '[166]', NULL, 1, 0, 10.00, NULL, NULL, NULL, '2026-09-09 12:17:33'),
(178, 48, 68, '[170,172,171]', NULL, 1, 0, 10.00, NULL, NULL, NULL, '2026-09-09 12:17:33'),
(185, 49, 67, '[166]', NULL, 1, 0, 10.00, NULL, NULL, NULL, '2026-09-11 11:17:13'),
(186, 49, 66, '[165,163,162]', NULL, 1, 0, 10.00, NULL, NULL, NULL, '2026-09-11 11:17:13'),
(187, 49, 69, '[174,175,176]', NULL, 0, 0, 0.00, NULL, NULL, NULL, '2026-09-11 11:17:13'),
(199, 49, 68, NULL, NULL, 0, 0, 0.00, NULL, NULL, NULL, '2026-09-11 11:17:13'),
(200, 51, 68, '[172,171,173]', NULL, 0, 0, 0.00, NULL, NULL, NULL, '2026-09-11 13:28:14'),
(203, 51, 67, '[166]', NULL, 1, 0, 10.00, NULL, NULL, NULL, '2026-09-11 13:28:14'),
(204, 51, 66, '[163,165,162]', NULL, 1, 0, 10.00, NULL, NULL, NULL, '2026-09-11 13:28:14'),
(212, 52, 68, '[170,173,172]', NULL, 0, 0, 0.00, NULL, NULL, NULL, '2026-09-11 13:30:30'),
(215, 52, 67, '[166]', NULL, 1, 0, 10.00, NULL, NULL, NULL, '2026-09-11 13:30:30'),
(216, 52, 66, '[162,165,163]', NULL, 1, 0, 10.00, NULL, NULL, NULL, '2026-09-11 13:30:30');

-- --------------------------------------------------------

--
-- Table structure for table `assessment_attempts`
--

CREATE TABLE `assessment_attempts` (
  `id` int(10) UNSIGNED NOT NULL,
  `assessment_id` int(10) UNSIGNED NOT NULL,
  `station_id` int(10) UNSIGNED DEFAULT NULL,
  `student_name` varchar(100) NOT NULL,
  `student_roll_no` varchar(50) NOT NULL,
  `student_class` varchar(50) NOT NULL,
  `started_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expected_end_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `submitted_at` timestamp NULL DEFAULT NULL,
  `status` enum('in_progress','completed','timed_out','disqualified','abandoned') NOT NULL DEFAULT 'in_progress',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `needs_review` tinyint(1) NOT NULL DEFAULT 0,
  `is_reviewed` tinyint(1) NOT NULL DEFAULT 0,
  `reviewed_by` int(10) UNSIGNED DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `assessment_attempts`
--

INSERT INTO `assessment_attempts` (`id`, `assessment_id`, `station_id`, `student_name`, `student_roll_no`, `student_class`, `started_at`, `expected_end_at`, `submitted_at`, `status`, `created_at`, `needs_review`, `is_reviewed`, `reviewed_by`, `reviewed_at`) VALUES
(46, 46, 79, 'Rozina', '11', 'General', '2026-09-09 09:18:30', '2026-09-09 09:28:30', '2026-09-09 09:18:56', 'completed', '2026-09-09 09:18:30', 0, 1, 1, '2026-09-11 11:26:54'),
(47, 46, 74, 'Sarfraz', '18', 'General', '2026-09-09 12:10:01', '2026-09-09 12:20:01', '2026-09-09 12:10:23', 'completed', '2026-09-09 12:10:01', 0, 1, NULL, NULL),
(48, 48, 80, 'Ahsan', '660', 'Grade 8', '2026-09-09 12:17:18', '2026-09-09 12:23:18', '2026-09-09 12:17:33', 'completed', '2026-09-09 12:17:18', 0, 1, 1, '2026-09-11 14:07:35'),
(49, 46, 72, 'Ahsan', '7676', 'General', '2026-09-11 11:07:17', '2026-09-11 11:17:17', '2026-09-11 11:17:13', 'completed', '2026-09-11 11:07:17', 0, 1, NULL, NULL),
(51, 48, 81, 'AHsan', '2026', 'Grade 8', '2026-09-11 13:27:53', '2026-09-11 13:33:53', '2026-09-11 13:28:14', 'completed', '2026-09-11 13:27:53', 0, 1, NULL, NULL),
(52, 48, 82, 'Raza', '22', 'Grade 8', '2026-09-11 13:30:12', '2026-09-11 13:36:12', '2026-09-11 13:30:30', 'completed', '2026-09-11 13:30:12', 0, 1, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `assessment_questions`
--

CREATE TABLE `assessment_questions` (
  `id` int(10) UNSIGNED NOT NULL,
  `assessment_id` int(10) UNSIGNED NOT NULL,
  `question_id` int(10) UNSIGNED NOT NULL,
  `question_order` int(10) UNSIGNED NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `assessment_questions`
--

INSERT INTO `assessment_questions` (`id`, `assessment_id`, `question_id`, `question_order`) VALUES
(84, 46, 69, 1),
(85, 46, 68, 2),
(86, 46, 67, 3),
(87, 46, 66, 4),
(89, 48, 67, 1),
(90, 48, 66, 2),
(91, 48, 68, 3);

-- --------------------------------------------------------

--
-- Table structure for table `assessment_results`
--

CREATE TABLE `assessment_results` (
  `id` int(10) UNSIGNED NOT NULL,
  `attempt_id` int(10) UNSIGNED NOT NULL,
  `total_marks` decimal(6,2) NOT NULL,
  `obtained_marks` decimal(6,2) NOT NULL,
  `percentage` decimal(5,2) NOT NULL,
  `pass_fail` enum('pass','fail') NOT NULL,
  `generated_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `assessment_results`
--

INSERT INTO `assessment_results` (`id`, `attempt_id`, `total_marks`, `obtained_marks`, `percentage`, `pass_fail`, `generated_at`) VALUES
(49, 46, 40.00, 30.00, 75.00, 'pass', '2026-09-11 11:26:54'),
(50, 47, 40.00, 10.00, 25.00, 'fail', '2026-09-09 12:10:23'),
(51, 48, 40.00, 30.00, 75.00, 'pass', '2026-09-11 14:07:35'),
(52, 49, 40.00, 20.00, 50.00, 'fail', '2026-09-11 11:17:13'),
(54, 51, 30.00, 20.00, 66.67, 'pass', '2026-09-11 13:28:14'),
(55, 52, 30.00, 20.00, 66.67, 'pass', '2026-09-11 13:30:30');

-- --------------------------------------------------------

--
-- Table structure for table `assessment_stations`
--

CREATE TABLE `assessment_stations` (
  `id` int(10) UNSIGNED NOT NULL,
  `assessment_id` int(10) UNSIGNED NOT NULL,
  `station_id` int(10) UNSIGNED NOT NULL,
  `status` enum('idle','in_progress','submitted') NOT NULL DEFAULT 'idle',
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `assessment_stations`
--

INSERT INTO `assessment_stations` (`id`, `assessment_id`, `station_id`, `status`, `assigned_at`, `updated_at`) VALUES
(398, 46, 79, 'submitted', '2026-09-09 09:18:30', '2026-09-09 09:18:56'),
(399, 46, 74, 'submitted', '2026-09-09 12:10:01', '2026-09-09 12:10:23'),
(400, 48, 80, 'submitted', '2026-09-09 12:17:18', '2026-09-09 12:17:33'),
(401, 46, 72, 'submitted', '2026-09-11 11:07:17', '2026-09-11 11:17:13'),
(402, 48, 81, 'submitted', '2026-09-11 13:27:52', '2026-09-11 13:28:14'),
(403, 48, 82, 'submitted', '2026-09-11 13:30:12', '2026-09-11 13:30:30');

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `module` varchar(50) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_values`)),
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_values`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `module`, `description`, `old_values`, `new_values`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, NULL, 'login_failed', 'auth', 'Failed login attempt for username: asd', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-02 15:28:50'),
(2, NULL, 'login_failed', 'auth', 'Failed login attempt for username: admin', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-02 15:29:02'),
(3, NULL, 'login_failed', 'auth', 'Failed login attempt for username: admin', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-02 15:29:19'),
(4, NULL, 'login_failed', 'auth', 'Failed login attempt for username: admin', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-02 15:29:30'),
(5, NULL, 'login_failed', 'auth', 'Failed login attempt for username: admin', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-02 15:33:31'),
(6, 1, 'login_failed', 'auth', 'Wrong password for user: admin', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-02 15:41:22'),
(7, 1, 'login_failed', 'auth', 'Wrong password for user: admin', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-02 15:41:27'),
(8, 1, 'login_success', 'auth', 'User logged in: admin', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-02 15:41:38'),
(9, 1, 'logout', 'auth', 'User logged out: admin', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-02 15:42:27'),
(10, 1, 'login_failed', 'auth', 'Wrong password for user: admin', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-02 18:06:10'),
(11, 1, 'login_success', 'auth', 'User logged in: admin', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-02 18:06:27'),
(12, 1, 'logout', 'auth', 'User logged out: admin', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-02 18:08:40'),
(13, 1, 'login_success', 'auth', 'User logged in: admin', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-02 18:09:39'),
(14, 1, 'logout', 'auth', 'User logged out: admin', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-02 18:09:59'),
(15, 1, 'login_success', 'auth', 'User logged in: admin', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-02 18:10:07'),
(16, 1, 'logout', 'auth', 'User logged out: admin', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-02 18:10:11'),
(17, 1, 'question_created', 'questions', 'Created question ID: 1 (single_choice)', NULL, NULL, NULL, NULL, '2026-09-02 18:37:59'),
(18, 2, 'question_created', 'questions', 'Created question ID: 2 (multiple_choice)', NULL, NULL, NULL, NULL, '2026-09-02 18:37:59'),
(25, 1, 'assessment_created', 'assessments', 'Created assessment: \'Computer Fundamentals & Networking Midterm Quiz\' (ID: 1)', NULL, NULL, NULL, NULL, '2026-09-02 18:42:24'),
(26, NULL, 'assessment_published', 'assessments', 'Published assessment ID: 1', NULL, NULL, NULL, NULL, '2026-09-02 18:42:24'),
(27, 1, 'assessment_created', 'assessments', 'Created assessment: \'Computer Skills Assessment (Quiz + Typing)\' (ID: 2)', NULL, NULL, NULL, NULL, '2026-09-02 18:42:24'),
(28, NULL, 'assessment_published', 'assessments', 'Published assessment ID: 2', NULL, NULL, NULL, NULL, '2026-09-02 18:42:24'),
(29, 2, 'login_success', 'auth', 'User logged in: teacher', NULL, NULL, NULL, NULL, '2026-09-02 18:46:49'),
(30, 2, 'login_success', 'auth', 'User logged in: teacher', NULL, NULL, NULL, NULL, '2026-09-02 18:48:25'),
(31, 2, 'login_success', 'auth', 'User logged in: teacher', NULL, NULL, NULL, NULL, '2026-09-02 18:48:36'),
(32, 2, 'login_success', 'auth', 'User logged in: teacher', NULL, NULL, NULL, NULL, '2026-09-02 18:48:53'),
(33, 1, 'login_success', 'auth', 'User logged in: admin', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-02 18:51:32'),
(34, 1, 'login_success', 'auth', 'User logged in: admin', NULL, NULL, '127.0.0.1', 'TestBrowser', '2026-09-02 18:53:33'),
(35, 1, 'candidate_created', 'candidates', 'Registered candidate \'Ahsan Raza\' (MITC-2026-0001) in competition #1', NULL, '{\"competition_id\":1,\"registration_number\":\"MITC-2026-0001\",\"roll_number\":\"102\",\"seat_number\":\"\",\"full_name\":\"Ahsan Raza\",\"father_name\":\"\",\"cnic\":\"\",\"phone\":\"\",\"email\":\"arkolachi190@gmail.com\",\"gender\":\"male\",\"course\":\"CIT\",\"batch\":\"2026-A\",\"shift\":\"morning\",\"branch\":\"Main Campus\",\"status\":\"registered\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-02 18:56:13'),
(36, 1, 'logout', 'auth', 'User logged out: admin', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-02 18:59:51'),
(37, 1, 'question_created', 'questions', 'Created question ID: 9 (short_answer)', NULL, NULL, NULL, NULL, '2026-09-03 07:29:24'),
(38, 2, 'login_success', 'auth', 'User logged in: teacher', NULL, NULL, NULL, NULL, '2026-09-03 07:30:29'),
(39, 2, 'login_success', 'auth', 'User logged in: teacher', NULL, NULL, NULL, NULL, '2026-09-03 07:31:00'),
(40, 2, 'login_success', 'auth', 'User logged in: teacher', NULL, NULL, NULL, NULL, '2026-09-03 07:31:15'),
(41, 2, 'login_success', 'auth', 'User logged in: teacher', NULL, NULL, NULL, NULL, '2026-09-03 07:32:31'),
(42, 2, 'login_success', 'auth', 'User logged in: teacher', NULL, NULL, NULL, NULL, '2026-09-03 07:32:39'),
(43, 2, 'login_success', 'auth', 'User logged in: teacher', NULL, NULL, NULL, NULL, '2026-09-03 07:32:54'),
(44, 2, 'login_success', 'auth', 'User logged in: teacher', NULL, NULL, NULL, NULL, '2026-09-03 07:33:10'),
(45, 1, 'login_success', 'auth', 'User logged in: admin', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-03 07:34:08'),
(46, 1, 'assessment_created', 'assessments', 'Created assessment: \'Computer Fundamentals\' (ID: 3)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-03 07:36:46'),
(47, 1, 'candidate_updated', 'candidates', 'Updated candidate #6 \'Workstation 1\' (LAB-PC-01)', '{\"id\":6,\"competition_id\":1,\"registration_number\":\"LAB-PC-01\",\"user_id\":null,\"full_name\":\"Zubair Ahmed\",\"father_name\":null,\"cnic\":null,\"email\":null,\"phone\":null,\"gender\":null,\"course\":null,\"batch\":null,\"shift\":null,\"branch\":null,\"roll_number\":\"PC-01\",\"seat_number\":null,\"photo\":null,\"status\":\"registered\",\"registered_by\":null,\"created_at\":\"2026-09-03 12:21:12\",\"updated_at\":\"2026-09-03 12:30:29\"}', '{\"competition_id\":1,\"registration_number\":\"LAB-PC-01\",\"roll_number\":\"PC-01\",\"seat_number\":\"\",\"full_name\":\"Workstation 1\",\"father_name\":\"\",\"cnic\":\"\",\"phone\":\"\",\"email\":\"\",\"gender\":\"male\",\"course\":\"\",\"batch\":\"\",\"shift\":\"morning\",\"branch\":\"\",\"status\":\"registered\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-03 07:37:58'),
(48, 1, 'assessment_published', 'assessments', 'Published assessment ID: 3', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-03 07:39:08'),
(49, 1, 'login_success', 'auth', 'User logged in: admin', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.21996.1', '2026-09-03 08:00:37'),
(50, 2, 'login_success', 'auth', 'User logged in: teacher', NULL, NULL, NULL, NULL, '2026-09-03 08:02:02'),
(51, 2, 'login_success', 'auth', 'User logged in: teacher', NULL, NULL, NULL, NULL, '2026-09-03 08:02:07'),
(52, 1, 'attendance_marked', 'attendance', 'Marked candidate \'Ahsan Raza\' (MITC-2026-0001) as Absent', NULL, '{\"candidate_id\":5,\"status\":\"absent\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-03 09:00:56'),
(53, 1, 'attendance_marked', 'attendance', 'Marked candidate \'Ahsan Raza\' (REG-2026-001) as Present', NULL, '{\"candidate_id\":1,\"status\":\"present\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-03 09:00:59'),
(54, 1, 'assessment_updated', 'assessments', 'Updated assessment ID: 3', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-03 09:03:06'),
(55, 1, 'login_success', 'auth', 'User logged in: admin', NULL, NULL, NULL, NULL, '2026-09-03 10:49:58'),
(56, 2, 'login_success', 'auth', 'User logged in: teacher', NULL, NULL, NULL, NULL, '2026-09-03 10:50:07'),
(57, 1, 'login_success', 'auth', 'User logged in: admin', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.21996.1', '2026-09-03 10:51:26'),
(58, 1, 'login_success', 'auth', 'User logged in: admin', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.21996.1', '2026-09-03 10:51:45'),
(59, 1, 'login_success', 'auth', 'User logged in: admin', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.21996.1', '2026-09-03 10:52:13'),
(60, 2, 'login_success', 'auth', 'User logged in: teacher', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.21996.1', '2026-09-03 10:52:13'),
(61, 1, 'login_success', 'auth', 'User logged in: admin', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.21996.1', '2026-09-03 10:52:38'),
(62, 2, 'login_success', 'auth', 'User logged in: teacher', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.21996.1', '2026-09-03 10:52:38'),
(63, 1, 'login_success', 'auth', 'User logged in: admin', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-03 10:53:21'),
(64, 1, 'logout', 'auth', 'User logged out: admin', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-03 10:54:02'),
(65, 1, 'login_success', 'auth', 'User logged in: admin', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-04 10:31:17'),
(67, 1, 'login_success', 'auth', 'User logged in: admin', NULL, NULL, NULL, NULL, '2026-09-08 08:52:18'),
(68, 1, 'question_created', 'questions', 'Created question ID #9 (single_choice)', NULL, NULL, NULL, NULL, '2026-09-08 08:52:18'),
(69, 1, 'assessment_created', 'assessments', 'Created assessment \'Automated Workflow Verification Exam\' (ID #3)', NULL, NULL, NULL, NULL, '2026-09-08 08:52:18'),
(70, 1, 'login_success', 'auth', 'User logged in: admin', NULL, NULL, NULL, NULL, '2026-09-08 08:52:38'),
(71, 1, 'question_created', 'questions', 'Created question ID #10 (single_choice)', NULL, NULL, NULL, NULL, '2026-09-08 08:52:38'),
(72, 1, 'assessment_created', 'assessments', 'Created assessment \'Automated Workflow Verification Exam\' (ID #4)', NULL, NULL, NULL, NULL, '2026-09-08 08:52:38'),
(73, 1, 'login_success', 'auth', 'User logged in: admin', NULL, NULL, NULL, NULL, '2026-09-08 08:52:58'),
(74, 1, 'question_created', 'questions', 'Created question ID #11 (single_choice)', NULL, NULL, NULL, NULL, '2026-09-08 08:52:58'),
(75, 1, 'assessment_created', 'assessments', 'Created assessment \'Automated Workflow Verification Exam\' (ID #5)', NULL, NULL, NULL, NULL, '2026-09-08 08:52:58'),
(76, 1, 'login_success', 'auth', 'User logged in: admin', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.21996.1', '2026-09-08 08:53:20'),
(77, 1, 'login_success', 'auth', 'User logged in: admin', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 08:58:05'),
(78, 1, 'assessment_created', 'assessments', 'Created assessment \'ahsan\' (ID #8)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 08:59:10'),
(79, 1, 'login_success', 'auth', 'User logged in: admin', NULL, NULL, NULL, NULL, '2026-09-08 09:25:55'),
(80, 1, 'question_created', 'questions', 'Created question ID #20 (single_choice)', NULL, NULL, NULL, NULL, '2026-09-08 09:25:55'),
(81, 1, 'assessment_created', 'assessments', 'Created assessment \'Automated Workflow Verification Exam\' (ID #9)', NULL, NULL, NULL, NULL, '2026-09-08 09:25:55'),
(82, 1, 'login_success', 'auth', 'User logged in: admin', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.21996.1', '2026-09-08 09:26:01'),
(83, 1, 'assessment_deleted', 'assessments', 'Deleted assessment \'ahsan\' (ID #8)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 09:35:06'),
(84, 1, 'assessment_deleted', 'assessments', 'Deleted assessment \'Mid-Term Networking & Architecture Examination\' (ID #7)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 09:35:10'),
(85, 1, 'assessment_deleted', 'assessments', 'Deleted assessment \'Mid-Term Networking & Architecture Examination\' (ID #6)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 09:35:13'),
(86, 1, 'login_success', 'auth', 'User logged in: admin', NULL, NULL, NULL, NULL, '2026-09-08 09:40:28'),
(87, 1, 'assessment_created', 'assessments', 'Created assessment \'Delete Test Temp\' (ID #10)', NULL, NULL, NULL, NULL, '2026-09-08 09:40:28'),
(88, 1, 'login_success', 'auth', 'User logged in: admin', NULL, NULL, NULL, NULL, '2026-09-08 09:40:38'),
(89, 1, 'assessment_created', 'assessments', 'Created assessment \'Delete Test Temp\' (ID #11)', NULL, NULL, NULL, NULL, '2026-09-08 09:40:38'),
(90, 1, 'assessment_deleted', 'assessments', 'Deleted assessment \'Delete Test Temp\' (ID #11)', NULL, NULL, NULL, NULL, '2026-09-08 09:40:38'),
(91, 1, 'login_success', 'auth', 'User logged in: admin', NULL, NULL, NULL, NULL, '2026-09-08 17:14:02'),
(92, 1, 'assessment_created', 'assessments', 'Created assessment \'Biology Quiz 1\' (ID #12)', NULL, NULL, NULL, NULL, '2026-09-08 17:14:03'),
(93, 1, 'assessment_created', 'assessments', 'Created assessment \'Chemistry Quiz 2\' (ID #13)', NULL, NULL, NULL, NULL, '2026-09-08 17:14:03'),
(94, 1, 'login_success', 'auth', 'User logged in: admin', NULL, NULL, NULL, NULL, '2026-09-08 17:14:37'),
(95, 1, 'assessment_created', 'assessments', 'Created assessment \'Biology Quiz 1\' (ID #14)', NULL, NULL, NULL, NULL, '2026-09-08 17:14:37'),
(96, 1, 'assessment_created', 'assessments', 'Created assessment \'Chemistry Quiz 2\' (ID #15)', NULL, NULL, NULL, NULL, '2026-09-08 17:14:37'),
(97, 1, 'assessment_launched', 'assessments', 'Launched exam session for \'Biology Quiz 1\' (ID #14)', NULL, NULL, NULL, NULL, '2026-09-08 17:14:37'),
(98, 1, 'assessment_launched', 'assessments', 'Launched exam session for \'Chemistry Quiz 2\' (ID #15)', NULL, NULL, NULL, NULL, '2026-09-08 17:14:37'),
(99, 1, 'assessment_stopped', 'assessments', 'Stopped exam session for \'Chemistry Quiz 2\' (ID #15)', NULL, NULL, NULL, NULL, '2026-09-08 17:14:37'),
(100, 1, 'login_success', 'auth', 'User logged in: admin', NULL, NULL, NULL, NULL, '2026-09-08 17:14:51'),
(101, 1, 'assessment_created', 'assessments', 'Created assessment \'Biology Quiz 1\' (ID #16)', NULL, NULL, NULL, NULL, '2026-09-08 17:14:51'),
(102, 1, 'assessment_created', 'assessments', 'Created assessment \'Chemistry Quiz 2\' (ID #17)', NULL, NULL, NULL, NULL, '2026-09-08 17:14:51'),
(103, 1, 'assessment_launched', 'assessments', 'Launched exam session for \'Biology Quiz 1\' (ID #16)', NULL, NULL, NULL, NULL, '2026-09-08 17:14:51'),
(104, 1, 'assessment_launched', 'assessments', 'Launched exam session for \'Chemistry Quiz 2\' (ID #17)', NULL, NULL, NULL, NULL, '2026-09-08 17:14:51'),
(105, 1, 'assessment_stopped', 'assessments', 'Stopped exam session for \'Chemistry Quiz 2\' (ID #17)', NULL, NULL, NULL, NULL, '2026-09-08 17:14:51'),
(106, 1, 'login_success', 'auth', 'User logged in: admin', NULL, NULL, NULL, NULL, '2026-09-08 17:14:51'),
(107, 1, 'assessment_deleted', 'assessments', 'Deleted assessment \'Chemistry Quiz 2\' (ID #17)', NULL, NULL, NULL, NULL, '2026-09-08 17:14:51'),
(108, 1, 'assessment_launched', 'assessments', 'Launched exam session for \'Biology Quiz 1\' (ID #16)', NULL, NULL, NULL, NULL, '2026-09-08 17:14:51'),
(109, 1, 'assessment_deleted', 'assessments', 'Deleted assessment \'Biology Quiz 1\' (ID #16)', NULL, NULL, NULL, NULL, '2026-09-08 17:14:51'),
(110, 1, 'assessment_launched', 'assessments', 'Launched exam session for \'Chemistry Quiz 2\' (ID #15)', NULL, NULL, NULL, NULL, '2026-09-08 17:14:51'),
(111, 1, 'login_success', 'auth', 'User logged in: admin', NULL, NULL, NULL, NULL, '2026-09-08 17:15:00'),
(112, 1, 'question_created', 'questions', 'Created question ID #21 (single_choice)', NULL, NULL, NULL, NULL, '2026-09-08 17:15:00'),
(113, 1, 'assessment_created', 'assessments', 'Created assessment \'Automated Workflow Verification Exam\' (ID #18)', NULL, NULL, NULL, NULL, '2026-09-08 17:15:00'),
(114, 1, 'assessment_launched', 'assessments', 'Launched exam session for \'Automated Workflow Verification Exam\' (ID #18)', NULL, NULL, NULL, NULL, '2026-09-08 17:15:00'),
(115, 1, 'login_success', 'auth', 'User logged in: admin', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.21996.1', '2026-09-08 17:15:08'),
(116, 1, 'login_success', 'auth', 'User logged in: admin', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 17:18:26'),
(117, 1, 'assessment_launched', 'assessments', 'Launched exam session for \'Chemistry Quiz 2\' (ID #15)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 17:19:04'),
(118, 1, 'question_deleted', 'questions', 'Deleted question ID #21', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 17:20:41'),
(119, 1, 'question_deleted', 'questions', 'Deleted question ID #20', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 17:20:47'),
(120, 1, 'assessment_launched', 'assessments', 'Launched exam session for \'Chemistry Quiz 2\' (ID #15)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 17:21:27'),
(121, 1, 'assessment_launched', 'assessments', 'Launched exam session for \'Chemistry Quiz 2\' (ID #15)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 17:23:33'),
(122, 1, 'settings_updated', 'settings', 'System settings updated', '{\"institute_name\":\"Marr\",\"system_name\":\"Marr\",\"timezone\":\"Asia\\/Karachi\",\"institute_address\":\"Khairpur Mirs, Sindh, Pakistan\",\"institute_phone\":\"\",\"institute_email\":\"\",\"cert_signature_1_title\":\"\",\"cert_signature_1_name\":\"\",\"cert_signature_2_title\":\"\",\"cert_signature_2_name\":\"\",\"cert_signature_3_title\":\"\",\"cert_signature_3_name\":\"\"}', '{\"institute_name\":\"AHsan\",\"system_name\":\"Marr\",\"timezone\":\"Asia\\/Karachi\",\"institute_address\":\"Khairpur Mirs, Sindh, Pakistan\",\"institute_phone\":\"\",\"institute_email\":\"\",\"cert_signature_1_title\":\"Competition Coordinator\",\"cert_signature_1_name\":\"\",\"cert_signature_2_title\":\"Director \\/ Principal\",\"cert_signature_2_name\":\"\",\"cert_signature_3_title\":\"Examination Controller\",\"cert_signature_3_name\":\"\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 17:25:01'),
(123, 1, 'login_success', 'auth', 'User logged in: admin', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.21996.1', '2026-09-08 17:59:00'),
(124, 1, 'login_success', 'auth', 'User logged in: admin', NULL, NULL, NULL, NULL, '2026-09-08 17:59:54'),
(125, 1, 'assessment_launched', 'assessments', 'Launched exam session for \'Chemistry Quiz 2\' (ID #15)', NULL, NULL, NULL, NULL, '2026-09-08 17:59:54'),
(126, 1, 'login_success', 'auth', 'User logged in: admin', NULL, NULL, NULL, NULL, '2026-09-08 18:00:45'),
(127, 1, 'assessment_launched', 'assessments', 'Launched exam session for \'Chemistry Quiz 2\' (ID #15)', NULL, NULL, NULL, NULL, '2026-09-08 18:00:45'),
(128, 1, 'login_success', 'auth', 'User logged in: admin', NULL, NULL, NULL, NULL, '2026-09-08 18:01:14'),
(129, 1, 'assessment_launched', 'assessments', 'Launched exam session for \'Chemistry Quiz 2\' (ID #15)', NULL, NULL, NULL, NULL, '2026-09-08 18:01:14'),
(130, 1, 'login_success', 'auth', 'User logged in: admin', NULL, NULL, NULL, NULL, '2026-09-08 18:01:31'),
(131, 1, 'assessment_launched', 'assessments', 'Launched exam session for \'Chemistry Quiz 2\' (ID #15)', NULL, NULL, NULL, NULL, '2026-09-08 18:01:31'),
(132, 1, 'login_success', 'auth', 'User logged in: admin', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-US) WindowsPowerShell/5.1.21996.1', '2026-09-08 18:01:37'),
(133, 1, 'login_success', 'auth', 'User logged in: admin', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 18:02:56'),
(134, 1, 'question_created', 'questions', 'Created question ID #22 (short_answer)', NULL, NULL, NULL, NULL, '2026-09-08 18:55:04'),
(135, 1, 'question_created', 'questions', 'Created question ID #23 (single_choice)', NULL, NULL, NULL, NULL, '2026-09-08 18:55:04'),
(136, 1, 'question_created', 'questions', 'Created question ID #24 (short_answer)', NULL, NULL, NULL, NULL, '2026-09-08 18:55:58'),
(137, 1, 'question_created', 'questions', 'Created question ID #25 (single_choice)', NULL, NULL, NULL, NULL, '2026-09-08 18:55:58'),
(138, 1, 'question_created', 'questions', 'Created question ID #26 (short_answer)', NULL, NULL, NULL, NULL, '2026-09-08 18:56:21'),
(139, 1, 'question_created', 'questions', 'Created question ID #27 (single_choice)', NULL, NULL, NULL, NULL, '2026-09-08 18:56:21'),
(140, 1, 'question_created', 'questions', 'Created question ID #28 (short_answer)', NULL, NULL, NULL, NULL, '2026-09-08 18:56:48'),
(141, 1, 'question_created', 'questions', 'Created question ID #29 (single_choice)', NULL, NULL, NULL, NULL, '2026-09-08 18:56:48'),
(142, 1, 'attempt_reviewed', 'assessment_attempts', 'Finalized and generated result for attempt #30', NULL, NULL, NULL, NULL, '2026-09-08 18:56:48'),
(143, 1, 'question_created', 'questions', 'Created question ID #30 (short_answer)', NULL, NULL, NULL, NULL, '2026-09-08 18:57:58'),
(144, 1, 'question_created', 'questions', 'Created question ID #31 (single_choice)', NULL, NULL, NULL, NULL, '2026-09-08 18:57:58'),
(145, 1, 'attempt_reviewed', 'assessment_attempts', 'Finalized and generated result for attempt #31', NULL, NULL, NULL, NULL, '2026-09-08 18:57:58'),
(146, 1, 'assessment_stopped', 'assessments', 'Stopped exam session for \'E2E Subjective & Proctoring Verification Exam 1788893878\' (ID #22)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 18:59:16'),
(147, 1, 'assessment_stopped', 'assessments', 'Stopped exam session for \'E2E Subjective & Proctoring Verification Exam 1788893808\' (ID #21)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 18:59:19'),
(148, 1, 'assessment_stopped', 'assessments', 'Stopped exam session for \'E2E Subjective & Proctoring Verification Exam 1788893781\' (ID #20)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 18:59:20'),
(149, 1, 'assessment_stopped', 'assessments', 'Stopped exam session for \'E2E Subjective & Proctoring Verification Exam 1788893758\' (ID #19)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 18:59:21'),
(150, 1, 'assessment_stopped', 'assessments', 'Stopped exam session for \'Chemistry Quiz 2\' (ID #15)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 18:59:21'),
(151, 1, 'assessment_launched', 'assessments', 'Launched exam session for \'E2E Subjective & Proctoring Verification Exam 1788893878\' (ID #22)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 18:59:38'),
(152, 1, 'attempt_reviewed', 'assessment_attempts', 'Finalized and generated result for attempt #32', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 19:01:07'),
(153, 1, 'assessment_launched', 'assessments', 'Launched exam session for \'E2E Subjective & Proctoring Verification Exam 1788893878\' (ID #22)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 19:02:51'),
(154, 1, 'assessment_stopped', 'assessments', 'Stopped exam session for \'E2E Subjective & Proctoring Verification Exam 1788893878\' (ID #22)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 19:04:02'),
(155, 1, 'assessment_launched', 'assessments', 'Launched exam session for \'E2E Subjective & Proctoring Verification Exam 1788893878\' (ID #22)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 19:04:07'),
(156, 1, 'assessment_launched', 'assessments', 'Launched exam session for \'E2E Subjective & Proctoring Verification Exam 1788893808\' (ID #21)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 19:04:10'),
(157, 1, 'assessment_launched', 'assessments', 'Launched exam session for \'E2E Subjective & Proctoring Verification Exam 1788893878\' (ID #22)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 19:04:12'),
(158, 1, 'assessment_stopped', 'assessments', 'Stopped exam session for \'E2E Subjective & Proctoring Verification Exam 1788893878\' (ID #22)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 19:04:16'),
(159, 1, 'assessment_launched', 'assessments', 'Launched exam session for \'E2E Subjective & Proctoring Verification Exam 1788893878\' (ID #22)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 19:04:49'),
(160, 1, 'assessment_launched', 'assessments', 'Launched exam session for \'E2E Subjective & Proctoring Verification Exam 1788893878\' (ID #22)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 19:04:59'),
(161, 1, 'assessment_stopped', 'assessments', 'Stopped exam session for \'E2E Subjective & Proctoring Verification Exam 1788893878\' (ID #22)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 19:12:59'),
(162, 1, 'question_created', 'questions', 'Created question ID #32 (short_answer)', NULL, NULL, NULL, NULL, '2026-09-08 19:24:59'),
(163, 1, 'question_created', 'questions', 'Created question ID #33 (single_choice)', NULL, NULL, NULL, NULL, '2026-09-08 19:25:00'),
(164, 1, 'attempt_reviewed', 'assessment_attempts', 'Finalized and generated result for attempt #33', NULL, NULL, NULL, NULL, '2026-09-08 19:25:00'),
(165, 1, 'assessment_stopped', 'assessments', 'Stopped exam session for \'E2E Subjective & Proctoring Verification Exam 1788895500\' (ID #23)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 19:30:42'),
(166, 1, 'assessment_stopped', 'assessments', 'Stopped exam session for \'Mid-Term Networking & Architecture Examination\' (ID #1)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 19:30:49'),
(167, 1, 'question_created', 'questions', 'Created question ID #34 (short_answer)', NULL, NULL, NULL, NULL, '2026-09-08 19:37:41'),
(168, 1, 'question_created', 'questions', 'Created question ID #35 (single_choice)', NULL, NULL, NULL, NULL, '2026-09-08 19:37:41'),
(169, 1, 'attempt_reviewed', 'assessment_attempts', 'Finalized and generated result for attempt #35', NULL, NULL, NULL, NULL, '2026-09-08 19:37:41'),
(170, 1, 'assessment_launched', 'assessments', 'Launched exam session for \'E2E Subjective & Proctoring Verification Exam 1788895500\' (ID #23)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 19:41:14'),
(171, 1, 'attempt_reviewed', 'assessment_attempts', 'Finalized and generated result for attempt #36', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 19:45:10'),
(172, 1, 'attempt_reviewed', 'assessment_attempts', 'Finalized and generated result for attempt #36', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 19:45:14'),
(173, 1, 'attempt_reviewed', 'assessment_attempts', 'Finalized and generated result for attempt #37', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 19:45:31'),
(174, 1, 'assessment_deleted', 'assessments', 'Deleted assessment \'E2E Subjective & Proctoring Verification Exam 1788896261\' (ID #24)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 19:46:27'),
(175, 1, 'assessment_stopped', 'assessments', 'Stopped exam session for \'E2E Subjective & Proctoring Verification Exam 1788895500\' (ID #23)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 19:46:30'),
(176, 1, 'assessment_deleted', 'assessments', 'Deleted assessment \'E2E Subjective & Proctoring Verification Exam 1788895500\' (ID #23)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 19:46:33'),
(177, 1, 'assessment_deleted', 'assessments', 'Deleted assessment \'E2E Subjective & Proctoring Verification Exam 1788893878\' (ID #22)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 19:46:35'),
(178, 1, 'assessment_deleted', 'assessments', 'Deleted assessment \'E2E Subjective & Proctoring Verification Exam 1788893808\' (ID #21)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 19:46:40'),
(179, 1, 'assessment_deleted', 'assessments', 'Deleted assessment \'E2E Subjective & Proctoring Verification Exam 1788893781\' (ID #20)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 19:46:42'),
(180, 1, 'assessment_deleted', 'assessments', 'Deleted assessment \'E2E Subjective & Proctoring Verification Exam 1788893758\' (ID #19)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 19:46:44'),
(181, 1, 'assessment_deleted', 'assessments', 'Deleted assessment \'Automated Workflow Verification Exam\' (ID #18)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 19:46:47'),
(182, 1, 'assessment_deleted', 'assessments', 'Deleted assessment \'Chemistry Quiz 2\' (ID #15)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 19:46:49'),
(183, 1, 'assessment_deleted', 'assessments', 'Deleted assessment \'Biology Quiz 1\' (ID #14)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 19:46:51'),
(184, 1, 'assessment_deleted', 'assessments', 'Deleted assessment \'Chemistry Quiz 2\' (ID #13)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 19:46:54'),
(185, 1, 'assessment_deleted', 'assessments', 'Deleted assessment \'Biology Quiz 1\' (ID #12)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 19:46:56'),
(186, 1, 'assessment_deleted', 'assessments', 'Deleted assessment \'Automated Workflow Verification Exam\' (ID #9)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 19:46:58'),
(187, 1, 'assessment_deleted', 'assessments', 'Deleted assessment \'Automated Workflow Verification Exam\' (ID #5)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 19:47:00'),
(188, 1, 'assessment_deleted', 'assessments', 'Deleted assessment \'Automated Workflow Verification Exam\' (ID #4)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 19:47:05'),
(189, 1, 'assessment_deleted', 'assessments', 'Deleted assessment \'Automated Workflow Verification Exam\' (ID #3)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 19:47:06'),
(190, 1, 'assessment_deleted', 'assessments', 'Deleted assessment \'Mid-Term Networking & Architecture Examination\' (ID #2)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 19:47:08'),
(191, 1, 'assessment_deleted', 'assessments', 'Deleted assessment \'Mid-Term Networking & Architecture Examination\' (ID #1)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 19:47:10'),
(192, 1, 'question_deleted', 'questions', 'Deleted question ID #35', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 19:47:17'),
(193, 1, 'question_deleted', 'questions', 'Deleted question ID #34', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 19:47:19'),
(194, 1, 'question_deleted', 'questions', 'Deleted question ID #33', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 19:47:21'),
(195, 1, 'question_deleted', 'questions', 'Deleted question ID #32', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 19:47:24'),
(196, 1, 'question_deleted', 'questions', 'Deleted question ID #31', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 19:47:26'),
(197, 1, 'question_deleted', 'questions', 'Deleted question ID #30', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 19:47:28'),
(198, 1, 'question_deleted', 'questions', 'Deleted question ID #29', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 19:47:30'),
(199, 1, 'question_deleted', 'questions', 'Deleted question ID #28', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 19:47:32'),
(200, 1, 'question_deleted', 'questions', 'Deleted question ID #27', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 19:47:34'),
(201, 1, 'question_deleted', 'questions', 'Deleted question ID #26', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 19:47:37'),
(202, 1, 'question_deleted', 'questions', 'Deleted question ID #25', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 19:47:39'),
(203, 1, 'question_deleted', 'questions', 'Deleted question ID #24', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 19:47:40'),
(204, 1, 'question_deleted', 'questions', 'Deleted question ID #23', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 19:47:42'),
(205, 1, 'question_deleted', 'questions', 'Deleted question ID #22', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 19:47:44'),
(206, 1, 'question_deleted', 'questions', 'Deleted question ID #19', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 19:47:46'),
(207, 1, 'question_deleted', 'questions', 'Deleted question ID #18', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 19:47:48'),
(208, 1, 'question_deleted', 'questions', 'Deleted question ID #17', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 19:47:51'),
(209, 1, 'question_deleted', 'questions', 'Deleted question ID #16', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 19:47:54'),
(210, 1, 'question_deleted', 'questions', 'Deleted question ID #15', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 19:47:58'),
(211, 1, 'question_deleted', 'questions', 'Deleted question ID #14', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 19:48:00'),
(212, 1, 'question_deleted', 'questions', 'Deleted question ID #13', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 19:48:02'),
(213, 1, 'question_deleted', 'questions', 'Deleted question ID #12', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 19:48:04'),
(214, 1, 'question_deleted', 'questions', 'Deleted question ID #11', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 19:48:06'),
(215, 1, 'question_deleted', 'questions', 'Deleted question ID #10', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 19:48:08'),
(216, 1, 'question_deleted', 'questions', 'Deleted question ID #9', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 19:48:10'),
(217, 1, 'question_deleted', 'questions', 'Deleted question ID #8', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 19:48:12'),
(218, 1, 'question_deleted', 'questions', 'Deleted question ID #7', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 19:48:14'),
(219, 1, 'question_deleted', 'questions', 'Deleted question ID #6', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 19:48:16'),
(220, 1, 'question_deleted', 'questions', 'Deleted question ID #5', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 19:48:19'),
(221, 1, 'question_deleted', 'questions', 'Deleted question ID #4', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 19:48:21'),
(222, 1, 'question_deleted', 'questions', 'Deleted question ID #3', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 19:48:23'),
(223, 1, 'question_deleted', 'questions', 'Deleted question ID #2', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 19:48:25'),
(224, 1, 'question_deleted', 'questions', 'Deleted question ID #1', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 19:48:27'),
(225, 1, 'question_created', 'questions', 'Created question ID #37 (single_choice)', NULL, NULL, NULL, NULL, '2026-09-08 20:03:25'),
(226, 1, 'question_created', 'questions', 'Created question ID #38 (single_choice)', NULL, NULL, NULL, NULL, '2026-09-08 20:03:32'),
(227, 1, 'question_updated', 'questions', 'Updated question ID #38', NULL, NULL, NULL, NULL, '2026-09-08 20:03:32'),
(228, 1, 'assessment_created', 'assessments', 'Created assessment \'Turkish Language Midterm - Grade 1 Section B\' (ID #25)', NULL, NULL, NULL, NULL, '2026-09-08 20:03:32'),
(229, 1, 'assessment_created', 'assessments', 'Created assessment \'Arabic Beginners Advanced Batch\' (ID #26)', NULL, NULL, NULL, NULL, '2026-09-08 20:03:32'),
(230, 1, 'question_deleted', 'questions', 'Deleted question ID #38', NULL, NULL, NULL, NULL, '2026-09-08 20:03:32'),
(231, 1, 'question_created', 'questions', 'Created question ID #39 (single_choice)', NULL, NULL, NULL, NULL, '2026-09-08 20:03:40'),
(232, 1, 'question_updated', 'questions', 'Updated question ID #39', NULL, NULL, NULL, NULL, '2026-09-08 20:03:40'),
(233, 1, 'assessment_created', 'assessments', 'Created assessment \'Turkish Language Midterm - Grade 1 Section B\' (ID #27)', NULL, NULL, NULL, NULL, '2026-09-08 20:03:40'),
(234, 1, 'assessment_created', 'assessments', 'Created assessment \'Arabic Beginners Advanced Batch\' (ID #28)', NULL, NULL, NULL, NULL, '2026-09-08 20:03:40'),
(235, 1, 'question_deleted', 'questions', 'Deleted question ID #39', NULL, NULL, NULL, NULL, '2026-09-08 20:03:40'),
(236, 1, 'question_created', 'questions', 'Created question ID #40 (single_choice)', NULL, NULL, NULL, NULL, '2026-09-08 20:03:51'),
(237, 1, 'question_updated', 'questions', 'Updated question ID #40', NULL, NULL, NULL, NULL, '2026-09-08 20:03:51'),
(238, 1, 'assessment_created', 'assessments', 'Created assessment \'Turkish Language Midterm - Grade 1 Section B\' (ID #29)', NULL, NULL, NULL, NULL, '2026-09-08 20:03:51'),
(239, 1, 'assessment_created', 'assessments', 'Created assessment \'Arabic Beginners Advanced Batch\' (ID #30)', NULL, NULL, NULL, NULL, '2026-09-08 20:03:51'),
(240, 1, 'question_deleted', 'questions', 'Deleted question ID #40', NULL, NULL, NULL, NULL, '2026-09-08 20:03:51'),
(241, 1, 'question_created', 'questions', 'Created question ID #41 (single_choice)', NULL, NULL, NULL, NULL, '2026-09-08 20:04:15'),
(242, 1, 'question_updated', 'questions', 'Updated question ID #41', NULL, NULL, NULL, NULL, '2026-09-08 20:04:15'),
(243, 1, 'assessment_created', 'assessments', 'Created assessment \'Turkish Language Midterm - Grade 1 Section B\' (ID #31)', NULL, NULL, NULL, NULL, '2026-09-08 20:04:15'),
(244, 1, 'assessment_created', 'assessments', 'Created assessment \'Arabic Beginners Advanced Batch\' (ID #32)', NULL, NULL, NULL, NULL, '2026-09-08 20:04:15'),
(245, 1, 'question_deleted', 'questions', 'Deleted question ID #41', NULL, NULL, NULL, NULL, '2026-09-08 20:04:15'),
(246, 1, 'assessment_deleted', 'assessments', 'Deleted assessment \'Turkish Language Midterm - Grade 1 Section B\' (ID #31)', NULL, NULL, NULL, NULL, '2026-09-08 20:04:15'),
(247, 1, 'assessment_deleted', 'assessments', 'Deleted assessment \'Arabic Beginners Advanced Batch\' (ID #32)', NULL, NULL, NULL, NULL, '2026-09-08 20:04:15'),
(248, 1, 'assessment_deleted', 'assessments', 'Deleted assessment \'Arabic Beginners Advanced Batch\' (ID #30)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 20:09:18'),
(249, 1, 'assessment_deleted', 'assessments', 'Deleted assessment \'Arabic Beginners Advanced Batch\' (ID #28)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 20:09:21'),
(250, 1, 'assessment_deleted', 'assessments', 'Deleted assessment \'Arabic Beginners Advanced Batch\' (ID #26)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 20:09:24'),
(251, 1, 'question_created', 'questions', 'Created question ID #42 (single_choice, easy)', NULL, NULL, NULL, NULL, '2026-09-08 20:19:09'),
(252, 1, 'question_created', 'questions', 'Created question ID #43 (true_false, easy)', NULL, NULL, NULL, NULL, '2026-09-08 20:19:09'),
(253, 1, 'question_created', 'questions', 'Created question ID #44 (fill_blank, hard)', NULL, NULL, NULL, NULL, '2026-09-08 20:19:09'),
(254, 1, 'question_deleted', 'questions', 'Deleted question ID #42', NULL, NULL, NULL, NULL, '2026-09-08 20:19:09'),
(255, 1, 'question_deleted', 'questions', 'Deleted question ID #43', NULL, NULL, NULL, NULL, '2026-09-08 20:19:09'),
(256, 1, 'question_deleted', 'questions', 'Deleted question ID #44', NULL, NULL, NULL, NULL, '2026-09-08 20:19:09'),
(257, 1, 'question_created', 'questions', 'Created question ID #45 (single_choice, easy)', NULL, NULL, NULL, NULL, '2026-09-08 20:19:44'),
(258, 1, 'question_created', 'questions', 'Created question ID #46 (single_choice, easy)', NULL, NULL, NULL, NULL, '2026-09-08 20:19:44'),
(259, 1, 'question_created', 'questions', 'Created question ID #47 (single_choice, easy)', NULL, NULL, NULL, NULL, '2026-09-08 20:20:31'),
(260, 1, 'question_created', 'questions', 'Created question ID #48 (single_choice, hard)', NULL, NULL, NULL, NULL, '2026-09-08 20:20:31');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `module`, `description`, `old_values`, `new_values`, `ip_address`, `user_agent`, `created_at`) VALUES
(261, 1, 'question_deleted', 'questions', 'Deleted question ID #47', NULL, NULL, NULL, NULL, '2026-09-08 20:20:31'),
(262, 1, 'question_deleted', 'questions', 'Deleted question ID #48', NULL, NULL, NULL, NULL, '2026-09-08 20:20:31'),
(263, 1, 'assessment_stopped', 'assessments', 'Stopped exam session for \'Live Monitor Unit Test Exam\' (ID #33)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 20:50:48'),
(264, 1, 'assessment_launched', 'assessments', 'Launched exam session for \'Live Monitor Unit Test Exam\' (ID #33)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 20:52:25'),
(265, 1, 'attempt_reviewed', 'assessment_attempts', 'Finalized and generated result for attempt #39', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 20:53:38'),
(266, 1, 'candidate_disqualified', 'assessments', 'Invigilator disqualified attempt #38', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 20:54:59'),
(267, 1, 'assessment_stopped', 'assessments', 'Stopped exam session for \'Live Monitor Unit Test Exam\' (ID #33)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 20:56:57'),
(268, 1, 'assessment_launched', 'assessments', 'Launched exam session for \'Live Monitor Unit Test Exam\' (ID #33)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 20:57:55'),
(269, 1, 'attempt_reviewed', 'assessment_attempts', 'Finalized and generated result for attempt #40', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 20:59:01'),
(270, 1, 'attempt_reviewed', 'assessment_attempts', 'Finalized and generated result for attempt #38', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 21:00:45'),
(271, 1, 'assessment_stopped', 'assessments', 'Stopped exam session for \'Live Monitor Unit Test Exam\' (ID #33)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 21:01:37'),
(272, 1, 'attempt_reviewed', 'assessment_attempts', 'Finalized and generated result for attempt #41', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 21:17:25'),
(273, 1, 'assessment_created', 'assessments', 'Created assessment \'jyiuyi\' (ID #34)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 21:18:52'),
(274, 1, 'assessment_deleted', 'assessments', 'Deleted assessment \'jyiuyi\' (ID #34)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 21:19:40'),
(275, 1, 'assessment_created', 'assessments', 'Created assessment \'hjgjh\' (ID #35)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 21:20:02'),
(276, 1, 'assessment_launched', 'assessments', 'Launched exam session for \'hjgjh\' (ID #35)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 21:20:14'),
(277, 1, 'assessment_stopped', 'assessments', 'Stopped exam session for \'hjgjh\' (ID #35)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 21:20:28'),
(278, 1, 'assessment_launched', 'assessments', 'Launched exam session for \'Live Monitor Unit Test Exam\' (ID #33)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 21:20:30'),
(279, 1, 'assessment_stopped', 'assessments', 'Stopped exam session for \'Live Monitor Unit Test Exam\' (ID #33)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 21:20:46'),
(280, 1, 'assessment_launched', 'assessments', 'Launched exam session for \'Live Monitor Unit Test Exam\' (ID #33)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 21:22:20'),
(281, 1, 'assessment_deleted', 'assessments', 'Deleted assessment \'Live Monitor Unit Test Exam\' (ID #33)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 21:23:51'),
(282, 1, 'assessment_deleted', 'assessments', 'Deleted assessment \'hjgjh\' (ID #35)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 21:23:53'),
(283, 1, 'assessment_created', 'assessments', 'Created assessment \'xzcxz\' (ID #36)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 21:31:15'),
(284, 1, 'assessment_created', 'assessments', 'Created assessment \'sd\' (ID #37)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 21:36:07'),
(285, 1, 'assessment_created', 'assessments', 'Created assessment \'Term Examination Alpha 1788931142\' (ID #38)', NULL, NULL, NULL, NULL, '2026-09-09 05:19:02'),
(286, 1, 'assessment_updated', 'assessments', 'Updated assessment ID #38', NULL, NULL, NULL, NULL, '2026-09-09 05:19:02'),
(287, 1, 'assessment_created', 'assessments', 'Created assessment \'Term Examination Alpha 1788931165\' (ID #39)', NULL, NULL, NULL, NULL, '2026-09-09 05:19:25'),
(288, 1, 'assessment_updated', 'assessments', 'Updated assessment ID #39', NULL, NULL, NULL, NULL, '2026-09-09 05:19:25'),
(289, 1, 'assessment_created', 'assessments', 'Created assessment \'Term Examination Alpha 1788931179\' (ID #40)', NULL, NULL, NULL, NULL, '2026-09-09 05:19:39'),
(290, 1, 'assessment_updated', 'assessments', 'Updated assessment ID #40', NULL, NULL, NULL, NULL, '2026-09-09 05:19:39'),
(291, 1, 'login_success', 'auth', 'User logged in: admin', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-09 05:22:29'),
(292, 1, 'assessment_stopped', 'assessments', 'Stopped exam session for \'Term Examination Alpha 1788931165 (Updated)\' (ID #39)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-09 05:24:00'),
(293, 1, 'assessment_stopped', 'assessments', 'Stopped exam session for \'Term Examination Alpha 1788931142 (Updated)\' (ID #38)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-09 05:24:01'),
(294, 1, 'user_created', 'users', 'Created user \'test_subuser_nodelete\' with role \'subuser\'', NULL, NULL, NULL, NULL, '2026-09-09 07:33:37'),
(295, NULL, 'login_success', 'auth', 'User logged in: test_subuser_nodelete', NULL, NULL, NULL, NULL, '2026-09-09 07:33:37'),
(296, 1, 'user_created', 'users', 'Created user \'test_subuser_nodelete\' with role \'subuser\'', NULL, NULL, NULL, NULL, '2026-09-09 07:33:45'),
(297, NULL, 'login_success', 'auth', 'User logged in: test_subuser_nodelete', NULL, NULL, NULL, NULL, '2026-09-09 07:33:45'),
(298, 1, 'user_created', 'users', 'Created user \'test_subuser_nodelete\' with role \'subuser\'', NULL, NULL, NULL, NULL, '2026-09-09 07:33:53'),
(299, 1, 'login_success', 'auth', 'User logged in: test_subuser_nodelete', NULL, NULL, NULL, NULL, '2026-09-09 07:33:53'),
(300, 1, 'user_deleted', 'users', 'Deleted user \'test_subuser_nodelete\' (ID #6)', NULL, NULL, NULL, NULL, '2026-09-09 07:37:15'),
(301, 1, 'user_created', 'users', 'Created user \'test_subuser_nodelete\' with role \'subuser\'', NULL, NULL, NULL, NULL, '2026-09-09 07:37:15'),
(302, 1, 'login_success', 'auth', 'User logged in: test_subuser_nodelete', NULL, NULL, NULL, NULL, '2026-09-09 07:37:15'),
(303, 1, 'logout', 'auth', 'User logged out: test_subuser_nodelete', NULL, NULL, NULL, NULL, '2026-09-09 07:37:15'),
(304, 1, 'question_deleted', 'questions', 'Deleted question ID #52', NULL, NULL, NULL, NULL, '2026-09-09 07:37:15'),
(305, 1, 'assessment_deleted', 'assessments', 'Deleted assessment \'Unit Test Temp Assessment\' (ID #42)', NULL, NULL, NULL, NULL, '2026-09-09 07:37:15'),
(306, 1, 'user_deleted', 'users', 'Deleted user \'test_subuser_nodelete\' (ID #7)', NULL, NULL, NULL, NULL, '2026-09-09 07:37:51'),
(307, 1, 'user_created', 'users', 'Created user \'test_subuser_nodelete\' with role \'subuser\'', NULL, NULL, NULL, NULL, '2026-09-09 07:37:51'),
(308, 1, 'login_success', 'auth', 'User logged in: test_subuser_nodelete', NULL, NULL, NULL, NULL, '2026-09-09 07:37:51'),
(309, 1, 'logout', 'auth', 'User logged out: test_subuser_nodelete', NULL, NULL, NULL, NULL, '2026-09-09 07:37:51'),
(310, 1, 'question_deleted', 'questions', 'Deleted question ID #53', NULL, NULL, NULL, NULL, '2026-09-09 07:37:51'),
(311, 1, 'assessment_deleted', 'assessments', 'Deleted assessment \'Unit Test Temp Assessment\' (ID #43)', NULL, NULL, NULL, NULL, '2026-09-09 07:37:51'),
(312, 1, 'user_updated', 'users', 'Updated user \'test_subuser_nodelete\' (ID #8)', NULL, NULL, NULL, NULL, '2026-09-09 07:37:51'),
(313, 1, 'user_deleted', 'users', 'Deleted user \'test_subuser_nodelete\' (ID #8)', NULL, NULL, NULL, NULL, '2026-09-09 07:37:51'),
(314, 1, 'user_created', 'users', 'Created user \'test_subuser_nodelete\' with role \'subuser\'', NULL, NULL, NULL, NULL, '2026-09-09 07:38:22'),
(315, 1, 'login_success', 'auth', 'User logged in: test_subuser_nodelete', NULL, NULL, NULL, NULL, '2026-09-09 07:38:22'),
(316, 1, 'logout', 'auth', 'User logged out: test_subuser_nodelete', NULL, NULL, NULL, NULL, '2026-09-09 07:38:22'),
(317, 1, 'question_deleted', 'questions', 'Deleted question ID #54', NULL, NULL, NULL, NULL, '2026-09-09 07:38:22'),
(318, 1, 'assessment_deleted', 'assessments', 'Deleted assessment \'Unit Test Temp Assessment\' (ID #44)', NULL, NULL, NULL, NULL, '2026-09-09 07:38:22'),
(319, 1, 'user_updated', 'users', 'Updated user \'test_subuser_nodelete\' (ID #9)', NULL, NULL, NULL, NULL, '2026-09-09 07:38:23'),
(320, 1, 'user_deleted', 'users', 'Deleted user \'test_subuser_nodelete\' (ID #9)', NULL, NULL, NULL, NULL, '2026-09-09 07:38:23'),
(321, 1, 'login_failed', 'auth', 'Wrong password for user: admin', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-09 07:42:45'),
(322, 1, 'login_success', 'auth', 'User logged in: admin', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-09 07:42:51'),
(323, 1, 'user_updated', 'users', 'Updated user \'teacher\' (ID #2)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-09 07:44:00'),
(324, 1, 'logout', 'auth', 'User logged out: admin', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-09 07:44:19'),
(325, 2, 'login_success', 'auth', 'User logged in: teacher', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-09 07:44:26'),
(326, 2, 'logout', 'auth', 'User logged out: teacher', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-09 08:14:32'),
(327, 1, 'user_created', 'users', 'Created user \'test_subuser_nodelete\' with role \'subuser\'', NULL, NULL, NULL, NULL, '2026-09-09 08:14:43'),
(328, 1, 'login_success', 'auth', 'User logged in: test_subuser_nodelete', NULL, NULL, NULL, NULL, '2026-09-09 08:14:43'),
(329, 1, 'logout', 'auth', 'User logged out: test_subuser_nodelete', NULL, NULL, NULL, NULL, '2026-09-09 08:14:43'),
(330, 1, 'question_deleted', 'questions', 'Deleted question ID #55', NULL, NULL, NULL, NULL, '2026-09-09 08:14:43'),
(331, 1, 'assessment_deleted', 'assessments', 'Deleted assessment \'Unit Test Temp Assessment\' (ID #45)', NULL, NULL, NULL, NULL, '2026-09-09 08:14:43'),
(332, 1, 'user_updated', 'users', 'Updated user \'test_subuser_nodelete\' (ID #10)', NULL, NULL, NULL, NULL, '2026-09-09 08:14:43'),
(333, 1, 'user_deleted', 'users', 'Deleted user \'test_subuser_nodelete\' (ID #10)', NULL, NULL, NULL, NULL, '2026-09-09 08:14:43'),
(334, NULL, 'login_failed', 'auth', 'Failed login attempt for username: teachers', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-09 08:17:19'),
(335, 2, 'login_success', 'auth', 'User logged in: teacher', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-09 08:17:26'),
(336, 2, 'logout', 'auth', 'User logged out: teacher', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-09 08:18:18'),
(337, 1, 'login_success', 'auth', 'User logged in: admin', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-09 08:18:26'),
(338, 1, 'assessment_deleted', 'assessments', 'Deleted assessment \'Term Examination Alpha 1788931165 (Updated)\' (ID #39)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-09 08:18:40'),
(339, 1, 'assessment_deleted', 'assessments', 'Deleted assessment \'Term Examination Alpha 1788931142 (Updated)\' (ID #38)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-09 08:18:43'),
(340, 1, 'assessment_deleted', 'assessments', 'Deleted assessment \'sd\' (ID #37)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-09 08:18:45'),
(341, 1, 'assessment_deleted', 'assessments', 'Deleted assessment \'xzcxz\' (ID #36)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-09 08:18:48'),
(342, 1, 'question_deleted', 'questions', 'Deleted question ID #49', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-09 08:18:54'),
(343, 1, 'question_deleted', 'questions', 'Deleted question ID #51', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-09 08:18:56'),
(344, 1, 'question_created', 'questions', 'Created question ID #56 (single_choice, medium)', NULL, NULL, NULL, NULL, '2026-09-09 08:52:55'),
(345, 2, 'question_created', 'questions', 'Created question ID #57 (single_choice, easy)', NULL, NULL, NULL, NULL, '2026-09-09 08:55:05'),
(346, 2, 'question_created', 'questions', 'Created question ID #58 (single_choice, easy)', NULL, NULL, NULL, NULL, '2026-09-09 09:08:28'),
(347, 1, 'question_created', 'questions', 'Created question ID #59 (single_choice, medium)', NULL, NULL, NULL, NULL, '2026-09-09 09:10:31'),
(348, 1, 'question_created', 'questions', 'Created question ID #60 (multiple_choice, hard)', NULL, NULL, NULL, NULL, '2026-09-09 09:10:31'),
(349, 1, 'question_created', 'questions', 'Created question ID #61 (true_false, easy)', NULL, NULL, NULL, NULL, '2026-09-09 09:10:31'),
(350, 1, 'question_created', 'questions', 'Created question ID #62 (fill_blank, medium)', NULL, NULL, NULL, NULL, '2026-09-09 09:10:32'),
(351, 1, 'question_created', 'questions', 'Created question ID #63 (short_answer, hard)', NULL, NULL, NULL, NULL, '2026-09-09 09:10:32'),
(352, 1, 'question_created', 'questions', 'Created question ID #64 (single_choice, hard)', NULL, NULL, NULL, NULL, '2026-09-09 09:10:32'),
(353, 1, 'question_created', 'questions', 'Created question ID #65 (single_choice, hard)', NULL, NULL, NULL, NULL, '2026-09-09 09:10:32'),
(354, 1, 'question_deleted', 'questions', 'Deleted question ID #65', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-09 09:12:17'),
(355, 1, 'question_deleted', 'questions', 'Deleted question ID #64', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-09 09:12:19'),
(356, 1, 'question_deleted', 'questions', 'Deleted question ID #63', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-09 09:12:22'),
(357, 1, 'question_deleted', 'questions', 'Deleted question ID #62', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-09 09:12:25'),
(358, 1, 'question_deleted', 'questions', 'Deleted question ID #61', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-09 09:12:27'),
(359, 1, 'question_deleted', 'questions', 'Deleted question ID #60', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-09 09:12:32'),
(360, 1, 'question_deleted', 'questions', 'Deleted question ID #59', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-09 09:12:35'),
(361, 1, 'question_deleted', 'questions', 'Deleted question ID #56', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-09 09:12:38'),
(362, 1, 'question_deleted', 'questions', 'Deleted question ID #57', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-09 09:12:42'),
(363, 1, 'question_deleted', 'questions', 'Deleted question ID #58', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-09 09:12:44'),
(364, 1, 'question_created', 'questions', 'Created question ID #66 (multiple_choice, medium)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-09 09:15:39'),
(365, 1, 'question_created', 'questions', 'Created question ID #67 (multiple_choice, medium)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-09 09:15:39'),
(366, 1, 'question_created', 'questions', 'Created question ID #68 (multiple_choice, medium)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-09 09:15:39'),
(367, 1, 'question_created', 'questions', 'Created question ID #69 (multiple_choice, medium)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-09 09:15:39'),
(368, 1, 'assessment_created', 'assessments', 'Created assessment \'Computer Assesment Dated 9-9-26\' (ID #46)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-09 09:17:38'),
(369, 1, 'assessment_launched', 'assessments', 'Launched exam session for \'Computer Assesment Dated 9-9-26\' (ID #46)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-09 09:18:04'),
(370, 1, 'login_success', 'auth', 'User logged in: admin', NULL, NULL, '192.168.21.23', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-09 12:11:03'),
(371, 1, 'assessment_created', 'assessments', 'Created assessment \'SSt\' (ID #47)', NULL, NULL, '192.168.21.23', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-09 12:14:03'),
(372, 1, 'assessment_deleted', 'assessments', 'Deleted assessment \'SSt\' (ID #47)', NULL, NULL, '192.168.21.23', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-09 12:14:29'),
(373, 1, 'assessment_created', 'assessments', 'Created assessment \'hjhj\' (ID #48)', NULL, NULL, '192.168.21.23', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-09 12:14:52'),
(374, 1, 'assessment_stopped', 'assessments', 'Stopped exam session for \'Computer Assesment Dated 9-9-26\' (ID #46)', NULL, NULL, '192.168.21.23', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-09 12:15:40'),
(375, 1, 'assessment_launched', 'assessments', 'Launched exam session for \'hjhj\' (ID #48)', NULL, NULL, '192.168.21.23', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-09 12:15:47'),
(376, 1, 'assessment_stopped', 'assessments', 'Stopped exam session for \'hjhj\' (ID #48)', NULL, NULL, '192.168.21.23', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-09 12:15:50'),
(377, 1, 'assessment_launched', 'assessments', 'Launched exam session for \'hjhj\' (ID #48)', NULL, NULL, '192.168.21.23', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-09 12:16:31'),
(378, 1, 'login_failed', 'auth', 'Wrong password for user: admin', NULL, NULL, '192.168.20.184', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-11 10:26:37'),
(379, 1, 'login_failed', 'auth', 'Wrong password for user: admin', NULL, NULL, '192.168.20.184', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-11 10:26:47'),
(380, 1, 'login_success', 'auth', 'User logged in: admin', NULL, NULL, '192.168.20.184', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-11 10:26:53'),
(381, 1, 'assessment_stopped', 'assessments', 'Stopped exam session for \'hjhj\' (ID #48)', NULL, NULL, '192.168.20.184', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-11 10:27:10'),
(382, 1, 'login_success', 'auth', 'User logged in: admin', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-11 10:29:00'),
(383, 1, 'settings_updated', 'settings', 'System settings updated', '{\"institute_name\":\"vibe.S\\u0131nav\",\"system_name\":\"vibe.S\\u0131nav\",\"default_campus_id\":\"\",\"timezone\":\"Asia\\/Karachi\",\"institute_address\":\"\",\"institute_phone\":\"\",\"institute_email\":\"\",\"cert_signature_1_title\":\"\",\"cert_signature_1_name\":\"\",\"cert_signature_2_title\":\"\",\"cert_signature_2_name\":\"\",\"cert_signature_3_title\":\"\",\"cert_signature_3_name\":\"\",\"proctoring_anomaly_timer\":\"5\",\"proctoring_detect_blur\":\"1\",\"proctoring_strict_fullscreen\":\"1\",\"default_campus_name\":\"\",\"default_campus_code\":\"\"}', '{\"institute_name\":\"vibe.S\\u0131nav\",\"system_name\":\"vibe.S\\u0131na\",\"default_campus_id\":\"1\",\"timezone\":\"Asia\\/Karachi\",\"institute_address\":\"\",\"institute_phone\":\"\",\"institute_email\":\"\",\"cert_signature_1_title\":\"Competition Coordinator\",\"cert_signature_1_name\":\"\",\"cert_signature_2_title\":\"Director \\/ Principal\",\"cert_signature_2_name\":\"\",\"cert_signature_3_title\":\"Examination Controller\",\"cert_signature_3_name\":\"\",\"proctoring_anomaly_timer\":\"5\",\"proctoring_detect_blur\":\"1\",\"proctoring_strict_fullscreen\":\"1\",\"default_campus_name\":\"Main Campus\",\"default_campus_code\":\"MAIN\"}', '192.168.20.184', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-11 11:05:48'),
(384, 1, 'settings_updated', 'settings', 'System settings updated', '{\"institute_name\":\"vibe.S\\u0131nav\",\"system_name\":\"vibe.S\\u0131na\",\"default_campus_id\":\"\",\"timezone\":\"Asia\\/Karachi\",\"institute_address\":\"\",\"institute_phone\":\"\",\"institute_email\":\"\",\"cert_signature_1_title\":\"\",\"cert_signature_1_name\":\"\",\"cert_signature_2_title\":\"\",\"cert_signature_2_name\":\"\",\"cert_signature_3_title\":\"\",\"cert_signature_3_name\":\"\",\"proctoring_anomaly_timer\":\"5\",\"proctoring_detect_blur\":\"1\",\"proctoring_strict_fullscreen\":\"1\",\"default_campus_name\":\"\",\"default_campus_code\":\"\"}', '{\"institute_name\":\"vibe.S\\u0131nav\",\"system_name\":\"vibe.S\\u0131nav\",\"default_campus_id\":\"1\",\"timezone\":\"Asia\\/Karachi\",\"institute_address\":\"\",\"institute_phone\":\"\",\"institute_email\":\"\",\"cert_signature_1_title\":\"Competition Coordinator\",\"cert_signature_1_name\":\"\",\"cert_signature_2_title\":\"Director \\/ Principal\",\"cert_signature_2_name\":\"\",\"cert_signature_3_title\":\"Examination Controller\",\"cert_signature_3_name\":\"\",\"proctoring_anomaly_timer\":\"5\",\"proctoring_detect_blur\":\"1\",\"proctoring_strict_fullscreen\":\"1\",\"default_campus_name\":\"Main Campus\",\"default_campus_code\":\"MAIN\"}', '192.168.20.184', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-11 11:06:18'),
(385, 1, 'assessment_launched', 'assessments', 'Launched exam session for \'Computer Assesment Dated 9-9-26\' (ID #46)', NULL, NULL, '192.168.20.184', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-11 11:07:02'),
(386, 1, 'logout', 'auth', 'User logged out: admin', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-11 11:08:49'),
(387, 1, 'user_updated', 'users', 'Updated user \'admin\' (ID #1)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-11 11:09:29'),
(388, 1, 'settings_updated', 'settings', 'System settings updated', '{\"institute_name\":\"vibe.S\\u0131nav\",\"system_name\":\"vibe.S\\u0131nav\",\"default_campus_id\":\"\",\"timezone\":\"Asia\\/Karachi\",\"institute_address\":\"\",\"institute_phone\":\"\",\"institute_email\":\"\",\"cert_signature_1_title\":\"\",\"cert_signature_1_name\":\"\",\"cert_signature_2_title\":\"\",\"cert_signature_2_name\":\"\",\"cert_signature_3_title\":\"\",\"cert_signature_3_name\":\"\",\"proctoring_anomaly_timer\":\"5\",\"proctoring_detect_blur\":\"1\",\"proctoring_strict_fullscreen\":\"1\",\"default_campus_name\":\"\",\"default_campus_code\":\"\"}', '{\"institute_name\":\"PTM\",\"system_name\":\"vibe.S\\u0131nav\",\"default_campus_id\":\"1\",\"timezone\":\"Asia\\/Karachi\",\"institute_address\":\"\",\"institute_phone\":\"\",\"institute_email\":\"\",\"cert_signature_1_title\":\"Competition Coordinator\",\"cert_signature_1_name\":\"\",\"cert_signature_2_title\":\"Director \\/ Principal\",\"cert_signature_2_name\":\"\",\"cert_signature_3_title\":\"Examination Controller\",\"cert_signature_3_name\":\"\",\"proctoring_anomaly_timer\":\"5\",\"proctoring_detect_blur\":\"1\",\"proctoring_strict_fullscreen\":\"1\",\"default_campus_name\":\"Main Campus\",\"default_campus_code\":\"MAIN\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-11 11:14:12'),
(389, 1, 'logout', 'auth', 'User logged out: admin', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-11 11:14:23'),
(390, 1, 'login_failed', 'auth', 'Wrong password for user: admin', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-11 11:14:30'),
(391, 1, 'logout', 'auth', 'User logged out: admin', NULL, NULL, '192.168.20.184', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-11 11:14:53'),
(392, 1, 'attempt_reviewed', 'assessment_attempts', 'Finalized and generated result for attempt #46', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-11 11:26:54'),
(393, 1, 'assessment_stopped', 'assessments', 'Stopped exam session for \'Computer Assesment Dated 9-9-26\' (ID #46)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-11 11:32:02'),
(394, 1, 'logout', 'auth', 'User logged out: admin', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-11 11:35:12'),
(395, 1, 'login_failed', 'auth', 'Wrong password for user: admin', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-11 11:35:18'),
(396, 1, 'login_failed', 'auth', 'Wrong password for user: admin', NULL, NULL, NULL, NULL, '2026-09-11 11:37:38'),
(397, 1, 'login_success', 'auth', 'User logged in: admin', NULL, NULL, NULL, NULL, '2026-09-11 11:37:48'),
(398, 1, 'logout', 'auth', 'User logged out: admin', NULL, NULL, '192.168.20.184', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-11 11:39:11'),
(399, 1, 'login_success', 'auth', 'User logged in: admin', NULL, NULL, '192.168.20.184', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-11 11:39:17'),
(400, 1, 'settings_updated', 'settings', 'System settings updated', '{\"institute_name\":\"PTM\",\"system_name\":\"vibe.S\\u0131nav\",\"default_campus_id\":\"\",\"timezone\":\"Asia\\/Karachi\",\"institute_address\":\"\",\"institute_phone\":\"\",\"institute_email\":\"\",\"cert_signature_1_title\":\"\",\"cert_signature_1_name\":\"\",\"cert_signature_2_title\":\"\",\"cert_signature_2_name\":\"\",\"cert_signature_3_title\":\"\",\"cert_signature_3_name\":\"\",\"proctoring_anomaly_timer\":\"5\",\"proctoring_detect_blur\":\"1\",\"proctoring_strict_fullscreen\":\"1\",\"default_campus_name\":\"\",\"default_campus_code\":\"\"}', '{\"institute_name\":\"PTM\",\"system_name\":\"vibe.S\\u0131nav\",\"default_campus_id\":\"1\",\"timezone\":\"Asia\\/Karachi\",\"institute_address\":\"\",\"institute_phone\":\"\",\"institute_email\":\"\",\"cert_signature_1_title\":\"Competition Coordinator\",\"cert_signature_1_name\":\"\",\"cert_signature_2_title\":\"Director \\/ Principal\",\"cert_signature_2_name\":\"\",\"cert_signature_3_title\":\"Examination Controller\",\"cert_signature_3_name\":\"\",\"proctoring_anomaly_timer\":\"5\",\"proctoring_detect_blur\":\"1\",\"proctoring_strict_fullscreen\":\"1\",\"default_campus_name\":\"Main Campus\",\"default_campus_code\":\"MAIN\"}', '192.168.20.184', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-11 11:42:10'),
(401, 1, 'logout', 'auth', 'User logged out: admin', NULL, NULL, '192.168.20.184', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-11 11:42:15'),
(402, 1, 'login_success', 'auth', 'User logged in: admin', NULL, NULL, '192.168.20.184', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-11 11:42:22'),
(403, 1, 'settings_updated', 'settings', 'System settings updated', '{\"institute_name\":\"PTM\",\"system_name\":\"vibe.S\\u0131nav\",\"default_campus_id\":\"\",\"timezone\":\"Asia\\/Karachi\",\"institute_address\":\"\",\"institute_phone\":\"\",\"institute_email\":\"\",\"cert_signature_1_title\":\"\",\"cert_signature_1_name\":\"\",\"cert_signature_2_title\":\"\",\"cert_signature_2_name\":\"\",\"cert_signature_3_title\":\"\",\"cert_signature_3_name\":\"\",\"proctoring_anomaly_timer\":\"5\",\"proctoring_detect_blur\":\"1\",\"proctoring_strict_fullscreen\":\"1\",\"default_campus_name\":\"\",\"default_campus_code\":\"\"}', '{\"institute_name\":\"PTM\",\"system_name\":\"vibe.S\\u0131nav\",\"default_campus_id\":\"1\",\"timezone\":\"Asia\\/Karachi\",\"institute_address\":\"\",\"institute_phone\":\"\",\"institute_email\":\"\",\"cert_signature_1_title\":\"Competition Coordinator\",\"cert_signature_1_name\":\"\",\"cert_signature_2_title\":\"Director \\/ Principal\",\"cert_signature_2_name\":\"\",\"cert_signature_3_title\":\"Examination Controller\",\"cert_signature_3_name\":\"\",\"proctoring_anomaly_timer\":\"5\",\"proctoring_detect_blur\":\"1\",\"proctoring_strict_fullscreen\":\"1\",\"default_campus_name\":\"Main Campus\",\"default_campus_code\":\"MAIN\"}', '192.168.20.184', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-11 11:43:12'),
(404, 1, 'logout', 'auth', 'User logged out: admin', NULL, NULL, '192.168.20.184', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-11 11:43:17'),
(405, 1, 'login_success', 'auth', 'User logged in: admin', NULL, NULL, '192.168.20.184', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-11 11:43:50'),
(406, 1, 'logout', 'auth', 'User logged out: admin', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-11 12:00:58'),
(407, 1, 'login_failed', 'auth', 'Wrong password for user: admin', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-11 12:01:19'),
(408, 1, 'login_success', 'auth', 'User logged in: admin', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-11 12:01:26'),
(409, 1, 'assessment_launched', 'assessments', 'Launched exam session for \'hjhj\' (ID #48)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-11 12:02:13'),
(410, 1, 'assessment_stopped', 'assessments', 'Stopped exam session for \'hjhj\' (ID #48)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-11 12:02:21'),
(411, 1, 'assessment_launched', 'assessments', 'Launched exam session for \'Computer Assesment Dated 9-9-26\' (ID #46)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-11 12:02:30'),
(412, 1, 'settings_updated', 'settings', 'System settings updated', '{\"institute_name\":\"PTM\",\"system_name\":\"vibe.S\\u0131nav\",\"default_campus_id\":\"\",\"timezone\":\"Asia\\/Karachi\",\"institute_address\":\"\",\"institute_phone\":\"\",\"institute_email\":\"\",\"cert_signature_1_title\":\"\",\"cert_signature_1_name\":\"\",\"cert_signature_2_title\":\"\",\"cert_signature_2_name\":\"\",\"cert_signature_3_title\":\"\",\"cert_signature_3_name\":\"\",\"proctoring_anomaly_timer\":\"5\",\"proctoring_detect_blur\":\"1\",\"proctoring_strict_fullscreen\":\"1\",\"default_campus_name\":\"\",\"default_campus_code\":\"\"}', '{\"institute_name\":\"PTM\",\"system_name\":\"vibe.S\\u0131nav\",\"default_campus_id\":\"1\",\"timezone\":\"Asia\\/Karachi\",\"institute_address\":\"\",\"institute_phone\":\"\",\"institute_email\":\"\",\"cert_signature_1_title\":\"Teacher\",\"cert_signature_1_name\":\"\",\"cert_signature_2_title\":\"Vice Principal\",\"cert_signature_2_name\":\"\",\"cert_signature_3_title\":\"IT Person \\/\",\"cert_signature_3_name\":\"\",\"proctoring_anomaly_timer\":\"5\",\"proctoring_detect_blur\":\"1\",\"proctoring_strict_fullscreen\":\"1\",\"default_campus_name\":\"Main Campus\",\"default_campus_code\":\"MAIN\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-11 12:08:45'),
(413, 1, 'assessment_stopped', 'assessments', 'Stopped exam session for \'Computer Assesment Dated 9-9-26\' (ID #46)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-11 12:09:30'),
(414, 1, 'logout', 'auth', 'User logged out: admin', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-11 12:10:00'),
(415, 1, 'login_success', 'auth', 'User logged in: admin', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-11 12:15:09'),
(416, 1, 'settings_updated', 'settings', 'System settings updated', '{\"institute_name\":\"PTM\",\"system_name\":\"vibe.S\\u0131nav\",\"default_campus_id\":\"\",\"timezone\":\"Asia\\/Karachi\",\"institute_address\":\"\",\"institute_phone\":\"\",\"institute_email\":\"\",\"cert_signature_1_title\":\"\",\"cert_signature_1_name\":\"\",\"cert_signature_2_title\":\"\",\"cert_signature_2_name\":\"\",\"cert_signature_3_title\":\"\",\"cert_signature_3_name\":\"\",\"proctoring_anomaly_timer\":\"5\",\"proctoring_detect_blur\":\"1\",\"proctoring_strict_fullscreen\":\"1\",\"default_campus_name\":\"\",\"default_campus_code\":\"\"}', '{\"institute_name\":\"PTM\",\"system_name\":\"vibe.S\\u0131navv\",\"default_campus_id\":\"1\",\"timezone\":\"Asia\\/Karachi\",\"institute_address\":\"\",\"institute_phone\":\"\",\"institute_email\":\"\",\"cert_signature_1_title\":\"Competition Coordinator\",\"cert_signature_1_name\":\"\",\"cert_signature_2_title\":\"Director \\/ Principal\",\"cert_signature_2_name\":\"\",\"cert_signature_3_title\":\"Examination Controller\",\"cert_signature_3_name\":\"\",\"proctoring_anomaly_timer\":\"5\",\"proctoring_detect_blur\":\"1\",\"proctoring_strict_fullscreen\":\"1\",\"default_campus_name\":\"Main Campus\",\"default_campus_code\":\"MAIN\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-11 12:15:36'),
(417, 1, 'settings_updated', 'settings', 'System settings updated', '{\"institute_name\":\"PTM\",\"system_name\":\"vibe.S\\u0131navv\",\"default_campus_id\":\"\",\"timezone\":\"Asia\\/Karachi\",\"institute_address\":\"\",\"institute_phone\":\"\",\"institute_email\":\"\",\"cert_signature_1_title\":\"\",\"cert_signature_1_name\":\"\",\"cert_signature_2_title\":\"\",\"cert_signature_2_name\":\"\",\"cert_signature_3_title\":\"\",\"cert_signature_3_name\":\"\",\"proctoring_anomaly_timer\":\"5\",\"proctoring_detect_blur\":\"1\",\"proctoring_strict_fullscreen\":\"1\",\"default_campus_name\":\"\",\"default_campus_code\":\"\"}', '{\"institute_name\":\"PTM\",\"system_name\":\"vibe.S\\u0131nav\",\"default_campus_id\":\"1\",\"timezone\":\"Asia\\/Karachi\",\"institute_address\":\"\",\"institute_phone\":\"\",\"institute_email\":\"\",\"cert_signature_1_title\":\"Competition Coordinator\",\"cert_signature_1_name\":\"\",\"cert_signature_2_title\":\"Director \\/ Principal\",\"cert_signature_2_name\":\"\",\"cert_signature_3_title\":\"Examination Controller\",\"cert_signature_3_name\":\"\",\"proctoring_anomaly_timer\":\"5\",\"proctoring_detect_blur\":\"1\",\"proctoring_strict_fullscreen\":\"1\",\"default_campus_name\":\"Main Campus\",\"default_campus_code\":\"MAIN\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-11 12:15:44'),
(418, 1, 'settings_updated', 'settings', 'System settings updated', '{\"institute_name\":\"PTM\",\"system_name\":\"vibe.S\\u0131nav\",\"default_campus_id\":\"\",\"timezone\":\"Asia\\/Karachi\",\"institute_address\":\"\",\"institute_phone\":\"\",\"institute_email\":\"\",\"cert_signature_1_title\":\"\",\"cert_signature_1_name\":\"\",\"cert_signature_2_title\":\"\",\"cert_signature_2_name\":\"\",\"cert_signature_3_title\":\"\",\"cert_signature_3_name\":\"\",\"proctoring_anomaly_timer\":\"5\",\"proctoring_detect_blur\":\"1\",\"proctoring_strict_fullscreen\":\"1\",\"default_campus_name\":\"\",\"default_campus_code\":\"\"}', '{\"institute_name\":\"Pak-turk Maarif\",\"system_name\":\"vibe.S\\u0131nav\",\"default_campus_id\":\"1\",\"timezone\":\"Asia\\/Karachi\",\"institute_address\":\"\",\"institute_phone\":\"\",\"institute_email\":\"\",\"cert_signature_1_title\":\"Competition Coordinator\",\"cert_signature_1_name\":\"\",\"cert_signature_2_title\":\"Director \\/ Principal\",\"cert_signature_2_name\":\"\",\"cert_signature_3_title\":\"Examination Controller\",\"cert_signature_3_name\":\"\",\"proctoring_anomaly_timer\":\"5\",\"proctoring_detect_blur\":\"1\",\"proctoring_strict_fullscreen\":\"1\",\"default_campus_name\":\"Main Campus\",\"default_campus_code\":\"MAIN\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-11 12:16:18'),
(419, 1, 'logout', 'auth', 'User logged out: admin', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-11 12:16:38'),
(420, 1, 'login_failed', 'auth', 'Wrong password for user: admin', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-11 12:17:21'),
(421, 1, 'login_success', 'auth', 'User logged in: admin', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-11 12:17:56'),
(422, 1, 'settings_updated', 'settings', 'System settings updated', '{\"institute_name\":\"Pak-turk Maarif\",\"system_name\":\"vibe.S\\u0131nav\",\"default_campus_id\":\"\",\"timezone\":\"Asia\\/Karachi\",\"institute_address\":\"\",\"institute_phone\":\"\",\"institute_email\":\"\",\"cert_signature_1_title\":\"\",\"cert_signature_1_name\":\"\",\"cert_signature_2_title\":\"\",\"cert_signature_2_name\":\"\",\"cert_signature_3_title\":\"\",\"cert_signature_3_name\":\"\",\"proctoring_anomaly_timer\":\"5\",\"proctoring_detect_blur\":\"1\",\"proctoring_strict_fullscreen\":\"1\",\"default_campus_name\":\"\",\"default_campus_code\":\"\"}', '{\"institute_name\":\"Maarif\",\"system_name\":\"vibe.S\\u0131nav\",\"default_campus_id\":\"1\",\"timezone\":\"Asia\\/Karachi\",\"institute_address\":\"\",\"institute_phone\":\"\",\"institute_email\":\"\",\"cert_signature_1_title\":\"Competition Coordinator\",\"cert_signature_1_name\":\"\",\"cert_signature_2_title\":\"Director \\/ Principal\",\"cert_signature_2_name\":\"\",\"cert_signature_3_title\":\"Examination Controller\",\"cert_signature_3_name\":\"\",\"proctoring_anomaly_timer\":\"5\",\"proctoring_detect_blur\":\"1\",\"proctoring_strict_fullscreen\":\"1\",\"default_campus_name\":\"Main Campus\",\"default_campus_code\":\"MAIN\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-11 12:19:57'),
(423, 1, 'logout', 'auth', 'User logged out: admin', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-11 12:20:06'),
(424, 1, 'login_failed', 'auth', 'Wrong password for user: admin', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-11 12:20:14'),
(425, 1, 'login_success', 'auth', 'User logged in: admin', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-11 12:20:19'),
(426, 1, 'campus_updated', 'campuses', 'Updated campus \'Khairpur Campus\' (KPR)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-11 12:33:25'),
(427, 1, 'settings_updated', 'settings', 'System settings updated', '{\"institute_name\":\"Maarif\",\"system_name\":\"vibe.S\\u0131nav\",\"default_campus_id\":\"\",\"timezone\":\"Asia\\/Karachi\",\"institute_address\":\"\",\"institute_phone\":\"\",\"institute_email\":\"\",\"cert_signature_1_title\":\"\",\"cert_signature_1_name\":\"\",\"cert_signature_2_title\":\"\",\"cert_signature_2_name\":\"\",\"cert_signature_3_title\":\"\",\"cert_signature_3_name\":\"\",\"proctoring_anomaly_timer\":\"5\",\"proctoring_detect_blur\":\"1\",\"proctoring_strict_fullscreen\":\"1\",\"default_campus_name\":\"\",\"default_campus_code\":\"\"}', '{\"institute_name\":\"Turkish Maarif Foundation\",\"system_name\":\"vibe.S\\u0131nav\",\"default_campus_id\":\"1\",\"timezone\":\"Asia\\/Karachi\",\"institute_address\":\"\",\"institute_phone\":\"\",\"institute_email\":\"\",\"cert_signature_1_title\":\"Competition Coordinator\",\"cert_signature_1_name\":\"\",\"cert_signature_2_title\":\"Director \\/ Principal\",\"cert_signature_2_name\":\"\",\"cert_signature_3_title\":\"Examination Controller\",\"cert_signature_3_name\":\"\",\"proctoring_anomaly_timer\":\"5\",\"proctoring_detect_blur\":\"1\",\"proctoring_strict_fullscreen\":\"1\",\"default_campus_name\":\"Khairpur Campus\",\"default_campus_code\":\"KPR\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-11 12:33:52'),
(428, 1, 'settings_updated', 'settings', 'System settings updated', '{\"institute_name\":\"Turkish Maarif Foundation\",\"system_name\":\"vibe.S\\u0131nav\",\"default_campus_id\":\"\",\"timezone\":\"Asia\\/Karachi\",\"institute_address\":\"\",\"institute_phone\":\"\",\"institute_email\":\"\",\"cert_signature_1_title\":\"\",\"cert_signature_1_name\":\"\",\"cert_signature_2_title\":\"\",\"cert_signature_2_name\":\"\",\"cert_signature_3_title\":\"\",\"cert_signature_3_name\":\"\",\"proctoring_anomaly_timer\":\"5\",\"proctoring_detect_blur\":\"1\",\"proctoring_strict_fullscreen\":\"1\",\"default_campus_name\":\"\",\"default_campus_code\":\"\"}', '{\"institute_name\":\"Maarif\",\"system_name\":\"vibe.S\\u0131nav\",\"default_campus_id\":\"1\",\"timezone\":\"Asia\\/Karachi\",\"institute_address\":\"\",\"institute_phone\":\"\",\"institute_email\":\"\",\"cert_signature_1_title\":\"Competition Coordinator\",\"cert_signature_1_name\":\"\",\"cert_signature_2_title\":\"Director \\/ Principal\",\"cert_signature_2_name\":\"\",\"cert_signature_3_title\":\"Examination Controller\",\"cert_signature_3_name\":\"\",\"proctoring_anomaly_timer\":\"5\",\"proctoring_detect_blur\":\"1\",\"proctoring_strict_fullscreen\":\"1\",\"default_campus_name\":\"Khairpur Campus\",\"default_campus_code\":\"KPR\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-11 12:34:01'),
(429, 1, 'campus_created', 'campuses', 'Created campus \'Hyderabad Campus\' (HYD)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-11 12:34:18'),
(430, 1, 'campus_status_toggled', 'campuses', 'Changed campus #3 status to inactive', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-11 12:34:24'),
(431, 1, 'campus_status_toggled', 'campuses', 'Changed campus #3 status to active', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-11 12:34:41'),
(432, 1, 'assessment_updated', 'assessments', 'Updated assessment ID #48', NULL, NULL, NULL, NULL, '2026-09-11 12:41:16'),
(433, 1, 'assessment_created', 'assessments', 'Created assessment \'Temp Delete Test\' (ID #49)', NULL, NULL, NULL, NULL, '2026-09-11 12:41:16'),
(434, 1, 'assessment_deleted', 'assessments', 'Deleted assessment \'Temp Delete Test\' (ID #49)', NULL, NULL, NULL, NULL, '2026-09-11 12:41:16');
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `module`, `description`, `old_values`, `new_values`, `ip_address`, `user_agent`, `created_at`) VALUES
(435, 1, 'assessment_launched', 'assessments', 'Launched exam session for \'Computer Assesment Dated 9-9-26\' (ID #46)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-11 12:56:40'),
(436, 1, 'assessment_stopped', 'assessments', 'Stopped exam session for \'Computer Assesment Dated 9-9-26\' (ID #46)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-11 12:57:47'),
(437, 1, 'logout', 'auth', 'User logged out: admin', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-11 13:00:56'),
(438, 1, 'login_failed', 'auth', 'Wrong password for user: admin', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-11 13:09:08'),
(439, 1, 'login_success', 'auth', 'User logged in: admin', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-11 13:09:12'),
(440, 1, 'assessment_launched', 'assessments', 'Launched exam session for \'hjhj (Edited Test)\' (ID #48)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-11 13:18:35'),
(441, 1, 'assessment_stopped', 'assessments', 'Stopped exam session for \'hjhj (Edited Test)\' (ID #48)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-11 13:19:09'),
(442, 1, 'settings_updated', 'settings', 'System settings updated', '{\"institute_name\":\"Maarif\",\"system_name\":\"vibe.S\\u0131nav\",\"default_campus_id\":\"\",\"timezone\":\"Asia\\/Karachi\",\"institute_address\":\"\",\"institute_phone\":\"\",\"institute_email\":\"\",\"cert_signature_1_title\":\"\",\"cert_signature_1_name\":\"\",\"cert_signature_2_title\":\"\",\"cert_signature_2_name\":\"\",\"cert_signature_3_title\":\"\",\"cert_signature_3_name\":\"\",\"proctoring_anomaly_timer\":\"5\",\"proctoring_detect_blur\":\"1\",\"proctoring_strict_fullscreen\":\"1\",\"default_campus_name\":\"\",\"default_campus_code\":\"\"}', '{\"institute_name\":\"Maarif\",\"system_name\":\"vibe.S\\u0131nav\",\"default_campus_id\":\"1\",\"timezone\":\"Asia\\/Karachi\",\"institute_address\":\"\",\"institute_phone\":\"\",\"institute_email\":\"\",\"cert_signature_1_title\":\"Competition Coordinator\",\"cert_signature_1_name\":\"\",\"cert_signature_2_title\":\"Director \\/ Principal\",\"cert_signature_2_name\":\"\",\"cert_signature_3_title\":\"Examination Controller\",\"cert_signature_3_name\":\"\",\"proctoring_anomaly_timer\":\"5\",\"proctoring_detect_blur\":\"1\",\"proctoring_strict_fullscreen\":\"1\",\"default_campus_name\":\"Khairpur Campus\",\"default_campus_code\":\"KPR\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-11 13:21:37'),
(443, 1, 'settings_updated', 'settings', 'System settings updated', '{\"institute_name\":\"Maarif\",\"system_name\":\"vibe.S\\u0131nav\",\"default_campus_id\":\"\",\"timezone\":\"Asia\\/Karachi\",\"institute_address\":\"\",\"institute_phone\":\"\",\"institute_email\":\"\",\"cert_signature_1_title\":\"\",\"cert_signature_1_name\":\"\",\"cert_signature_2_title\":\"\",\"cert_signature_2_name\":\"\",\"cert_signature_3_title\":\"\",\"cert_signature_3_name\":\"\",\"proctoring_anomaly_timer\":\"5\",\"proctoring_detect_blur\":\"1\",\"proctoring_strict_fullscreen\":\"1\",\"default_campus_name\":\"\",\"default_campus_code\":\"\"}', '{\"institute_name\":\"Maarif\",\"system_name\":\"vibe.S\\u0131nav\",\"default_campus_id\":\"1\",\"timezone\":\"Asia\\/Karachi\",\"institute_address\":\"\",\"institute_phone\":\"\",\"institute_email\":\"\",\"cert_signature_1_title\":\"Competition Coordinator\",\"cert_signature_1_name\":\"\",\"cert_signature_2_title\":\"Director \\/ Principal\",\"cert_signature_2_name\":\"\",\"cert_signature_3_title\":\"Examination Controller\",\"cert_signature_3_name\":\"\",\"proctoring_anomaly_timer\":\"5\",\"proctoring_detect_blur\":\"1\",\"proctoring_strict_fullscreen\":\"1\",\"default_campus_name\":\"Khairpur Campus\",\"default_campus_code\":\"KPR\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-11 13:21:56'),
(444, 1, 'settings_updated', 'settings', 'System settings updated', '{\"institute_name\":\"Maarif\",\"system_name\":\"vibe.S\\u0131nav\",\"default_campus_id\":\"\",\"timezone\":\"Asia\\/Karachi\",\"institute_address\":\"\",\"institute_phone\":\"\",\"institute_email\":\"\",\"cert_signature_1_title\":\"\",\"cert_signature_1_name\":\"\",\"cert_signature_2_title\":\"\",\"cert_signature_2_name\":\"\",\"cert_signature_3_title\":\"\",\"cert_signature_3_name\":\"\",\"proctoring_anomaly_timer\":\"5\",\"proctoring_detect_blur\":\"1\",\"proctoring_strict_fullscreen\":\"1\",\"default_campus_name\":\"\",\"default_campus_code\":\"\"}', '{\"institute_name\":\"Maarif\",\"system_name\":\"vibe.S\\u0131nav\",\"default_campus_id\":\"1\",\"timezone\":\"Asia\\/Karachi\",\"institute_address\":\"\",\"institute_phone\":\"\",\"institute_email\":\"\",\"cert_signature_1_title\":\"Competition Coordinator\",\"cert_signature_1_name\":\"\",\"cert_signature_2_title\":\"Director \\/ Principal\",\"cert_signature_2_name\":\"\",\"cert_signature_3_title\":\"Examination Controller\",\"cert_signature_3_name\":\"\",\"proctoring_anomaly_timer\":\"5\",\"proctoring_detect_blur\":\"1\",\"proctoring_strict_fullscreen\":\"1\",\"default_campus_name\":\"Khairpur Campus\",\"default_campus_code\":\"KPR\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-11 13:25:39'),
(445, 1, 'settings_updated', 'settings', 'System settings updated', '{\"institute_name\":\"Maarif\",\"system_name\":\"vibe.S\\u0131nav\",\"default_campus_id\":\"\",\"timezone\":\"Asia\\/Karachi\",\"institute_address\":\"\",\"institute_phone\":\"\",\"institute_email\":\"\",\"cert_signature_1_title\":\"\",\"cert_signature_1_name\":\"\",\"cert_signature_2_title\":\"\",\"cert_signature_2_name\":\"\",\"cert_signature_3_title\":\"\",\"cert_signature_3_name\":\"\",\"proctoring_anomaly_timer\":\"5\",\"proctoring_detect_blur\":\"1\",\"proctoring_strict_fullscreen\":\"1\",\"default_campus_name\":\"\",\"default_campus_code\":\"\"}', '{\"institute_name\":\"Maarif\",\"system_name\":\"vibe.S\\u0131nav\",\"default_campus_id\":\"1\",\"timezone\":\"Asia\\/Karachi\",\"institute_address\":\"\",\"institute_phone\":\"\",\"institute_email\":\"\",\"cert_signature_1_title\":\"Competition Coordinator\",\"cert_signature_1_name\":\"\",\"cert_signature_2_title\":\"Director \\/ Principal\",\"cert_signature_2_name\":\"\",\"cert_signature_3_title\":\"Examination Controller\",\"cert_signature_3_name\":\"\",\"proctoring_anomaly_timer\":\"5\",\"proctoring_detect_blur\":\"1\",\"proctoring_strict_fullscreen\":\"1\",\"default_campus_name\":\"Khairpur Campus\",\"default_campus_code\":\"KPR\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-11 13:26:55'),
(446, 1, 'campus_status_toggled', 'campuses', 'Changed campus #1 status to inactive', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-11 13:27:04'),
(447, 1, 'campus_deleted', 'campuses', 'Deleted campus \'Hyderabad Campus\' (ID: 3)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-11 13:27:09'),
(448, 1, 'assessment_launched', 'assessments', 'Launched exam session for \'hjhj (Edited Test)\' (ID #48)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-11 13:27:23'),
(449, 1, 'settings_updated', 'settings', 'System settings updated', '{\"institute_name\":\"Maarif\",\"system_name\":\"vibe.S\\u0131nav\",\"default_campus_id\":\"\",\"timezone\":\"Asia\\/Karachi\",\"institute_address\":\"\",\"institute_phone\":\"\",\"institute_email\":\"\",\"cert_signature_1_title\":\"\",\"cert_signature_1_name\":\"\",\"cert_signature_2_title\":\"\",\"cert_signature_2_name\":\"\",\"cert_signature_3_title\":\"\",\"cert_signature_3_name\":\"\",\"proctoring_anomaly_timer\":\"5\",\"proctoring_detect_blur\":\"1\",\"proctoring_strict_fullscreen\":\"1\",\"default_campus_name\":\"\",\"default_campus_code\":\"\"}', '{\"institute_name\":\"Maarif\",\"system_name\":\"vibe.S\\u0131nav\",\"default_campus_id\":\"1\",\"timezone\":\"Asia\\/Karachi\",\"institute_address\":\"\",\"institute_phone\":\"\",\"institute_email\":\"\",\"cert_signature_1_title\":\"Competition Coordinator\",\"cert_signature_1_name\":\"\",\"cert_signature_2_title\":\"Director \\/ Principal\",\"cert_signature_2_name\":\"\",\"cert_signature_3_title\":\"Examination Controller\",\"cert_signature_3_name\":\"\",\"proctoring_anomaly_timer\":\"5\",\"proctoring_detect_blur\":\"1\",\"proctoring_strict_fullscreen\":\"1\",\"default_campus_name\":\"Khairpur Campus\",\"default_campus_code\":\"KPR\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-11 13:43:37'),
(450, 1, 'settings_updated', 'settings', 'System settings updated', '{\"institute_name\":\"Maarif\",\"system_name\":\"vibe.S\\u0131nav\",\"default_campus_id\":\"\",\"timezone\":\"Asia\\/Karachi\",\"institute_address\":\"\",\"institute_phone\":\"\",\"institute_email\":\"\",\"cert_signature_1_title\":\"\",\"cert_signature_1_name\":\"\",\"cert_signature_2_title\":\"\",\"cert_signature_2_name\":\"\",\"cert_signature_3_title\":\"\",\"cert_signature_3_name\":\"\",\"proctoring_anomaly_timer\":\"5\",\"proctoring_detect_blur\":\"1\",\"proctoring_strict_fullscreen\":\"1\",\"default_campus_name\":\"\",\"default_campus_code\":\"\"}', '{\"institute_name\":\"Maarif\",\"system_name\":\"vibe.S\\u0131nav\",\"default_campus_id\":\"1\",\"timezone\":\"Asia\\/Karachi\",\"institute_address\":\"\",\"institute_phone\":\"\",\"institute_email\":\"\",\"cert_signature_1_title\":\"Competition Coordinator\",\"cert_signature_1_name\":\"\",\"cert_signature_2_title\":\"Director \\/ Principal\",\"cert_signature_2_name\":\"\",\"cert_signature_3_title\":\"Examination Controller\",\"cert_signature_3_name\":\"\",\"proctoring_anomaly_timer\":\"5\",\"proctoring_detect_blur\":\"1\",\"proctoring_strict_fullscreen\":\"1\",\"default_campus_name\":\"Khairpur Campus\",\"default_campus_code\":\"KPR\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-11 13:44:33'),
(451, 1, 'settings_updated', 'settings', 'System settings updated', '{\"institute_name\":\"Maarif\",\"system_name\":\"vibe.S\\u0131nav\",\"default_campus_id\":\"\",\"timezone\":\"Asia\\/Karachi\",\"institute_address\":\"\",\"institute_phone\":\"\",\"institute_email\":\"\",\"cert_signature_1_title\":\"\",\"cert_signature_1_name\":\"\",\"cert_signature_2_title\":\"\",\"cert_signature_2_name\":\"\",\"cert_signature_3_title\":\"\",\"cert_signature_3_name\":\"\",\"proctoring_anomaly_timer\":\"5\",\"proctoring_detect_blur\":\"1\",\"proctoring_strict_fullscreen\":\"1\",\"default_campus_name\":\"\",\"default_campus_code\":\"\"}', '{\"institute_name\":\"Maarif\",\"system_name\":\"vibe.S\\u0131nav\",\"default_campus_id\":\"1\",\"timezone\":\"Asia\\/Karachi\",\"institute_address\":\"\",\"institute_phone\":\"\",\"institute_email\":\"\",\"cert_signature_1_title\":\"Competition Coordinator\",\"cert_signature_1_name\":\"\",\"cert_signature_2_title\":\"Director \\/ Principal\",\"cert_signature_2_name\":\"\",\"cert_signature_3_title\":\"Examination Controller\",\"cert_signature_3_name\":\"\",\"proctoring_anomaly_timer\":\"5\",\"proctoring_detect_blur\":\"1\",\"proctoring_strict_fullscreen\":\"1\",\"default_campus_name\":\"Khairpur Campus\",\"default_campus_code\":\"KPR\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-11 13:44:46'),
(452, 1, 'assessment_stopped', 'assessments', 'Stopped exam session for \'hjhj (Edited Test)\' (ID #48)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-11 13:45:02'),
(453, 1, 'logout', 'auth', 'User logged out: admin', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-11 13:46:13'),
(454, 1, 'login_success', 'auth', 'User logged in: admin', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-11 14:01:52'),
(455, 1, 'attempt_reviewed', 'assessment_attempts', 'Finalized and generated result for attempt #48', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-11 14:07:35'),
(456, 1, 'logout', 'auth', 'User logged out: admin', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-11 14:10:04');

-- --------------------------------------------------------

--
-- Table structure for table `campuses`
--

CREATE TABLE `campuses` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `code` varchar(20) NOT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `campuses`
--

INSERT INTO `campuses` (`id`, `name`, `code`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Khairpur Campus', 'KPR', 'inactive', '2026-09-03 09:21:55', '2026-09-11 13:27:04');

-- --------------------------------------------------------

--
-- Table structure for table `lab_stations`
--

CREATE TABLE `lab_stations` (
  `id` int(10) UNSIGNED NOT NULL,
  `campus_id` int(10) UNSIGNED NOT NULL,
  `station_code` varchar(50) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `status` enum('active','disabled') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `lab_stations`
--

INSERT INTO `lab_stations` (`id`, `campus_id`, `station_code`, `ip_address`, `status`, `created_at`) VALUES
(72, 1, 'PC-A1', NULL, 'active', '2026-09-08 19:41:34'),
(74, 1, 'PC-A15', NULL, 'active', '2026-09-08 19:44:37'),
(79, 1, 'PC-A01', NULL, 'active', '2026-09-09 09:18:30'),
(80, 1, 'PC-A3', NULL, 'active', '2026-09-09 12:17:18'),
(81, 1, 'PC-21', NULL, 'active', '2026-09-11 13:27:51'),
(82, 1, 'PC-MON', NULL, 'active', '2026-09-11 13:30:12');

-- --------------------------------------------------------

--
-- Table structure for table `permissions`
--

CREATE TABLE `permissions` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `module` varchar(50) NOT NULL COMMENT 'Module grouping: users, competitions, etc.',
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `permissions`
--

INSERT INTO `permissions` (`id`, `name`, `slug`, `module`, `description`, `created_at`, `updated_at`) VALUES
(73, 'View Dashboard', 'dashboard.view', 'dashboard', 'Access the dashboard', '2026-09-02 15:35:02', '2026-09-02 15:35:02'),
(74, 'View Users', 'users.view', 'users', 'View user list', '2026-09-02 15:35:02', '2026-09-02 15:35:02'),
(75, 'Create Users', 'users.create', 'users', 'Create new users', '2026-09-02 15:35:02', '2026-09-02 15:35:02'),
(76, 'Edit Users', 'users.edit', 'users', 'Edit existing users', '2026-09-02 15:35:02', '2026-09-02 15:35:02'),
(77, 'Delete Users', 'users.delete', 'users', 'Delete users', '2026-09-02 15:35:02', '2026-09-02 15:35:02'),
(78, 'Manage Roles', 'roles.manage', 'users', 'Manage roles and permissions', '2026-09-02 15:35:02', '2026-09-02 15:35:02'),
(79, 'View Competitions', 'competitions.view', 'competitions', 'View competition list', '2026-09-02 15:35:02', '2026-09-02 15:35:02'),
(80, 'Create Competitions', 'competitions.create', 'competitions', 'Create new competitions', '2026-09-02 15:35:02', '2026-09-02 15:35:02'),
(81, 'Edit Competitions', 'competitions.edit', 'competitions', 'Edit existing competitions', '2026-09-02 15:35:02', '2026-09-02 15:35:02'),
(82, 'Delete Competitions', 'competitions.delete', 'competitions', 'Delete competitions', '2026-09-02 15:35:02', '2026-09-02 15:35:02'),
(83, 'Manage Competition Status', 'competitions.manage_status', 'competitions', 'Change competition status', '2026-09-02 15:35:02', '2026-09-02 15:35:02'),
(84, 'View Candidates', 'candidates.view', 'candidates', 'View candidate list', '2026-09-02 15:35:02', '2026-09-02 15:35:02'),
(85, 'Create Candidates', 'candidates.create', 'candidates', 'Register new candidates', '2026-09-02 15:35:02', '2026-09-02 15:35:02'),
(86, 'Edit Candidates', 'candidates.edit', 'candidates', 'Edit candidate details', '2026-09-02 15:35:02', '2026-09-02 15:35:02'),
(87, 'Delete Candidates', 'candidates.delete', 'candidates', 'Remove candidates', '2026-09-02 15:35:02', '2026-09-02 15:35:02'),
(88, 'Approve Candidates', 'candidates.approve', 'candidates', 'Approve candidate registration', '2026-09-02 15:35:02', '2026-09-02 15:35:02'),
(89, 'Import Candidates', 'candidates.import', 'candidates', 'Bulk import candidates via CSV', '2026-09-02 15:35:02', '2026-09-02 15:35:02'),
(90, 'View Paragraphs', 'paragraphs.view', 'paragraphs', 'View typing paragraphs', '2026-09-02 15:35:02', '2026-09-02 15:35:02'),
(91, 'Create Paragraphs', 'paragraphs.create', 'paragraphs', 'Create new paragraphs', '2026-09-02 15:35:02', '2026-09-02 15:35:02'),
(92, 'Edit Paragraphs', 'paragraphs.edit', 'paragraphs', 'Edit paragraphs', '2026-09-02 15:35:02', '2026-09-02 15:35:02'),
(93, 'Delete Paragraphs', 'paragraphs.delete', 'paragraphs', 'Delete paragraphs', '2026-09-02 15:35:02', '2026-09-02 15:35:02'),
(94, 'View Tests', 'tests.view', 'tests', 'View test sessions', '2026-09-02 15:35:02', '2026-09-02 15:35:02'),
(95, 'Manage Tests', 'tests.manage', 'tests', 'Start/stop/manage test sessions', '2026-09-02 15:35:02', '2026-09-02 15:35:02'),
(96, 'Configure Tests', 'tests.configure', 'tests', 'Configure test settings', '2026-09-02 15:35:02', '2026-09-02 15:35:02'),
(97, 'View Test Settings', 'test_settings.view', 'test_settings', 'View competition test settings', '2026-09-02 15:35:02', '2026-09-02 15:35:02'),
(98, 'Manage Test Settings', 'test_settings.manage', 'test_settings', 'Manage and modify competition test settings', '2026-09-02 15:35:02', '2026-09-02 15:35:02'),
(99, 'View Results', 'results.view', 'results', 'View test results', '2026-09-02 15:35:02', '2026-09-02 15:35:02'),
(100, 'Export Results', 'results.export', 'results', 'Export results to PDF/Excel', '2026-09-02 15:35:02', '2026-09-02 15:35:02'),
(101, 'Manage Results', 'results.manage', 'results', 'Edit/recalculate results', '2026-09-02 15:35:02', '2026-09-02 15:35:02'),
(102, 'View Attendance', 'attendance.view', 'attendance', 'View attendance records', '2026-09-02 15:35:02', '2026-09-02 15:35:02'),
(103, 'Manage Attendance', 'attendance.manage', 'attendance', 'Mark/edit attendance', '2026-09-02 15:35:02', '2026-09-02 15:35:02'),
(104, 'View Settings', 'settings.view', 'settings', 'View system settings', '2026-09-02 15:35:02', '2026-09-02 15:35:02'),
(105, 'Manage Settings', 'settings.manage', 'settings', 'Modify system settings', '2026-09-02 15:35:02', '2026-09-02 15:35:02'),
(106, 'View Audit Logs', 'audit.view', 'audit', 'View audit log records', '2026-09-02 15:35:02', '2026-09-02 15:35:02'),
(107, 'View Reports', 'reports.view', 'reports', 'View system reports', '2026-09-02 15:35:02', '2026-09-02 15:35:02'),
(108, 'Export Reports', 'reports.export', 'reports', 'Export reports', '2026-09-02 15:35:02', '2026-09-02 15:35:02'),
(109, 'View Campuses', 'campuses.view', 'campuses', 'View list of campuses', '2026-09-02 18:34:58', '2026-09-02 18:34:58'),
(110, 'Manage Campuses', 'campuses.manage', 'campuses', 'Create, edit, and configure campuses', '2026-09-02 18:34:58', '2026-09-02 18:34:58'),
(111, 'View Subjects', 'subjects.view', 'subjects', 'View subjects and categories', '2026-09-02 18:34:58', '2026-09-02 18:34:58'),
(112, 'Manage Subjects', 'subjects.manage', 'subjects', 'Create and modify subjects and categories', '2026-09-02 18:34:58', '2026-09-02 18:34:58'),
(113, 'View Questions', 'questions.view', 'questions', 'View question bank questions', '2026-09-02 18:34:58', '2026-09-02 18:34:58'),
(114, 'Create Questions', 'questions.create', 'questions', 'Add new questions to question bank', '2026-09-02 18:34:58', '2026-09-02 18:34:58'),
(115, 'Edit Questions', 'questions.edit', 'questions', 'Edit questions', '2026-09-02 18:34:58', '2026-09-02 18:34:58'),
(116, 'Delete Questions', 'questions.delete', 'questions', 'Delete questions', '2026-09-02 18:34:58', '2026-09-02 18:34:58'),
(117, 'View Assessments', 'assessments.view', 'assessments', 'View assessments and quizzes', '2026-09-02 18:34:58', '2026-09-02 18:34:58'),
(118, 'Create Assessments', 'assessments.create', 'assessments', 'Create new assessments and quizzes', '2026-09-02 18:34:58', '2026-09-02 18:34:58'),
(119, 'Edit Assessments', 'assessments.edit', 'assessments', 'Edit assessment details and rules', '2026-09-02 18:34:58', '2026-09-02 18:34:58'),
(120, 'Delete Assessments', 'assessments.delete', 'assessments', 'Remove assessments', '2026-09-02 18:34:58', '2026-09-02 18:34:58'),
(121, 'Publish Assessments', 'assessments.publish', 'assessments', 'Publish or unpublish assessments', '2026-09-02 18:34:58', '2026-09-02 18:34:58'),
(122, 'Preview Assessments', 'assessments.preview', 'assessments', 'Preview student assessment experience', '2026-09-02 18:34:58', '2026-09-02 18:34:58'),
(123, 'View Assessment Results', 'assessment_results.view', 'assessment_results', 'View candidate quiz scores and results', '2026-09-02 18:34:58', '2026-09-02 18:34:58'),
(124, 'Export Assessment Results', 'assessment_results.export', 'assessment_results', 'Export quiz results to CSV/print', '2026-09-02 18:34:58', '2026-09-02 18:34:58'),
(125, 'View Teacher Dashboard', 'teacher.dashboard', 'teacher', 'Access the teacher dashboard', '2026-09-02 18:34:58', '2026-09-02 18:34:58'),
(126, 'Delete / Reset Results', 'results.delete', 'results', 'Delete or reset candidate exam attempts and score records', '2026-09-09 06:34:06', '2026-09-09 06:34:06'),
(127, 'View Security Violations', 'security_events.view', 'security', 'View exam proctoring violation logs and telemetry', '2026-09-11 10:57:28', '2026-09-11 10:57:28');

-- --------------------------------------------------------

--
-- Table structure for table `questions`
--

CREATE TABLE `questions` (
  `id` int(10) UNSIGNED NOT NULL,
  `subject_id` int(10) UNSIGNED NOT NULL,
  `created_by` int(10) UNSIGNED NOT NULL,
  `question_text` text NOT NULL,
  `question_type` enum('single_choice','multiple_choice','true_false','fill_blank','short_answer') NOT NULL,
  `difficulty` enum('easy','medium','hard') NOT NULL DEFAULT 'medium',
  `marks` decimal(4,2) NOT NULL DEFAULT 1.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `questions`
--

INSERT INTO `questions` (`id`, `subject_id`, `created_by`, `question_text`, `question_type`, `difficulty`, `marks`, `created_at`, `updated_at`) VALUES
(66, 1, 1, 'Which of the following are web browsers? (Select all that apply)', 'multiple_choice', 'medium', 10.00, '2026-09-09 09:15:39', '2026-09-09 09:15:39'),
(67, 1, 1, 'How many bits are there in one byte?', 'multiple_choice', 'medium', 10.00, '2026-09-09 09:15:39', '2026-09-09 09:15:39'),
(68, 1, 1, 'Which of the following are storage devices? (Select all that apply)', 'multiple_choice', 'medium', 10.00, '2026-09-09 09:15:39', '2026-09-09 09:15:39'),
(69, 1, 1, 'Which of the following are commonly used in web development? (Select all that apply)', 'multiple_choice', 'medium', 10.00, '2026-09-09 09:15:39', '2026-09-09 09:15:39');

-- --------------------------------------------------------

--
-- Table structure for table `question_options`
--

CREATE TABLE `question_options` (
  `id` int(10) UNSIGNED NOT NULL,
  `question_id` int(10) UNSIGNED NOT NULL,
  `option_text` text NOT NULL,
  `is_correct` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `question_options`
--

INSERT INTO `question_options` (`id`, `question_id`, `option_text`, `is_correct`) VALUES
(162, 66, 'Chrome', 1),
(163, 66, 'Safari', 1),
(164, 66, 'Gnome', 0),
(165, 66, 'Edge', 1),
(166, 67, '8', 1),
(167, 67, '4', 0),
(168, 67, '2', 0),
(169, 67, '10', 0),
(170, 68, 'SSD', 1),
(171, 68, 'HDD', 1),
(172, 68, 'USB', 1),
(173, 68, 'Keyboard', 0),
(174, 69, 'HTML', 1),
(175, 69, 'CSS', 0),
(176, 69, 'JS', 0),
(177, 69, 'Excel', 0);

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(50) NOT NULL,
  `slug` varchar(50) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `is_system` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'System roles cannot be deleted',
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `name`, `slug`, `description`, `is_system`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Super Admin', 'super-admin', 'Full system access with all privileges', 1, 'active', '2026-09-02 15:35:01', '2026-09-02 15:35:01'),
(2, 'Admin', 'admin', 'Administrative access with management capabilities', 1, 'active', '2026-09-02 15:35:01', '2026-09-02 15:35:01'),
(3, 'Invigilator', 'invigilator', 'Competition monitoring and supervision access', 1, 'active', '2026-09-02 15:35:01', '2026-09-02 15:35:01'),
(4, 'Candidate', 'candidate', 'Typing test participant access', 1, 'active', '2026-09-02 15:35:01', '2026-09-02 15:35:01'),
(5, 'Teacher', 'teacher', 'Assessment designer and question bank manager', 1, 'active', '2026-09-02 18:34:58', '2026-09-02 18:34:58');

-- --------------------------------------------------------

--
-- Table structure for table `role_permissions`
--

CREATE TABLE `role_permissions` (
  `id` int(10) UNSIGNED NOT NULL,
  `role_id` int(10) UNSIGNED NOT NULL,
  `permission_id` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `role_permissions`
--

INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `created_at`) VALUES
(285, 1, 102, '2026-09-02 15:35:02'),
(286, 1, 103, '2026-09-02 15:35:02'),
(287, 1, 106, '2026-09-02 15:35:02'),
(288, 1, 84, '2026-09-02 15:35:02'),
(289, 1, 85, '2026-09-02 15:35:02'),
(290, 1, 86, '2026-09-02 15:35:02'),
(291, 1, 87, '2026-09-02 15:35:02'),
(292, 1, 88, '2026-09-02 15:35:02'),
(293, 1, 89, '2026-09-02 15:35:02'),
(294, 1, 79, '2026-09-02 15:35:02'),
(295, 1, 80, '2026-09-02 15:35:02'),
(296, 1, 81, '2026-09-02 15:35:02'),
(297, 1, 82, '2026-09-02 15:35:02'),
(298, 1, 83, '2026-09-02 15:35:02'),
(299, 1, 73, '2026-09-02 15:35:02'),
(300, 1, 90, '2026-09-02 15:35:02'),
(301, 1, 91, '2026-09-02 15:35:02'),
(302, 1, 92, '2026-09-02 15:35:02'),
(303, 1, 93, '2026-09-02 15:35:02'),
(304, 1, 107, '2026-09-02 15:35:02'),
(305, 1, 108, '2026-09-02 15:35:02'),
(306, 1, 99, '2026-09-02 15:35:02'),
(307, 1, 100, '2026-09-02 15:35:02'),
(308, 1, 101, '2026-09-02 15:35:02'),
(309, 1, 104, '2026-09-02 15:35:02'),
(310, 1, 105, '2026-09-02 15:35:02'),
(311, 1, 97, '2026-09-02 15:35:02'),
(312, 1, 98, '2026-09-02 15:35:02'),
(313, 1, 94, '2026-09-02 15:35:02'),
(314, 1, 95, '2026-09-02 15:35:02'),
(315, 1, 96, '2026-09-02 15:35:02'),
(316, 1, 74, '2026-09-02 15:35:02'),
(317, 1, 75, '2026-09-02 15:35:02'),
(318, 1, 76, '2026-09-02 15:35:02'),
(319, 1, 77, '2026-09-02 15:35:02'),
(320, 1, 78, '2026-09-02 15:35:02'),
(348, 2, 103, '2026-09-02 15:35:02'),
(349, 2, 102, '2026-09-02 15:35:02'),
(350, 2, 88, '2026-09-02 15:35:02'),
(351, 2, 85, '2026-09-02 15:35:02'),
(352, 2, 87, '2026-09-02 15:35:02'),
(353, 2, 86, '2026-09-02 15:35:02'),
(354, 2, 89, '2026-09-02 15:35:02'),
(355, 2, 84, '2026-09-02 15:35:02'),
(356, 2, 80, '2026-09-02 15:35:02'),
(357, 2, 82, '2026-09-02 15:35:02'),
(358, 2, 81, '2026-09-02 15:35:02'),
(359, 2, 83, '2026-09-02 15:35:02'),
(360, 2, 79, '2026-09-02 15:35:02'),
(361, 2, 73, '2026-09-02 15:35:02'),
(362, 2, 91, '2026-09-02 15:35:02'),
(363, 2, 93, '2026-09-02 15:35:02'),
(364, 2, 92, '2026-09-02 15:35:02'),
(365, 2, 90, '2026-09-02 15:35:02'),
(366, 2, 108, '2026-09-02 15:35:02'),
(367, 2, 107, '2026-09-02 15:35:02'),
(368, 2, 100, '2026-09-02 15:35:02'),
(369, 2, 101, '2026-09-02 15:35:02'),
(370, 2, 99, '2026-09-02 15:35:02'),
(371, 2, 105, '2026-09-02 15:35:02'),
(372, 2, 104, '2026-09-02 15:35:02'),
(373, 2, 98, '2026-09-02 15:35:02'),
(374, 2, 97, '2026-09-02 15:35:02'),
(375, 2, 96, '2026-09-02 15:35:02'),
(376, 2, 95, '2026-09-02 15:35:02'),
(377, 2, 94, '2026-09-02 15:35:02'),
(378, 2, 75, '2026-09-02 15:35:02'),
(379, 2, 76, '2026-09-02 15:35:02'),
(380, 2, 74, '2026-09-02 15:35:02'),
(411, 3, 103, '2026-09-02 15:35:02'),
(412, 3, 102, '2026-09-02 15:35:02'),
(413, 3, 84, '2026-09-02 15:35:02'),
(414, 3, 79, '2026-09-02 15:35:02'),
(415, 3, 73, '2026-09-02 15:35:02'),
(416, 3, 99, '2026-09-02 15:35:02'),
(417, 3, 95, '2026-09-02 15:35:02'),
(418, 3, 94, '2026-09-02 15:35:02'),
(426, 4, 73, '2026-09-02 15:35:02'),
(427, 1, 123, '2026-09-02 18:34:58'),
(428, 1, 124, '2026-09-02 18:34:58'),
(429, 1, 117, '2026-09-02 18:34:58'),
(430, 1, 118, '2026-09-02 18:34:58'),
(431, 1, 119, '2026-09-02 18:34:58'),
(432, 1, 120, '2026-09-02 18:34:58'),
(433, 1, 121, '2026-09-02 18:34:58'),
(434, 1, 122, '2026-09-02 18:34:58'),
(435, 1, 109, '2026-09-02 18:34:58'),
(436, 1, 110, '2026-09-02 18:34:58'),
(437, 1, 113, '2026-09-02 18:34:58'),
(438, 1, 114, '2026-09-02 18:34:58'),
(439, 1, 115, '2026-09-02 18:34:58'),
(440, 1, 116, '2026-09-02 18:34:58'),
(441, 1, 111, '2026-09-02 18:34:58'),
(442, 1, 112, '2026-09-02 18:34:58'),
(443, 1, 125, '2026-09-02 18:34:58'),
(458, 2, 124, '2026-09-02 18:34:58'),
(459, 2, 123, '2026-09-02 18:34:58'),
(460, 2, 118, '2026-09-02 18:34:58'),
(461, 2, 120, '2026-09-02 18:34:58'),
(462, 2, 119, '2026-09-02 18:34:58'),
(463, 2, 122, '2026-09-02 18:34:58'),
(464, 2, 121, '2026-09-02 18:34:58'),
(465, 2, 117, '2026-09-02 18:34:58'),
(466, 2, 109, '2026-09-02 18:34:58'),
(467, 2, 114, '2026-09-02 18:34:58'),
(468, 2, 116, '2026-09-02 18:34:58'),
(469, 2, 115, '2026-09-02 18:34:58'),
(470, 2, 113, '2026-09-02 18:34:58'),
(471, 2, 112, '2026-09-02 18:34:58'),
(472, 2, 111, '2026-09-02 18:34:58'),
(473, 5, 124, '2026-09-02 18:34:58'),
(474, 5, 123, '2026-09-02 18:34:58'),
(475, 5, 118, '2026-09-02 18:34:58'),
(476, 5, 120, '2026-09-02 18:34:58'),
(477, 5, 119, '2026-09-02 18:34:58'),
(478, 5, 122, '2026-09-02 18:34:58'),
(479, 5, 121, '2026-09-02 18:34:58'),
(480, 5, 117, '2026-09-02 18:34:58'),
(481, 5, 109, '2026-09-02 18:34:58'),
(482, 5, 73, '2026-09-02 18:34:58'),
(483, 5, 114, '2026-09-02 18:34:58'),
(484, 5, 116, '2026-09-02 18:34:58'),
(485, 5, 115, '2026-09-02 18:34:58'),
(486, 5, 113, '2026-09-02 18:34:58'),
(487, 5, 111, '2026-09-02 18:34:58'),
(488, 5, 125, '2026-09-02 18:34:58'),
(489, 5, 102, '2026-09-08 21:11:37'),
(490, 5, 103, '2026-09-08 21:11:37'),
(491, 5, 107, '2026-09-08 21:11:37'),
(492, 5, 108, '2026-09-08 21:11:37'),
(493, 1, 127, '2026-09-11 10:57:28'),
(494, 2, 127, '2026-09-11 10:57:28'),
(495, 5, 127, '2026-09-11 10:57:28');

-- --------------------------------------------------------

--
-- Table structure for table `security_events`
--

CREATE TABLE `security_events` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `attempt_id` int(10) UNSIGNED DEFAULT NULL,
  `candidate_id` int(10) UNSIGNED DEFAULT NULL,
  `competition_id` int(10) UNSIGNED DEFAULT NULL,
  `event_type` varchar(50) NOT NULL COMMENT 'login_failed, account_locked, suspicious_activity, etc.',
  `description` text DEFAULT NULL,
  `event_details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `severity` enum('low','medium','high','critical') NOT NULL DEFAULT 'low',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `server_timestamp` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `security_events`
--

INSERT INTO `security_events` (`id`, `user_id`, `attempt_id`, `candidate_id`, `competition_id`, `event_type`, `description`, `event_details`, `ip_address`, `user_agent`, `metadata`, `severity`, `created_at`, `server_timestamp`) VALUES
(1, NULL, NULL, NULL, NULL, 'login_failed', 'Invalid username: asd', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', NULL, 'medium', '2026-09-02 15:28:50', '2026-09-03 07:48:49'),
(2, NULL, NULL, NULL, NULL, 'login_failed', 'Invalid username: admin', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', NULL, 'medium', '2026-09-02 15:29:02', '2026-09-03 07:48:49'),
(3, NULL, NULL, NULL, NULL, 'login_failed', 'Invalid username: admin', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', NULL, 'medium', '2026-09-02 15:29:19', '2026-09-03 07:48:49'),
(4, NULL, NULL, NULL, NULL, 'login_failed', 'Invalid username: admin', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', NULL, 'medium', '2026-09-02 15:29:30', '2026-09-03 07:48:49'),
(5, NULL, NULL, NULL, NULL, 'login_failed', 'Invalid username: admin', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', NULL, 'medium', '2026-09-02 15:33:31', '2026-09-03 07:48:49'),
(6, 1, 3, NULL, NULL, 'TAB_SWITCH', 'Assessment violation: TAB_SWITCH on attempt #3', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '{\"detail\":\"Candidate switched browser tab\",\"candidate_id\":1,\"attempt_id\":3}', 'high', '2026-09-02 18:56:32', '2026-09-03 07:48:49'),
(7, 1, 3, NULL, NULL, 'TAB_SWITCH', 'Assessment violation: TAB_SWITCH on attempt #3', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '{\"detail\":\"Candidate switched browser tab\",\"candidate_id\":1,\"attempt_id\":3}', 'high', '2026-09-02 18:57:03', '2026-09-03 07:48:49'),
(8, NULL, 5, NULL, NULL, 'WINDOW_BLUR', 'Candidate window lost focus', NULL, NULL, NULL, '{\"attempt_id\":5,\"candidate_id\":6}', 'medium', '2026-09-03 07:31:00', '2026-09-03 07:48:49'),
(9, NULL, 5, NULL, NULL, 'TAB_SWITCH', 'Candidate switched tab', NULL, NULL, NULL, '{\"attempt_id\":5,\"candidate_id\":6}', 'high', '2026-09-03 07:31:00', '2026-09-03 07:48:49'),
(10, NULL, 6, NULL, NULL, 'WINDOW_BLUR', 'Candidate window lost focus', NULL, NULL, NULL, '{\"attempt_id\":6,\"candidate_id\":6}', 'medium', '2026-09-03 07:31:15', '2026-09-03 07:48:49'),
(11, NULL, 6, NULL, NULL, 'TAB_SWITCH', 'Candidate switched tab', NULL, NULL, NULL, '{\"attempt_id\":6,\"candidate_id\":6}', 'high', '2026-09-03 07:31:15', '2026-09-03 07:48:49'),
(12, NULL, 7, NULL, NULL, 'WINDOW_BLUR', 'Candidate window lost focus', NULL, NULL, NULL, '{\"attempt_id\":7,\"candidate_id\":6}', 'medium', '2026-09-03 07:32:31', '2026-09-03 07:48:49'),
(13, NULL, 7, NULL, NULL, 'TAB_SWITCH', 'Candidate switched tab', NULL, NULL, NULL, '{\"attempt_id\":7,\"candidate_id\":6}', 'high', '2026-09-03 07:32:31', '2026-09-03 07:48:49'),
(14, NULL, 10, NULL, NULL, 'WINDOW_BLUR', 'Candidate window lost focus', NULL, NULL, NULL, '{\"attempt_id\":10,\"candidate_id\":6}', 'medium', '2026-09-03 07:33:10', '2026-09-03 07:48:49'),
(15, NULL, 10, NULL, NULL, 'TAB_SWITCH', 'Candidate switched tab', NULL, NULL, NULL, '{\"attempt_id\":10,\"candidate_id\":6}', 'high', '2026-09-03 07:33:10', '2026-09-03 07:48:49'),
(16, 1, 11, NULL, NULL, 'TAB_SWITCH', 'Assessment violation: TAB_SWITCH on attempt #11', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '{\"detail\":\"Candidate switched browser tab\",\"candidate_id\":31,\"attempt_id\":11}', 'high', '2026-09-03 07:41:19', '2026-09-03 07:48:49'),
(17, 1, 11, NULL, NULL, 'WINDOW_BLUR', 'Assessment violation: WINDOW_BLUR on attempt #11', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '{\"detail\":\"Window lost focus\",\"candidate_id\":31,\"attempt_id\":11}', 'medium', '2026-09-03 07:41:19', '2026-09-03 07:48:49'),
(18, 1, 11, NULL, NULL, 'TAB_SWITCH', 'Assessment violation: TAB_SWITCH on attempt #11', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '{\"detail\":\"Candidate switched browser tab\",\"candidate_id\":31,\"attempt_id\":11}', 'high', '2026-09-03 07:42:44', '2026-09-03 07:48:49'),
(19, NULL, NULL, NULL, NULL, 'WINDOW_BLUR', 'Candidate window lost focus', NULL, NULL, NULL, '{\"attempt_id\":15,\"candidate_id\":6}', 'medium', '2026-09-03 08:02:08', '2026-09-03 08:02:08'),
(20, NULL, NULL, NULL, NULL, 'TAB_SWITCH', 'Candidate switched tab', NULL, NULL, NULL, '{\"attempt_id\":15,\"candidate_id\":6}', 'high', '2026-09-03 08:02:08', '2026-09-03 08:02:08'),
(21, 1, NULL, NULL, NULL, 'TAB_SWITCH', 'Assessment violation: TAB_SWITCH on attempt #16', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '{\"detail\":\"Candidate switched browser tab\",\"candidate_id\":1,\"attempt_id\":16}', 'high', '2026-09-03 08:34:36', '2026-09-03 08:34:36'),
(22, 1, NULL, NULL, NULL, 'WINDOW_BLUR', 'Assessment violation: WINDOW_BLUR on attempt #17', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '{\"detail\":\"Window lost focus\",\"candidate_id\":2,\"attempt_id\":17}', 'medium', '2026-09-03 09:02:20', '2026-09-03 09:02:20'),
(23, 1, NULL, NULL, NULL, 'TAB_SWITCH', 'Assessment violation: TAB_SWITCH on attempt #17', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '{\"detail\":\"Candidate switched browser tab\",\"candidate_id\":2,\"attempt_id\":17}', 'high', '2026-09-03 09:02:20', '2026-09-03 09:02:20'),
(24, 1, NULL, NULL, NULL, 'WINDOW_BLUR', 'Assessment violation: WINDOW_BLUR on attempt #17', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '{\"detail\":\"Window lost focus\",\"candidate_id\":2,\"attempt_id\":17}', 'medium', '2026-09-03 09:02:33', '2026-09-03 09:02:33'),
(25, 1, NULL, NULL, NULL, 'TAB_SWITCH', 'Assessment violation: TAB_SWITCH on attempt #17', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '{\"detail\":\"Candidate switched browser tab\",\"candidate_id\":2,\"attempt_id\":17}', 'high', '2026-09-03 09:02:33', '2026-09-03 09:02:33'),
(26, 1, NULL, NULL, NULL, 'WINDOW_BLUR', 'Assessment violation: WINDOW_BLUR on attempt #17', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '{\"detail\":\"Window lost focus\",\"candidate_id\":2,\"attempt_id\":17}', 'medium', '2026-09-03 09:02:55', '2026-09-03 09:02:55'),
(27, 1, NULL, NULL, NULL, 'TAB_SWITCH', 'Assessment violation: TAB_SWITCH on attempt #17', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '{\"detail\":\"Candidate switched browser tab\",\"candidate_id\":2,\"attempt_id\":17}', 'high', '2026-09-03 09:02:55', '2026-09-03 09:02:55'),
(28, 1, NULL, NULL, NULL, 'TAB_SWITCH', 'Assessment violation: TAB_SWITCH on attempt #17', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '{\"detail\":\"Candidate switched browser tab\",\"candidate_id\":2,\"attempt_id\":17}', 'high', '2026-09-03 09:03:52', '2026-09-03 09:03:52'),
(29, 1, NULL, NULL, NULL, 'FULLSCREEN_EXIT', 'Assessment violation: FULLSCREEN_EXIT on attempt #18', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '{\"detail\":\"Exited fullscreen mode\",\"candidate_id\":4,\"attempt_id\":18}', 'high', '2026-09-03 09:04:17', '2026-09-03 09:04:17'),
(30, 1, NULL, NULL, NULL, 'TAB_SWITCH', 'Assessment violation: TAB_SWITCH on attempt #18', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '{\"detail\":\"Candidate switched browser tab\",\"candidate_id\":4,\"attempt_id\":18}', 'high', '2026-09-03 09:04:49', '2026-09-03 09:04:49'),
(31, 1, NULL, NULL, NULL, 'TAB_SWITCH', 'Assessment violation: TAB_SWITCH (away for 14s) on attempt #29', NULL, '127.0.0.1', 'Mozilla/5.0 Test Agent', '{\"attempt_id\":29,\"duration_seconds\":14,\"threshold\":5,\"timestamp\":\"2026-09-08T23:56:21+05:00\"}', 'high', '2026-09-08 18:56:21', '2026-09-08 18:56:21'),
(32, 1, NULL, NULL, NULL, 'TAB_SWITCH', 'Assessment violation: TAB_SWITCH (away for 14s) on attempt #30', NULL, '127.0.0.1', 'Mozilla/5.0 Test Agent', '{\"attempt_id\":30,\"duration_seconds\":14,\"threshold\":5,\"timestamp\":\"2026-09-08T23:56:48+05:00\"}', 'high', '2026-09-08 18:56:48', '2026-09-08 18:56:48'),
(33, 1, 31, NULL, NULL, 'TAB_SWITCH', 'Assessment violation: TAB_SWITCH (away for 14s) on attempt #31', NULL, '127.0.0.1', 'Mozilla/5.0 Test Agent', '{\"attempt_id\":31,\"duration_seconds\":14,\"threshold\":5,\"timestamp\":\"2026-09-08T23:57:58+05:00\"}', 'high', '2026-09-08 18:57:58', '2026-09-08 18:57:58'),
(34, 1, 33, NULL, NULL, 'TAB_SWITCH', 'Assessment violation: TAB_SWITCH (away for 14s) on attempt #33', NULL, '127.0.0.1', 'Mozilla/5.0 Test Agent', '{\"attempt_id\":33,\"duration_seconds\":14,\"threshold\":5,\"timestamp\":\"2026-09-09T00:25:00+05:00\"}', 'high', '2026-09-08 19:25:00', '2026-09-08 19:25:00'),
(35, 1, 35, NULL, NULL, 'TAB_SWITCH', 'Assessment violation: TAB_SWITCH (away for 14s) on attempt #35', NULL, '127.0.0.1', 'Mozilla/5.0 Test Agent', '{\"attempt_id\":35,\"duration_seconds\":14,\"threshold\":5,\"timestamp\":\"2026-09-09T00:37:41+05:00\"}', 'high', '2026-09-08 19:37:41', '2026-09-08 19:37:41'),
(36, NULL, 38, NULL, NULL, 'WINDOW_BLUR', NULL, NULL, NULL, NULL, '{\"attempt_id\":38,\"detail\":\"User clicked outside window\"}', '', '2026-09-08 20:45:42', '2026-09-08 20:45:42'),
(37, NULL, 38, NULL, NULL, 'TAB_SWITCH', NULL, NULL, NULL, NULL, '{\"attempt_id\":38,\"detail\":\"Tab switched to another browser tab\"}', 'critical', '2026-09-08 20:45:42', '2026-09-08 20:45:42'),
(38, 1, 49, NULL, NULL, 'TAB_SWITCH', 'Violations have been detected: Student navigated away from assessment window for 5s (threshold: 5s)', NULL, '192.168.20.184', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '{\"duration_seconds\":5,\"threshold\":5,\"timestamp\":\"2026-09-11T11:07:40.225Z\",\"candidate_id\":null,\"attempt_id\":49}', 'high', '2026-09-11 11:07:35', '2026-09-11 11:07:35'),
(39, 1, 49, NULL, NULL, 'FULLSCREEN_EXIT', 'Violations have been detected: Student navigated away from assessment window for 0s (threshold: 5s)', NULL, '192.168.20.184', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '{\"duration_seconds\":0,\"threshold\":5,\"timestamp\":\"2026-09-11T11:08:13.929Z\",\"candidate_id\":null,\"attempt_id\":49}', 'high', '2026-09-11 11:08:09', '2026-09-11 11:08:09'),
(40, 1, 49, NULL, NULL, 'FULLSCREEN_EXIT', 'Violations have been detected: Student navigated away from assessment window for 0s (threshold: 5s)', NULL, '192.168.20.184', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '{\"duration_seconds\":0,\"threshold\":5,\"timestamp\":\"2026-09-11T11:13:32.697Z\",\"candidate_id\":null,\"attempt_id\":49}', 'high', '2026-09-11 11:13:28', '2026-09-11 11:13:28'),
(41, 1, 49, NULL, NULL, 'FULLSCREEN_EXIT', 'Violations have been detected: Student navigated away from assessment window for 0s (threshold: 5s)', NULL, '192.168.20.184', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '{\"duration_seconds\":0,\"threshold\":5,\"timestamp\":\"2026-09-11T11:14:49.201Z\",\"candidate_id\":null,\"attempt_id\":49}', 'high', '2026-09-11 11:14:44', '2026-09-11 11:14:44'),
(42, 1, 49, NULL, NULL, 'TAB_SWITCH', 'Violations have been detected: Student navigated away from assessment window for 6s (threshold: 5s)', NULL, '192.168.20.184', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '{\"duration_seconds\":6,\"threshold\":5,\"timestamp\":\"2026-09-11T11:14:57.228Z\",\"candidate_id\":null,\"attempt_id\":49}', 'high', '2026-09-11 11:14:52', '2026-09-11 11:14:52'),
(43, 1, 49, NULL, NULL, 'FULLSCREEN_EXIT', 'Violations have been detected: Student navigated away from assessment window for 0s (threshold: 5s)', NULL, '192.168.20.184', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '{\"duration_seconds\":0,\"threshold\":5,\"timestamp\":\"2026-09-11T11:15:53.327Z\",\"candidate_id\":null,\"attempt_id\":49}', 'high', '2026-09-11 11:15:48', '2026-09-11 11:15:48');

-- --------------------------------------------------------

--
-- Table structure for table `subjects`
--

CREATE TABLE `subjects` (
  `id` int(10) UNSIGNED NOT NULL,
  `campus_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `code` varchar(30) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `subjects`
--

INSERT INTO `subjects` (`id`, `campus_id`, `name`, `code`, `created_at`) VALUES
(1, 1, 'Computer Science', 'CS-101', '2026-09-03 09:21:55'),
(2, 1, 'Information Technology', 'IT-102', '2026-09-03 09:21:55'),
(3, 1, 'English Language', 'ENG-103', '2026-09-03 09:21:55'),
(4, 1, 'Arabic Language', 'ARAB-101', '2026-09-08 19:58:49'),
(5, 1, 'Turkish Language', 'TURK-101', '2026-09-08 19:58:49'),
(6, 1, 'Mathematics', 'MATH-101', '2026-09-08 19:58:49'),
(7, 1, 'General Science', 'SCI-101', '2026-09-08 19:58:49'),
(8, 1, 'Physics', 'PHYS-101', '2026-09-08 19:58:49'),
(9, 1, 'Chemistry', 'CHEM-101', '2026-09-08 19:58:49'),
(10, 1, 'Biology', 'BIO-101', '2026-09-08 19:58:49'),
(11, 1, 'Social Studies', 'SS-101', '2026-09-08 19:58:49'),
(12, 1, 'Islamic Studies', 'ISL-101', '2026-09-08 19:58:49'),
(13, 1, 'Pakistan Studies', 'PST-101', '2026-09-08 19:58:49'),
(14, 1, 'History & Geography', 'HIST-101', '2026-09-08 19:58:49'),
(15, 1, 'Urdu Language', 'URDU-101', '2026-09-08 19:58:49'),
(16, 1, 'Arts & Drawing', 'ART-101', '2026-09-08 19:58:49'),
(17, 1, 'Physical Education', 'PE-101', '2026-09-08 19:58:49'),
(18, 1, 'Economics & Commerce', 'ECON-101', '2026-09-08 19:58:49');

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `id` int(10) UNSIGNED NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `setting_type` enum('text','number','boolean','json','file') NOT NULL DEFAULT 'text',
  `setting_group` varchar(50) NOT NULL DEFAULT 'general',
  `description` varchar(255) DEFAULT NULL,
  `is_public` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Visible without login',
  `updated_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`id`, `setting_key`, `setting_value`, `setting_type`, `setting_group`, `description`, `is_public`, `updated_by`, `created_at`, `updated_at`) VALUES
(1, 'institute_name', 'Maarif', 'text', 'general', 'Institute name', 1, 1, '2026-09-02 15:35:02', '2026-09-11 12:34:01'),
(2, 'system_name', 'vibe.Sınav', 'text', 'general', 'System display name', 1, 1, '2026-09-02 15:35:02', '2026-09-11 12:15:44'),
(3, 'timezone', 'Asia/Karachi', 'text', 'general', 'System timezone', 0, 1, '2026-09-02 15:35:02', '2026-09-08 17:25:01'),
(4, 'institute_logo', 'logo_1789134286.png', 'file', 'general', 'Institute logo file path', 1, 1, '2026-09-02 15:35:02', '2026-09-11 13:44:46'),
(5, 'institute_address', '', 'text', 'general', 'Institute address', 1, 1, '2026-09-02 15:35:02', '2026-09-11 10:57:11'),
(6, 'institute_phone', '', 'text', 'general', 'Institute phone number', 1, 1, '2026-09-02 15:35:02', '2026-09-08 17:25:01'),
(7, 'institute_email', '', 'text', 'general', 'Institute email address', 1, 1, '2026-09-02 15:35:02', '2026-09-08 17:25:01'),
(8, 'session_timeout', '30', 'number', 'security', 'Session timeout in minutes', 0, NULL, '2026-09-02 15:35:02', '2026-09-02 15:35:02'),
(9, 'max_login_attempts', '5', 'number', 'security', 'Maximum login attempts before lockout', 0, NULL, '2026-09-02 15:35:02', '2026-09-02 15:35:02'),
(10, 'lockout_duration', '15', 'number', 'security', 'Account lockout duration in minutes', 0, NULL, '2026-09-02 15:35:02', '2026-09-02 15:35:02'),
(13, 'proctoring_anomaly_timer', '5', 'number', 'proctoring', 'Proctoring Anomaly Violation Threshold in seconds', 1, 1, '2026-09-08 18:28:48', '2026-09-11 11:05:48'),
(14, 'proctoring_detect_blur', '1', 'boolean', 'proctoring', 'Detect window blur and tab switches', 1, 1, '2026-09-08 18:28:48', '2026-09-11 11:05:48'),
(15, 'proctoring_strict_fullscreen', '1', 'boolean', 'proctoring', 'Require fullscreen mode during examination', 1, 1, '2026-09-08 18:28:48', '2026-09-11 11:05:48');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `campus_id` int(10) UNSIGNED DEFAULT NULL,
  `role` varchar(50) NOT NULL DEFAULT 'teacher',
  `username` varchar(50) NOT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `campus_id`, `role`, `username`, `full_name`, `password_hash`, `status`, `created_at`, `updated_at`) VALUES
(1, 1, 'super-admin', 'admin', 'System Administrator', '$2y$10$V2GMmqxkhRQLnssji62e.ulxOWSZyZODgAdtolzi3RI6RXaoeo44S', 'active', '2026-09-03 09:21:55', '2026-09-11 11:09:29'),
(2, 1, 'teacher', 'teacher', 'Instructor / Teacher', '$2y$10$XNwW4MihwHIiSGfk68GX1u4RRmCqwvXn9j7hEvU0xPAbhmgx6iBhe', 'active', '2026-09-03 09:21:55', '2026-09-09 07:44:00');

-- --------------------------------------------------------

--
-- Table structure for table `user_permissions`
--

CREATE TABLE `user_permissions` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `permission_slug` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_roles`
--

CREATE TABLE `user_roles` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `role_id` int(10) UNSIGNED NOT NULL,
  `assigned_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_roles`
--

INSERT INTO `user_roles` (`id`, `user_id`, `role_id`, `assigned_by`, `created_at`) VALUES
(3, 1, 1, NULL, '2026-09-02 15:35:02'),
(4, 2, 5, NULL, '2026-09-02 18:34:58');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `assessments`
--
ALTER TABLE `assessments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_assessments_campus` (`campus_id`),
  ADD KEY `idx_assessments_subject` (`subject_id`),
  ADD KEY `idx_assessments_created_by` (`created_by`),
  ADD KEY `idx_assessments_status` (`status`),
  ADD KEY `idx_assessments_published` (`is_result_published`);

--
-- Indexes for table `assessment_answers`
--
ALTER TABLE `assessment_answers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_attempt_question_answer` (`attempt_id`,`question_id`),
  ADD KEY `idx_answers_attempt` (`attempt_id`),
  ADD KEY `idx_answers_question` (`question_id`);

--
-- Indexes for table `assessment_attempts`
--
ALTER TABLE `assessment_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_attempts_assessment` (`assessment_id`),
  ADD KEY `idx_attempts_station` (`station_id`),
  ADD KEY `idx_attempts_status` (`status`),
  ADD KEY `idx_attempts_roll_no` (`student_roll_no`);

--
-- Indexes for table `assessment_questions`
--
ALTER TABLE `assessment_questions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_aq_assessment_question` (`assessment_id`,`question_id`),
  ADD KEY `idx_aq_order` (`assessment_id`,`question_order`),
  ADD KEY `fk_aq_question` (`question_id`);

--
-- Indexes for table `assessment_results`
--
ALTER TABLE `assessment_results`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_results_attempt` (`attempt_id`),
  ADD KEY `idx_results_pass_fail` (`pass_fail`);

--
-- Indexes for table `assessment_stations`
--
ALTER TABLE `assessment_stations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_as_assessment_station` (`assessment_id`,`station_id`),
  ADD KEY `idx_as_status` (`status`),
  ADD KEY `fk_as_station` (`station_id`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_audit_user` (`user_id`),
  ADD KEY `idx_audit_action` (`action`),
  ADD KEY `idx_audit_module` (`module`),
  ADD KEY `idx_audit_created` (`created_at`);

--
-- Indexes for table `campuses`
--
ALTER TABLE `campuses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_campuses_code` (`code`),
  ADD KEY `idx_campuses_status` (`status`);

--
-- Indexes for table `lab_stations`
--
ALTER TABLE `lab_stations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_stations_campus_code` (`campus_id`,`station_code`),
  ADD KEY `idx_stations_ip` (`ip_address`),
  ADD KEY `idx_stations_status` (`status`);

--
-- Indexes for table `permissions`
--
ALTER TABLE `permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_permissions_slug` (`slug`),
  ADD KEY `idx_permissions_module` (`module`);

--
-- Indexes for table `questions`
--
ALTER TABLE `questions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_questions_subject` (`subject_id`),
  ADD KEY `idx_questions_created_by` (`created_by`),
  ADD KEY `idx_questions_type` (`question_type`);

--
-- Indexes for table `question_options`
--
ALTER TABLE `question_options`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_options_question` (`question_id`),
  ADD KEY `idx_options_correct` (`is_correct`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_roles_slug` (`slug`),
  ADD KEY `idx_roles_status` (`status`);

--
-- Indexes for table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_role_permission` (`role_id`,`permission_id`),
  ADD KEY `fk_rp_permission` (`permission_id`);

--
-- Indexes for table `security_events`
--
ALTER TABLE `security_events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_security_user` (`user_id`),
  ADD KEY `idx_security_type` (`event_type`),
  ADD KEY `idx_security_severity` (`severity`),
  ADD KEY `idx_security_created` (`created_at`),
  ADD KEY `idx_security_attempt` (`attempt_id`),
  ADD KEY `idx_security_candidate` (`candidate_id`),
  ADD KEY `idx_security_competition` (`competition_id`);

--
-- Indexes for table `subjects`
--
ALTER TABLE `subjects`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_subjects_campus_code` (`campus_id`,`code`),
  ADD KEY `idx_subjects_campus` (`campus_id`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_settings_key` (`setting_key`),
  ADD KEY `idx_settings_group` (`setting_group`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_users_username` (`username`),
  ADD KEY `idx_users_role` (`role`),
  ADD KEY `idx_users_campus` (`campus_id`);

--
-- Indexes for table `user_permissions`
--
ALTER TABLE `user_permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_user_permission` (`user_id`,`permission_slug`),
  ADD KEY `idx_user_permissions_user` (`user_id`);

--
-- Indexes for table `user_roles`
--
ALTER TABLE `user_roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_user_role` (`user_id`,`role_id`),
  ADD KEY `fk_ur_role` (`role_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `assessments`
--
ALTER TABLE `assessments`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=52;

--
-- AUTO_INCREMENT for table `assessment_answers`
--
ALTER TABLE `assessment_answers`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=224;

--
-- AUTO_INCREMENT for table `assessment_attempts`
--
ALTER TABLE `assessment_attempts`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=53;

--
-- AUTO_INCREMENT for table `assessment_questions`
--
ALTER TABLE `assessment_questions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=94;

--
-- AUTO_INCREMENT for table `assessment_results`
--
ALTER TABLE `assessment_results`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=57;

--
-- AUTO_INCREMENT for table `assessment_stations`
--
ALTER TABLE `assessment_stations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=404;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=457;

--
-- AUTO_INCREMENT for table `campuses`
--
ALTER TABLE `campuses`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `lab_stations`
--
ALTER TABLE `lab_stations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=83;

--
-- AUTO_INCREMENT for table `permissions`
--
ALTER TABLE `permissions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=128;

--
-- AUTO_INCREMENT for table `questions`
--
ALTER TABLE `questions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=70;

--
-- AUTO_INCREMENT for table `question_options`
--
ALTER TABLE `question_options`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=178;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `role_permissions`
--
ALTER TABLE `role_permissions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=496;

--
-- AUTO_INCREMENT for table `security_events`
--
ALTER TABLE `security_events`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=45;

--
-- AUTO_INCREMENT for table `subjects`
--
ALTER TABLE `subjects`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `user_permissions`
--
ALTER TABLE `user_permissions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=71;

--
-- AUTO_INCREMENT for table `user_roles`
--
ALTER TABLE `user_roles`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `assessments`
--
ALTER TABLE `assessments`
  ADD CONSTRAINT `fk_assessments_campus` FOREIGN KEY (`campus_id`) REFERENCES `campuses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_assessments_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_assessments_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`);

--
-- Constraints for table `assessment_answers`
--
ALTER TABLE `assessment_answers`
  ADD CONSTRAINT `fk_answers_attempt` FOREIGN KEY (`attempt_id`) REFERENCES `assessment_attempts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_answers_question` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`);

--
-- Constraints for table `assessment_attempts`
--
ALTER TABLE `assessment_attempts`
  ADD CONSTRAINT `fk_attempts_assessment` FOREIGN KEY (`assessment_id`) REFERENCES `assessments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_attempts_station` FOREIGN KEY (`station_id`) REFERENCES `lab_stations` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `assessment_questions`
--
ALTER TABLE `assessment_questions`
  ADD CONSTRAINT `fk_aq_assessment` FOREIGN KEY (`assessment_id`) REFERENCES `assessments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_aq_question` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `assessment_results`
--
ALTER TABLE `assessment_results`
  ADD CONSTRAINT `fk_results_attempt` FOREIGN KEY (`attempt_id`) REFERENCES `assessment_attempts` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `assessment_stations`
--
ALTER TABLE `assessment_stations`
  ADD CONSTRAINT `fk_as_assessment` FOREIGN KEY (`assessment_id`) REFERENCES `assessments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_as_station` FOREIGN KEY (`station_id`) REFERENCES `lab_stations` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `lab_stations`
--
ALTER TABLE `lab_stations`
  ADD CONSTRAINT `fk_stations_campus` FOREIGN KEY (`campus_id`) REFERENCES `campuses` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `questions`
--
ALTER TABLE `questions`
  ADD CONSTRAINT `fk_questions_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_questions_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `question_options`
--
ALTER TABLE `question_options`
  ADD CONSTRAINT `fk_options_question` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD CONSTRAINT `fk_rp_permission` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_rp_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `security_events`
--
ALTER TABLE `security_events`
  ADD CONSTRAINT `fk_security_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `subjects`
--
ALTER TABLE `subjects`
  ADD CONSTRAINT `fk_subjects_campus` FOREIGN KEY (`campus_id`) REFERENCES `campuses` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_campus` FOREIGN KEY (`campus_id`) REFERENCES `campuses` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `user_permissions`
--
ALTER TABLE `user_permissions`
  ADD CONSTRAINT `fk_up_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_roles`
--
ALTER TABLE `user_roles`
  ADD CONSTRAINT `fk_ur_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ur_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
