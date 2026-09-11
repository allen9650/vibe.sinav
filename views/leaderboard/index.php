<?php
/**
 * Official Competition Leaderboard & Rankings View
 */

$competitions = Database::fetchAll("SELECT id, name, code, status, results_locked, results_finalized_at FROM competitions ORDER BY id DESC");
$selectedCompId = (int)($_GET['competition_id'] ?? 0);
if ($selectedCompId === 0 && !empty($competitions)) {
    $selectedCompId = (int)$competitions[0]['id'];
}

$competition = Database::fetch("SELECT * FROM competitions WHERE id = ?", [$selectedCompId]);
$settings = Database::fetch("SELECT * FROM test_settings WHERE competition_id = ?", [$selectedCompId]) ?: [];
$allAttempts = (bool)($_GET['all_attempts'] ?? 0);

// Check visibility for non-admin candidate sessions
$isAdmin = Session::isAuthenticated() && Auth::hasPermission('leaderboard.view');
$leaderboardVisible = (bool)($settings['leaderboard_visible'] ?? 1);

if (!$isAdmin && !$leaderboardVisible) {
    ?>
    <div class="container py-5 text-center">
        <div class="card bg-dark border-secondary p-5 mx-auto" style="max-width: 600px;">
            <i class="fas fa-lock fa-3x text-warning mb-3"></i>
            <h4 class="text-light fw-bold">Official Leaderboard Restricted</h4>
            <p class="text-muted">Rankings and standings for this competition will be released and announced by the administration.</p>
            <a href="<?= url('test-portal') ?>" class="btn btn-outline-primary btn-sm mt-3">Back to Portal</a>
        </div>
    </div>
    <?php
    return;
}

$leaderboard = $selectedCompId > 0 ? RankingService::getLeaderboard($selectedCompId, $allAttempts) : [];
$isFinalized = !empty($competition['results_finalized_at']);
$isLocked = !empty($competition['results_locked']);

$top3 = array_slice($leaderboard, 0, 3);
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-1"><i class="fas fa-trophy text-warning me-2"></i> Official Competition Leaderboard</h4>
        <div class="text-muted small">
            <?= $competition ? e($competition['name']) . ' (' . e($competition['code']) . ')' : 'Live Standings' ?>
        </div>
    </div>
    <div class="d-flex flex-wrap gap-2 align-items-center">
        <?php if ($isLocked): ?>
            <span class="badge bg-danger p-2"><i class="fas fa-lock me-1"></i> FINAL RESULTS LOCKED</span>
        <?php elseif ($isFinalized): ?>
            <span class="badge bg-success p-2"><i class="fas fa-check-circle me-1"></i> FINALIZED</span>
        <?php else: ?>
            <span class="badge bg-primary bg-opacity-25 text-primary border border-primary border-opacity-50 p-2">
                <i class="fas fa-circle-notch fa-spin me-1"></i> PROVISIONAL / LIVE
            </span>
        <?php endif; ?>

        <?php if ($isAdmin && Auth::hasPermission('competitions.finalize') && !$isFinalized): ?>
            <a href="<?= url('competitions-finalize') ?>&id=<?= $selectedCompId ?>" class="btn btn-success btn-sm">
                <i class="fas fa-flag-checkered me-1"></i> Finalize Competition
            </a>
        <?php endif; ?>

        <?php if ($isAdmin && Auth::hasPermission('results.lock')): ?>
            <?php if (!$isLocked): ?>
                <a href="<?= url('competitions-lock') ?>&id=<?= $selectedCompId ?>&action=lock" class="btn btn-outline-danger btn-sm" onclick="return confirm('Lock final results against modification?')">
                    <i class="fas fa-lock me-1"></i> Lock Results
                </a>
            <?php elseif (Auth::hasRole('super-admin')): ?>
                <a href="<?= url('competitions-lock') ?>&id=<?= $selectedCompId ?>&action=unlock" class="btn btn-outline-warning btn-sm" onclick="return confirm('Unlock results? This action will be audited.')">
                    <i class="fas fa-unlock me-1"></i> Unlock Override
                </a>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Selector Card -->
