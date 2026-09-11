-- ============================================================
-- PTM Assessment & Interactive Examination System
-- Database Migration: Assessment System Foundation
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+05:00";

-- 1. CAMPUSES
CREATE TABLE IF NOT EXISTS `campuses` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(150) NOT NULL,
    `code` VARCHAR(50) NOT NULL,
    `city` VARCHAR(100) DEFAULT NULL,
    `address` VARCHAR(255) DEFAULT NULL,
    `phone` VARCHAR(30) DEFAULT NULL,
    `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_campuses_code` (`code`),
    INDEX `idx_campuses_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. SUBJECTS
CREATE TABLE IF NOT EXISTS `subjects` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(150) NOT NULL,
    `code` VARCHAR(50) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_subjects_code` (`code`),
    INDEX `idx_subjects_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. QUESTION CATEGORIES (Topics / Units)
CREATE TABLE IF NOT EXISTS `question_categories` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `subject_id` INT UNSIGNED NOT NULL,
    `name` VARCHAR(150) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_qc_subject` (`subject_id`),
    CONSTRAINT `fk_qc_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. QUESTIONS (Reusable Question Bank)
CREATE TABLE IF NOT EXISTS `questions` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `campus_id` INT UNSIGNED NULL COMMENT 'NULL = Global / Shared across all campuses',
    `created_by` INT UNSIGNED NOT NULL COMMENT 'User ID of teacher / creator',
    `subject_id` INT UNSIGNED NOT NULL,
    `category_id` INT UNSIGNED NULL,
    `question_text` TEXT NOT NULL,
    `question_type` ENUM(
        'single_choice', 
        'multiple_choice', 
        'true_false', 
        'yes_no', 
        'fill_blank', 
        'matching', 
        'ordering', 
        'typing',
        'short_answer'
    ) NOT NULL DEFAULT 'single_choice',
    `difficulty` ENUM('easy', 'medium', 'hard') NOT NULL DEFAULT 'medium',
    `marks` DECIMAL(5,2) NOT NULL DEFAULT 1.00,
    `negative_marks` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    `partial_scoring` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Enable for multiple choice & matching',
    `explanation` TEXT DEFAULT NULL COMMENT 'Solution feedback/explanation',
    `image_path` VARCHAR(255) DEFAULT NULL COMMENT 'Local upload path in public/uploads/questions/',
    `typing_paragraph_id` INT UNSIGNED NULL COMMENT 'FK to typing_paragraphs for typing questions',
    `status` ENUM('active', 'inactive', 'archived') NOT NULL DEFAULT 'active',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_questions_subject` (`subject_id`),
    INDEX `idx_questions_category` (`category_id`),
    INDEX `idx_questions_type` (`question_type`),
    INDEX `idx_questions_difficulty` (`difficulty`),
    INDEX `idx_questions_creator` (`created_by`),
    INDEX `idx_questions_campus` (`campus_id`),
    INDEX `idx_questions_status` (`status`),
    CONSTRAINT `fk_q_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_q_category` FOREIGN KEY (`category_id`) REFERENCES `question_categories` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_q_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_q_campus` FOREIGN KEY (`campus_id`) REFERENCES `campuses` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_q_typing_para` FOREIGN KEY (`typing_paragraph_id`) REFERENCES `typing_paragraphs` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. QUESTION OPTIONS & ANSWERS
CREATE TABLE IF NOT EXISTS `question_options` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `question_id` INT UNSIGNED NOT NULL,
    `option_text` TEXT NOT NULL,
    `option_order` INT UNSIGNED NOT NULL DEFAULT 0,
    `is_correct` TINYINT(1) NOT NULL DEFAULT 0,
    `match_target` TEXT DEFAULT NULL COMMENT 'For matching questions: Target pair',
    `accepted_answers` TEXT DEFAULT NULL COMMENT 'For fill_blank: comma-separated accepted alternatives',
    PRIMARY KEY (`id`),
    INDEX `idx_qo_question` (`question_id`),
    INDEX `idx_qo_order` (`option_order`),
    CONSTRAINT `fk_qo_question` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. ASSESSMENTS
