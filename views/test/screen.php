<?php
declare(strict_types=1);

/**
 * PTM Assessment System — Exam Runner Screen
 * Clean, distraction-free, high-contrast examination workspace.
 */

$attemptId = (int)($_GET['attempt_id'] ?? (Session::get('current_attempt_id') ?? 0));

if ($attemptId <= 0) {
    redirectTo('test-start');
}

// Fetch attempt details
$attempt = Database::fetch(
    "SELECT att.*, a.title, a.duration_minutes, a.total_marks, a.passing_percentage,
            a.shuffle_questions, a.shuffle_options, a.instructions, a.rules_guidelines,
            s.station_code, s.ip_address, sub.name AS subject_name
     FROM assessment_attempts att
     JOIN assessments a ON att.assessment_id = a.id
     JOIN lab_stations s ON att.station_id = s.id
     JOIN subjects sub ON a.subject_id = sub.id
     WHERE att.id = ?",
    [$attemptId]
);

if (!$attempt) {
    Session::flash('error', 'Attempt record not found.');
    redirectTo('test-start');
}

// If already submitted, redirect to result screen
if ($attempt['status'] === 'completed' || $attempt['status'] === 'timed_out') {
    redirectTo('test-result&attempt_id=' . $attemptId);
}

// Fetch questions and options for this assessment
$questions = AssessmentService::getAssessmentQuestions((int)$attempt['assessment_id']);

// Deterministic Candidate Question Shuffling
if (!empty($attempt['shuffle_questions']) && count($questions) > 1) {
    mt_srand($attemptId * 31);
    for ($i = count($questions) - 1; $i > 0; $i--) {
        $j = mt_rand(0, $i);
        $tmp = $questions[$i];
        $questions[$i] = $questions[$j];
        $questions[$j] = $tmp;
    }
    $questions = array_values($questions);
    mt_srand();
}

// Deterministic Candidate Option Shuffling
if (!empty($attempt['shuffle_options'])) {
    foreach ($questions as &$q) {
        if (!empty($q['options']) && count($q['options']) > 1) {
            mt_srand($attemptId * 31 + (int)$q['id']);
            $opts = $q['options'];
            for ($i = count($opts) - 1; $i > 0; $i--) {
                $j = mt_rand(0, $i);
                $tmp = $opts[$i];
                $opts[$i] = $opts[$j];
                $opts[$j] = $tmp;
            }
            $q['options'] = array_values($opts);
            mt_srand();
        }
    }
    unset($q);
}

// Fetch any previously saved answers for this attempt
$existingAnswersRaw = Database::fetchAll(
    "SELECT question_id, selected_option_ids, text_answer FROM assessment_answers WHERE attempt_id = ?",
    [$attemptId]
);
$initialAnswers = [];
foreach ($existingAnswersRaw as $ea) {
    $qid = (int)$ea['question_id'];
    if (!empty($ea['selected_option_ids'])) {
        $parsed = json_decode((string)$ea['selected_option_ids'], true);
        $initialAnswers[$qid] = count($parsed) === 1 ? (int)$parsed[0] : $parsed;
    } elseif ($ea['text_answer'] !== null) {
        $initialAnswers[$qid] = $ea['text_answer'];
    }
}

// Prepare configuration for JavaScript client
$hasQuestions = !empty($questions);
$firstQuestion = $hasQuestions ? $questions[0] : null;
$typeLabels = [
    'single_choice'   => 'Single Choice',
    'multiple_choice' => 'Multiple Choice (Select all that apply)',
    'true_false'      => 'True / False',
    'fill_blank'      => 'Fill in the Blank',
    'short_answer'    => 'Short Answer / Written Response',
];

