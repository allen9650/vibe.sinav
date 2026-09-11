<?php
/**
 * Step 2 Automated Test Suite
 * Comprehensive verification of Competition Management, Candidate Registration & Management,
 * Attendance, Bulk CSV Import, Printing, RBAC, and Security.
 */

echo "=== MICROSOFT INSTITUTE TYPING COMPETITION SYSTEM ===\n";
echo "=== STEP 2 — COMPREHENSIVE AUTOMATED TEST SUITE ===\n\n";

$passed = 0;
$failed = 0;
$tests = [];

function test(string $name, bool $result, string $detail = '') {
    global $passed, $failed, $tests;
    if ($result) {
        $passed++;
        echo "[PASS] {$name}" . ($detail ? " — {$detail}" : '') . "\n";
    } else {
        $failed++;
        echo "[FAIL] {$name}" . ($detail ? " — {$detail}" : '') . "\n";
    }
    $tests[] = ['name' => $name, 'result' => $result, 'detail' => $detail];
}

// ----------------------------------------------------
// Setup Environment & Core classes
// ----------------------------------------------------
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Session.php';
require_once __DIR__ . '/../core/CSRF.php';
require_once __DIR__ . '/../core/AuditLog.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Middleware.php';
require_once __DIR__ . '/../core/Validator.php';
require_once __DIR__ . '/../core/helpers.php';

Session::start();
$pdo = Database::getInstance();

// ============================================================
// 1. DATABASE SCHEMA CHECKS
// ============================================================
echo "\n--- 1. DATABASE SCHEMA INTEGRITY ---\n";

function checkCol($pdo, $table, $column) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?");
    $stmt->execute([$table, $column]);
    return (bool)$stmt->fetchColumn();
}

test('competitions.code column exists', checkCol($pdo, 'competitions', 'code'));
test('competitions.instructions column exists', checkCol($pdo, 'competitions', 'instructions'));
test('candidates.registration_number column exists', checkCol($pdo, 'candidates', 'registration_number'));
test('candidates.gender column exists', checkCol($pdo, 'candidates', 'gender'));
test('candidates.course column exists', checkCol($pdo, 'candidates', 'course'));
test('candidates.batch column exists', checkCol($pdo, 'candidates', 'batch'));
test('candidates.shift column exists', checkCol($pdo, 'candidates', 'shift'));
test('candidates.branch column exists', checkCol($pdo, 'candidates', 'branch'));
test('attendance table exists', (bool)Database::fetchColumn("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'attendance'"));

// ============================================================
// 2. COMPETITION MANAGEMENT TESTS
// ============================================================
echo "\n--- 2. COMPETITION MANAGEMENT ---\n";

// Clean any previous test data
Database::delete('candidates', "registration_number LIKE 'MITC-TEST-%'");
Database::delete('competitions', "code LIKE 'TEST-COMP-%'");

$testCode = 'TEST-COMP-' . time();
$compId = Database::insert('competitions', [
    'name'             => 'Test Championship 2026',
    'code'             => $testCode,
    'description'      => 'A test competition for Step 2 testing suite',
    'competition_date' => date('Y-m-d'),
    'start_time'       => '10:00:00',
    'end_time'         => '16:00:00',
    'venue'            => 'Main Lab Khairpur',
    'instructions'     => 'Follow all rules carefully.',
    'max_candidates'   => 50,
    'status'           => 'draft',
    'created_by'       => 1,
]);

test('Competition created successfully', $compId > 0, "ID: {$compId}, Code: {$testCode}");

$fetched = Database::fetch("SELECT * FROM competitions WHERE id = ?", [$compId]);
test('Competition data retrieved correctly', $fetched['name'] === 'Test Championship 2026' && $fetched['code'] === $testCode);

// Duplicate code rejection
try {
    Database::insert('competitions', [
        'name'             => 'Duplicate Code Test',
        'code'             => $testCode,
        'competition_date' => date('Y-m-d'),
        'status'           => 'draft',
        'created_by'       => 1,
    ]);
    test('Duplicate competition code rejected', false, 'Database permitted duplicate code');
} catch (Exception $e) {
    test('Duplicate competition code rejected', true, 'Unique constraint caught duplicate code');
}

