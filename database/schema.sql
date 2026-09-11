-- ============================================================
-- PTM Assessment System — Core Database Schema
-- Device-allocated, server-authoritative offline LAN model
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

-- Drop legacy & existing tables
DROP TABLE IF EXISTS `assessment_candidates`;
DROP TABLE IF EXISTS `assessment_sections`;
DROP TABLE IF EXISTS `question_categories`;
DROP TABLE IF EXISTS `assessment_results`;
DROP TABLE IF EXISTS `assessment_answers`;
DROP TABLE IF EXISTS `assessment_attempts`;
DROP TABLE IF EXISTS `assessment_stations`;
DROP TABLE IF EXISTS `assessment_questions`;
DROP TABLE IF EXISTS `assessments`;
DROP TABLE IF EXISTS `lab_stations`;
DROP TABLE IF EXISTS `question_options`;
DROP TABLE IF EXISTS `questions`;
DROP TABLE IF EXISTS `subjects`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `campuses`;

-- Drop legacy typing & candidate tables if they exist
DROP TABLE IF EXISTS `test_progress`;
DROP TABLE IF EXISTS `test_results`;
DROP TABLE IF EXISTS `test_attempts`;
DROP TABLE IF EXISTS `competition_paragraphs`;
DROP TABLE IF EXISTS `typing_paragraphs`;
DROP TABLE IF EXISTS `test_settings`;
DROP TABLE IF EXISTS `competitions`;
DROP TABLE IF EXISTS `candidates`;
DROP TABLE IF EXISTS `attendance`;
DROP TABLE IF EXISTS `certificates`;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- 1. CAMPUSES
-- ============================================================
CREATE TABLE `campuses` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `code` VARCHAR(20) NOT NULL,
    `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_campuses_code` (`code`),
    INDEX `idx_campuses_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 2. USERS
-- ============================================================
CREATE TABLE `users` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `campus_id` INT UNSIGNED DEFAULT NULL,
    `role` VARCHAR(50) NOT NULL DEFAULT 'teacher',
    `username` VARCHAR(50) NOT NULL,
    `full_name` VARCHAR(100) NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_users_username` (`username`),
    INDEX `idx_users_role` (`role`),
    INDEX `idx_users_status` (`status`),
    INDEX `idx_users_campus` (`campus_id`),
    CONSTRAINT `fk_users_campus` FOREIGN KEY (`campus_id`) REFERENCES `campuses` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_permissions` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `permission_slug` VARCHAR(100) NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_user_permission` (`user_id`, `permission_slug`),
    INDEX `idx_user_permissions_user` (`user_id`),
    CONSTRAINT `fk_up_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 3. SUBJECTS
-- ============================================================
CREATE TABLE `subjects` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `campus_id` INT UNSIGNED NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `code` VARCHAR(30) NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_subjects_campus` (`campus_id`),
    UNIQUE KEY `uk_subjects_campus_code` (`campus_id`, `code`),
    CONSTRAINT `fk_subjects_campus` FOREIGN KEY (`campus_id`) REFERENCES `campuses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 4. QUESTIONS
-- ============================================================
CREATE TABLE `questions` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `subject_id` INT UNSIGNED NOT NULL,
    `created_by` INT UNSIGNED NOT NULL,
    `question_text` TEXT NOT NULL,
    `question_type` ENUM('single_choice', 'multiple_choice', 'true_false', 'fill_blank', 'short_answer') NOT NULL,
    `difficulty` ENUM('easy', 'medium', 'hard') NOT NULL DEFAULT 'medium',
    `marks` DECIMAL(4,2) NOT NULL DEFAULT 1.00,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_questions_subject` (`subject_id`),
    INDEX `idx_questions_created_by` (`created_by`),
    INDEX `idx_questions_type` (`question_type`),
    INDEX `idx_questions_difficulty` (`difficulty`),
    CONSTRAINT `fk_questions_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_questions_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 5. QUESTION_OPTIONS
-- ============================================================
CREATE TABLE `question_options` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `question_id` INT UNSIGNED NOT NULL,
    `option_text` TEXT NOT NULL,
    `is_correct` TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    INDEX `idx_options_question` (`question_id`),
    INDEX `idx_options_correct` (`is_correct`),
    CONSTRAINT `fk_options_question` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 6. LAB_STATIONS
-- ============================================================
CREATE TABLE `lab_stations` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `campus_id` INT UNSIGNED NOT NULL,
    `station_code` VARCHAR(50) NOT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `status` ENUM('active', 'disabled') NOT NULL DEFAULT 'active',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_stations_campus_code` (`campus_id`, `station_code`),
    INDEX `idx_stations_ip` (`ip_address`),
    INDEX `idx_stations_status` (`status`),
    CONSTRAINT `fk_stations_campus` FOREIGN KEY (`campus_id`) REFERENCES `campuses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 7. ASSESSMENTS
-- ============================================================
CREATE TABLE `assessments` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `campus_id` INT UNSIGNED NOT NULL,
    `subject_id` INT UNSIGNED NOT NULL,
    `created_by` INT UNSIGNED NOT NULL,
    `title` VARCHAR(200) NOT NULL,
    `assessment_type` VARCHAR(50) NOT NULL DEFAULT 'quiz',
    `target_class` VARCHAR(50) NOT NULL,
    `section` VARCHAR(50) NULL DEFAULT 'Section A',
    `description` TEXT NULL,
    `duration_minutes` INT UNSIGNED NOT NULL DEFAULT 30,
    `start_date` DATE NULL,
    `start_time` TIME NULL,
    `end_date` DATE NULL,
    `end_time` TIME NULL,
    `total_marks` DECIMAL(6,2) NOT NULL DEFAULT 0.00,
    `passing_marks` DECIMAL(6,2) NULL,
    `shuffle_questions` TINYINT(1) NOT NULL DEFAULT 1,
    `shuffle_options` TINYINT(1) NOT NULL DEFAULT 1,
    `instructions` TEXT NULL,
    `rules_guidelines` TEXT NULL,
    `passing_percentage` DECIMAL(5,2) NOT NULL DEFAULT 40.00,
    `device_limit` INT UNSIGNED NOT NULL DEFAULT 0,
    `is_result_published` TINYINT(1) NOT NULL DEFAULT 0,
    `status` ENUM('draft', 'published', 'archived') NOT NULL DEFAULT 'draft',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_assessments_campus` (`campus_id`),
    INDEX `idx_assessments_subject` (`subject_id`),
    INDEX `idx_assessments_created_by` (`created_by`),
    INDEX `idx_assessments_status` (`status`),
    INDEX `idx_assessments_published` (`is_result_published`),
    CONSTRAINT `fk_assessments_campus` FOREIGN KEY (`campus_id`) REFERENCES `campuses` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_assessments_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_assessments_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 8. ASSESSMENT_QUESTIONS
