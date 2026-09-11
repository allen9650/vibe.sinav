<?php
/**
 * Competitions Management — List View
 */

$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');
$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo = trim($_GET['date_to'] ?? '');
$currentPageNum = max(1, (int)($_GET['p'] ?? 1));
$perPage = 10;

// Handle POST actions (Status update & Safe delete)
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    CSRF::validateOrFail();

    $action = $_POST['action'] ?? '';
    $competitionId = (int)($_POST['competition_id'] ?? 0);

    $competition = Database::fetch("SELECT * FROM competitions WHERE id = ?", [$competitionId]);
    if (!$competition) {
        Session::flash('error', 'Competition not found.');
        redirectTo('competitions');
    }

    if ($action === 'change_status' && Auth::hasPermission('competitions.manage_status')) {
        $newStatus = trim($_POST['new_status'] ?? '');
        $allowedStatuses = ['draft', 'upcoming', 'active', 'completed', 'cancelled'];

        if (in_array($newStatus, $allowedStatuses, true)) {
            $oldStatus = $competition['status'];
            Database::update('competitions', [
                'status' => $newStatus,
                'updated_by' => Session::get('user_id'),
            ], 'id = ?', [$competitionId]);

            AuditLog::log('competition_status_changed', 'competitions', 
                "Changed status of '{$competition['name']}' from {$oldStatus} to {$newStatus}", 
                Session::get('user_id'), 
                ['status' => $oldStatus], 
                ['status' => $newStatus]
            );

            Session::flash('success', "Competition status updated to " . ucfirst($newStatus) . ".");
        } else {
            Session::flash('error', 'Invalid status specified.');
        }
        CSRF::regenerate();
        redirectTo('competitions');
    }

    if ($action === 'delete' && Auth::hasPermission('competitions.delete')) {
        // Safe check: verify if candidates or test attempts exist
        $candidateCount = (int)Database::fetchColumn("SELECT COUNT(*) FROM candidates WHERE competition_id = ?", [$competitionId]);
        $attemptCount = (int)Database::fetchColumn("SELECT COUNT(*) FROM test_attempts WHERE competition_id = ?", [$competitionId]);

        if ($candidateCount > 0 || $attemptCount > 0) {
            Session::flash('error', "Cannot delete competition '{$competition['name']}' because it has {$candidateCount} candidate(s) and {$attemptCount} test record(s) linked to it. You may change its status to 'Cancelled' instead.");
        } else {
            Database::delete('competitions', 'id = ?', [$competitionId]);
            AuditLog::log('competition_deleted', 'competitions', 
                "Deleted competition '{$competition['name']}' ({$competition['code']})", 
                Session::get('user_id'), 
                $competition, 
                null
            );
            Session::flash('success', "Competition '{$competition['name']}' deleted successfully.");
        }
        CSRF::regenerate();
        redirectTo('competitions');
    }
}

// Build query
$where = ['1=1'];
$params = [];

