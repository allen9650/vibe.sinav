<?php
/**
 * PTM Assessment System — Assessment Result Service
 * Handles attempt lifecycles, server-authoritative timers, delta autosave, and result computation.
 */

require_once __DIR__ . '/AnswerEvaluator.php';

class AssessmentResultService
{
    /**
     * Start or resume a candidate examination attempt
     */
    public static function startOrResumeAttempt(int $assessmentId, int $candidateId, ?string $studentName = null): array
    {
        $assessment = AssessmentService::getAssessment($assessmentId);
        if (!$assessment || $assessment['status'] !== 'published') {
            throw new Exception("Assessment is not currently published for candidate access.");
        }

        // Update candidate name if supplied
        if ($studentName !== null && trim($studentName) !== '') {
            $cleanName = trim($studentName);
            Database::update('candidates', ['full_name' => $cleanName], 'id = ?', [$candidateId]);
        }

        // Check for an active in_progress attempt
        $existingAttempt = Database::fetch(
            "SELECT * FROM assessment_attempts 
             WHERE assessment_id = ? AND candidate_id = ? AND status = 'in_progress'
             ORDER BY id DESC LIMIT 1",
            [$assessmentId, $candidateId]
        );

        if ($existingAttempt) {
            $now = time();
            $expectedEnd = strtotime($existingAttempt['expected_end_at']);
            $remainingSeconds = max(0, $expectedEnd - $now);

            // If time has expired while away, submit it
            if ($remainingSeconds <= 0) {
                self::submitAttempt((int)$existingAttempt['id'], true);
                throw new Exception("Your allocated time for this assessment has expired.");
            }

            // Update student name on existing attempt if provided
            if ($studentName !== null && trim($studentName) !== '' && empty($existingAttempt['student_name'])) {
                Database::update('assessment_attempts', ['student_name' => trim($studentName)], 'id = ?', [$existingAttempt['id']]);
                $existingAttempt['student_name'] = trim($studentName);
            }

            return [
                'resumed'           => true,
                'attempt'           => $existingAttempt,
                'remaining_seconds' => $remainingSeconds,
                'assessment'        => $assessment,
            ];
        }

        // Check max attempts limit
        $pastAttemptsCount = (int)Database::fetchColumn(
            "SELECT COUNT(*) FROM assessment_attempts WHERE assessment_id = ? AND candidate_id = ?",
            [$assessmentId, $candidateId]
        );

        if ($pastAttemptsCount >= (int)$assessment['max_attempts']) {
            throw new Exception("You have already reached the maximum allowed attempts ({$assessment['max_attempts']}) for this assessment.");
        }

        // Prepare question sequence (shuffle if randomize_questions is enabled)
        $questions = AssessmentService::getAssessmentQuestions($assessmentId);
        if (empty($questions)) {
            throw new Exception("This assessment does not contain any questions.");
        }

        $questionIds = array_column($questions, 'question_id');
        if (!empty($assessment['randomize_questions'])) {
            shuffle($questionIds);
        }

        $now = date('Y-m-d H:i:s');
        $durationSeconds = (int)$assessment['duration_seconds'];
        $expectedEndAt = date('Y-m-d H:i:s', time() + $durationSeconds);

        $attemptId = Database::insert('assessment_attempts', [
            'assessment_id'       => $assessmentId,
            'candidate_id'        => $candidateId,
            'student_name'        => $studentName ? trim($studentName) : null,
            'attempt_number'      => $pastAttemptsCount + 1,
            'started_at'          => $now,
            'expected_end_at'     => $expectedEndAt,
            'duration_seconds'    => $durationSeconds,
            'status'              => 'in_progress',
            'question_order_seed' => json_encode($questionIds),
            'ip_address'          => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent'          => $_SERVER['HTTP_USER_AGENT'] ?? null,
        ]);

        // Update candidate attendance / status
        Database::update('assessment_candidates', ['status' => 'in_progress'], 
            'assessment_id = ? AND candidate_id = ?', [$assessmentId, $candidateId]);

        $attempt = Database::fetch("SELECT * FROM assessment_attempts WHERE id = ?", [$attemptId]);

        return [
            'resumed'           => false,
            'attempt'           => $attempt,
            'remaining_seconds' => $durationSeconds,
            'assessment'        => $assessment,
        ];
    }