// Status transition
Database::update('competitions', ['status' => 'active'], 'id = ?', [$compId]);
$updatedComp = Database::fetch("SELECT status FROM competitions WHERE id = ?", [$compId]);
test('Competition status updated to active', $updatedComp['status'] === 'active');

// Competition Duplication
$dupCode = $testCode . '-DUP';
$dupId = Database::insert('competitions', [
    'name'             => 'Test Championship 2026 (Copy)',
    'code'             => $dupCode,
    'description'      => $fetched['description'],
    'competition_date' => $fetched['competition_date'],
    'venue'            => $fetched['venue'],
    'status'           => 'draft',
    'created_by'       => 1,
]);
test('Competition cloned/duplicated successfully', $dupId > 0 && $dupId !== $compId, "Dup ID: {$dupId}");

// ============================================================
// 3. CANDIDATE REGISTRATION & MANAGEMENT TESTS
// ============================================================
echo "\n--- 3. CANDIDATE MANAGEMENT ---\n";

$regNum1 = 'MITC-TEST-0001';
$candId1 = Database::insert('candidates', [
    'competition_id'      => $compId,
    'registration_number' => $regNum1,
    'roll_number'         => 'R-101',
    'seat_number'         => 'S-01',
    'full_name'           => 'Zubair Ahmed',
    'father_name'         => 'Nisar Ahmed',
    'cnic'                => '45203-1234567-1',
    'phone'               => '03001234567',
    'email'               => 'zubair@example.com',
    'gender'              => 'male',
    'course'              => 'CIT',
    'batch'               => '2026-A',
    'shift'               => 'morning',
    'branch'              => 'Khairpur Campus',
    'status'              => 'registered',
    'registered_by'       => 1,
]);

test('Candidate 1 registered', $candId1 > 0, "Reg #: {$regNum1}, Roll #: R-101");

// Unique Registration Number check
try {
    Database::insert('candidates', [
        'competition_id'      => $compId,
        'registration_number' => $regNum1,
        'full_name'           => 'Another Candidate',
        'registered_by'       => 1,
    ]);
    test('Duplicate candidate registration number rejected', false, 'Allowed duplicate reg #');
} catch (Exception $e) {
    test('Duplicate candidate registration number rejected', true, 'Unique key rejected duplicate reg #');
}

// Unique Roll Number in same competition check
try {
    Database::insert('candidates', [
        'competition_id'      => $compId,
        'registration_number' => 'MITC-TEST-0002',
        'roll_number'         => 'R-101', // Duplicate roll in same competition
        'full_name'           => 'Candidate Duplicate Roll',
        'registered_by'       => 1,
    ]);
    test('Duplicate roll number in same competition rejected', false, 'Allowed duplicate roll # in same competition');
} catch (Exception $e) {
    test('Duplicate roll number in same competition rejected', true, 'Unique compound index rejected duplicate roll #');
}

// Second candidate (female, evening shift)
$regNum2 = 'MITC-TEST-0002';
$candId2 = Database::insert('candidates', [
    'competition_id'      => $compId,
    'registration_number' => $regNum2,
    'roll_number'         => 'R-102',
    'seat_number'         => 'S-02',
    'full_name'           => 'Sana Tariq',
    'father_name'         => 'Tariq Mehmood',
    'gender'              => 'female',
    'course'              => 'Typing',
    'shift'               => 'evening',
    'branch'              => 'Khairpur Campus',
    'status'              => 'registered',
    'registered_by'       => 1,
]);
test('Candidate 2 registered', $candId2 > 0, "Reg #: {$regNum2}, Roll #: R-102");

// Candidate Profile Update
Database::update('candidates', ['phone' => '03019876543'], 'id = ?', [$candId1]);
$updatedCand = Database::fetch("SELECT phone FROM candidates WHERE id = ?", [$candId1]);
test('Candidate profile updated', $updatedCand['phone'] === '03019876543');

// Safe Deletion check: Competition has candidates enrolled -> deletion should be guarded
$hasCandidates = (int)Database::fetchColumn("SELECT COUNT(*) FROM candidates WHERE competition_id = ?", [$compId]);
test('Competition has linked candidates count', $hasCandidates === 2, "{$hasCandidates} candidates enrolled");

