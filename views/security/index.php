<?php
/**
 * Security Violations & Anti-Cheating Logs View
 */

if (!Auth::isSuperAdmin() && !Auth::hasPermission('security_events.view') && !Auth::hasPermission('assessments.view')) {
    Middleware::forbidden('You do not have permission to view security violation logs.');
}

// Fetch all assessments for filter dropdown
$assessments = Database::fetchAll("SELECT id, title, assessment_type FROM assessments ORDER BY id DESC");

$selectedAssessmentId = (int)($_GET['assessment_id'] ?? ($_GET['competition_id'] ?? 0));
$selectedEventType = trim($_GET['event_type'] ?? '');
$search = trim($_GET['search'] ?? '');
$page = max(1, (int)($_GET['p'] ?? 1));
$perPage = 25;
$offset = ($page - 1) * $perPage;

$where = ["1=1"];
$params = [];

if ($selectedAssessmentId > 0) {
    $where[] = "aa.assessment_id = ?";
    $params[] = $selectedAssessmentId;
}

if ($selectedEventType !== '') {
    $where[] = "se.event_type = ?";
    $params[] = $selectedEventType;
}

if ($search !== '') {
    $where[] = "(aa.student_name LIKE ? OR aa.student_roll_no LIKE ? OR aa.student_class LIKE ? OR asm.title LIKE ? OR se.description LIKE ? OR se.event_details LIKE ? OR ls.station_code LIKE ?)";
    $like = "%{$search}%";
    $params = array_merge($params, [$like, $like, $like, $like, $like, $like, $like]);
}

$whereSql = implode(' AND ', $where);