$expectedEndEpoch = strtotime($attempt['expected_end_at']);
$examConfig = [
    'attemptId'        => $attemptId,
    'expectedEndEpoch' => $expectedEndEpoch,
    'questions'        => $questions,
    'initialAnswers'   => $initialAnswers,
    'csrfToken'        => CSRF::token(),
    'autosaveUrl'      => url('api-autosave'),
    'submitUrl'        => url('test-submit'),
    'telemetryUrl'     => url('api-quiz-telemetry'),
    'anomalyTimer'     => (int)getSetting('proctoring_anomaly_timer', '5'),
    'detectBlur'       => getSetting('proctoring_detect_blur', '1') === '1',
    'strictFullscreen' => getSetting('proctoring_strict_fullscreen', '1') === '1',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($attempt['title']) ?> — PTM Assessment</title>
    <?= getFaviconTag() ?>
    <!-- Theme Initialization: Zero FOUC early execution -->
    <script src="<?= asset('js/theme-engine.js') ?>?v=<?= time() ?>"></script>
    <link rel="stylesheet" href="<?= asset('css/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/all.min.css') ?>">
    <style>
        :root,
        [data-bs-theme="light"] {
            color-scheme: light;
            --bg-canvas: #f1f5f9;
            --panel-bg: #ffffff;
            --card-border: #cbd5e1;
            --text-primary: #0f172a;
            --text-secondary: #334155;
            --text-muted: #64748b;
            --accent-blue: #2563eb;
            --accent-green: #059669;
            --accent-yellow: #d97706;
            --accent-danger: #dc2626;
            --nav-bg: #ffffff;
            --nav-border: #cbd5e1;
            --header-bar-bg: #f8fafc;
            --option-bg: #ffffff;
            --option-border: #cbd5e1;
            --option-hover-bg: #eff6ff;
            --option-hover-border: #3b82f6;
            --option-selected-bg: #dbeafe;
            --option-selected-border: #2563eb;
            --option-badge-bg: #e2e8f0;
            --option-badge-border: #cbd5e1;
            --option-badge-text: #1e293b;
            --station-tag-bg: #e0f2fe;
            --station-tag-text: #0369a1;
            --station-tag-border: #7dd3fc;
            --countdown-bg: #ffffff;
            --countdown-text: #0f172a;
            --countdown-border: #cbd5e1;
            --nav-unanswered-bg: #f8fafc;
            --nav-unanswered-border: #cbd5e1;
            --nav-unanswered-text: #1e293b;
            --nav-answered-bg: #dcfce7;
            --nav-answered-border: #16a34a;
            --nav-answered-text: #166534;
            --nav-flagged-bg: #fef3c7;
            --nav-flagged-border: #d97706;
            --nav-flagged-text: #92400e;
        }

        [data-bs-theme="dark"] {
            color-scheme: dark;
            --bg-canvas: #090d16;
            --panel-bg: #111827;
            --card-border: #1f2937;
            --text-primary: #f8fafc;
            --text-secondary: #cbd5e1;
            --text-muted: #9ca3af;
            --accent-blue: #3b82f6;
            --accent-green: #10b981;
            --accent-yellow: #f59e0b;
            --accent-danger: #ef4444;
            --nav-bg: #0f172a;
            --nav-border: #1e293b;
            --header-bar-bg: #0f172a;
            --option-bg: #111827;
            --option-border: #1f2937;
            --option-hover-bg: #162032;
            --option-hover-border: #38bdf8;
            --option-selected-bg: #172554;
            --option-selected-border: #3b82f6;
            --option-badge-bg: #1e293b;
            --option-badge-border: #334155;
            --option-badge-text: #94a3b8;
            --station-tag-bg: #1e293b;
            --station-tag-text: #38bdf8;
            --station-tag-border: #0369a1;
            --countdown-bg: #0b1120;
            --countdown-text: #f8fafc;
            --countdown-border: #334155;
            --nav-unanswered-bg: #1f2937;
            --nav-unanswered-border: #374151;
            --nav-unanswered-text: #9ca3af;
            --nav-answered-bg: #065f46;
            --nav-answered-border: #10b981;
            --nav-answered-text: #ecfdf5;
            --nav-flagged-bg: #78350f;
            --nav-flagged-border: #f59e0b;
            --nav-flagged-text: #fffbeb;
        }

        body {
            background-color: var(--bg-canvas);
            color: var(--text-primary);
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            height: 100vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            margin: 0;
            user-select: none;
            transition: background-color 0.2s ease, color 0.2s ease;
        }

        /* Sticky Top Header */
        .exam-navbar {
            background-color: var(--nav-bg);
            border-bottom: 2px solid var(--nav-border);
            padding: 0.75rem 1.5rem;
            z-index: 1020;
        }
        .station-tag {
            font-family: monospace;
            font-weight: 700;
            font-size: 1.1rem;
            color: var(--station-tag-text);
            background: var(--station-tag-bg);
            border: 1px solid var(--station-tag-border);
            padding: 0.25rem 0.65rem;
            border-radius: 4px;
        }
        .countdown-badge {
            font-family: monospace;
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--countdown-text);
            background: var(--countdown-bg);
            border: 2px solid var(--countdown-border);
            padding: 0.25rem 0.9rem;
            border-radius: 6px;
            letter-spacing: 0.05em;
        }

        /* Workspace Grid */
        .exam-workspace {
            display: grid;
            grid-template-columns: 1fr 300px;
            flex: 1;
            overflow: hidden;
        }

        /* Question Center Pane */
        .question-pane {
            overflow-y: auto;
            padding: 1.5rem 2rem;
            background: var(--bg-canvas);
        }
        .question-card {
            background: var(--panel-bg);
            border: 1px solid var(--card-border);
            border-radius: 12px;
            padding: 1.75rem 2rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
            max-width: 920px;
            margin: 0 auto;
        }
        .question-statement {
            font-size: 1.25rem;
            font-weight: 600;
            line-height: 1.6;
            color: var(--text-primary);
        }

        /* Modern Exam Option Items */
        .option-item {
            background: var(--option-bg);
            border: 2px solid var(--option-border);
            border-radius: 10px;
            padding: 0.85rem 1.25rem;
            margin-bottom: 0.85rem;
            cursor: pointer;
            transition: all 0.15s ease;
            user-select: none;
        }
        .option-item:hover {
            border-color: var(--option-hover-border);
            background: var(--option-hover-bg);
        }
        .option-item.selected {
            border-color: var(--option-selected-border);
            background: var(--option-selected-bg);
            box-shadow: 0 0 0 1px rgba(37, 99, 235, 0.4);
        }
        .option-text {
            color: var(--text-primary);
            font-weight: 500;
        }
        .exam-input {
            width: 22px;
            height: 22px;
            cursor: pointer;
            border: 2px solid #64748b;
            background-color: var(--option-bg);
            margin-top: 0;
            flex-shrink: 0;
        }
        .exam-input:checked {
            background-color: var(--accent-blue);
            border-color: var(--accent-blue);
        }
        .option-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: 8px;
            background: var(--option-badge-bg);
            border: 1px solid var(--option-badge-border);
            font-weight: 700;
            font-size: 0.95rem;
            color: var(--option-badge-text);
            flex-shrink: 0;
            transition: all 0.15s ease;
        }
        .option-item.selected .option-badge {
            background: var(--accent-blue);
            border-color: var(--accent-blue);
            color: #ffffff;
        }

        /* Right Sidebar Palette */
        .palette-sidebar {
            background-color: var(--panel-bg);
            border-left: 2px solid var(--card-border);
            display: flex;
            flex-direction: column;
            height: 100%;
        }
        .palette-header {
            padding: 1.25rem;
            border-bottom: 1px solid var(--card-border);
            background: var(--header-bar-bg);
        }
        .palette-body {
            flex: 1;
            overflow-y: auto;
            padding: 1.25rem;
        }
        .palette-footer {
            padding: 1.25rem;
            border-top: 1px solid var(--card-border);
            background: var(--header-bar-bg);
        }

        /* Question Grid Buttons */
        .nav-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 0.5rem;
        }
        .nav-cell {
            height: 42px;
            font-weight: 700;
            font-size: 0.95rem;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.1s ease;
        }
        .nav-cell:hover {
            opacity: 0.85;
            transform: scale(1.04);
        }
        .nav-cell.active {
            box-shadow: 0 0 0 3px #38bdf8 !important;
            border-color: #38bdf8 !important;
        }
        .nav-cell.answered {
            background-color: var(--nav-answered-bg);
            border: 2px solid var(--nav-answered-border);
            color: var(--nav-answered-text);
        }
        .nav-cell.unanswered {
            background-color: var(--nav-unanswered-bg);
            border: 2px solid var(--nav-unanswered-border);
            color: var(--nav-unanswered-text);
        }
        .nav-cell.flagged {
            background-color: var(--nav-flagged-bg);
            border: 2px solid var(--nav-flagged-border);
            color: var(--nav-flagged-text);
        }

        .animate-pulse {
            animation: pulseAnim 1.5s infinite;
        }
        @keyframes pulseAnim {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.4; }
        }
    </style>
