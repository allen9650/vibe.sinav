<?php
/**
 * Web Routes
 * Maps page names to view files and their middleware
 * 
 * Format: 'page_name' => [
 *   'view'       => 'path/to/view.php',
 *   'middleware'  => 'auth' | 'guest' | null,
 *   'permission'  => 'permission.slug' (optional),
 *   'roles'       => ['role-slug'] (optional),
 *   'title'       => 'Page Title',
 * ]
 */

return [
    // Auth routes (guest only)
    'login' => [
        'view'       => 'auth/login.php',
        'middleware'  => 'guest',
        'title'      => 'Login',
        'layout'     => 'auth',
    ],

    // Auth routes (requires login)
    'logout' => [
        'view'       => 'auth/logout.php',
        'middleware'  => 'auth',
        'title'      => 'Logout',
        'layout'     => null,
    ],
    'change-password' => [
        'view'       => 'auth/change-password.php',
        'middleware'  => 'auth',
        'title'      => 'Change Password',
        'layout'     => 'admin',
    ],

    // Dashboard (Main System Dashboard)
    'dashboard' => [
        'view'       => 'dashboard/index.php',
        'middleware'  => 'auth',
        'title'      => 'Dashboard',
        'layout'     => 'admin',
    ],

    // Teacher Dashboard
    'teacher-dashboard' => [
        'view'       => 'teacher/dashboard.php',
        'middleware'  => 'auth',
        'title'      => 'Teacher Dashboard',
        'layout'     => 'admin',
    ],

    // Profile
    'profile' => [
        'view'       => 'profile/index.php',
        'middleware'  => 'auth',
        'title'      => 'My Profile',
        'layout'     => 'admin',
    ],

    // Settings
    'settings' => [
        'view'       => 'settings/index.php',
        'middleware'  => 'auth',
        'permission'  => 'settings.manage',
        'title'      => 'System Settings',
        'layout'     => 'admin',
    ],

    // Audit Logs
    'audit-logs' => [
        'view'       => 'audit/index.php',
        'middleware'  => 'auth',
        'permission'  => 'audit.view',
        'title'      => 'Audit Logs',
        'layout'     => 'admin',
    ],

    // User Management (Super-Admin Only)
    'users' => [
        'view'       => 'users/index.php',
        'middleware' => 'auth',
        'roles'      => ['super-admin'],
        'title'      => 'User Management',
        'layout'     => 'admin',
    ],
    'users-create' => [
        'view'       => 'users/create.php',
        'middleware' => 'auth',
        'roles'      => ['super-admin'],
        'title'      => 'Add New User',
        'layout'     => 'admin',
    ],
    'users-edit' => [
        'view'       => 'users/edit.php',
        'middleware' => 'auth',
        'roles'      => ['super-admin'],
        'title'      => 'Edit User & Permissions',
        'layout'     => 'admin',
    ],

    // Competitions Module
    'competitions' => [
        'view'       => 'competitions/index.php',
        'middleware'  => 'auth',
        'permission'  => 'competitions.view',
        'title'      => 'Competitions',
        'layout'     => 'admin',
    ],
    'competitions-create' => [
        'view'       => 'competitions/create.php',
        'middleware'  => 'auth',
        'permission'  => 'competitions.create',
        'title'      => 'Add Competition',
        'layout'     => 'admin',
    ],
    'competitions-edit' => [
        'view'       => 'competitions/edit.php',
        'middleware'  => 'auth',
        'permission'  => 'competitions.edit',
        'title'      => 'Edit Competition',
        'layout'     => 'admin',
    ],
    'competitions-view' => [
        'view'       => 'competitions/view.php',
        'middleware'  => 'auth',
        'permission'  => 'competitions.view',
        'title'      => 'Competition Details',
        'layout'     => 'admin',
    ],
    'competitions-duplicate' => [
        'view'       => 'competitions/duplicate.php',
        'middleware'  => 'auth',
        'permission'  => 'competitions.create',
        'title'      => 'Duplicate Competition',
        'layout'     => 'admin',
    ],

    // Candidates Module
    'candidates' => [
        'view'       => 'candidates/index.php',
        'middleware'  => 'auth',
        'permission'  => 'candidates.view',
        'title'      => 'Candidates',
        'layout'     => 'admin',
    ],
    'candidates-create' => [
        'view'       => 'candidates/create.php',
        'middleware'  => 'auth',
        'permission'  => 'candidates.create',
        'title'      => 'Register Candidate',
        'layout'     => 'admin',
    ],
    'candidates-edit' => [
        'view'       => 'candidates/edit.php',
        'middleware'  => 'auth',
        'permission'  => 'candidates.edit',
        'title'      => 'Edit Candidate',
        'layout'     => 'admin',
    ],
    'candidates-view' => [
        'view'       => 'candidates/view.php',
        'middleware'  => 'auth',
        'permission'  => 'candidates.view',
        'title'      => 'Candidate Profile',
        'layout'     => 'admin',
    ],
    'candidates-import' => [
        'view'       => 'candidates/import.php',
        'middleware'  => 'auth',
        'permission'  => 'candidates.import',
        'title'      => 'Import Candidates',
        'layout'     => 'admin',
    ],
    'candidates-print' => [
        'view'       => 'candidates/print.php',
        'middleware'  => 'auth',
        'permission'  => 'candidates.view',
        'title'      => 'Print Candidate List',
        'layout'     => null,
    ],

    // Attendance Module
    'attendance' => [
        'view'       => 'attendance/index.php',
        'middleware'  => 'auth',
        'permission'  => 'attendance.view',
        'title'      => 'Attendance',
        'layout'     => 'admin',
    ],

    // Competition Sub-Modules (Paragraphs & Settings)
    'competitions-paragraphs' => [
        'view'       => 'competitions/paragraphs.php',
        'middleware'  => 'auth',
        'permission'  => 'competitions.edit',
        'title'      => 'Competition Paragraphs',
        'layout'     => 'admin',
    ],
    'competitions-settings' => [
        'view'       => 'competitions/settings.php',
        'middleware'  => 'auth',
        'permission'  => 'test_settings.manage',
        'title'      => 'Competition Test Settings',
        'layout'     => 'admin',
    ],

    // Typing Paragraphs Module
    'paragraphs' => [
        'view'       => 'paragraphs/index.php',
        'middleware'  => 'auth',
        'permission'  => 'paragraphs.view',
        'title'      => 'Typing Paragraphs',
        'layout'     => 'admin',
    ],
    'paragraphs-create' => [
        'view'       => 'paragraphs/create.php',
        'middleware'  => 'auth',
        'permission'  => 'paragraphs.create',
        'title'      => 'Add Paragraph',
        'layout'     => 'admin',
    ],
    'paragraphs-edit' => [
        'view'       => 'paragraphs/edit.php',
        'middleware'  => 'auth',
        'permission'  => 'paragraphs.edit',
        'title'      => 'Edit Paragraph',
        'layout'     => 'admin',
    ],
    'paragraphs-view' => [
        'view'       => 'paragraphs/view.php',
        'middleware'  => 'auth',
        'permission'  => 'paragraphs.view',
        'title'      => 'Paragraph Details',
        'layout'     => 'admin',
    ],
    'paragraphs-preview' => [
        'view'       => 'paragraphs/preview.php',
        'middleware'  => 'auth',
        'permission'  => 'paragraphs.view',
        'title'      => 'Preview Paragraph Test Layout',
        'layout'     => 'admin',
    ],

    // Candidate Test Portal (Candidate Facing)
    'test-portal' => [
        'view'       => 'test/login.php',
        'middleware'  => null,
        'title'      => 'Candidate Test Login',
        'layout'     => null,
    ],
    'test-instructions' => [
        'view'       => 'test/instructions.php',
        'middleware'  => null,
        'title'      => 'Test Instructions',
        'layout'     => null,
    ],
    'test-screen' => [
        'view'       => 'test/screen.php',
        'middleware'  => null,
        'title'      => 'Typing Test',
        'layout'     => null,
    ],
    'test-autosave' => [
        'view'       => 'test/autosave.php',
        'middleware'  => null,
        'title'      => 'Test Auto Save',
        'layout'     => null,
    ],
    'test-submit' => [
        'view'       => 'test/submit.php',
        'middleware'  => null,
        'title'      => 'Test Submission',
        'layout'     => null,
    ],
    'test-completed' => [
        'view'       => 'test/completed.php',
        'middleware'  => null,
        'title'      => 'Test Submitted',
        'layout'     => null,
    ],
    'test-result' => [
        'view'       => 'test/result.php',
        'middleware'  => null,
        'title'      => 'Test Result',
        'layout'     => null,
    ],
    'test-security-event' => [
        'view'       => 'test/security_event.php',
        'middleware'  => null,
        'title'      => 'Security Event',
        'layout'     => null,
    ],
    'test-disqualified' => [
        'view'       => 'test/disqualified.php',
        'middleware'  => null,
        'title'      => 'Test Disqualified',
        'layout'     => null,
    ],
    'candidate-logout' => [
        'view'       => 'test/logout.php',
        'middleware'  => null,
        'title'      => 'Candidate Logout',
        'layout'     => null,
    ],

    // Security & Telemetry Module (Step 6)
    'security-events' => [
        'view'       => 'security/index.php',
        'middleware'  => 'auth',
        'permission'  => 'security_events.view',
        'title'      => 'Security Violations',
        'layout'     => 'admin',
    ],
    'live-tests' => [
        'view'       => 'monitor/index.php',
        'middleware'  => 'auth',
        'permission'  => 'live_tests.view',
        'title'      => 'Live Test Monitor',
        'layout'     => 'admin',
    ],
    'live-tests-api' => [
        'view'       => 'monitor/api.php',
        'middleware'  => null,
        'title'      => 'Live Monitor API',
        'layout'     => null,
    ],

    // Leaderboard & Competition Finalization (Step 6)
    'leaderboard' => [
        'view'       => 'leaderboard/index.php',
        'middleware'  => null,
        'title'      => 'Leaderboard',
        'layout'     => 'admin',
    ],
    'leaderboard-print' => [
        'view'       => 'leaderboard/print.php',
        'middleware'  => null,
        'title'      => 'Official Competition Standings',
        'layout'     => null,
    ],
    'competitions-finalize' => [
        'view'       => 'competitions/finalize.php',
        'middleware'  => 'auth',
        'permission'  => 'competitions.finalize',
        'title'      => 'Finalize Competition',
        'layout'     => 'admin',
    ],
    'competitions-lock' => [
        'view'       => 'competitions/lock.php',
        'middleware'  => 'auth',
        'permission'  => 'results.lock',
        'title'      => 'Lock Results',
        'layout'     => null,
    ],

    // Results Module (Step 6 & 7)
    'results' => [
        'view'       => 'results/index.php',
        'middleware'  => 'auth',
        'permission'  => 'results.view',
        'title'      => 'Test Results',
        'layout'     => 'admin',
    ],
    'results-view' => [
        'view'       => 'results/view.php',
        'middleware'  => 'auth',
        'permission'  => 'results.view',
        'title'      => 'Result Breakdown',
        'layout'     => 'admin',
    ],
    'results-print' => [
        'view'       => 'results/print.php',
        'middleware'  => 'auth',
        'permission'  => 'results.view',
        'title'      => 'Print Result Sheet',
        'layout'     => null,
    ],
    'results-slip' => [
        'view'       => 'results/slip.php',
        'middleware'  => 'auth',
        'permission'  => 'results.view',
        'title'      => 'Result Slip',
        'layout'     => null,
    ],

    // Reports Module (Step 7)
    'reports' => [
        'view'       => 'reports/index.php',
        'middleware'  => 'auth',
        'permission'  => 'reports.view',
        'title'      => 'Reports Center',
        'layout'     => 'admin',
    ],
    'reports-export' => [
        'view'       => 'reports/export.php',
        'middleware'  => 'auth',
        'permission'  => 'reports.export',
        'title'      => 'Export Report',
        'layout'     => null,
    ],

    // Certificates Module (Step 7)
    'certificates' => [
        'view'       => 'certificates/index.php',
        'middleware'  => 'auth',
        'permission'  => 'certificates.view',
        'title'      => 'Certificates',
        'layout'     => 'admin',
    ],
    'certificates-generate' => [
        'view'       => 'certificates/generate.php',
        'middleware'  => 'auth',
        'permission'  => 'certificates.generate',
        'title'      => 'Bulk Certificate Generator',
        'layout'     => 'admin',
    ],
    'certificates-view' => [
        'view'       => 'certificates/view.php',
        'middleware'  => 'auth',
        'permission'  => 'certificates.print',
        'title'      => 'Print Certificate',
        'layout'     => null,
    ],

    // System & Maintenance Module (Step 8)
    'backup' => [
        'view'       => 'system/backup.php',
        'middleware'  => 'auth',
        'permission'  => 'settings.manage',
        'title'      => 'Database Backup',
        'layout'     => 'admin',
    ],
    'system-health' => [
        'view'       => 'system/health.php',
        'middleware'  => 'auth',
        'permission'  => 'settings.manage',
        'title'      => 'System Diagnostics',
        'layout'     => 'admin',
    ],

    // Placeholder routes for remaining management (Step 8+)
    'tests' => [
        'view'       => 'placeholder.php',
        'middleware'  => 'auth',
        'permission'  => 'tests.view',
        'title'      => 'Tests',
        'layout'     => 'admin',
    ],

    // ============================================================
    // PTM Assessment & Interactive Examination System Routes
    // ============================================================

    // Question Bank
    'questions' => [
        'view'       => 'questions/index.php',
        'middleware'  => 'auth',
        'permission'  => 'questions.view',
        'title'      => 'Question Bank',
        'layout'     => 'admin',
    ],
    'questions-create' => [
        'view'       => 'questions/create.php',
        'middleware'  => 'auth',
        'permission'  => 'questions.create',
        'title'      => 'Add Question',
        'layout'     => 'admin',
    ],
    'questions-edit' => [
        'view'       => 'questions/edit.php',
        'middleware'  => 'auth',
        'permission'  => 'questions.edit',
        'title'      => 'Edit Question',
        'layout'     => 'admin',
    ],

    // Assessments & Quiz Builder
    'assessments' => [
        'view'       => 'assessments/index.php',
        'middleware'  => 'auth',
        'permission'  => 'assessments.view',
        'title'      => 'Assessments & Quizzes',
        'layout'     => 'admin',
    ],
    'assessments-create' => [
        'view'       => 'assessments/create.php',
        'middleware'  => 'auth',
        'permission'  => 'assessments.create',
        'title'      => 'Create Assessment',
        'layout'     => 'admin',
    ],
    'assessments-edit' => [
        'view'       => 'assessments/edit.php',
        'middleware'  => 'auth',
        'permission'  => 'assessments.edit',
        'title'      => 'Edit Assessment Settings',
        'layout'     => 'admin',
    ],
    'assessments-builder' => [
        'view'       => 'assessments/builder.php',
        'middleware'  => 'auth',
        'permission'  => 'assessments.edit',
        'title'      => 'Assessment Question Builder',
        'layout'     => 'admin',
    ],
    'assessments-preview' => [
        'view'       => 'assessments/preview.php',
        'middleware'  => 'auth',
        'permission'  => 'assessments.preview',
        'title'      => 'Preview Assessment',
        'layout'     => null,
    ],
    'assessments-results' => [
        'view'       => 'assessments/results.php',
        'middleware'  => 'auth',
        'permission'  => 'assessment_results.view',
        'title'      => 'Assessment Results Ledger',
        'layout'     => 'admin',
    ],
    'assessments-candidate-detail' => [
        'view'       => 'assessments/candidate_detail.php',
        'middleware'  => 'auth',
        'permission'  => 'assessment_results.view',
        'title'      => 'Candidate Performance Audit',
        'layout'     => 'admin',
    ],
    'monitoring' => [
        'view'       => 'assessments/live_monitor.php',
        'middleware'  => 'auth',
        'title'      => 'Live Lab & Assessment Monitor',
        'layout'     => 'admin',
    ],
    'assessments-monitor' => [
        'view'       => 'assessments/live_monitor.php',
        'middleware'  => 'auth',
        'title'      => 'Live Lab & Assessment Monitor',
        'layout'     => 'admin',
    ],

    // Candidate Assessment Examination Portal
    'test-start' => [
        'view'       => 'test/start.php',
        'middleware'  => null,
        'title'      => 'Workstation Candidate Intake',
        'layout'     => null,
    ],
    'quiz-start' => [
        'view'       => 'test/start.php',
        'middleware'  => null,
        'title'      => 'Workstation Candidate Intake',
        'layout'     => null,
    ],
    'test-screen' => [
        'view'       => 'test/screen.php',
        'middleware'  => null,
        'title'      => 'Candidate Assessment Examination',
        'layout'     => null,
    ],
    'quiz-screen' => [
        'view'       => 'test/screen.php',
        'middleware'  => null,
        'title'      => 'Candidate Assessment Examination',
        'layout'     => null,
    ],
    'test-submit' => [
        'view'       => 'test/submit.php',
        'middleware'  => null,
        'title'      => 'Submit Assessment',
        'layout'     => null,
    ],
    'quiz-submit' => [
        'view'       => 'test/submit.php',
        'middleware'  => null,
        'title'      => 'Submit Assessment',
        'layout'     => null,
    ],
    'test-result' => [
        'view'       => 'test/result.php',
        'middleware'  => null,
        'title'      => 'Assessment Scorecard',
        'layout'     => null,
    ],
    'quiz-result' => [
        'view'       => 'test/result.php',
        'middleware'  => null,
        'title'      => 'Assessment Scorecard',
        'layout'     => null,
    ],
    'assessments-devices' => [
        'view'       => 'assessments/devices.php',
        'middleware'  => 'auth',
        'title'      => 'Lab Device Allocation',
        'layout'     => 'admin',
    ],
    'assessments-export' => [
        'view'       => 'assessments/export.php',
        'middleware'  => 'auth',
        'title'      => 'Export Assessment Results',
        'layout'     => null,
    ],

    // Lightweight LAN APIs
    'api-autosave' => [
        'view'       => 'api/autosave.php',
        'middleware'  => null,
        'layout'     => null,
    ],
    'api-check-result-status' => [
        'view'       => 'api/check_result_status.php',
        'middleware'  => null,
        'layout'     => null,
    ],
    'api-toggle-publish' => [
        'view'       => 'api/toggle_publish.php',
        'middleware'  => 'auth',
        'layout'     => null,
    ],
    'api-categories' => [
        'view'       => 'api/categories.php',
        'middleware'  => null,
        'layout'     => null,
    ],
    'api-quiz-autosave' => [
        'view'       => 'api/autosave.php',
        'middleware'  => null,
        'layout'     => null,
    ],
    'api-quiz-telemetry' => [
        'view'       => 'api/quiz_telemetry.php',
        'middleware'  => null,
        'layout'     => null,
    ],
    'api-assessment-monitor' => [
        'view'       => 'api/assessment_monitor.php',
        'middleware'  => 'auth',
        'layout'     => null,
    ],
];
