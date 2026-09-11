<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Session.php';
require_once __DIR__ . '/../core/CSRF.php';
require_once __DIR__ . '/../core/AuditLog.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Middleware.php';
require_once __DIR__ . '/../core/Validator.php';
require_once __DIR__ . '/../core/TypingCalculator.php';
require_once __DIR__ . '/../core/RankingService.php';
require_once __DIR__ . '/../core/ResultService.php';
require_once __DIR__ . '/../core/ReportService.php';
require_once __DIR__ . '/../core/CertificateService.php';
require_once __DIR__ . '/../core/BackupService.php';
require_once __DIR__ . '/../core/helpers.php';

Session::start();
Session::set('user_id', 1);
Session::set('username', 'admin');
Session::set('full_name', 'System Administrator');
Session::set('role_id', 1);
Session::set('role_slug', 'super-admin');
Session::set('role_name', 'Super Administrator');
Session::set('candidate_logged_in', true);
Session::set('candidate_id', 1);
Session::set('candidate_comp_id', 1);
Session::set('current_attempt_id', 1);

$routes = require __DIR__ . '/../routes/web.php';
$pagesToTest = [
    'dashboard',
    'competitions',
    'competitions-create',
    'competitions-view',
    'competitions-paragraphs',
    'competitions-settings',
    'candidates',
    'candidates-create',
    'candidates-import',
    'candidates-print',
    'attendance',
    'paragraphs',
    'paragraphs-create',
    'security-events',
    'live-tests',
    'leaderboard',
    'competitions-finalize',
    'results',
    'results-view',
    'results-print',
    'results-slip',
    'reports',
    'certificates',
    'certificates-generate',
    'certificates-view',
    'test-portal',
    'test-completed',
    'test-result',
    'test-disqualified',
    'profile',
    'settings',
    'backup',
    'system-health',
    'audit-logs',
];

echo "Testing view rendering for Step 2 routes:\n";
$allOk = true;
$latestResult = null;

foreach ($pagesToTest as $pageSlug) {
    $_GET['page'] = $pageSlug;
    if (!$latestResult) {
        $cand = Database::fetch("SELECT * FROM candidates ORDER BY id DESC LIMIT 1");
        $p = Database::fetch("SELECT * FROM typing_paragraphs WHERE status = 'active' LIMIT 1");
        if ($cand && $p) {
            $attId = Database::insert('test_attempts', [
                'candidate_id'           => (int)$cand['id'],
                'competition_id'         => (int)$cand['competition_id'],
                'paragraph_id'           => (int)$p['id'],
                'original_text_snapshot' => $p['content'],
                'attempt_number'         => 1,
                'started_at'             => date('Y-m-d H:i:s', time() - 120),
                'expected_end_at'        => date('Y-m-d H:i:s', time() + 180),
                'finished_at'            => date('Y-m-d H:i:s'),
                'submitted_at'           => date('Y-m-d H:i:s'),
                'duration_seconds'       => 300,
                'time_taken_seconds'     => 120,
                'status'                 => 'completed',
            ]);
            $resId = Database::insert('test_results', [
                'attempt_id'             => $attId,
                'candidate_id'           => (int)$cand['id'],
                'competition_id'         => (int)$cand['competition_id'],
                'paragraph_id'           => (int)$p['id'],
                'original_text_snapshot' => $p['content'],
                'final_typed_text'       => $p['content'],
                'gross_wpm'              => 50.0,
                'net_wpm'                => 48.0,
                'accuracy'               => 96.0,
                'total_characters'       => 500,
                'correct_characters'     => 480,
                'incorrect_characters'   => 10,
                'missing_characters'     => 10,
                'extra_characters'       => 0,
                'error_count'            => 20,
                'errors_per_minute'      => 10.0,
                'score'                  => 46.08,
                'time_taken_seconds'     => 120,
                'qualification_status'   => 'qualified',
                'scoring_version'        => '1.0',
            ]);
            $latestResult = Database::fetch("SELECT r.*, a.candidate_id, a.competition_id FROM test_results r JOIN test_attempts a ON r.attempt_id = a.id WHERE r.id = ?", [$resId]);
        }
    }
    if ($latestResult) {
        Session::set('candidate_comp_id', (int)$latestResult['competition_id']);
        Session::set('candidate_id', (int)$latestResult['candidate_id']);
        Session::set('current_attempt_id', (int)$latestResult['attempt_id']);
        $_GET['competition_id'] = (int)$latestResult['competition_id'];
        if ($pageSlug === 'results-view' || $pageSlug === 'results-print' || $pageSlug === 'results-slip') {
            $_GET['id'] = (int)$latestResult['id'];
        } elseif ($pageSlug === 'certificates-view') {
            $latestCert = Database::fetch("SELECT id FROM certificates ORDER BY id DESC LIMIT 1");
            if (!$latestCert) {
                $cId = Database::insert('certificates', [
                    'certificate_number' => 'MITC-CERT-DEMO-001',
                    'competition_id'     => (int)$latestResult['competition_id'],
                    'candidate_id'       => (int)$latestResult['candidate_id'],
                    'result_id'          => (int)$latestResult['id'],
                    'certificate_type'   => 'participation',
                    'position'           => 'Participant',
                    'net_wpm_snapshot'   => 45.0,
                    'accuracy_snapshot'  => 95.0,
                    'score_snapshot'     => 42.75,
                ]);
                $_GET['id'] = $cId;
            } else {
                $_GET['id'] = (int)$latestCert['id'];
            }
        } else {
            $_GET['id'] = (int)$latestResult['competition_id'];
        }
    }
    if (!isset($routes[$pageSlug])) {
        echo "[FAIL] Missing route: $pageSlug\n";
        $allOk = false;
        continue;
    }
    $route = $routes[$pageSlug];
    $viewFile = VIEWS_PATH . '/' . $route['view'];
    if (!file_exists($viewFile)) {
        echo "[FAIL] Missing view file: $viewFile\n";
        $allOk = false;
        continue;
    }
    ob_start();
    try {
        $pageTitle = $route['title'];
        require $viewFile;
        $out = ob_get_clean();
        echo "[PASS] Rendered page={$pageSlug} (" . strlen($out) . " bytes)\n";
    } catch (Throwable $e) {
        ob_end_clean();
        echo "[FAIL] Exception in {$pageSlug}: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine() . "\n";
        $allOk = false;
    }
}

if ($allOk) {
    echo "\nAll Step 2 views rendered cleanly without errors!\n";
    exit(0);
} else {
    echo "\nSome views failed rendering!\n";
    exit(1);
}
