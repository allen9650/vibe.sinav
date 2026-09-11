<?php
/**
 * vibe.Sınav Assessment System — Teacher Candidate Performance & Review Audit
 * Provides question-by-question response inspection, manual grading for subjective questions
 * (fill_blank, short_answer), proctoring violation logs, and result finalization.
 */

$attemptId = (int)($_GET['attempt_id'] ?? 0);

// Handle manual grading submission (single question)
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['action'] ?? '') === 'grade_question') {
    CSRF::validateOrFail();
    $questionId = (int)$_POST['question_id'];
    $marksAwarded = (float)$_POST['marks_awarded'];
    $comment = trim($_POST['teacher_comment'] ?? '');

    try {
        AssessmentResultService::gradeQuestion($attemptId, $questionId, $marksAwarded, $comment, (int)Auth::id());
        Session::flash('success', 'Question marks and teacher remarks saved successfully.');
    } catch (Throwable $e) {
        Session::flash('error', $e->getMessage());
    }
    redirectTo('assessments-candidate-detail&attempt_id=' . $attemptId);
}

// Handle final result generation & publication
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['action'] ?? '') === 'finalize_result') {
    CSRF::validateOrFail();

    try {
        $finalScore = AssessmentResultService::finalizeCandidateResult($attemptId, (int)Auth::id());
        Session::flash('success', "Result successfully generated and released! Final Score: {$finalScore['obtained_marks']}/{$finalScore['total_marks']} ({$finalScore['percentage']}%, " . strtoupper($finalScore['pass_fail']) . ").");
    } catch (Throwable $e) {
        Session::flash('error', "Failed to finalize result: " . $e->getMessage());
    }
    redirectTo('assessments-candidate-detail&attempt_id=' . $attemptId);
}

$scorecard = AssessmentResultService::getCandidateScorecard($attemptId);

if (!$scorecard) {
    Session::flash('error', 'Candidate performance record not found.');
    redirectTo('assessments');
}

$mins = floor(($scorecard['time_taken_seconds'] ?? 0) / 60);
$secs = ($scorecard['time_taken_seconds'] ?? 0) % 60;
$timeFormatted = sprintf('%02d:%02d', $mins, $secs);
$isPassed = ($scorecard['pass_fail'] ?? '') === 'pass';
$hasPendingReview = ((int)($scorecard['needs_review'] ?? 0) === 1 && (int)($scorecard['is_reviewed'] ?? 0) === 0);
$isReviewed = ((int)($scorecard['is_reviewed'] ?? 0) === 1);
$securityEvents = $scorecard['security_events'] ?? [];
$violationCount = count($securityEvents);
?>

<!-- Top Breadcrumbs & Page Header -->
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="<?= url('assessments') ?>" class="text-decoration-none text-muted">Assessments</a></li>
                <li class="breadcrumb-item"><a href="<?= url('assessments-results&id=' . $scorecard['assessment_id']) ?>" class="text-decoration-none text-muted">Results Ledger</a></li>
                <li class="breadcrumb-item active" aria-current="page">Candidate Audit</li>
            </ol>
        </nav>
        <h4 class="mb-0 fw-bold">
            <i class="fas fa-user-graduate text-primary me-2"></i>Candidate Response Audit & Review
        </h4>
    </div>
    <div class="d-flex gap-2">
        <button onclick="window.print()" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-print me-1"></i> Print Audit
        </button>
        <a href="<?= url('assessments-results&id=' . $scorecard['assessment_id']) ?>" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-arrow-left me-1"></i> Back to Ledger
        </a>
    </div>
</div>

<!-- Pending Review Callout Banner -->
<?php if ($hasPendingReview): ?>
<div class="alert alert-warning border border-warning shadow-sm d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4 p-3">
    <div>
        <h5 class="fw-bold mb-1 text-warning">
            <i class="fas fa-clipboard-check me-2"></i> Subjective Review Required
        </h5>
        <div class="small">
            This student's examination contains questions requiring teacher evaluation (Fill in the Blanks / Written Responses). Results are currently <strong>withheld from the student</strong>. Grade the responses below, then click <strong>Generate & Release Result</strong>.
        </div>
    </div>
    <form method="POST" action="<?= url('assessments-candidate-detail&attempt_id=' . $attemptId) ?>" class="m-0">
        <?= CSRF::field() ?>
        <input type="hidden" name="action" value="finalize_result">
        <button type="submit" class="btn btn-warning fw-bold text-dark px-4 py-2 shadow-sm">
            <i class="fas fa-bullhorn me-1"></i> Generate & Release Result
        </button>
    </form>
