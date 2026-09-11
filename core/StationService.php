<?php
declare(strict_types=1);

/**
 * PTM Assessment System — Workstation Management Service
 * Resolves workstations by LAN IP or code, allows direct "Enter Workstation Code" (without IP),
 * and provides one-click bulk device allocation for computer labs.
 */
class StationService
{
    /**
     * Normalize workstation code input (e.g., "4" -> "PC-04", "pc-5" -> "PC-05", "PC-12" -> "PC-12").
     */
    public static function normalizeStationCode(string|int $code): string
    {
        $term = trim((string)$code);
        if ($term === '') {
            return '';
        }

        // If numeric only (e.g. "4"), convert to "PC-04"
        if (ctype_digit($term)) {
            return 'PC-' . str_pad($term, 2, '0', STR_PAD_LEFT);
        }

        // If begins with pc or PC followed by digits (e.g. "pc4" or "PC-4")
        if (preg_match('/^pc[-_\s]?(\d+)$/i', $term, $m)) {
            return 'PC-' . str_pad($m[1], 2, '0', STR_PAD_LEFT);
        }

        return strtoupper($term);
    }

    /**
     * Resolve a lab workstation based on manual station code or LAN IP address.
     *
     * @param string|int $ipOrCode IP address (e.g. "192.168.1.101") or code (e.g. "PC-01" or 5).
     * @return array|null Returns the station record with campus details, or null if not found.
     */
    public static function resolveStation(string|int $ipOrCode): ?array
    {
        $term = trim((string)$ipOrCode);
        if ($term === '') {
            return null;
        }

        $normalizedCode = self::normalizeStationCode($term);

        $sql = "SELECT s.*, c.name AS campus_name, c.code AS campus_code
                FROM lab_stations s
                JOIN campuses c ON s.campus_id = c.id
                WHERE (s.station_code = ? OR s.station_code = ? OR s.ip_address = ?)
                  AND s.status = 'active'
                LIMIT 1";

        return Database::fetch($sql, [$normalizedCode, $term, $term]);
    }

    /**
     * Resolve workstation or auto-create it without IP address.
     * Enables students or instructors to enter any station code (e.g. "PC-05") instantly.
     */
    public static function resolveOrCreateStation(string|int $codeOrIp, int $campusId = 1): array
    {
        $station = self::resolveStation($codeOrIp);
        if ($station) {
            return $station;
        }

        $code = self::normalizeStationCode($codeOrIp);
        if ($code === '') {
            $code = 'PC-01';
        }

        if ($campusId <= 0) {
            $campusId = 1;
        }

        // Insert new workstation without IP address
        $id = Database::insert('lab_stations', [
            'campus_id'    => $campusId,
            'station_code' => $code,
            'ip_address'   => null,
            'status'       => 'active',
        ]);

        return self::resolveStation($code) ?: [
            'id'           => $id,
            'campus_id'    => $campusId,
            'station_code' => $code,
            'ip_address'   => null,
            'status'       => 'active',
        ];
    }

    /**
     * Ensure a campus has at least $count workstations (PC-01 through PC-N) without requiring IP addresses.
     */
    public static function ensureStationsCount(int $campusId, int $count): void
    {
        if ($campusId <= 0) {
            $campusId = 1;
        }

        for ($i = 1; $i <= $count; $i++) {
            $code = 'PC-' . str_pad((string)$i, 2, '0', STR_PAD_LEFT);
            $exists = Database::fetch(
                "SELECT id FROM lab_stations WHERE campus_id = ? AND station_code = ?",
                [$campusId, $code]
            );
            if (!$exists) {
                Database::insert('lab_stations', [
                    'campus_id'    => $campusId,
                    'station_code' => $code,
                    'ip_address'   => null,
                    'status'       => 'active',
                ]);
            }
        }
    }