</head>
<body>

<!-- STICKY TOP BAR -->
<header class="exam-navbar d-flex justify-content-between align-items-center">
    <div class="d-flex align-items-center gap-3">
        <span class="station-tag">
            <i class="fas fa-desktop me-1"></i><?= e($attempt['station_code']) ?>
        </span>
        <div>
            <div class="fw-bold" style="color: var(--text-primary);"><?= e($attempt['student_name']) ?></div>
            <div class="small text-muted font-monospace">
                Roll: <span class="text-info fw-semibold"><?= e($attempt['student_roll_no']) ?></span> | Class: <span class="fw-semibold" style="color: var(--text-secondary);"><?= e($attempt['student_class']) ?></span>
            </div>
        </div>
    </div>

    <!-- Center Exam Title & Connectivity Badge -->
    <div class="d-none d-md-flex align-items-center gap-3">
        <span class="badge px-3 py-2 fw-semibold" style="background: var(--panel-bg); border: 1px solid var(--card-border); color: var(--text-primary);">
            <?= e($attempt['title']) ?>
        </span>
        <span id="networkStatusBadge" class="badge bg-success-subtle text-success border border-success px-2 py-1 small">
            <i class="fas fa-signal me-1"></i><span id="networkStatusText">Connected</span>
        </span>
    </div>

    <!-- Authoritative Countdown Clock & Theme Switcher -->
    <div class="d-flex align-items-center gap-2 gap-sm-3">
        <div class="text-end d-none d-sm-block">
            <div class="text-muted small text-uppercase fw-bold" style="font-size: 0.7rem;">Time Remaining</div>
            <div class="countdown-badge" id="countdownClock">--:--</div>
        </div>
        <!-- Theme quick toggle -->
        <button type="button" class="btn btn-outline-secondary btn-sm theme-quick-toggle" title="Toggle Light / Dark Mode">
            <i class="fas fa-sun text-warning"></i>
        </button>
        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="requestFullscreenExam()" title="Enter Fullscreen">
            <i class="fas fa-expand"></i>
        </button>
    </div>
