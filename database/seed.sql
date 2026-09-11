-- ============================================================
-- Marr Typing Competition System
-- Database Seed — Default Data
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+05:00";

-- ============================================================
-- ROLES
-- ============================================================
INSERT INTO `roles` (`id`, `name`, `slug`, `description`, `is_system`) VALUES
(1, 'Super Admin', 'super-admin', 'Full system access with all privileges', 1),
(2, 'Admin', 'admin', 'Administrative access with management capabilities', 1),
(3, 'Invigilator', 'invigilator', 'Competition monitoring and supervision access', 1),
(4, 'Candidate', 'candidate', 'Typing test participant access', 1);

-- ============================================================
-- PERMISSIONS
-- ============================================================
INSERT INTO `permissions` (`name`, `slug`, `module`, `description`) VALUES
-- Dashboard
('View Dashboard', 'dashboard.view', 'dashboard', 'Access the dashboard'),

-- User Management
('View Users', 'users.view', 'users', 'View user list'),
('Create Users', 'users.create', 'users', 'Create new users'),
('Edit Users', 'users.edit', 'users', 'Edit existing users'),
('Delete Users', 'users.delete', 'users', 'Delete users'),
('Manage Roles', 'roles.manage', 'users', 'Manage roles and permissions'),

-- Competitions
('View Competitions', 'competitions.view', 'competitions', 'View competition list'),
('Create Competitions', 'competitions.create', 'competitions', 'Create new competitions'),
('Edit Competitions', 'competitions.edit', 'competitions', 'Edit existing competitions'),
('Delete Competitions', 'competitions.delete', 'competitions', 'Delete competitions'),
('Manage Competition Status', 'competitions.manage_status', 'competitions', 'Change competition status'),

-- Candidates
('View Candidates', 'candidates.view', 'candidates', 'View candidate list'),
('Create Candidates', 'candidates.create', 'candidates', 'Register new candidates'),
('Edit Candidates', 'candidates.edit', 'candidates', 'Edit candidate details'),
('Delete Candidates', 'candidates.delete', 'candidates', 'Remove candidates'),
('Approve Candidates', 'candidates.approve', 'candidates', 'Approve candidate registration'),
('Import Candidates', 'candidates.import', 'candidates', 'Bulk import candidates via CSV'),

-- Typing Paragraphs
('View Paragraphs', 'paragraphs.view', 'paragraphs', 'View typing paragraphs'),
('Create Paragraphs', 'paragraphs.create', 'paragraphs', 'Create new paragraphs'),
('Edit Paragraphs', 'paragraphs.edit', 'paragraphs', 'Edit paragraphs'),
('Delete Paragraphs', 'paragraphs.delete', 'paragraphs', 'Delete paragraphs'),

-- Test Management
('View Tests', 'tests.view', 'tests', 'View test sessions'),
('Manage Tests', 'tests.manage', 'tests', 'Start/stop/manage test sessions'),
('Configure Tests', 'tests.configure', 'tests', 'Configure test settings'),
('View Test Settings', 'test_settings.view', 'test_settings', 'View competition test settings'),
('Manage Test Settings', 'test_settings.manage', 'test_settings', 'Manage and modify competition test settings'),

-- Results
('View Results', 'results.view', 'results', 'View test results'),
('Export Results', 'results.export', 'results', 'Export results to PDF/Excel'),
('Manage Results', 'results.manage', 'results', 'Edit/recalculate results'),

-- Attendance
('View Attendance', 'attendance.view', 'attendance', 'View attendance records'),
('Manage Attendance', 'attendance.manage', 'attendance', 'Mark/edit attendance'),

-- Settings
('View Settings', 'settings.view', 'settings', 'View system settings'),
('Manage Settings', 'settings.manage', 'settings', 'Modify system settings'),

-- Audit Logs
('View Audit Logs', 'audit.view', 'audit', 'View audit log records'),

-- Reports
('View Reports', 'reports.view', 'reports', 'View system reports'),
('Export Reports', 'reports.export', 'reports', 'Export reports');

