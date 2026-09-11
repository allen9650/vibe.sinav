<?php
/**
 * Candidate Management — Bulk CSV Import
 */

// Download sample template handler
if (isset($_GET['download_sample']) && $_GET['download_sample'] == '1') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=candidates_sample_template.csv');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['roll_number', 'seat_number', 'full_name', 'father_name', 'mobile_number', 'gender', 'course', 'batch', 'shift', 'branch']);
    fputcsv($output, ['101', 'S-01', 'Ali Ahmed', 'Muhammad Ahmed', '03001234567', 'male', 'CIT', '2026-A', 'morning', 'Main Campus']);
    fputcsv($output, ['102', 'S-02', 'Fatima Noor', 'Ghulam Nabi', '03017654321', 'female', 'Typing', '2026-A', 'afternoon', 'Main Campus']);
    fputcsv($output, ['103', 'S-03', 'Bilal Khan', 'Tariq Khan', '03339876543', 'male', 'DIT', '2026-A', 'evening', 'Main Campus']);
    fclose($output);
    exit;
}

$competitions = Database::fetchAll("SELECT id, name, code, competition_date FROM competitions WHERE status != 'cancelled' ORDER BY competition_date DESC, id DESC");
$selectedCompId = (int)($_GET['competition_id'] ?? ($_POST['competition_id'] ?? 0));
if ($selectedCompId === 0 && !empty($competitions)) {
    $selectedCompId = (int)$competitions[0]['id'];
}

