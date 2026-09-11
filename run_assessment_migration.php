<?php
chdir('C:/xampp/htdocs/ptmtest');
require_once 'config/app.php';
require_once CORE_PATH . '/Database.php';

echo "============================================================\n";
echo "PTM Assessment System — Database Migration\n";
echo "============================================================\n\n";

$pdo = Database::getInstance();

// 1. Execute SQL schema migration
echo "1. Applying assessment_system.sql tables...\n";
$sqlFile = __DIR__ . '/database/migrations/assessment_system.sql';
if (!file_exists($sqlFile)) {
    die("Error: $sqlFile not found.\n");
}
$sql = file_get_contents($sqlFile);
$pdo->exec($sql);
echo "   Tables created successfully.\n\n";

// 2. Add campus_id to users if not present
echo "2. Checking users table for campus_id column...\n";
$cols = $pdo->query("SHOW COLUMNS FROM `users` LIKE 'campus_id'")->fetchAll();
if (empty($cols)) {
    $pdo->exec("ALTER TABLE `users` ADD COLUMN `campus_id` INT UNSIGNED NULL AFTER `role_id`");
    $pdo->exec("ALTER TABLE `users` ADD CONSTRAINT `fk_users_campus` FOREIGN KEY (`campus_id`) REFERENCES `campuses`(`id`) ON DELETE SET NULL");
    echo "   Added campus_id column and foreign key to users table.\n\n";
} else {
    echo "   campus_id column already exists.\n\n";
}