<div class="card bg-dark border-secondary mb-4">
    <div class="card-body py-3">
        <form method="GET" action="<?= url('leaderboard') ?>" class="row g-2 align-items-center">
            <input type="hidden" name="page" value="leaderboard">

            <div class="col-md-5">
                <select name="competition_id" class="form-select bg-dark text-light border-secondary" onchange="this.form.submit()">
                    <?php foreach ($competitions as $cmp): ?>
                        <option value="<?= $cmp['id'] ?>" <?= $selectedCompId === (int)$cmp['id'] ? 'selected' : '' ?>>
                            <?= e($cmp['name']) ?> (<?= e($cmp['code']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-4">
                <div class="form-check form-switch pt-1">
                    <input class="form-check-input" type="checkbox" id="allAttemptsSwitch" name="all_attempts" value="1" 
                           <?= $allAttempts ? 'checked' : '' ?> onchange="this.form.submit()">
                    <label class="form-check-label text-light small" for="allAttemptsSwitch">
                        Show all attempts (Default: Best per candidate)
                    </label>
                </div>
            </div>

            <div class="col-md-3 text-md-end">
                <a href="<?= url('leaderboard-print') ?>&competition_id=<?= $selectedCompId ?><?= $allAttempts ? '&all_attempts=1' : '' ?>" target="_blank" class="btn btn-outline-light btn-sm shadow-sm">
                    <i class="fas fa-print me-1"></i> Print Standings
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Top 3 Podium Highlights -->
<?php if (!empty($top3)): ?>
    <div class="row g-3 mb-4">
        <?php foreach ($top3 as $idx => $top): ?>
            <div class="col-md-4">
                <div class="card bg-dark border-<?= $idx === 0 ? 'warning' : ($idx === 1 ? 'secondary' : 'info') ?> h-100 shadow">
                    <div class="card-body text-center py-4">
                        <div class="fs-1 mb-1"><?= $top['medal']['icon'] ?? '🏅' ?></div>
                        <h5 class="fw-bold text-light mb-1"><?= e($top['candidate_name']) ?></h5>
                        <div class="text-muted small font-monospace mb-3">Roll: <?= e($top['roll_number'] ?: '—') ?></div>
                        <div class="row g-2 text-center small border-top border-secondary pt-3">
                            <div class="col-4">
                                <span class="text-muted d-block">Score</span>
                                <strong class="text-warning fs-5"><?= number_format($top['score'], 2) ?></strong>
                            </div>
                            <div class="col-4">
                                <span class="text-muted d-block">Net WPM</span>
                                <strong class="text-primary fs-5"><?= number_format($top['net_wpm'], 2) ?></strong>
                            </div>
                            <div class="col-4">
                                <span class="text-muted d-block">Accuracy</span>
                                <strong class="text-success fs-5"><?= number_format($top['accuracy'], 2) ?>%</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Leaderboard Table -->
<div class="card bg-dark border-secondary">
    <div class="card-header border-secondary d-flex justify-content-between align-items-center">
        <span class="fw-bold"><i class="fas fa-list-ol me-1"></i> Official Rankings Table</span>
        <span class="badge bg-secondary"><?= count($leaderboard) ?> Ranked Candidates</span>
    </div>
    <div class="table-responsive">
        <table class="table table-dark table-hover table-bordered mb-0 align-middle text-center">
            <thead class="table-secondary text-light small text-uppercase">
                <tr>
                    <th style="width: 70px;">Rank</th>
                    <th class="text-start">Candidate Name</th>
                    <th>Roll #</th>
                    <th>Course / Shift</th>
                    <th>Net WPM</th>
                    <th>Accuracy</th>
                    <th>Gross WPM</th>
                    <th>Errors</th>
                    <th>Time</th>
                    <th>Final Score</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($leaderboard)): ?>
                    <tr>
                        <td colspan="11" class="py-5 text-muted">
                            <i class="fas fa-keyboard fa-3x mb-3 d-block opacity-25"></i>
                            No completed test results available for this competition yet.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($leaderboard as $row): ?>
                        <tr class="<?= (int)$row['rank'] === 1 ? 'table-active' : '' ?>">
                            <td>
                                <?php if ($row['medal']): ?>
                                    <span class="fs-5" title="<?= e($row['medal']['title']) ?>"><?= $row['medal']['icon'] ?></span>
                                    <span class="fw-bold ms-1 font-monospace">#<?= $row['rank'] ?></span>
                                <?php else: ?>
                                    <span class="badge bg-dark border border-secondary font-monospace">#<?= $row['rank'] ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="text-start">
                                <strong class="text-light"><?= e($row['candidate_name']) ?></strong>
                            </td>
                            <td>
                                <span class="badge bg-dark border border-secondary font-monospace"><?= e($row['roll_number'] ?: '—') ?></span>
                            </td>
                            <td class="small text-muted">
                                <?= e($row['course'] ?: '—') ?> (<?= e($row['shift'] ?: '—') ?>)
                            </td>
                            <td>
                                <strong class="text-primary font-monospace"><?= number_format($row['net_wpm'], 2) ?></strong>
                            </td>
                            <td>
                                <span class="text-success font-monospace"><?= number_format($row['accuracy'], 2) ?>%</span>
                            </td>
                            <td class="small text-muted font-monospace"><?= number_format($row['gross_wpm'], 2) ?></td>
                            <td>
                                <span class="badge bg-danger bg-opacity-25 text-danger font-monospace"><?= (int)$row['error_count'] ?></span>
                            </td>
                            <td class="small text-muted font-monospace"><?= (int)$row['time_taken_seconds'] ?>s</td>
                            <td>
                                <strong class="text-warning fs-6 font-monospace"><?= number_format($row['score'], 2) ?></strong>
                            </td>
                            <td>
                                <?php if ($row['qualification_status'] === 'qualified'): ?>
                                    <span class="badge bg-success">QUALIFIED</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">NOT QUALIFIED</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