-- ============================================================
CREATE TABLE `assessment_questions` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `assessment_id` INT UNSIGNED NOT NULL,
    `question_id` INT UNSIGNED NOT NULL,
    `question_order` INT UNSIGNED NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_aq_assessment_question` (`assessment_id`, `question_id`),
    INDEX `idx_aq_order` (`assessment_id`, `question_order`),
    CONSTRAINT `fk_aq_assessment` FOREIGN KEY (`assessment_id`) REFERENCES `assessments` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_aq_question` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 9. ASSESSMENT_STATIONS
-- ============================================================
CREATE TABLE `assessment_stations` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `assessment_id` INT UNSIGNED NOT NULL,
    `station_id` INT UNSIGNED NOT NULL,
    `status` ENUM('idle', 'in_progress', 'submitted') NOT NULL DEFAULT 'idle',
    `assigned_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_as_assessment_station` (`assessment_id`, `station_id`),
    INDEX `idx_as_status` (`status`),
    CONSTRAINT `fk_as_assessment` FOREIGN KEY (`assessment_id`) REFERENCES `assessments` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_as_station` FOREIGN KEY (`station_id`) REFERENCES `lab_stations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 10. ASSESSMENT_ATTEMPTS
-- ============================================================
CREATE TABLE `assessment_attempts` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `assessment_id` INT UNSIGNED NOT NULL,
    `station_id` INT UNSIGNED NULL DEFAULT NULL,
    `student_name` VARCHAR(100) NOT NULL,
    `student_roll_no` VARCHAR(50) NOT NULL,
    `student_class` VARCHAR(50) NOT NULL,
    `started_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `expected_end_at` TIMESTAMP NOT NULL,
    `submitted_at` TIMESTAMP NULL DEFAULT NULL,
    `status` ENUM('in_progress', 'completed', 'timed_out', 'disqualified', 'abandoned') NOT NULL DEFAULT 'in_progress',
    `needs_review` TINYINT(1) NOT NULL DEFAULT 0,
    `is_reviewed` TINYINT(1) NOT NULL DEFAULT 0,
    `reviewed_by` INT UNSIGNED NULL,
    `reviewed_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_attempts_assessment` (`assessment_id`),
    INDEX `idx_attempts_station` (`station_id`),
    INDEX `idx_attempts_status` (`status`),
    INDEX `idx_attempts_roll_no` (`student_roll_no`),
    CONSTRAINT `fk_attempts_assessment` FOREIGN KEY (`assessment_id`) REFERENCES `assessments` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_attempts_station` FOREIGN KEY (`station_id`) REFERENCES `lab_stations` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 11. ASSESSMENT_ANSWERS
