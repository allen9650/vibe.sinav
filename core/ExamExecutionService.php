<?php
declare(strict_types=1);

/**
 * PTM Assessment System — Exam Execution Service
 * Handles attempt initialization, server-authoritative delta autosaves, and terminal exam submission & scoring.
 */
class ExamExecutionService
{
    /**
     * Start or resume an assessment attempt from an authorized lab station.
     *
     * @param int $assessmentId Target assessment ID.
     * @param int $stationId Authorized lab station ID.
     * @param string $studentName Student full name.
     * @param string $rollNo Student roll / ID number.
     * @return array Returns attempt and session payload.
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public static function startAttempt(
        int $assessmentId,
        int $stationId,
        string $studentName,
        string $rollNo
    ): array {
        $studentName = trim($studentName);
        $rollNo = trim($rollNo);

        if ($assessmentId <= 0 || $stationId <= 0) {
            throw new InvalidArgumentException("Valid assessment ID and station ID are required.");
        }
        if ($studentName === '') {
            throw new InvalidArgumentException("Student full name cannot be empty.");
        }
        if ($rollNo === '') {
            throw new InvalidArgumentException("Student roll number cannot be empty.");
        }

        // Verify assessment is published
        $assessment = Database::fetch(
            "SELECT * FROM assessments WHERE id = ? AND status = 'published'",
            [$assessmentId]
        );
        if (!$assessment) {
            throw new RuntimeException("Assessment is not currently published or available for testing.");
        }

        // Verify station is allocated to this assessment
        $stationAlloc = Database::fetch(
            "SELECT ast.*, s.station_code, s.status AS hardware_status 
             FROM assessment_stations ast
             JOIN lab_stations s ON ast.station_id = s.id
             WHERE ast.assessment_id = ? AND ast.station_id = ?",
            [$assessmentId, $stationId]
        );
        if (!$stationAlloc) {
            throw new RuntimeException("Workstation is not allocated to this assessment.");
        }
        if ($stationAlloc['hardware_status'] !== 'active') {
            throw new RuntimeException("This workstation is currently marked as disabled by administrator.");
        }

        // Check for existing active attempt on this station
        $existingAttempt = Database::fetch(
            "SELECT * FROM assessment_attempts 
             WHERE assessment_id = ? AND station_id = ? AND status = 'in_progress'
             ORDER BY id DESC LIMIT 1",
            [$assessmentId, $stationId]
        );

        $now = time();

        if ($existingAttempt) {
            $expectedEnd = strtotime($existingAttempt['expected_end_at']);
            if ($now < $expectedEnd) {
                // Resume active attempt
                return self::formatAttemptSession($existingAttempt, $assessment);
            }

            // Attempt has expired: mark timed out
            Database::update('assessment_attempts', [
                'status'       => 'timed_out',
                'submitted_at' => date('Y-m-d H:i:s', $expectedEnd),
            ], 'id = ?', [(int)$existingAttempt['id']]);

            Database::update('assessment_stations', [
                'status' => 'submitted',
            ], 'assessment_id = ? AND station_id = ?', [$assessmentId, $stationId]);

            throw new RuntimeException("The previous exam session on this station has timed out.");
        }

        // Check if station is already marked submitted
        if ($stationAlloc['status'] === 'submitted') {
            throw new RuntimeException("This workstation has already completed and submitted its examination.");
        }

        $durationMins = (int)$assessment['duration_minutes'];
        $startedAtStr = date('Y-m-d H:i:s', $now);
        $expectedEndStr = date('Y-m-d H:i:s', $now + ($durationMins * 60));

        Database::beginTransaction();
        try {
            // Insert attempt
            $attemptId = Database::insert('assessment_attempts', [
                'assessment_id'   => $assessmentId,
                'station_id'      => $stationId,
                'student_name'    => $studentName,
                'student_roll_no' => $rollNo,
                'student_class'   => $assessment['target_class'],
                'started_at'      => $startedAtStr,
                'expected_end_at' => $expectedEndStr,
                'status'          => 'in_progress',
            ]);

            // Mark station in progress
            Database::update('assessment_stations', [
                'status' => 'in_progress',
            ], 'assessment_id = ? AND station_id = ?', [$assessmentId, $stationId]);

            Database::commit();

            $attempt = Database::fetch("SELECT * FROM assessment_attempts WHERE id = ?", [$attemptId]);
            return self::formatAttemptSession($attempt, $assessment);
        } catch (Throwable $e) {
            Database::rollback();
            throw new RuntimeException("Failed to initialize exam attempt: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Save an authoritative delta answer for a single question.
     *
     * @param int $attemptId Attempt ID.
     * @param int $questionId Question ID being answered.
     * @param mixed $answer Selected option IDs (int/array) or text answer (string).
     * @return bool
     * @throws RuntimeException
     */
    public static function saveDeltaAnswer(int $attemptId, int $questionId, mixed $answer): bool
    {
        $attempt = Database::fetch("SELECT * FROM assessment_attempts WHERE id = ?", [$attemptId]);
        if (!$attempt) {
            throw new RuntimeException("Attempt not found.");
        }

        if ($attempt['status'] !== 'in_progress') {
            throw new RuntimeException("Cannot save answer: attempt status is '{$attempt['status']}'.");
        }

        // Server-authoritative timer check
        $now = time();
        $expectedEnd = strtotime($attempt['expected_end_at']);
        if ($now > $expectedEnd) {
            // Lock as timed out
            Database::update('assessment_attempts', [
                'status'       => 'timed_out',
                'submitted_at' => date('Y-m-d H:i:s', $expectedEnd),
            ], 'id = ?', [$attemptId]);

            Database::update('assessment_stations', [
                'status' => 'submitted',
            ], 'assessment_id = ? AND station_id = ?', [(int)$attempt['assessment_id'], (int)$attempt['station_id']]);

            throw new RuntimeException("Time expired. Your test has been submitted.");
        }

        // Verify question belongs to this assessment
        $q = Database::fetch(
            "SELECT q.id, q.question_type 
             FROM assessment_questions aq
             JOIN questions q ON aq.question_id = q.id
             WHERE aq.assessment_id = ? AND aq.question_id = ?",
            [(int)$attempt['assessment_id'], $questionId]
        );
        if (!$q) {
            throw new RuntimeException("Question #{$questionId} does not belong to this assessment.");
        }

        $selectedOptionIds = null;
        $textAnswer = null;

        if (in_array($q['question_type'], ['fill_blank', 'short_answer'], true)) {
            $textAnswer = is_string($answer) ? trim($answer) : (is_array($answer) ? trim((string)reset($answer)) : '');
        } else {
            // Choice question
            $ids = [];
            if (is_array($answer)) {
                $ids = array_map('intval', array_filter($answer, fn($v) => is_numeric($v) && (int)$v > 0));
            } elseif (is_numeric($answer) && (int)$answer > 0) {
                $ids = [(int)$answer];
            }
            $selectedOptionIds = !empty($ids) ? json_encode(array_values(array_unique($ids))) : null;
        }

        // Upsert into assessment_answers
        Database::query(
            "INSERT INTO assessment_answers (attempt_id, question_id, selected_option_ids, text_answer, updated_at)
             VALUES (?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE 
                selected_option_ids = VALUES(selected_option_ids),
                text_answer = VALUES(text_answer),
                updated_at = NOW()",
            [$attemptId, $questionId, $selectedOptionIds, $textAnswer]
        );

        return true;
    }

    /**
     * Terminally evaluate and finalize an exam attempt.
     * Compares student answers against reference solutions, updates marks, and generates scorecard.
     *
     * @param int $attemptId Attempt ID.
     * @param array $allAnswers Optional fallback map of questionId => answer submitted with the form.
     * @return array Result record array.
     * @throws RuntimeException
     */
    public static function submitExam(int $attemptId, array $allAnswers = []): array
    {
        $attempt = Database::fetch(
            "SELECT att.*, a.total_marks AS assessment_total_marks, a.passing_percentage
             FROM assessment_attempts att
             JOIN assessments a ON att.assessment_id = a.id
             WHERE att.id = ?",
            [$attemptId]
        );
        if (!$attempt) {
            throw new RuntimeException("Attempt not found.");
        }

        // If already submitted, return existing result
        if ($attempt['status'] === 'completed' || $attempt['status'] === 'timed_out') {
            $res = Database::fetch("SELECT * FROM assessment_results WHERE attempt_id = ?", [$attemptId]);
            if ($res) {
                return $res;
            }
        }

        // Flush any un-autosaved answers from client payload
        if (!empty($allAnswers)) {
            foreach ($allAnswers as $qId => $ansVal) {
                $qIdInt = (int)$qId;
                if ($qIdInt > 0) {
                    try {
                        self::saveDeltaAnswer($attemptId, $qIdInt, $ansVal);
                    } catch (Throwable) {
                        // Suppress individual autosave errors during batch flush
                    }
                }
            }
        }

        $assessmentId = (int)$attempt['assessment_id'];
        $now = time();
        $expectedEnd = strtotime($attempt['expected_end_at']);
        $finalStatus = ($now > $expectedEnd + 15) ? 'timed_out' : 'completed';

        // Fetch all questions and correct reference options
        $questions = Database::fetchAll(
            "SELECT q.*, aq.question_order 
             FROM assessment_questions aq
             JOIN questions q ON aq.question_id = q.id
             WHERE aq.assessment_id = ?
             ORDER BY aq.question_order ASC",
            [$assessmentId]
        );

        // Fetch all saved answers for this attempt
        $savedAnswers = Database::fetchAll(
            "SELECT * FROM assessment_answers WHERE attempt_id = ?",
            [$attemptId]
        );
        $savedMap = [];
        foreach ($savedAnswers as $sa) {
            $savedMap[(int)$sa['question_id']] = $sa;
        }

        $totalMarksPossible = 0.00;
        $totalMarksObtained = 0.00;
        $hasSubjectiveQuestions = false;

        Database::beginTransaction();
        try {
            foreach ($questions as $q) {
                $qId = (int)$q['id'];
                $qMarks = (float)$q['marks'];
                $totalMarksPossible += $qMarks;

                // Fetch reference options
                $options = Database::fetchAll(
                    "SELECT id, option_text, is_correct FROM question_options WHERE question_id = ?",
                    [$qId]
                );

                $correctOptionIds = [];
                $correctOptionTexts = [];
                foreach ($options as $opt) {
                    if ((int)$opt['is_correct'] === 1) {
                        $correctOptionIds[] = (int)$opt['id'];
                        $correctOptionTexts[] = mb_strtolower(trim($opt['option_text']));
                    }
                }

                $userAnswer = $savedMap[$qId] ?? null;
                $isCorrect = 0;
                $marksEarned = 0.00;
                $needsReview = 0;

                $isSubjective = in_array($q['question_type'], ['fill_blank', 'short_answer'], true);
                if ($isSubjective) {
                    $hasSubjectiveQuestions = true;
                }

                if ($userAnswer) {
                    if ($q['question_type'] === 'fill_blank') {
                        $userText = mb_strtolower(trim((string)($userAnswer['text_answer'] ?? '')));
                        if ($userText !== '') {
                            // Exact match gives provisional points, but still marked for teacher review
                            if (in_array($userText, $correctOptionTexts, true)) {
                                $isCorrect = 1;
                                $marksEarned = $qMarks;
                            }
                            $needsReview = 1;
                        } else {
                            $isCorrect = 0;
                            $marksEarned = 0.00;
                            $needsReview = 0;
                        }
                    } elseif ($q['question_type'] === 'short_answer') {
                        $userText = trim((string)($userAnswer['text_answer'] ?? ''));
                        if ($userText !== '') {
                            $needsReview = 1;
                            $isCorrect = null;
                            $marksEarned = 0.00; // Requires teacher manual scoring
                        } else {
                            $isCorrect = 0;
                            $marksEarned = 0.00;
                            $needsReview = 0;
                        }
                    } elseif ($q['question_type'] === 'multiple_choice') {
                        $userSelected = json_decode((string)($userAnswer['selected_option_ids'] ?? '[]'), true) ?: [];
                        $userSelected = array_map('intval', $userSelected);
                        sort($userSelected);
                        sort($correctOptionIds);

                        if (!empty($userSelected) && $userSelected === $correctOptionIds) {
                            $isCorrect = 1;
                            $marksEarned = $qMarks;
                        }
                    } else {
                        // single_choice, true_false
                        $userSelected = json_decode((string)($userAnswer['selected_option_ids'] ?? '[]'), true) ?: [];
                        if (count($userSelected) === 1 && in_array((int)$userSelected[0], $correctOptionIds, true)) {
                            $isCorrect = 1;
                            $marksEarned = $qMarks;
                        }
                    }

                    // Update answer record
                    Database::update('assessment_answers', [
                        'is_correct'     => $isCorrect,
                        'needs_review'   => $needsReview,
                        'marks_obtained' => $marksEarned,
                    ], 'attempt_id = ? AND question_id = ?', [$attemptId, $qId]);
                } else {
                    // Record blank/unanswered
                    Database::insert('assessment_answers', [
                        'attempt_id'          => $attemptId,
                        'question_id'         => $qId,
                        'selected_option_ids' => null,
                        'text_answer'         => null,
                        'is_correct'          => 0,
                        'needs_review'        => 0,
                        'marks_obtained'      => 0.00,
                    ]);
                }

                $totalMarksObtained += $marksEarned;
            }

            // Calculate percentage and pass/fail
            $percentage = ($totalMarksPossible > 0)
                ? round(($totalMarksObtained / $totalMarksPossible) * 100, 2)
                : 0.00;
            $passingPct = (float)$attempt['passing_percentage'];
            $passFail = ($percentage >= $passingPct) ? 'pass' : 'fail';

            // Insert or update assessment_results
            Database::query(
                "INSERT INTO assessment_results (attempt_id, total_marks, obtained_marks, percentage, pass_fail, generated_at)
                 VALUES (?, ?, ?, ?, ?, NOW())
                 ON DUPLICATE KEY UPDATE 
                    total_marks = VALUES(total_marks),
                    obtained_marks = VALUES(obtained_marks),
                    percentage = VALUES(percentage),
                    pass_fail = VALUES(pass_fail),
                    generated_at = NOW()",
                [$attemptId, $totalMarksPossible, $totalMarksObtained, $percentage, $passFail]
            );

            // Lock attempt and flag manual review if needed
            Database::update('assessment_attempts', [
                'status'       => $finalStatus,
                'submitted_at' => date('Y-m-d H:i:s'),
                'needs_review' => $hasSubjectiveQuestions ? 1 : 0,
                'is_reviewed'  => $hasSubjectiveQuestions ? 0 : 1,
            ], 'id = ?', [$attemptId]);

            // Mark station submitted
            Database::update('assessment_stations', [
                'status' => 'submitted',
            ], 'assessment_id = ? AND station_id = ?', [$assessmentId, (int)$attempt['station_id']]);

            Database::commit();

            return Database::fetch("SELECT * FROM assessment_results WHERE attempt_id = ?", [$attemptId]);
        } catch (Throwable $e) {
            Database::rollback();
            throw new RuntimeException("Exam submission failed: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Helper to package active attempt session info.
     */
    private static function formatAttemptSession(array $attempt, array $assessment): array
    {
        $now = time();
        $expectedEnd = strtotime($attempt['expected_end_at']);
        $remainingSecs = max(0, $expectedEnd - $now);

        return [
            'attempt_id'        => (int)$attempt['id'],
            'attempt'           => $attempt,
            'assessment'        => $assessment,
            'remaining_seconds' => $remainingSecs,
            'server_time'       => date('Y-m-d H:i:s', $now),
        ];
    }
}