</div>
<?php elseif ($isReviewed): ?>
<div class="alert alert-success border border-success shadow-sm d-flex justify-content-between align-items-center mb-4 p-3">
    <div>
        <h6 class="fw-bold mb-0 text-success">
            <i class="fas fa-circle-check me-2"></i> Result Finalized & Released
        </h6>
        <div class="small text-muted">
            This candidate's subjective evaluation has been finalized and official scorecard released to the candidate.
            <?php if (!empty($scorecard['reviewed_at'])): ?>
                • Finalized at <?= e($scorecard['reviewed_at']) ?>
            <?php endif; ?>
        </div>
    </div>
    <form method="POST" action="<?= url('assessments-candidate-detail&attempt_id=' . $attemptId) ?>" class="m-0">
        <?= CSRF::field() ?>
        <input type="hidden" name="action" value="finalize_result">
        <button type="submit" class="btn btn-outline-success btn-sm fw-semibold">
            <i class="fas fa-rotate me-1"></i> Re-Calculate Totals
        </button>
    </form>
</div>
<?php endif; ?>

<!-- Candidate Summary Header Card -->
<div class="card border mb-4 shadow-sm">
    <div class="card-body p-4">
        <div class="row g-4 align-items-center">
            <div class="col-md-7">
                <div class="d-flex align-items-center gap-2 mb-1">
                    <h3 class="fw-bold mb-0"><?= e($scorecard['candidate_name']) ?></h3>
                    <span class="badge bg-secondary font-monospace"><?= e($scorecard['station_code'] ?? 'PC-01') ?></span>
                </div>
                <div class="text-muted font-monospace small mb-2">
                    Roll Number: <strong class="text-info"><?= e($scorecard['roll_number']) ?></strong> | 
                    Class: <strong><?= e($scorecard['student_class'] ?? $scorecard['target_class']) ?></strong> |
                    IP: <span><?= e($scorecard['station_ip'] ?? 'DHCP') ?></span>
                </div>
                <div class="small text-muted d-flex flex-wrap gap-3">
                    <span><i class="fas fa-university me-1 text-primary"></i><?= e($scorecard['campus_name']) ?></span>
                    <span><i class="fas fa-book me-1 text-warning"></i><?= e($scorecard['subject_name']) ?></span>
                    <span><i class="fas fa-user-tie me-1 text-info"></i>Teacher: <?= e($scorecard['teacher_name']) ?></span>
                </div>
            </div>

            <div class="col-md-5 text-md-end">
                <div class="d-inline-block text-center p-3 bg-secondary bg-opacity-10 rounded border" style="min-width: 220px;">
                    <div class="small text-muted text-uppercase fw-bold mb-1">Overall Outcome</div>
                    <?php if ($hasPendingReview): ?>
                        <span class="badge bg-warning text-dark fs-5 px-3 py-2 text-uppercase">
                            <i class="fas fa-clock me-1"></i> PENDING REVIEW
                        </span>
                    <?php elseif ($isPassed): ?>
                        <span class="badge bg-success fs-5 px-3 py-2 text-uppercase">
                            <i class="fas fa-check me-1"></i> PASS (<?= (float)($scorecard['percentage'] ?? 0) ?>%)
                        </span>
                    <?php else: ?>
                        <span class="badge bg-danger fs-5 px-3 py-2 text-uppercase">
                            <i class="fas fa-xmark me-1"></i> FAIL (<?= (float)($scorecard['percentage'] ?? 0) ?>%)
                        </span>
                    <?php endif; ?>
                    <div class="mt-2 text-muted small">
                        Marks: <strong><?= number_format((float)($scorecard['obtained_marks'] ?? 0), 2) ?> / <?= number_format((float)($scorecard['total_marks'] ?? 0), 2) ?></strong>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Performance Metrics Bar -->
