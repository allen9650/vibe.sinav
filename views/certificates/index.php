<?php
/**
 * Certificate Registry & Management Dashboard
 */

Middleware::requirePermission('certificates.view');

$competitions = Database::fetchAll("SELECT id, name, code, status, results_finalized_at FROM competitions ORDER BY id DESC");
$selectedCompId = (int)($_GET['competition_id'] ?? 0);
$search = trim($_GET['search'] ?? '');
$certType = trim($_GET['type'] ?? '');
$page = max(1, (int)($_GET['p'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$where = ["1=1"];
$params = [];

if ($selectedCompId > 0) {
    $where[] = "cert.competition_id = ?";
    $params[] = $selectedCompId;
}

if ($certType !== '') {
    $where[] = "cert.certificate_type = ?";
    $params[] = $certType;
}

if ($search !== '') {
    $where[] = "(c.full_name LIKE ? OR c.roll_number LIKE ? OR cert.certificate_number LIKE ?)";
    $like = "%{$search}%";
    $params = array_merge($params, [$like, $like, $like]);
}

$whereSql = implode(' AND ', $where);

$total = (int)Database::fetchColumn("
    SELECT COUNT(*) 
    FROM certificates cert
    JOIN candidates c ON cert.candidate_id = c.id
    WHERE {$whereSql}
", $params);

$certificates = Database::fetchAll("
    SELECT 
        cert.*,
        c.full_name AS candidate_name,
        c.roll_number,
        c.registration_number,
        c.course,
        cmp.name AS competition_name,
        cmp.code AS competition_code
    FROM certificates cert
    JOIN candidates c ON cert.candidate_id = c.id
    JOIN competitions cmp ON cert.competition_id = cmp.id
    WHERE {$whereSql}
    ORDER BY cert.id DESC
    LIMIT {$perPage} OFFSET {$offset}
", $params);

$totalPages = max(1, (int)ceil($total / $perPage));
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1"><i class="fas fa-award text-warning me-2"></i> Certificate Management Center</h4>
        <div class="text-muted small">Generate, search, preview, and print official achievement and participation certificates</div>
    </div>
    <div class="d-flex gap-2">
        <?php if (Auth::hasPermission('certificates.generate')): ?>
            <a href="<?= url('certificates-generate') ?>&competition_id=<?= $selectedCompId ?>" class="btn btn-warning btn-sm fw-bold">
                <i class="fas fa-magic me-1"></i> Bulk Certificate Generator
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- Filters Card -->
<div class="card bg-dark border-secondary mb-4">
    <div class="card-body py-3">
        <form method="GET" action="<?= url('certificates') ?>" class="row g-2 align-items-center">
            <input type="hidden" name="page" value="certificates">

            <div class="col-md-4">
                <select name="competition_id" class="form-select form-select-sm bg-dark text-light border-secondary">
                    <option value="">All Competitions</option>
                    <?php foreach ($competitions as $cmp): ?>
                        <option value="<?= $cmp['id'] ?>" <?= $selectedCompId === (int)$cmp['id'] ? 'selected' : '' ?>>
                            <?= e($cmp['name']) ?> (<?= e($cmp['code']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-3">
                <select name="type" class="form-select form-select-sm bg-dark text-light border-secondary">
                    <option value="">All Certificate Types</option>
                    <option value="position" <?= $certType === 'position' ? 'selected' : '' ?>>Achievement (Position)</option>
                    <option value="participation" <?= $certType === 'participation' ? 'selected' : '' ?>>Participation</option>
                </select>
            </div>

            <div class="col-md-3">
                <input type="text" name="search" class="form-control form-select-sm bg-dark text-light border-secondary" 
                       placeholder="Candidate, Roll #, Cert #..." value="<?= e($search) ?>">
            </div>

            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm w-100">
                    <i class="fas fa-filter me-1"></i> Filter
                </button>
                <a href="<?= url('certificates') ?>" class="btn btn-outline-secondary btn-sm" title="Reset">
                    <i class="fas fa-undo"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Certificates Table -->
<div class="card bg-dark border-secondary">
    <div class="card-header border-secondary d-flex justify-content-between align-items-center">
        <span class="fw-bold"><i class="fas fa-certificate me-1 text-warning"></i> Issued Certificate Records</span>
        <span class="badge bg-secondary"><?= number_format($total) ?> Total Issued</span>
    </div>
    <div class="table-responsive">
        <table class="table table-dark table-hover table-bordered mb-0 align-middle text-center">
            <thead class="table-secondary text-light small text-uppercase">
                <tr>
                    <th style="width: 160px;">Certificate #</th>
                    <th>Roll #</th>
                    <th class="text-start">Candidate Name</th>
                    <th>Competition</th>
                    <th>Type</th>
                    <th>Design</th>
                    <th>Position</th>
                    <th>Speed</th>
                    <th>Accuracy</th>
                    <th>Issue Date</th>
                    <th style="width: 90px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($certificates)): ?>
                    <tr>
                        <td colspan="11" class="text-center py-5 text-muted">
                            <i class="fas fa-award fa-3x mb-3 d-block opacity-25"></i>
                            No certificates found matching the selected criteria.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($certificates as $cert): ?>
                        <?php 
                        $tpl = $cert['template_key'] ?? 'classic';
                        $tplBadge = match($tpl) {
                            'champion' => '<span class="badge bg-warning text-dark"><i class="fas fa-trophy me-1"></i> Champion</span>',
                            'modern'   => '<span class="badge bg-primary"><i class="fas fa-certificate me-1"></i> Modern</span>',
                            'academic' => '<span class="badge bg-danger"><i class="fas fa-graduation-cap me-1"></i> Academic</span>',
                            default    => '<span class="badge bg-secondary"><i class="fas fa-award me-1"></i> Classic</span>',
                        };
                        ?>
                        <tr>
                            <td>
                                <strong class="font-monospace text-warning"><?= e($cert['certificate_number']) ?></strong>
                            </td>
                            <td>
                                <span class="badge bg-dark border border-secondary font-monospace"><?= e($cert['roll_number'] ?: '—') ?></span>
                            </td>
                            <td class="text-start">
                                <strong class="text-light"><?= e($cert['candidate_name']) ?></strong>
                            </td>
                            <td class="small font-monospace"><?= e($cert['competition_code']) ?></td>
                            <td>
                                <?php if ($cert['certificate_type'] === 'position'): ?>
                                    <span class="badge bg-warning text-dark"><i class="fas fa-trophy me-1"></i> POSITION</span>
                                <?php else: ?>
                                    <span class="badge bg-info text-dark"><i class="fas fa-medal me-1"></i> PARTICIPATION</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= $tplBadge ?>
                            </td>
                            <td>
                                <strong class="text-light small"><?= e($cert['position']) ?></strong>
                            </td>
                            <td class="font-monospace text-primary fw-bold">
                                <?= number_format($cert['net_wpm_snapshot'], 2) ?>
                            </td>
                            <td class="font-monospace text-success">
                                <?= number_format($cert['accuracy_snapshot'], 2) ?>%
                            </td>
                            <td class="small text-muted font-monospace">
                                <?= formatDate($cert['issued_at']) ?>
                            </td>
                            <td>
                                <a href="<?= url('certificates-view') ?>&id=<?= $cert['id'] ?>" target="_blank" class="btn btn-outline-warning btn-sm py-0 px-2" title="Preview & Print Certificate">
                                    <i class="fas fa-print"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
        <div class="card-footer border-secondary d-flex justify-content-between align-items-center py-2">
            <span class="small text-muted">Page <?= $page ?> of <?= $totalPages ?></span>
            <ul class="pagination pagination-sm mb-0">
                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link bg-dark border-secondary text-light" href="<?= url('certificates') ?>&p=<?= $page - 1 ?>&competition_id=<?= $selectedCompId ?>&type=<?= e($certType) ?>&search=<?= urlencode($search) ?>">Previous</a>
                </li>
                <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                    <a class="page-link bg-dark border-secondary text-light" href="<?= url('certificates') ?>&p=<?= $page + 1 ?>&competition_id=<?= $selectedCompId ?>&type=<?= e($certType) ?>&search=<?= urlencode($search) ?>">Next</a>
                </li>
            </ul>
        </div>
    <?php endif; ?>
</div>
