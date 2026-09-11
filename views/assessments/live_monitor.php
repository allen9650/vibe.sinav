<?php
declare(strict_types=1);

/**
 * PTM Assessment System — Centralized Live Lab & Exam Proctoring Monitor
 * Real-time LAN telemetry: Workstation PC Name, Candidate Roll & Name, Attempting Currently,
 * Time Details, Window Shifts / Tab Switches, Total Correct & Score, Subjective Review Modal,
 * Full Questions Inspection Modal, and One-Click "Publish All Results" Toggle.
 */

$id = (int)($_GET['id'] ?? 0);

// Fetch all assessments for the switcher dropdown
$allAssessments = Database::fetchAll(
    "SELECT a.id, a.title, a.target_class, a.status, a.is_result_published, sub.name AS subject_name
     FROM assessments a
     LEFT JOIN subjects sub ON a.subject_id = sub.id
     ORDER BY a.id DESC"
);

// If no specific ID requested, auto-select the latest published or latest assessment
if ($id <= 0 && !empty($allAssessments)) {
    foreach ($allAssessments as $ass) {
        if ($ass['status'] === 'published') {
            $id = (int)$ass['id'];
            break;
        }
    }
    if ($id <= 0) {
        $id = (int)$allAssessments[0]['id'];
    }
}

$assessment = null;
if ($id > 0) {
    $assessment = AssessmentService::getAssessment($id);
}

// Handle POST actions if submitted via standard form fallback
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    CSRF::validateOrFail();
    $postAction = $_POST['action'] ?? '';
    
    if ($postAction === 'disqualify_candidate') {
        $attemptId = (int)$_POST['attempt_id'];
        Database::update('assessment_attempts', ['status' => 'disqualified'], 'id = ?', [$attemptId]);
        AuditLog::log('candidate_disqualified', 'assessments', "Teacher disqualified candidate attempt #$attemptId on assessment #$id", Auth::id());
        Session::flash('warning', "Candidate attempt #$attemptId was disqualified.");
        redirectTo('monitoring' . ($id > 0 ? '&id=' . $id : ''));
    }
    
    if ($postAction === 'reset_candidate_attempt') {
        if (!Auth::hasPermission('results.delete')) {
            Session::flash('error', 'Permission denied: You do not have permission to reset or delete candidate attempts.');
            redirectTo('monitoring' . ($id > 0 ? '&id=' . $id : ''));
        }
        $attemptId = (int)($_POST['attempt_id'] ?? 0);
        if ($attemptId > 0) {
            Database::delete('assessment_results', 'attempt_id = ?', [$attemptId]);
            Database::delete('assessment_answers', 'attempt_id = ?', [$attemptId]);
            Database::delete('security_events', 'attempt_id = ?', [$attemptId]);
            Database::delete('assessment_attempts', 'id = ?', [$attemptId]);
            AuditLog::log('attempt_reset', 'assessment_attempts', "Reset candidate attempt #$attemptId on assessment #$id", Auth::id() ?: 1);
            Session::flash('success', "Candidate attempt #$attemptId has been wiped and reset.");
            redirectTo('monitoring' . ($id > 0 ? '&id=' . $id : ''));
        }
    }
    
    if ($postAction === 'toggle_publish_all' && $assessment) {
        if (!Auth::hasPermission('assessments.publish')) {
            Session::flash('error', 'Permission denied: You do not have permission to publish or withhold results.');
            redirectTo('monitoring' . ($id > 0 ? '&id=' . $id : ''));
        }
        $currentPublished = ((int)$assessment['is_result_published'] === 1);
        $newStatus = !$currentPublished;
        AssessmentService::toggleResultPublish($id, $newStatus);
        Session::flash('success', $newStatus ? 'All results for this assessment have been published.' : 'Results have been withheld from candidates.');
        redirectTo('monitoring&id=' . $id);
    }
}
?>

<?php if (empty($allAssessments)): ?>
    <div class="card border-secondary text-center p-5 my-4">
        <div class="py-4">
            <i class="fas fa-satellite-dish text-muted fa-3x mb-3"></i>
            <h4 class="fw-bold">No Assessments Available</h4>
            <p class="text-muted">Create an assessment and launch it to begin live lab proctoring and workstation monitoring.</p>
            <a href="<?= url('assessments-create') ?>" class="btn btn-primary">
                <i class="fas fa-plus-circle me-1"></i> Create Assessment
            </a>
        </div>
    </div>
<?php else: ?>

