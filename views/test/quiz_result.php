<?php
/**
 * PTM Assessment System — Candidate Quiz Result & Scorecard View
 */

$attemptId = (int)($_GET['attempt_id'] ?? (Session::get('completed_attempt_id') ?? 0));

if ($attemptId <= 0) {
    Session::flash('error', 'Attempt result not specified.');
    redirectTo('quiz-login');
}

$scorecard = AssessmentResultService::getCandidateScorecard($attemptId);
if (!$scorecard) {
    Session::flash('error', 'Result scorecard not found.');
    redirectTo('quiz-login');
}

$isPassed = $scorecard['pass_fail'] === 'pass';
$mins = floor($scorecard['time_taken_seconds'] / 60);
$secs = $scorecard['time_taken_seconds'] % 60;
$timeFormatted = sprintf('%02d:%02d', $mins, $secs);

$isResultVisible = ($scorecard['result_display_mode'] === 'immediate' && empty($scorecard['manual_review_pending']));
$showReview = $isResultVisible;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $isResultVisible ? 'Scorecard: ' . e($scorecard['candidate_name']) : 'Assessment Completed' ?> — PTM</title>
    <?= getFaviconTag() ?>
    <link rel="stylesheet" href="<?= asset('css/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/fontawesome.min.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
    <style>
        body { background: #0f172a; color: #f8fafc; font-family: system-ui, -apple-system, sans-serif; }
        .scorecard-box { max-width: 860px; margin: 2rem auto; background: #1e293b; border: 1px solid #334155; border-radius: 12px; }
        .stat-card { background: #0f172a; border: 1px solid #334155; border-radius: 8px; padding: 1.25rem; text-align: center; }
        @media print {
            body { background: #fff !important; color: #000 !important; }
            .no-print { display: none !important; }
            .scorecard-box { border: none !important; box-shadow: none !important; max-width: 100% !important; margin: 0 !important; }
            .stat-card { background: #f8fafc !important; border: 1px solid #cbd5e1 !important; color: #000 !important; }
            .text-light { color: #000 !important; }
            .text-muted { color: #64748b !important; }
            .card { border: 1px solid #cbd5e1 !important; background: #fff !important; }
        }
    </style>
</head>
<body>

<div class="container py-4">
    <div class="scorecard-box p-4 p-md-5">
        <?php if (!$isResultVisible): ?>
            <!-- Withheld Result Mode: Contact Concerned Teacher Page -->
            <div class="text-center py-4">
                <div class="d-inline-flex align-items-center justify-content-center bg-success bg-opacity-10 text-success rounded-circle p-4 mb-3" style="width: 85px; height: 85px;">
                    <i class="fas fa-check-circle fa-3x"></i>
                </div>
                <h3 class="fw-bold text-light mb-1">Assessment Completed Successfully</h3>
                <p class="text-muted small mb-4">Your responses have been safely submitted and recorded on the server.</p>

                <div class="card bg-dark border-secondary p-4 text-start mx-auto mb-4" style="max-width: 580px;">
                    <div class="row g-2 small">
                        <div class="col-sm-4 text-muted">Candidate Name:</div>
                        <div class="col-sm-8 fw-bold text-light"><?= e($scorecard['candidate_name']) ?></div>
                        
                        <div class="col-sm-4 text-muted">Workstation / Roll:</div>
                        <div class="col-sm-8 font-monospace text-info"><?= e($scorecard['roll_number']) ?></div>

                        <div class="col-sm-4 text-muted">Assessment Title:</div>
                        <div class="col-sm-8 fw-semibold text-light"><?= e($scorecard['assessment_title']) ?></div>

                        <div class="col-sm-4 text-muted">Class / Grade:</div>
                        <div class="col-sm-8 text-light"><?= e($scorecard['class_grade']) ?></div>

                        <div class="col-sm-4 text-muted">Subject:</div>
                        <div class="col-sm-8 text-light"><?= e($scorecard['subject_name']) ?></div>

                        <div class="col-sm-4 text-muted">Teacher:</div>
                        <div class="col-sm-8 text-light"><?= e($scorecard['teacher_name']) ?></div>

                        <div class="col-sm-4 text-muted">Time Taken:</div>
                        <div class="col-sm-8 font-monospace text-warning"><?= $timeFormatted ?></div>
                    </div>
                </div>

                <div class="alert alert-info py-3 px-4 mx-auto mb-4" style="max-width: 580px;">
                    <i class="fas fa-info-circle fa-lg me-2"></i>
                    <?php if (!empty($scorecard['manual_review_pending'])): ?>
                        This assessment contains subjective questions requiring teacher grading. <strong>Please contact your concerned teacher for results once evaluated.</strong>
                    <?php else: ?>
                        Results for this assessment are withheld by the administration. <strong>Please contact your concerned teacher for more information and result announcement.</strong>
                    <?php endif; ?>
                </div>

                <div>
                    <a href="<?= url('quiz-login') ?>" class="btn btn-outline-secondary px-4">
                        <i class="fas fa-arrow-left me-1"></i> Return to Examination Terminal
                    </a>
                </div>
            </div>

        <?php else: ?>
            <!-- Immediate Result Mode: Full Official Scorecard -->
            <!-- Action Buttons (No Print) -->
            <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom border-secondary no-print">
                <a href="<?= url('quiz-login') ?>" class="btn btn-outline-secondary btn-sm">
                    <i class="fas fa-arrow-left me-1"></i> Return to Portal
                </a>
                <div class="d-flex gap-2">
                    <button onclick="window.print()" class="btn btn-primary btn-sm fw-bold">
                        <i class="fas fa-print me-1"></i> Print Scorecard
                    </button>
                </div>
            </div>

            <!-- Official Header -->
            <div class="text-center mb-4">
                <h4 class="fw-bold text-primary mb-1"><?= e($scorecard['campus_name']) ?></h4>
                <h2 class="fw-bold text-light mb-1"><?= e($scorecard['assessment_title']) ?></h2>
                <div class="text-muted small">
                    <span>Subject: <strong><?= e($scorecard['subject_name']) ?></strong></span> • 
                    <span>Class: <strong><?= e($scorecard['class_grade']) ?></strong></span> • 
                    <span>Teacher: <strong><?= e($scorecard['teacher_name']) ?></strong></span>
                </div>
            </div>

            <!-- Candidate Identity & Pass/Fail Ribbon -->
            <div class="card bg-dark border-secondary p-3 mb-4">
                <div class="row g-3 align-items-center">
                    <div class="col-md-8">
                        <div class="fs-5 fw-bold text-light"><?= e($scorecard['candidate_name']) ?></div>
                        <div class="small text-muted font-monospace">
                            <span class="me-3"><i class="fas fa-id-badge me-1"></i>Roll Number: <strong><?= e($scorecard['roll_number']) ?></strong></span>
                            <?php if (!empty($scorecard['father_name'])): ?>
                                <span class="me-3">Father: <?= e($scorecard['father_name']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-4 text-md-end">
                        <?php if ($isPassed): ?>
                            <span class="badge bg-success fs-5 px-4 py-2 text-uppercase"><i class="fas fa-check-circle me-1"></i> PASSED</span>
                        <?php else: ?>
                            <span class="badge bg-danger fs-5 px-4 py-2 text-uppercase"><i class="fas fa-times-circle me-1"></i> FAILED</span>
                        <?php endif; ?>
                        <?php if (!empty($scorecard['rank'])): ?>
                            <div class="small text-warning mt-1"><i class="fas fa-trophy me-1"></i>Rank: #<?= $scorecard['rank'] ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        <!-- Key Metrics Grid -->
        <div class="row g-3 mb-4">
            <div class="col-md-3 col-6">
                <div class="stat-card">
                    <div class="small text-muted text-uppercase fw-bold mb-1">Score Obtained</div>
                    <div class="fs-3 fw-bold text-primary"><?= (float)$scorecard['obtained_marks'] ?> / <?= (float)$scorecard['total_marks'] ?></div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="stat-card">
                    <div class="small text-muted text-uppercase fw-bold mb-1">Percentage</div>
                    <div class="fs-3 fw-bold text-info"><?= (float)$scorecard['percentage'] ?>%</div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="stat-card">
                    <div class="small text-muted text-uppercase fw-bold mb-1">Accuracy</div>
                    <div class="fs-3 fw-bold text-success"><?= (float)$scorecard['accuracy'] ?>%</div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="stat-card">
                    <div class="small text-muted text-uppercase fw-bold mb-1">Time Taken</div>
                    <div class="fs-3 fw-bold text-warning font-monospace"><?= $timeFormatted ?></div>
                </div>
            </div>
        </div>

        <!-- Breakdown Counters -->
        <div class="row g-3 mb-4">
            <div class="col-4">
                <div class="p-3 bg-dark rounded border border-success text-center">
                    <div class="text-success fw-bold fs-4"><?= $scorecard['correct_answers'] ?></div>
                    <div class="small text-muted">Correct Answers</div>
                </div>
            </div>
            <div class="col-4">
                <div class="p-3 bg-dark rounded border border-danger text-center">
                    <div class="text-danger fw-bold fs-4"><?= $scorecard['wrong_answers'] ?></div>
                    <div class="small text-muted">Wrong Answers</div>
                </div>
            </div>
            <div class="col-4">
                <div class="p-3 bg-dark rounded border border-secondary text-center">
                    <div class="text-secondary fw-bold fs-4"><?= $scorecard['unanswered'] ?></div>
                    <div class="small text-muted">Unanswered</div>
                </div>
            </div>
        </div>

        <!-- Question Level Review (if enabled) -->
        <?php if ($showReview && !empty($scorecard['questions'])): ?>
            <div class="mt-4 pt-3 border-top border-secondary">
                <h5 class="fw-bold text-light mb-3"><i class="fas fa-clipboard-check me-2 text-primary"></i>Question Performance Analysis</h5>
                
                <?php foreach ($scorecard['questions'] as $q): ?>
                    <div class="card bg-dark border-secondary mb-3">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="badge bg-secondary">Question <?= $q['question_order'] ?></span>
                                <div>
                                    <?php if (!empty($q['is_correct'])): ?>
                                        <span class="badge bg-success"><i class="fas fa-check me-1"></i>Correct (+<?= (float)$q['marks_obtained'] ?>)</span>
                                    <?php elseif ($q['selected_option_ids'] !== null || $q['text_answer'] !== null || $q['matching_answers'] !== null): ?>
                                        <span class="badge bg-danger"><i class="fas fa-times me-1"></i>Incorrect (<?= (float)$q['marks_obtained'] ?>)</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Unanswered (0)</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="text-light mb-2"><?= nl2br(e($q['question_text'])) ?></div>

                            <?php if (!empty($q['explanation'])): ?>
                                <div class="small text-muted bg-black p-2 rounded border border-secondary mt-2">
                                    <strong class="text-info">Explanation:</strong> <?= e($q['explanation']) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; // End of showReview check ?>
        <?php endif; // End of isResultVisible check ?>

        <!-- Footer Seal -->
        <div class="text-center pt-4 border-top border-secondary text-muted small mt-4">
            <div>This is an official computer-generated performance record issued by the <strong>vibe.Sınav Assessment System</strong>.</div>
            <div class="font-monospace mt-1">Generated: <?= date('Y-m-d H:i:s') ?></div>
            <div class="mt-2 text-muted small">Developed by Ahsan Raza</div>
        </div>
    </div>
</div>

</body>
</html>