</header>

<!-- MAIN EXAM WORKSPACE -->
<main class="exam-workspace">
    <!-- LEFT/CENTER QUESTION DISPLAY PANE -->
    <section class="question-pane">
        <div class="question-card">
            <?php if (!$hasQuestions): ?>
                <div class="alert alert-warning text-center p-5">
                    <i class="fas fa-triangle-exclamation fa-3x mb-3 text-warning"></i>
                    <h4 class="fw-bold">No Questions Attached</h4>
                    <p class="text-muted mb-0">This assessment does not contain any questions yet. Please contact your instructor or exam administrator.</p>
                </div>
            <?php else: ?>
                <!-- Question Metadata Bar -->
                <div class="d-flex justify-content-between align-items-center mb-3 pb-3 border-bottom border-secondary border-opacity-25">
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-primary px-3 py-2 fw-bold" id="activeQuestionNumber">Question 1 of <?= count($questions) ?></span>
                        <span class="badge bg-dark border border-secondary text-muted" id="activeQuestionTypeBadge"><?= e($typeLabels[$firstQuestion['question_type']] ?? 'Single Choice') ?></span>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="text-success fw-bold font-monospace" id="activeQuestionMarks">+<?= number_format((float)$firstQuestion['marks'], 2) ?> Marks</span>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="btnFlagReview">
                            <i class="far fa-flag me-1"></i> Flag for Review
                        </button>
                    </div>
                </div>

                <!-- Question Statement -->
                <div class="question-statement mb-4" id="activeQuestionStatement">
                    <?= nl2br(e($firstQuestion['question_text'])) ?>
                </div>

                <!-- Options / Input Area -->
                <div id="activeQuestionInputContainer" class="mb-4">
                    <?php 
                    $optionLetters = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'];
                    ?>
                    <?php if (in_array($firstQuestion['question_type'], ['single_choice', 'true_false'], true)): ?>
                        <?php foreach ($firstQuestion['options'] as $optIdx => $opt): ?>
                            <?php 
                            $optLetter = $optionLetters[$optIdx] ?? chr(65 + $optIdx);
                            $isOptSelected = (isset($initialAnswers[$firstQuestion['id']]) && (string)$initialAnswers[$firstQuestion['id']] === (string)$opt['id']);
                            ?>
                            <div class="option-item <?= $isOptSelected ? 'selected' : '' ?>" data-opt-id="<?= (int)$opt['id'] ?>">
                                <label class="d-flex align-items-center gap-3 w-100 mb-0 py-1" for="radio_opt_<?= (int)$opt['id'] ?>" style="cursor: pointer;">
                                    <input type="radio" 
                                           name="question_opt_<?= (int)$firstQuestion['id'] ?>" 
                                           id="radio_opt_<?= (int)$opt['id'] ?>" 
                                           value="<?= (int)$opt['id'] ?>" 
                                           class="form-check-input exam-input" 
                                           <?= $isOptSelected ? 'checked' : '' ?>>
                                    <span class="option-badge"><?= $optLetter ?></span>
                                    <span class="option-text fs-5"><?= e($opt['option_text']) ?></span>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    <?php elseif ($firstQuestion['question_type'] === 'multiple_choice'): ?>
                        <?php foreach ($firstQuestion['options'] as $optIdx => $opt): ?>
                            <?php 
                            $optLetter = $optionLetters[$optIdx] ?? chr(65 + $optIdx);
                            $firstAnsArr = (array)($initialAnswers[$firstQuestion['id']] ?? []);
                            $isOptSelected = in_array($opt['id'], $firstAnsArr) || in_array((string)$opt['id'], array_map('strval', $firstAnsArr), true);
                            ?>
                            <div class="option-item <?= $isOptSelected ? 'selected' : '' ?>" data-opt-id="<?= (int)$opt['id'] ?>">
                                <label class="d-flex align-items-center gap-3 w-100 mb-0 py-1" for="check_opt_<?= (int)$opt['id'] ?>" style="cursor: pointer;">
                                    <input type="checkbox" 
                                           name="question_opt_<?= (int)$firstQuestion['id'] ?>[]" 
                                           id="check_opt_<?= (int)$opt['id'] ?>" 
                                           value="<?= (int)$opt['id'] ?>" 
                                           class="form-check-input exam-input" 
                                           <?= $isOptSelected ? 'checked' : '' ?>>
                                    <span class="option-badge"><?= $optLetter ?></span>
                                    <span class="option-text fs-5"><?= e($opt['option_text']) ?></span>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    <?php elseif ($firstQuestion['question_type'] === 'fill_blank'): ?>
                        <div class="py-3">
                            <label class="form-label text-muted small text-uppercase fw-bold mb-2">
                                Type Your Answer:
                            </label>
                            <input type="text" class="form-control form-control-lg font-monospace fs-4" 
                                   id="fillBlankInput" placeholder="Type answer here..." value="<?= e((string)($initialAnswers[$firstQuestion['id']] ?? '')) ?>" autocomplete="off">
                            <div class="form-text text-muted small mt-2">
                                <i class="fas fa-info-circle me-1"></i> Spelling counts. Case does not matter.
                            </div>
                        </div>
                    <?php elseif ($firstQuestion['question_type'] === 'short_answer'): ?>
                        <div class="py-3">
                            <label class="form-label text-muted small text-uppercase fw-bold mb-2">
                                Type Your Written Response:
                            </label>
                            <textarea class="form-control font-monospace fs-5" id="shortAnswerInput" rows="5" placeholder="Enter your detailed response here..." autocomplete="off"><?= e((string)($initialAnswers[$firstQuestion['id']] ?? '')) ?></textarea>
                            <div class="form-text text-muted small mt-2">
                                <i class="fas fa-feather-alt me-1"></i> Responses are automatically saved as you write and reviewed by the instructor.
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Action Controls -->
                <div class="d-flex justify-content-between align-items-center pt-3 border-top border-secondary border-opacity-25">
                    <button type="button" class="btn btn-secondary px-4 fw-semibold" id="btnPrev" disabled>
                        <i class="fas fa-arrow-left me-1"></i> Previous
                    </button>
                    <button type="button" class="btn btn-primary px-4 fw-semibold" id="btnNext">
                        <?= count($questions) === 1 ? 'Review & Finish <i class="fas fa-flag-checkered ms-1"></i>' : 'Next Question <i class="fas fa-arrow-right ms-1"></i>' ?>
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- RIGHT SIDEBAR PALETTE -->
    <aside class="palette-sidebar">
        <div class="palette-header">
            <h6 class="fw-bold mb-1" style="color: var(--text-primary);">Question Navigator</h6>
            <div class="d-flex gap-3 small text-muted">
                <span><span class="badge bg-success me-1" id="statCountAnswered">0</span> Answered</span>
                <span><span class="badge bg-secondary me-1" id="statCountUnanswered"><?= count($questions) ?></span> Pending</span>
                <span><span class="badge bg-warning me-1" id="statCountFlagged">0</span> Flagged</span>
            </div>
        </div>

        <div class="palette-body">
            <div class="nav-grid" id="questionNavigatorGrid">
                <?php if ($hasQuestions): ?>
                    <?php foreach ($questions as $qIdx => $qItem): ?>
                        <button type="button" class="nav-cell <?= $qIdx === 0 ? 'active unanswered' : 'unanswered' ?>" id="navBtn_<?= (int)$qItem['id'] ?>">
                            <?= $qIdx + 1 ?>
                        </button>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="palette-footer d-grid">
            <button type="button" class="btn btn-danger btn-lg fw-bold py-2" onclick="confirmSubmitExam()">
                <i class="fas fa-check-double me-2"></i> Finish Exam
            </button>
        </div>
    </aside>