<!-- Header & Controls Bar -->
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
            <span class="badge bg-danger px-2.5 py-1.5 fs-7"><i class="fas fa-satellite-dish me-1"></i>LIVE PROCTOR</span>
            <h4 class="mb-0 fw-bold" id="examTitleHeading"><?= e($assessment['title'] ?? 'Assessment Monitor') ?></h4>
            <?php if (!empty($assessment['class_grade'])): ?>
                <span class="badge bg-secondary"><?= e($assessment['class_grade']) ?></span>
            <?php endif; ?>
        </div>
        <p class="text-muted small mb-0">
            <span id="examMetaText">
                <?= e($assessment['campus_name'] ?? '') ?> • <?= e($assessment['subject_name'] ?? '') ?> • Teacher: <strong><?= e($assessment['teacher_name'] ?? '') ?></strong>
            </span>
        </p>
    </div>

    <div class="d-flex flex-wrap align-items-center gap-2">
        <!-- Assessment Switcher Dropdown -->
        <div class="input-group input-group-sm" style="width: auto; min-width: 220px;">
            <label class="input-group-text bg-dark border-secondary text-muted small"><i class="fas fa-list-check"></i></label>
            <select class="form-select form-select-sm bg-dark text-light border-secondary" id="assessmentSelect" onchange="switchAssessment(this.value)">
                <?php foreach ($allAssessments as $aItem): ?>
                    <option value="<?= (int)$aItem['id'] ?>" <?= ((int)$aItem['id'] === $id) ? 'selected' : '' ?>>
                        <?= e($aItem['title']) ?> [<?= strtoupper($aItem['status']) ?>]
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Live Status Pill -->
        <span class="badge bg-success-subtle text-success border border-success px-3 py-2">
            <span class="spinner-grow spinner-grow-sm text-success me-1" role="status" style="width: 8px; height: 8px;"></span>
            <span id="liveStatusText">Live Feed Active (5s)</span>
        </span>

        <!-- Refresh Now Button -->
        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="fetchLiveUpdates()">
            <i class="fas fa-sync-alt me-1"></i> Refresh
        </button>

        <!-- Publish All Results Button -->
        <button type="button" class="btn btn-sm <?= ((int)($assessment['is_result_published'] ?? 0) === 1) ? 'btn-outline-warning' : 'btn-success' ?>" id="btnPublishToggle" onclick="togglePublishAll()">
            <i class="fas <?= ((int)($assessment['is_result_published'] ?? 0) === 1) ? 'fa-lock' : 'fa-bullhorn' ?> me-1"></i>
            <span id="btnPublishText"><?= ((int)($assessment['is_result_published'] ?? 0) === 1) ? 'Unpublish All' : 'Publish All Results' ?></span>
        </button>

        <!-- Quick Links -->
        <?php if ($id > 0): ?>
            <a href="<?= url('assessments-devices&id=' . $id) ?>" class="btn btn-outline-primary btn-sm" title="Manage PC Allocations">
                <i class="fas fa-network-wired me-1"></i> Device Allocation
            </a>
            <a href="<?= url('assessments-results&id=' . $id) ?>" class="btn btn-outline-info btn-sm" title="View Full Ledger">
                <i class="fas fa-poll me-1"></i> Results Ledger
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- Publication Status Banner Strip -->
<div class="card border mb-4 py-2 px-3 shadow-sm" style="background: var(--bg-surface, #1e222d);">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div class="d-flex align-items-center gap-2">
            <span class="small fw-semibold text-muted text-uppercase">Scorecard Release Status:</span>
            <span class="badge <?= ((int)($assessment['is_result_published'] ?? 0) === 1) ? 'bg-success' : 'bg-warning text-dark' ?>" id="publicationStatusBadge">
                <i class="fas <?= ((int)($assessment['is_result_published'] ?? 0) === 1) ? 'fa-check-circle' : 'fa-clock' ?> me-1"></i>
                <span id="publicationStatusText"><?= ((int)($assessment['is_result_published'] ?? 0) === 1) ? 'Published to Candidates (Instant Scorecard Available)' : 'Withheld (Hidden from Candidates until released)' ?></span>
            </span>
        </div>
        <div class="small text-muted">
            Workstations Synced: <span class="font-monospace fw-bold text-info" id="lastSyncTime">--:--:--</span>
        </div>
    </div>
</div>