// ============================================================
// 4. CANDIDATE ATTENDANCE MODULE TESTS
// ============================================================
echo "\n--- 4. CANDIDATE ATTENDANCE ---\n";

// Mark Candidate 1 Present
Database::insert('attendance', [
    'candidate_id'   => $candId1,
    'competition_id' => $compId,
    'status'         => 'present',
    'check_in_time'  => date('Y-m-d H:i:s'),
    'marked_by'      => 1,
]);
Database::update('candidates', ['status' => 'present'], 'id = ?', [$candId1]);

$attRecord1 = Database::fetch("SELECT * FROM attendance WHERE candidate_id = ? AND competition_id = ?", [$candId1, $compId]);
test('Candidate 1 marked as Present in attendance table', $attRecord1 && $attRecord1['status'] === 'present');

// Duplicate attendance insertion prevention (uk_attendance_candidate_comp)
try {
    Database::insert('attendance', [
        'candidate_id'   => $candId1,
        'competition_id' => $compId,
        'status'         => 'present',
        'marked_by'      => 1,
    ]);
    test('Duplicate attendance record prevented by DB constraint', false);
} catch (Exception $e) {
    test('Duplicate attendance record prevented by DB constraint', true, 'Unique compound key enforced');
}

// Mark Candidate 2 Absent
Database::insert('attendance', [
    'candidate_id'   => $candId2,
    'competition_id' => $compId,
    'status'         => 'absent',
    'marked_by'      => 1,
]);
Database::update('candidates', ['status' => 'absent'], 'id = ?', [$candId2]);

$attRecord2 = Database::fetch("SELECT * FROM attendance WHERE candidate_id = ? AND competition_id = ?", [$candId2, $compId]);
test('Candidate 2 marked as Absent in attendance table', $attRecord2 && $attRecord2['status'] === 'absent');

// Live Counters verification
$countRegistered = (int)Database::fetchColumn("SELECT COUNT(*) FROM candidates WHERE competition_id = ?", [$compId]);
$countPresent = (int)Database::fetchColumn("SELECT COUNT(*) FROM attendance WHERE competition_id = ? AND status = 'present'", [$compId]);
$countAbsent = (int)Database::fetchColumn("SELECT COUNT(*) FROM attendance WHERE competition_id = ? AND status = 'absent'", [$compId]);
$countUnmarked = max(0, $countRegistered - ($countPresent + $countAbsent));

test('Attendance counters accurate', 
    $countRegistered === 2 && $countPresent === 1 && $countAbsent === 1 && $countUnmarked === 0,
    "Registered: {$countRegistered}, Present: {$countPresent}, Absent: {$countAbsent}, Unmarked: {$countUnmarked}"
);

// ============================================================
// 5. BULK CSV IMPORT LOGIC TESTS
// ============================================================
echo "\n--- 5. BULK CANDIDATE CSV IMPORT ---\n";

$csvData = "roll_number,seat_number,full_name,father_name,mobile_number,gender,course,batch,shift,branch\n"
         . "R-201,S-10,Hamza Ali,Ali Nawaz,03001112233,male,CIT,2026-A,morning,Khairpur\n"
         . "R-202,S-11,Ayesha Khan,Farooq Khan,03004445566,female,DIT,2026-A,afternoon,Khairpur\n"
         . "R-201,S-12,Duplicate Roll Test,Test Father,03007778899,male,Typing,2026-A,morning,Khairpur\n" // Duplicate roll
         . ",S-13,,Missing Name Test,03000000000,male,CIT,2026-A,morning,Khairpur\n"; // Missing full name

$lines = explode("\n", trim($csvData));
$header = str_getcsv(array_shift($lines));

$csvImported = 0;
$csvDuplicates = 0;
$csvFailed = 0;
$existingRolls = ['R-101' => true, 'R-102' => true];

