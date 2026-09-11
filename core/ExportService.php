<?php
declare(strict_types=1);

/**
 * PTM Assessment System — Export Service
 * Generates and streams RFC 4180 compliant CSV exports for assessment results and station attendance.
 */
class ExportService
{
    /**
     * Stream an RFC 4180 compliant CSV of student attendance, station codes, scores, and pass/fail statuses.
     *
     * @param int $assessmentId Target assessment ID.
     * @return void
     * @throws RuntimeException
     */
    public static function exportResultsToCSV(int $assessmentId): void
    {
        $assessment = Database::fetch(
            "SELECT a.*, c.name AS campus_name, s.name AS subject_name 
             FROM assessments a
             JOIN campuses c ON a.campus_id = c.id
             JOIN subjects s ON a.subject_id = s.id
             WHERE a.id = ?",
            [$assessmentId]
        );
        if (!$assessment) {
            throw new RuntimeException("Assessment #{$assessmentId} not found.");
        }

        $records = self::fetchExportRecords($assessmentId);

        // Sanitize filename
        $safeTitle = preg_replace('/[^A-Za-z0-9_\-]/', '_', (string)$assessment['title']);
        $filename = sprintf("PTM_Results_%s_%s_%s.csv", $assessmentId, $safeTitle, date('Ymd_His'));

        if (!headers_sent()) {
            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Pragma: no-cache');
            header('Expires: 0');
        }

        $output = fopen('php://output', 'w');
        if ($output === false) {
            throw new RuntimeException("Failed to open output stream for CSV export.");
        }

        self::writeCSVToStream($output, $records);
        fclose($output);
        exit;
    }

    /**
     * Generate CSV content as a string (ideal for testing, logging, and background jobs).
     *
     * @param int $assessmentId
     * @return string
     */
    public static function generateResultsCSVString(int $assessmentId): string
    {
        $records = self::fetchExportRecords($assessmentId);

        $memoryStream = fopen('php://temp', 'r+');
        if ($memoryStream === false) {
            throw new RuntimeException("Unable to allocate memory stream.");
        }

        self::writeCSVToStream($memoryStream, $records);
        rewind($memoryStream);
        $csvContent = stream_get_contents($memoryStream);
        fclose($memoryStream);

        return $csvContent !== false ? $csvContent : '';
    }

    /**
     * Query all allocated stations, candidate identities, and tabulated marks.
     */
    private static function fetchExportRecords(int $assessmentId): array
    {
        return Database::fetchAll(
            "SELECT 
                 s.station_code,
                 s.ip_address,
                 ast.status AS station_status,
                 att.student_roll_no,
                 att.student_name,
                 att.student_class,
                 att.started_at,
                 att.submitted_at,
                 att.status AS attempt_status,
                 res.total_marks,
                 res.obtained_marks,
                 res.percentage,
                 res.pass_fail,
                 res.generated_at
             FROM assessment_stations ast
             JOIN lab_stations s ON ast.station_id = s.id
             LEFT JOIN assessment_attempts att 
               ON ast.assessment_id = att.assessment_id AND ast.station_id = att.station_id
             LEFT JOIN assessment_results res ON att.id = res.attempt_id
             WHERE ast.assessment_id = ?
             ORDER BY s.station_code ASC",
            [$assessmentId]
        );
    }

    /**
     * Write RFC 4180 formatted CSV with UTF-8 BOM to any valid stream resource.
     */
    private static function writeCSVToStream($stream, array $records): void
    {
        // Emit UTF-8 Byte Order Mark (BOM) for Excel Windows compatibility
        fwrite($stream, "\xEF\xBB\xBF");

        // Header row
        $headers = [
            'Workstation Code',
            'IP Address',
            'Station Status',
            'Roll Number',
            'Student Full Name',
            'Class / Grade',
            'Exam Started At',
            'Exam Submitted At',
            'Attempt Status',
            'Total Marks',
            'Obtained Marks',
            'Percentage (%)',
            'Result Status',
            'Result Generated At',
        ];
        fputcsv($stream, $headers);

        foreach ($records as $row) {
            $csvRow = [
                $row['station_code'] ?? '',
                $row['ip_address'] ?? 'N/A',
                strtoupper((string)($row['station_status'] ?? 'IDLE')),
                $row['student_roll_no'] ?? 'UNATTENDED',
                $row['student_name'] ?? '—',
                $row['student_class'] ?? '—',
                $row['started_at'] ?? '—',
                $row['submitted_at'] ?? '—',
                strtoupper((string)($row['attempt_status'] ?? 'ABSENT')),
                isset($row['total_marks']) ? number_format((float)$row['total_marks'], 2) : '0.00',
                isset($row['obtained_marks']) ? number_format((float)$row['obtained_marks'], 2) : '0.00',
                isset($row['percentage']) ? number_format((float)$row['percentage'], 2) . '%' : '0.00%',
                strtoupper((string)($row['pass_fail'] ?? 'PENDING')),
                $row['generated_at'] ?? '—',
            ];
            fputcsv($stream, $csvRow);
        }
    }
}