CREATE TABLE IF NOT EXISTS `assessments` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title` VARCHAR(255) NOT NULL,
    `code` VARCHAR(50) NOT NULL,
    `assessment_type` ENUM('quiz', 'typing', 'combined') NOT NULL DEFAULT 'quiz',
    `campus_id` INT UNSIGNED NOT NULL,
    `teacher_id` INT UNSIGNED NOT NULL COMMENT 'Creator / Teacher user ID',
    `subject_id` INT UNSIGNED NOT NULL,
    `class_grade` VARCHAR(100) NOT NULL COMMENT 'e.g. Grade 8, Batch 2026, Class 10',
    `description` TEXT DEFAULT NULL,
    `instructions` TEXT DEFAULT NULL,
    `duration_minutes` INT UNSIGNED NOT NULL DEFAULT 30,
    `duration_seconds` INT UNSIGNED NOT NULL DEFAULT 1800,
    `start_date_time` DATETIME NULL DEFAULT NULL,
    `end_date_time` DATETIME NULL DEFAULT NULL,
    `randomize_questions` TINYINT(1) NOT NULL DEFAULT 0,
    `randomize_options` TINYINT(1) NOT NULL DEFAULT 0,
    `allow_navigation` TINYINT(1) NOT NULL DEFAULT 1,
    `allow_backtrack` TINYINT(1) NOT NULL DEFAULT 1,
    `mark_for_review` TINYINT(1) NOT NULL DEFAULT 1,
    `fullscreen_required` TINYINT(1) NOT NULL DEFAULT 1,
    `detect_tab_switch` TINYINT(1) NOT NULL DEFAULT 1,
    `detect_window_blur` TINYINT(1) NOT NULL DEFAULT 1,
    `disable_copy_paste` TINYINT(1) NOT NULL DEFAULT 1,
    `disable_right_click` TINYINT(1) NOT NULL DEFAULT 1,
    `max_violations` INT UNSIGNED NOT NULL DEFAULT 3,
    `violation_action` ENUM('log_only', 'warning', 'auto_disqualify') NOT NULL DEFAULT 'warning',
    `total_marks` DECIMAL(8,2) NOT NULL DEFAULT 0.00,
    `passing_percentage` DECIMAL(5,2) NOT NULL DEFAULT 50.00,
    `negative_marking` TINYINT(1) NOT NULL DEFAULT 0,
    `max_attempts` INT UNSIGNED NOT NULL DEFAULT 1,
    `result_display_mode` ENUM('immediate', 'after_publish', 'hidden') NOT NULL DEFAULT 'immediate',
    `status` ENUM('draft', 'published', 'archived') NOT NULL DEFAULT 'draft',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_assessments_code` (`code`),
    INDEX `idx_assessments_campus` (`campus_id`),
    INDEX `idx_assessments_teacher` (`teacher_id`),
    INDEX `idx_assessments_subject` (`subject_id`),
    INDEX `idx_assessments_status` (`status`),
    INDEX `idx_assessments_type` (`assessment_type`),
    CONSTRAINT `fk_ass_campus` FOREIGN KEY (`campus_id`) REFERENCES `campuses` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_ass_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_ass_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. ASSESSMENT SECTIONS (For Combined Quizzes & Multi-Part Tests)
CREATE TABLE IF NOT EXISTS `assessment_sections` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `assessment_id` INT UNSIGNED NOT NULL,
    `title` VARCHAR(150) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `section_type` ENUM('quiz', 'typing') NOT NULL DEFAULT 'quiz',
    `section_order` INT UNSIGNED NOT NULL DEFAULT 1,
    `typing_paragraph_id` INT UNSIGNED NULL,
    `duration_minutes` INT UNSIGNED NULL,
    `total_marks` DECIMAL(8,2) NOT NULL DEFAULT 0.00,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_asec_assessment` (`assessment_id`),
    INDEX `idx_asec_order` (`section_order`),
    CONSTRAINT `fk_asec_assessment` FOREIGN KEY (`assessment_id`) REFERENCES `assessments` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_asec_typing_para` FOREIGN KEY (`typing_paragraph_id`) REFERENCES `typing_paragraphs` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. ASSESSMENT QUESTIONS (Mapping & Custom Ordering)
