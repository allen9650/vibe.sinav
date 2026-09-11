<?php
/**
 * Bulk Certificate Generator View
 */

Middleware::requirePermission('certificates.generate');

$competitions = Database::fetchAll("SELECT id, name, code, status, results_finalized_at FROM competitions ORDER BY id DESC");
$selectedCompId = (int)($_GET['competition_id'] ?? 0);
if ($selectedCompId === 0 && !empty($competitions)) {
    $selectedCompId = (int)$competitions[0]['id'];
}

$competition = Database::fetch("SELECT * FROM competitions WHERE id = ?", [$selectedCompId]);
$mode = trim($_POST['mode'] ?? ($_GET['mode'] ?? 'all_qualified'));
$minWpm = (float)($_POST['min_wpm'] ?? ($_GET['min_wpm'] ?? 30.0));
$minAcc = (float)($_POST['min_accuracy'] ?? ($_GET['min_accuracy'] ?? 90.0));
$templateKey = trim($_POST['template_key'] ?? ($_GET['template_key'] ?? 'classic'));
$showEligibleOnly = (bool)($_GET['eligible_only'] ?? 0);

$error = '';
$success = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'generate') {
    CSRF::validateOrFail();

    try {
        $customCriteria = [
            'min_wpm'      => $minWpm,
            'min_accuracy' => $minAcc,
        ];
        $res = CertificateService::bulkGenerate($selectedCompId, $mode, $customCriteria, Session::get('user_id'), $templateKey);
        Session::flash('success', "Bulk generation complete! Generated: {$res['generated']} new certificate(s), Skipped (already generated): {$res['skipped']}.");
        redirectTo('certificates', ['competition_id' => $selectedCompId]);
    } catch (Exception $e) {
        $error = "Bulk generation failed: " . $e->getMessage();
    }
}

// Compute comprehensive preview data
$candidates = $selectedCompId > 0 ? Database::fetchAll("SELECT * FROM candidates WHERE competition_id = ? ORDER BY id ASC", [$selectedCompId]) : [];
$rankedList = $selectedCompId > 0 ? RankingService::getLeaderboard($selectedCompId, false) : [];
$rankedMap = [];
foreach ($rankedList as $r) {
    $rankedMap[(int)$r['candidate_id']] = $r;
}

