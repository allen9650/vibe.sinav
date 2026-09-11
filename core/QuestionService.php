<?php
declare(strict_types=1);

/**
 * PTM Assessment System — Question Service
 * Handles Question Bank CRUD, filtering, and options persistence for Single Choice, Multiple Choice, True/False, and Fill in Blank.
 */
class QuestionService
{
    /**
     * Supported question types
     */
    public const TYPES = [
        'single_choice'   => 'Single Choice (Radio)',
        'multiple_choice' => 'Multiple Choice (Checkboxes)',
        'true_false'      => 'True / False',
        'fill_blank'      => 'Fill in the Blank',
        'short_answer'    => 'Short Answer / Written Response',
    ];

    /**
     * List questions with multi-criteria filtering
     */
    public static function getQuestions(array $filters = []): array
    {
        $sql = "SELECT q.*, 
                       s.name AS subject_name, s.code AS subject_code,
                       u.username AS creator_name,
                       (SELECT COUNT(*) FROM question_options qo WHERE qo.question_id = q.id) AS options_count
                FROM questions q
                LEFT JOIN subjects s ON q.subject_id = s.id
                LEFT JOIN users u ON q.created_by = u.id
                WHERE 1=1";

        $params = [];

        // Scoping by Subject
        if (!empty($filters['subject_id'])) {
            $sql .= " AND q.subject_id = ?";
            $params[] = (int)$filters['subject_id'];
        }

        // Scoping by Campus (via subject)
        if (!empty($filters['campus_id'])) {
            $sql .= " AND s.campus_id = ?";
            $params[] = (int)$filters['campus_id'];
        }

        // Question type filter
        if (!empty($filters['question_type'])) {
            $sql .= " AND q.question_type = ?";
            $params[] = $filters['question_type'];
        }

        // Difficulty filter
        if (!empty($filters['difficulty'])) {
            $sql .= " AND q.difficulty = ?";
            $params[] = $filters['difficulty'];
        }

        // Text search
        if (!empty($filters['search'])) {
            $sql .= " AND q.question_text LIKE ?";
            $params[] = '%' . trim((string)$filters['search']) . '%';
        }

        // Exclude specific question IDs
        if (!empty($filters['exclude_ids']) && is_array($filters['exclude_ids'])) {
            $excludeIds = array_filter(array_map('intval', $filters['exclude_ids']), fn($id) => $id > 0);
            if (!empty($excludeIds)) {
                $placeholders = implode(',', array_fill(0, count($excludeIds), '?'));
                $sql .= " AND q.id NOT IN ($placeholders)";
                $params = array_merge($params, array_values($excludeIds));
            }
        }

        $sql .= " ORDER BY q.id DESC";

        if (!empty($filters['limit'])) {
            $limit = max(1, (int)$filters['limit']);
            $offset = max(0, (int)($filters['offset'] ?? 0));
            $sql .= " LIMIT $offset, $limit";
        }

        $questions = Database::fetchAll($sql, $params);

        // Fetch options for each question
        foreach ($questions as &$q) {
            $q['options'] = Database::fetchAll(
                "SELECT id, option_text, is_correct FROM question_options WHERE question_id = ? ORDER BY id ASC",
                [(int)$q['id']]
            );
        }

        return $questions;
    }

    /**
     * Get single question with its options
     */
    public static function getQuestion(int $id): ?array
    {
        $question = Database::fetch(
            "SELECT q.*, 
                    s.name AS subject_name, s.code AS subject_code,
                    u.username AS creator_name
             FROM questions q
             LEFT JOIN subjects s ON q.subject_id = s.id
             LEFT JOIN users u ON q.created_by = u.id
             WHERE q.id = ?",
            [$id]
        );

        if (!$question) {
            return null;
        }

        $question['options'] = Database::fetchAll(
            "SELECT id, option_text, is_correct FROM question_options WHERE question_id = ? ORDER BY id ASC",
            [$id]
        );

        return $question;
    }

