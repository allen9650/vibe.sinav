<?php
declare(strict_types=1);

/**
 * PTM Assessment System — Assessment Results CSV Export Handler
 * Streams RFC 4180 CSV with UTF-8 BOM directly to browser.
 */

$assessmentId = (int)($_GET['id'] ?? 0);
if ($assessmentId <= 0) {
    Session::flash('error', 'Valid assessment ID is required.');
    redirectTo('assessments');
}

ExportService::exportResultsToCSV($assessmentId);