</main>

<!-- CONFIRM SUBMISSION MODAL -->
<div class="modal fade" id="confirmSubmitModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold text-warning">
                    <i class="fas fa-triangle-exclamation me-2"></i> Confirm Final Submission
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center py-4">
                <p class="lead mb-4">Are you ready to submit your assessment?</p>
                <div class="d-flex justify-content-center gap-4 mb-4">
                    <div class="p-3 bg-secondary bg-opacity-10 rounded text-center" style="min-width: 120px; border: 1px solid var(--card-border);">
                        <div class="h2 fw-bold text-success mb-0" id="modalSummaryAnswered">0</div>
                        <div class="small text-muted text-uppercase fw-bold">Answered</div>
                    </div>
                    <div class="p-3 bg-secondary bg-opacity-10 rounded text-center" style="min-width: 120px; border: 1px solid var(--card-border);">
                        <div class="h2 fw-bold text-danger mb-0" id="modalSummaryUnanswered">0</div>
                        <div class="small text-muted text-uppercase fw-bold">Unanswered</div>
                    </div>
                </div>
                <p class="text-muted small">
                    Once submitted, your answers will be locked and authoritatively evaluated. You cannot re-enter this exam.
                </p>
            </div>
            <div class="modal-footer justify-content-between">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    Return to Exam
                </button>
                <button type="button" class="btn btn-danger px-4 fw-bold" id="btnConfirmSubmit" onclick="performFinalSubmit()">
                    Yes, Submit Now
                </button>
            </div>
        </div>
    </div>