<div class="row g-3 mb-4">
    <div class="col-md-2 col-4">
        <div class="card border p-3 text-center shadow-sm">
            <div class="small text-muted text-uppercase fw-bold mb-1">Total Qs</div>
            <div class="fs-3 fw-bold font-monospace"><?= (int)$scorecard['total_questions'] ?></div>
        </div>
    </div>
    <div class="col-md-2 col-4">
        <div class="card border p-3 text-center shadow-sm">
            <div class="small text-muted text-uppercase fw-bold mb-1">Answered</div>
            <div class="fs-3 fw-bold text-info font-monospace"><?= (int)$scorecard['answered_count'] ?></div>
        </div>
    </div>
    <div class="col-md-2 col-4">
        <div class="card border p-3 text-center shadow-sm">
            <div class="small text-muted text-uppercase fw-bold mb-1">Correct</div>
            <div class="fs-3 fw-bold text-success font-monospace"><?= (int)$scorecard['correct_answers'] ?></div>
        </div>
    </div>
    <div class="col-md-2 col-4">
        <div class="card border p-3 text-center shadow-sm">
            <div class="small text-muted text-uppercase fw-bold mb-1">Incorrect</div>
            <div class="fs-3 fw-bold text-danger font-monospace"><?= (int)$scorecard['wrong_answers'] ?></div>
        </div>
    </div>
    <div class="col-md-2 col-4">
        <div class="card border p-3 text-center shadow-sm">
            <div class="small text-muted text-uppercase fw-bold mb-1">Time Taken</div>
            <div class="fs-3 fw-bold text-warning font-monospace"><?= $timeFormatted ?></div>
        </div>
    </div>
    <div class="col-md-2 col-4">
        <div class="card border p-3 text-center shadow-sm">
            <div class="small text-muted text-uppercase fw-bold mb-1">Violations</div>
            <div class="fs-3 fw-bold <?= $violationCount > 0 ? 'text-danger' : 'text-success' ?> font-monospace">
                <?= $violationCount ?>
            </div>
        </div>
    </div>
</div>

