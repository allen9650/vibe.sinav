<?php
/**
 * Admin Result Breakdown & Diff Detail View
 */

Middleware::requirePermission('results.view');

$resultId = (int)($_GET['id'] ?? 0);
$result = Database::fetch("
    SELECT 
        r.*,
        c.full_name AS candidate_name,
        c.roll_number,
        c.registration_number,
        c.course,
        c.shift,
        c.batch,
        cmp.name AS competition_name,
        cmp.code AS competition_code,
        a.attempt_number,
        a.started_at,
        a.finished_at,
        a.submitted_at,
        a.duration_seconds AS configured_duration,
        a.status AS attempt_status,
        (SELECT COUNT(*) FROM security_events se WHERE se.attempt_id = r.attempt_id) AS violation_count
    FROM test_results r
    JOIN candidates c ON r.candidate_id = c.id
    JOIN competitions cmp ON r.competition_id = cmp.id
    JOIN test_attempts a ON r.attempt_id = a.id
    WHERE r.id = ?
", [$resultId]);

if (!$result) {
    Session::flash('error', 'Result record not found.');
    redirectTo('results');
}

$diffAlignment = TypingCalculator::alignCharacters($result['original_text_snapshot'] ?? '', $result['final_typed_text'] ?? '');
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1"><i class="fas fa-file-invoice text-primary me-2"></i> Test Result Detail & Telemetry</h4>
        <div class="text-muted small">Comprehensive scoring audit, keystroke diff, and attempt verification</div>
    </div>
    <div>
        <a href="<?= url('results') ?>" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-arrow-left me-1"></i> Back to Results
        </a>
    </div>
</div>

<!-- Candidate Summary Card -->
<div class="card bg-dark border-secondary mb-4">
    <div class="card-body p-4">
        <div class="row g-3">
            <div class="col-sm-6 col-md-3">
                <span class="text-muted small d-block">Candidate</span>
                <strong class="fs-6 text-light"><?= e($result['candidate_name']) ?></strong>
            </div>
            <div class="col-sm-6 col-md-3">
                <span class="text-muted small d-block">Roll Number</span>
                <strong class="fs-6 font-monospace text-primary"><?= e($result['roll_number'] ?: '—') ?></strong>
            </div>
            <div class="col-sm-6 col-md-3">
                <span class="text-muted small d-block">Competition</span>
                <strong class="text-light"><?= e($result['competition_name']) ?> (<?= e($result['competition_code']) ?>)</strong>
            </div>
            <div class="col-sm-6 col-md-3">
                <span class="text-muted small d-block">Attempt / Status</span>
                <span class="badge bg-primary">Attempt #<?= $result['attempt_number'] ?></span>
                <?php if ($result['qualification_status'] === 'qualified'): ?>
                    <span class="badge bg-success ms-1">QUALIFIED</span>
                <?php else: ?>
                    <span class="badge bg-danger ms-1">NOT QUALIFIED</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- KPI Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card bg-dark border-secondary text-center p-3">
            <span class="small text-muted text-uppercase fw-bold">Net Speed</span>
            <div class="fs-2 fw-bold text-primary font-monospace"><?= number_format($result['net_wpm'], 2) ?></div>
            <span class="badge bg-dark border border-secondary text-muted small">WPM</span>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card bg-dark border-secondary text-center p-3">
            <span class="small text-muted text-uppercase fw-bold">Accuracy</span>
            <div class="fs-2 fw-bold text-success font-monospace"><?= number_format($result['accuracy'], 2) ?>%</div>
            <span class="badge bg-dark border border-secondary text-muted small"><?= (int)$result['correct_characters'] ?> / <?= (int)$result['total_characters'] ?> Chars</span>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card bg-dark border-secondary text-center p-3">
            <span class="small text-muted text-uppercase fw-bold">Gross Speed</span>
            <div class="fs-2 fw-bold text-info font-monospace"><?= number_format($result['gross_wpm'], 2) ?></div>
            <span class="badge bg-dark border border-secondary text-muted small">WPM</span>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card bg-dark border-secondary text-center p-3">
            <span class="small text-muted text-uppercase fw-bold">Final Score</span>
            <div class="fs-2 fw-bold text-warning font-monospace"><?= number_format($result['score'] ?? 0, 2) ?></div>
            <span class="badge bg-warning text-dark small fw-bold">Rank: <?= $result['rank'] ? '#' . $result['rank'] : 'Provisional' ?></span>
        </div>
    </div>
</div>

<!-- Tables & Detailed Breakdown -->
<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="card bg-dark border-secondary h-100">
            <div class="card-header border-secondary fw-bold text-light">
                <i class="fas fa-font me-2 text-primary"></i> Character & Keystroke Breakdown
            </div>
            <div class="card-body p-0">
                <table class="table table-dark table-borderless mb-0 small">
                    <tbody>
                        <tr>
                            <td class="text-muted">Total Characters Typed:</td>
                            <td class="text-end fw-bold font-monospace"><?= number_format($result['total_characters']) ?></td>
                        </tr>
                        <tr>
                            <td class="text-success"><i class="fas fa-check me-1"></i> Correct Characters:</td>
                            <td class="text-end text-success fw-bold font-monospace"><?= number_format($result['correct_characters']) ?></td>
                        </tr>
                        <tr>
                            <td class="text-danger"><i class="fas fa-times me-1"></i> Substitutions (Incorrect):</td>
                            <td class="text-end text-danger fw-bold font-monospace"><?= number_format($result['incorrect_characters']) ?></td>
                        </tr>
                        <tr>
                            <td class="text-warning"><i class="fas fa-minus me-1"></i> Deletions (Missing):</td>
                            <td class="text-end text-warning fw-bold font-monospace"><?= number_format($result['missing_characters']) ?></td>
                        </tr>
                        <tr>
                            <td class="text-info"><i class="fas fa-plus me-1"></i> Insertions (Extra):</td>
                            <td class="text-end text-info fw-bold font-monospace"><?= number_format($result['extra_characters']) ?></td>
                        </tr>
                        <tr class="border-top border-secondary">
                            <td class="fw-bold text-light">Total Errors:</td>
                            <td class="text-end fw-bold text-danger font-monospace"><?= number_format($result['error_count']) ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card bg-dark border-secondary h-100">
            <div class="card-header border-secondary fw-bold text-light">
                <i class="fas fa-stopwatch me-2 text-info"></i> Duration, Words & Integrity
            </div>
            <div class="card-body p-0">
                <table class="table table-dark table-borderless mb-0 small">
                    <tbody>
                        <tr>
                            <td class="text-muted">Scoring Duration:</td>
                            <td class="text-end fw-bold font-monospace"><?= (int)$result['time_taken_seconds'] ?>s (out of <?= (int)$result['configured_duration'] ?>s)</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Errors Per Minute:</td>
                            <td class="text-end text-warning fw-bold font-monospace"><?= number_format($result['errors_per_minute'] ?? 0, 2) ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Backspaces Recorded:</td>
                            <td class="text-end text-info fw-bold font-monospace"><?= (int)$result['backspace_count'] ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Words Typed (Correct / Total):</td>
                            <td class="text-end fw-bold font-monospace"><?= (int)$result['correct_words'] ?> / <?= (int)$result['total_words'] ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Security Violations:</td>
                            <td class="text-end fw-bold font-monospace">
                                <?php if ($result['violation_count'] > 0): ?>
                                    <span class="badge bg-danger"><?= $result['violation_count'] ?> violation(s)</span>
                                <?php else: ?>
                                    <span class="text-success"><i class="fas fa-shield-alt me-1"></i> Clean</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr class="border-top border-secondary">
                            <td class="fw-bold text-light">Scoring Engine Version:</td>
                            <td class="text-end text-muted font-monospace">v<?= e($result['scoring_version'] ?? '1.0') ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<style>
.typing-diff {
    background: #090d16;
    border: 1px solid #1f2937;
    border-radius: 8px;
    padding: 16px 20px;
    font-family: 'Courier New', Courier, monospace;
    font-size: 16px;
    white-space: pre-wrap;
    overflow-wrap: break-word;
    word-break: normal;
    line-height: 1.8;
    max-height: 320px;
    overflow-y: auto;
}
.typing-diff span {
    display: inline;
    margin: 0;
    padding: 0;
}
.typing-diff .diff-correct {
    color: #4ade80;
}
.typing-diff .diff-error {
    background: rgba(239, 68, 68, 0.25);
    color: #f87171;
    text-decoration: underline wavy #ef4444;
    border-radius: 2px;
}
.typing-diff .diff-missing {
    background: rgba(234, 179, 8, 0.2);
    color: #facc15;
    text-decoration: line-through;
    border-radius: 2px;
}
.typing-diff .diff-extra {
    background: rgba(59, 130, 246, 0.25);
    color: #60a5fa;
    font-style: italic;
    border-radius: 2px;
}
</style>

<!-- Visual Diff Box -->
<div class="card bg-dark border-secondary">
    <div class="card-header border-secondary d-flex justify-content-between align-items-center">
        <span class="fw-bold text-light"><i class="fas fa-align-left me-2"></i> Keystroke Alignment Diff</span>
        <div class="small text-muted">
            <span class="diff-correct me-2"><i class="fas fa-square me-1"></i> Correct</span>
            <span class="diff-error me-2"><i class="fas fa-square me-1"></i> Substitution</span>
            <span class="diff-missing me-2"><i class="fas fa-square me-1"></i> Missing</span>
            <span class="diff-extra"><i class="fas fa-square me-1"></i> Extra</span>
        </div>
    </div>
    <div class="card-body">
        <?php
        $diffHtml = '';
        foreach ($diffAlignment['aligned_pairs'] as $pair) {
            $type = $pair['type'];
            $ref = $pair['ref'] ?? '';
            $typed = $pair['typed'] ?? '';

            if ($type === 'correct') {
                $diffHtml .= '<span class="diff-correct">' . htmlspecialchars($ref) . '</span>';
            } elseif ($type === 'incorrect') {
                $displayChar = ($typed === ' ') ? '␣' : htmlspecialchars($typed);
                $title = 'Expected: ' . htmlspecialchars($ref) . ' | Typed: ' . htmlspecialchars($typed);
                $diffHtml .= '<span class="diff-error" title="' . $title . '">' . $displayChar . '</span>';
            } elseif ($type === 'missing') {
                $displayChar = ($ref === ' ') ? '␣' : htmlspecialchars($ref);
                $title = 'Missing: ' . htmlspecialchars($ref);
                $diffHtml .= '<span class="diff-missing" title="' . $title . '">' . $displayChar . '</span>';
            } elseif ($type === 'extra') {
                $displayChar = ($typed === ' ') ? '␣' : htmlspecialchars($typed);
                $title = 'Extra: ' . htmlspecialchars($typed);
                $diffHtml .= '<span class="diff-extra" title="' . $title . '">' . $displayChar . '</span>';
            }
        }
        ?>
        <div class="typing-diff"><?= $diffHtml ?></div>
    </div>
</div>
