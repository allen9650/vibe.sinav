<?php
/**
 * Centralized Competition Ranking & Leaderboard Engine
 * Marr Typing Competition System
 *
 * Provides authoritative ranking calculations:
 * 1. Highest Final Score
 * 2. Highest Accuracy %
 * 3. Highest Net WPM
 * 4. Lowest Total Errors
 * 5. Shortest Scoring Duration
 *
 * Supports shared ranks, medal positions (1st, 2nd, 3rd),
 * multiple-attempt best selection, and result freezing.
 */

class RankingService {
    /**
     * Retrieves ranked leaderboard entries for a given competition.
     *
     * @param int $competitionId
     * @param bool $allAttempts If true, includes all attempts; default false selects candidate's best attempt
     * @return array Ranked results with rank and medal metadata
     */
    public static function getLeaderboard(int $competitionId, bool $allAttempts = false): array {
        // Fetch all completed/timed_out results with candidate and attempt details
        $sql = "
            SELECT 
                r.*,
                c.full_name AS candidate_name,
                c.roll_number,
                c.registration_number,
                c.course,
                c.batch,
                c.shift,
                c.gender,
                c.status AS candidate_status,
                a.attempt_number,
                a.status AS attempt_status,
                a.started_at,
                a.submitted_at,
                cmp.name AS competition_name,
                cmp.code AS competition_code,
                cmp.results_locked,
                cmp.results_finalized_at
            FROM test_results r
            JOIN candidates c ON r.candidate_id = c.id
            JOIN test_attempts a ON r.attempt_id = a.id
            JOIN competitions cmp ON r.competition_id = cmp.id
            WHERE r.competition_id = ?
              AND a.status IN ('completed', 'timed_out')
              AND r.qualification_status != 'disqualified'
              AND c.status != 'disqualified'
        ";

        $rows = Database::fetchAll($sql, [$competitionId]);

        if (empty($rows)) {
            return [];
        }

        // Filter for Best Attempt per candidate if $allAttempts is false
        $processedRows = [];
        if (!$allAttempts) {
            $candidateGroups = [];
            foreach ($rows as $row) {
                $cId = (int)$row['candidate_id'];
                $candidateGroups[$cId][] = $row;
            }

            foreach ($candidateGroups as $cId => $attempts) {
                // Sort candidate's attempts using standard ranking comparison
                usort($attempts, function(array $a, array $b): int {
                    $cmp = self::compareResults($a, $b);
                    if ($cmp !== 0) {
                        return $cmp;
                    }
                    // For candidate's own tied attempts, choose latest attempt
                    $attNumA = (int)($a['attempt_number'] ?? 0);
                    $attNumB = (int)($b['attempt_number'] ?? 0);
                    if ($attNumA !== $attNumB) {
                        return $attNumA > $attNumB ? -1 : 1;
                    }
                    $idA = (int)($a['id'] ?? 0);
                    $idB = (int)($b['id'] ?? 0);
                    return $idA > $idB ? -1 : 1;
                });
                // Pick top (best) attempt
                $processedRows[] = $attempts[0];
            }
        } else {
            $processedRows = $rows;
        }

        // Global sort across all candidates
        usort($processedRows, [self::class, 'compareResults']);

        // Assign Ranks and Medals (handling shared ranks for exact ties)
        $ranked = [];
        $currentRank = 1;
        $total = count($processedRows);

        for ($i = 0; $i < $total; $i++) {
            if ($i > 0) {
                // Check if current row is tied with previous row across all criteria
                if (self::compareResults($processedRows[$i], $processedRows[$i - 1]) === 0) {
                    // Shared rank: retain previous rank
                    $rankToAssign = $ranked[$i - 1]['rank'];
                } else {
                    // Distinct rank: 1-indexed position
                    $rankToAssign = $i + 1;
                }
            } else {
                $rankToAssign = 1;
            }

            $entry = $processedRows[$i];
            $entry['rank'] = $rankToAssign;
            $entry['medal'] = self::getMedal($rankToAssign);
            $ranked[] = $entry;
        }

        return $ranked;
    }

    /**
     * Retrieves the single best finalized result for a candidate in a competition.
     * Returns the complete, authoritative test_results row as a single unit.
     *
     * @param int $candidateId
     * @param int $competitionId
     * @return array|null
     */
    public static function getBestFinalizedResultForCandidate(int $candidateId, int $competitionId): ?array {
        $sql = "
            SELECT 
                r.*,
                c.full_name AS candidate_name,
                c.roll_number,
                c.registration_number,
                c.course,
                c.batch,
                c.shift,
                c.gender,
                c.status AS candidate_status,
                a.attempt_number,
                a.status AS attempt_status,
                a.started_at,
                a.submitted_at,
                cmp.name AS competition_name,
                cmp.code AS competition_code,
                cmp.results_locked,
                cmp.results_finalized_at
            FROM test_results r
            JOIN candidates c ON r.candidate_id = c.id
            JOIN test_attempts a ON r.attempt_id = a.id
            JOIN competitions cmp ON r.competition_id = cmp.id
            WHERE r.candidate_id = ?
              AND r.competition_id = ?
              AND a.status IN ('completed', 'timed_out')
              AND r.qualification_status != 'disqualified'
              AND c.status != 'disqualified'
            ORDER BY 
                r.score DESC,
                r.accuracy DESC,
                r.net_wpm DESC,
                r.error_count ASC,
                r.time_taken_seconds ASC,
                a.attempt_number DESC,
                r.id DESC
            LIMIT 1
        ";
        return Database::fetch($sql, [$candidateId, $competitionId]);
    }