$summary = null;
$importErrors = [];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    CSRF::validateOrFail();

    $competitionId = (int)($_POST['competition_id'] ?? 0);
    $comp = Database::fetch("SELECT * FROM competitions WHERE id = ?", [$competitionId]);

    if (!$comp) {
        Session::flash('error', 'Selected competition does not exist.');
        redirectTo('candidates-import');
    }

    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        $importErrors[] = 'Please select a valid CSV file to upload.';
    } else {
        $file = $_FILES['csv_file'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if ($ext !== 'csv') {
            $importErrors[] = 'Invalid file extension. Please upload a .csv file.';
        } else {
            $handle = fopen($file['tmp_name'], 'r');
            if ($handle === false) {
                $importErrors[] = 'Failed to read uploaded CSV file.';
            } else {
                // Read header row
                $header = fgetcsv($handle);
                if (!$header) {
                    $importErrors[] = 'The CSV file is empty.';
                } else {
                    // Normalize headers
                    $normalizedHeader = array_map(function($h) {
                        return strtolower(trim(str_replace([' ', '-', '/'], '_', $h)));
                    }, $header);

                    // Required column: full_name
                    if (!in_array('full_name', $normalizedHeader) && !in_array('name', $normalizedHeader) && !in_array('candidate_name', $normalizedHeader)) {
                        $importErrors[] = "Missing required 'full_name' or 'name' column in CSV.";
                    } else {
                        // Find column indices
                        $colIndex = [];
                        foreach ($normalizedHeader as $idx => $name) {
                            if ($name === 'full_name' || $name === 'name' || $name === 'candidate_name') $colIndex['full_name'] = $idx;
                            if ($name === 'roll_number' || $name === 'roll_no' || $name === 'roll') $colIndex['roll_number'] = $idx;
                            if ($name === 'seat_number' || $name === 'seat_no' || $name === 'seat') $colIndex['seat_number'] = $idx;
                            if ($name === 'father_name' || $name === 'father') $colIndex['father_name'] = $idx;
                            if ($name === 'mobile_number' || $name === 'phone' || $name === 'mobile') $colIndex['phone'] = $idx;
                            if ($name === 'gender') $colIndex['gender'] = $idx;
                            if ($name === 'course') $colIndex['course'] = $idx;
                            if ($name === 'batch') $colIndex['batch'] = $idx;
                            if ($name === 'shift') $colIndex['shift'] = $idx;
                            if ($name === 'branch') $colIndex['branch'] = $idx;
                        }

                        $year = date('Y', strtotime($comp['competition_date']));
                        $prefix = "MITC-{$year}-";

                        // Get latest reg number sequence
                        $lastReg = Database::fetchColumn(
                            "SELECT registration_number FROM candidates WHERE registration_number LIKE ? ORDER BY id DESC LIMIT 1",
                            [$prefix . '%']
                        );
                        $currentSeq = $lastReg ? (int)substr($lastReg, strlen($prefix)) : 0;

                        // Existing roll numbers in this competition to avoid duplicates
                        $existingRolls = Database::fetchAll(
                            "SELECT roll_number FROM candidates WHERE competition_id = ? AND roll_number IS NOT NULL",
                            [$competitionId]
                        );
                        $rollSet = [];
                        foreach ($existingRolls as $r) {
                            $rollSet[strtoupper(trim($r['roll_number']))] = true;
                        }

                        $totalRows = 0;
                        $importedCount = 0;
                        $skippedDuplicates = 0;
                        $failedRows = [];

                        Database::beginTransaction();
                        try {
                            $rowNum = 1;
                            while (($data = fgetcsv($handle)) !== false) {
                                $rowNum++;
                                // Skip completely blank lines
                                if (empty(array_filter($data, fn($v) => trim($v) !== ''))) {
                                    continue;
                                }

                                $totalRows++;

                                $fullName = isset($colIndex['full_name']) ? trim($data[$colIndex['full_name']] ?? '') : '';
                                if ($fullName === '') {
                                    $failedRows[] = "Row {$rowNum}: Full name is missing.";
                                    continue;
                                }

                                $rollNumber = isset($colIndex['roll_number']) ? trim($data[$colIndex['roll_number']] ?? '') : '';
                                if ($rollNumber !== '') {
                                    $normRoll = strtoupper($rollNumber);
                                    if (isset($rollSet[$normRoll])) {
                                        $skippedDuplicates++;
                                        $failedRows[] = "Row {$rowNum}: Duplicate roll number '{$rollNumber}' skipped.";
                                        continue;
                                    }
                                    $rollSet[$normRoll] = true;
                                }

                                $seatNumber = isset($colIndex['seat_number']) ? trim($data[$colIndex['seat_number']] ?? '') : null;
                                $fatherName = isset($colIndex['father_name']) ? trim($data[$colIndex['father_name']] ?? '') : null;
                                $phone = isset($colIndex['phone']) ? trim($data[$colIndex['phone']] ?? '') : null;
                                $gender = isset($colIndex['gender']) ? strtolower(trim($data[$colIndex['gender']] ?? '')) : null;
                                if (!in_array($gender, ['male', 'female', 'other'], true)) {
                                    $gender = null;
                                }

                                $course = isset($colIndex['course']) ? trim($data[$colIndex['course']] ?? '') : null;
                                $batch = isset($colIndex['batch']) ? trim($data[$colIndex['batch']] ?? '') : null;
                                $shift = isset($colIndex['shift']) ? strtolower(trim($data[$colIndex['shift']] ?? '')) : 'morning';
                                if (!in_array($shift, ['morning', 'afternoon', 'evening'], true)) {
                                    $shift = 'morning';
                                }
                                $branch = isset($colIndex['branch']) ? trim($data[$colIndex['branch']] ?? '') : null;

                                $currentSeq++;
                                $newRegNum = $prefix . str_pad((string)$currentSeq, 4, '0', STR_PAD_LEFT);

                                Database::insert('candidates', [
                                    'competition_id'      => $competitionId,
                                    'registration_number' => $newRegNum,
                                    'roll_number'         => $rollNumber ?: null,
                                    'seat_number'         => $seatNumber ?: null,
                                    'full_name'           => $fullName,
                                    'father_name'         => $fatherName ?: null,
                                    'phone'               => $phone ?: null,
                                    'gender'              => $gender,
                                    'course'              => $course ?: null,
                                    'batch'               => $batch ?: null,
                                    'shift'               => $shift,
                                    'branch'              => $branch ?: null,
                                    'status'              => 'registered',
                                    'registered_by'       => Session::get('user_id'),
                                ]);

                                $importedCount++;
                            }

                            Database::commit();

                            $summary = [
                                'total'      => $totalRows,
                                'imported'   => $importedCount,
                                'duplicates' => $skippedDuplicates,
                                'failed'     => count($failedRows),
                                'errors'     => $failedRows,
                            ];

                            AuditLog::log('candidates_bulk_imported', 'candidates', 
                                "Bulk imported {$importedCount} candidates into competition #{$competitionId} ({$comp['name']})", 
                                Session::get('user_id'), 
                                null, 
                                ['competition_id' => $competitionId, 'total_rows' => $totalRows, 'imported' => $importedCount]
                            );

                            Session::flash('success', "Import complete: {$importedCount} candidates imported successfully.");
                        } catch (Exception $e) {
                            Database::rollback();
                            $importErrors[] = 'Database error during import: ' . $e->getMessage();
                        }
                    }
                }
                fclose($handle);
            }
        }
    }
}
?>

