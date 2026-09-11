<?php
/**
 * Lightweight AJAX endpoint: categories by subject
 */
header('Content-Type: application/json');

$subjectId = (int)($_GET['subject_id'] ?? 0);
if ($subjectId <= 0) {
    echo json_encode([]);
    exit;
}

$categories = QuestionService::getCategoriesBySubject($subjectId);
echo json_encode(array_map(function($c) {
    return [
        'id'   => (int)$c['id'],
        'name' => $c['name'],
    ];
}, $categories));
exit;

