<?php
/**
 * Reports & Analytics Engine
 * Marr Typing Competition System
 *
 * Provides authoritative reporting, multi-filter aggregations,
 * and secure CSV/Excel export with formula-injection prevention.
 */

class ReportService {
    /**
     * Sanitizes string values to prevent CSV / Excel Formula Injection (DDE attacks).
     * Prefixes dangerous starting characters (=, +, -, @, tab, carriage return) with an apostrophe.
     *
     * @param mixed $value
     * @return string
     */
    public static function sanitizeCsvValue($value): string {
        if ($value === null) return '';
        $str = (string)$value;
        $dangerousChars = ['=', '+', '-', '@', "\t", "\r"];
        if ($str !== '' && in_array($str[0], $dangerousChars, true)) {
            $str = "'" . $str;
        }
        return $str;
    }

    /**
     * Streams or outputs a secure Excel-compatible CSV file.
     *
     * @param string $filename
     * @param array $headers
     * @param array $rows
     */
    public static function exportCsv(string $filename, array $headers, array $rows): void {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . rawurlencode($filename) . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'w');

        // Write UTF-8 BOM for automatic Excel encoding recognition
        fwrite($out, "\xEF\xBB\xBF");

        // Write headers
        fputcsv($out, $headers);

        // Write sanitized rows
        foreach ($rows as $row) {
            $sanitized = array_map([self::class, 'sanitizeCsvValue'], $row);
            fputcsv($out, $sanitized);
        }

