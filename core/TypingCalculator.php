<?php
/**
 * Typing Calculation Engine & Alignment Service
 * Marr Typing Competition System
 *
 * Scoring Version: 1.0
 * Provides authoritative server-side calculations for:
 * - Dynamic programming character-level sequence alignment
 * - Correct, Substitution (Incorrect), Missing (Deletion), and Extra (Insertion) character counts
 * - Word-level analysis (Correct, Incorrect, Missing, Extra)
 * - Standard Gross WPM, Net WPM, and Accuracy %
 * - Final Score and Qualification determination
 */

class TypingCalculator {
    const SCORING_VERSION = '1.0';

    /**
     * Calculate complete typing performance statistics.
     *
     * @param string $referenceText Authoritative passage text
     * @param string $typedText Candidate final typed text
     * @param int $durationSeconds Scoring duration in seconds
     * @param int $backspaceCount Total backspaces recorded
     * @param array $settings Optional competition settings (passing_wpm, passing_accuracy)
     * @return array Calculated performance metrics
     */
    public static function calculate(
        string $referenceText,
        string $typedText,
        int $durationSeconds,
        int $backspaceCount = 0,
        array $settings = []
    ): array {
        // 1. Normalize line endings (CRLF -> LF) without changing spaces or content
        $reference = str_replace(["\r\n", "\r"], "\n", $referenceText);
        $typed = str_replace(["\r\n", "\r"], "\n", $typedText);

        $refLen = mb_strlen($reference, 'UTF-8');
        $typedLen = mb_strlen($typed, 'UTF-8');

        // 2. Character-Level Sequence Alignment
        $charAlignment = self::alignCharacters($reference, $typed);

        $correctChars   = $charAlignment['correct'];
        $incorrectChars = $charAlignment['incorrect'];
        $missingChars   = $charAlignment['missing']; // Missing characters within typed span
        $unreachedChars = $charAlignment['unreached']; // Untyped trailing characters
        $extraChars     = $charAlignment['extra'];

        $totalErrors = $incorrectChars + $missingChars + $extraChars;

        // 3. Word-Level Analysis
        $wordAnalysis = self::analyzeWords($reference, $typed);

        // 4. Duration & Speed Calculations
        $effectiveSeconds = max(1, $durationSeconds);
        $minutes = $effectiveSeconds / 60.0;

        if ($typedLen === 0) {
            $grossWpm = 0.00;
            $netWpm = 0.00;
            $accuracy = 0.00;
            $errorsPerMin = 0.00;
            $finalScore = 0.00;
        } else {
            // Standard Gross WPM = (Total Typed Characters / 5) / Minutes
            $grossWpm = round(($typedLen / 5.0) / $minutes, 2);

            // Errors Per Minute (based on actual errors made during typing)
            $errorsPerMin = round($totalErrors / $minutes, 2);

            // Net WPM = Gross WPM - Errors Per Minute (never below 0)
            $netWpm = max(0.00, round($grossWpm - $errorsPerMin, 2));

            // Accuracy % = Correct Characters / Evaluated Attempted Characters * 100
            $evaluatedChars = max(1, $correctChars + $incorrectChars + $missingChars + $extraChars);
            $accuracy = max(0.00, min(100.00, round(($correctChars / $evaluatedChars) * 100, 2)));

            // If completely identical
            if ($typed === $reference) {
                $accuracy = 100.00;
            }

            // Final Score = Net WPM * (Accuracy / 100)
            $finalScore = round($netWpm * ($accuracy / 100.0), 2);
        }

        // 5. Qualification Status
        $minAccuracy = isset($settings['passing_accuracy']) ? (float)$settings['passing_accuracy'] : 90.00;
        $minWpm = isset($settings['passing_wpm']) ? (float)$settings['passing_wpm'] : 0.00;

        $isQualified = ($accuracy >= $minAccuracy) && ($netWpm >= $minWpm) && ($typedLen > 0);
        $qualificationStatus = $isQualified ? 'qualified' : 'not_qualified';

        return [
            'gross_wpm'               => $grossWpm,
            'net_wpm'                 => $netWpm,
            'accuracy'                => $accuracy,
            'final_score'             => $finalScore,
            'total_typed_characters'  => $typedLen,
            'reference_characters'    => $refLen,
            'correct_characters'      => $correctChars,
            'incorrect_characters'    => $incorrectChars,
            'missing_characters'      => $missingChars,
            'unreached_characters'    => $unreachedChars,
            'extra_characters'        => $extraChars,
            'total_errors'            => $totalErrors,
            'errors_per_minute'       => $errorsPerMin,
            'backspace_count'         => max(0, $backspaceCount),
            'scoring_duration_seconds'=> $effectiveSeconds,
            'total_typed_words'       => $wordAnalysis['total_typed_words'],
            'reference_words'         => $wordAnalysis['reference_words'],
            'correct_words'           => $wordAnalysis['correct_words'],
            'incorrect_words'         => $wordAnalysis['incorrect_words'],
            'missing_words'           => $wordAnalysis['missing_words'],
            'extra_words'             => $wordAnalysis['extra_words'],
            'qualification_status'    => $qualificationStatus,
            'pass_fail'               => $isQualified ? 'pass' : 'fail',
            'scoring_version'         => self::SCORING_VERSION,
            'alignment_details'       => $charAlignment['aligned_pairs'],
        ];
    }

