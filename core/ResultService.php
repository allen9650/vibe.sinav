<?php
declare(strict_types=1);

/**
 * Result Management & Canonical Candidate Selection Service
 * Marr Typing Competition System
 *
 * Centralizes authoritative canonical best-attempt result selection,
 * attempt history retrieval, and synchronized status/ranking calculations.
 */

class ResultService {
    /**
     * Retrieves canonical best-result records (one per candidate).
     *
     * @param int $competitionId 0 for all competitions
     * @param array $filters ['search' => '', 'status' => '', 'competition_id' => 0]
     * @param int $limit 0 for no limit
     * @param int $offset
     * @return array
     */
    public static function getCanonicalResults(int $competitionId = 0, array $filters = [], int $limit = 20, int $offset = 0): array {
        $allCanonical = self::fetchFullCanonicalList($competitionId, $filters);

        if ($limit > 0) {
            return array_slice($allCanonical, $offset, $limit);
        }

        return $allCanonical;
    }

    /**
     * Total count of unique candidates with canonical results matching filters.
     *
     * @param int $competitionId
     * @param array $filters
     * @return int
     */
    public static function getCanonicalResultsCount(int $competitionId = 0, array $filters = []): int {
        $all = self::fetchFullCanonicalList($competitionId, $filters);
        return count($all);
    }

