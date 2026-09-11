<?php
/**
 * Audit Logs Viewer
 */

// Filters
$filterModule = $_GET['module'] ?? '';
$filterAction = $_GET['action'] ?? '';
$filterDateFrom = $_GET['date_from'] ?? '';
$filterDateTo = $_GET['date_to'] ?? '';
$currentPageNum = max(1, (int)($_GET['p'] ?? 1));
$perPage = 20;

$filters = [];
if ($filterModule) $filters['module'] = $filterModule;
if ($filterAction) $filters['action'] = $filterAction;
if ($filterDateFrom) $filters['date_from'] = $filterDateFrom;
if ($filterDateTo) $filters['date_to'] = $filterDateTo;

$totalLogs = AuditLog::count($filters);
$totalPages = max(1, ceil($totalLogs / $perPage));
$offset = ($currentPageNum - 1) * $perPage;

$logs = AuditLog::getLogs($filters, $perPage, $offset);

// Get unique modules for filter
$modules = Database::fetchAll("SELECT DISTINCT module FROM audit_logs ORDER BY module");
?>

<div class="dashboard-header mb-4">
    <h1 class="page-title">
        <i class="fas fa-history me-2"></i> Audit Logs
    </h1>
    <p class="page-subtitle">Track all system activities and changes</p>
</div>

<!-- Filters -->
<div class="content-card mb-4">
    <div class="card-body-custom">
        <form method="GET" class="row g-3 align-items-end" id="auditFilterForm">
            <input type="hidden" name="page" value="audit-logs">

            <div class="col-md-3">
                <label class="form-label">Module</label>
                <select class="form-select" name="module">
                    <option value="">All Modules</option>
                    <?php foreach ($modules as $mod): ?>
                        <option value="<?= e($mod['module']) ?>" <?= $filterModule === $mod['module'] ? 'selected' : '' ?>>
                            <?= e(ucfirst($mod['module'])) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label">From</label>
                <input type="date" class="form-control" name="date_from" value="<?= e($filterDateFrom) ?>">
            </div>

            <div class="col-md-2">
                <label class="form-label">To</label>
                <input type="date" class="form-control" name="date_to" value="<?= e($filterDateTo) ?>">
            </div>

            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search me-1"></i> Filter
                </button>
                <a href="<?= url('audit-logs') ?>" class="btn btn-outline-secondary">
                    <i class="fas fa-redo me-1"></i> Reset
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Logs Table -->
<div class="content-card">
    <div class="card-header-custom d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            <i class="fas fa-list me-2"></i> Activity Log
            <span class="badge bg-secondary ms-2"><?= $totalLogs ?> records</span>
        </h5>
    </div>
    <div class="card-body-custom p-0">
        <?php if (empty($logs)): ?>
            <div class="text-center text-muted py-5">
                <i class="fas fa-inbox fa-3x mb-3"></i>
                <p>No audit logs found</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-dark table-hover mb-0" id="auditTable">
                    <thead>
                        <tr>
                            <th width="160">Timestamp</th>
                            <th width="120">User</th>
                            <th width="100">Module</th>
                            <th width="140">Action</th>
                            <th>Description</th>
                            <th width="120">IP Address</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td>
                                    <small><?= formatDateTime($log['created_at'], 'd M Y') ?></small><br>
                                    <small class="text-muted"><?= formatDateTime($log['created_at'], 'h:i:s A') ?></small>
                                </td>
                                <td>
                                    <?php if ($log['username']): ?>
                                        <span class="fw-semibold"><?= e($log['username']) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">System</span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge bg-secondary"><?= e(ucfirst($log['module'] ?? '—')) ?></span></td>
                                <td>
                                    <span class="badge bg-<?= match(true) {
                                        str_contains($log['action'], 'failed') || str_contains($log['action'], 'locked') => 'danger',
                                        str_contains($log['action'], 'success') || str_contains($log['action'], 'created') => 'success',
                                        str_contains($log['action'], 'updated') || str_contains($log['action'], 'changed') => 'info',
                                        str_contains($log['action'], 'deleted') => 'warning',
                                        default => 'primary'
                                    } ?>">
                                        <?= e($log['action']) ?>
                                    </span>
                                </td>
                                <td><small><?= e($log['description'] ?? '—') ?></small></td>
                                <td><small class="text-muted"><?= e($log['ip_address'] ?? '—') ?></small></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="d-flex justify-content-center py-3">
                    <nav>
                        <ul class="pagination pagination-sm mb-0">
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <li class="page-item <?= $i === $currentPageNum ? 'active' : '' ?>">
                                    <a class="page-link" href="<?= url('audit-logs') ?>&p=<?= $i ?><?= $filterModule ? '&module=' . e($filterModule) : '' ?><?= $filterDateFrom ? '&date_from=' . e($filterDateFrom) : '' ?><?= $filterDateTo ? '&date_to=' . e($filterDateTo) : '' ?>">
                                        <?= $i ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