// Map candidate attempts and check for missing results
$attempts = $selectedCompId > 0 ? Database::fetchAll("
    SELECT a.*, r.id AS result_id 
    FROM test_attempts a 
    LEFT JOIN test_results r ON a.id = r.attempt_id 
    WHERE a.competition_id = ? 
    ORDER BY a.candidate_id, a.attempt_number DESC, a.id DESC
", [$selectedCompId]) : [];

$candidateAttemptsMap = [];
foreach ($attempts as $att) {
    $candidateAttemptsMap[(int)$att['candidate_id']][] = $att;
}

// Map already issued certificates
$issuedCerts = $selectedCompId > 0 ? Database::fetchAll("
    SELECT candidate_id, certificate_type, certificate_number, id 
    FROM certificates 
    WHERE competition_id = ?
", [$selectedCompId]) : [];

$issuedMap = [];
foreach ($issuedCerts as $ic) {
    $issuedMap[(int)$ic['candidate_id']][$ic['certificate_type']] = $ic;
}

$previewRows = [];
$readyToIssueCount = 0;
$alreadyIssuedCount = 0;
$eligibleCount = 0;

foreach ($candidates as $cand) {
    $cId = (int)$cand['id'];
    $isCandDisqualified = ($cand['status'] === 'disqualified');
    $hasRankedResult = isset($rankedMap[$cId]);
    $candAttempts = $candidateAttemptsMap[$cId] ?? [];

    $row = [
        'candidate_id'         => $cId,
        'candidate_name'       => $cand['full_name'],
        'roll_number'          => $cand['roll_number'] ?: $cand['registration_number'],
        'attempt_number'       => '—',
        'net_wpm'              => '—',
        'accuracy'             => '—',
        'score'                => '—',
        'qualification_status' => strtoupper($cand['status']),
        'cert_type'            => '—',
        'cert_type_code'       => 'participation',
        'status'               => 'NOT ELIGIBLE',
        'is_eligible'          => false,
        'already_issued'       => false,
        'rank'                 => null,
    ];

    if ($isCandDisqualified) {
        $row['status'] = 'DISQUALIFIED';
        $row['qualification_status'] = 'DISQUALIFIED';
    } elseif ($hasRankedResult) {
        $res = $rankedMap[$cId];
        $netWpm = (float)$res['net_wpm'];
        $accuracy = (float)$res['accuracy'];
        $score = (float)$res['score'];
        $rank = (int)$res['rank'];
        $qualStatus = $res['qualification_status'];

        $row['attempt_number'] = '#' . $res['attempt_number'];
        $row['net_wpm'] = number_format($netWpm, 2);
        $row['accuracy'] = number_format($accuracy, 2) . '%';
        $row['score'] = number_format($score, 2);
        $row['qualification_status'] = strtoupper(str_replace('_', ' ', $qualStatus));
        $row['rank'] = $rank;

        // Evaluate eligibility based on mode
        $isEligible = false;
        $certTypeCode = 'participation';

        switch ($mode) {
            case 'top_3':
                if ($rank <= 3) {
                    $isEligible = true;
                    $certTypeCode = 'position';
                }
                break;
            case 'top_10':
                if ($rank <= 10) {
                    $isEligible = true;
                    $certTypeCode = ($rank <= 3 && !empty($competition['results_finalized_at'])) ? 'position' : 'participation';
                }
                break;
            case 'all_completed':
                $isEligible = true;
                $certTypeCode = ($rank <= 3 && !empty($competition['results_finalized_at'])) ? 'position' : 'participation';
                break;
            case 'all_qualified':
                if ($qualStatus === 'qualified') {
                    $isEligible = true;
                    $certTypeCode = ($rank <= 3 && !empty($competition['results_finalized_at'])) ? 'position' : 'participation';
                }
                break;
            case 'custom':
                if ($netWpm >= $minWpm && $accuracy >= $minAcc) {
                    $isEligible = true;
                    $certTypeCode = ($rank <= 3 && !empty($competition['results_finalized_at'])) ? 'position' : 'participation';
                }
                break;
        }

        $row['cert_type_code'] = $certTypeCode;
        $row['cert_type'] = ($certTypeCode === 'position') ? 'ACHIEVEMENT' : 'PARTICIPATION';
        $row['is_eligible'] = $isEligible;

        if ($isEligible) {
            $eligibleCount++;
            $alreadyIssued = isset($issuedMap[$cId][$certTypeCode]);
            $row['already_issued'] = $alreadyIssued;

            if ($alreadyIssued) {
                $row['status'] = 'ALREADY GENERATED';
                $alreadyIssuedCount++;
            } else {
                $row['status'] = 'READY TO GENERATE';
                $readyToIssueCount++;
            }
        } else {
            $row['status'] = 'NOT ELIGIBLE';
        }
    } else {
        // Check if candidate completed an attempt but result is missing
        $hasCompletedAttemptNoResult = false;
        foreach ($candAttempts as $att) {
            if (in_array($att['status'], ['completed', 'timed_out', 'submitted'], true) && empty($att['result_id'])) {
                $hasCompletedAttemptNoResult = true;
                $row['attempt_number'] = '#' . $att['attempt_number'];
                break;
            }
        }

        if ($hasCompletedAttemptNoResult) {
            $row['status'] = 'NO FINAL RESULT';
            $row['qualification_status'] = 'NO FINAL RESULT';
        } else {
            $row['status'] = 'NO FINAL RESULT';
        }
    }

    $previewRows[] = $row;
}

$displayRows = $previewRows;
if ($showEligibleOnly) {
    $displayRows = array_filter($previewRows, fn($r) => $r['is_eligible']);
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1"><i class="fas fa-magic text-warning me-2"></i> Bulk Certificate Generator</h4>
        <div class="text-muted small">Generate certificates for winners and qualifying candidates based on customizable policies</div>
    </div>
    <div>
        <a href="<?= url('certificates') ?>&competition_id=<?= $selectedCompId ?>" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-arrow-left me-1"></i> Back to Certificates
        </a>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger py-2 px-3 mb-4 small">
        <i class="fas fa-exclamation-circle me-2"></i> <?= e($error) ?>
    </div>
<?php endif; ?>

<div class="row g-4">
    <!-- Generator Config Form -->
    <div class="col-lg-5">
        <div class="card bg-dark border-secondary">
            <div class="card-header border-secondary fw-bold text-light py-3">
                <i class="fas fa-cog me-2 text-primary"></i> Generation Policy
            </div>
            <div class="card-body p-4">
                <form method="GET" action="<?= url('certificates-generate') ?>" id="previewForm">
                    <input type="hidden" name="page" value="certificates-generate">

                    <div class="mb-3">
                        <label class="form-label text-light small fw-bold">Select Competition</label>
                        <select name="competition_id" class="form-select bg-dark text-light border-secondary" onchange="document.getElementById('previewForm').submit()">
                            <?php foreach ($competitions as $cmp): ?>
                                <option value="<?= $cmp['id'] ?>" <?= $selectedCompId === (int)$cmp['id'] ? 'selected' : '' ?>>
                                    <?= e($cmp['name']) ?> (<?= e($cmp['code']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label text-light small fw-bold">Eligibility Mode</label>
                        <select name="mode" class="form-select bg-dark text-light border-secondary" onchange="document.getElementById('previewForm').submit()">
                            <option value="top_3" <?= $mode === 'top_3' ? 'selected' : '' ?>>Mode A: Top 3 Winners Only (Position)</option>
                            <option value="top_10" <?= $mode === 'top_10' ? 'selected' : '' ?>>Mode B: Top 10 Candidates</option>
                            <option value="all_qualified" <?= $mode === 'all_qualified' ? 'selected' : '' ?>>Mode D: All Qualified Candidates</option>
                            <option value="all_completed" <?= $mode === 'all_completed' ? 'selected' : '' ?>>Mode C: All Completed Tests (Includes Participation)</option>
                            <option value="custom" <?= $mode === 'custom' ? 'selected' : '' ?>>Mode E: Custom Minimum Requirements</option>
                        </select>
                    </div>

                    <?php if ($mode === 'custom'): ?>
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label text-muted small">Min Net WPM</label>
                                <input type="number" step="0.1" name="min_wpm" class="form-control form-control-sm bg-dark text-light border-secondary" value="<?= $minWpm ?>" onchange="document.getElementById('previewForm').submit()">
                            </div>
                            <div class="col-6">
                                <label class="form-label text-muted small">Min Accuracy %</label>
                                <input type="number" step="0.1" name="min_accuracy" class="form-control form-control-sm bg-dark text-light border-secondary" value="<?= $minAcc ?>" onchange="document.getElementById('previewForm').submit()">
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="eligibleOnlySwitch" name="eligible_only" value="1" 
                               <?= $showEligibleOnly ? 'checked' : '' ?> onchange="document.getElementById('previewForm').submit()">
                        <label class="form-check-label text-light small" for="eligibleOnlySwitch">
                            Filter Preview: Show Eligible Candidates Only
                        </label>
                    </div>
                </form>

                <hr class="border-secondary my-4">

                <!-- Summary Checklist Card -->
                <div class="p-3 bg-dark rounded border border-secondary mb-4">
                    <div class="row g-2 text-center">
                        <div class="col-4">
                            <span class="small text-muted d-block">Eligible</span>
                            <strong class="fs-4 text-info font-monospace"><?= $eligibleCount ?></strong>
                        </div>
                        <div class="col-4">
                            <span class="small text-muted d-block">Already Issued</span>
                            <strong class="fs-4 text-secondary font-monospace"><?= $alreadyIssuedCount ?></strong>
                        </div>
                        <div class="col-4">
                            <span class="small text-muted d-block">Ready to Issue</span>
                            <strong class="fs-4 text-warning font-monospace"><?= $readyToIssueCount ?></strong>
                        </div>
                    </div>
                </div>

                <!-- Certificate Generation Action Form -->
                <form method="POST" action="<?= url('certificates-generate') ?>&competition_id=<?= $selectedCompId ?>">
                    <?= CSRF::field() ?>
                    <input type="hidden" name="action" value="generate">
                    <input type="hidden" name="mode" value="<?= e($mode) ?>">
                    <input type="hidden" name="min_wpm" value="<?= $minWpm ?>">
                    <input type="hidden" name="min_accuracy" value="<?= $minAcc ?>">

                    <div class="mb-3">
                        <label class="form-label text-light small fw-bold">Certificate Design Template</label>
                        <select name="template_key" class="form-select bg-dark text-light border-secondary">
                            <option value="classic" <?= $templateKey === 'classic' ? 'selected' : '' ?>>1. Classic Professional (Formal Ivory & Navy)</option>
                            <option value="modern" <?= $templateKey === 'modern' ? 'selected' : '' ?>>2. Modern Premium (Geometric Slate & Blue)</option>
                            <option value="academic" <?= $templateKey === 'academic' ? 'selected' : '' ?>>3. Academic Excellence (Burgundy & Gold Crest)</option>
                            <option value="champion" <?= $templateKey === 'champion' ? 'selected' : '' ?>>4. Typing Champion (Gold Winner Trophy)</option>
                        </select>
                        <div class="form-text text-muted small">Design can also be changed individually at any time on preview without altering certificate records.</div>
                    </div>

                    <button type="submit" class="btn btn-warning btn-lg fw-bold w-100 py-3" <?= $readyToIssueCount === 0 ? 'disabled' : '' ?>>
                        <i class="fas fa-certificate me-2"></i> Generate <?= $readyToIssueCount ?> Certificate(s)
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Candidate Preview Table -->
    <div class="col-lg-7">
        <div class="card bg-dark border-secondary">
            <div class="card-header border-secondary d-flex justify-content-between align-items-center">
                <span class="fw-bold text-light"><i class="fas fa-users me-2"></i> Candidate Eligibility Preview</span>
                <span class="badge bg-secondary"><?= count($displayRows) ?> Candidates</span>
            </div>
            <div class="table-responsive">
                <table class="table table-dark table-hover table-bordered mb-0 align-middle text-center small">
                    <thead class="table-secondary text-light text-uppercase">
                        <tr>
                            <th class="text-start">Candidate</th>
                            <th>Roll #</th>
                            <th>Attempt #</th>
                            <th>Net WPM</th>
                            <th>Accuracy</th>
                            <th>Final Score</th>
                            <th>Qualification</th>
                            <th>Certificate Type</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($displayRows)): ?>
                            <tr>
                                <td colspan="9" class="text-center py-4 text-muted">
                                    No candidates meet the selected criteria.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($displayRows as $row): ?>
                                <?php
                                $statusBadgeClass = match($row['status']) {
                                    'READY TO GENERATE' => 'bg-success',
                                    'ALREADY GENERATED' => 'bg-secondary',
                                    'DISQUALIFIED'      => 'bg-danger',
                                    'NO FINAL RESULT'   => 'bg-warning text-dark',
                                    default             => 'bg-dark border border-secondary text-muted',
                                };

                                $qualBadgeClass = match($row['qualification_status']) {
                                    'QUALIFIED'       => 'text-success fw-bold',
                                    'DISQUALIFIED'    => 'text-danger fw-bold',
                                    'NO FINAL RESULT' => 'text-warning fw-bold',
                                    default           => 'text-muted',
                                };
                                ?>
                                <tr>
                                    <td class="text-start fw-bold text-light">
                                        <?= e($row['candidate_name']) ?>
                                        <?php if (!empty($row['rank'])): ?>
                                            <span class="badge bg-dark border border-secondary text-muted small ms-1">#<?= $row['rank'] ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="font-monospace text-primary"><?= e($row['roll_number']) ?></td>
                                    <td class="font-monospace text-muted small"><?= e($row['attempt_number']) ?></td>
                                    <td class="font-monospace text-primary fw-bold"><?= $row['net_wpm'] ?></td>
                                    <td class="font-monospace text-success"><?= $row['accuracy'] ?></td>
                                    <td class="font-monospace text-warning"><?= $row['score'] ?></td>
                                    <td class="small <?= $qualBadgeClass ?>"><?= e($row['qualification_status']) ?></td>
                                    <td>
                                        <?php if ($row['cert_type'] === 'ACHIEVEMENT'): ?>
                                            <span class="badge bg-warning text-dark">ACHIEVEMENT</span>
                                        <?php elseif ($row['cert_type'] === 'PARTICIPATION'): ?>
                                            <span class="badge bg-info text-dark">PARTICIPATION</span>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge <?= $statusBadgeClass ?>"><?= $row['status'] ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