$total = (int)Database::fetchColumn("
    SELECT COUNT(*) 
    FROM security_events se
    LEFT JOIN assessment_attempts aa ON (se.attempt_id = aa.id OR (se.attempt_id IS NULL AND JSON_UNQUOTE(JSON_EXTRACT(se.metadata, '$.attempt_id')) = CAST(aa.id AS CHAR)))
    LEFT JOIN assessments asm ON aa.assessment_id = asm.id
    LEFT JOIN lab_stations ls ON aa.station_id = ls.id
    WHERE {$whereSql}
", $params);

$events = Database::fetchAll("
    SELECT 
        se.*,
        aa.student_name,
        aa.student_roll_no,
        aa.student_class,
        aa.status AS attempt_status,
        asm.id AS assessment_id,
        asm.title AS assessment_title,
        asm.assessment_type,
        ls.station_code
    FROM security_events se
    LEFT JOIN assessment_attempts aa ON (se.attempt_id = aa.id OR (se.attempt_id IS NULL AND JSON_UNQUOTE(JSON_EXTRACT(se.metadata, '$.attempt_id')) = CAST(aa.id AS CHAR)))
    LEFT JOIN assessments asm ON aa.assessment_id = asm.id
    LEFT JOIN lab_stations ls ON aa.station_id = ls.id
    WHERE {$whereSql}
    ORDER BY se.id DESC
    LIMIT {$perPage} OFFSET {$offset}
", $params);

$totalPages = max(1, (int)ceil($total / $perPage));

$eventTypes = [
    'TAB_SWITCH'             => 'Tab Switch / Window Away',
    'WINDOW_BLUR'            => 'Window Blur (Focus Loss)',
    'WINDOW_BLUR_ANOMALY'    => 'Window Blur Alert Trigger',
    'FULLSCREEN_EXIT'        => 'Fullscreen Exit',
    'COPY_ATTEMPT'           => 'Copy Attempt (Clipboard)',
    'PASTE_ATTEMPT'          => 'Paste Attempt (Clipboard)',
    'CUT_ATTEMPT'            => 'Cut Attempt',
    'RIGHT_CLICK_ATTEMPT'    => 'Right Click / Context Menu',
    'MULTIPLE_TAB_WARNING'   => 'Multiple Tabs Opened',
    'DEVTOOLS_SUSPECTED'     => 'DevTools Inspection',
    'OTHER'                  => 'Other Anomaly',
];
?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <div>
        <h4 class="fw-bold mb-1">
            <i class="fas fa-shield-alt text-danger me-2"></i> Security Violations & Anti-Cheating Logs
        </h4>
        <div class="text-muted small">Live exam integrity violation records, tab-switch telemetry, and anomaly audits</div>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= url('monitoring') ?>" class="btn btn-primary btn-sm">
            <i class="fas fa-desktop me-1"></i> Live Assessment Monitor
        </a>
        <a href="<?= url('settings') ?>#proctoring_anomaly_timer" class="btn btn-outline-secondary btn-sm" title="Configure Anomaly Timer">
            <i class="fas fa-cog me-1"></i> Telemetry Settings
        </a>
    </div>
</div>

<!-- Filters Card -->
<div class="card bg-dark border-secondary mb-4">
    <div class="card-body py-3">
        <form method="GET" action="<?= url('security-events') ?>" class="row g-2 align-items-center">
            <input type="hidden" name="page" value="security-events">

            <!-- Assessment Filter -->
            <div class="col-md-3">
                <select name="assessment_id" class="form-select form-select-sm bg-dark text-light border-secondary">
                    <option value="">All Assessments</option>
                    <?php foreach ($assessments as $asm): ?>
                        <option value="<?= $asm['id'] ?>" <?= $selectedAssessmentId === (int)$asm['id'] ? 'selected' : '' ?>>
                            <?= e($asm['title']) ?> (<?= strtoupper(e($asm['assessment_type'])) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Event Type Filter -->
            <div class="col-md-3">
                <select name="event_type" class="form-select form-select-sm bg-dark text-light border-secondary">
                    <option value="">All Violation Types</option>
                    <?php foreach ($eventTypes as $key => $label): ?>
                        <option value="<?= $key ?>" <?= $selectedEventType === $key ? 'selected' : '' ?>>
                            <?= e($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Search Filter -->
            <div class="col-md-4">
                <input type="text" name="search" class="form-control form-control-sm bg-dark text-light border-secondary" 
                       placeholder="Search candidate name, roll #, station, details..." value="<?= e($search) ?>">
            </div>

            <!-- Filter Buttons -->
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm w-100">
                    <i class="fas fa-filter me-1"></i> Filter
                </button>
                <a href="<?= url('security-events') ?>" class="btn btn-outline-secondary btn-sm" title="Reset Filters">
                    <i class="fas fa-undo"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Events Table Card -->
<div class="card bg-dark border-secondary shadow-sm">
    <div class="card-header border-secondary d-flex justify-content-between align-items-center py-2">
        <span class="fw-bold"><i class="fas fa-list me-1 text-info"></i> Recorded Security Events</span>
        <span class="badge bg-secondary"><?= number_format($total) ?> Total Violations</span>
    </div>
    <div class="table-responsive">
        <table class="table table-dark table-hover table-bordered mb-0 align-middle">
            <thead class="table-secondary text-light small text-uppercase">
                <tr>
                    <th style="width: 155px;">Timestamp</th>
                    <th>Candidate</th>
                    <th>Assessment</th>
                    <th style="width: 95px;">Station</th>
                    <th>Violation Event</th>
                    <th style="width: 95px;">Severity</th>
                    <th style="width: 110px;">Attempt Status</th>
                    <th>Telemetry Details</th>
                    <th class="text-end" style="width: 70px;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($events)): ?>
                    <tr>
                        <td colspan="9" class="text-center py-5 text-muted">
                            <i class="fas fa-check-circle fa-3x text-success mb-3 d-block"></i>
                            <h6 class="text-light fw-bold">No Security Violations Found</h6>
                            <p class="small mb-0">No anti-cheating violations match the selected filter criteria.</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($events as $ev): ?>
                        <?php
                            $meta = !empty($ev['metadata']) ? json_decode($ev['metadata'], true) : [];
                            $detailText = $ev['description'] ?: ($ev['event_details'] ?: ($meta['detail'] ?? ''));
                            $durationSec = (int)($meta['duration_seconds'] ?? 0);
                            $thresholdSec = (int)($meta['threshold'] ?? 0);
                        ?>
                        <tr>
                            <!-- Timestamp -->
                            <td class="small font-monospace text-muted">
                                <?= formatDateTime($ev['created_at']) ?>
                            </td>

                            <!-- Candidate -->
                            <td>
                                <?php if (!empty($ev['student_name'])): ?>
                                    <div class="fw-bold text-light"><?= e($ev['student_name']) ?></div>
                                    <div class="small text-muted font-monospace">
                                        Roll #<?= e($ev['student_roll_no'] ?: '—') ?>
                                        <?php if (!empty($ev['student_class'])): ?>
                                            • <span class="text-info"><?= e($ev['student_class']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                <?php elseif (!empty($ev['attempt_id'])): ?>
                                    <span class="text-light">Candidate (Attempt #<?= (int)$ev['attempt_id'] ?>)</span>
                                    <div class="small text-muted">Session Closed</div>
                                <?php else: ?>
                                    <span class="text-muted">Unidentified Client</span>
                                <?php endif; ?>
                            </td>

                            <!-- Assessment -->
                            <td>
                                <?php if (!empty($ev['assessment_title'])): ?>
                                    <div class="text-light small fw-semibold"><?= e($ev['assessment_title']) ?></div>
                                    <span class="badge bg-secondary bg-opacity-50 text-light border border-secondary font-monospace" style="font-size: 0.7rem;">
                                        <?= strtoupper(e($ev['assessment_type'] ?? 'ASSESSMENT')) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted small">—</span>
                                <?php endif; ?>
                            </td>

                            <!-- Lab Station -->
                            <td class="text-center">
                                <?php if (!empty($ev['station_code'])): ?>
                                    <span class="badge bg-dark border border-secondary font-monospace text-info">
                                        <i class="fas fa-desktop me-1"></i><?= e($ev['station_code']) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted small">—</span>
                                <?php endif; ?>
                            </td>

                            <!-- Violation Event -->
                            <td>
                                <span class="badge bg-danger bg-opacity-25 text-danger border border-danger border-opacity-50 fw-semibold">
                                    <i class="fas fa-exclamation-triangle me-1"></i> <?= e($ev['event_type']) ?>
                                </span>
                            </td>

                            <!-- Severity -->
                            <td>
                                <?php 
                                    $sev = strtolower((string)$ev['severity']);
                                    if ($sev === 'critical'):
                                ?>
                                    <span class="badge bg-danger">CRITICAL</span>
                                <?php elseif ($sev === 'high'): ?>
                                    <span class="badge bg-danger bg-opacity-75">HIGH</span>
                                <?php elseif ($sev === 'medium'): ?>
                                    <span class="badge bg-warning text-dark">MEDIUM</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">LOW</span>
                                <?php endif; ?>
                            </td>

                            <!-- Attempt Status -->
                            <td>
                                <?php 
                                    $status = strtolower((string)($ev['attempt_status'] ?? ''));
                                    if ($status === 'disqualified'): 
                                ?>
                                    <span class="badge bg-danger">DISQUALIFIED</span>
                                <?php elseif ($status === 'in_progress'): ?>
                                    <span class="badge bg-success">ACTIVE</span>
                                <?php elseif ($status === 'completed'): ?>
                                    <span class="badge bg-info text-dark">COMPLETED</span>
                                <?php elseif ($status === 'flagged'): ?>
                                    <span class="badge bg-warning text-dark">FLAGGED</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary"><?= strtoupper($status ?: 'CLOSED') ?></span>
                                <?php endif; ?>
                            </td>

                            <!-- Details / Telemetry -->
                            <td class="small" style="max-width: 280px;">
                                <?php if ($durationSec > 0): ?>
                                    <div class="text-danger fw-bold mb-1">
                                        <i class="fas fa-stopwatch me-1"></i> Away for <?= $durationSec ?>s 
                                        <?php if ($thresholdSec > 0): ?>
                                            <span class="text-muted fw-normal">(Limit: <?= $thresholdSec ?>s)</span>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>

                                <div class="text-muted text-truncate" title="<?= e($detailText) ?>">
                                    <?= e($detailText ?: 'Violation recorded by proctoring monitor') ?>
                                </div>

                                <?php if (!empty($ev['ip_address'])): ?>
                                    <div class="text-muted font-monospace" style="font-size: 0.72rem;">
                                        IP: <?= e($ev['ip_address']) ?>
                                    </div>
                                <?php endif; ?>
                            </td>

                            <!-- Action -->
                            <td class="text-end">
                                <?php if (!empty($ev['attempt_id'])): ?>
                                    <a href="<?= url('assessments-candidate-detail') ?>&attempt_id=<?= (int)$ev['attempt_id'] ?>" 
                                       class="btn btn-outline-info btn-sm" title="View Candidate Audit & Scorecard">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
        <div class="card-footer border-secondary d-flex justify-content-between align-items-center py-2">
            <span class="small text-muted">Page <?= $page ?> of <?= $totalPages ?> (Total <?= number_format($total) ?> events)</span>
            <ul class="pagination pagination-sm mb-0">
                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link bg-dark border-secondary text-light" 
                       href="<?= url('security-events') ?>&p=<?= $page - 1 ?>&assessment_id=<?= $selectedAssessmentId ?>&event_type=<?= urlencode($selectedEventType) ?>&search=<?= urlencode($search) ?>">
                        Previous
                    </a>
                </li>
                <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                    <a class="page-link bg-dark border-secondary text-light" 
                       href="<?= url('security-events') ?>&p=<?= $page + 1 ?>&assessment_id=<?= $selectedAssessmentId ?>&event_type=<?= urlencode($selectedEventType) ?>&search=<?= urlencode($search) ?>">
                        Next
                    </a>
                </li>
            </ul>
        </div>
    <?php endif; ?>
</div>