// 3. Seed Campuses
echo "3. Seeding default campuses...\n";
$campusCount = (int)$pdo->query("SELECT COUNT(*) FROM campuses")->fetchColumn();
if ($campusCount === 0) {
    $stmt = $pdo->prepare("INSERT INTO campuses (id, name, code, city, address, status) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([1, 'PTM Campus — Khairpur', 'KHP', 'Khairpur Mirs', 'Main Campus, Station Road, Khairpur Mirs, Sindh', 'active']);
    echo "   Inserted default campus: PTM Campus — Khairpur (ID: 1)\n\n";
} else {
    echo "   Campuses already seeded ($campusCount found).\n\n";
}

// 4. Seed Subjects & Categories
echo "4. Seeding default subjects and categories...\n";
$subjectCount = (int)$pdo->query("SELECT COUNT(*) FROM subjects")->fetchColumn();
if ($subjectCount === 0) {
    $stmt = $pdo->prepare("INSERT INTO subjects (id, name, code, description, status) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([1, 'Computer Science', 'CS-101', 'Core computer fundamentals, hardware, software, and networking', 'active']);
    $stmt->execute([2, 'Information Technology', 'IT-101', 'Practical computing, office productivity, and database applications', 'active']);
    $stmt->execute([3, 'General English & Typing', 'ENG-TYP', 'Keyboard mastery, language typing accuracy, and speed', 'active']);
    echo "   Inserted 3 default subjects.\n";

    $catStmt = $pdo->prepare("INSERT INTO question_categories (subject_id, name, description) VALUES (?, ?, ?)");
    // Under Computer Science
    $catStmt->execute([1, 'Computer Fundamentals', 'Introduction to computer parts, CPU, ALU, and basic operations']);
    $catStmt->execute([1, 'Computer Hardware', 'Input/output devices, memory, storage types, motherboard components']);
    $catStmt->execute([1, 'Networking & Internet', 'LAN, WAN, routers, switches, IP addresses, OSI model']);
    $catStmt->execute([1, 'Operating Systems', 'Windows, Linux, filesystems, processes, and basic OS commands']);
    // Under Information Technology
    $catStmt->execute([2, 'Office Automation', 'Word processors, spreadsheets, formulas, presentation software']);
    $catStmt->execute([2, 'Database Fundamentals', 'Tables, keys, records, SQL queries, relationships']);
    // Under English & Typing
    $catStmt->execute([3, 'Speed & Accuracy', 'Fast typing drills, rhythm, touch typing fundamentals']);
    echo "   Inserted 7 question categories.\n\n";
} else {
    echo "   Subjects already seeded ($subjectCount found).\n\n";
}

// 5. Seed Teacher Role
echo "5. Seeding Teacher role...\n";
$teacherRole = Database::fetch("SELECT * FROM roles WHERE slug = 'teacher'");
if (!$teacherRole) {
    $pdo->exec("INSERT INTO roles (id, name, slug, description, is_system, status) VALUES (5, 'Teacher', 'teacher', 'Assessment designer and question bank manager', 1, 'active')");
    $teacherRoleId = 5;
    echo "   Inserted Teacher role (ID: 5).\n\n";
} else {
    $teacherRoleId = $teacherRole['id'];
    echo "   Teacher role exists (ID: $teacherRoleId).\n\n";
}

// 6. Seed Permissions
echo "6. Seeding new permissions...\n";
$newPermissions = [
    // Campuses
    ['name' => 'View Campuses', 'slug' => 'campuses.view', 'module' => 'campuses', 'description' => 'View list of campuses'],
    ['name' => 'Manage Campuses', 'slug' => 'campuses.manage', 'module' => 'campuses', 'description' => 'Create, edit, and configure campuses'],

    // Subjects
    ['name' => 'View Subjects', 'slug' => 'subjects.view', 'module' => 'subjects', 'description' => 'View subjects and categories'],
    ['name' => 'Manage Subjects', 'slug' => 'subjects.manage', 'module' => 'subjects', 'description' => 'Create and modify subjects and categories'],

    // Questions
    ['name' => 'View Questions', 'slug' => 'questions.view', 'module' => 'questions', 'description' => 'View question bank questions'],
    ['name' => 'Create Questions', 'slug' => 'questions.create', 'module' => 'questions', 'description' => 'Add new questions to question bank'],
    ['name' => 'Edit Questions', 'slug' => 'questions.edit', 'module' => 'questions', 'description' => 'Edit questions'],
    ['name' => 'Delete Questions', 'slug' => 'questions.delete', 'module' => 'questions', 'description' => 'Delete questions'],

    // Assessments
    ['name' => 'View Assessments', 'slug' => 'assessments.view', 'module' => 'assessments', 'description' => 'View assessments and quizzes'],
    ['name' => 'Create Assessments', 'slug' => 'assessments.create', 'module' => 'assessments', 'description' => 'Create new assessments and quizzes'],
    ['name' => 'Edit Assessments', 'slug' => 'assessments.edit', 'module' => 'assessments', 'description' => 'Edit assessment details and rules'],
    ['name' => 'Delete Assessments', 'slug' => 'assessments.delete', 'module' => 'assessments', 'description' => 'Remove assessments'],
    ['name' => 'Publish Assessments', 'slug' => 'assessments.publish', 'module' => 'assessments', 'description' => 'Publish or unpublish assessments'],
    ['name' => 'Preview Assessments', 'slug' => 'assessments.preview', 'module' => 'assessments', 'description' => 'Preview student assessment experience'],

    // Results & Reports
    ['name' => 'View Assessment Results', 'slug' => 'assessment_results.view', 'module' => 'assessment_results', 'description' => 'View candidate quiz scores and results'],
    ['name' => 'Export Assessment Results', 'slug' => 'assessment_results.export', 'module' => 'assessment_results', 'description' => 'Export quiz results to CSV/print'],

    // Teacher Dashboard
    ['name' => 'View Teacher Dashboard', 'slug' => 'teacher.dashboard', 'module' => 'teacher', 'description' => 'Access the teacher dashboard'],
];

$permInsertStmt = $pdo->prepare("INSERT INTO permissions (name, slug, module, description) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE name = VALUES(name), module = VALUES(module), description = VALUES(description)");
foreach ($newPermissions as $p) {
    $permInsertStmt->execute([$p['name'], $p['slug'], $p['module'], $p['description']]);
}
echo "   New permissions ensured.\n\n";

// 7. Map Role Permissions
echo "7. Mapping role permissions...\n";
// Super Admin gets ALL permissions
$pdo->exec("INSERT IGNORE INTO role_permissions (role_id, permission_id) SELECT 1, id FROM permissions");

// Admin gets all assessment, question, subject, and result permissions
$pdo->exec("INSERT IGNORE INTO role_permissions (role_id, permission_id) SELECT 2, id FROM permissions WHERE slug IN (
    'campuses.view', 'subjects.view', 'subjects.manage',
    'questions.view', 'questions.create', 'questions.edit', 'questions.delete',
    'assessments.view', 'assessments.create', 'assessments.edit', 'assessments.delete', 'assessments.publish', 'assessments.preview',
    'assessment_results.view', 'assessment_results.export'
)");

// Teacher gets strictly educational permissions
$teacherPermSlugs = [
    'dashboard.view',
    'teacher.dashboard',
    'campuses.view',
    'subjects.view',
    'questions.view',
    'questions.create',
    'questions.edit',
    'questions.delete',
    'assessments.view',
    'assessments.create',
    'assessments.edit',
    'assessments.delete',
    'assessments.publish',
    'assessments.preview',
    'assessment_results.view',
    'assessment_results.export',
];
$inClause = "'" . implode("','", $teacherPermSlugs) . "'";
$pdo->exec("INSERT IGNORE INTO role_permissions (role_id, permission_id) SELECT $teacherRoleId, id FROM permissions WHERE slug IN ($inClause)");
echo "   Role permissions assigned.\n\n";

// 8. Update admin campus and seed sample teacher user
echo "8. Linking admin user and creating sample teacher user...\n";
$pdo->exec("UPDATE users SET campus_id = 1 WHERE username = 'admin'");

$teacherUser = Database::fetch("SELECT * FROM users WHERE username = 'teacher'");
if (!$teacherUser) {
    $teacherPasswordHash = password_hash('Teacher@123', PASSWORD_BCRYPT, ['cost' => 12]);
    $stmt = $pdo->prepare("INSERT INTO users (username, email, password, full_name, role_id, campus_id, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute(['teacher', 'teacher@marr.local', $teacherPasswordHash, 'Muhammad Ahmed', $teacherRoleId, 1, 'active']);
    $newTeacherId = (int)$pdo->lastInsertId();
    $pdo->exec("INSERT IGNORE INTO user_roles (user_id, role_id) VALUES ($newTeacherId, $teacherRoleId)");
    echo "   Created default teacher user:\n";
    echo "   - Username: teacher\n";
    echo "   - Password: Teacher@123\n";
    echo "   - Full Name: Muhammad Ahmed\n";
    echo "   - Campus: PTM Campus — Khairpur\n";
    echo "   - Role: Teacher\n\n";
} else {
    $pdo->exec("UPDATE users SET campus_id = 1 WHERE username = 'teacher'");
    echo "   Teacher user already exists (ID: {$teacherUser['id']}).\n\n";
}

// 9. Ensure uploads directory for question images
echo "9. Ensuring upload directories exist...\n";
$qDir = 'C:/xampp/htdocs/ptmtest/public/uploads/questions';
if (!is_dir($qDir)) {
    mkdir($qDir, 0755, true);
    echo "   Created directory: public/uploads/questions\n";
} else {
    echo "   public/uploads/questions already exists.\n";
}

echo "\nMigration and initialization completed successfully!\n";