    /**
     * Deterministic comparison between two result records.
     * Return < 0 if $a ranks higher than $b; > 0 if $b ranks higher than $a; 0 if exact tie.
     *
     * @param array $a
     * @param array $b
     * @return int
     */
    public static function compareResults(array $a, array $b): int {
        // 1. Highest Final Score
        $scoreA = (float)($a['score'] ?? 0.0);
        $scoreB = (float)($b['score'] ?? 0.0);
        if ($scoreA !== $scoreB) {
            return $scoreA > $scoreB ? -1 : 1;
        }

        // 2. Highest Accuracy %
        $accA = (float)($a['accuracy'] ?? 0.0);
        $accB = (float)($b['accuracy'] ?? 0.0);
        if ($accA !== $accB) {
            return $accA > $accB ? -1 : 1;
        }

        // 3. Highest Net WPM
        $netA = (float)($a['net_wpm'] ?? 0.0);
        $netB = (float)($b['net_wpm'] ?? 0.0);
        if ($netA !== $netB) {
            return $netA > $netB ? -1 : 1;
        }

        // 4. Lowest Total Errors (ASC)
        $errA = (int)($a['error_count'] ?? 0);
        $errB = (int)($b['error_count'] ?? 0);
        if ($errA !== $errB) {
            return $errA < $errB ? -1 : 1;
        }

        // 5. Shortest Scoring Duration / Completion Time (ASC)
        $timeA = (int)($a['time_taken_seconds'] ?? 0);
        $timeB = (int)($b['time_taken_seconds'] ?? 0);
        if ($timeA !== $timeB) {
            return $timeA < $timeB ? -1 : 1;
        }

        // Exact tie across all primary metrics
        return 0;
    }

    /**
     * Maps rank number to medal symbol and title.
     *
     * @param int $rank
     * @return array|null
     */
    public static function getMedal(int $rank): ?array {
        if ($rank === 1) {
            return ['icon' => '🥇', 'title' => '1st Position (Gold)', 'class' => 'badge-gold'];
        } elseif ($rank === 2) {
            return ['icon' => '🥈', 'title' => '2nd Position (Silver)', 'class' => 'badge-silver'];
        } elseif ($rank === 3) {
            return ['icon' => '🥉', 'title' => '3rd Position (Bronze)', 'class' => 'badge-bronze'];
        }
        return null;
    }

    /**
     * Finalizes competition, freezes ranks in test_results, and timestamps competition.
     *
     * @param int $competitionId
     * @param int $userId Admin user performing finalization
     * @return array Summary of finalization
     */
    public static function finalizeCompetition(int $competitionId, int $userId): array {
        $comp = Database::fetch("SELECT * FROM competitions WHERE id = ?", [$competitionId]);
        if (!$comp) {
            throw new Exception("Competition not found.");
        }

        Database::beginTransaction();
        try {
            $rankedLeaderboard = self::getLeaderboard($competitionId, false);

            // Reset any existing ranks for all results of this competition
            Database::update('test_results', ['rank' => null], 'competition_id = ?', [$competitionId]);

            // Update rank field in test_results for all candidate best attempts
            foreach ($rankedLeaderboard as $entry) {
                Database::update('test_results', [
                    'rank' => (int)$entry['rank'],
                ], 'id = ?', [$entry['id']]);
            }

            // Mark competition finalized
            Database::update('competitions', [
                'status'                => 'completed',
                'results_finalized_at'  => date('Y-m-d H:i:s'),
                'results_finalized_by'  => $userId,
            ], 'id = ?', [$competitionId]);

            Database::commit();

            AuditLog::log('competition_finalized', 'competitions', 
                "Competition #{$competitionId} ('{$comp['name']}') finalized with " . count($rankedLeaderboard) . " ranked candidates.", 
                $userId, 
                null, 
                ['competition_id' => $competitionId, 'total_ranked' => count($rankedLeaderboard)]
            );

            return [
                'status'       => 'success',
                'total_ranked' => count($rankedLeaderboard),
                'finalized_at' => date('Y-m-d H:i:s'),
            ];
        } catch (Exception $e) {
            Database::rollback();
            throw $e;
        }
    }

    /**
     * Locks final results against further modification.
     *
     * @param int $competitionId
     * @param int $userId
     * @return bool
     */
    public static function lockResults(int $competitionId, int $userId): bool {
        Database::update('competitions', [
            'results_locked'    => 1,
            'results_locked_at' => date('Y-m-d H:i:s'),
            'results_locked_by' => $userId,
        ], 'id = ?', [$competitionId]);

        AuditLog::log('results_locked', 'competitions', 
            "Competition #{$competitionId} results locked against modification.", 
            $userId, 
            null, 
            ['competition_id' => $competitionId]
        );

        return true;
    }

    /**
     * Unlocks results (Super Admin override).
     *
     * @param int $competitionId
     * @param int $userId
     * @return bool
     */
    public static function unlockResults(int $competitionId, int $userId): bool {
        Database::update('competitions', [
            'results_locked'    => 0,
            'results_locked_at' => null,
            'results_locked_by' => null,
        ], 'id = ?', [$competitionId]);

        AuditLog::log('results_unlocked', 'competitions', 
            "Competition #{$competitionId} results unlocked by Super Admin override.", 
            $userId, 
            null, 
            ['competition_id' => $competitionId]
        );

        return true;
    }
}
