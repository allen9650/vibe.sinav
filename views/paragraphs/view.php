<?php
/**
 * Typing Paragraph Management — Paragraph Details View
 */

$paragraphId = (int)($_GET['id'] ?? 0);
$paragraph = Database::fetch(
    "SELECT p.*, u.full_name as author_name
     FROM typing_paragraphs p
     LEFT JOIN users u ON p.created_by = u.id
     WHERE p.id = ?",
    [$paragraphId]
);

if (!$paragraph) {
    Session::flash('error', 'Paragraph not found.');
    redirectTo('paragraphs');
}

// Fetch assigned competitions
$assignedCompetitions = Database::fetchAll(
    "SELECT cmp.id, cmp.name, cmp.code, cmp.competition_date, cmp.status, cp.is_active
     FROM competition_paragraphs cp
     JOIN competitions cmp ON cp.competition_id = cmp.id
     WHERE cp.paragraph_id = ?
     ORDER BY cmp.competition_date DESC",
    [$paragraphId]
);

// Calculate metrics
$rawText = $paragraph['content'];
$noSpaces = mb_strlen(preg_replace('/\s+/', '', $rawText));
$spacesCount = mb_strlen($rawText) - $noSpaces;
$linesCount = count(explode("\n", str_replace("\r", "", trim($rawText))));
?>

<div class="dashboard-header d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <h1 class="page-title mb-0"><?= e($paragraph['title']) ?></h1>
            <span class="badge bg-secondary">#<?= $paragraph['id'] ?></span>
            <span class="badge <?= $paragraph['status'] === 'active' ? 'bg-success' : 'bg-secondary' ?>"><?= ucfirst($paragraph['status']) ?></span>
            <?php
            $diffBadge = match($paragraph['difficulty']) {
                'easy' => 'bg-success',
                'hard' => 'bg-warning text-dark',
                'expert' => 'bg-danger',
                default => 'bg-primary'
            };
            ?>
            <span class="badge <?= $diffBadge ?> text-capitalize"><?= e($paragraph['difficulty']) ?></span>
        </div>
        <p class="page-subtitle">Created by <?= e($paragraph['author_name'] ?: 'System') ?> on <?= formatDateTime($paragraph['created_at']) ?></p>
    </div>

    <div class="d-flex gap-2">
        <a href="<?= url('paragraphs-preview') ?>&id=<?= $paragraph['id'] ?>" class="btn btn-outline-info">
            <i class="fas fa-eye me-1"></i> Preview Test Layout
        </a>
        <?php if (Auth::hasPermission('paragraphs.edit')): ?>
            <a href="<?= url('paragraphs-edit') ?>&id=<?= $paragraph['id'] ?>" class="btn btn-outline-primary">
                <i class="fas fa-edit me-1"></i> Edit Paragraph
            </a>
        <?php endif; ?>
        <a href="<?= url('paragraphs') ?>" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i> Back to List
        </a>
    </div>
</div>

<div class="row g-4">
    <!-- Left: Content Display -->
    <div class="col-lg-8">
        <div class="content-card mb-4">
            <div class="card-header-custom d-flex justify-content-between align-items-center">
                <h5><i class="fas fa-align-left me-2"></i> Full Passage Text</h5>
                <span class="badge bg-dark border border-secondary"><?= e(strtoupper($paragraph['language'])) ?></span>
            </div>
            <div class="card-body-custom">
                <div class="p-4 bg-dark rounded border border-secondary font-monospace" 
                     style="font-size: 16px; line-height: 1.8; white-space: pre-wrap; word-break: break-word;">
                    <?= htmlspecialchars($paragraph['content']) ?>
                </div>
            </div>
        </div>

        <!-- Assigned Competitions Table -->
        <div class="content-card">
            <div class="card-header-custom">
                <h5><i class="fas fa-trophy me-2"></i> Assigned Competitions</h5>
            </div>
            <div class="card-body-custom p-0">
                <?php if (empty($assignedCompetitions)): ?>
                    <div class="text-center text-muted py-4">
                        <p class="mb-0">This paragraph is not assigned to any competition yet.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-dark table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Competition Title</th>
                                    <th>Code</th>
                                    <th>Date</th>
                                    <th class="text-center">Status</th>
                                    <th class="text-end pe-3">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($assignedCompetitions as $c): ?>
                                    <tr>
                                        <td class="fw-bold"><?= e($c['name']) ?></td>
                                        <td><span class="font-monospace text-info"><?= e($c['code']) ?></span></td>
                                        <td><?= formatDate($c['competition_date']) ?></td>
                                        <td class="text-center">
                                            <span class="badge <?= $c['is_active'] ? 'bg-success' : 'bg-secondary' ?>">
                                                <?= $c['is_active'] ? 'Active in Comp' : 'Disabled in Comp' ?>
                                            </span>
                                        </td>
                                        <td class="text-end pe-3">
                                            <a href="<?= url('competitions-view') ?>&id=<?= $c['id'] ?>" class="btn btn-outline-info btn-sm">
                                                View Competition <i class="fas fa-arrow-right ms-1"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Right: Text Analytics Card -->
    <div class="col-lg-4">
        <div class="content-card mb-4">
            <div class="card-header-custom">
                <h5><i class="fas fa-chart-pie me-2"></i> Authoritative Analytics</h5>
            </div>
            <div class="card-body-custom">
                <ul class="list-group list-group-flush bg-transparent">
                    <li class="list-group-item bg-transparent d-flex justify-content-between text-light px-0 py-2 border-secondary">
                        <span class="text-muted"><i class="fas fa-font me-2"></i> Total Words</span>
                        <strong class="text-primary fs-5"><?= number_format($paragraph['word_count']) ?></strong>
                    </li>
                    <li class="list-group-item bg-transparent d-flex justify-content-between text-light px-0 py-2 border-secondary">
                        <span class="text-muted"><i class="fas fa-i-cursor me-2"></i> Total Characters</span>
                        <strong class="text-info fs-5"><?= number_format($paragraph['char_count']) ?></strong>
                    </li>
                    <li class="list-group-item bg-transparent d-flex justify-content-between text-light px-0 py-2 border-secondary">
                        <span class="text-muted"><i class="fas fa-stream me-2"></i> Chars (No Spaces)</span>
                        <strong class="text-light"><?= number_format($noSpaces) ?></strong>
                    </li>
                    <li class="list-group-item bg-transparent d-flex justify-content-between text-light px-0 py-2 border-secondary">
                        <span class="text-muted"><i class="fas fa-arrows-alt-h me-2"></i> Spaces Count</span>
                        <strong class="text-light"><?= number_format($spacesCount) ?></strong>
                    </li>
                    <li class="list-group-item bg-transparent d-flex justify-content-between text-light px-0 py-2 border-secondary">
                        <span class="text-muted"><i class="fas fa-bars me-2"></i> Lines Count</span>
                        <strong class="text-light"><?= number_format($linesCount) ?></strong>
                    </li>
                    <li class="list-group-item bg-transparent d-flex justify-content-between text-light px-0 py-2">
                        <span class="text-muted"><i class="fas fa-layer-group me-2"></i> Category</span>
                        <strong class="text-light"><?= e($paragraph['category'] ?: 'General') ?></strong>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>