<!-- PROCTORING & INTEGRITY TELEMETRY AUDIT -->
<div class="card border mb-4 shadow-sm" id="proctoringSection">
    <div class="card-header border-bottom p-3 d-flex justify-content-between align-items-center">
        <h6 class="mb-0 fw-bold">
            <i class="fas fa-shield-halved me-2 <?= $violationCount > 0 ? 'text-danger' : 'text-success' ?>"></i>
            Proctoring & Exam Integrity Telemetry Log
        </h6>
        <?php if ($violationCount > 0): ?>
            <span class="badge bg-danger">
                <i class="fas fa-triangle-exclamation me-1"></i><?= $violationCount ?> Anomaly Event(s) Recorded
            </span>
        <?php else: ?>
            <span class="badge bg-success-subtle text-success border border-success">
                <i class="fas fa-check-circle me-1"></i>Zero Violations Detected
            </span>
        <?php endif; ?>
    </div>
    <div class="card-body p-0">
        <?php if (empty($securityEvents)): ?>
            <div class="p-4 text-center text-muted">
                <i class="fas fa-user-shield fa-2x mb-2 text-success opacity-75"></i>
                <div>No window blur, tab switch, or fullscreen violations were detected during this examination session.</div>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover table-striped align-middle mb-0">
                    <thead class="table-secondary small text-uppercase">
                        <tr>
                            <th style="width: 60px;">#</th>
                            <th>Incident Event</th>
                            <th>Duration Away</th>
                            <th>Description</th>
                            <th>Severity</th>
                            <th>Logged Timestamp</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($securityEvents as $idx => $ev): 
                            $meta = json_decode((string)($ev['metadata'] ?? '[]'), true) ?: [];
                            $dur = $meta['duration_seconds'] ?? null;
                        ?>
                            <tr>
                                <td class="font-monospace text-muted"><?= $idx + 1 ?></td>
                                <td>
                                    <span class="badge bg-danger bg-opacity-20 text-danger border border-danger font-monospace">
                                        <?= e($ev['event_type']) ?>
                                    </span>
                                </td>
                                <td class="font-monospace fw-bold <?= !empty($dur) ? 'text-danger' : 'text-muted' ?>">
                                    <?= !empty($dur) ? (int)$dur . ' seconds' : '—' ?>
                                </td>
                                <td class="small"><?= e($ev['description'] ?? 'Window blurred / left examination viewport') ?></td>
                                <td>
                                    <span class="badge bg-<?= ($ev['severity'] ?? '') === 'high' ? 'danger' : 'warning' ?> text-uppercase" style="font-size: 0.7rem;">
                                        <?= e($ev['severity'] ?? 'medium') ?>
                                    </span>
                                </td>
                                <td class="font-monospace small text-muted"><?= e($ev['created_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- QUESTION-BY-QUESTION RESPONSE AUDIT -->
<div class="card border shadow-sm mb-4">
    <div class="card-header border-bottom p-3 d-flex justify-content-between align-items-center">
        <h6 class="mb-0 fw-bold"><i class="fas fa-list-check me-2 text-primary"></i>Question-by-Question Response Audit</h6>
        <span class="badge bg-secondary font-monospace"><?= count($scorecard['questions']) ?> Questions</span>
    </div>
    <div class="card-body p-3">
        <?php foreach ($scorecard['questions'] as $q): 
            $qType = $q['question_type'];
            $isSubjective = in_array($qType, ['fill_blank', 'short_answer'], true);
            $isCorrect = !empty($q['is_correct']);
            $isUnanswered = ($q['selected_option_ids'] === null && ($q['text_answer'] === null || trim((string)$q['text_answer']) === ''));
            $isGraded = ($q['evaluated_by'] !== null || !$isSubjective);
        ?>
        <div class="p-4 mb-3 rounded border <?= $isCorrect ? 'border-success bg-success bg-opacity-10' : ($isUnanswered ? 'border-secondary bg-secondary bg-opacity-10' : ($isSubjective && !$isGraded ? 'border-warning bg-warning bg-opacity-10' : 'border-danger bg-danger bg-opacity-10')) ?>">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-primary">Question <?= $q['question_order'] ?></span>
                    <span class="badge bg-dark border text-info"><?= QuestionService::TYPES[$qType] ?? $qType ?></span>
                    <span class="text-muted small font-monospace">Max Marks: <?= number_format((float)$q['marks'], 2) ?></span>
                </div>
                <div>
                    <?php if ($isSubjective && !$isGraded): ?>
                        <span class="badge bg-warning text-dark fw-bold px-3 py-1"><i class="fas fa-clock me-1"></i>Pending Teacher Review</span>
                    <?php elseif ($isSubjective && $isGraded): ?>
                        <span class="badge bg-info-subtle text-info border border-info px-3 py-1">
                            <i class="fas fa-check-double me-1"></i>Reviewed: +<?= number_format((float)$q['marks_obtained'], 2) ?> / <?= number_format((float)$q['marks'], 2) ?>
                        </span>
                    <?php elseif ($isCorrect): ?>
                        <span class="badge bg-success-subtle text-success border border-success px-3 py-1">
                            <i class="fas fa-check me-1"></i>Awarded +<?= number_format((float)$q['marks_obtained'], 2) ?>
                        </span>
                    <?php elseif ($isUnanswered): ?>
                        <span class="badge bg-secondary px-3 py-1">Unanswered (0.00)</span>
                    <?php else: ?>
                        <span class="badge bg-danger-subtle text-danger border border-danger px-3 py-1">
                            <i class="fas fa-times me-1"></i>Incorrect (<?= number_format((float)$q['marks_obtained'], 2) ?>)
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Question Statement -->
            <div class="fw-semibold fs-5 mb-3" style="color: var(--text-primary);"><?= nl2br(e($q['question_text'])) ?></div>

            <!-- Subjective Responses UI (Fill in Blank & Short Answer) -->
            <?php if ($isSubjective): ?>
                <div class="p-3 bg-body-tertiary border rounded mb-3">
                    <div class="small text-muted text-uppercase fw-bold mb-2">
                        <i class="fas fa-quote-left text-info me-1"></i> Candidate's Submitted Response:
                    </div>
                    <?php if (!empty($q['text_answer'])): ?>
                        <div class="font-monospace fs-5 p-2 bg-body border rounded text-break" style="white-space: pre-wrap; color: var(--text-primary);">
                            <?= e($q['text_answer']) ?>
                        </div>
                    <?php else: ?>
                        <div class="text-muted fst-italic">No answer provided by candidate (Left Blank).</div>
                    <?php endif; ?>
                </div>

                <!-- Reference / Accepted Solution -->
                <?php if (!empty($q['options'])): ?>
                    <div class="p-3 bg-body-tertiary border rounded mb-3">
                        <div class="small text-muted text-uppercase fw-bold mb-1">
                            <i class="fas fa-lightbulb text-warning me-1"></i> Reference / Accepted Answers:
                        </div>
                        <ul class="mb-0 small ps-3">
                            <?php foreach ($q['options'] as $opt): ?>
                                <li class="font-monospace text-success fw-bold"><?= e($opt['option_text']) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <!-- Teacher Manual Grading Interface -->
                <div class="p-3 bg-body-tertiary border border-primary border-opacity-50 rounded mb-2">
                    <div class="small text-uppercase fw-bold text-primary mb-2">
                        <i class="fas fa-pen-to-square me-1"></i> Teacher Evaluation & Marks Allocation
                    </div>
                    <form method="POST" action="<?= url('assessments-candidate-detail&attempt_id=' . $attemptId) ?>" class="row g-3 align-items-end">
                        <?= CSRF::field() ?>
                        <input type="hidden" name="action" value="grade_question">
                        <input type="hidden" name="question_id" value="<?= (int)$q['question_id'] ?>">

                        <div class="col-md-3">
                            <label class="form-label text-muted small text-uppercase fw-bold">
                                Award Marks (Max: <?= number_format((float)$q['marks'], 2) ?>)
                            </label>
                            <input type="number" step="0.25" min="0" max="<?= (float)$q['marks'] ?>" 
                                   name="marks_awarded" id="marksInput_<?= (int)$q['question_id'] ?>"
                                   class="form-control fw-bold font-monospace fs-5" 
                                   value="<?= (float)($q['marks_obtained'] ?? 0) ?>" required>
                        </div>

                        <div class="col-md-3 d-flex gap-2">
                            <button type="button" class="btn btn-outline-success btn-sm flex-fill fw-bold" 
                                    onclick="document.getElementById('marksInput_<?= (int)$q['question_id'] ?>').value = '<?= (float)$q['marks'] ?>'">
                                <i class="fas fa-check me-1"></i> Full (<?= (float)$q['marks'] ?>)
                            </button>
                            <button type="button" class="btn btn-outline-warning btn-sm flex-fill fw-bold" 
                                    onclick="document.getElementById('marksInput_<?= (int)$q['question_id'] ?>').value = '<?= round((float)$q['marks'] / 2, 2) ?>'">
                                <i class="fas fa-adjust me-1"></i> Half
                            </button>
                            <button type="button" class="btn btn-outline-danger btn-sm flex-fill fw-bold" 
                                    onclick="document.getElementById('marksInput_<?= (int)$q['question_id'] ?>').value = '0.00'">
                                <i class="fas fa-xmark me-1"></i> 0.00
                            </button>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label text-muted small text-uppercase fw-bold">Teacher Feedback / Notes</label>
                            <input type="text" name="teacher_comment" class="form-control form-control-sm" 
                                   placeholder="e.g. Well formulated or Partially accurate..." 
                                   value="<?= e($q['teacher_comment'] ?? '') ?>">
                        </div>

                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary btn-sm w-100 fw-bold py-2">
                                <i class="fas fa-save me-1"></i> Save Grade
                            </button>
                        </div>
                    </form>
                </div>

            <!-- Objective Choice Display -->
            <?php else: ?>
                <div class="mb-2">
                    <?php 
                    $selectedIds = json_decode((string)($q['selected_option_ids'] ?? '[]'), true) ?: [];
                    $selectedIds = array_map('intval', (array)$selectedIds);
                    ?>
                    <div class="row g-2">
                        <?php foreach ($q['options'] as $opt): 
                            $isOptSelected = in_array((int)$opt['id'], $selectedIds, true);
                            $isOptCorrect = ((int)$opt['is_correct'] === 1);
                        ?>
                            <div class="col-md-6">
                                <div class="p-2 rounded border d-flex align-items-center gap-2 <?= $isOptCorrect ? 'border-success bg-success bg-opacity-10 text-success fw-semibold' : ($isOptSelected ? 'border-danger bg-danger bg-opacity-10 text-danger' : 'border-secondary bg-body-tertiary text-muted') ?>">
                                    <span class="badge <?= $isOptCorrect ? 'bg-success' : ($isOptSelected ? 'bg-danger' : 'bg-secondary') ?>">
                                        <?= $isOptCorrect ? '✓ Correct' : ($isOptSelected ? '✗ Chosen' : 'Option') ?>
                                    </span>
                                    <span><?= e($opt['option_text']) ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Bottom Finalize Action -->
<div class="card border shadow-sm p-4 text-center">
    <h5 class="fw-bold mb-2">Ready to Release Final Scorecard?</h5>
    <p class="text-muted small mb-3">
        Finalizing this attempt recalculates total obtained marks, accuracy percentage, and passing outcome, and immediately unlocks the scorecard for the candidate.
    </p>
    <form method="POST" action="<?= url('assessments-candidate-detail&attempt_id=' . $attemptId) ?>" class="d-inline-block">
        <?= CSRF::field() ?>
        <input type="hidden" name="action" value="finalize_result">
        <button type="submit" class="btn btn-success btn-lg fw-bold px-5 py-3 shadow">
            <i class="fas fa-check-double me-2"></i> Generate & Release Final Result
        </button>
    </form>
</div>