        fclose($out);
        exit;
    }

    /**
     * Builds SQL WHERE clause and parameters from global filter inputs.
     *
     * @param array $filters
     * @param string $alias Prefix for candidates (e.g. 'c')
     * @param string $resAlias Prefix for results (e.g. 'r')
     * @return array [$whereSql, $params]
     */
    public static function buildFilterConditions(array $filters, string $alias = 'c', string $resAlias = 'r'): array {
        $where = ["1=1"];
        $params = [];

        if (!empty($filters['competition_id'])) {
            $where[] = "{$alias}.competition_id = ?";
            $params[] = (int)$filters['competition_id'];
        }

        if (!empty($filters['date_from'])) {
            $where[] = "DATE({$alias}.created_at) >= ?";
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = "DATE({$alias}.created_at) <= ?";
            $params[] = $filters['date_to'];
        }

        if (!empty($filters['search'])) {
            $where[] = "({$alias}.full_name LIKE ? OR {$alias}.roll_number LIKE ? OR {$alias}.registration_number LIKE ?)";
            $like = "%{$filters['search']}%";
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        if (!empty($filters['course'])) {
            $where[] = "{$alias}.course = ?";
            $params[] = $filters['course'];
        }

        if (!empty($filters['batch'])) {
            $where[] = "{$alias}.batch = ?";
            $params[] = $filters['batch'];
        }

        if (!empty($filters['shift'])) {
            $where[] = "{$alias}.shift = ?";
            $params[] = $filters['shift'];
        }

        if (!empty($filters['branch'])) {
            $where[] = "{$alias}.branch = ?";
            $params[] = $filters['branch'];
        }

        if (!empty($filters['qualification_status']) && $resAlias) {
            $where[] = "{$resAlias}.qualification_status = ?";
            $params[] = $filters['qualification_status'];
        }

        if (isset($filters['min_wpm']) && $filters['min_wpm'] !== '' && $resAlias) {
            $where[] = "{$resAlias}.net_wpm >= ?";
            $params[] = (float)$filters['min_wpm'];
        }

        if (isset($filters['max_wpm']) && $filters['max_wpm'] !== '' && $resAlias) {
            $where[] = "{$resAlias}.net_wpm <= ?";
            $params[] = (float)$filters['max_wpm'];
        }

        if (isset($filters['min_accuracy']) && $filters['min_accuracy'] !== '' && $resAlias) {
            $where[] = "{$resAlias}.accuracy >= ?";
            $params[] = (float)$filters['min_accuracy'];
        }

        if (isset($filters['max_accuracy']) && $filters['max_accuracy'] !== '' && $resAlias) {
            $where[] = "{$resAlias}.accuracy <= ?";
            $params[] = (float)$filters['max_accuracy'];
        }

        return [implode(' AND ', $where), $params];
    }

    /**
     * Report 1: Competition Results Report
     */
    public static function getCompetitionResults(array $filters): array {
        [$whereSql, $params] = self::buildFilterConditions($filters, 'c', 'r');

        $sql = "
            SELECT 
                r.*,
                c.full_name AS candidate_name,
                c.father_name,
                c.roll_number,
                c.registration_number,
                c.course,
                c.shift,
                c.batch,
                c.branch,
                cmp.name AS competition_name,
                cmp.code AS competition_code,
                a.attempt_number,
                a.started_at,
                a.submitted_at
            FROM test_results r
            JOIN candidates c ON r.candidate_id = c.id
            JOIN competitions cmp ON r.competition_id = cmp.id
            JOIN test_attempts a ON r.attempt_id = a.id
            WHERE {$whereSql}
            ORDER BY 
                CASE WHEN r.rank IS NOT NULL AND r.rank > 0 THEN r.rank ELSE 999999 END ASC,
                r.score DESC, 
                r.accuracy DESC
        ";

        return Database::fetchAll($sql, $params);
    }

    /**
     * Report 4: Attendance Report
     */
    public static function getAttendanceReport(array $filters): array {
        [$whereSql, $params] = self::buildFilterConditions($filters, 'c', '');

        $sql = "
            SELECT 
                c.id AS candidate_id,
                c.full_name AS candidate_name,
                c.father_name,
                c.roll_number,
                c.registration_number,
                c.course,
                c.shift,
                c.batch,
                c.branch,
                c.status AS candidate_status,
                cmp.name AS competition_name,
                cmp.code AS competition_code,
                att.status AS attendance_status,
                att.check_in_time,
                (SELECT COUNT(*) FROM test_attempts a WHERE a.candidate_id = c.id AND a.status = 'completed') AS completed_tests_count
            FROM candidates c
            JOIN competitions cmp ON c.competition_id = cmp.id
            LEFT JOIN attendance att ON c.id = att.candidate_id AND c.competition_id = att.competition_id
            WHERE {$whereSql}
            ORDER BY c.roll_number ASC, c.full_name ASC
        ";

        return Database::fetchAll($sql, $params);
    }

    /**
     * Report 6, 7, 8: Grouped Analytics (Course, Shift, Branch)
     */
    public static function getGroupedAnalytics(string $groupBy, array $filters): array {
        $allowedGroups = ['course', 'shift', 'branch', 'batch'];
        if (!in_array($groupBy, $allowedGroups, true)) {
            $groupBy = 'course';
        }

        [$whereSql, $params] = self::buildFilterConditions($filters, 'c', 'r');

        $sql = "
            SELECT 
                COALESCE(NULLIF(c.{$groupBy}, ''), 'Unspecified') AS group_name,
                COUNT(DISTINCT c.id) AS total_candidates,
                COUNT(DISTINCT r.id) AS completed_tests,
                ROUND(AVG(r.gross_wpm), 2) AS avg_gross_wpm,
                ROUND(AVG(r.net_wpm), 2) AS avg_net_wpm,
                ROUND(AVG(r.accuracy), 2) AS avg_accuracy,
                COALESCE(MAX(r.net_wpm), 0) AS highest_net_wpm,
                COALESCE(MIN(r.net_wpm), 0) AS lowest_net_wpm,
                SUM(CASE WHEN r.qualification_status = 'qualified' THEN 1 ELSE 0 END) AS qualified_count,
                SUM(CASE WHEN r.qualification_status = 'not_qualified' THEN 1 ELSE 0 END) AS not_qualified_count,
                SUM(CASE WHEN c.status = 'disqualified' THEN 1 ELSE 0 END) AS disqualified_count
            FROM candidates c
            LEFT JOIN test_results r ON c.id = r.candidate_id
            JOIN competitions cmp ON c.competition_id = cmp.id
            WHERE {$whereSql}
            GROUP BY c.{$groupBy}
            ORDER BY completed_tests DESC, avg_net_wpm DESC
        ";

        return Database::fetchAll($sql, $params);
    }

    /**
     * Report 9: WPM Range Distribution
     */
    public static function getWpmDistribution(array $filters): array {
        [$whereSql, $params] = self::buildFilterConditions($filters, 'c', 'r');

        $sql = "
            SELECT 
                CASE 
                    WHEN r.net_wpm < 20 THEN 'Under 20 WPM'
                    WHEN r.net_wpm >= 20 AND r.net_wpm < 30 THEN '20 – 29.99 WPM'
                    WHEN r.net_wpm >= 30 AND r.net_wpm < 40 THEN '30 – 39.99 WPM'
                    WHEN r.net_wpm >= 40 AND r.net_wpm < 50 THEN '40 – 49.99 WPM'
                    WHEN r.net_wpm >= 50 AND r.net_wpm < 60 THEN '50 – 59.99 WPM'
                    ELSE '60+ WPM'
                END AS speed_range,
                COUNT(r.id) AS candidates_count,
                ROUND(AVG(r.net_wpm), 2) AS avg_wpm_in_range,
                ROUND(AVG(r.accuracy), 2) AS avg_accuracy_in_range
            FROM test_results r
            JOIN candidates c ON r.candidate_id = c.id
            WHERE {$whereSql}
            GROUP BY speed_range
            ORDER BY MIN(r.net_wpm) ASC
        ";

        return Database::fetchAll($sql, $params);
    }

    /**
     * Report 10: Accuracy Range Distribution
     */
    public static function getAccuracyDistribution(array $filters): array {
        [$whereSql, $params] = self::buildFilterConditions($filters, 'c', 'r');

        $sql = "
            SELECT 
                CASE 
                    WHEN r.accuracy < 80 THEN 'Below 80%'
                    WHEN r.accuracy >= 80 AND r.accuracy < 90 THEN '80% – 89.99%'
                    WHEN r.accuracy >= 90 AND r.accuracy < 95 THEN '90% – 94.99%'
                    WHEN r.accuracy >= 95 AND r.accuracy < 98 THEN '95% – 97.99%'
                    WHEN r.accuracy >= 98 AND r.accuracy < 100 THEN '98% – 99.99%'
                    ELSE '100% Perfect'
                END AS accuracy_bracket,
                COUNT(r.id) AS candidates_count,
                ROUND(AVG(r.net_wpm), 2) AS avg_wpm_in_bracket
            FROM test_results r
            JOIN candidates c ON r.candidate_id = c.id
            WHERE {$whereSql}
            GROUP BY accuracy_bracket
            ORDER BY MIN(r.accuracy) ASC
        ";

        return Database::fetchAll($sql, $params);
    }

    /**
     * Report 11: Error Analysis Report
     */
    public static function getErrorAnalysis(array $filters): array {
        [$whereSql, $params] = self::buildFilterConditions($filters, 'c', 'r');

        $sql = "
            SELECT 
                r.id,
                r.candidate_id,
                r.attempt_id,
                c.full_name AS candidate_name,
                c.roll_number,
                cmp.code AS competition_code,
                r.net_wpm,
                r.accuracy,
                r.total_characters,
                r.correct_characters,
                r.incorrect_characters,
                r.missing_characters,
                r.extra_characters,
                r.error_count AS total_errors,
                r.errors_per_minute,
                r.backspace_count
            FROM test_results r
            JOIN candidates c ON r.candidate_id = c.id
            JOIN competitions cmp ON r.competition_id = cmp.id
            WHERE {$whereSql}
            ORDER BY r.error_count DESC, r.errors_per_minute DESC
        ";

        return Database::fetchAll($sql, $params);
    }

    /**
     * Report 13: Security Violations Summary
     */
    public static function getSecurityReport(array $filters): array {
        $where = ["1=1"];
        $params = [];

        if (!empty($filters['competition_id'])) {
            $where[] = "se.competition_id = ?";
            $params[] = (int)$filters['competition_id'];
        }

        $whereSql = implode(' AND ', $where);

        $sql = "
            SELECT 
                c.id AS candidate_id,
                c.full_name AS candidate_name,
                c.roll_number,
                c.registration_number,
                cmp.name AS competition_name,
                cmp.code AS competition_code,
                a.id AS attempt_id,
                a.status AS attempt_status,
                SUM(CASE WHEN se.event_type = 'TAB_SWITCH' THEN 1 ELSE 0 END) AS tab_switches,
                SUM(CASE WHEN se.event_type = 'WINDOW_BLUR' THEN 1 ELSE 0 END) AS window_blurs,
                SUM(CASE WHEN se.event_type = 'FULLSCREEN_EXIT' THEN 1 ELSE 0 END) AS fullscreen_exits,
                SUM(CASE WHEN se.event_type = 'COPY_ATTEMPT' THEN 1 ELSE 0 END) AS copy_attempts,
                SUM(CASE WHEN se.event_type = 'PASTE_ATTEMPT' THEN 1 ELSE 0 END) AS paste_attempts,
                SUM(CASE WHEN se.event_type = 'CUT_ATTEMPT' THEN 1 ELSE 0 END) AS cut_attempts,
                SUM(CASE WHEN se.event_type = 'RIGHT_CLICK_ATTEMPT' THEN 1 ELSE 0 END) AS right_clicks,
                COUNT(se.id) AS total_violations
            FROM security_events se
            JOIN candidates c ON se.candidate_id = c.id
            JOIN competitions cmp ON se.competition_id = cmp.id
            JOIN test_attempts a ON se.attempt_id = a.id
            WHERE {$whereSql}
            GROUP BY se.attempt_id
            ORDER BY total_violations DESC
        ";

        return Database::fetchAll($sql, $params);
    }
}
