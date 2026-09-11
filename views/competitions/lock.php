<?php
/**
 * Lock / Unlock Competition Results Action Handler
 */

Middleware::requirePermission('results.lock');

$competitionId = (int)($_GET['id'] ?? 0);
$action = trim($_GET['action'] ?? 'lock');

$competition = Database::fetch("SELECT * FROM competitions WHERE id = ?", [$competitionId]);

if (!$competition) {
    Session::flash('error', 'Competition not found.');
    redirectTo('competitions');
}

if ($action === 'unlock') {
    // Only Super Admin can override unlock
    if (!Auth::hasRole('super-admin')) {
        Session::flash('error', 'Only Super Administrators can unlock finalized competition results.');
        redirectTo('leaderboard', ['competition_id' => $competitionId]);
    }

    RankingService::unlockResults($competitionId, Session::get('user_id'));
    Session::flash('success', "Results for competition '{$competition['name']}' unlocked by administrator override.");
} else {
    RankingService::lockResults($competitionId, Session::get('user_id'));
    Session::flash('success', "Results for competition '{$competition['name']}' have been permanently locked.");
}

redirectTo('leaderboard', ['competition_id' => $competitionId]);