    /**
     * Internal helper to fetch and rank canonical best results per candidate.
     *
     * @param int $competitionId
     * @param array $filters
     * @return array
     */
    private static function fetchFullCanonicalList(int $competitionId = 0, array $filters = []): array {
        $compWhere = ["1=1"];
        $compParams = [];

        if ($competitionId > 0) {
            $compWhere[] = "c.competition_id = ?";
            $compParams[] = $competitionId;
        } elseif (!empty($filters['competition_id'])) {
            $compWhere[] = "c.competition_id = ?";
            $compParams[] = (int)$filters['competition_id'];
        }

        if (!empty($filters['search'])) {
            $search = "%{$filters['search']}%";
            $compWhere[] = "(c.full_name LIKE ? OR c.roll_number LIKE ? OR c.registration_number LIKE ?)";
            $compParams[] = $search;
            $compParams[] = $search;
            $compParams[] = $search;
        }

        $whereSql = implode(' AND ', $compWhere);

        // Fetch candidates
        $candidates = Database::fetchAll("
            SELECT 
                c.*,
                cmp.name AS competition_name,
                cmp.code AS competition_code,
                cmp.status AS competition_status,
                cmp.results_locked,
                cmp.results_finalized_at
            FROM candidates c
            JOIN competitions cmp ON c.competition_id = cmp.id
            WHERE {$whereSql}
            ORDER BY c.competition_id DESC, c.id ASC
        ", $compParams);

        if (empty($candidates)) {
            return [];
        }

        // Group by competition for ranking determination
        $candidatesByComp = [];
        foreach ($candidates as $cand) {
            $cCompId = (int)$cand['competition_id'];
            $candidatesByComp[$cCompId][] = $cand;
        }

        $canonicalList = [];

        foreach ($candidatesByComp as $cCompId => $compCandidates) {
            // Get authoritative leaderboard for this competition to determine rank
            $leaderboard = RankingService::getLeaderboard($cCompId, false);
            $lbMap = [];
            foreach ($leaderboard as $lbRow) {
                $lbMap[(int)$lbRow['candidate_id']] = $lbRow;
            }

            // Fetch certificates issued for this competition
            $certs = Database::fetchAll("
                SELECT id, candidate_id, certificate_number, certificate_type, status 
                FROM certificates 
                WHERE competition_id = ?
            ", [$cCompId]);
            $certMap = [];
            foreach ($certs as $crt) {
                $certMap[(int)$crt['candidate_id']][] = $crt;
            }

            foreach ($compCandidates as $cand) {
                $cId = (int)$cand['id'];
                $isDisqualified = ($cand['status'] === 'disqualified');

                // Get candidate's authoritative best result
                $bestResult = RankingService::getBestFinalizedResultForCandidate($cId, $cCompId);

                if (!$bestResult) {
                    // Check if candidate has any attempts (even in-progress / no result)
                    $totalAttemptsCount = (int)Database::fetchColumn(
                        "SELECT COUNT(*) FROM test_attempts WHERE candidate_id = ? AND competition_id = ?",
                        [$cId, $cCompId]
                    );

                    if ($totalAttemptsCount === 0 && !empty($filters['has_result_only'])) {
                        continue;
                    }

                    // Candidate with no completed test result
                    $canonicalList[] = [
                        'candidate_id'         => $cId,
                        'result_id'            => null,
                        'attempt_id'           => null,
                        'attempt_number'       => 0,
                        'total_attempts'       => $totalAttemptsCount,
                        'candidate_name'       => $cand['full_name'],
                        'roll_number'          => $cand['roll_number'] ?: $cand['registration_number'],
                        'registration_number'  => $cand['registration_number'],
                        'course'               => $cand['course'],
                        'shift'                => $cand['shift'],
                        'competition_id'       => $cCompId,
                        'competition_name'     => $cand['competition_name'],
                        'competition_code'     => $cand['competition_code'],
                        'gross_wpm'            => 0.00,
                        'net_wpm'              => 0.00,
                        'accuracy'             => 0.00,
                        'error_count'          => 0,
                        'score'                => 0.00,
                        'time_taken_seconds'   => 0,
                        'rank'                 => null,
                        'provisional_rank'     => null,
                        'rank_display'         => '—',
                        'qualification_status' => $isDisqualified ? 'disqualified' : 'no_result',
                        'is_finalized'         => !empty($cand['results_finalized_at']),
                        'has_certificate'      => false,
                        'certificate'          => null,
                        'certificate_eligible' => false,
                        'violation_count'      => 0,
                        'submitted_at'         => null,
                        'started_at'           => null,
                    ];
                    continue;
                }

                // Total attempts count for this candidate
                $totalAttemptsCount = (int)Database::fetchColumn(
                    "SELECT COUNT(*) FROM test_attempts WHERE candidate_id = ? AND competition_id = ?",
                    [$cId, $cCompId]
                );

                // Violation count for best attempt
                $violationCount = (int)Database::fetchColumn(
                    "SELECT COUNT(*) FROM security_events WHERE attempt_id = ?",
                    [$bestResult['attempt_id']]
                );

                // Determine rank
                $rankNum = null;
                $rankDisplay = '—';
                $isCompFinalized = !empty($cand['results_finalized_at']);

                if (isset($lbMap[$cId])) {
                    $rankNum = (int)$lbMap[$cId]['rank'];
                    if ($isCompFinalized) {
                        $rankDisplay = '#' . $rankNum;
                    } else {
                        $rankDisplay = 'PROVISIONAL #' . $rankNum;
                    }
                } elseif (!empty($bestResult['rank'])) {
                    $rankNum = (int)$bestResult['rank'];
                    $rankDisplay = '#' . $rankNum;
                }

                // Certificate status
                $candCerts = $certMap[$cId] ?? [];
                $primaryCert = !empty($candCerts) ? $candCerts[0] : null;
                $hasCert = !empty($primaryCert);
                $isCertEligible = !$isDisqualified && in_array($bestResult['attempt_status'], ['completed', 'timed_out', 'submitted'], true);

                $canonicalList[] = [
                    'candidate_id'         => $cId,
                    'result_id'            => (int)$bestResult['id'],
                    'attempt_id'           => (int)$bestResult['attempt_id'],
                    'attempt_number'       => (int)$bestResult['attempt_number'],
                    'total_attempts'       => $totalAttemptsCount,
                    'candidate_name'       => $cand['full_name'],
                    'roll_number'          => $cand['roll_number'] ?: $cand['registration_number'],
                    'registration_number'  => $cand['registration_number'],
                    'course'               => $cand['course'],
                    'shift'                => $cand['shift'],
                    'competition_id'       => $cCompId,
                    'competition_name'     => $cand['competition_name'],
                    'competition_code'     => $cand['competition_code'],
                    'gross_wpm'            => (float)$bestResult['gross_wpm'],
                    'net_wpm'              => (float)$bestResult['net_wpm'],
                    'accuracy'             => (float)$bestResult['accuracy'],
                    'error_count'          => (int)$bestResult['error_count'],
                    'score'                => (float)$bestResult['score'],
                    'time_taken_seconds'   => (int)$bestResult['time_taken_seconds'],
                    'rank'                 => $rankNum,
                    'provisional_rank'     => isset($lbMap[$cId]) ? (int)$lbMap[$cId]['rank'] : null,
                    'rank_display'         => $rankDisplay,
                    'qualification_status' => $isDisqualified ? 'disqualified' : $bestResult['qualification_status'],
                    'is_finalized'         => $isCompFinalized,
                    'has_certificate'      => $hasCert,
                    'certificate'          => $primaryCert,
                    'certificate_eligible' => $isCertEligible,
                    'violation_count'      => $violationCount,
                    'submitted_at'         => $bestResult['submitted_at'],
                    'started_at'           => $bestResult['started_at'],
                ];
            }
        }

        // Apply status filter if provided
        if (!empty($filters['status'])) {
            $canonicalList = array_values(array_filter($canonicalList, function($item) use ($filters) {
                return $item['qualification_status'] === $filters['status'];
            }));
        }

        // Sort canonical list: by competition ID DESC, then Rank ASC (if ranked) or Score DESC
        usort($canonicalList, function($a, $b) {
            if ($a['competition_id'] !== $b['competition_id']) {
                return $b['competition_id'] <=> $a['competition_id'];
            }
            if ($a['rank'] !== null && $b['rank'] !== null) {
                return $a['rank'] <=> $b['rank'];
            }
            if ($a['rank'] !== null) return -1;
            if ($b['rank'] !== null) return 1;

            return $b['score'] <=> $a['score'];
        });

        return $canonicalList;
    }

    /**
     * Retrieves all historical attempt results (every attempt row).
     *
     * @param int $competitionId
     * @param array $filters
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public static function getAllAttemptResults(int $competitionId = 0, array $filters = [], int $limit = 20, int $offset = 0): array {
        $where = ["1=1"];
        $params = [];

        if ($competitionId > 0) {
            $where[] = "a.competition_id = ?";
            $params[] = $competitionId;
        } elseif (!empty($filters['competition_id'])) {
            $where[] = "a.competition_id = ?";
            $params[] = (int)$filters['competition_id'];
        }

        if (!empty($filters['candidate_id'])) {
            $where[] = "a.candidate_id = ?";
            $params[] = (int)$filters['candidate_id'];
        }

        if (!empty($filters['search'])) {
            $search = "%{$filters['search']}%";
            $where[] = "(c.full_name LIKE ? OR c.roll_number LIKE ? OR c.registration_number LIKE ?)";
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }

        if (!empty($filters['status'])) {
            $where[] = "r.qualification_status = ?";
            $params[] = $filters['status'];
        }

        $whereSql = implode(' AND ', $where);

        $limitSql = "";
        if ($limit > 0) {
            $limitSql = "LIMIT {$limit} OFFSET {$offset}";
        }

        $sql = "
            SELECT 
                r.*,
                a.id AS attempt_id,
                a.attempt_number,
                a.started_at,
                a.submitted_at,
                a.time_taken_seconds AS attempt_duration,
                a.status AS attempt_status,
                c.id AS candidate_id,
                c.full_name AS candidate_name,
                c.roll_number,
                c.registration_number,
                c.course,
                c.shift,
                c.status AS candidate_status,
                cmp.id AS competition_id,
                cmp.name AS competition_name,
                cmp.code AS competition_code,
                cmp.results_finalized_at,
                (SELECT COUNT(*) FROM security_events se WHERE se.attempt_id = a.id) AS violation_count
            FROM test_attempts a
            JOIN candidates c ON a.candidate_id = c.id
            JOIN competitions cmp ON a.competition_id = cmp.id
            LEFT JOIN test_results r ON a.id = r.attempt_id
            WHERE {$whereSql}
            ORDER BY a.id DESC
            {$limitSql}
        ";

        return Database::fetchAll($sql, $params);
    }

    /**
     * Count of all historical attempts matching filters.
     *
     * @param int $competitionId
     * @param array $filters
     * @return int
     */
    public static function getAllAttemptResultsCount(int $competitionId = 0, array $filters = []): int {
        $where = ["1=1"];
        $params = [];

        if ($competitionId > 0) {
            $where[] = "a.competition_id = ?";
            $params[] = $competitionId;
        } elseif (!empty($filters['competition_id'])) {
            $where[] = "a.competition_id = ?";
            $params[] = (int)$filters['competition_id'];
        }

        if (!empty($filters['candidate_id'])) {
            $where[] = "a.candidate_id = ?";
            $params[] = (int)$filters['candidate_id'];
        }

        if (!empty($filters['search'])) {
            $search = "%{$filters['search']}%";
            $where[] = "(c.full_name LIKE ? OR c.roll_number LIKE ? OR c.registration_number LIKE ?)";
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }

        if (!empty($filters['status'])) {
            $where[] = "r.qualification_status = ?";
            $params[] = $filters['status'];
        }

        $whereSql = implode(' AND ', $where);

        return (int)Database::fetchColumn("
            SELECT COUNT(*) 
            FROM test_attempts a
            JOIN candidates c ON a.candidate_id = c.id
            LEFT JOIN test_results r ON a.id = r.attempt_id
            WHERE {$whereSql}
        ", $params);
    }

    /**
     * Retrieves all attempt history for a specific candidate.
     *
     * @param int $candidateId
     * @param int $competitionId
     * @return array
     */
    public static function getCandidateAttemptHistory(int $candidateId, int $competitionId = 0): array {
        return self::getAllAttemptResults($competitionId, ['candidate_id' => $candidateId], 0, 0);
    }
}