-- ============================================================
-- ROLE_PERMISSIONS — Super Admin gets ALL permissions
-- ============================================================
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 1, `id` FROM `permissions`;

-- Admin gets most permissions except role management and audit
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 2, `id` FROM `permissions`
WHERE `slug` NOT IN ('roles.manage', 'users.delete', 'audit.view');

-- Invigilator gets monitoring permissions
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 3, `id` FROM `permissions`
WHERE `slug` IN (
    'dashboard.view',
    'competitions.view',
    'candidates.view',
    'tests.view',
    'tests.manage',
    'results.view',
    'attendance.view',
    'attendance.manage'
);

-- Candidate gets minimal permissions
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 4, `id` FROM `permissions`
WHERE `slug` IN ('dashboard.view');

-- ============================================================
-- DEFAULT SUPER ADMIN USER
-- Password: Admin@123 (bcrypt hashed)
-- ============================================================
INSERT INTO `users` (`id`, `username`, `email`, `password`, `full_name`, `role_id`, `status`, `force_password_change`) VALUES
(1, 'admin', 'admin@marr.local', '$2y$10$TdvyZShm/ON4fPlybzwGFeJ641590J0yvokDAzvHzzYXFmP0t8zxK', 'System Administrator', 1, 'active', 0);

-- Also insert into user_roles for multi-role support
INSERT INTO `user_roles` (`user_id`, `role_id`) VALUES (1, 1);

-- ============================================================
-- SYSTEM SETTINGS
-- ============================================================
INSERT INTO `system_settings` (`setting_key`, `setting_value`, `setting_type`, `setting_group`, `description`, `is_public`) VALUES
('institute_name', 'Marr', 'text', 'general', 'Name of the institute', 1),
('system_name', 'Marr', 'text', 'general', 'System display name', 1),
('timezone', 'Asia/Karachi', 'text', 'general', 'System timezone', 0),
('institute_logo', '', 'file', 'general', 'Institute logo file path', 1),
('institute_address', 'Khairpur Mirs, Sindh, Pakistan', 'text', 'general', 'Institute address', 1),
('institute_phone', '', 'text', 'general', 'Institute phone number', 1),
('institute_email', '', 'text', 'general', 'Institute email address', 1),
('session_timeout', '30', 'number', 'security', 'Session timeout in minutes', 0),
('max_login_attempts', '5', 'number', 'security', 'Maximum login attempts before lockout', 0),
('lockout_duration', '15', 'number', 'security', 'Account lockout duration in minutes', 0),
-- ============================================================
-- DEFAULT TYPING PARAGRAPHS
-- ============================================================
INSERT INTO `typing_paragraphs` (`id`, `title`, `content`, `word_count`, `char_count`, `difficulty`, `language`, `category`, `status`, `created_by`) VALUES
(1, 'The Power of Modern Computing', 'Computers have transformed nearly every aspect of modern society. From scientific research and engineering to education and business, digital systems enable people to communicate instantly, process immense volumes of data, and automate repetitive tasks. Mastering the keyboard is one of the most fundamental skills for navigating the digital age with speed, confidence, and precision.', 51, 381, 'easy', 'english', 'Technology', 'active', 1),
(2, 'Discipline and Professional Excellence', 'Professional excellence is not an accident; it is the culmination of deliberate practice, focused concentration, and an unwavering commitment to quality. When typing at high speeds, accuracy must always precede velocity. A calm mind, correct ergonomics, and rhythmic keystrokes allow typists to achieve sustained high performance without fatigue.', 46, 346, 'medium', 'english', 'Productivity', 'active', 1),
(3, 'Algorithms and Cyber Security Architecture', 'In modern distributed networks, cryptographic algorithms (such as RSA-4096 and AES-256-GCM) safeguard data integrity across multi-cloud environments. Network engineers must configure robust firewalls, monitor real-time packet latency [10-50ms], and enforce zero-trust authentication protocols to protect sensitive corporate assets against unauthorized intrusions.', 44, 357, 'hard', 'english', 'Cybersecurity', 'active', 1);

COMMIT;