if ($search !== '') {
    $where[] = '(name LIKE ? OR code LIKE ? OR venue LIKE ?)';
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

if ($status !== '') {
    $where[] = 'status = ?';
    $params[] = $status;
}

if ($dateFrom !== '') {
    $where[] = 'competition_date >= ?';
    $params[] = $dateFrom;
}

if ($dateTo !== '') {
    $where[] = 'competition_date <= ?';
    $params[] = $dateTo;
}

$whereClause = implode(' AND ', $where);

// Get total count
$totalCount = (int)Database::fetchColumn("SELECT COUNT(*) FROM competitions WHERE {$whereClause}", $params);
$totalPages = max(1, (int)ceil($totalCount / $perPage));
$offset = ($currentPageNum - 1) * $perPage;

// Fetch competitions with candidate count
$queryParams = array_merge($params, [$perPage, $offset]);
$competitions = Database::fetchAll(
    "SELECT c.*, 
            (SELECT COUNT(*) FROM candidates cd WHERE cd.competition_id = c.id) as total_candidates,
            (SELECT COUNT(*) FROM attendance a WHERE a.competition_id = c.id AND a.status = 'present') as present_candidates,
            u.full_name as creator_name
     FROM competitions c
     LEFT JOIN users u ON c.created_by = u.id
     WHERE {$whereClause}
     ORDER BY c.competition_date DESC, c.id DESC
     LIMIT ? OFFSET ?",
    $queryParams
);
?>

<div class="dashboard-header d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
    <div>
        <h1 class="page-title">
            <i class="fas fa-trophy me-2 text-warning"></i> Competitions
        </h1>
        <p class="page-subtitle">Manage typing competitions, schedules, venues, and status</p>
    </div>
    <?php if (Auth::hasPermission('competitions.create')): ?>
        <div>
            <a href="<?= url('competitions-create') ?>" class="btn btn-primary" id="btnCreateCompetition">
                <i class="fas fa-plus me-2"></i> Add Competition
            </a>
        </div>
    <?php endif; ?>
</div>

<!-- Filters Card -->
<div class="content-card mb-4">
    <div class="card-body-custom">
        <form method="GET" class="row g-3 align-items-end" id="competitionFilterForm">
            <input type="hidden" name="page" value="competitions">

            <div class="col-md-3">
                <label class="form-label small text-muted">Search</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                    <input type="text" class="form-control" name="search" placeholder="Title, code, venue..." value="<?= e($search) ?>">
                </div>
            </div>

            <div class="col-md-2">
                <label class="form-label small text-muted">Status</label>
                <select class="form-select" name="status">
                    <option value="">All Statuses</option>
                    <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>Draft</option>
                    <option value="upcoming" <?= $status === 'upcoming' ? 'selected' : '' ?>>Upcoming</option>
                    <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="completed" <?= $status === 'completed' ? 'selected' : '' ?>>Completed</option>
                    <option value="cancelled" <?= $status === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label small text-muted">Date From</label>
                <input type="date" class="form-control" name="date_from" value="<?= e($dateFrom) ?>">
            </div>

            <div class="col-md-2">
                <label class="form-label small text-muted">Date To</label>
                <input type="date" class="form-control" name="date_to" value="<?= e($dateTo) ?>">
            </div>

            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-filter me-1"></i> Filter
                </button>
                <a href="<?= url('competitions') ?>" class="btn btn-outline-secondary">
                    <i class="fas fa-redo me-1"></i> Reset
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Competitions Table -->
<div class="content-card">
    <div class="card-header-custom d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            <i class="fas fa-list me-2"></i> All Competitions
            <span class="badge bg-secondary ms-2"><?= $totalCount ?></span>
        </h5>
    </div>
    <div class="card-body-custom p-0">
        <?php if (empty($competitions)): ?>
            <div class="text-center text-muted py-5">
                <i class="fas fa-trophy fa-3x mb-3 text-secondary"></i>
                <p class="mb-2">No competitions found.</p>
                <?php if (Auth::hasPermission('competitions.create')): ?>
                    <a href="<?= url('competitions-create') ?>" class="btn btn-sm btn-primary">
                        <i class="fas fa-plus me-1"></i> Create First Competition
                    </a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-dark table-hover align-middle mb-0" id="competitionsTable">
                    <thead>
                        <tr>
                            <th width="70">ID</th>
                            <th>Title & Code</th>
                            <th>Date & Time</th>
                            <th>Venue</th>
                            <th class="text-center">Candidates</th>
                            <th class="text-center">Status</th>
                            <th width="190" class="text-end pe-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($competitions as $c): ?>
                            <tr>
                                <td><span class="text-muted">#<?= $c['id'] ?></span></td>
                                <td>
                                    <div class="fw-bold text-light"><?= e($c['name']) ?></div>
                                    <div class="small text-info font-monospace"><?= e($c['code']) ?></div>
                                </td>
                                <td>
                                    <div><i class="fas fa-calendar-day me-1 text-muted"></i> <?= formatDate($c['competition_date']) ?></div>
                                    <?php if ($c['start_time']): ?>
                                        <div class="small text-muted">
                                            <i class="fas fa-clock me-1"></i>
                                            <?= date('h:i A', strtotime($c['start_time'])) ?>
                                            <?= $c['end_time'] ? ' — ' . date('h:i A', strtotime($c['end_time'])) : '' ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="text-muted"><?= e($c['venue'] ?: '—') ?></span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-dark border border-secondary">
                                        <?= (int)$c['total_candidates'] ?> enrolled
                                    </span>
                                </td>
                                <td class="text-center">
                                    <?php
                                    $badgeClass = match($c['status']) {
                                        'active' => 'bg-success',
                                        'upcoming' => 'bg-info',
                                        'completed' => 'bg-secondary',
                                        'cancelled' => 'bg-danger',
                                        default => 'bg-warning text-dark'
                                    };
                                    ?>
                                    <span class="badge <?= $badgeClass ?>">
                                        <?= ucfirst(e($c['status'])) ?>
                                    </span>
                                </td>
                                <td class="text-end pe-3">
                                    <div class="btn-group btn-group-sm">
                                        <a href="<?= url('competitions-view') ?>&id=<?= $c['id'] ?>" class="btn btn-outline-info" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>

                                        <?php if (Auth::hasPermission('competitions.edit')): ?>
                                            <a href="<?= url('competitions-edit') ?>&id=<?= $c['id'] ?>" class="btn btn-outline-primary" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                        <?php endif; ?>

                                        <?php if (Auth::hasPermission('competitions.create')): ?>
                                            <a href="<?= url('competitions-duplicate') ?>&id=<?= $c['id'] ?>" class="btn btn-outline-warning" title="Duplicate">
                                                <i class="fas fa-copy"></i>
                                            </a>
                                        <?php endif; ?>

                                        <?php if (Auth::hasPermission('competitions.manage_status')): ?>
                                            <button type="button" class="btn btn-outline-secondary dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-expanded="false" title="Change Status">
                                                <span class="visually-hidden">Toggle Status</span>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-dark dropdown-menu-end">
                                                <li><h6 class="dropdown-header">Change Status</h6></li>
                                                <?php foreach (['draft', 'upcoming', 'active', 'completed', 'cancelled'] as $st): ?>
                                                    <?php if ($st !== $c['status']): ?>
                                                        <li>
                                                            <form method="POST" action="<?= url('competitions') ?>" class="d-inline">
                                                                <?= CSRF::field() ?>
                                                                <input type="hidden" name="action" value="change_status">
                                                                <input type="hidden" name="competition_id" value="<?= $c['id'] ?>">
                                                                <input type="hidden" name="new_status" value="<?= $st ?>">
                                                                <button type="submit" class="dropdown-item">
                                                                    Mark as <?= ucfirst($st) ?>
                                                                </button>
                                                            </form>
                                                        </li>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>

                                                <?php if (Auth::hasPermission('competitions.delete')): ?>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li>
                                                        <form method="POST" action="<?= url('competitions') ?>" onsubmit="return confirm('Are you sure you want to delete this competition?');">
                                                            <?= CSRF::field() ?>
                                                            <input type="hidden" name="action" value="delete">
                                                            <input type="hidden" name="competition_id" value="<?= $c['id'] ?>">
                                                            <button type="submit" class="dropdown-item text-danger">
                                                                <i class="fas fa-trash me-2"></i> Delete
                                                            </button>
                                                        </form>
                                                    </li>
                                                <?php endif; ?>
                                            </ul>
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
                                    <a class="page-link" href="<?= url('competitions') ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>&date_from=<?= urlencode($dateFrom) ?>&date_to=<?= urlencode($dateTo) ?>&p=<?= $currentPageNum - 1 ?>">Previous</a>
                                </li>
                            <?php endif; ?>

                            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                                <li class="page-item <?= $p === $currentPageNum ? 'active' : '' ?>">
                                    <a class="page-link" href="<?= url('competitions') ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>&date_from=<?= urlencode($dateFrom) ?>&date_to=<?= urlencode($dateTo) ?>&p=<?= $p ?>"><?= $p ?></a>
                                </li>
                            <?php endfor; ?>

                            <?php if ($currentPageNum < $totalPages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="<?= url('competitions') ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>&date_from=<?= urlencode($dateFrom) ?>&date_to=<?= urlencode($dateTo) ?>&p=<?= $currentPageNum + 1 ?>">Next</a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