    /**
     * Performs dynamic programming sequence alignment between reference and typed text.
     * Uses prefix-aware edit distance backtracking to classify each position without penalizing
     * unreached trailing text as errors.
     *
     * @param string $reference
     * @param string $typed
     * @return array
     */
    public static function alignCharacters(string $reference, string $typed): array {
        $refChars = self::mbStringToArray($reference);
        $typedChars = self::mbStringToArray($typed);

        $m = count($refChars);
        $n = count($typedChars);

        // Edge case: Empty typed text
        if ($n === 0) {
            return [
                'correct'       => 0,
                'incorrect'     => 0,
                'missing'       => 0,
                'unreached'     => $m,
                'extra'         => 0,
                'aligned_pairs' => array_map(fn($c) => ['type' => 'missing', 'ref' => $c, 'typed' => null], $refChars),
            ];
        }

        // Edge case: Empty reference text
        if ($m === 0) {
            return [
                'correct'       => 0,
                'incorrect'     => 0,
                'missing'       => 0,
                'unreached'     => 0,
                'extra'         => $n,
                'aligned_pairs' => array_map(fn($c) => ['type' => 'extra', 'ref' => null, 'typed' => $c], $typedChars),
            ];
        }

        // DP matrix initialization
        // dp[i][j] represents the minimum edit cost between ref[0..i-1] and typed[0..j-1]
        $dp = [];
        for ($i = 0; $i <= $m; $i++) {
            $dp[$i] = array_fill(0, $n + 1, 0);
            $dp[$i][0] = $i;
        }
        for ($j = 0; $j <= $n; $j++) {
            $dp[0][$j] = $j;
        }

        for ($i = 1; $i <= $m; $i++) {
            for ($j = 1; $j <= $n; $j++) {
                if ($refChars[$i - 1] === $typedChars[$j - 1]) {
                    $dp[$i][$j] = $dp[$i - 1][$j - 1]; // Match (cost 0)
                } else {
                    $dp[$i][$j] = 1 + min(
                        $dp[$i - 1][$j - 1], // Substitution / Incorrect
                        $dp[$i - 1][$j],     // Deletion / Missing from typed
                        $dp[$i][$j - 1]      // Insertion / Extra in typed
                    );
                }
            }
        }

        // Determine the optimal reference endpoint for the typed string length
        if ($n >= $m || $m <= $n + 2) {
            $bestI = $m;
        } else {
            $minCost = PHP_INT_MAX;
            $bestI = $m;
            $searchMin = max(1, $n - 10);
            $searchMax = min($m, max($n + 15, (int)($n * 1.35)));
            if ($m <= $n + 15) {
                $searchMax = $m;
            }

            // Search from searchMax down to searchMin to favor maximal reference match on equal cost
            for ($i = $searchMax; $i >= $searchMin; $i--) {
                if ($dp[$i][$n] < $minCost) {
                    $minCost = $dp[$i][$n];
                    $bestI = $i;
                }
            }
        }

        // Backtrack from (bestI, n)
        $aligned = [];
        $i = $bestI;
        $j = $n;

        $correct = 0;
        $incorrect = 0;
        $missing = 0;
        $extra = 0;

        while ($i > 0 || $j > 0) {
            if ($i > 0 && $j > 0 && $refChars[$i - 1] === $typedChars[$j - 1] && $dp[$i][$j] === $dp[$i - 1][$j - 1]) {
                $aligned[] = ['type' => 'correct', 'ref' => $refChars[$i - 1], 'typed' => $typedChars[$j - 1]];
                $correct++;
                $i--;
                $j--;
            } elseif ($i > 0 && $j > 0 && $dp[$i][$j] === $dp[$i - 1][$j - 1] + 1) {
                $aligned[] = ['type' => 'incorrect', 'ref' => $refChars[$i - 1], 'typed' => $typedChars[$j - 1]];
                $incorrect++;
                $i--;
                $j--;
            } elseif ($j > 0 && $dp[$i][$j] === $dp[$i][$j - 1] + 1) {
                $aligned[] = ['type' => 'extra', 'ref' => null, 'typed' => $typedChars[$j - 1]];
                $extra++;
                $j--;
            } elseif ($i > 0 && $dp[$i][$j] === $dp[$i - 1][$j] + 1) {
                $aligned[] = ['type' => 'missing', 'ref' => $refChars[$i - 1], 'typed' => null];
                $missing++;
                $i--;
            } else {
                // Fallback tie-break
                if ($i > 0 && $j > 0) {
                    $aligned[] = ['type' => 'incorrect', 'ref' => $refChars[$i - 1], 'typed' => $typedChars[$j - 1]];
                    $incorrect++;
                    $i--;
                    $j--;
                } elseif ($i > 0) {
                    $aligned[] = ['type' => 'missing', 'ref' => $refChars[$i - 1], 'typed' => null];
                    $missing++;
                    $i--;
                } else {
                    $aligned[] = ['type' => 'extra', 'ref' => null, 'typed' => $typedChars[$j - 1]];
                    $extra++;
                    $j--;
                }
            }
        }

        // Reverse backtracked array to preserve left-to-right order
        $aligned = array_reverse($aligned);

        // Append unreached remaining reference characters for complete text visual diff
        $unreached = $m - $bestI;
        for ($k = $bestI; $k < $m; $k++) {
            $aligned[] = ['type' => 'missing', 'ref' => $refChars[$k], 'typed' => null];
        }

        return [
            'correct'       => $correct,
            'incorrect'     => $incorrect,
            'missing'       => $missing,
            'unreached'     => $unreached,
            'extra'         => $extra,
            'aligned_pairs' => $aligned,
        ];
    }