    /**
     * Save a small delta answer payload (< 1 KB) via lightweight fetch
     */
    public static function saveAnswerDelta(int $attemptId, int $questionId, array $payload): bool
    {
        $attempt = Database::fetch("SELECT * FROM assessment_attempts WHERE id = ?", [$attemptId]);
        if (!$attempt || $attempt['status'] !== 'in_progress') {
            return false;
        }

        // Server authoritative time check
        if (time() > strtotime($attempt['expected_end_at']) + 5) {
            self::submitAttempt($attemptId, true);
            return false;
        }

        $selectedOptionIds = !empty($payload['selected_option_ids']) ? json_encode(array_values((array)$payload['selected_option_ids'])) : null;
        $textAnswer = isset($payload['text_answer']) ? trim($payload['text_answer']) : null;
        $matchingAnswers = !empty($payload['matching_answers']) ? json_encode($payload['matching_answers']) : null;
        $orderingAnswers = !empty($payload['ordering_answers']) ? json_encode(array_values((array)$payload['ordering_answers'])) : null;
        $typingResultId = !empty($payload['typing_result_id']) ? (int)$payload['typing_result_id'] : null;
        $isReview = !empty($payload['is_marked_for_review']) ? 1 : 0;

        $pdo = Database::getInstance();
        $stmt = $pdo->prepare(
            "INSERT INTO assessment_answers 
                (attempt_id, question_id, selected_option_ids, text_answer, matching_answers, ordering_answers, typing_result_id, is_marked_for_review, last_saved_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE 
                selected_option_ids = VALUES(selected_option_ids),
                text_answer = VALUES(text_answer),
                matching_answers = VALUES(matching_answers),
                ordering_answers = VALUES(ordering_answers),
                typing_result_id = VALUES(typing_result_id),
                is_marked_for_review = VALUES(is_marked_for_review),
                last_saved_at = NOW()"
        );

        return $stmt->execute([
            $attemptId, 
            $questionId, 
            $selectedOptionIds, 
            $textAnswer, 
            $matchingAnswers, 
            $orderingAnswers, 
            $typingResultId, 
            $isReview
        ]);
    }

    /**
     * Submit and authoritatively evaluate an assessment attempt
     */
    public static function submitAttempt(int $attemptId, bool $isTimeout = false): array
    {
        $attempt = Database::fetch(
            "SELECT att.*, a.total_marks as assessment_total_marks, a.passing_percentage, a.negative_marking
             FROM assessment_attempts att
             JOIN assessments a ON att.assessment_id = a.id
             WHERE att.id = ?",
            [$attemptId]
        );

        if (!$attempt) {
            throw new Exception("Attempt not found.");
        }

        // If already completed, return existing result
        if ($attempt['status'] === 'completed' || $attempt['status'] === 'timed_out') {
            return Database::fetch("SELECT * FROM assessment_results WHERE attempt_id = ?", [$attemptId]);
        }

        $now = time();
        $startedAt = strtotime($attempt['started_at']);
        $timeTaken = max(1, $now - $startedAt);

        // Fetch all assessment questions
        $questions = AssessmentService::getAssessmentQuestions((int)$attempt['assessment_id']);
        
        // Fetch all saved answers for this attempt
        $savedAnswers = Database::fetchAll(
            "SELECT * FROM assessment_answers WHERE attempt_id = ?",
            [$attemptId]
        );
        $answersMap = [];
        foreach ($savedAnswers as $sa) {
            $answersMap[(int)$sa['question_id']] = [
                'selected_option_ids' => json_decode($sa['selected_option_ids'] ?? '[]', true),
                'text_answer'         => $sa['text_answer'],
                'matching_answers'    => json_decode($sa['matching_answers'] ?? '[]', true),
                'ordering_answers'    => json_decode($sa['ordering_answers'] ?? '[]', true),
                'typing_result_id'    => $sa['typing_result_id'],
            ];
        }

        $totalQuestions = count($questions);
        $correctCount = 0;
        $wrongCount = 0;
        $unansweredCount = 0;
        $totalMarksPossible = 0.00;
        $obtainedMarks = 0.00;

        $pdo = Database::getInstance();
        $pdo->beginTransaction();

        try {
            $updateAnswerStmt = $pdo->prepare(
                "UPDATE assessment_answers 
                 SET is_correct = ?, marks_obtained = ?, evaluated_at = NOW() 
                 WHERE attempt_id = ? AND question_id = ?"
            );

            $manualReviewPending = 0;
            foreach ($questions as $q) {
                $qid = (int)$q['question_id'];
                $marks = (float)$q['active_marks'];
                $totalMarksPossible += $marks;

                $candidateAnswer = $answersMap[$qid] ?? [];

                $eval = AnswerEvaluator::evaluateQuestion($q, $candidateAnswer, (bool)$attempt['negative_marking']);

                if (!empty($eval['requires_manual_grading'])) {
                    $manualReviewPending = 1;
                }

                if ($eval['is_unanswered']) {
                    $unansweredCount++;
                } elseif (!empty($eval['is_correct'])) {
                    $correctCount++;
                    $obtainedMarks += $eval['marks_obtained'];
                } else {
                    $wrongCount++;
                    $obtainedMarks += $eval['marks_obtained'];
                }

                // If record exists in answers table, update marks and evaluated_at
                $updateAnswerStmt->execute([$eval['is_correct'], $eval['marks_obtained'], $attemptId, $qid]);
            }

            // Floor obtained marks at 0
            $obtainedMarks = max(0.00, round($obtainedMarks, 2));
            $percentage = $totalMarksPossible > 0 ? round(($obtainedMarks / $totalMarksPossible) * 100, 2) : 0.00;
            $accuracy = ($totalQuestions - $unansweredCount) > 0 
                ? round(($correctCount / ($totalQuestions - $unansweredCount)) * 100, 2) 
                : 0.00;

            $passFail = ($percentage >= (float)$attempt['passing_percentage']) ? 'pass' : 'fail';
            $finalStatus = $isTimeout ? 'timed_out' : 'completed';

            // Insert / update assessment_results
            $resultData = [
                'attempt_id'            => $attemptId,
                'assessment_id'         => (int)$attempt['assessment_id'],
                'candidate_id'          => (int)$attempt['candidate_id'],
                'total_questions'       => $totalQuestions,
                'correct_answers'       => $correctCount,
                'wrong_answers'         => $wrongCount,
                'unanswered'            => $unansweredCount,
                'total_marks'           => $totalMarksPossible,
                'obtained_marks'        => $obtainedMarks,
                'percentage'            => $percentage,
                'accuracy'              => $accuracy,
                'pass_fail'             => $passFail,
                'manual_review_pending' => $manualReviewPending,
                'time_taken_seconds'    => $timeTaken,
            ];

            Database::insert('assessment_results', $resultData);

            // Update attempt record
            Database::update('assessment_attempts', [
                'status'             => $finalStatus,
                'submitted_at'       => date('Y-m-d H:i:s'),
                'time_taken_seconds' => $timeTaken,
            ], 'id = ?', [$attemptId]);

            // Update candidate status
            Database::update('assessment_candidates', [
                'status' => 'completed'
            ], 'assessment_id = ? AND candidate_id = ?', [(int)$attempt['assessment_id'], (int)$attempt['candidate_id']]);

            // Recompute ranking for this assessment
            self::updateAssessmentRanks((int)$attempt['assessment_id']);

            $pdo->commit();

            return Database::fetch("SELECT * FROM assessment_results WHERE attempt_id = ?", [$attemptId]);
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Compute and update ranks for an assessment
     */
    private static function updateAssessmentRanks(int $assessmentId): void
    {
        $results = Database::fetchAll(
            "SELECT id FROM assessment_results 
             WHERE assessment_id = ? 
             ORDER BY obtained_marks DESC, accuracy DESC, time_taken_seconds ASC, id ASC",
            [$assessmentId]
        );

        $rank = 1;
        foreach ($results as $res) {
            Database::update('assessment_results', ['rank' => $rank++], 'id = ?', [$res['id']]);
        }
    }

    /**
     * Get results list for an assessment (Teacher & Admin ledger)
     */
    public static function getResultsByAssessment(int $assessmentId): array
    {
        return Database::fetchAll(
            "SELECT ar.*, 
                    c.full_name as candidate_name, c.roll_number, c.registration_number, c.course, c.batch,
                    att.started_at, att.submitted_at, att.status as attempt_status
             FROM assessment_results ar
             JOIN candidates c ON ar.candidate_id = c.id
             JOIN assessment_attempts att ON ar.attempt_id = att.id
             WHERE ar.assessment_id = ?
             ORDER BY ar.rank ASC, ar.obtained_marks DESC",
            [$assessmentId]
        );
    }

    /**
     * Get candidate scorecard, questions, options, and security violation audit
     */
    public static function getCandidateScorecard(int $attemptId): ?array
    {
        $attempt = Database::fetch(
            "SELECT att.*,
                    att.id AS attempt_id,
                    att.student_name AS candidate_name,
                    att.student_roll_no AS roll_number,
                    att.student_class,
                    ls.station_code,
                    ls.ip_address AS station_ip,
                    a.id AS assessment_id,
                    a.title AS assessment_title,
                    a.target_class,
                    a.duration_minutes,
                    a.passing_percentage,
                    a.is_result_published,
                    camp.name AS campus_name,
                    sub.name AS subject_name,
                    u.username AS teacher_name,
                    res.total_marks,
                    res.obtained_marks,
                    res.percentage,
                    res.pass_fail,
                    res.generated_at
             FROM assessment_attempts att
             JOIN assessments a ON att.assessment_id = a.id
             JOIN campuses camp ON a.campus_id = camp.id
             JOIN subjects sub ON a.subject_id = sub.id
             LEFT JOIN users u ON a.created_by = u.id
             LEFT JOIN lab_stations ls ON att.station_id = ls.id
             LEFT JOIN assessment_results res ON att.id = res.attempt_id
             WHERE att.id = ?",
            [$attemptId]
        );

        if (!$attempt) {
            return null;
        }

        // Detailed question answers
        $questions = Database::fetchAll(
            "SELECT aq.question_order, 
                    q.id AS question_id, q.question_text, q.question_type, q.marks,
                    aa.id AS answer_id, aa.selected_option_ids, aa.text_answer,
                    aa.is_correct, aa.needs_review, aa.marks_obtained,
                    aa.teacher_comment, aa.evaluated_by, aa.evaluated_at
             FROM assessment_questions aq
             JOIN questions q ON aq.question_id = q.id
             LEFT JOIN assessment_answers aa ON aa.attempt_id = ? AND aa.question_id = q.id
             WHERE aq.assessment_id = ?
             ORDER BY aq.question_order ASC",
            [$attemptId, (int)$attempt['assessment_id']]
        );

        $totalQuestions = count($questions);
        $answeredCount = 0;
        $correctCount = 0;
        $wrongCount = 0;
        $unansweredCount = 0;
        $pendingReviewCount = 0;

        foreach ($questions as &$q) {
            $q['options'] = Database::fetchAll(
                "SELECT id, option_text, is_correct FROM question_options WHERE question_id = ? ORDER BY id ASC",
                [(int)$q['question_id']]
            );

            $hasAnswer = ($q['selected_option_ids'] !== null || ($q['text_answer'] !== null && trim((string)$q['text_answer']) !== ''));
            if ($hasAnswer) {
                $answeredCount++;
                if ((int)($q['needs_review'] ?? 0) === 1) {
                    $pendingReviewCount++;
                }
                if ((int)($q['is_correct'] ?? 0) === 1) {
                    $correctCount++;
                } else {
                    $wrongCount++;
                }
            } else {
                $unansweredCount++;
            }
        }
        unset($q);

        $attempt['questions'] = $questions;
        $attempt['total_questions'] = $totalQuestions;
        $attempt['answered_count'] = $answeredCount;
        $attempt['correct_answers'] = $correctCount;
        $attempt['wrong_answers'] = $wrongCount;
        $attempt['unanswered'] = $unansweredCount;
        $attempt['pending_review_count'] = $pendingReviewCount;
        $attempt['accuracy'] = ($answeredCount > 0) ? round(($correctCount / $answeredCount) * 100, 1) : 0.0;

        $startTs = strtotime($attempt['started_at'] ?? 'now');
        $endTs = !empty($attempt['submitted_at']) ? strtotime($attempt['submitted_at']) : time();
        $attempt['time_taken_seconds'] = max(0, $endTs - $startTs);

        // Fetch proctoring violation security events for this attempt
        $attempt['security_events'] = Database::fetchAll(
            "SELECT * FROM security_events 
             WHERE attempt_id = ? OR JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.attempt_id')) = ? 
             ORDER BY created_at ASC",
            [$attemptId, (string)$attemptId]
        );

        return $attempt;
    }

    /**
     * Teacher manual grading for subjective questions (fill_blank, short_answer)
     */
    public static function gradeQuestion(int $attemptId, int $questionId, float $marksAwarded, ?string $comment, int $teacherId): bool
    {
        $question = Database::fetch("SELECT * FROM questions WHERE id = ?", [$questionId]);
        if (!$question) {
            throw new RuntimeException("Question not found.");
        }

        $maxMarks = (float)$question['marks'];
        $marksAwarded = max(0.0, min($maxMarks, round($marksAwarded, 2)));
        $isCorrect = ($marksAwarded > 0.0) ? (($marksAwarded >= ($maxMarks / 2.0)) ? 1 : 0) : 0;

        // Upsert / update answer row
        Database::query(
            "INSERT INTO assessment_answers (attempt_id, question_id, marks_obtained, is_correct, needs_review, teacher_comment, evaluated_by, evaluated_at, updated_at)
             VALUES (?, ?, ?, ?, 0, ?, ?, NOW(), NOW())
             ON DUPLICATE KEY UPDATE 
                marks_obtained = VALUES(marks_obtained),
                is_correct = VALUES(is_correct),
                needs_review = 0,
                teacher_comment = VALUES(teacher_comment),
                evaluated_by = VALUES(evaluated_by),
                evaluated_at = NOW(),
                updated_at = NOW()",
            [$attemptId, $questionId, $marksAwarded, $isCorrect, $comment ? trim($comment) : null, $teacherId]
        );

        return true;
    }

    /**
     * Finalize attempt evaluation, recalculate scorecard totals, and release results
     */
    public static function finalizeCandidateResult(int $attemptId, int $teacherId): array
    {
        $attempt = Database::fetch(
            "SELECT att.*, a.passing_percentage, a.total_marks AS assessment_total
             FROM assessment_attempts att
             JOIN assessments a ON att.assessment_id = a.id
             WHERE att.id = ?",
            [$attemptId]
        );
        if (!$attempt) {
            throw new RuntimeException("Attempt not found.");
        }

        $allAnswers = Database::fetchAll(
            "SELECT aa.marks_obtained, aa.is_correct, q.marks AS max_marks
             FROM assessment_answers aa
             JOIN questions q ON aa.question_id = q.id
             WHERE aa.attempt_id = ?",
            [$attemptId]
        );

        $totalObtained = 0.00;
        $totalPossible = 0.00;

        foreach ($allAnswers as $ans) {
            $totalObtained += (float)$ans['marks_obtained'];
            $totalPossible += (float)$ans['max_marks'];
        }

        if ($totalPossible <= 0) {
            $totalPossible = (float)$attempt['assessment_total'];
        }

        $percentage = ($totalPossible > 0) ? round(($totalObtained / $totalPossible) * 100, 2) : 0.00;
        $passingPct = (float)$attempt['passing_percentage'];
        $passFail = ($percentage >= $passingPct) ? 'pass' : 'fail';

        // Update or insert into assessment_results
        Database::query(
            "INSERT INTO assessment_results (attempt_id, total_marks, obtained_marks, percentage, pass_fail, generated_at)
             VALUES (?, ?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE
                total_marks = VALUES(total_marks),
                obtained_marks = VALUES(obtained_marks),
                percentage = VALUES(percentage),
                pass_fail = VALUES(pass_fail),
                generated_at = NOW()",
            [$attemptId, $totalPossible, $totalObtained, $percentage, $passFail]
        );

        // Update attempt status: mark as reviewed
        Database::update('assessment_attempts', [
            'needs_review' => 0,
            'is_reviewed'  => 1,
            'reviewed_by'  => $teacherId,
            'reviewed_at'  => date('Y-m-d H:i:s'),
        ], 'id = ?', [$attemptId]);

        AuditLog::log('attempt_reviewed', 'assessment_attempts', "Finalized and generated result for attempt #{$attemptId}", $teacherId);

        return Database::fetch("SELECT * FROM assessment_results WHERE attempt_id = ?", [$attemptId]);
    }
}

