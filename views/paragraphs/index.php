<?php
/**
 * Typing Paragraph Management — Index / List View
 */

$search = trim($_GET['search'] ?? '');
$filterDifficulty = trim($_GET['difficulty'] ?? '');
$filterStatus = trim($_GET['status'] ?? '');
$filterLanguage = trim($_GET['language'] ?? '');

$page = max(1, (int)($_GET['p'] ?? 1));
$perPage = 10;

// Handle POST actions: Status toggle & Safe Delete
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    CSRF::validateOrFail();

    $action = $_POST['action'] ?? '';
    $paragraphId = (int)($_POST['paragraph_id'] ?? 0);

    if ($action === 'toggle_status' && Auth::hasPermission('paragraphs.edit')) {
        $p = Database::fetch("SELECT * FROM typing_paragraphs WHERE id = ?", [$paragraphId]);
        if ($p) {
            $newStatus = $p['status'] === 'active' ? 'inactive' : 'active';
            Database::update('typing_paragraphs', ['status' => $newStatus], 'id = ?', [$paragraphId]);
            AuditLog::log(
                'paragraph_status_changed',
                'paragraphs',
                "Changed paragraph '{$p['title']}' status to {$newStatus}",
                Session::get('user_id'),
                ['status' => $p['status']],
                ['paragraph_id' => $paragraphId, 'status' => $newStatus]
            );
            Session::flash('success', "Paragraph '{$p['title']}' is now " . ucfirst($newStatus) . ".");
        }
        CSRF::regenerate();
        redirectTo('paragraphs');
    }

    if ($action === 'delete' && Auth::hasPermission('paragraphs.delete')) {
        $p = Database::fetch("SELECT * FROM typing_paragraphs WHERE id = ?", [$paragraphId]);
        if ($p) {
            // Check if paragraph is assigned to active competitions or has test attempts
            $assignedCount = (int)Database::fetchColumn("SELECT COUNT(*) FROM competition_paragraphs WHERE paragraph_id = ?", [$paragraphId]);
            $attemptsCount = (int)Database::fetchColumn("SELECT COUNT(*) FROM test_attempts WHERE paragraph_id = ?", [$paragraphId]);

            if ($attemptsCount > 0) {
                Session::flash('error', "Cannot delete paragraph '{$p['title']}' because it is linked to {$attemptsCount} test attempt(s). You may deactivate it instead.");
            } elseif ($assignedCount > 0) {
                Session::flash('error', "Cannot delete paragraph '{$p['title']}' because it is currently assigned to {$assignedCount} competition(s). Unassign it first.");
            } else {
                Database::delete('typing_paragraphs', 'id = ?', [$paragraphId]);
                AuditLog::log(
                    'paragraph_deleted',
                    'paragraphs',
                    "Deleted paragraph '{$p['title']}'",
                    Session::get('user_id'),
                    $p,
                    null
                );
                Session::flash('success', "Paragraph '{$p['title']}' deleted successfully.");
            }
        }
        CSRF::regenerate();
        redirectTo('paragraphs');
    }
}

// Build query
$where = ['1=1'];
$params = [];