<!-- 5 Overview Telemetry Counters -->
<div class="row g-3 mb-4">
    <div class="col-xl-2 col-md-4 col-6">
        <div class="card border p-3 text-center shadow-sm h-100">
            <div class="text-muted small text-uppercase fw-bold mb-1">Attempting Currently</div>
            <div class="fs-2 fw-bold text-primary d-flex align-items-center justify-content-center gap-2">
                <span class="spinner-grow spinner-grow-sm text-primary" style="width: 10px; height: 10px;"></span>
                <span id="statActive">0</span>
            </div>
            <div class="text-muted small">In Progress</div>
        </div>
    </div>
    <div class="col-xl-2 col-md-4 col-6">
        <div class="card border p-3 text-center shadow-sm h-100">
            <div class="text-muted small text-uppercase fw-bold mb-1">Completed / Submitted</div>
            <div class="fs-2 fw-bold text-success" id="statCompleted">0</div>
            <div class="text-muted small">Exams Turned In</div>
        </div>
    </div>
    <div class="col-xl-3 col-md-4 col-6">
        <div class="card border p-3 text-center shadow-sm h-100">
            <div class="text-muted small text-uppercase fw-bold mb-1">Subjective Review</div>
            <div class="fs-2 fw-bold text-warning" id="statPendingReview">0</div>
            <div class="text-muted small">Pending Teacher Evaluation</div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6 col-6">
        <div class="card border p-3 text-center shadow-sm h-100">
            <div class="text-muted small text-uppercase fw-bold mb-1">Security Violations</div>
            <div class="fs-2 fw-bold text-danger" id="statViolations">0</div>
            <div class="text-muted small">Blurs & Tab Switches</div>
        </div>
    </div>
    <div class="col-xl-2 col-md-6 col-12">
        <div class="card border p-3 text-center shadow-sm h-100">
            <div class="text-muted small text-uppercase fw-bold mb-1">Lab Workstations</div>
            <div class="fs-2 fw-bold text-info" id="statWorkstations">0</div>
            <div class="text-muted small">Connected PCs</div>
        </div>
    </div>
</div>

<!-- Candidate Workstation Telemetry Table -->
<div class="card border shadow-sm">
    <div class="card-header border-bottom p-3 d-flex justify-content-between align-items-center">
        <h6 class="mb-0 fw-bold">
            <i class="fas fa-desktop me-2 text-primary"></i>Workstation Telemetry & Proctoring Ledger
        </h6>
        <div class="d-flex align-items-center gap-2">
            <input type="text" id="filterInput" class="form-control form-control-sm bg-dark text-light border-secondary" placeholder="Filter candidate, roll, PC..." onkeyup="filterTable()" style="width: 220px;">
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" id="liveCandidateTable">
            <thead class="border-bottom text-muted small text-uppercase">
                <tr>
                    <th style="min-width: 100px;">PC Name</th>
                    <th style="min-width: 110px;">Roll Number</th>
                    <th style="min-width: 160px;">Candidate Name</th>
                    <th style="min-width: 140px;">Status / Attempting</th>
                    <th style="min-width: 150px;">Time Details</th>
                    <th style="min-width: 140px;">Violations</th>
                    <th style="min-width: 150px;">Total Correct & Score</th>
                    <th style="min-width: 180px;">Review & Inspection</th>
                    <th class="text-end" style="min-width: 100px;">Actions</th>
                </tr>
            </thead>
            <tbody id="candidateRowsContainer">
                <tr>
                    <td colspan="9" class="text-center py-5 text-muted">
                        <div class="spinner-border spinner-border-sm text-primary me-2"></div>
                        Connecting to lab workstations telemetry feed...
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- ============================================================= -->
<!-- MODAL: Subjective Question Review & Grading                   -->
<!-- ============================================================= -->
<div class="modal fade" id="reviewModal" tabindex="-1" aria-labelledby="reviewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-secondary shadow-lg">
            <div class="modal-header border-secondary">
                <div>
                    <h5 class="modal-title fw-bold" id="reviewModalLabel">
                        <i class="fas fa-clipboard-check text-warning me-2"></i>Subjective Response Evaluation
                    </h5>
                    <div class="small text-muted" id="reviewModalSubtitle">Evaluating Candidate Responses</div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="reviewModalBody">
                <div class="text-center py-5 text-muted">
                    <div class="spinner-border text-warning me-2"></div>
                    Loading subjective questions...
                </div>
            </div>
            <div class="modal-footer border-secondary justify-content-between">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-warning" onclick="submitReviewGrades(false)">
                        <i class="fas fa-save me-1"></i> Save Marks
                    </button>
                    <button type="button" class="btn btn-warning fw-bold text-dark" onclick="submitReviewGrades(true)">
                        <i class="fas fa-bullhorn me-1"></i> Save & Release Result
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================= -->
<!-- MODAL: Attempt Questions & Full Results Inspection             -->
<!-- ============================================================= -->
<div class="modal fade" id="inspectionModal" tabindex="-1" aria-labelledby="inspectionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-secondary shadow-lg">
            <div class="modal-header border-secondary">
                <div>
                    <h5 class="modal-title fw-bold" id="inspectionModalLabel">
                        <i class="fas fa-list-check text-info me-2"></i>Attempt Performance & Question Audit
                    </h5>
                    <div class="small text-muted" id="inspectionModalSubtitle">Workstation & Candidate Question-by-Question Breakdown</div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4" id="inspectionModalBody">
                <div class="text-center py-5 text-muted">
                    <div class="spinner-border text-info me-2"></div>
                    Loading attempt questions and answers...
                </div>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
