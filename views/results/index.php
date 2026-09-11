<?php
/**
 * Admin Test Results Management & Audit List
 * Marr Typing Competition System
 *
 * Provides:
 * - Default: Best Result Per Candidate (Canonical View)
 * - Optional: All Attempt History (Full Audit Trail)
 * - Direct Certificate Generation / View Actions
 * - Individual Candidate Attempt History Filtering
 */

Middleware::requirePermission('results.view');

$competitions = Database::fetchAll("SELECT id, name, code, status, results_finalized_at FROM competitions ORDER BY id DESC");
$selectedCompId = (int)($_GET['competition_id'] ?? 0);
$selectedCandidateId = (int)($_GET['candidate_id'] ?? 0);
$search = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$viewMode = trim($_GET['view_mode'] ?? 'canonical');
if (!in_array($viewMode, ['canonical', 'history'], true)) {
    $viewMode = 'canonical';
}

$page = max(1, (int)($_GET['p'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$filters = [
    'competition_id' => $selectedCompId,
    'candidate_id'   => $selectedCandidateId,
    'search'         => $search,
    'status'         => $statusFilter,
];

if ($viewMode === 'canonical') {
    $total = ResultService::getCanonicalResultsCount($selectedCompId, $filters);
    $results = ResultService::getCanonicalResults($selectedCompId, $filters, $perPage, $offset);
} else {
    $total = ResultService::getAllAttemptResultsCount($selectedCompId, $filters);
    $results = ResultService::getAllAttemptResults($selectedCompId, $filters, $perPage, $offset);
}

$totalPages = max(1, (int)ceil($total / $perPage));

// Selected Candidate Details if filtering by specific candidate
$filteredCandidate = null;
if ($selectedCandidateId > 0) {
    $filteredCandidate = Database::fetch("SELECT * FROM candidates WHERE id = ?", [$selectedCandidateId]);
}
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-1"><i class="fas fa-poll text-info me-2"></i> Candidate Test Results</h4>
        <div class="text-muted small">Official canonical scores, WPM metrics, accuracy ratings, and complete attempt audit trails</div>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="<?= url('leaderboard') ?>&competition_id=<?= $selectedCompId ?>" class="btn btn-warning btn-sm fw-bold">
            <i class="fas fa-trophy me-1"></i> Official Leaderboard
        </a>
        <a href="<?= url('certificates-generate') ?>&competition_id=<?= $selectedCompId ?>" class="btn btn-outline-info btn-sm">
            <i class="fas fa-certificate me-1"></i> Certificate Generator
        </a>
    </div>
</div>

<?php if ($filteredCandidate): ?>
    <div class="alert alert-info py-2 px-3 mb-4 d-flex justify-content-between align-items-center small">
        <div>
            <i class="fas fa-user-clock me-2"></i> Viewing attempt history for candidate: <strong><?= e($filteredCandidate['full_name']) ?></strong> (Roll #: <strong><?= e($filteredCandidate['roll_number'] ?: '—') ?></strong>)
        </div>
        <a href="<?= url('results') ?>&competition_id=<?= $selectedCompId ?>&view_mode=canonical" class="btn btn-outline-dark btn-sm py-0 px-2">
            <i class="fas fa-times me-1"></i> Clear Candidate Filter
        </a>
    </div>
<?php endif; ?>

<!-- Filters Card -->
<div class="card bg-dark border-secondary mb-4">
    <div class="card-body py-3">
        <form method="GET" action="<?= url('results') ?>" class="row g-2 align-items-center" id="resultsFilterForm">
            <input type="hidden" name="page" value="results">

            <!-- Competition Selector -->
            <div class="col-md-3">
                <select name="competition_id" class="form-select form-select-sm bg-dark text-light border-secondary" onchange="document.getElementById('resultsFilterForm').submit()">
                    <option value="">All Competitions</option>
                    <?php foreach ($competitions as $cmp): ?>
                        <option value="<?= $cmp['id'] ?>" <?= $selectedCompId === (int)$cmp['id'] ? 'selected' : '' ?>>
                            <?= e($cmp['name']) ?> (<?= e($cmp['code']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- View Mode Switch -->
            <div class="col-md-4">
                <div class="btn-group btn-group-sm w-100" role="group">
                    <input type="radio" class="btn-check" name="view_mode" id="modeCanonical" value="canonical" <?= $viewMode === 'canonical' ? 'checked' : '' ?> onchange="document.getElementById('resultsFilterForm').submit()">
                    <label class="btn btn-outline-secondary text-nowrap <?= $viewMode === 'canonical' ? 'active text-warning fw-bold' : '' ?>" for="modeCanonical">
                        <i class="fas fa-star me-1"></i> Best Result Per Candidate
                    </label>

                    <input type="radio" class="btn-check" name="view_mode" id="modeHistory" value="history" <?= $viewMode === 'history' ? 'checked' : '' ?> onchange="document.getElementById('resultsFilterForm').submit()">
                    <label class="btn btn-outline-secondary text-nowrap <?= $viewMode === 'history' ? 'active text-info fw-bold' : '' ?>" for="modeHistory">
                        <i class="fas fa-history me-1"></i> All Attempt History
                    </label>
                </div>
            </div>

            <!-- Search Field -->
            <div class="col-md-3">
                <input type="text" name="search" class="form-control form-control-sm bg-dark text-light border-secondary" 
                       placeholder="Search candidate, roll #, reg #..." value="<?= e($search) ?>">
            </div>

            <!-- Actions -->
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm w-100">
                    <i class="fas fa-filter me-1"></i> Filter
                </button>
                <a href="<?= url('results') ?>" class="btn btn-outline-secondary btn-sm" title="Reset All Filters">
                    <i class="fas fa-undo"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Results Table Card -->
<div class="card bg-dark border-secondary">
    <div class="card-header border-secondary d-flex justify-content-between align-items-center">
        <div>
            <span class="fw-bold">
                <?php if ($viewMode === 'canonical'): ?>
                    <i class="fas fa-star text-warning me-1"></i> Canonical Result Records (Best Attempt Per Candidate)
                <?php else: ?>
                    <i class="fas fa-history text-info me-1"></i> Full Attempt History Audit Trail
                <?php endif; ?>
            </span>
        </div>
        <div>
            <span class="badge bg-secondary">
                <?php if ($viewMode === 'canonical'): ?>
                    <?= number_format($total) ?> Candidate<?= $total === 1 ? '' : 's' ?>
                <?php else: ?>
                    <?= number_format($total) ?> Attempt<?= $total === 1 ? '' : 's' ?>
                <?php endif; ?>
            </span>
        </div>
    </div>

    <?php if ($viewMode === 'canonical'): ?>
        <!-- ============================================================
             MODE 1: CANONICAL VIEW (ONE ROW PER CANDIDATE)
             ============================================================ -->
        <div class="table-responsive">
            <table class="table table-dark table-hover table-bordered mb-0 align-middle text-center">
                <thead class="table-secondary text-light small text-uppercase">
                    <tr>
                        <th style="width: 85px;">Roll #</th>
                        <th class="text-start">Candidate</th>
                        <th>Competition</th>
                        <th style="width: 85px;">Attempt #</th>
                        <th>Gross WPM</th>
                        <th>Net WPM</th>
                        <th>Accuracy</th>
                        <th>Errors</th>
                        <th>Final Score</th>
                        <th>Rank</th>
                        <th>Qualification</th>
                        <th style="width: 140px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($results)): ?>
                        <tr>
                            <td colspan="12" class="text-center py-5 text-muted">
                                <i class="fas fa-clipboard-list fa-3x mb-3 d-block opacity-25"></i>
                                No candidate test results found for the selected criteria.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($results as $res): ?>
                            <?php
                            $hasResult = !empty($res['result_id']);
                            $qualStatus = $res['qualification_status'];
                            $qualBadgeClass = match($qualStatus) {
                                'qualified'    => 'bg-success',
                                'disqualified' => 'bg-danger',
                                'no_result'    => 'bg-dark border border-secondary text-muted',
                                default        => 'bg-secondary',
                            };
                            $qualLabel = match($qualStatus) {
                                'qualified'    => 'QUALIFIED',
                                'disqualified' => 'DISQUALIFIED',
                                'no_result'    => 'NO RESULT',
                                default        => 'NOT QUALIFIED',
                            };
                            ?>
                            <tr>
                                <td>
                                    <strong class="font-monospace text-primary"><?= e($res['roll_number'] ?: '—') ?></strong>
                                </td>
                                <td class="text-start">
                                    <strong class="text-light"><?= e($res['candidate_name']) ?></strong>
                                    <?php if ($res['total_attempts'] > 1): ?>
                                        <span class="badge bg-dark border border-info text-info small ms-1" title="<?= $res['total_attempts'] ?> total attempts recorded">
                                            <?= $res['total_attempts'] ?> attempts
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="small font-monospace"><?= e($res['competition_code']) ?></td>
                                <td>
                                    <?php if ($hasResult): ?>
                                        <span class="badge bg-dark border border-secondary font-monospace">#<?= $res['attempt_number'] ?></span>
                                    <?php else: ?>
                                        <span class="text-muted small">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="small text-muted font-monospace"><?= $hasResult ? number_format($res['gross_wpm'], 2) : '—' ?></span>
                                </td>
                                <td>
                                    <strong class="text-primary font-monospace"><?= $hasResult ? number_format($res['net_wpm'], 2) : '—' ?></strong>
                                </td>
                                <td>
                                    <span class="text-success font-monospace"><?= $hasResult ? number_format($res['accuracy'], 2) . '%' : '—' ?></span>
                                </td>
                                <td>
                                    <?php if ($hasResult): ?>
                                        <span class="badge bg-danger bg-opacity-25 text-danger font-monospace"><?= (int)$res['error_count'] ?></span>
                                    <?php else: ?>
                                        <span class="text-muted small">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong class="text-warning font-monospace"><?= $hasResult ? number_format($res['score'], 2) : '—' ?></strong>
                                </td>
                                <td>
                                    <?php if ($res['rank_display'] !== '—'): ?>
                                        <span class="badge bg-warning text-dark font-monospace fw-bold"><?= e($res['rank_display']) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted small">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge <?= $qualBadgeClass ?>"><?= $qualLabel ?></span>
                                </td>
                                <td>
                                    <div class="d-flex justify-content-center gap-1">
                                        <?php if ($hasResult): ?>
                                            <!-- View Result Breakdown -->
                                            <a href="<?= url('results-view') ?>&id=<?= $res['result_id'] ?>" class="btn btn-outline-primary btn-sm py-0 px-2" title="View Detailed Breakdown">
                                                <i class="fas fa-eye"></i>
                                            </a>

                                            <!-- Certificate Action -->
                                            <?php if ($res['has_certificate']): ?>
                                                <a href="<?= url('certificates-view') ?>&id=<?= $res['certificate']['id'] ?>" target="_blank" class="btn btn-outline-warning btn-sm py-0 px-2" title="View / Print Issued Certificate (#<?= e($res['certificate']['certificate_number']) ?>)">
                                                    <i class="fas fa-award"></i>
                                                </a>
                                            <?php elseif ($res['certificate_eligible']): ?>
                                                <a href="<?= url('certificates-generate') ?>&competition_id=<?= $res['competition_id'] ?>" class="btn btn-outline-info btn-sm py-0 px-2" title="Certificate Ready to Generate">
                                                    <i class="fas fa-certificate"></i>
                                                </a>
                                            <?php endif; ?>
                                        <?php endif; ?>

                                        <!-- Candidate Attempt History -->
                                        <a href="<?= url('results') ?>&view_mode=history&candidate_id=<?= $res['candidate_id'] ?>&competition_id=<?= $res['competition_id'] ?>" class="btn btn-outline-secondary btn-sm py-0 px-2" title="View All Attempts (<?= $res['total_attempts'] ?>)">
                                            <i class="fas fa-history"></i>
                                        </a>

                                        <?php if ($res['violation_count'] > 0): ?>
                                            <a href="<?= url('security-events') ?>&search=<?= urlencode($res['roll_number']) ?>" class="btn btn-outline-danger btn-sm py-0 px-2" title="View Security Violations (<?= $res['violation_count'] ?>)">
                                                <i class="fas fa-shield-alt"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    <?php else: ?>
        <!-- ============================================================
             MODE 2: ALL ATTEMPT HISTORY (FULL AUDIT TRAIL)
             ============================================================ -->
        <div class="table-responsive">
            <table class="table table-dark table-hover table-bordered mb-0 align-middle text-center small">
                <thead class="table-secondary text-light text-uppercase">
                    <tr>
                        <th style="width: 85px;">Roll #</th>
                        <th class="text-start">Candidate</th>
                        <th>Competition</th>
                        <th style="width: 75px;">Attempt #</th>
                        <th>Started At</th>
                        <th>Submitted At</th>
                        <th>Duration</th>
                        <th>Gross WPM</th>
                        <th>Net WPM</th>
                        <th>Accuracy</th>
                        <th>Errors</th>
                        <th>Score</th>
                        <th>Status</th>
                        <th style="width: 90px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($results)): ?>
                        <tr>
                            <td colspan="14" class="text-center py-5 text-muted">
                                <i class="fas fa-history fa-3x mb-3 d-block opacity-25"></i>
                                No attempt records found for the selected criteria.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($results as $att): ?>
                            <?php
                            $hasRes = !empty($att['id']);
                            $qualStatus = $att['qualification_status'] ?? $att['attempt_status'];
                            $qualBadge = match($qualStatus) {
                                'qualified'    => '<span class="badge bg-success">QUALIFIED</span>',
                                'disqualified' => '<span class="badge bg-danger">DISQUALIFIED</span>',
                                'timed_out'    => '<span class="badge bg-warning text-dark">TIMED OUT</span>',
                                'in_progress'  => '<span class="badge bg-info text-dark">IN PROGRESS</span>',
                                default        => '<span class="badge bg-secondary">NOT QUALIFIED</span>',
                            };
                            ?>
                            <tr>
                                <td>
                                    <strong class="font-monospace text-primary"><?= e($att['roll_number'] ?: '—') ?></strong>
                                </td>
                                <td class="text-start">
                                    <strong class="text-light"><?= e($att['candidate_name']) ?></strong>
                                </td>
                                <td class="font-monospace"><?= e($att['competition_code']) ?></td>
                                <td>
                                    <span class="badge bg-dark border border-secondary font-monospace">#<?= $att['attempt_number'] ?></span>
                                </td>
                                <td class="font-monospace text-muted"><?= !empty($att['started_at']) ? date('H:i:s', strtotime($att['started_at'])) : '—' ?></td>
                                <td class="font-monospace text-muted"><?= !empty($att['submitted_at']) ? date('H:i:s', strtotime($att['submitted_at'])) : '—' ?></td>
                                <td class="font-monospace"><?= (int)$att['attempt_duration'] ?>s</td>
                                <td class="font-monospace text-muted"><?= $hasRes ? number_format((float)$att['gross_wpm'], 2) : '—' ?></td>
                                <td class="font-monospace text-primary fw-bold"><?= $hasRes ? number_format((float)$att['net_wpm'], 2) : '—' ?></td>
                                <td class="font-monospace text-success"><?= $hasRes ? number_format((float)$att['accuracy'], 2) . '%' : '—' ?></td>
                                <td class="font-monospace"><?= $hasRes ? (int)$att['error_count'] : '—' ?></td>
                                <td class="font-monospace text-warning fw-bold"><?= $hasRes ? number_format((float)$att['score'], 2) : '—' ?></td>
                                <td><?= $qualBadge ?></td>
                                <td>
                                    <?php if ($hasRes): ?>
                                        <a href="<?= url('results-view') ?>&id=<?= $att['id'] ?>" class="btn btn-outline-primary btn-sm py-0 px-2" title="View Result Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted small">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
        <div class="card-footer border-secondary d-flex justify-content-between align-items-center py-2">
            <span class="small text-muted">Page <?= $page ?> of <?= $totalPages ?> (Total: <?= number_format($total) ?>)</span>
            <ul class="pagination pagination-sm mb-0">
                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link bg-dark border-secondary text-light" href="<?= url('results') ?>&p=<?= $page - 1 ?>&competition_id=<?= $selectedCompId ?>&view_mode=<?= $viewMode ?>&search=<?= urlencode($search) ?>&candidate_id=<?= $selectedCandidateId ?>">Previous</a>
                </li>
                <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                    <a class="page-link bg-dark border-secondary text-light" href="<?= url('results') ?>&p=<?= $page + 1 ?>&competition_id=<?= $selectedCompId ?>&view_mode=<?= $viewMode ?>&search=<?= urlencode($search) ?>&candidate_id=<?= $selectedCandidateId ?>">Next</a>
                </li>
            </ul>
        </div>
    <?php endif; ?>
</div>