-- ============================================================
CREATE TABLE `assessment_answers` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `attempt_id` INT UNSIGNED NOT NULL,
    `question_id` INT UNSIGNED NOT NULL,
    `selected_option_ids` JSON DEFAULT NULL COMMENT 'Array of option IDs for single/multiple choice/true_false',
    `text_answer` TEXT DEFAULT NULL COMMENT 'Normalized text response for fill_blank',
    `is_correct` TINYINT(1) DEFAULT NULL,
    `needs_review` TINYINT(1) NOT NULL DEFAULT 0,
    `marks_obtained` DECIMAL(4,2) NOT NULL DEFAULT 0.00,
    `teacher_comment` TEXT DEFAULT NULL,
    `evaluated_by` INT UNSIGNED NULL,
    `evaluated_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_attempt_question_answer` (`attempt_id`, `question_id`),
    INDEX `idx_answers_attempt` (`attempt_id`),
    INDEX `idx_answers_question` (`question_id`),
    CONSTRAINT `fk_answers_attempt` FOREIGN KEY (`attempt_id`) REFERENCES `assessment_attempts` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_answers_question` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 12. ASSESSMENT_RESULTS
-- ============================================================
CREATE TABLE `assessment_results` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `attempt_id` INT UNSIGNED NOT NULL,
    `total_marks` DECIMAL(6,2) NOT NULL,
    `obtained_marks` DECIMAL(6,2) NOT NULL,
    `percentage` DECIMAL(5,2) NOT NULL,
    `pass_fail` ENUM('pass', 'fail') NOT NULL,
    `generated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_results_attempt` (`attempt_id`),
    INDEX `idx_results_pass_fail` (`pass_fail`),
    CONSTRAINT `fk_results_attempt` FOREIGN KEY (`attempt_id`) REFERENCES `assessment_attempts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- INITIAL SEED DATA
-- ============================================================

-- 1. Default Campus
INSERT INTO `campuses` (`id`, `name`, `code`, `status`) VALUES
(1, 'Main Campus', 'MAIN', 'active');

-- 2. Default Administrative Users (Pass: Admin@123, Teacher@123)
INSERT INTO `users` (`id`, `campus_id`, `role`, `username`, `password_hash`) VALUES
(1, 1, 'super-admin', 'admin', '$2y$10$PAV9v/3TUpfMnMGzFPZDOebu.IRkoloXTvklICX5bnb2aRoiNfO92'),
(2, 1, 'teacher', 'teacher', '$2y$10$zDlg17eJoKpm9au4touBOu48gLIHiyVJ3aDyTH29YKP9tM5QzL.Oe');

-- 3. Default Subjects
INSERT INTO `subjects` (`id`, `campus_id`, `name`, `code`) VALUES
(1, 1, 'Computer Science', 'CS-101'),
(2, 1, 'Information Technology', 'IT-102'),
(3, 1, 'English Language', 'ENG-103'),
(4, 1, 'Arabic Language', 'ARAB-101'),
(5, 1, 'Turkish Language', 'TURK-101'),
(6, 1, 'Mathematics', 'MATH-101'),
(7, 1, 'General Science', 'SCI-101'),
(8, 1, 'Physics', 'PHYS-101'),
(9, 1, 'Chemistry', 'CHEM-101'),
(10, 1, 'Biology', 'BIO-101'),
(11, 1, 'Social Studies', 'SS-101'),
(12, 1, 'Islamic Studies', 'ISL-101'),
(13, 1, 'Pakistan Studies', 'PST-101'),
(14, 1, 'History & Geography', 'HIST-101'),
(15, 1, 'Urdu Language', 'URDU-101'),
(16, 1, 'Arts & Drawing', 'ART-101'),
(17, 1, 'Physical Education', 'PE-101'),
(18, 1, 'Economics & Commerce', 'ECON-101');