let currentAssessmentId = <?= (int)$id ?>;
let activeAttemptIdForReview = null;
let currentCandidatesData = [];

function switchAssessment(newId) {
    if (newId && parseInt(newId) !== currentAssessmentId) {
        window.location.href = 'index.php?page=monitoring&id=' + newId;
    }
}

// Fetch live updates from LAN telemetry API
function fetchLiveUpdates() {
    if (currentAssessmentId <= 0) return;

    fetch('index.php?page=api-assessment-monitor&id=' + currentAssessmentId)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                renderTelemetryData(data);
            } else {
                console.error('API Error:', data.error);
                document.getElementById('liveStatusText').textContent = 'Feed Warning';
            }
        })
        .catch(err => {
            console.error('Fetch error:', err);
            document.getElementById('liveStatusText').textContent = 'Reconnecting...';
        });
}

function renderTelemetryData(data) {
    document.getElementById('lastSyncTime').textContent = data.formatted_time || '--:--:--';
    document.getElementById('liveStatusText').textContent = 'Live Feed Active (5s)';

    // Update Counters
    document.getElementById('statActive').textContent = data.stats.active;
    document.getElementById('statCompleted').textContent = data.stats.completed;
    document.getElementById('statPendingReview').textContent = data.stats.pending_review;
    document.getElementById('statViolations').textContent = data.stats.violations;
    document.getElementById('statWorkstations').textContent = data.stats.workstations;

    // Update Publication Status
    const isPublished = data.is_result_published;
    const pubBadge = document.getElementById('publicationStatusBadge');
    const pubText = document.getElementById('publicationStatusText');
    const btnPub = document.getElementById('btnPublishToggle');
    const btnPubText = document.getElementById('btnPublishText');

    if (isPublished) {
        pubBadge.className = 'badge bg-success';
        pubBadge.innerHTML = '<i class="fas fa-check-circle me-1"></i> Published to Candidates (Instant Scorecard Available)';
        btnPub.className = 'btn btn-sm btn-outline-warning';
        btnPub.innerHTML = '<i class="fas fa-lock me-1"></i> Unpublish All';
    } else {
        pubBadge.className = 'badge bg-warning text-dark';
        pubBadge.innerHTML = '<i class="fas fa-clock me-1"></i> Withheld (Hidden from Candidates until released)';
        btnPub.className = 'btn btn-sm btn-success';
        btnPub.innerHTML = '<i class="fas fa-bullhorn me-1"></i> Publish All Results';
    }

    currentCandidatesData = data.candidates || [];
    renderCandidatesTable(currentCandidatesData);
}

