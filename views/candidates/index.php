<?php
/**
 * Candidate Management — List View
 */

$search = trim($_GET['search'] ?? '');
$competitionId = (int)($_GET['competition_id'] ?? 0);
$course = trim($_GET['course'] ?? '');
$shift = trim($_GET['shift'] ?? '');
$branch = trim($_GET['branch'] ?? '');
$status = trim($_GET['status'] ?? '');
$currentPageNum = max(1, (int)($_GET['p'] ?? 1));
$perPage = 15;

// Handle Safe Deletion
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    CSRF::validateOrFail();

    if (!Auth::hasPermission('candidates.delete')) {
        Session::flash('error', 'Unauthorized action.');
        redirectTo('candidates');
    }

    $candidateId = (int)($_POST['candidate_id'] ?? 0);
    $candidate = Database::fetch("SELECT * FROM candidates WHERE id = ?", [$candidateId]);

    if (!$candidate) {
        Session::flash('error', 'Candidate not found.');
        redirectTo('candidates');
    }

    // Safety check: verify if candidate has any test attempts or completed results
    $attemptsCount = (int)Database::fetchColumn("SELECT COUNT(*) FROM test_attempts WHERE candidate_id = ?", [$candidateId]);
    $resultsCount = (int)Database::fetchColumn("SELECT COUNT(*) FROM test_results WHERE candidate_id = ?", [$candidateId]);

    if ($attemptsCount > 0 || $resultsCount > 0) {
        Session::flash('error', "Cannot delete candidate '{$candidate['full_name']}' because they have {$attemptsCount} test attempt(s) recorded. You may set status to 'Disqualified' instead.");
    } else {
        // Delete uploaded photo if exists
        if ($candidate['photo'] && file_exists(UPLOADS_PATH . '/' . $candidate['photo'])) {
            @unlink(UPLOADS_PATH . '/' . $candidate['photo']);
        }

        // Delete attendance records linked to candidate
        Database::delete('attendance', 'candidate_id = ?', [$candidateId]);
        Database::delete('candidates', 'id = ?', [$candidateId]);

        AuditLog::log('candidate_deleted', 'candidates', 
            "Deleted candidate '{$candidate['full_name']}' ({$candidate['registration_number']})", 
            Session::get('user_id'), 
            $candidate, 
            null
        );

        Session::flash('success', "Candidate '{$candidate['full_name']}' deleted successfully.");
    }

    CSRF::regenerate();
    redirectTo('candidates' . ($competitionId ? "&competition_id={$competitionId}" : ''));
}

// Fetch filter options
$competitionsList = Database::fetchAll("SELECT id, name, code FROM competitions ORDER BY competition_date DESC, id DESC");
$coursesList = Database::fetchAll("SELECT DISTINCT course FROM candidates WHERE course IS NOT NULL AND course != '' ORDER BY course");
$shiftsList = ['morning' => 'Morning', 'afternoon' => 'Afternoon', 'evening' => 'Evening'];
$branchesList = Database::fetchAll("SELECT DISTINCT branch FROM candidates WHERE branch IS NOT NULL AND branch != '' ORDER BY branch");

// Build query
$where = ['1=1'];
$params = [];

