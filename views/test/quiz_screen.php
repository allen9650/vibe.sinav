<?php
/**
 * PTM Assessment System — Interactive Candidate Examination Screen
 * LAN-optimized, zero-external-CDN, server-authoritative timer, delta autosave, offline recovery.
 */

$assessmentId = (int)($_GET['id'] ?? (Session::get('current_assessment_id') ?? 0));
$candidateId = (int)(Session::get('candidate_id') ?? 0);

if ($candidateId <= 0) {
    Session::flash('error', 'Please log in with your Roll Number first.');
    redirectTo('quiz-login&assessment_id=' . $assessmentId);
}

$candidate = Database::fetch("SELECT * FROM candidates WHERE id = ?", [$candidateId]);
if (!$candidate) {
    Session::flash('error', 'Candidate record not found.');
    redirectTo('quiz-login');
}

// Start or resume candidate attempt
try {
    $sessionData = AssessmentResultService::startOrResumeAttempt($assessmentId, $candidateId);
} catch (Exception $e) {
    Session::flash('error', $e->getMessage());
    redirectTo('quiz-login');
}

$attempt = $sessionData['attempt'];
$attemptId = (int)$attempt['id'];
$assessment = $sessionData['assessment'];
$remainingSeconds = $sessionData['remaining_seconds'];
$serverEndTimeMs = (time() + $remainingSeconds) * 1000;

// Fetch ordered questions
$allQuestions = AssessmentService::getAssessmentQuestions($assessmentId);
if (!empty($attempt['question_order_seed'])) {
    $seedOrder = json_decode($attempt['question_order_seed'], true);
    if (is_array($seedOrder)) {
        $qMap = [];
        foreach ($allQuestions as $q) {
            $qMap[(int)$q['question_id']] = $q;
        }
        $ordered = [];
        foreach ($seedOrder as $qid) {
            if (isset($qMap[$qid])) {
                $ordered[] = $qMap[$qid];
            }
        }
        if (!empty($ordered)) {
            $allQuestions = $ordered;
        }
    }
}

// Prepare questions and options data for client
$questionsClient = [];
foreach ($allQuestions as $idx => $q) {
    $fullQ = QuestionService::getQuestion((int)$q['question_id']);
    $optionsList = [];
    if (!empty($fullQ['options'])) {
        foreach ($fullQ['options'] as $opt) {
            $optionsList[] = [
                'id'           => (int)$opt['id'],
                'option_text'  => $opt['option_text'],
                'match_target' => $opt['match_target'] ?? null,
            ];
        }
        // Shuffle options if enabled
        if (!empty($assessment['randomize_options']) && in_array($q['question_type'], ['single_choice', 'multiple_choice', 'matching'], true)) {
            shuffle($optionsList);
        }
    }

    $typingPara = null;
    if ($q['question_type'] === 'typing' && !empty($fullQ['typing_paragraph'])) {
        $typingPara = [
            'id'         => (int)$fullQ['typing_paragraph']['id'],
            'title'      => $fullQ['typing_paragraph']['title'],
            'content'    => $fullQ['typing_paragraph']['content'],
            'word_count' => (int)$fullQ['typing_paragraph']['word_count'],
        ];
    }

    $questionsClient[] = [
        'id'               => (int)$q['question_id'],
        'order_num'        => $idx + 1,
        'question_text'    => $q['question_text'],
        'question_type'    => $q['question_type'],
        'difficulty'       => $q['difficulty'],
        'marks'            => (float)$q['active_marks'],
        'image_path'       => $fullQ['image_path'] ? asset($fullQ['image_path']) : null,
        'options'          => $optionsList,
        'typing_paragraph' => $typingPara,
    ];
}

