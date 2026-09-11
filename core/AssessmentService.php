<?php
declare(strict_types=1);

/**
 * PTM Assessment System — Assessment Service
 * Full-fledged exam management: creation, editing, question attachment/builder, ordering, and publication.
 */
class AssessmentService
{
    /**
     * Create a new assessment with validated inputs.
     *
     * @param array $data Input data array.
     * @return int Inserted assessment ID.
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public static function createAssessment(array $data): int
    {
        $campusId = (int)($data['campus_id'] ?? (Auth::campusId() ?: 1));
        $subjectId = (int)($data['subject_id'] ?? 1);
        $createdBy = (int)($data['teacher_id'] ?? ($data['created_by'] ?? (Auth::id() ?: 1)));
        $title = trim((string)($data['title'] ?? ''));
        $assessmentType = trim((string)($data['assessment_type'] ?? 'quiz'));

        // Class
        $targetClass = trim((string)($data['class'] ?? ($data['target_class'] ?? ($data['class_grade'] ?? 'Grade 1'))));
        if (($targetClass === '__custom__' || $targetClass === '') && !empty($data['custom_class'])) {
            $targetClass = trim((string)$data['custom_class']);
        }
        if (($targetClass === '__custom__' || $targetClass === '') && !empty($data['custom_class_grade'])) {
            $targetClass = trim((string)$data['custom_class_grade']);
        }
        if ($targetClass === '' || $targetClass === '__custom__') {
            $targetClass = 'Grade 1';
        }

        // Section
        $section = trim((string)($data['section'] ?? 'Section A'));
        if (($section === '__custom__' || $section === '') && !empty($data['custom_section'])) {
            $section = trim((string)$data['custom_section']);
        }
        if ($section === '' || $section === '__custom__') {
            $section = 'Section A';
        }

        $description = trim((string)($data['description'] ?? ''));
        $durationMinutes = max(1, (int)($data['duration_minutes'] ?? 30));

        $startDate = !empty($data['start_date']) ? trim((string)$data['start_date']) : null;
        $startTime = !empty($data['start_time']) ? trim((string)$data['start_time']) : null;
        $endDate = !empty($data['end_date']) ? trim((string)$data['end_date']) : null;
        $endTime = !empty($data['end_time']) ? trim((string)$data['end_time']) : null;

        $totalMarks = (isset($data['total_marks']) && $data['total_marks'] !== '') ? max(0.0, (float)$data['total_marks']) : 100.00;
        $passingMarks = (isset($data['passing_marks']) && $data['passing_marks'] !== '') ? max(0.0, (float)$data['passing_marks']) : 40.00;
        $passingPercentage = ($totalMarks > 0) ? min(100.0, max(0.0, ($passingMarks / $totalMarks) * 100.0)) : 40.00;

        $shuffleQuestions = isset($data['shuffle_questions']) ? (int)(bool)$data['shuffle_questions'] : 1;
        $shuffleOptions = isset($data['shuffle_options']) ? (int)(bool)$data['shuffle_options'] : 1;

        $instructions = trim((string)($data['instructions'] ?? ''));
        $rulesGuidelines = trim((string)($data['rules_guidelines'] ?? ''));

        if ($title === '') {
            throw new InvalidArgumentException("Assessment title cannot be empty.");
        }
        if ($campusId <= 0) {
            $campusId = 1;
        }
        if ($subjectId <= 0) {
            $subjectId = (int)Database::fetchColumn("SELECT id FROM subjects LIMIT 1") ?: 1;
        }
        if ($createdBy <= 0) {
            $createdBy = (int)Database::fetchColumn("SELECT id FROM users LIMIT 1") ?: 1;
        }

        $assessmentId = Database::insert('assessments', [
            'campus_id'           => $campusId,
            'subject_id'          => $subjectId,
            'created_by'          => $createdBy,
            'title'               => $title,
            'assessment_type'     => $assessmentType,
            'target_class'        => $targetClass,
            'section'             => $section,
            'description'         => $description,
            'duration_minutes'    => $durationMinutes,
            'start_date'          => $startDate,
            'start_time'          => $startTime,
            'end_date'            => $endDate,
            'end_time'            => $endTime,
            'total_marks'         => $totalMarks,
            'passing_marks'       => $passingMarks,
            'shuffle_questions'   => $shuffleQuestions,
            'shuffle_options'     => $shuffleOptions,
            'instructions'        => $instructions,
            'rules_guidelines'    => $rulesGuidelines,
            'passing_percentage'  => $passingPercentage,
            'is_result_published' => 0,
            'status'              => 'draft',
        ]);

        if (class_exists('AuditLog')) {
            AuditLog::log('assessment_created', 'assessments', "Created assessment '{$title}' (ID #{$assessmentId})", $createdBy);
        }

        return $assessmentId;
    }

    /**
     * Update existing assessment details.
     */
    public static function updateAssessment(int $id, array $data): bool
    {
        $existing = self::getAssessment($id);
        if (!$existing) {
            return false;
        }

        $title = trim((string)($data['title'] ?? $existing['title']));
        $assessmentType = trim((string)($data['assessment_type'] ?? ($existing['assessment_type'] ?? 'quiz')));

        $targetClass = trim((string)($data['class'] ?? ($data['target_class'] ?? ($data['class_grade'] ?? $existing['target_class']))));
        if (($targetClass === '__custom__' || $targetClass === '') && !empty($data['custom_class'])) {
            $targetClass = trim((string)$data['custom_class']);
        }
        if (($targetClass === '__custom__' || $targetClass === '') && !empty($data['custom_class_grade'])) {
            $targetClass = trim((string)$data['custom_class_grade']);
        }
        if ($targetClass === '__custom__') {
            $targetClass = $existing['target_class'];
        }

        $section = trim((string)($data['section'] ?? ($existing['section'] ?? 'Section A')));
        if (($section === '__custom__' || $section === '') && !empty($data['custom_section'])) {
            $section = trim((string)$data['custom_section']);
        }

        $description = trim((string)($data['description'] ?? ($existing['description'] ?? '')));
        $durationMinutes = max(1, (int)($data['duration_minutes'] ?? $existing['duration_minutes']));

        $startDate = array_key_exists('start_date', $data) ? (!empty($data['start_date']) ? trim((string)$data['start_date']) : null) : ($existing['start_date'] ?? null);
        $startTime = array_key_exists('start_time', $data) ? (!empty($data['start_time']) ? trim((string)$data['start_time']) : null) : ($existing['start_time'] ?? null);
        $endDate = array_key_exists('end_date', $data) ? (!empty($data['end_date']) ? trim((string)$data['end_date']) : null) : ($existing['end_date'] ?? null);
        $endTime = array_key_exists('end_time', $data) ? (!empty($data['end_time']) ? trim((string)$data['end_time']) : null) : ($existing['end_time'] ?? null);

        $totalMarks = (isset($data['total_marks']) && $data['total_marks'] !== '') ? max(0.0, (float)$data['total_marks']) : (float)$existing['total_marks'];
        $passingMarks = (isset($data['passing_marks']) && $data['passing_marks'] !== '') ? max(0.0, (float)$data['passing_marks']) : (float)($existing['passing_marks'] ?? 40.00);
        $passingPercentage = ($totalMarks > 0) ? min(100.0, max(0.0, ($passingMarks / $totalMarks) * 100.0)) : (float)$existing['passing_percentage'];

        $shuffleQuestions = isset($data['shuffle_questions']) ? (int)(bool)$data['shuffle_questions'] : (int)($existing['shuffle_questions'] ?? 1);
        $shuffleOptions = isset($data['shuffle_options']) ? (int)(bool)$data['shuffle_options'] : (int)($existing['shuffle_options'] ?? 1);

        $instructions = array_key_exists('instructions', $data) ? trim((string)$data['instructions']) : ($existing['instructions'] ?? '');
        $rulesGuidelines = array_key_exists('rules_guidelines', $data) ? trim((string)$data['rules_guidelines']) : ($existing['rules_guidelines'] ?? '');

        $subjectId = (int)($data['subject_id'] ?? $existing['subject_id']);
        if ($subjectId <= 0) {
            $subjectId = 1;
        }

        $campusId = (int)($data['campus_id'] ?? ($existing['campus_id'] ?? 0));
        if ($campusId <= 0) {
            $campusId = (int)(class_exists('Auth') ? (Auth::campusId() ?: 1) : 1);
        }

        $createdBy = (int)($data['teacher_id'] ?? ($data['created_by'] ?? ($existing['created_by'] ?? 0)));
        if ($createdBy <= 0) {
            $createdBy = (int)(class_exists('Auth') ? (Auth::id() ?: 1) : 1);
        }

        Database::update('assessments', [
            'title'               => $title,
            'assessment_type'     => $assessmentType,
            'target_class'        => $targetClass,
            'section'             => $section,
            'description'         => $description,
            'duration_minutes'    => $durationMinutes,
            'start_date'          => $startDate,
            'start_time'          => $startTime,
            'end_date'            => $endDate,
            'end_time'            => $endTime,
            'total_marks'         => $totalMarks,
            'passing_marks'       => $passingMarks,
            'shuffle_questions'   => $shuffleQuestions,
            'shuffle_options'     => $shuffleOptions,
            'instructions'        => $instructions,
            'rules_guidelines'    => $rulesGuidelines,
            'passing_percentage'  => $passingPercentage,
            'subject_id'          => $subjectId,
            'campus_id'           => $campusId,
            'created_by'          => $createdBy,
        ], 'id = ?', [$id]);

        if (class_exists('AuditLog')) {
            AuditLog::log('assessment_updated', 'assessments', "Updated assessment ID #{$id}", class_exists('Auth') ? (Auth::id() ?: 1) : 1);
        }

        return true;
    }