</div>

<!-- FULLSCREEN REQUIREMENT MODAL -->
<div class="modal fade" id="fullscreenModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-warning">
            <div class="modal-body text-center p-5">
                <div class="text-warning mb-3">
                    <i class="fas fa-expand fa-3x"></i>
                </div>
                <h4 class="fw-bold mb-2">Fullscreen Mode Required</h4>
                <p class="text-muted mb-4">
                    The examination environment requires fullscreen mode. Window shifting and tab changes are prohibited and logged to proctoring records.
                </p>
                <button type="button" class="btn btn-warning btn-lg fw-bold px-5" onclick="requestFullscreenExam()">
                    Re-enter Fullscreen
                </button>
            </div>
        </div>
    </div>
</div>

<!-- PROCTORING VIOLATION ALERT MODAL -->
<div class="modal fade" id="proctoringViolationModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-danger shadow-lg">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title fw-bold">
                    <i class="fas fa-shield-alt me-2"></i> Proctoring Violation Detected
                </h5>
            </div>
            <div class="modal-body text-center p-4">
                <div class="text-danger mb-3">
                    <i class="fas fa-exclamation-triangle fa-3x animate-pulse"></i>
                </div>
                <h5 class="fw-bold text-danger mb-2">Violations Have Been Detected!</h5>
                <p class="lead fs-6 mb-3 text-secondary" id="proctoringViolationMessage">
                    You have navigated away from the assessment window for longer than allowable limit. This incident has been logged. Please return to the exam immediately.
                </p>
                <div class="alert alert-warning py-2 small mb-3 text-start">
                    <i class="fas fa-clock me-1"></i> Away Duration: <strong id="proctoringViolationDuration">0</strong> seconds.<br>
                    <i class="fas fa-info-circle me-1"></i> All proctoring telemetry is recorded in real-time and reviewed by instructors. Repeated violations may result in exam invalidation.
                </div>
                <button type="button" class="btn btn-danger btn-lg fw-bold px-5" onclick="dismissProctoringViolationAlert()">
                    I Understand & Return to Exam
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Inject Exam Configuration -->
<script>
    window.EXAM_CONFIG = <?= json_encode($examConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
</script>
<script src="<?= asset('js/bootstrap.bundle.min.js') ?>?v=<?= time() ?>"></script>
<script src="<?= asset('js/quiz-runner.js') ?>?v=<?= time() ?>"></script>

</body>
</html>