// Fetch already saved answers for this attempt (instant recovery)
$savedAnswers = Database::fetchAll("SELECT * FROM assessment_answers WHERE attempt_id = ?", [$attemptId]);
$clientAnswersState = [];
foreach ($savedAnswers as $sa) {
    $clientAnswersState[(int)$sa['question_id']] = [
        'selected_option_ids'  => json_decode($sa['selected_option_ids'] ?? '[]', true) ?: [],
        'text_answer'          => $sa['text_answer'] ?? '',
        'matching_answers'     => json_decode($sa['matching_answers'] ?? '{}', true) ?: (object)[],
        'ordering_answers'     => json_decode($sa['ordering_answers'] ?? '[]', true) ?: [],
        'typing_result_id'     => $sa['typing_result_id'] ? (int)$sa['typing_result_id'] : null,
        'is_marked_for_review' => (bool)$sa['is_marked_for_review'],
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($assessment['title']) ?> — PTM Examination</title>
    <?= getFaviconTag() ?>
    <link rel="stylesheet" href="<?= asset('css/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/fontawesome.min.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
    <style>
        body { background: #0b0f19; color: #f1f5f9; font-family: system-ui, -apple-system, sans-serif; overflow-x: hidden; }
        .exam-header { background: #111827; border-bottom: 2px solid #1f2937; padding: 0.85rem 1.5rem; }
        .timer-badge { font-family: monospace; font-size: 1.35rem; font-weight: 700; letter-spacing: 1px; }
        .timer-warning { color: #f59e0b !important; }
        .timer-danger { color: #ef4444 !important; animation: pulse 1s infinite; }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.6; } }

        .exam-panel { background: #111827; border: 1px solid #1f2937; border-radius: 10px; padding: 1.75rem; min-height: 480px; }
        .nav-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 8px; }
        .nav-btn { height: 42px; font-weight: 600; font-size: 0.95rem; border-radius: 6px; border: 1px solid #374151; background: #1f2937; color: #94a3b8; transition: all 0.15s ease; position: relative; }
        .nav-btn:hover { border-color: #3b82f6; color: #fff; }
        .nav-btn.active { border-color: #3b82f6; background: #1e3a8a; color: #fff; box-shadow: 0 0 0 2px #3b82f6; }
        .nav-btn.answered { background: #065f46; border-color: #10b981; color: #ecfdf5; }
        .nav-btn.reviewed::after { content: '★'; position: absolute; top: 2px; right: 4px; font-size: 0.65rem; color: #f59e0b; }

        .opt-card { background: #1f2937; border: 1px solid #374151; border-radius: 8px; padding: 0.9rem 1.15rem; cursor: pointer; transition: all 0.15s ease; }
        .opt-card:hover { border-color: #4b5563; background: #263346; }
        .opt-card.selected { border-color: #3b82f6; background: #1e293b; box-shadow: 0 0 0 1px #3b82f6; }

        .status-pill { font-size: 0.8rem; padding: 4px 10px; border-radius: 20px; }
    </style>
</head>
<body oncontextmenu="return false;">

<!-- Authoritative Exam Header Bar -->
<header class="exam-header">
    <div class="container-fluid d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="badge bg-primary text-uppercase px-2"><?= e($assessment['code']) ?></span>
                <h5 class="mb-0 fw-bold text-light"><?= e($assessment['title']) ?></h5>
                <span class="badge bg-info text-dark fw-bold px-2 py-1"><i class="fas fa-graduation-cap me-1"></i>Class: <?= e($assessment['class_grade']) ?></span>
            </div>
            <div class="small text-muted d-flex flex-wrap gap-3">
                <span class="text-light"><i class="fas fa-user-graduate me-1 text-warning"></i>Candidate: <strong class="text-light fs-6"><?= e(!empty($attempt['student_name']) ? $attempt['student_name'] : (Session::get('candidate_name') ?: $candidate['full_name'])) ?></strong> <span class="font-monospace text-muted">(<?= e($candidate['roll_number']) ?>)</span></span>
                <span><i class="fas fa-university me-1 text-primary"></i><?= e($assessment['campus_name']) ?></span>
                <span><i class="fas fa-book me-1 text-info"></i><?= e($assessment['subject_name']) ?></span>
                <span><i class="fas fa-user-tie me-1 text-secondary"></i>Teacher: <strong><?= e($assessment['teacher_name']) ?></strong></span>
            </div>
        </div>

        <div class="d-flex align-items-center gap-3">
            <!-- Autosave Network Pill -->
            <div id="saveStatusIndicator" class="status-pill bg-success-subtle text-success border border-success">
                <i class="fas fa-cloud-check me-1"></i> <span id="saveStatusText">All answers saved</span>
            </div>

            <!-- Server Authoritative Countdown Timer -->
            <div class="d-flex align-items-center bg-black px-3 py-2 rounded border border-secondary">
                <i class="fas fa-stopwatch me-2 text-info fs-5"></i>
                <span id="countdownDisplay" class="timer-badge text-light">--:--</span>
            </div>

            <button type="button" class="btn btn-danger btn-sm fw-bold px-3" onclick="confirmSubmission()">
                <i class="fas fa-paper-plane me-1"></i> Submit Test
            </button>
        </div>
    </div>
</header>

<!-- Main Examination Area -->
<main class="container-fluid py-4 px-md-5">
    <div class="row g-4">
        <!-- Center Left: Active Question Card -->
        <div class="col-lg-9">
            <div class="exam-panel d-flex flex-column justify-content-between">
                <!-- Question Top Meta -->
                <div>
                    <div class="d-flex justify-content-between align-items-center mb-3 pb-3 border-bottom border-secondary">
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-primary fs-6 px-3 py-2" id="questionNumberBadge">Question 1 of <?= count($questionsClient) ?></span>
                            <span class="badge bg-dark border border-secondary text-info" id="questionTypeBadge">Single Choice</span>
                        </div>
                        <div class="text-end">
                            <span class="badge bg-success-subtle text-success border border-success" id="questionMarksBadge">+1.00 Mark</span>
                        </div>
                    </div>

                    <!-- Question Statement -->
                    <div class="fs-5 text-light fw-normal mb-4" id="questionStatementText">
                        Loading question...
                    </div>

                    <!-- Question Image (if attached) -->
                    <div id="questionImageContainer" class="mb-4 text-center d-none">
                        <img id="questionImageElement" src="" alt="Question Illustration" class="img-fluid rounded border border-secondary" style="max-height: 260px;">
                    </div>

                    <!-- Interactive Options & Inputs Container -->
                    <div id="interactiveOptionsContainer" class="mb-4">
                        <!-- Rendered dynamically by JavaScript -->
                    </div>
                </div>

                <!-- Navigation Controls Bar -->
                <div class="d-flex justify-content-between align-items-center pt-3 border-top border-secondary mt-4">
                    <div>
                        <button type="button" id="prevBtn" class="btn btn-outline-secondary px-3" onclick="navigateQuestion(-1)">
                            <i class="fas fa-chevron-left me-1"></i> Previous
                        </button>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" id="reviewBtn" class="btn btn-outline-warning px-3" onclick="toggleReviewFlag()">
                            <i class="fas fa-flag me-1"></i> <span id="reviewBtnText">Mark for Review</span>
                        </button>
                        <button type="button" id="nextBtn" class="btn btn-primary px-4 fw-bold" onclick="navigateQuestion(1)">
                            Next <i class="fas fa-chevron-right ms-1"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Side: Question Navigator Palette -->
        <div class="col-lg-3">
            <div class="card bg-dark border-secondary">
                <div class="card-header bg-dark border-secondary p-3">
                    <h6 class="mb-0 fw-bold text-light"><i class="fas fa-th me-2 text-primary"></i>Question Navigator</h6>
                </div>
                <div class="card-body p-3">
                    <div class="nav-grid mb-4" id="navigatorGrid">
                        <!-- Numbered buttons generated by JS -->
                    </div>

                    <!-- Status Legend -->
                    <div class="border-top border-secondary pt-3 small text-muted">
                        <div class="d-flex align-items-center mb-2">
                            <span class="d-inline-block rounded me-2 bg-success" style="width: 14px; height: 14px;"></span>
                            <span class="text-light">Answered (<span id="countAnswered">0</span>)</span>
                        </div>
                        <div class="d-flex align-items-center mb-2">
                            <span class="d-inline-block rounded me-2 bg-secondary" style="width: 14px; height: 14px;"></span>
                            <span class="text-light">Unanswered (<span id="countUnanswered">0</span>)</span>
                        </div>
                        <div class="d-flex align-items-center mb-2">
                            <span class="d-inline-block rounded me-2 bg-warning" style="width: 14px; height: 14px;"></span>
                            <span class="text-light">Marked for Review (<span id="countReview">0</span>)</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Submit Confirmation Modal -->
<div class="modal fade" id="submitModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark border-secondary text-light">
            <div class="modal-header border-secondary">
                <h5 class="modal-title fw-bold"><i class="fas fa-question-circle text-primary me-2"></i>Confirm Test Submission</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 text-center">
                <p class="text-light mb-3">Are you sure you want to finish and submit your assessment?</p>
                <div class="d-flex justify-content-center gap-3 p-3 bg-black rounded border border-secondary mb-3">
                    <div><div class="fw-bold text-success fs-5" id="modalAnsweredCount">0</div><div class="small text-muted">Answered</div></div>
                    <div class="border-start border-secondary mx-2"></div>
                    <div><div class="fw-bold text-warning fs-5" id="modalReviewCount">0</div><div class="small text-muted">For Review</div></div>
                    <div class="border-start border-secondary mx-2"></div>
                    <div><div class="fw-bold text-danger fs-5" id="modalUnansweredCount">0</div><div class="small text-muted">Unanswered</div></div>
                </div>
                <div class="text-danger small"><i class="fas fa-exclamation-triangle me-1"></i> Once submitted, your answers cannot be altered.</div>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Resume Test</button>
                <button type="button" class="btn btn-danger fw-bold" onclick="performFinalSubmit()">Yes, Submit My Test</button>
            </div>
        </div>
    </div>
</div>

<!-- Fullscreen Requirement Modal -->
<div class="modal fade" id="fullscreenModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark border-danger text-light text-center p-4">
            <i class="fas fa-expand-arrows-alt fa-3x text-warning mb-3"></i>
            <h5 class="fw-bold text-light">Fullscreen Required</h5>
            <p class="small text-muted mb-4">This assessment requires fullscreen mode for proctoring integrity. Please click the button below to resume fullscreen.</p>
            <button type="button" class="btn btn-primary fw-bold" onclick="enterFullscreen()">
                <i class="fas fa-expand me-1"></i> Enter Fullscreen Mode
            </button>
        </div>
    </div>
</div>

<script>
// Authoritative Server Context
const ATTEMPT_ID = <?= $attemptId ?>;
const ASSESSMENT_ID = <?= $assessmentId ?>;
const SERVER_END_TIME_MS = <?= $serverEndTimeMs ?>;
const FULLSCREEN_REQUIRED = <?= !empty($assessment['fullscreen_required']) ? 'true' : 'false' ?>;
const MAX_VIOLATIONS = <?= (int)$assessment['max_violations'] ?>;
const VIOLATION_ACTION = "<?= e($assessment['violation_action']) ?>";
const ALLOW_BACKTRACK = <?= !empty($assessment['allow_backtrack']) ? 'true' : 'false' ?>;

// Client Questions & Saved Answers
const questions = <?= json_encode($questionsClient) ?>;
const answers = <?= json_encode((object)$clientAnswersState) ?>;

let currentIndex = 0;
let dirtyQuestions = new Set();
let violationsCount = 0;
let isSubmitting = false;

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    initNavigator();
    loadQuestion(0);
    startAuthoritativeTimer();
    initAutosaveTimer();
    initAntiCheatingTelemetry();

    if (FULLSCREEN_REQUIRED) {
        setTimeout(enterFullscreen, 500);
    }
});

// Render question navigator palette
function initNavigator() {
    const grid = document.getElementById('navigatorGrid');
    grid.innerHTML = '';
    questions.forEach((q, idx) => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'nav-btn';
        btn.id = 'navBtn_' + idx;
        btn.textContent = idx + 1;
        btn.onclick = () => jumpToQuestion(idx);
        grid.appendChild(btn);
    });
    updateNavigatorVisuals();
}

function updateNavigatorVisuals() {
    let answered = 0;
    let reviewed = 0;

    questions.forEach((q, idx) => {
        const btn = document.getElementById('navBtn_' + idx);
        if (!btn) return;

        btn.className = 'nav-btn';
        if (idx === currentIndex) btn.classList.add('active');

        const ans = answers[q.id];
        let hasAnswer = false;

        if (ans) {
            if (q.question_type === 'single_choice' || q.question_type === 'true_false' || q.question_type === 'yes_no') {
                hasAnswer = ans.selected_option_ids && ans.selected_option_ids.length > 0;
            } else if (q.question_type === 'multiple_choice') {
                hasAnswer = ans.selected_option_ids && ans.selected_option_ids.length > 0;
            } else if (q.question_type === 'fill_blank') {
                hasAnswer = ans.text_answer && ans.text_answer.trim() !== '';
            } else if (q.question_type === 'matching') {
                hasAnswer = ans.matching_answers && Object.keys(ans.matching_answers).length > 0;
            } else if (q.question_type === 'ordering') {
                hasAnswer = ans.ordering_answers && ans.ordering_answers.length > 0;
            } else if (q.question_type === 'short_answer') {
                hasAnswer = ans.text_answer && ans.text_answer.trim() !== '';
            } else if (q.question_type === 'typing') {
                hasAnswer = !!ans.typing_result_id;
            }

            if (ans.is_marked_for_review) {
                btn.classList.add('reviewed');
                reviewed++;
            }
        }

        if (hasAnswer) {
            btn.classList.add('answered');
            answered++;
        }
    });

    document.getElementById('countAnswered').textContent = answered;
    document.getElementById('countUnanswered').textContent = questions.length - answered;
    document.getElementById('countReview').textContent = reviewed;
}

// Display Question by Index
function loadQuestion(idx) {
    if (idx < 0 || idx >= questions.length) return;

    // Save pending delta from current question before moving
    saveDirtyAnswers();

    currentIndex = idx;
    const q = questions[idx];

    document.getElementById('questionNumberBadge').textContent = `Question ${idx + 1} of ${questions.length}`;
    document.getElementById('questionTypeBadge').textContent = formatQuestionType(q.question_type);
    document.getElementById('questionMarksBadge').textContent = `+${q.marks} Marks`;
    document.getElementById('questionStatementText').innerHTML = escapeHtml(q.question_text).replace(/\n/g, '<br>');

    // Handle Image
    const imgContainer = document.getElementById('questionImageContainer');
    const imgElement = document.getElementById('questionImageElement');
    if (q.image_path) {
        imgElement.src = q.image_path;
        imgContainer.classList.remove('d-none');
    } else {
        imgContainer.classList.add('d-none');
    }

    // Previous / Next Button States
    const prevBtn = document.getElementById('prevBtn');
    if (!ALLOW_BACKTRACK) {
        prevBtn.disabled = true;
    } else {
        prevBtn.disabled = (idx === 0);
    }
    document.getElementById('nextBtn').textContent = (idx === questions.length - 1) ? 'Review & Submit' : 'Next →';

    // Review Button State
    const currentAns = answers[q.id] || {};
    const isReview = !!currentAns.is_marked_for_review;
    document.getElementById('reviewBtnText').textContent = isReview ? 'Remove Review Flag' : 'Mark for Review';

    // Render Question Inputs
    renderQuestionInputs(q);
    updateNavigatorVisuals();
}

function renderQuestionInputs(q) {
    const container = document.getElementById('interactiveOptionsContainer');
    container.innerHTML = '';
    const ans = answers[q.id] || { selected_option_ids: [] };

    switch (q.question_type) {
        case 'single_choice':
        case 'true_false':
        case 'yes_no':
            q.options.forEach(opt => {
                const isSelected = ans.selected_option_ids && ans.selected_option_ids.includes(opt.id);
                const card = document.createElement('div');
                card.className = 'opt-card mb-2 d-flex align-items-center gap-3 ' + (isSelected ? 'selected' : '');
                card.innerHTML = `
                    <i class="far ${isSelected ? 'fa-dot-circle text-primary' : 'fa-circle text-muted'}"></i>
                    <span class="text-light">${escapeHtml(opt.option_text)}</span>
                `;
                card.onclick = () => {
                    selectSingleChoice(q.id, opt.id);
                };
                container.appendChild(card);
            });
            break;

        case 'multiple_choice':
            q.options.forEach(opt => {
                const isSelected = ans.selected_option_ids && ans.selected_option_ids.includes(opt.id);
                const card = document.createElement('div');
                card.className = 'opt-card mb-2 d-flex align-items-center gap-3 ' + (isSelected ? 'selected' : '');
                card.innerHTML = `
                    <i class="far ${isSelected ? 'fa-check-square text-primary' : 'fa-square text-muted'}"></i>
                    <span class="text-light">${escapeHtml(opt.option_text)}</span>
                `;
                card.onclick = () => {
                    toggleMultipleChoice(q.id, opt.id);
                };
                container.appendChild(card);
            });
            break;

        case 'fill_blank':
            const textVal = ans.text_answer || '';
            container.innerHTML = `
                <div class="alert alert-info py-2 px-3 small border-0 bg-info bg-opacity-10 text-info mb-3">
                    <i class="fas fa-info-circle me-1"></i> <strong>Instruction:</strong> Please type your answer entirely in <strong>small case / lowercase letters</strong>.
                </div>
                <div class="mb-3">
                    <label class="form-label text-muted small text-uppercase fw-bold">Type Your Answer (in lowercase)</label>
                    <input type="text" class="form-control form-control-lg bg-dark text-light border-secondary font-monospace" 
                           id="fillBlankInput" placeholder="type in lowercase..." value="${escapeHtml(textVal)}"
                           oninput="updateFillBlankAnswer(${q.id}, this.value)">
                </div>
            `;
            break;

        case 'short_answer':
            const shortVal = ans.text_answer || '';
            container.innerHTML = `
                <div class="alert alert-secondary py-2 px-3 small border-0 bg-dark text-muted mb-3">
                    <i class="fas fa-pen-nib text-primary me-1"></i> <strong>Subjective Question:</strong> Write your response below. Your teacher will evaluate and award marks up to <strong>${q.marks} marks</strong>.
                </div>
                <div class="mb-3">
                    <label class="form-label text-muted small text-uppercase fw-bold">Your Written Answer</label>
                    <textarea class="form-control bg-dark text-light border-secondary" rows="6" 
                              id="shortAnswerInput" placeholder="Type your detailed answer here..." 
                              oninput="updateShortAnswer(${q.id}, this.value)">${escapeHtml(shortVal)}</textarea>
                    <div class="d-flex justify-content-between text-muted small mt-1">
                        <span>Teacher manual review is required for this question.</span>
                        <span id="charCount_${q.id}">${shortVal.length} characters</span>
                    </div>
                </div>
            `;
            break;

        case 'matching':
            container.innerHTML = '<p class="small text-muted mb-2">Match each premise with its corresponding target:</p>';
            const matchedAnswers = ans.matching_answers || {};
            // Get all target values
            const allTargets = q.options.map(o => o.match_target).filter(Boolean);

            q.options.forEach(opt => {
                const currentVal = matchedAnswers[opt.id] || '';
                const row = document.createElement('div');
                row.className = 'row g-2 align-items-center mb-2 p-2 bg-dark rounded border border-secondary';
                
                let selectHtml = `<select class="form-select form-select-sm bg-dark text-light border-secondary" onchange="updateMatchingAnswer(${q.id}, ${opt.id}, this.value)">
                                    <option value="">-- Select Match --</option>`;
                allTargets.forEach(tgt => {
                    selectHtml += `<option value="${escapeHtml(tgt)}" ${currentVal === tgt ? 'selected' : ''}>${escapeHtml(tgt)}</option>`;
                });
                selectHtml += `</select>`;

                row.innerHTML = `
                    <div class="col-6 fw-semibold text-light">${escapeHtml(opt.option_text)}</div>
                    <div class="col-6">${selectHtml}</div>
                `;
                container.appendChild(row);
            });
            break;

        case 'ordering':
            container.innerHTML = '<p class="small text-muted mb-2">Select the sequence position for each step:</p>';
            const orderedList = ans.ordering_answers || [];
            q.options.forEach((opt, oIdx) => {
                const currentPos = orderedList.indexOf(opt.id) + 1;
                const row = document.createElement('div');
                row.className = 'input-group input-group-sm mb-2';
                row.innerHTML = `
                    <span class="input-group-text bg-secondary border-secondary text-light fw-bold">${oIdx + 1}</span>
                    <input type="text" class="form-control bg-dark text-light border-secondary" value="${escapeHtml(opt.option_text)}" readonly>
                    <select class="form-select bg-dark text-light border-secondary" style="max-width: 120px;" onchange="updateOrderingAnswer(${q.id}, ${opt.id}, this.value)">
                        <option value="">Order...</option>
                        ${q.options.map((_, i) => `<option value="${i + 1}" ${currentPos === (i + 1) ? 'selected' : ''}>Step ${i + 1}</option>`).join('')}
                    </select>
                `;
                container.appendChild(row);
            });
            break;

        case 'typing':
            container.innerHTML = `
                <div class="p-3 bg-dark border border-warning rounded mb-3 font-monospace small">
                    <div class="text-warning fw-bold mb-2"><i class="fas fa-keyboard me-1"></i> Typing Speed Test Section</div>
                    <div class="p-3 bg-black rounded border border-secondary mb-3 text-light lh-lg" style="user-select: none;">
                        ${escapeHtml(q.typing_paragraph ? q.typing_paragraph.content : 'Sample typing paragraph text...')}
                    </div>
                    <textarea class="form-control bg-dark text-light border-secondary font-monospace" rows="4" 
                              placeholder="Start typing the passage here..." 
                              oninput="recordTypingInput(${q.id}, this.value)"></textarea>
                    <div class="form-text text-muted small mt-1">Typing speed and accuracy are calculated using Levenshtein alignment upon submission.</div>
                </div>
            `;
            break;
    }
}

// Answer updating helpers
function selectSingleChoice(qid, optId) {
    if (!answers[qid]) answers[qid] = {};
    answers[qid].selected_option_ids = [optId];
    dirtyQuestions.add(qid);
    loadQuestion(currentIndex);
}

function toggleMultipleChoice(qid, optId) {
    if (!answers[qid]) answers[qid] = { selected_option_ids: [] };
    let list = answers[qid].selected_option_ids || [];
    if (list.includes(optId)) {
        list = list.filter(id => id !== optId);
    } else {
        list.push(optId);
    }
    answers[qid].selected_option_ids = list;
    dirtyQuestions.add(qid);
    loadQuestion(currentIndex);
}

function updateFillBlankAnswer(qid, val) {
    if (!answers[qid]) answers[qid] = {};
    answers[qid].text_answer = val;
    dirtyQuestions.add(qid);
    updateNavigatorVisuals();
}

function updateShortAnswer(qid, val) {
    if (!answers[qid]) answers[qid] = {};
    answers[qid].text_answer = val;
    dirtyQuestions.add(qid);
    const counter = document.getElementById('charCount_' + qid);
    if (counter) counter.textContent = val.length + ' characters';
    updateNavigatorVisuals();
}

function updateMatchingAnswer(qid, optId, targetVal) {
    if (!answers[qid]) answers[qid] = { matching_answers: {} };
    if (!answers[qid].matching_answers) answers[qid].matching_answers = {};
    answers[qid].matching_answers[optId] = targetVal;
    dirtyQuestions.add(qid);
    updateNavigatorVisuals();
}

function updateOrderingAnswer(qid, optId, stepNum) {
    if (!answers[qid]) answers[qid] = { ordering_answers: [] };
    let list = answers[qid].ordering_answers || [];
    list = list.filter(id => id !== optId);
    if (stepNum) {
        list.splice(parseInt(stepNum) - 1, 0, optId);
    }
    answers[qid].ordering_answers = list;
    dirtyQuestions.add(qid);
    updateNavigatorVisuals();
}

function recordTypingInput(qid, val) {
    if (!answers[qid]) answers[qid] = {};
    answers[qid].text_answer = val;
    dirtyQuestions.add(qid);
    updateNavigatorVisuals();
}

function toggleReviewFlag() {
    const qid = questions[currentIndex].id;
    if (!answers[qid]) answers[qid] = {};
    answers[qid].is_marked_for_review = !answers[qid].is_marked_for_review;
    dirtyQuestions.add(qid);
    loadQuestion(currentIndex);
}

function navigateQuestion(delta) {
    const nextIdx = currentIndex + delta;
    if (nextIdx >= questions.length) {
        confirmSubmission();
    } else {
        loadQuestion(nextIdx);
    }
}

function jumpToQuestion(idx) {
    loadQuestion(idx);
}

// Lightweight Delta Autosave
function initAutosaveTimer() {
    setInterval(saveDirtyAnswers, 5000);
}

function saveDirtyAnswers() {
    if (dirtyQuestions.size === 0 || isSubmitting) return;

    const idsToSave = Array.from(dirtyQuestions);
    dirtyQuestions.clear();

    const indicator = document.getElementById('saveStatusIndicator');
    const text = document.getElementById('saveStatusText');
    indicator.className = 'status-pill bg-warning-subtle text-warning border border-warning';
    text.textContent = 'Saving...';

    // Send delta for each dirty question
    idsToSave.forEach(qid => {
        const payload = {
            attempt_id: ATTEMPT_ID,
            question_id: qid,
            answer_data: answers[qid] || {}
        };

        fetch('<?= url("api-quiz-autosave") ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                indicator.className = 'status-pill bg-success-subtle text-success border border-success';
                text.textContent = 'Saved ' + data.saved_at;
            }
        })
        .catch(() => {
            // Local fallback on network interruption
            indicator.className = 'status-pill bg-danger-subtle text-danger border border-danger';
            text.textContent = 'Offline (Saved locally)';
            dirtyQuestions.add(qid);
            localStorage.setItem('ptm_cache_' + ATTEMPT_ID, JSON.stringify(answers));
        });
    });
}

// Authoritative Timer
function startAuthoritativeTimer() {
    const timerDisplay = document.getElementById('countdownDisplay');

    function tick() {
        const remaining = Math.max(0, Math.floor((SERVER_END_TIME_MS - Date.now()) / 1000));
        const mins = Math.floor(remaining / 60);
        const secs = remaining % 60;
        timerDisplay.textContent = String(mins).padStart(2, '0') + ':' + String(secs).padStart(2, '0');

        if (remaining <= 60) {
            timerDisplay.className = 'timer-badge timer-danger';
        } else if (remaining <= 300) {
            timerDisplay.className = 'timer-badge timer-warning';
        }

        if (remaining <= 0) {
            performFinalSubmit(true);
        }
    }

    tick();
    setInterval(tick, 1000);
}

// Anti-Cheating Telemetry
function initAntiCheatingTelemetry() {
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            logViolation('TAB_SWITCH', { detail: 'Candidate switched browser tab' });
        }
    });

    window.addEventListener('blur', function() {
        logViolation('WINDOW_BLUR', { detail: 'Window lost focus' });
    });

    document.addEventListener('fullscreenchange', function() {
        if (!document.fullscreenElement && FULLSCREEN_REQUIRED) {
            logViolation('FULLSCREEN_EXIT', { detail: 'Exited fullscreen mode' });
            new bootstrap.Modal(document.getElementById('fullscreenModal')).show();
        }
    });

    ['copy', 'cut', 'paste'].forEach(evt => {
        document.addEventListener(evt, function(e) {
            e.preventDefault();
            logViolation(evt.toUpperCase() + '_ATTEMPT', { detail: 'Blocked clipboard attempt' });
        });
    });
}

function enterFullscreen() {
    const elem = document.documentElement;
    if (elem.requestFullscreen) {
        elem.requestFullscreen().then(() => {
            const modal = bootstrap.Modal.getInstance(document.getElementById('fullscreenModal'));
            if (modal) modal.hide();
        }).catch(() => {});
    }
}

function logViolation(type, meta) {
    violationsCount++;
    fetch('<?= url("api-quiz-telemetry") ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            attempt_id: ATTEMPT_ID,
            event_type: type,
            metadata: meta
        })
    }).then(res => res.json()).then(data => {
        if (data.violations_count >= MAX_VIOLATIONS && VIOLATION_ACTION === 'auto_disqualify') {
            alert('Maximum security violation threshold exceeded. Your test is being terminated.');
            performFinalSubmit(true);
        }
    }).catch(() => {});
}

// Final Submission
function confirmSubmission() {
    saveDirtyAnswers();
    let answered = 0;
    let reviewed = 0;

    questions.forEach(q => {
        const a = answers[q.id];
        if (a) {
            if ((a.selected_option_ids && a.selected_option_ids.length > 0) ||
                (a.text_answer && a.text_answer.trim() !== '') ||
                (a.matching_answers && Object.keys(a.matching_answers).length > 0) ||
                (a.ordering_answers && a.ordering_answers.length > 0) ||
                a.typing_result_id) {
                answered++;
            }
            if (a.is_marked_for_review) reviewed++;
        }
    });

    document.getElementById('modalAnsweredCount').textContent = answered;
    document.getElementById('modalReviewCount').textContent = reviewed;
    document.getElementById('modalUnansweredCount').textContent = questions.length - answered;

    new bootstrap.Modal(document.getElementById('submitModal')).show();
}

function performFinalSubmit(isTimeout = false) {
    if (isSubmitting) return;
    isSubmitting = true;

    // Show loading indicator on modal button if open
    const submitModalBtn = document.querySelector('#submitModal .btn-danger');
    if (submitModalBtn) {
        submitModalBtn.disabled = true;
        submitModalBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Submitting...';
    }

    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '<?= url("quiz-submit") ?>';

    const pageInput = document.createElement('input');
    pageInput.type = 'hidden';
    pageInput.name = 'page';
    pageInput.value = 'quiz-submit';
    form.appendChild(pageInput);

    const csrfInput = document.createElement('input');
    csrfInput.type = 'hidden';
    csrfInput.name = 'csrf_token';
    csrfInput.value = '<?= CSRF::token() ?>';
    form.appendChild(csrfInput);

    const attemptInput = document.createElement('input');
    attemptInput.type = 'hidden';
    attemptInput.name = 'attempt_id';
    attemptInput.value = ATTEMPT_ID;
    form.appendChild(attemptInput);

    // Pass all answers payload to guarantee zero answer loss on final submit
    const answersInput = document.createElement('input');
    answersInput.type = 'hidden';
    answersInput.name = 'all_answers_payload';
    answersInput.value = JSON.stringify(answers);
    form.appendChild(answersInput);

    if (isTimeout) {
        const timeoutInput = document.createElement('input');
        timeoutInput.type = 'hidden';
        timeoutInput.name = 'is_timeout';
        timeoutInput.value = '1';
        form.appendChild(timeoutInput);
    }

    document.body.appendChild(form);
    form.submit();
}

function formatQuestionType(type) {
    const map = {
        'single_choice': 'Single Choice',
        'multiple_choice': 'Multiple Choice',
        'true_false': 'True / False',
        'yes_no': 'Yes / No',
        'fill_blank': 'Fill in the Blank',
        'matching': 'Matching Pairs',
        'ordering': 'Sequence Order',
        'typing': 'Typing Test',
        'short_answer': 'Question Answer (Teacher Graded)'
    };
    return map[type] || type;
}

function escapeHtml(text) {
    const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
    return String(text || '').replace(/[&<>"']/g, m => map[m]);
}
</script>

<script src="<?= asset('js/bootstrap.bundle.min.js') ?>"></script>
</body>
</html>