    /**
     * Create a new question with options in the Question Bank
     */
    public static function createQuestion(array $data, array $options = [], ?array $imageFile = null): int
    {
        $subjectId = (int)($data['subject_id'] ?? 0);
        $createdBy = (int)($data['created_by'] ?? (Auth::id() ?: 1));
        $text = trim((string)($data['question_text'] ?? ''));
        $type = trim((string)($data['question_type'] ?? 'single_choice'));
        $marks = max(0.25, (float)($data['marks'] ?? 1.00));
        $difficulty = in_array(($data['difficulty'] ?? 'medium'), ['easy', 'medium', 'hard'], true) ? $data['difficulty'] : 'medium';

        if ($subjectId <= 0) {
            throw new InvalidArgumentException("Valid subject must be selected.");
        }
        if ($text === '') {
            throw new InvalidArgumentException("Question text cannot be empty.");
        }
        if (!array_key_exists($type, self::TYPES)) {
            throw new InvalidArgumentException("Invalid question type: {$type}");
        }

        Database::beginTransaction();
        try {
            $questionId = Database::insert('questions', [
                'subject_id'    => $subjectId,
                'created_by'    => $createdBy,
                'question_text' => $text,
                'question_type' => $type,
                'difficulty'    => $difficulty,
                'marks'         => $marks,
            ]);

            self::saveQuestionOptions($questionId, $type, $options);

            AuditLog::log('question_created', 'questions', "Created question ID #{$questionId} ({$type}, {$difficulty})", $createdBy);

            Database::commit();
            return $questionId;
        } catch (Throwable $e) {
            Database::rollback();
            throw $e;
        }
    }

    /**
     * Create multiple questions in bulk in a single atomic transaction.
     *
     * @param array $common Common metadata (subject_id, created_by)
     * @param array $questions List of question items
     * @return array List of created question IDs
     */
    public static function createQuestionsBulk(array $common, array $questions): array
    {
        $subjectId = (int)($common['subject_id'] ?? 0);
        $createdBy = (int)($common['created_by'] ?? (Auth::id() ?: 1));

        if ($subjectId <= 0) {
            throw new InvalidArgumentException("A valid subject must be selected.");
        }

        Database::beginTransaction();
        try {
            $insertedIds = [];
            foreach ($questions as $q) {
                $text = trim((string)($q['question_text'] ?? ''));
                if ($text === '') {
                    continue; // Skip blank question items
                }
                $type = trim((string)($q['question_type'] ?? 'single_choice'));
                if (!array_key_exists($type, self::TYPES)) {
                    $type = 'single_choice';
                }
                $marks = max(0.25, (float)($q['marks'] ?? 1.00));
                $difficulty = in_array(($q['difficulty'] ?? 'medium'), ['easy', 'medium', 'hard'], true) ? $q['difficulty'] : 'medium';

                $qId = Database::insert('questions', [
                    'subject_id'    => $subjectId,
                    'created_by'    => $createdBy,
                    'question_text' => $text,
                    'question_type' => $type,
                    'difficulty'    => $difficulty,
                    'marks'         => $marks,
                ]);

                $options = $q['options'] ?? [];
                self::saveQuestionOptions($qId, $type, $options);
                $insertedIds[] = $qId;
            }

            if (empty($insertedIds)) {
                throw new InvalidArgumentException("Please provide question text for at least one question.");
            }

            AuditLog::log('questions_bulk_created', 'questions', "Bulk created " . count($insertedIds) . " questions for subject #{$subjectId}", $createdBy);

            Database::commit();
            return $insertedIds;
        } catch (Throwable $e) {
            Database::rollback();
            throw $e;
        }
    }

    /**
     * Update an existing question and its options
     */
    public static function updateQuestion(int $id, array $data, array $options = []): bool
    {
        $existing = self::getQuestion($id);
        if (!$existing) {
            return false;
        }

        $subjectId = (int)($data['subject_id'] ?? $existing['subject_id']);
        $text = trim((string)($data['question_text'] ?? $existing['question_text']));
        $type = trim((string)($data['question_type'] ?? $existing['question_type']));
        $marks = max(0.25, (float)($data['marks'] ?? $existing['marks']));
        $difficulty = in_array(($data['difficulty'] ?? $existing['difficulty'] ?? 'medium'), ['easy', 'medium', 'hard'], true) ? ($data['difficulty'] ?? $existing['difficulty'] ?? 'medium') : 'medium';

        Database::beginTransaction();
        try {
            Database::update('questions', [
                'subject_id'    => $subjectId,
                'question_text' => $text,
                'question_type' => $type,
                'difficulty'    => $difficulty,
                'marks'         => $marks,
            ], 'id = ?', [$id]);

            if (!empty($options)) {
                Database::delete('question_options', 'question_id = ?', [$id]);
                self::saveQuestionOptions($id, $type, $options);
            }

            AuditLog::log('question_updated', 'questions', "Updated question ID #{$id}", Auth::id() ?: 1);

            Database::commit();
            return true;
        } catch (Throwable $e) {
            Database::rollback();
            throw $e;
        }
    }