    /**
     * Analyzes words using word-level tokenization and dynamic programming alignment.
     *
     * @param string $reference
     * @param string $typed
     * @return array
     */
    public static function analyzeWords(string $reference, string $typed): array {
        $refTrim = trim($reference);
        $typedTrim = trim($typed);

        $refWords = $refTrim === '' ? [] : preg_split('/\s+/', $refTrim);
        $typedWords = $typedTrim === '' ? [] : preg_split('/\s+/', $typedTrim);

        $m = count($refWords);
        $n = count($typedWords);

        if ($n === 0) {
            return [
                'reference_words'   => $m,
                'total_typed_words' => 0,
                'correct_words'     => 0,
                'incorrect_words'   => 0,
                'missing_words'     => $m,
                'extra_words'       => 0,
            ];
        }

        // DP matrix for word alignment
        $dp = [];
        for ($i = 0; $i <= $m; $i++) {
            $dp[$i] = array_fill(0, $n + 1, 0);
            $dp[$i][0] = $i;
        }
        for ($j = 0; $j <= $n; $j++) {
            $dp[0][$j] = $j;
        }

        for ($i = 1; $i <= $m; $i++) {
            for ($j = 1; $j <= $n; $j++) {
                if ($refWords[$i - 1] === $typedWords[$j - 1]) {
                    $dp[$i][$j] = $dp[$i - 1][$j - 1];
                } else {
                    $dp[$i][$j] = 1 + min(
                        $dp[$i - 1][$j - 1], // Word substitution
                        $dp[$i - 1][$j],     // Word omitted
                        $dp[$i][$j - 1]      // Word inserted
                    );
                }
            }
        }

        $i = $m;
        $j = $n;
        $correct = 0;
        $incorrect = 0;
        $missing = 0;
        $extra = 0;

        while ($i > 0 || $j > 0) {
            if ($i > 0 && $j > 0 && $refWords[$i - 1] === $typedWords[$j - 1] && $dp[$i][$j] === $dp[$i - 1][$j - 1]) {
                $correct++;
                $i--;
                $j--;
            } elseif ($i > 0 && $j > 0 && $dp[$i][$j] === $dp[$i - 1][$j - 1] + 1) {
                $incorrect++;
                $i--;
                $j--;
            } elseif ($j > 0 && $dp[$i][$j] === $dp[$i][$j - 1] + 1) {
                $extra++;
                $j--;
            } elseif ($i > 0 && $dp[$i][$j] === $dp[$i - 1][$j] + 1) {
                $missing++;
                $i--;
            } else {
                if ($i > 0 && $j > 0) {
                    $incorrect++;
                    $i--;
                    $j--;
                } elseif ($i > 0) {
                    $missing++;
                    $i--;
                } else {
                    $extra++;
                    $j--;
                }
            }
        }

        return [
            'reference_words'   => $m,
            'total_typed_words' => $n,
            'correct_words'     => $correct,
            'incorrect_words'   => $incorrect,
            'missing_words'     => $missing,
            'extra_words'       => $extra,
        ];
    }

    /**
     * Splits a multi-byte UTF-8 string into an array of individual characters.
     *
     * @param string $str
     * @return array
     */
    public static function mbStringToArray(string $str): array {
        if ($str === '') return [];
        return preg_split('//u', $str, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }
}