function renderCandidatesTable(candidates) {
    const tbody = document.getElementById('candidateRowsContainer');

    if (!candidates || candidates.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="9" class="text-center py-5 text-muted">
                    <i class="fas fa-network-wired fa-2x mb-2 d-block opacity-50"></i>
                    No candidates or workstations currently attempting this assessment.
                    <div class="small mt-1">Candidates sitting at workstations will appear here dynamically in real time.</div>
                </td>
            </tr>
        `;
        return;
    }

    let rowsHtml = '';

    candidates.forEach(c => {
        // Status badge
        let statusBadge = '';
        if (c.status === 'in_progress') {
            const pct = c.total_questions > 0 ? Math.round((c.answered_count / c.total_questions) * 100) : 0;
            statusBadge = `
                <div>
                    <span class="badge bg-primary-subtle text-primary border border-primary">
                        <span class="spinner-grow spinner-grow-sm me-1" style="width: 6px; height: 6px;"></span>Attempting Currently
                    </span>
                    <div class="d-flex align-items-center gap-1 mt-1">
                        <div class="progress bg-secondary flex-grow-1" style="height: 5px; min-width: 60px;">
                            <div class="progress-bar bg-success" style="width: ${pct}%"></div>
                        </div>
                        <span class="small text-muted font-monospace" style="font-size: 0.72rem;">${c.answered_count}/${c.total_questions}</span>
                    </div>
                </div>
            `;
        } else if (c.status === 'completed') {
            statusBadge = `<span class="badge bg-success-subtle text-success border border-success"><i class="fas fa-check-circle me-1"></i>Submitted</span>`;
        } else if (c.status === 'timed_out') {
            statusBadge = `<span class="badge bg-warning-subtle text-warning border border-warning"><i class="fas fa-clock me-1"></i>Timed Out</span>`;
        } else if (c.status === 'disqualified') {
            statusBadge = `<span class="badge bg-danger"><i class="fas fa-ban me-1"></i>Disqualified</span>`;
        } else {
            statusBadge = `<span class="badge bg-secondary">${escapeHtml(c.status)}</span>`;
        }

        // Time presentation
        let timeDetails = '';
        if (c.status === 'in_progress') {
            timeDetails = `
                <div class="small">
                    <span class="text-warning font-monospace fw-bold"><i class="fas fa-hourglass-half me-1"></i>${c.remaining_fmt} left</span>
                    <div class="text-muted" style="font-size: 0.75rem;">Started: ${c.started_at_fmt}</div>
                </div>
            `;
        } else {
            timeDetails = `
                <div class="small">
                    <span class="text-muted font-monospace"><i class="fas fa-stopwatch me-1"></i>Took ${c.elapsed_fmt}</span>
                    <div class="text-muted" style="font-size: 0.75rem;">Submitted: ${c.submitted_at_fmt || '--:--'}</div>
                </div>
            `;
        }

        // Violations & Anomaly Pill
        let violationsHtml = '';
        const totalV = c.violations.total;
        let anomalyPill = '';
        if (totalV === 0) {
            anomalyPill = `<span class="badge bg-success-subtle text-success border border-success" style="font-size: 0.7rem;"><i class="fas fa-shield-alt me-1"></i>Normal</span>`;
        } else if (totalV <= 2) {
            anomalyPill = `<span class="badge bg-warning text-dark" style="font-size: 0.7rem;"><i class="fas fa-exclamation-triangle me-1"></i>${totalV} Flags</span>`;
        } else {
            anomalyPill = `<span class="badge bg-danger" style="font-size: 0.7rem;"><i class="fas fa-radiation me-1"></i>${totalV} High Risk</span>`;
        }

        violationsHtml = `
            <div>
                <div class="d-flex align-items-center gap-1 mb-1">
                    ${anomalyPill}
                </div>
                <div class="text-muted" style="font-size: 0.75rem;">
                    <span>${c.violations.window_blur} blurs</span> • 
                    <span>${c.violations.tab_switch} tabs</span>
                </div>
            </div>
        `;

        // Total Correct & Score
        let scoreHtml = `
            <div>
                <span class="badge bg-dark border text-light font-monospace">
                    <i class="fas fa-check text-success me-1"></i>${c.total_correct} / ${c.total_questions} Correct
                </span>
                <div class="mt-1 font-monospace small">
                    <span class="fw-bold ${c.percentage >= 50 ? 'text-success' : 'text-danger'}">${c.obtained_marks} / ${c.total_marks}</span>
                    <span class="text-muted">(${c.percentage}%)</span>
                </div>
            </div>
        `;

        // Review & Inspection Buttons
        let reviewInspectionHtml = '';
        if (c.needs_review) {
            reviewInspectionHtml = `
                <button type="button" class="btn btn-sm btn-warning text-dark fw-bold d-flex align-items-center gap-1 shadow-sm" onclick="openReviewModal(${c.attempt_id})">
                    <i class="fas fa-clipboard-check"></i>
                    <span>Review Pending (${c.pending_review_count})</span>
                </button>
            `;
        } else {
            reviewInspectionHtml = `
                <button type="button" class="btn btn-sm btn-outline-info d-flex align-items-center gap-1" onclick="openInspectionModal(${c.attempt_id})">
                    <i class="fas fa-list-check"></i>
                    <span>View Questions & Result</span>
                </button>
            `;
        }

        // Action Buttons
        let actionButtons = `
            <div class="btn-group btn-group-sm">
                <a href="index.php?page=assessments-candidate-detail&attempt_id=${c.attempt_id}" class="btn btn-outline-secondary" title="Full Candidate Audit">
                    <i class="fas fa-eye"></i>
                </a>
                ${c.status === 'in_progress' ? `
                    <button type="button" class="btn btn-outline-warning" title="Force Submit Attempt" onclick="forceSubmitAttempt(${c.attempt_id}, '${escapeHtml(c.student_name)}')">
                        <i class="fas fa-paper-plane"></i>
                    </button>
                    <button type="button" class="btn btn-outline-danger" title="Disqualify Candidate" onclick="disqualifyAttempt(${c.attempt_id}, '${escapeHtml(c.student_name)}')">
                        <i class="fas fa-ban"></i>
                    </button>
                ` : ''}
            </div>
        `;

        rowsHtml += `
            <tr data-attempt-id="${c.attempt_id}">
                <td>
                    <span class="badge bg-secondary font-monospace fs-7 px-2.5 py-1.5">
                        <i class="fas fa-desktop me-1 text-info"></i>${escapeHtml(c.pc_name)}
                    </span>
                </td>
                <td class="font-monospace fw-bold text-info">${escapeHtml(c.roll_number)}</td>
                <td>
                    <span class="fw-bold text-light">${escapeHtml(c.student_name)}</span>
                    <small class="text-muted d-block">${escapeHtml(c.student_class || '')}</small>
                </td>
                <td>${statusBadge}</td>
                <td>${timeDetails}</td>
                <td>${violationsHtml}</td>
                <td>${scoreHtml}</td>
                <td>${reviewInspectionHtml}</td>
                <td class="text-end">${actionButtons}</td>
            </tr>
        `;
    });

    tbody.innerHTML = rowsHtml;
}

// Filter table live
function filterTable() {
    const term = document.getElementById('filterInput').value.toLowerCase();
    if (!term) {
        renderCandidatesTable(currentCandidatesData);
        return;
    }
    const filtered = currentCandidatesData.filter(c => {
        return (c.pc_name || '').toLowerCase().includes(term) ||
               (c.student_name || '').toLowerCase().includes(term) ||
               (c.roll_number || '').toLowerCase().includes(term) ||
               (c.status || '').toLowerCase().includes(term);
    });
    renderCandidatesTable(filtered);
}

// Toggle Publish All Results
function togglePublishAll() {
    if (!confirm('Toggle result publication for ALL candidates in this assessment?\n\nIf published, all students will immediately see their scorecard upon test completion.')) {
        return;
    }

    fetch('index.php?page=api-toggle-publish', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ assessment_id: currentAssessmentId })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            fetchLiveUpdates();
        } else {
            alert('Failed to update result publication status: ' + (data.error || 'Unknown error'));
        }
    })
    .catch(err => {
        console.error('Publish toggle error:', err);
        alert('Server communication error.');
    });
}

// Force Submit In-Progress Attempt
function forceSubmitAttempt(attemptId, studentName) {
    if (!confirm(`Force submit examination for candidate ${studentName}? This will immediately lock their test.`)) {
        return;
    }

    fetch('index.php?page=api-assessment-monitor&action=force_submit', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'attempt_id=' + attemptId
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            fetchLiveUpdates();
        } else {
            alert('Failed to submit attempt: ' + data.error);
        }
    })
    .catch(err => console.error('Force submit error:', err));
}

// Disqualify Candidate
function disqualifyAttempt(attemptId, studentName) {
    if (!confirm(`Are you sure you want to DISQUALIFY candidate ${studentName} for exam policy violations?`)) {
        return;
    }

    fetch('index.php?page=api-assessment-monitor&action=disqualify_candidate', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'attempt_id=' + attemptId
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            fetchLiveUpdates();
        } else {
            alert('Failed to disqualify candidate: ' + data.error);
        }
    })
    .catch(err => console.error('Disqualification error:', err));
}

// Open Review Modal
function openReviewModal(attemptId) {
    activeAttemptIdForReview = attemptId;
    const modalEl = document.getElementById('reviewModal');
    const modal = new bootstrap.Modal(modalEl);
    modal.show();

    const body = document.getElementById('reviewModalBody');
    body.innerHTML = `
        <div class="text-center py-5 text-muted">
            <div class="spinner-border text-warning me-2"></div>
            Loading candidate written responses...
        </div>
    `;

    fetch('index.php?page=api-assessment-monitor&action=get_attempt_review&attempt_id=' + attemptId)
        .then(res => res.json())
        .then(data => {
            if (!data.success) {
                body.innerHTML = `<div class="alert alert-danger">${escapeHtml(data.error || 'Failed to load review questions')}</div>`;
                return;
            }

            document.getElementById('reviewModalSubtitle').innerHTML = `
                Workstation: <strong>${escapeHtml(data.station_code)}</strong> • 
                Candidate: <strong>${escapeHtml(data.candidate_name)}</strong> (Roll: ${escapeHtml(data.roll_number)})
            `;

            if (data.review_questions.length === 0) {
                body.innerHTML = `<div class="alert alert-info">No subjective questions require grading for this attempt.</div>`;
                return;
            }

            let formHtml = `<form id="reviewGradingForm">`;
            data.review_questions.forEach((q, idx) => {
                const qTypeLabel = q.question_type === 'fill_blank' ? 'Fill in the Blank' : 'Short Answer / Written';
                const currentMarks = q.marks_obtained !== null ? parseFloat(q.marks_obtained) : 0;
                
                // Collect reference correct answers if available
                let refAnswers = [];
                if (q.options && q.options.length > 0) {
                    q.options.forEach(opt => {
                        if (opt.is_correct == 1) refAnswers.push(opt.option_text);
                    });
                }

                formHtml += `
                    <div class="card bg-dark border-secondary mb-3 p-3 question-review-card" data-qid="${q.question_id}">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="badge bg-warning text-dark fw-bold">Q${idx + 1} • ${qTypeLabel}</span>
                            <span class="small text-muted">Max Marks: <strong>${q.marks}</strong></span>
                        </div>
                        <div class="fw-bold mb-2 text-light">${escapeHtml(q.question_text)}</div>
                        
                        <div class="p-2 mb-2 rounded bg-black border border-secondary">
                            <div class="small text-muted mb-1 text-uppercase fw-semibold" style="font-size: 0.72rem;">Candidate Submitted Response:</div>
                            <div class="font-monospace text-warning">${escapeHtml(q.text_answer || '[No Answer Submitted]')}</div>
                        </div>

                        ${refAnswers.length > 0 ? `
                            <div class="small text-muted mb-2">
                                <span class="text-success fw-semibold">Model / Reference Answer:</span> ${escapeHtml(refAnswers.join(', '))}
                            </div>
                        ` : ''}

                        <div class="row g-2 align-items-center">
                            <div class="col-sm-4">
                                <label class="small text-muted form-label mb-1">Marks Awarded (0 - ${q.marks}):</label>
                                <input type="number" class="form-control form-control-sm bg-dark text-light border-secondary marks-input" 
                                       data-qid="${q.question_id}" min="0" max="${q.marks}" step="0.5" value="${currentMarks}">
                            </div>
                            <div class="col-sm-8">
                                <label class="small text-muted form-label mb-1">Teacher Remarks / Feedback:</label>
                                <input type="text" class="form-control form-control-sm bg-dark text-light border-secondary comment-input" 
                                       data-qid="${q.question_id}" placeholder="Optional teacher feedback..." value="${escapeHtml(q.teacher_comment || '')}">
                            </div>
                        </div>
                    </div>
                `;
            });
            formHtml += `</form>`;
            body.innerHTML = formHtml;
        })
        .catch(err => {
            console.error('Review load error:', err);
            body.innerHTML = `<div class="alert alert-danger">Error connecting to telemetry feed.</div>`;
        });
}

// Submit Review Grades
function submitReviewGrades(finalize = false) {
    if (!activeAttemptIdForReview) return;

    const cards = document.querySelectorAll('.question-review-card');
    const grades = [];
    cards.forEach(c => {
        const qId = parseInt(c.dataset.qid);
        const marksInput = c.querySelector('.marks-input');
        const commentInput = c.querySelector('.comment-input');
        grades.push({
            question_id: qId,
            marks_awarded: parseFloat(marksInput.value || 0),
            teacher_comment: commentInput.value || ''
        });
    });

    fetch('index.php?page=api-assessment-monitor&action=save_review', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            attempt_id: activeAttemptIdForReview,
            grades: grades,
            finalize: finalize
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('reviewModal')).hide();
            fetchLiveUpdates();
        } else {
            alert('Failed to save grades: ' + (data.error || 'Unknown error'));
        }
    })
    .catch(err => console.error('Save grades error:', err));
}

// Open Full Inspection Modal
function openInspectionModal(attemptId) {
    const modalEl = document.getElementById('inspectionModal');
    const modal = new bootstrap.Modal(modalEl);
    modal.show();

    const body = document.getElementById('inspectionModalBody');
    body.innerHTML = `
        <div class="text-center py-5 text-muted">
            <div class="spinner-border text-info me-2"></div>
            Loading full attempt scorecard and questions...
        </div>
    `;

    fetch('index.php?page=api-assessment-monitor&action=get_attempt_inspection&attempt_id=' + attemptId)
        .then(res => res.json())
        .then(data => {
            if (!data.success) {
                body.innerHTML = `<div class="alert alert-danger">${escapeHtml(data.error || 'Failed to load details')}</div>`;
                return;
            }

            const sc = data.scorecard;
            document.getElementById('inspectionModalSubtitle').innerHTML = `
                Workstation: <strong>${escapeHtml(sc.station_code || 'Workstation')}</strong> • 
                Candidate: <strong>${escapeHtml(sc.candidate_name)}</strong> (Roll: ${escapeHtml(sc.roll_number)}) • 
                Status: <span class="badge ${sc.status === 'completed' ? 'bg-success' : 'bg-primary'}">${escapeHtml(sc.status)}</span>
            `;

            let scorecardHeader = `
                <div class="row g-3 mb-4">
                    <div class="col-md-3 col-6">
                        <div class="card bg-dark border-secondary p-3 text-center">
                            <div class="small text-muted text-uppercase fw-bold mb-1">Final Score</div>
                            <div class="fs-3 fw-bold text-success font-monospace">${sc.obtained_marks} / ${sc.total_marks}</div>
                            <div class="small text-muted">${sc.percentage}% • ${sc.pass_fail ? sc.pass_fail.toUpperCase() : ''}</div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="card bg-dark border-secondary p-3 text-center">
                            <div class="small text-muted text-uppercase fw-bold mb-1">Correct Answers</div>
                            <div class="fs-3 fw-bold text-success font-monospace">${sc.correct_answers} / ${sc.total_questions}</div>
                            <div class="small text-muted">Accuracy: ${sc.accuracy || 0}%</div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="card bg-dark border-secondary p-3 text-center">
                            <div class="small text-muted text-uppercase fw-bold mb-1">Incorrect / Unanswered</div>
                            <div class="fs-3 fw-bold text-danger font-monospace">${sc.wrong_answers} wrong</div>
                            <div class="small text-muted">${sc.unanswered} unanswered</div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="card bg-dark border-secondary p-3 text-center">
                            <div class="small text-muted text-uppercase fw-bold mb-1">Duration & Time</div>
                            <div class="fs-3 fw-bold text-info font-monospace">${Math.floor((sc.time_taken_seconds || 0)/60)}m ${(sc.time_taken_seconds || 0)%60}s</div>
                            <div class="small text-muted">Exam Duration</div>
                        </div>
                    </div>
                </div>
            `;

            let questionsHtml = `<h6 class="fw-bold mb-3"><i class="fas fa-tasks me-2 text-primary"></i>All Attempted Questions & Answers</h6>`;
            
            sc.questions.forEach((q, idx) => {
                let statusBadge = '';
                let borderClass = 'border-secondary';
                if (q.is_correct == 1) {
                    statusBadge = `<span class="badge bg-success-subtle text-success border border-success"><i class="fas fa-check me-1"></i>Correct (+${q.marks_obtained} marks)</span>`;
                    borderClass = 'border-success';
                } else if (q.is_correct === 0 || q.is_correct === '0') {
                    statusBadge = `<span class="badge bg-danger-subtle text-danger border border-danger"><i class="fas fa-times me-1"></i>Incorrect (0 marks)</span>`;
                    borderClass = 'border-danger';
                } else {
                    statusBadge = `<span class="badge bg-warning-subtle text-warning border border-warning"><i class="fas fa-clock me-1"></i>Pending Review</span>`;
                    borderClass = 'border-warning';
                }

                // Format candidate chosen answer
                let candidateAnswerText = '';
                if (q.text_answer !== null && q.text_answer !== '') {
                    candidateAnswerText = escapeHtml(q.text_answer);
                } else if (q.selected_option_ids) {
                    let selIds = [];
                    try { selIds = JSON.parse(q.selected_option_ids); } catch(e) { selIds = [q.selected_option_ids]; }
                    let selTexts = [];
                    if (q.options) {
                        q.options.forEach(opt => {
                            if (selIds.includes(opt.id) || selIds.includes(String(opt.id)) || selIds.includes(Number(opt.id))) {
                                selTexts.push(opt.option_text);
                            }
                        });
                    }
                    candidateAnswerText = selTexts.length > 0 ? escapeHtml(selTexts.join(', ')) : '<span class="text-muted">[No option chosen]</span>';
                } else {
                    candidateAnswerText = '<span class="text-muted">[Unanswered]</span>';
                }

                // Format correct answers
                let correctAnswers = [];
                if (q.options) {
                    q.options.forEach(opt => {
                        if (opt.is_correct == 1) correctAnswers.push(opt.option_text);
                    });
                }

                questionsHtml += `
                    <div class="card bg-dark ${borderClass} mb-3 p-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="fw-bold">Question ${idx + 1} <span class="badge bg-secondary ms-1">${escapeHtml(q.question_type)}</span></span>
                            <div>${statusBadge}</div>
                        </div>
                        <div class="mb-3 text-light">${escapeHtml(q.question_text)}</div>

                        <div class="row g-2">
                            <div class="col-md-6">
                                <div class="p-2 rounded bg-black border border-secondary h-100">
                                    <div class="small text-muted mb-1 text-uppercase fw-semibold" style="font-size: 0.72rem;">Student's Submitted Answer:</div>
                                    <div class="font-monospace ${q.is_correct == 1 ? 'text-success' : 'text-warning'}">${candidateAnswerText}</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="p-2 rounded bg-black border border-secondary h-100">
                                    <div class="small text-muted mb-1 text-uppercase fw-semibold" style="font-size: 0.72rem;">Correct Answer Key:</div>
                                    <div class="font-monospace text-success">${correctAnswers.length > 0 ? escapeHtml(correctAnswers.join(', ')) : '<span class="text-muted">Subjective / Instructor Evaluated</span>'}</div>
                                </div>
                            </div>
                        </div>

                        ${q.teacher_comment ? `
                            <div class="mt-2 small text-info">
                                <strong>Teacher Feedback:</strong> ${escapeHtml(q.teacher_comment)}
                            </div>
                        ` : ''}
                    </div>
                `;
            });

            body.innerHTML = scorecardHeader + questionsHtml;
        })
        .catch(err => {
            console.error('Inspection load error:', err);
            body.innerHTML = `<div class="alert alert-danger">Failed to fetch attempt details.</div>`;
        });
}

function escapeHtml(text) {
    const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
    return String(text || '').replace(/[&<>"']/g, m => map[m]);
}

// Initial polling boot
document.addEventListener('DOMContentLoaded', function() {
    fetchLiveUpdates();
    setInterval(fetchLiveUpdates, 5000);
});
</script>

<?php endif; ?>