CREATE TABLE IF NOT EXISTS `assessment_questions` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `assessment_id` INT UNSIGNED NOT NULL,
    `section_id` INT UNSIGNED NULL,
    `question_id` INT UNSIGNED NOT NULL,
    `question_order` INT UNSIGNED NOT NULL DEFAULT 0,
    `custom_marks` DECIMAL(5,2) NULL DEFAULT NULL,
    `custom_negative_marks` DECIMAL(5,2) NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_aq_assessment_question` (`assessment_id`, `question_id`),
    INDEX `idx_aq_section` (`section_id`),
    INDEX `idx_aq_order` (`question_order`),
    CONSTRAINT `fk_aq_assessment` FOREIGN KEY (`assessment_id`) REFERENCES `assessments` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_aq_section` FOREIGN KEY (`section_id`) REFERENCES `assessment_sections` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_aq_question` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. ASSESSMENT CANDIDATES (Roster assignment)
CREATE TABLE IF NOT EXISTS `assessment_candidates` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `assessment_id` INT UNSIGNED NOT NULL,
    `candidate_id` INT UNSIGNED NOT NULL,
    `roll_number` VARCHAR(50) NOT NULL,
    `status` ENUM('registered', 'present', 'in_progress', 'completed', 'disqualified') NOT NULL DEFAULT 'registered',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_ac_assessment_candidate` (`assessment_id`, `candidate_id`),
    UNIQUE KEY `uk_ac_assessment_roll` (`assessment_id`, `roll_number`),
    INDEX `idx_ac_status` (`status`),
    CONSTRAINT `fk_ac_assessment` FOREIGN KEY (`assessment_id`) REFERENCES `assessments` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ac_candidate` FOREIGN KEY (`candidate_id`) REFERENCES `candidates` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. ASSESSMENT ATTEMPTS (Server-authoritative timer & session)
CREATE TABLE IF NOT EXISTS `assessment_attempts` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `assessment_id` INT UNSIGNED NOT NULL,
    `candidate_id` INT UNSIGNED NOT NULL,
    `student_name` VARCHAR(100) NULL,
    `attempt_number` INT UNSIGNED NOT NULL DEFAULT 1,
    `started_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `expected_end_at` TIMESTAMP NULL DEFAULT NULL,
    `submitted_at` TIMESTAMP NULL DEFAULT NULL,
    `time_taken_seconds` INT UNSIGNED DEFAULT 0,
    `duration_seconds` INT UNSIGNED NOT NULL DEFAULT 1800,
    `status` ENUM('in_progress', 'completed', 'timed_out', 'disqualified', 'abandoned') NOT NULL DEFAULT 'in_progress',
    `question_order_seed` JSON DEFAULT NULL COMMENT 'Stores randomized question ID sequence for this attempt',
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `user_agent` VARCHAR(500) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_att_assessment` (`assessment_id`),
    INDEX `idx_att_candidate` (`candidate_id`),
    INDEX `idx_att_status` (`status`),
    CONSTRAINT `fk_att_assessment` FOREIGN KEY (`assessment_id`) REFERENCES `assessments` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_att_candidate` FOREIGN KEY (`candidate_id`) REFERENCES `candidates` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. ASSESSMENT ANSWERS (Delta saves & evaluation)
CREATE TABLE IF NOT EXISTS `assessment_answers` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `attempt_id` INT UNSIGNED NOT NULL,
    `question_id` INT UNSIGNED NOT NULL,
    `selected_option_ids` JSON DEFAULT NULL COMMENT 'Single/Multiple choice, true/false, yes/no IDs',
    `text_answer` TEXT DEFAULT NULL COMMENT 'Fill in blank or short answer string',
    `matching_answers` JSON DEFAULT NULL COMMENT 'Key-value map: option_id => match_target',
    `ordering_answers` JSON DEFAULT NULL COMMENT 'Array of option_ids in submitted order',
    `typing_result_id` INT UNSIGNED NULL COMMENT 'FK to test_results if section was typing',
    `is_marked_for_review` TINYINT(1) NOT NULL DEFAULT 0,
    `is_correct` TINYINT(1) NULL DEFAULT NULL,
    `marks_obtained` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    `teacher_comment` VARCHAR(255) NULL,
    `evaluated_by` INT UNSIGNED NULL,
    `evaluated_at` TIMESTAMP NULL DEFAULT NULL,
    `last_saved_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_aa_attempt_question` (`attempt_id`, `question_id`),
    INDEX `idx_aa_question` (`question_id`),
    CONSTRAINT `fk_aa_attempt` FOREIGN KEY (`attempt_id`) REFERENCES `assessment_attempts` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_aa_question` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_aa_typing_result` FOREIGN KEY (`typing_result_id`) REFERENCES `test_results` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. ASSESSMENT RESULTS
CREATE TABLE IF NOT EXISTS `assessment_results` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `attempt_id` INT UNSIGNED NOT NULL,
    `assessment_id` INT UNSIGNED NOT NULL,
    `candidate_id` INT UNSIGNED NOT NULL,
    `total_questions` INT UNSIGNED NOT NULL DEFAULT 0,
    `correct_answers` INT UNSIGNED NOT NULL DEFAULT 0,
    `wrong_answers` INT UNSIGNED NOT NULL DEFAULT 0,
    `unanswered` INT UNSIGNED NOT NULL DEFAULT 0,
    `total_marks` DECIMAL(8,2) NOT NULL DEFAULT 0.00,
    `obtained_marks` DECIMAL(8,2) NOT NULL DEFAULT 0.00,
    `percentage` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    `accuracy` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    `pass_fail` ENUM('pass', 'fail') NOT NULL DEFAULT 'fail',
    `manual_review_pending` TINYINT(1) NOT NULL DEFAULT 0,
    `time_taken_seconds` INT UNSIGNED NOT NULL DEFAULT 0,
    `rank` INT UNSIGNED NULL DEFAULT NULL,
    `calculated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_ar_attempt` (`attempt_id`),
    INDEX `idx_ar_assessment` (`assessment_id`),
    INDEX `idx_ar_candidate` (`candidate_id`),
    INDEX `idx_ar_percentage` (`percentage`),
    INDEX `idx_ar_rank` (`rank`),
    CONSTRAINT `fk_ar_attempt` FOREIGN KEY (`attempt_id`) REFERENCES `assessment_attempts` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ar_assessment` FOREIGN KEY (`assessment_id`) REFERENCES `assessments` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ar_candidate` FOREIGN KEY (`candidate_id`) REFERENCES `candidates` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
COMMIT;