    /**
     * Delete an assessment.
     * Enforces assessments.delete permission or assessment creator ownership.
     * Cascades cleanups across attempts, security events, answers, results, and stations.
     */
    public static function deleteAssessment(int $id): bool
    {
        $existing = self::getAssessment($id);
        if (!$existing) {
            return false;
        }

        $canDelete = false;
        if (class_exists('Auth')) {
            $currentUserId = (int)Auth::id();
            $authorId = (int)($existing['created_by'] ?? 0);
            $canDelete = Auth::isSuperAdmin() 
                      || Auth::hasPermission('assessments.delete') 
                      || ($currentUserId > 0 && $currentUserId === $authorId);
        } else {
            $canDelete = true;
        }

        if (!$canDelete) {
            throw new RuntimeException("Permission denied: You do not have permission to delete assessments.");
        }

        Database::beginTransaction();
        try {
            // 1. Purge all attempt-related telemetry, answers, results, and attempts
            $attempts = Database::fetchAll("SELECT id FROM assessment_attempts WHERE assessment_id = ?", [$id]);
            if (!empty($attempts)) {
                $attemptIds = array_column($attempts, 'id');
                $placeholders = implode(',', array_fill(0, count($attemptIds), '?'));
                Database::query("DELETE FROM security_events WHERE attempt_id IN ($placeholders)", $attemptIds);
                Database::query("DELETE FROM assessment_answers WHERE attempt_id IN ($placeholders)", $attemptIds);
                Database::query("DELETE FROM assessment_results WHERE attempt_id IN ($placeholders)", $attemptIds);
                Database::query("DELETE FROM assessment_attempts WHERE id IN ($placeholders)", $attemptIds);
            }

            // 2. Remove questions link and station allocations
            Database::delete('assessment_questions', 'assessment_id = ?', [$id]);
            Database::delete('assessment_stations', 'assessment_id = ?', [$id]);

            // 3. Delete the assessment record
            Database::delete('assessments', 'id = ?', [$id]);

            Database::commit();

            if (class_exists('AuditLog')) {
                AuditLog::log('assessment_deleted', 'assessments', "Deleted assessment '{$existing['title']}' (ID #{$id})", class_exists('Auth') ? (Auth::id() ?: 1) : 1);
            }

            return true;
        } catch (Throwable $e) {
            Database::rollback();
            throw new RuntimeException("Failed to delete assessment: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * List assessments with filtering for index views.
     */
    public static function getAssessments(array $filters = []): array
    {
        $sql = "SELECT a.*, 
                       c.name AS campus_name, c.code AS campus_code,
                       s.name AS subject_name, s.code AS subject_code,
                       u.username AS teacher_name, u.username AS creator_username,
                       (SELECT COUNT(*) FROM assessment_questions aq WHERE aq.assessment_id = a.id) AS questions_count,
                       (SELECT COUNT(*) FROM assessment_stations ast WHERE ast.assessment_id = a.id) AS allocated_stations,
                       (SELECT COUNT(*) FROM assessment_attempts att WHERE att.assessment_id = a.id) AS attempts_count
                FROM assessments a
                LEFT JOIN campuses c ON a.campus_id = c.id
                LEFT JOIN subjects s ON a.subject_id = s.id
                LEFT JOIN users u ON a.created_by = u.id
                WHERE 1=1";

        $params = [];

        if (!empty($filters['status'])) {
            $sql .= " AND a.status = ?";
            $params[] = $filters['status'];
        } else {
            $sql .= " AND a.status != 'archived'";
        }

        if (!empty($filters['subject_id'])) {
            $sql .= " AND a.subject_id = ?";
            $params[] = (int)$filters['subject_id'];
        }

        if (!empty($filters['campus_id'])) {
            $sql .= " AND a.campus_id = ?";
            $params[] = (int)$filters['campus_id'];
        }

        if (!empty($filters['search'])) {
            $searchTerm = '%' . trim((string)$filters['search']) . '%';
            $sql .= " AND (a.title LIKE ? OR a.target_class LIKE ? OR s.name LIKE ?)";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        $sql .= " ORDER BY a.id DESC";

        return Database::fetchAll($sql, $params);
    }

    /**
     * Get single assessment with campus, subject, and author metadata.
     */
    public static function getAssessment(int $id): ?array
    {
        return Database::fetch(
            "SELECT a.*, 
                    c.name AS campus_name, c.code AS campus_code,
                    s.name AS subject_name, s.code AS subject_code,
                    u.username AS creator_username, u.username AS teacher_name
             FROM assessments a
             LEFT JOIN campuses c ON a.campus_id = c.id
             LEFT JOIN subjects s ON a.subject_id = s.id
             LEFT JOIN users u ON a.created_by = u.id
             WHERE a.id = ?",
            [$id]
        );
    }

    /**
     * Fetch ordered questions and their options for an assessment.
     */
    public static function getAssessmentQuestions(int $assessmentId): array
    {
        $questions = Database::fetchAll(
            "SELECT q.*, aq.id AS link_id, aq.question_order, aq.question_id
             FROM assessment_questions aq
             JOIN questions q ON aq.question_id = q.id
             WHERE aq.assessment_id = ?
             ORDER BY aq.question_order ASC",
            [$assessmentId]
        );

        foreach ($questions as &$q) {
            $q['options'] = Database::fetchAll(
                "SELECT id, option_text, is_correct 
                 FROM question_options 
                 WHERE question_id = ? 
                 ORDER BY id ASC",
                [(int)$q['id']]
            );
        }

        return $questions;
    }

    /**
     * Attach a list of questions to an assessment, preserving order and recomputing total marks.
     */
    public static function attachQuestions(int $assessmentId, array $questionIds): bool
    {
        if ($assessmentId <= 0) {
            throw new InvalidArgumentException("Invalid assessment ID: {$assessmentId}");
        }

        $cleanIds = array_values(array_unique(array_filter(
            array_map('intval', $questionIds),
            fn($id) => $id > 0
        )));

        Database::beginTransaction();
        try {
            Database::delete('assessment_questions', 'assessment_id = ?', [$assessmentId]);

            $order = 1;
            foreach ($cleanIds as $qId) {
                Database::insert('assessment_questions', [
                    'assessment_id'  => $assessmentId,
                    'question_id'    => $qId,
                    'question_order' => $order++,
                ]);
            }

            self::recalculateTotalMarks($assessmentId);

            Database::commit();
            return true;
        } catch (Throwable $e) {
            Database::rollback();
            throw new RuntimeException("Failed to attach questions: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Append questions from Question Bank to an assessment (used by Assessment Builder).
     */
    public static function addQuestions(int $assessmentId, array $questionIds): int
    {
        if ($assessmentId <= 0 || empty($questionIds)) {
            return 0;
        }

        $existing = Database::fetchAll(
            "SELECT question_id FROM assessment_questions WHERE assessment_id = ?",
            [$assessmentId]
        );
        $existingIds = array_column($existing, 'question_id');

        // Determine current max order
        $maxOrder = (int)Database::fetchColumn(
            "SELECT MAX(question_order) FROM assessment_questions WHERE assessment_id = ?",
            [$assessmentId]
        );

        $addedCount = 0;
        Database::beginTransaction();
        try {
            foreach ($questionIds as $qid) {
                $qidInt = (int)$qid;
                if ($qidInt > 0 && !in_array($qidInt, $existingIds, true)) {
                    $maxOrder++;
                    Database::insert('assessment_questions', [
                        'assessment_id'  => $assessmentId,
                        'question_id'    => $qidInt,
                        'question_order' => $maxOrder,
                    ]);
                    $existingIds[] = $qidInt;
                    $addedCount++;
                }
            }

            if ($addedCount > 0) {
                self::recalculateTotalMarks($assessmentId);
            }

            Database::commit();
            return $addedCount;
        } catch (Throwable $e) {
            Database::rollback();
            throw $e;
        }
    }

    /**
     * Add random questions from Question Bank.
     */
    public static function addRandomQuestions(
        int $assessmentId, 
        int $subjectId, 
        ?int $categoryId = null, 
        array $difficulties = [], 
        int $count = 10
    ): int {
        $existing = Database::fetchAll(
            "SELECT question_id FROM assessment_questions WHERE assessment_id = ?",
            [$assessmentId]
        );
        $excludeIds = array_column($existing, 'question_id');

        $randomIds = QuestionService::getRandomQuestions($subjectId, $count, $excludeIds);

        return self::addQuestions($assessmentId, $randomIds);
    }

    /**
     * Remove a single question from an assessment.
     */
    public static function removeQuestion(int $assessmentId, int $questionId): bool
    {
        Database::beginTransaction();
        try {
            Database::delete('assessment_questions', 'assessment_id = ? AND question_id = ?', [$assessmentId, $questionId]);

            // Renumber remaining questions sequentially
            $remaining = Database::fetchAll(
                "SELECT id FROM assessment_questions WHERE assessment_id = ? ORDER BY question_order ASC",
                [$assessmentId]
            );
            $order = 1;
            foreach ($remaining as $rem) {
                Database::update('assessment_questions', ['question_order' => $order++], 'id = ?', [(int)$rem['id']]);
            }

            self::recalculateTotalMarks($assessmentId);

            Database::commit();
            return true;
        } catch (Throwable $e) {
            Database::rollback();
            throw $e;
        }
    }

    /**
     * Reorder question position up or down.
     */
    public static function reorderQuestion(int $assessmentId, int $questionId, string $direction): bool
    {
        $questions = Database::fetchAll(
            "SELECT id, question_id, question_order 
             FROM assessment_questions 
             WHERE assessment_id = ? 
             ORDER BY question_order ASC",
            [$assessmentId]
        );

        $currentIndex = -1;
        foreach ($questions as $idx => $q) {
            if ((int)$q['question_id'] === $questionId) {
                $currentIndex = $idx;
                break;
            }
        }

        if ($currentIndex === -1) {
            return false;
        }

        $swapIndex = ($direction === 'up') ? $currentIndex - 1 : $currentIndex + 1;
        if ($swapIndex < 0 || $swapIndex >= count($questions)) {
            return false; // Already at boundary
        }

        // Swap question_order values
        $curItem = $questions[$currentIndex];
        $swapItem = $questions[$swapIndex];

        Database::beginTransaction();
        try {
            Database::update('assessment_questions', ['question_order' => (int)$swapItem['question_order']], 'id = ?', [(int)$curItem['id']]);
            Database::update('assessment_questions', ['question_order' => (int)$curItem['question_order']], 'id = ?', [(int)$swapItem['id']]);

            Database::commit();
            return true;
        } catch (Throwable $e) {
            Database::rollback();
            throw $e;
        }
    }

    /**
     * Get the single currently active/launched assessment for a campus.
     */
    public static function getActiveLaunchedAssessment(?int $campusId = null): ?array
    {
        $cid = $campusId ?: (class_exists('Auth') ? (Auth::campusId() ?: 1) : 1);
        return Database::fetch(
            "SELECT a.*, 
                    c.name AS campus_name, c.code AS campus_code,
                    s.name AS subject_name, s.code AS subject_code,
                    u.username AS creator_username,
                    (SELECT COUNT(*) FROM assessment_questions aq WHERE aq.assessment_id = a.id) AS questions_count
             FROM assessments a
             JOIN campuses c ON a.campus_id = c.id
             JOIN subjects s ON a.subject_id = s.id
             JOIN users u ON a.created_by = u.id
             WHERE a.status = 'published' AND a.campus_id = ?
             ORDER BY a.updated_at DESC, a.id DESC
             LIMIT 1",
            [$cid]
        );
    }

    /**
     * Launch an assessment to the lab as the single active exam session.
     * All other assessments in the campus are automatically stopped/unlaunched.
     */
    public static function launchAssessment(int $assessmentId): array
    {
        $assessment = self::getAssessment($assessmentId);
        if (!$assessment) {
            return ['success' => false, 'message' => 'Assessment not found.'];
        }

        $questionCount = (int)Database::fetchColumn(
            "SELECT COUNT(*) FROM assessment_questions WHERE assessment_id = ?",
            [$assessmentId]
        );
        if ($questionCount === 0) {
            return [
                'success' => false, 
                'message' => 'Cannot launch: This assessment has 0 questions attached. Please add questions in the Question Builder first.'
            ];
        }

        $campusId = (int)$assessment['campus_id'];

        Database::beginTransaction();
        try {
            // 1. Unlaunch any currently active assessments so ONLY 1 is live!
            Database::query(
                "UPDATE assessments SET status = 'draft' WHERE campus_id = ? AND status = 'published'",
                [$campusId]
            );

            // 2. Set this assessment to published
            Database::update('assessments', [
                'status' => 'published',
            ], 'id = ?', [$assessmentId]);

            // Workstations are allocated on-demand by teacher in Device Allocation or dynamically during student intake
            Database::commit();

            if (class_exists('AuditLog')) {
                AuditLog::log('assessment_launched', 'assessments', "Launched exam session for '{$assessment['title']}' (ID #{$assessmentId})", class_exists('Auth') ? (Auth::id() ?: 1) : 1);
            }

            return [
                'success' => true,
                'message' => "Assessment '{$assessment['title']}' is now LIVE in the lab! All student workstations will load this exam."
            ];
        } catch (Throwable $e) {
            Database::rollback();
            return ['success' => false, 'message' => 'Launch failed: ' . $e->getMessage()];
        }
    }

    /**
     * Stop the currently running exam session, returning all student terminals to 'Terminal Ready'.
     */
    public static function stopAssessment(int $assessmentId): array
    {
        $assessment = self::getAssessment($assessmentId);
        if (!$assessment) {
            return ['success' => false, 'message' => 'Assessment not found.'];
        }

        Database::update('assessments', [
            'status' => 'draft',
        ], 'id = ?', [$assessmentId]);

        if (class_exists('AuditLog')) {
            AuditLog::log('assessment_stopped', 'assessments', "Stopped exam session for '{$assessment['title']}' (ID #{$assessmentId})", class_exists('Auth') ? (Auth::id() ?: 1) : 1);
        }

        return [
            'success' => true,
            'message' => "Exam session stopped for '{$assessment['title']}'. Lab workstations are now in 'Terminal Ready' waiting state."
        ];
    }

    /**
     * Toggle assessment status between draft and published.
     */
    public static function togglePublish(int $assessmentId): array
    {
        $assessment = self::getAssessment($assessmentId);
        if (!$assessment) {
            return ['success' => false, 'message' => 'Assessment not found.'];
        }

        if ($assessment['status'] === 'published') {
            return self::stopAssessment($assessmentId);
        } else {
            return self::launchAssessment($assessmentId);
        }
    }

    /**
     * Toggle the `is_result_published` flag for candidate visibility.
     */
    public static function toggleResultPublish(int $assessmentId, bool $status): bool
    {
        if ($assessmentId <= 0) {
            throw new InvalidArgumentException("Invalid assessment ID: {$assessmentId}");
        }

        $val = $status ? 1 : 0;
        $updated = Database::update('assessments', [
            'is_result_published' => $val,
        ], 'id = ?', [$assessmentId]);

        return $updated >= 0;
    }

    /**
     * Update lifecycle status of an assessment ('draft', 'published', 'archived').
     */
    public static function setStatus(int $assessmentId, string $status): bool
    {
        if (!in_array($status, ['draft', 'published', 'archived'], true)) {
            throw new InvalidArgumentException("Invalid status: {$status}");
        }

        if ($status === 'published') {
            $questionCount = (int)Database::fetchColumn(
                "SELECT COUNT(*) FROM assessment_questions WHERE assessment_id = ?",
                [$assessmentId]
            );
            if ($questionCount === 0) {
                throw new RuntimeException("Cannot publish an assessment with zero questions. Please attach questions first.");
            }
        }

        $updated = Database::update('assessments', [
            'status' => $status,
        ], 'id = ?', [$assessmentId]);

        return $updated >= 0;
    }

    /**
     * Helper to recalculate and sync `assessments.total_marks`.
     */
    private static function recalculateTotalMarks(int $assessmentId): void
    {
        $sum = (float)Database::fetchColumn(
            "SELECT COALESCE(SUM(q.marks), 0.00)
             FROM assessment_questions aq
             JOIN questions q ON aq.question_id = q.id
             WHERE aq.assessment_id = ?",
            [$assessmentId]
        );

        Database::update('assessments', ['total_marks' => $sum], 'id = ?', [$assessmentId]);
    }

    /**
     * Standard school grades (Grade 1 to 12) with sections A to E.
     * Returns an associative array of Grade Group => array of Grade-Section names.
     *
     * @return array<string, array<string>>
     */
    public static function getStandardGradeSections(): array
    {
        $grades = [
            'Kindergarten / Early Years' => [
                'KG - Section A',
                'KG - Section B',
                'KG - Section C',
            ]
        ];

        for ($g = 1; $g <= 12; $g++) {
            $groupName = "Grade $g";
            $sections = [
                "Grade $g (All Sections)",
                "Grade $g - Section A",
                "Grade $g - Section B",
                "Grade $g - Section C",
                "Grade $g - Section D",
                "Grade $g - Section E",
            ];
            $grades[$groupName] = $sections;
        }

        return $grades;
    }

    /**
     * Flat list of all standard grade/section values.
     *
     * @return array<string>
     */
    public static function getAllGradeSectionValues(): array
    {
        $flat = [];
        foreach (self::getStandardGradeSections() as $sections) {
            foreach ($sections as $s) {
                $flat[] = $s;
            }
        }
        return $flat;
    }

    /**
     * Standard list of classes / grades.
     *
     * @return array<string, string>
     */
    public static function getStandardClasses(): array
    {
        return [
            'KG'       => 'Kindergarten / KG',
            'Grade 1'  => 'Grade 1',
            'Grade 2'  => 'Grade 2',
            'Grade 3'  => 'Grade 3',
            'Grade 4'  => 'Grade 4',
            'Grade 5'  => 'Grade 5',
            'Grade 6'  => 'Grade 6',
            'Grade 7'  => 'Grade 7',
            'Grade 8'  => 'Grade 8',
            'Grade 9'  => 'Grade 9',
            'Grade 10' => 'Grade 10',
            'Grade 11' => 'Grade 11',
            'Grade 12' => 'Grade 12',
        ];
    }

    /**
     * Standard list of class sections.
     *
     * @return array<string>
     */
    public static function getStandardSectionsList(): array
    {
        return [
            'Section A',
            'Section B',
            'Section C',
            'Section D',
            'Section E',
            'All Sections',
        ];
    }

    /**
     * Get instructors/teachers and administrative staff for assignment.
     *
     * @return array
     */
    public static function getTeachers(): array
    {
        return Database::fetchAll(
            "SELECT u.id, u.username, u.role, u.campus_id, c.name AS campus_name
             FROM users u
             LEFT JOIN campuses c ON u.campus_id = c.id
             ORDER BY u.username ASC"
        );
    }

    /**
     * Get available assessment types.
     *
     * @return array<string, string>
     */
    public static function getAssessmentTypes(): array
    {
        return [
            'quiz'       => 'Interactive Quiz',
            'exam'       => 'Formal Examination',
            'midterm'    => 'Midterm Assessment',
            'final'      => 'Final Examination',
            'class_test' => 'Class Test',
            'practice'   => 'Practice / Mock Test',
            'diagnostic' => 'Diagnostic Assessment',
        ];
    }
}