    /**
     * Allocate/reserve lab workstations for an assessment.
     * Inserts allocation records into `assessment_stations` with status 'idle'.
     *
     * @param int $assessmentId Target assessment ID.
     * @param int $count Number of computers for test taking (e.g. 25).
     * @param int[] $specificStationIds Optional list of explicit station IDs to assign.
     * @return array Array of allocated station records.
     */
    public static function allocateStationsToAssessment(
        int $assessmentId, 
        int $count = 0, 
        array $specificStationIds = []
    ): array {
        if ($assessmentId <= 0) {
            throw new InvalidArgumentException("Invalid assessment ID: {$assessmentId}");
        }

        $assessment = Database::fetch(
            "SELECT id, campus_id, status FROM assessments WHERE id = ?",
            [$assessmentId]
        );
        if (!$assessment) {
            throw new RuntimeException("Assessment #{$assessmentId} does not exist.");
        }

        $campusId = (int)$assessment['campus_id'];
        $stationsToAssign = [];

        if (!empty($specificStationIds)) {
            $cleanIds = array_filter(array_map('intval', $specificStationIds), fn($id) => $id > 0);
            if (empty($cleanIds)) {
                throw new InvalidArgumentException("No valid station IDs provided.");
            }

            $inPlaceholders = implode(',', array_fill(0, count($cleanIds), '?'));
            $stationsToAssign = Database::fetchAll(
                "SELECT * FROM lab_stations 
                 WHERE id IN ($inPlaceholders) 
                   AND campus_id = ? 
                   AND status = 'active'",
                array_merge(array_values($cleanIds), [$campusId])
            );
        } elseif ($count > 0) {
            // Automatically ensure that at least $count computers exist in the lab
            self::ensureStationsCount($campusId, $count);

            // Select the first $count stations ordered by natural station code
            $stationsToAssign = Database::fetchAll(
                "SELECT * FROM lab_stations 
                 WHERE campus_id = ? AND status = 'active'
                 ORDER BY LENGTH(station_code) ASC, station_code ASC
                 LIMIT " . (int)$count,
                [$campusId]
            );
        } else {
            throw new InvalidArgumentException("Must specify either a positive computer count or specific workstation IDs.");
        }

        Database::beginTransaction();
        try {
            $assignedStationIds = [];
            $allocated = [];

            foreach ($stationsToAssign as $station) {
                $stId = (int)$station['id'];
                $assignedStationIds[] = $stId;

                Database::query(
                    "INSERT INTO assessment_stations (assessment_id, station_id, status, assigned_at)
                     VALUES (?, ?, 'idle', NOW())
                     ON DUPLICATE KEY UPDATE status = IF(status IN ('in_progress', 'submitted'), status, 'idle')",
                    [$assessmentId, $stId]
                );

                $allocated[] = [
                    'assessment_id' => $assessmentId,
                    'station_id'    => $stId,
                    'station_code'  => $station['station_code'],
                    'ip_address'    => $station['ip_address'],
                    'status'        => 'idle',
                ];
            }

            // If reducing allocation, remove idle stations not in the new set
            if (!empty($assignedStationIds)) {
                $inPlaceholders = implode(',', array_fill(0, count($assignedStationIds), '?'));
                Database::query(
                    "DELETE FROM assessment_stations 
                     WHERE assessment_id = ? 
                       AND status = 'idle' 
                       AND station_id NOT IN ($inPlaceholders)",
                    array_merge([$assessmentId], $assignedStationIds)
                );
            }

            Database::commit();
            return $allocated;
        } catch (Throwable $e) {
            Database::rollback();
            throw new RuntimeException("Failed to allocate stations: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Fetch all allocated workstations for an assessment.
     */
    public static function getAssessmentStations(int $assessmentId): array
    {
        return Database::fetchAll(
            "SELECT ast.*, s.station_code, s.ip_address, s.status AS station_hardware_status,
                    att.id AS current_attempt_id, att.student_name, att.student_roll_no, 
                    att.started_at, att.expected_end_at, att.status AS attempt_status
             FROM assessment_stations ast
             JOIN lab_stations s ON ast.station_id = s.id
             LEFT JOIN assessment_attempts att 
               ON ast.assessment_id = att.assessment_id 
              AND ast.station_id = att.station_id 
              AND att.status = 'in_progress'
             WHERE ast.assessment_id = ?
             ORDER BY LENGTH(s.station_code) ASC, s.station_code ASC",
            [$assessmentId]
        );
    }

    /**
     * Set the device allocation limit for an assessment.
     */
    public static function setAssessmentDeviceLimit(int $assessmentId, int $limit): void
    {
        if ($assessmentId <= 0) {
            throw new InvalidArgumentException("Invalid assessment ID.");
        }
        Database::update('assessments', [
            'device_limit' => max(0, $limit),
        ], 'id = ?', [$assessmentId]);
    }

    /**
     * Get the device allocation limit for an assessment.
     */
    public static function getAssessmentDeviceLimit(int $assessmentId): int
    {
        return (int)Database::fetchColumn(
            "SELECT device_limit FROM assessments WHERE id = ?",
            [$assessmentId]
        );
    }

    /**
     * Assign a PC name / workstation code to an assessment within its allocated device limit.
     * Throws exception if allocation limit is reached.
     */
    public static function assignStationToAssessment(int $assessmentId, string $code, int $campusId = 1): array
    {
        $code = trim($code);
        if ($code === '') {
            throw new InvalidArgumentException("Workstation code / PC name cannot be empty.");
        }

        $assessment = Database::fetch(
            "SELECT id, campus_id, device_limit FROM assessments WHERE id = ?",
            [$assessmentId]
        );
        if (!$assessment) {
            throw new RuntimeException("Assessment #{$assessmentId} not found.");
        }

        $limit = max(0, (int)($assessment['device_limit'] ?? 0));
        $normalized = self::normalizeStationCode($code);
        $station = self::resolveOrCreateStation($normalized, $campusId);
        $stationId = (int)$station['id'];

        // Check if already assigned to this assessment
        $alreadyAssigned = Database::fetch(
            "SELECT id FROM assessment_stations WHERE assessment_id = ? AND station_id = ?",
            [$assessmentId, $stationId]
        );
        if ($alreadyAssigned) {
            return $station;
        }

        // Check allocation limit
        if ($limit > 0) {
            $currentAssigned = (int)Database::fetchColumn(
                "SELECT COUNT(*) FROM assessment_stations WHERE assessment_id = ?",
                [$assessmentId]
            );
            if ($currentAssigned >= $limit) {
                throw new RuntimeException("Device allocation limit ({$limit} devices) reached. Cannot assign '{$normalized}'. Please increase the allocation number to add more devices.");
            }
        }

        Database::query(
            "INSERT INTO assessment_stations (assessment_id, station_id, status, assigned_at)
             VALUES (?, ?, 'idle', NOW())
             ON DUPLICATE KEY UPDATE status = IF(status IN ('in_progress', 'submitted'), status, 'idle')",
            [$assessmentId, $stationId]
        );

        return $station;
    }

    /**
     * Add a single custom workstation and allocate it to an assessment on-demand.
     */
    public static function addStationToAssessment(int $assessmentId, string $code, int $campusId = 1): array
    {
        return self::assignStationToAssessment($assessmentId, $code, $campusId);
    }

    /**
     * Unassign a workstation from an assessment, freeing up its allocation slot.
     */
    public static function unassignStation(int $assessmentId, int $stationId): bool
    {
        if ($stationId <= 0 || $assessmentId <= 0) {
            return false;
        }

        $inProgress = Database::fetch(
            "SELECT id FROM assessment_attempts WHERE assessment_id = ? AND station_id = ? AND status = 'in_progress'",
            [$assessmentId, $stationId]
        );
        if ($inProgress) {
            throw new RuntimeException("Cannot unassign workstation while a candidate is actively taking an exam on it.");
        }

        return Database::delete(
            'assessment_stations', 
            'assessment_id = ? AND station_id = ?', 
            [$assessmentId, $stationId]
        ) > 0;
    }

    /**
     * Get structured allocation slots for an assessment.
     * If limit is 3, returns 3 slots (filled or empty).
     */
    public static function getAssessmentAllocationSlots(int $assessmentId): array
    {
        $assessment = Database::fetch(
            "SELECT id, campus_id, device_limit FROM assessments WHERE id = ?",
            [$assessmentId]
        );
        $limit = max(0, (int)($assessment['device_limit'] ?? 0));

        $assignedStations = Database::fetchAll(
            "SELECT ast.*, s.station_code, s.ip_address, s.status AS station_hardware_status,
                    att.id AS current_attempt_id, att.student_name, att.student_roll_no, 
                    att.started_at, att.expected_end_at, att.status AS attempt_status
             FROM assessment_stations ast
             JOIN lab_stations s ON ast.station_id = s.id
             LEFT JOIN assessment_attempts att 
               ON ast.assessment_id = att.assessment_id 
              AND ast.station_id = att.station_id 
              AND att.status = 'in_progress'
             WHERE ast.assessment_id = ?
             ORDER BY LENGTH(s.station_code) ASC, s.station_code ASC",
            [$assessmentId]
        );

        $totalSlots = max($limit, count($assignedStations));
        $slots = [];

        for ($i = 0; $i < $totalSlots; $i++) {
            $slotNumber = $i + 1;
            if (isset($assignedStations[$i])) {
                $slots[] = [
                    'slot_number' => $slotNumber,
                    'is_assigned' => true,
                    'station'     => $assignedStations[$i],
                ];
            } else {
                $slots[] = [
                    'slot_number' => $slotNumber,
                    'is_assigned' => false,
                    'station'     => null,
                ];
            }
        }

        return [
            'device_limit'    => $limit,
            'assigned_count'  => count($assignedStations),
            'available_count' => max(0, $limit - count($assignedStations)),
            'slots'           => $slots,
        ];
    }

    /**
     * Delete or de-allocate a workstation.
     * Prevents deletion if a candidate is actively taking an exam on this workstation.
     */
    public static function deleteStation(int $stationId, int $assessmentId = 0): bool
    {
        if ($stationId <= 0) {
            return false;
        }

        // Check if an active exam attempt is running
        $inProgress = Database::fetch(
            "SELECT id FROM assessment_attempts WHERE station_id = ? AND status = 'in_progress'",
            [$stationId]
        );
        if ($inProgress) {
            throw new RuntimeException("Cannot remove workstation while a candidate is actively taking an exam.");
        }

        Database::beginTransaction();
        try {
            if ($assessmentId > 0) {
                // Remove from this assessment
                Database::query(
                    "DELETE FROM assessment_stations WHERE assessment_id = ? AND station_id = ?",
                    [$assessmentId, $stationId]
                );

                // If not assigned to any other assessments, prune from lab_stations
                $otherAlloc = (int)Database::fetchColumn(
                    "SELECT COUNT(*) FROM assessment_stations WHERE station_id = ?",
                    [$stationId]
                );
                if ($otherAlloc === 0) {
                    Database::delete('lab_stations', 'id = ?', [$stationId]);
                }
            } else {
                // Delete workstation globally from system
                Database::delete('lab_stations', 'id = ?', [$stationId]);
            }

            Database::commit();
            return true;
        } catch (Throwable $e) {
            Database::rollback();
            throw $e;
        }
    }

    /**
     * Clear idle workstations for an assessment and prune unused lab stations.
     */
    public static function clearIdleStations(int $assessmentId, int $campusId = 1): int
    {
        Database::beginTransaction();
        try {
            $deletedCount = Database::delete(
                'assessment_stations', 
                "assessment_id = ? AND status = 'idle'", 
                [$assessmentId]
            );

            // Prune unreferenced lab_stations in campus (not in assessment_stations and no in_progress attempts)
            Database::query(
                "DELETE FROM lab_stations 
                 WHERE campus_id = ? 
                   AND id NOT IN (SELECT DISTINCT station_id FROM assessment_stations WHERE station_id IS NOT NULL)
                   AND id NOT IN (SELECT DISTINCT station_id FROM assessment_attempts WHERE status = 'in_progress' AND station_id IS NOT NULL)",
                [$campusId]
            );

            Database::commit();
            return $deletedCount;
        } catch (Throwable $e) {
            Database::rollback();
            throw $e;
        }
    }
}