if ($search !== '') {
    $where[] = '(c.full_name LIKE ? OR c.father_name LIKE ? OR c.roll_number LIKE ? OR c.registration_number LIKE ? OR c.cnic LIKE ? OR c.phone LIKE ?)';
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

if ($competitionId > 0) {
    $where[] = 'c.competition_id = ?';
    $params[] = $competitionId;
}

if ($course !== '') {
    $where[] = 'c.course = ?';
    $params[] = $course;
}

if ($shift !== '') {
    $where[] = 'c.shift = ?';
    $params[] = $shift;
}

if ($branch !== '') {
    $where[] = 'c.branch = ?';
    $params[] = $branch;
}

if ($status !== '') {
    $where[] = 'c.status = ?';
    $params[] = $status;
}

$whereClause = implode(' AND ', $where);

// Total count
$totalCount = (int)Database::fetchColumn(
    "SELECT COUNT(*) FROM candidates c WHERE {$whereClause}",
    $params
);

$totalPages = max(1, (int)ceil($totalCount / $perPage));
$offset = ($currentPageNum - 1) * $perPage;

// Fetch candidates with competition info & attendance
$queryParams = array_merge($params, [$perPage, $offset]);
$candidates = Database::fetchAll(
    "SELECT c.*, cmp.name as competition_name, cmp.code as competition_code,
            att.status as attendance_status, att.check_in_time
     FROM candidates c
     JOIN competitions cmp ON c.competition_id = cmp.id
     LEFT JOIN attendance att ON c.id = att.candidate_id AND att.competition_id = c.competition_id
     WHERE {$whereClause}
     ORDER BY c.id DESC
     LIMIT ? OFFSET ?",
    $queryParams
);
?>

<div class="dashboard-header d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
    <div>
        <h1 class="page-title">
            <i class="fas fa-users me-2 text-info"></i> Candidate Management
        </h1>
        <p class="page-subtitle">Register, manage, filter, and track competition candidates</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <?php if (Auth::hasPermission('candidates.create')): ?>
            <a href="<?= url('candidates-create') ?><?= $competitionId ? '&competition_id=' . $competitionId : '' ?>" class="btn btn-primary" id="btnRegisterCandidate">
                <i class="fas fa-user-plus me-1"></i> Register Candidate
            </a>
        <?php endif; ?>

        <?php if (Auth::hasPermission('candidates.import')): ?>
            <a href="<?= url('candidates-import') ?><?= $competitionId ? '&competition_id=' . $competitionId : '' ?>" class="btn btn-outline-info" id="btnImportCandidates">
                <i class="fas fa-file-import me-1"></i> CSV Import
            </a>
        <?php endif; ?>

        <a href="<?= url('candidates-print') ?><?= $competitionId ? '&competition_id=' . $competitionId : '' ?>" target="_blank" class="btn btn-outline-secondary">
            <i class="fas fa-print me-1"></i> Print List
        </a>
    </div>
</div>

<!-- Filter Box -->
<div class="content-card mb-4">
    <div class="card-body-custom">
        <form method="GET" class="row g-3 align-items-end" id="candidateFilterForm">
            <input type="hidden" name="page" value="candidates">

            <div class="col-md-3">
                <label class="form-label small text-muted">Search</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                    <input type="text" class="form-control" name="search" placeholder="Name, roll #, reg #, phone..." value="<?= e($search) ?>">
                </div>
            </div>

            <div class="col-md-3">
                <label class="form-label small text-muted">Competition</label>
                <select class="form-select" name="competition_id">
                    <option value="">All Competitions</option>
                    <?php foreach ($competitionsList as $cmp): ?>
                        <option value="<?= $cmp['id'] ?>" <?= $competitionId === (int)$cmp['id'] ? 'selected' : '' ?>>
                            <?= e($cmp['name']) ?> (<?= e($cmp['code']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label small text-muted">Course</label>
                <select class="form-select" name="course">
                    <option value="">All Courses</option>
                    <?php foreach ($coursesList as $cr): ?>
                        <option value="<?= e($cr['course']) ?>" <?= $course === $cr['course'] ? 'selected' : '' ?>>
                            <?= e($cr['course']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label small text-muted">Shift</label>
                <select class="form-select" name="shift">
                    <option value="">All Shifts</option>
                    <?php foreach ($shiftsList as $val => $lbl): ?>
                        <option value="<?= $val ?>" <?= $shift === $val ? 'selected' : '' ?>><?= $lbl ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label small text-muted">Status</label>
                <select class="form-select" name="status">
                    <option value="">All Statuses</option>
                    <option value="registered" <?= $status === 'registered' ? 'selected' : '' ?>>Registered</option>
                    <option value="present" <?= $status === 'present' ? 'selected' : '' ?>>Present</option>
                    <option value="absent" <?= $status === 'absent' ? 'selected' : '' ?>>Absent</option>
                    <option value="test_started" <?= $status === 'test_started' ? 'selected' : '' ?>>Test Started</option>
                    <option value="completed" <?= $status === 'completed' ? 'selected' : '' ?>>Completed</option>
                    <option value="disqualified" <?= $status === 'disqualified' ? 'selected' : '' ?>>Disqualified</option>
                </select>
            </div>

            <div class="col-12 d-flex justify-content-end gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-filter me-1"></i> Filter
                </button>
                <a href="<?= url('candidates') ?>" class="btn btn-outline-secondary">
                    <i class="fas fa-redo me-1"></i> Reset
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Candidate List Table -->
<div class="content-card">
    <div class="card-header-custom d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            <i class="fas fa-list me-2"></i> Candidates List
            <span class="badge bg-secondary ms-2"><?= $totalCount ?> records</span>
        </h5>
    </div>
    <div class="card-body-custom p-0">
        <?php if (empty($candidates)): ?>
            <div class="text-center text-muted py-5">
                <i class="fas fa-user-slash fa-3x mb-3 text-secondary"></i>
                <p class="mb-2">No candidates found matching your criteria.</p>
                <?php if (Auth::hasPermission('candidates.create')): ?>
                    <a href="<?= url('candidates-create') ?>" class="btn btn-sm btn-primary">
                        <i class="fas fa-user-plus me-1"></i> Register a Candidate
                    </a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-dark table-hover align-middle mb-0" id="candidatesTable">
                    <thead>
                        <tr>
                            <th width="50">Photo</th>
                            <th>Reg #</th>
                            <th>Roll #</th>
                            <th>Candidate Name</th>
                            <th>Father Name</th>
                            <th>Course / Shift</th>
                            <th>Competition</th>
                            <th class="text-center">Attendance</th>
                            <th class="text-center">Status</th>
                            <th width="120" class="text-end pe-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($candidates as $c): ?>
                            <tr>
                                <td>
                                    <?php if (!empty($c['photo'])): ?>
                                        <img src="<?= upload(e($c['photo'])) ?>" alt="Photo" class="rounded-circle object-fit-cover" style="width: 38px; height: 38px; border: 1px solid var(--border-color);">
                                    <?php else: ?>
                                        <div class="rounded-circle d-flex align-items-center justify-content-center bg-secondary text-light fw-bold" style="width: 38px; height: 38px; font-size: 14px;">
                                            <?= strtoupper(substr($c['full_name'], 0, 1)) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="font-monospace text-info small fw-bold"><?= e($c['registration_number']) ?></span>
                                </td>
                                <td>
                                    <span class="badge bg-dark border border-secondary"><?= e($c['roll_number'] ?: '—') ?></span>
                                    <?php if ($c['seat_number']): ?>
                                        <div class="small text-muted">Seat: <?= e($c['seat_number']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="fw-bold text-light"><?= e($c['full_name']) ?></div>
                                    <?php if ($c['phone']): ?>
                                        <div class="small text-muted"><i class="fas fa-phone me-1"></i> <?= e($c['phone']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><?= e($c['father_name'] ?: '—') ?></td>
                                <td>
                                    <div><?= e($c['course'] ?: '—') ?></div>
                                    <div class="small text-muted">
                                        <?= e(ucfirst($c['shift'] ?? '')) ?>
                                        <?= $c['branch'] ? ' • ' . e($c['branch']) : '' ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="small text-truncate" style="max-width: 180px;" title="<?= e($c['competition_name']) ?>">
                                        <?= e($c['competition_name']) ?>
                                    </div>
                                    <div class="small font-monospace text-muted"><?= e($c['competition_code']) ?></div>
                                </td>
                                <td class="text-center">
                                    <?php if ($c['attendance_status'] === 'present'): ?>
                                        <span class="badge bg-success"><i class="fas fa-check me-1"></i> Present</span>
                                    <?php elseif ($c['attendance_status'] === 'absent'): ?>
                                        <span class="badge bg-danger"><i class="fas fa-times me-1"></i> Absent</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Unmarked</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php
                                    $stBadge = match($c['status']) {
                                        'present' => 'bg-success',
                                        'completed' => 'bg-info',
                                        'test_started' => 'bg-primary',
                                        'disqualified' => 'bg-danger',
                                        'absent' => 'bg-secondary',
                                        default => 'bg-warning text-dark'
                                    };
                                    ?>
                                    <span class="badge <?= $stBadge ?>">
                                        <?= ucfirst(str_replace('_', ' ', e($c['status']))) ?>
                                    </span>
                                </td>
                                <td class="text-end pe-3">
                                    <div class="btn-group btn-group-sm">
                                        <a href="<?= url('candidates-view') ?>&id=<?= $c['id'] ?>" class="btn btn-outline-info" title="View Profile">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if (Auth::hasPermission('candidates.edit')): ?>
                                            <a href="<?= url('candidates-edit') ?>&id=<?= $c['id'] ?>" class="btn btn-outline-primary" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                        <?php endif; ?>
                                        <?php if (Auth::hasPermission('candidates.delete')): ?>
                                            <form method="POST" action="<?= url('candidates') ?>" class="d-inline" onsubmit="return confirm('Are you sure you want to delete candidate <?= e(addslashes($c['full_name'])) ?>?');">
                                                <?= CSRF::field() ?>
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="candidate_id" value="<?= $c['id'] ?>">
                                                <button type="submit" class="btn btn-outline-danger" title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="card-footer-custom d-flex justify-content-between align-items-center px-3 py-2 border-top border-secondary">
                    <small class="text-muted">Showing page <?= $currentPageNum ?> of <?= $totalPages ?></small>
                    <nav aria-label="Page navigation">
                        <ul class="pagination pagination-sm mb-0">
                            <?php if ($currentPageNum > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="<?= url('candidates') ?>&search=<?= urlencode($search) ?>&competition_id=<?= $competitionId ?>&course=<?= urlencode($course) ?>&shift=<?= urlencode($shift) ?>&status=<?= urlencode($status) ?>&p=<?= $currentPageNum - 1 ?>">Previous</a>
                                </li>
                            <?php endif; ?>

                            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                                <li class="page-item <?= $p === $currentPageNum ? 'active' : '' ?>">
                                    <a class="page-link" href="<?= url('candidates') ?>&search=<?= urlencode($search) ?>&competition_id=<?= $competitionId ?>&course=<?= urlencode($course) ?>&shift=<?= urlencode($shift) ?>&status=<?= urlencode($status) ?>&p=<?= $p ?>"><?= $p ?></a>
                                </li>
                            <?php endfor; ?>

                            <?php if ($currentPageNum < $totalPages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="<?= url('candidates') ?>&search=<?= urlencode($search) ?>&competition_id=<?= $competitionId ?>&course=<?= urlencode($course) ?>&shift=<?= urlencode($shift) ?>&status=<?= urlencode($status) ?>&p=<?= $currentPageNum + 1 ?>">Next</a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
