<?php
/**
 * Competition Finalization Confirmation Screen
 */

Middleware::requirePermission('competitions.finalize');

$competitionId = (int)($_GET['id'] ?? 0);
$competition = Database::fetch("SELECT * FROM competitions WHERE id = ?", [$competitionId]);

if (!$competition) {
    Session::flash('error', 'Competition not found.');
    redirectTo('competitions');
}

// Check stats
$totalCandidates = (int)Database::fetchColumn("SELECT COUNT(*) FROM candidates WHERE competition_id = ?", [$competitionId]);
$presentCandidates = (int)Database::fetchColumn("SELECT COUNT(*) FROM attendance WHERE competition_id = ? AND status = 'present'", [$competitionId]);
$absentCandidates = (int)Database::fetchColumn("SELECT COUNT(*) FROM attendance WHERE competition_id = ? AND status = 'absent'", [$competitionId]);

$completedAttempts = (int)Database::fetchColumn("SELECT COUNT(*) FROM test_attempts WHERE competition_id = ? AND status = 'completed'", [$competitionId]);
$timedOutAttempts = (int)Database::fetchColumn("SELECT COUNT(*) FROM test_attempts WHERE competition_id = ? AND status = 'timed_out'", [$competitionId]);
$activeAttempts = (int)Database::fetchColumn("SELECT COUNT(*) FROM test_attempts WHERE competition_id = ? AND status = 'in_progress'", [$competitionId]);
$disqualifiedAttempts = (int)Database::fetchColumn("SELECT COUNT(*) FROM test_attempts WHERE competition_id = ? AND status = 'disqualified'", [$competitionId]);

$rankedLeaderboard = RankingService::getLeaderboard($competitionId, false);
$totalRanked = count($rankedLeaderboard);

$error = '';
$success = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    CSRF::validateOrFail();

    try {
        $result = RankingService::finalizeCompetition($competitionId, Session::get('user_id'));
        Session::flash('success', "Competition '{$competition['name']}' finalized successfully! Total {$result['total_ranked']} candidate ranks generated.");
        redirectTo('leaderboard', ['competition_id' => $competitionId]);
    } catch (Exception $e) {
        $error = "Finalization failed: " . $e->getMessage();
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1"><i class="fas fa-flag-checkered text-success me-2"></i> Finalize Competition</h4>
        <div class="text-muted small"><?= e($competition['name']) ?> (<?= e($competition['code']) ?>)</div>
    </div>
    <div>
        <a href="<?= url('leaderboard') ?>&competition_id=<?= $competitionId ?>" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-arrow-left me-1"></i> Back to Leaderboard
        </a>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card bg-dark border-secondary">
            <div class="card-header border-secondary bg-dark text-light fw-bold py-3">
                <i class="fas fa-clipboard-check me-2 text-primary"></i> Pre-Finalization Competition Summary
            </div>
            <div class="card-body p-4">
                <?php if ($error): ?>
                    <div class="alert alert-danger py-2 px-3 mb-4 small">
                        <i class="fas fa-exclamation-circle me-2"></i> <?= e($error) ?>
                    </div>
                <?php endif; ?>

                <?php if ($activeAttempts > 0): ?>
                    <div class="alert alert-warning py-3 px-4 mb-4 small">
                        <i class="fas fa-exclamation-triangle me-2 fa-lg"></i>
                        <strong>Active Test Warning:</strong> There are currently <strong><?= $activeAttempts ?> active test session(s)</strong> in progress. Finalizing now will exclude ongoing attempts.
                    </div>
                <?php endif; ?>

                <div class="row g-3 mb-4">
                    <div class="col-sm-6 col-md-3">
                        <div class="p-3 bg-secondary bg-opacity-10 rounded border border-secondary text-center">
                            <span class="text-muted small d-block">Registered</span>
                            <strong class="fs-4 text-light font-monospace"><?= $totalCandidates ?></strong>
                        </div>
                    </div>
                    <div class="col-sm-6 col-md-3">
                        <div class="p-3 bg-secondary bg-opacity-10 rounded border border-secondary text-center">
                            <span class="text-muted small d-block">Present / Absent</span>
                            <strong class="fs-4 text-success font-monospace"><?= $presentCandidates ?></strong>
                            <span class="text-muted">/</span>
                            <strong class="fs-4 text-danger font-monospace"><?= $absentCandidates ?></strong>
                        </div>
                    </div>
                    <div class="col-sm-6 col-md-3">
                        <div class="p-3 bg-secondary bg-opacity-10 rounded border border-secondary text-center">
                            <span class="text-muted small d-block">Completed Tests</span>
                            <strong class="fs-4 text-info font-monospace"><?= $completedAttempts + $timedOutAttempts ?></strong>
                        </div>
                    </div>
                    <div class="col-sm-6 col-md-3">
                        <div class="p-3 bg-secondary bg-opacity-10 rounded border border-secondary text-center">
                            <span class="text-muted small d-block">Valid Ranked</span>
                            <strong class="fs-4 text-warning font-monospace"><?= $totalRanked ?></strong>
                        </div>
                    </div>
                </div>

                <div class="p-3 bg-dark border border-secondary rounded mb-4">
                    <h6 class="text-warning mb-2"><i class="fas fa-info-circle me-1"></i> What happens upon Finalization:</h6>
                    <ul class="small text-muted mb-0 ps-3" style="line-height: 1.8;">
                        <li>Final deterministic ranks (1st, 2nd, 3rd, etc.) will be computed and permanently saved to <code>test_results</code>.</li>
                        <li>Competition status will transition to <strong>Completed</strong>.</li>
                        <li>Audit log record will be generated with full timestamp and administrator ID.</li>
                    </ul>
                </div>

                <form method="POST" action="<?= url('competitions-finalize') ?>&id=<?= $competitionId ?>">
                    <?= CSRF::field() ?>
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-success btn-lg fw-bold py-3">
                            <i class="fas fa-flag-checkered me-2"></i> Confirm & Finalize Competition
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