if ($search !== '') {
    $where[] = '(title LIKE ? OR content LIKE ?)';
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

if ($filterDifficulty !== '') {
    $where[] = 'difficulty = ?';
    $params[] = $filterDifficulty;
}

if ($filterStatus !== '') {
    $where[] = 'status = ?';
    $params[] = $filterStatus;
}

if ($filterLanguage !== '') {
    $where[] = 'language = ?';
    $params[] = $filterLanguage;
}

$whereClause = implode(' AND ', $where);

// Total count
$totalCount = (int)Database::fetchColumn("SELECT COUNT(*) FROM typing_paragraphs WHERE {$whereClause}", $params);
$totalPages = max(1, ceil($totalCount / $perPage));
$offset = ($page - 1) * $perPage;

$paragraphs = Database::fetchAll(
    "SELECT p.*, u.full_name as author_name,
            (SELECT COUNT(*) FROM competition_paragraphs WHERE paragraph_id = p.id) as assigned_competitions
     FROM typing_paragraphs p
     LEFT JOIN users u ON p.created_by = u.id
     WHERE {$whereClause}
     ORDER BY p.id DESC
     LIMIT {$perPage} OFFSET {$offset}",
    $params
);
?>

<div class="dashboard-header d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
    <div>
        <h1 class="page-title">
            <i class="fas fa-paragraph me-2 text-primary"></i> Typing Paragraphs
        </h1>
        <p class="page-subtitle">Manage reading texts and paragraphs for typing speed competitions</p>
    </div>
    <div class="d-flex gap-2">
        <?php if (Auth::hasPermission('paragraphs.create')): ?>
            <a href="<?= url('paragraphs-create') ?>" class="btn btn-primary" id="btnCreateParagraph">
                <i class="fas fa-plus me-1"></i> Add New Paragraph
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- Filters Bar -->
<div class="content-card mb-4">
    <div class="card-body-custom">
        <form method="GET" class="row g-3 align-items-end" id="paragraphFilterForm">
            <input type="hidden" name="page" value="paragraphs">

            <div class="col-md-4">
                <label class="form-label small text-muted">Search Text / Title</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                    <input type="text" class="form-control" name="search" placeholder="Search keywords..." value="<?= e($search) ?>">
                </div>
            </div>

            <div class="col-md-2">
                <label class="form-label small text-muted">Difficulty</label>
                <select class="form-select" name="difficulty">
                    <option value="">All Difficulties</option>
                    <option value="easy" <?= $filterDifficulty === 'easy' ? 'selected' : '' ?>>Easy</option>
                    <option value="medium" <?= $filterDifficulty === 'medium' ? 'selected' : '' ?>>Medium</option>
                    <option value="hard" <?= $filterDifficulty === 'hard' ? 'selected' : '' ?>>Hard</option>
                    <option value="expert" <?= $filterDifficulty === 'expert' ? 'selected' : '' ?>>Expert</option>
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label small text-muted">Status</label>
                <select class="form-select" name="status">
                    <option value="">All Statuses</option>
                    <option value="active" <?= $filterStatus === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= $filterStatus === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label small text-muted">Language</label>
                <select class="form-select" name="language">
                    <option value="">All Languages</option>
                    <option value="english" <?= $filterLanguage === 'english' ? 'selected' : '' ?>>English</option>
                    <option value="urdu" <?= $filterLanguage === 'urdu' ? 'selected' : '' ?>>Urdu</option>
                    <option value="sindhi" <?= $filterLanguage === 'sindhi' ? 'selected' : '' ?>>Sindhi</option>
                </select>
            </div>

            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-filter me-1"></i> Filter
                </button>
                <a href="<?= url('paragraphs') ?>" class="btn btn-outline-secondary">
                    <i class="fas fa-redo"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Paragraphs Table -->
<div class="content-card">
    <div class="card-header-custom d-flex justify-content-between align-items-center">
        <h5>
            <i class="fas fa-list me-2"></i> Paragraph Library
            <span class="badge bg-secondary ms-2"><?= $totalCount ?> total</span>
        </h5>
    </div>
    <div class="card-body-custom p-0">
        <?php if (empty($paragraphs)): ?>
            <div class="text-center text-muted py-5">
                <i class="fas fa-file-alt fa-3x mb-3 text-secondary"></i>
                <p class="mb-2">No typing paragraphs found.</p>
                <?php if (Auth::hasPermission('paragraphs.create')): ?>
                    <a href="<?= url('paragraphs-create') ?>" class="btn btn-sm btn-primary">
                        <i class="fas fa-plus me-1"></i> Create First Paragraph
                    </a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-dark table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th width="60">ID</th>
                            <th>Title & Snippet</th>
                            <th width="110">Difficulty</th>
                            <th width="90">Language</th>
                            <th width="100" class="text-center">Words</th>
                            <th width="100" class="text-center">Characters</th>
                            <th width="100" class="text-center">Assigned</th>
                            <th width="90" class="text-center">Status</th>
                            <th width="160" class="text-end pe-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($paragraphs as $p): ?>
                            <tr>
                                <td class="text-muted small">#<?= $p['id'] ?></td>
                                <td>
                                    <div class="fw-bold text-light">
                                        <a href="<?= url('paragraphs-view') ?>&id=<?= $p['id'] ?>" class="text-light text-decoration-none hover-primary">
                                            <?= e($p['title']) ?>
                                        </a>
                                    </div>
                                    <div class="small text-muted text-truncate" style="max-width: 380px;">
                                        <?= e(mb_substr($p['content'], 0, 90)) ?>...
                                    </div>
                                </td>
                                <td>
                                    <?php
                                    $diffBadge = match($p['difficulty']) {
                                        'easy' => 'bg-success',
                                        'hard' => 'bg-warning text-dark',
                                        'expert' => 'bg-danger',
                                        default => 'bg-primary'
                                    };
                                    ?>
                                    <span class="badge <?= $diffBadge ?> text-capitalize"><?= e($p['difficulty']) ?></span>
                                </td>
                                <td>
                                    <span class="badge bg-dark border border-secondary text-capitalize"><?= e($p['language']) ?></span>
                                </td>
                                <td class="text-center fw-bold">
                                    <?= number_format($p['word_count']) ?>
                                </td>
                                <td class="text-center text-muted small">
                                    <?= number_format($p['char_count']) ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-dark border border-secondary"><?= $p['assigned_competitions'] ?> comp(s)</span>
                                </td>
                                <td class="text-center">
                                    <?php if (Auth::hasPermission('paragraphs.edit')): ?>
                                        <form method="POST" action="<?= url('paragraphs') ?>" class="d-inline">
                                            <?= CSRF::field() ?>
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="paragraph_id" value="<?= $p['id'] ?>">
                                            <button type="submit" class="badge border-0 cursor-pointer <?= $p['status'] === 'active' ? 'bg-success' : 'bg-secondary' ?>">
                                                <?= ucfirst($p['status']) ?>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="badge <?= $p['status'] === 'active' ? 'bg-success' : 'bg-secondary' ?>"><?= ucfirst($p['status']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-3">
                                    <div class="btn-group btn-group-sm">
                                        <a href="<?= url('paragraphs-preview') ?>&id=<?= $p['id'] ?>" class="btn btn-outline-info" title="Preview Test Layout">
                                            <i class="fas fa-eye"></i>
                                        </a>

                                        <?php if (Auth::hasPermission('paragraphs.edit')): ?>
                                            <a href="<?= url('paragraphs-edit') ?>&id=<?= $p['id'] ?>" class="btn btn-outline-primary" title="Edit Paragraph">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                        <?php endif; ?>

                                        <?php if (Auth::hasPermission('paragraphs.delete')): ?>
                                            <form method="POST" action="<?= url('paragraphs') ?>" class="d-inline" 
                                                  onsubmit="return confirm('Are you sure you want to delete paragraph \'<?= e($p['title']) ?>\'?');">
                                                <?= CSRF::field() ?>
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="paragraph_id" value="<?= $p['id'] ?>">
                                                <button type="submit" class="btn btn-outline-danger" title="Delete Paragraph">
                                                    <i class="fas fa-trash-alt"></i>
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
                <div class="card-footer-custom d-flex justify-content-between align-items-center p-3 border-top border-secondary">
                    <div class="small text-muted">
                        Showing <?= $offset + 1 ?> to <?= min($offset + $perPage, $totalCount) ?> of <?= $totalCount ?> paragraphs
                    </div>
                    <ul class="pagination pagination-sm mb-0">
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= url('paragraphs') ?>&p=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&difficulty=<?= $filterDifficulty ?>&status=<?= $filterStatus ?>">Previous</a>
                        </li>
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <li class="page-item <?= $page === $i ? 'active' : '' ?>">
                                <a class="page-link" href="<?= url('paragraphs') ?>&p=<?= $i ?>&search=<?= urlencode($search) ?>&difficulty=<?= $filterDifficulty ?>&status=<?= $filterStatus ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= url('paragraphs') ?>&p=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&difficulty=<?= $filterDifficulty ?>&status=<?= $filterStatus ?>">Next</a>
                        </li>
                    </ul>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
