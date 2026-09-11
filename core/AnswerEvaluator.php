<?php
/**
 * PTM Assessment System — Server-Authoritative Answer Evaluator
 * Evaluates candidate responses server-side with zero trust in client scores.
 */

require_once __DIR__ . '/TypingCalculator.php';

class AnswerEvaluator
{
    /**
     * Evaluate an individual question response
     */
    public static function evaluateQuestion(array $question, array $answerData, bool $allowNegative = false): array
    {
        $questionType = $question['question_type'];
        $marks = (float)$question['active_marks'];
        $negativeMarks = $allowNegative ? (float)$question['active_negative_marks'] : 0.00;
        $isPartial = !empty($question['partial_scoring']);

        $options = Database::fetchAll(
            "SELECT * FROM question_options WHERE question_id = ? ORDER BY option_order ASC",
            [$question['id']]
        );

        $isCorrect = false;
        $marksObtained = 0.00;
        $isUnanswered = true;

        switch ($questionType) {
            case 'single_choice':
            case 'true_false':
            case 'yes_no':
                $selectedIds = (array)($answerData['selected_option_ids'] ?? []);
                if (!empty($selectedIds)) {
                    $isUnanswered = false;
                    $selectedId = (int)$selectedIds[0];
                    $correctOption = null;
                    foreach ($options as $opt) {
                        if (!empty($opt['is_correct'])) {
                            $correctOption = (int)$opt['id'];
                            break;
                        }
                    }

                    if ($correctOption !== null && $selectedId === $correctOption) {
                        $isCorrect = true;
                        $marksObtained = $marks;
                    } else {
                        $marksObtained = -$negativeMarks;
                    }
                }
                break;

            case 'multiple_choice':
                $selectedIds = array_map('intval', (array)($answerData['selected_option_ids'] ?? []));
                if (!empty($selectedIds)) {
                    $isUnanswered = false;
                    $correctIds = [];
                    foreach ($options as $opt) {
                        if (!empty($opt['is_correct'])) {
                            $correctIds[] = (int)$opt['id'];
                        }
                    }

                    sort($selectedIds);
                    sort($correctIds);

                    if ($selectedIds === $correctIds) {
                        $isCorrect = true;
                        $marksObtained = $marks;
                    } elseif ($isPartial && count($correctIds) > 0) {
                        $correctChosen = count(array_intersect($selectedIds, $correctIds));
                        $wrongChosen = count(array_diff($selectedIds, $correctIds));
                        $fraction = max(0, ($correctChosen - $wrongChosen) / count($correctIds));
                        $marksObtained = round($fraction * $marks, 2);
                        $isCorrect = ($marksObtained >= $marks);
                    } else {
                        $marksObtained = -$negativeMarks;
                    }
                }
                break;

            case 'fill_blank':
                $textAnswer = trim($answerData['text_answer'] ?? '');
                if ($textAnswer !== '') {
                    $isUnanswered = false;
                    $acceptedRaw = $options[0]['accepted_answers'] ?? '';
                    $acceptedList = array_map('strtolower', array_map('trim', preg_split('/[,;\n]+/', $acceptedRaw)));

                    if (in_array(strtolower($textAnswer), $acceptedList, true)) {
                        $isCorrect = true;
                        $marksObtained = $marks;
                    } else {
                        $marksObtained = -$negativeMarks;
                    }
                }
                break;

            case 'matching':
                $matchedMap = (array)($answerData['matching_answers'] ?? []);
                if (!empty($matchedMap)) {
                    $isUnanswered = false;
                    $totalPairs = count($options);
                    $correctPairs = 0;

                    foreach ($options as $opt) {
                        $optId = (string)$opt['id'];
                        if (isset($matchedMap[$optId]) && trim($matchedMap[$optId]) === trim($opt['match_target'])) {
                            $correctPairs++;
                        }
                    }

                    if ($correctPairs === $totalPairs) {
                        $isCorrect = true;
                        $marksObtained = $marks;
                    } elseif ($isPartial && $totalPairs > 0) {
                        $fraction = $correctPairs / $totalPairs;
                        $marksObtained = round($fraction * $marks, 2);
                        $isCorrect = ($correctPairs === $totalPairs);
                    } else {
                        $marksObtained = -$negativeMarks;
                    }
                }
                break;

            case 'ordering':
                $submittedOrder = array_map('intval', (array)($answerData['ordering_answers'] ?? []));
                if (!empty($submittedOrder)) {
                    $isUnanswered = false;
                    $correctOrder = array_map('intval', array_column($options, 'id'));

                    if ($submittedOrder === $correctOrder) {
                        $isCorrect = true;
                        $marksObtained = $marks;
                    } else {
                        $marksObtained = -$negativeMarks;
                    }
                }
                break;

            case 'typing':
                // Check if candidate typing attempt was recorded
                if (!empty($answerData['typing_result_id'])) {
                    $typingRes = Database::fetch(
                        "SELECT * FROM test_results WHERE id = ?",
                        [(int)$answerData['typing_result_id']]
                    );
                    if ($typingRes) {
                        $isUnanswered = false;
                        $accuracy = (float)$typingRes['accuracy'];
                        $netWpm = (float)$typingRes['net_wpm'];
                        // Standard benchmark: >= 30 WPM and >= 90% accuracy gets full marks
                        if ($netWpm >= 30 && $accuracy >= 90.0) {
                            $marksObtained = $marks;
                            $isCorrect = true;
                        } else {
                            $fraction = min(1.0, ($netWpm / 30.0) * ($accuracy / 100.0));
                            $marksObtained = round($fraction * $marks, 2);
                            $isCorrect = ($fraction >= 0.5);
                        }
                    }
                }
            case 'short_answer':
                $textAns = trim($answerData['text_answer'] ?? '');
                if ($textAns !== '') {
                    $isUnanswered = false;
                    // Check if teacher has already evaluated this response
                    if (isset($answerData['evaluated_by']) && $answerData['evaluated_by'] !== null) {
                        $marksObtained = min($marks, (float)($answerData['marks_obtained'] ?? 0.00));
                        $isCorrect = ($marksObtained >= ($marks / 2.0));
                    } else {
                        $isCorrect = null; // Pending teacher review
                        $marksObtained = 0.00;
                    }
                }
                break;
        }

        return [
            'is_unanswered'           => $isUnanswered,
            'is_correct'              => $isCorrect === null ? null : ($isCorrect ? 1 : 0),
            'marks_obtained'          => max(0.0, (float)$marksObtained),
            'requires_manual_grading' => ($questionType === 'short_answer' && !$isUnanswered && empty($answerData['evaluated_by'])),
        ];
    }
}