    /**
     * Delete a question from the question bank.
     * Enforces questions.delete permission.
     */
    public static function deleteQuestion(int $id): bool
    {
        if (class_exists('Auth') && !Auth::hasPermission('questions.delete')) {
            throw new RuntimeException("Permission denied: You do not have permission to remove questions.");
        }

        $existing = self::getQuestion($id);
        if (!$existing) {
            return false;
        }

        Database::beginTransaction();
        try {
            Database::delete('question_options', 'question_id = ?', [$id]);
            Database::delete('assessment_questions', 'question_id = ?', [$id]);
            Database::delete('questions', 'id = ?', [$id]);
            Database::commit();

            if (class_exists('AuditLog')) {
                AuditLog::log('question_deleted', 'questions', "Deleted question ID #{$id}", class_exists('Auth') ? (Auth::id() ?: 1) : 1);
            }

            return true;
        } catch (Throwable $e) {
            Database::rollback();
            throw new RuntimeException("Failed to delete question: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Fetch random questions from the pool for a given subject
     */
    public static function getRandomQuestions(int $subjectId, int $count, array $excludeIds = []): array
    {
        $params = [$subjectId];
        $sql = "SELECT id FROM questions WHERE subject_id = ?";

        if (!empty($excludeIds)) {
            $cleanExclude = array_filter(array_map('intval', $excludeIds), fn($id) => $id > 0);
            if (!empty($cleanExclude)) {
                $placeholders = implode(',', array_fill(0, count($cleanExclude), '?'));
                $sql .= " AND id NOT IN ($placeholders)";
                $params = array_merge($params, array_values($cleanExclude));
            }
        }

        $sql .= " ORDER BY RAND() LIMIT " . max(1, $count);
        $rows = Database::fetchAll($sql, $params);

        return array_column($rows, 'id');
    }

    /**
     * Get active subjects list with questions count
     */
    public static function getSubjects(int $campusId = 0): array
    {
        $sql = "SELECT s.*, 
                       (SELECT COUNT(*) FROM questions q WHERE q.subject_id = s.id) AS questions_count
                FROM subjects s";
        $params = [];
        if ($campusId > 0) {
            $sql .= " WHERE s.campus_id = ?";
            $params[] = $campusId;
        }
        $sql .= " ORDER BY s.name ASC";

        return Database::fetchAll($sql, $params);
    }

    /**
     * Get campuses list
     */
    public static function getCampuses(): array
    {
        return Database::fetchAll("SELECT * FROM campuses WHERE status = 'active' ORDER BY name ASC");
    }

    /**
     * Compatibility stub for legacy views (categories removed in current schema)
     */
    public static function getCategoriesBySubject(int $subjectId): array
    {
        return [];
    }

    /**
     * Helper to persist question options based on type
     */
    private static function saveQuestionOptions(int $questionId, string $type, array $options): void
    {
        switch ($type) {
            case 'single_choice':
            case 'multiple_choice':
                foreach ($options as $opt) {
                    $optText = trim((string)($opt['text'] ?? ($opt['option_text'] ?? '')));
                    if ($optText !== '') {
                        Database::insert('question_options', [
                            'question_id' => $questionId,
                            'option_text' => $optText,
                            'is_correct'  => !empty($opt['is_correct']) ? 1 : 0,
                        ]);
                    }
                }
                break;

            case 'true_false':
                $correctVal = strtolower((string)($options['correct_answer'] ?? ($options['correct'] ?? 'true')));
                Database::insert('question_options', [
                    'question_id' => $questionId,
                    'option_text' => 'True',
                    'is_correct'  => ($correctVal === 'true') ? 1 : 0,
                ]);
                Database::insert('question_options', [
                    'question_id' => $questionId,
                    'option_text' => 'False',
                    'is_correct'  => ($correctVal === 'false') ? 1 : 0,
                ]);
                break;

            case 'fill_blank':
                $blanks = $options['blanks'] ?? ($options['accepted_answers'] ?? ($options ?? []));
                if (is_string($blanks)) {
                    $blanks = explode(',', $blanks);
                }
                foreach ((array)$blanks as $blank) {
                    $val = is_array($blank) ? ($blank['text'] ?? ($blank['option_text'] ?? '')) : $blank;
                    $trimmed = trim((string)$val);
                    if ($trimmed !== '') {
                        Database::insert('question_options', [
                            'question_id' => $questionId,
                            'option_text' => $trimmed,
                            'is_correct'  => 1,
                        ]);
                    }
                }
                break;

            case 'short_answer':
                $refAnswer = trim((string)($options['model_answer'] ?? ($options['reference_answer'] ?? ($options['text'] ?? ($options[0]['text'] ?? '')))));
                if ($refAnswer !== '') {
                    Database::insert('question_options', [
                        'question_id' => $questionId,
                        'option_text' => $refAnswer,
                        'is_correct'  => 1,
                    ]);
                }
                break;
        }
    }
}