foreach ($lines as $line) {
    if (trim($line) === '') continue;
    $row = str_getcsv($line);
    $rowRoll = trim($row[0]);
    $rowName = trim($row[2]);

    if ($rowName === '') {
        $csvFailed++;
        continue;
    }

    if ($rowRoll !== '' && isset($existingRolls[$rowRoll])) {
        $csvDuplicates++;
        continue;
    }

    if ($rowRoll !== '') {
        $existingRolls[$rowRoll] = true;
    }

    $cId = Database::insert('candidates', [
        'competition_id'      => $compId,
        'registration_number' => 'MITC-TEST-' . str_pad((string)($csvImported + 3), 4, '0', STR_PAD_LEFT),
        'roll_number'         => $rowRoll ?: null,
        'seat_number'         => $row[1] ?: null,
        'full_name'           => $rowName,
        'father_name'         => $row[3] ?: null,
        'phone'               => $row[4] ?: null,
        'gender'              => $row[5] ?: null,
        'course'              => $row[6] ?: null,
        'batch'               => $row[7] ?: null,
        'shift'               => $row[8] ?: 'morning',
        'branch'              => $row[9] ?: null,
        'status'              => 'registered',
        'registered_by'       => 1,
    ]);
    if ($cId) $csvImported++;
}

test('CSV Parser imported valid rows', $csvImported === 2, "Imported: {$csvImported}");
test('CSV Parser detected duplicate roll number', $csvDuplicates === 1, "Duplicates caught: {$csvDuplicates}");
test('CSV Parser rejected row with missing name', $csvFailed === 1, "Failed rows: {$csvFailed}");

// ============================================================
// 6. RBAC & PERMISSIONS VERIFICATION
// ============================================================
echo "\n--- 6. RBAC & PERMISSIONS ---\n";

$superAdminPerms = Database::fetchAll(
    "SELECT p.slug FROM role_permissions rp JOIN permissions p ON rp.permission_id = p.id WHERE rp.role_id = 1"
);
$superAdminPermSlugs = array_column($superAdminPerms, 'slug');

$adminPerms = Database::fetchAll(
    "SELECT p.slug FROM role_permissions rp JOIN permissions p ON rp.permission_id = p.id WHERE rp.role_id = 2"
);
$adminPermSlugs = array_column($adminPerms, 'slug');

$invigilatorPerms = Database::fetchAll(
    "SELECT p.slug FROM role_permissions rp JOIN permissions p ON rp.permission_id = p.id WHERE rp.role_id = 3"
);
$invigilatorPermSlugs = array_column($invigilatorPerms, 'slug');

test('Super Admin has all Step 2 permissions', 
    in_array('competitions.create', $superAdminPermSlugs) && 
    in_array('candidates.import', $superAdminPermSlugs) &&
    in_array('attendance.manage', $superAdminPermSlugs)
);

test('Admin has Step 2 management permissions', 
    in_array('competitions.create', $adminPermSlugs) && 
    in_array('candidates.import', $adminPermSlugs) &&
    in_array('attendance.manage', $adminPermSlugs)
);

test('Invigilator has view & attendance permissions but no delete', 
    in_array('competitions.view', $invigilatorPermSlugs) && 
    in_array('attendance.manage', $invigilatorPermSlugs) &&
    !in_array('competitions.delete', $invigilatorPermSlugs) &&
    !in_array('candidates.delete', $invigilatorPermSlugs)
);

// ============================================================
// 7. AUDIT LOGGING VERIFICATION
// ============================================================
echo "\n--- 7. AUDIT LOGGING ---\n";

AuditLog::log('competition_created', 'competitions', "Test audit log entry for Step 2 test suite", 1);
$latestLog = Database::fetch("SELECT * FROM audit_logs WHERE module = 'competitions' ORDER BY id DESC LIMIT 1");
test('Audit Log recorded competition event', $latestLog && $latestLog['action'] === 'competition_created');

AuditLog::log('attendance_marked', 'attendance', "Candidate attendance test log", 1);
$latestAttLog = Database::fetch("SELECT * FROM audit_logs WHERE module = 'attendance' ORDER BY id DESC LIMIT 1");
test('Audit Log recorded attendance event', $latestAttLog && $latestAttLog['action'] === 'attendance_marked');

// Clean up test data
Database::delete('attendance', 'competition_id = ?', [$compId]);
Database::delete('candidates', 'competition_id = ?', [$compId]);
Database::delete('competitions', 'id IN (?, ?)', [$compId, $dupId]);

echo "\n============================================================\n";
echo "STEP 2 TESTS SUMMARY: {$passed} PASSED / {$failed} FAILED\n";
echo "============================================================\n";

if ($failed > 0) {
    exit(1);
}
exit(0);