<div class="dashboard-header mb-4">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1 class="page-title">
                <i class="fas fa-file-import me-2 text-info"></i> Bulk Candidate Import
            </h1>
            <p class="page-subtitle">Upload CSV spreadsheet to register multiple candidates in bulk</p>
        </div>
        <div>
            <a href="<?= url('candidates') ?>" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i> Back to Candidates
            </a>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="content-card mb-4">
            <div class="card-header-custom">
                <h5><i class="fas fa-upload me-2"></i> Upload CSV File</h5>
            </div>
            <div class="card-body-custom">
                <?php if (!empty($importErrors)): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <ul class="mb-0 ps-3">
                            <?php foreach ($importErrors as $err): ?>
                                <li><?= e($err) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="POST" action="<?= url('candidates-import') ?>" enctype="multipart/form-data" id="importForm">
                    <?= CSRF::field() ?>

                    <div class="mb-3">
                        <label for="competition_id" class="form-label fw-bold">Target Competition <span class="text-danger">*</span></label>
                        <select class="form-select" id="competition_id" name="competition_id" required>
                            <?php foreach ($competitions as $cmp): ?>
                                <option value="<?= $cmp['id'] ?>" <?= $selectedCompId === (int)$cmp['id'] ? 'selected' : '' ?>>
                                    <?= e($cmp['name']) ?> (<?= e($cmp['code']) ?>) — <?= formatDate($cmp['competition_date']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label for="csv_file" class="form-label fw-bold">Choose CSV File <span class="text-danger">*</span></label>
                        <input type="file" class="form-control" id="csv_file" name="csv_file" accept=".csv" required>
                        <small class="text-muted">Upload a standard comma-delimited (.csv) file.</small>
                    </div>

                    <div class="d-flex justify-content-between align-items-center border-top border-secondary pt-3">
                        <a href="<?= url('candidates-import') ?>&download_sample=1" class="btn btn-outline-info btn-sm">
                            <i class="fas fa-download me-1"></i> Download Sample CSV Template
                        </a>

                        <button type="submit" class="btn btn-primary" id="btnUploadCsv">
                            <i class="fas fa-file-import me-1"></i> Start Import
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($summary): ?>
            <!-- Import Results Summary -->
            <div class="content-card">
                <div class="card-header-custom d-flex justify-content-between align-items-center">
                    <h5><i class="fas fa-chart-pie me-2"></i> Import Results Summary</h5>
                </div>
                <div class="card-body-custom">
                    <div class="row g-3 text-center mb-3">
                        <div class="col-3">
                            <div class="p-2 bg-dark rounded border border-secondary">
                                <div class="h4 mb-0 text-light"><?= $summary['total'] ?></div>
                                <div class="small text-muted">Total Rows</div>
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="p-2 bg-dark rounded border border-success">
                                <div class="h4 mb-0 text-success"><?= $summary['imported'] ?></div>
                                <div class="small text-muted">Imported</div>
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="p-2 bg-dark rounded border border-warning">
                                <div class="h4 mb-0 text-warning"><?= $summary['duplicates'] ?></div>
                                <div class="small text-muted">Duplicates</div>
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="p-2 bg-dark rounded border border-danger">
                                <div class="h4 mb-0 text-danger"><?= $summary['failed'] ?></div>
                                <div class="small text-muted">Skipped/Failed</div>
                            </div>
                        </div>
                    </div>

                    <?php if (!empty($summary['errors'])): ?>
                        <div class="mt-3">
                            <h6 class="text-warning small mb-2"><i class="fas fa-exclamation-circle me-1"></i> Row Details & Warnings:</h6>
                            <div class="bg-dark p-3 rounded border border-secondary font-monospace small" style="max-height: 200px; overflow-y: auto;">
                                <?php foreach ($summary['errors'] as $msg): ?>
                                    <div class="text-muted mb-1"><?= e($msg) ?></div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="mt-3 text-end">
                        <a href="<?= url('candidates') ?>&competition_id=<?= $selectedCompId ?>" class="btn btn-sm btn-primary">
                            View Imported Candidates <i class="fas fa-arrow-right ms-1"></i>
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Instructions & Column Guidelines -->
    <div class="col-lg-5">
        <div class="content-card">
            <div class="card-header-custom">
                <h5><i class="fas fa-book me-2"></i> CSV Column Guidelines</h5>
            </div>
            <div class="card-body-custom">
                <p class="small text-muted">Your CSV file should contain the following column headers:</p>
                <div class="table-responsive">
                    <table class="table table-sm table-dark table-bordered small mb-0">
                        <thead>
                            <tr>
                                <th>Column</th>
                                <th>Required</th>
                                <th>Sample Value</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><code>full_name</code></td>
                                <td><span class="badge bg-danger">Required</span></td>
                                <td>Ali Ahmed</td>
                            </tr>
                            <tr>
                                <td><code>roll_number</code></td>
                                <td><span class="badge bg-secondary">Optional</span></td>
                                <td>101</td>
                            </tr>
                            <tr>
                                <td><code>seat_number</code></td>
                                <td><span class="badge bg-secondary">Optional</span></td>
                                <td>S-01</td>
                            </tr>
                            <tr>
                                <td><code>father_name</code></td>
                                <td><span class="badge bg-secondary">Optional</span></td>
                                <td>Muhammad Ahmed</td>
                            </tr>
                            <tr>
                                <td><code>mobile_number</code></td>
                                <td><span class="badge bg-secondary">Optional</span></td>
                                <td>03001234567</td>
                            </tr>
                            <tr>
                                <td><code>gender</code></td>
                                <td><span class="badge bg-secondary">Optional</span></td>
                                <td>male / female</td>
                            </tr>
                            <tr>
                                <td><code>course</code></td>
                                <td><span class="badge bg-secondary">Optional</span></td>
                                <td>CIT / DIT / Typing</td>
                            </tr>
                            <tr>
                                <td><code>batch</code></td>
                                <td><span class="badge bg-secondary">Optional</span></td>
                                <td>2026-A</td>
                            </tr>
                            <tr>
                                <td><code>shift</code></td>
                                <td><span class="badge bg-secondary">Optional</span></td>
                                <td>morning / afternoon</td>
                            </tr>
                            <tr>
                                <td><code>branch</code></td>
                                <td><span class="badge bg-secondary">Optional</span></td>
                                <td>Main Campus</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="alert alert-warning mt-3 mb-0 small">
                    <i class="fas fa-lightbulb me-1"></i>
                    <strong>Tip:</strong> Registration numbers are generated automatically in the format <code>MITC-YYYY-XXXX</code>. Duplicate roll numbers within the same competition will be reported and skipped automatically.
                </div>
            </div>
        </div>
    </div>
</div>
