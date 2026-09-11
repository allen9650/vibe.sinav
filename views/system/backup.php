<?php
/**
 * System — Database Backup Management
 */

Middleware::requireAnyRole(['super-admin', 'admin']);

$error = '';
$success = '';

// Handle Download request
if (isset($_GET['download']) && $_GET['download'] !== '') {
    try {
        BackupService::downloadBackup($_GET['download']);
    } catch (Exception $e) {
        $error = "Download failed: " . $e->getMessage();
    }
}

// Handle Create Backup POST
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'backup') {
    CSRF::validateOrFail();

    try {
        $res = BackupService::createBackup(Session::get('user_id'));
        Session::flash('success', "Database backup created successfully: '{$res['filename']}' (" . round($res['size_bytes'] / 1024, 2) . " KB)");
        redirectTo('backup');
    } catch (Exception $e) {
        $error = "Backup failed: " . $e->getMessage();
    }
}

$backups = BackupService::listBackups();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1"><i class="fas fa-database text-warning me-2"></i> Database Backup & Disaster Recovery</h4>
        <div class="text-muted small">Generate standalone SQL dumps, preserve historical state, and download protected backups</div>
    </div>
    <div>
        <form method="POST" action="<?= url('backup') ?>" class="d-inline">
            <?= CSRF::field() ?>
            <input type="hidden" name="action" value="backup">
            <button type="submit" class="btn btn-warning btn-sm fw-bold">
                <i class="fas fa-plus-circle me-1"></i> Create Full Backup Now
            </button>
        </form>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger py-2 px-3 mb-4 small">
        <i class="fas fa-exclamation-circle me-2"></i> <?= e($error) ?>
    </div>
<?php endif; ?>

<!-- Backups List Card -->
<div class="card bg-dark border-secondary">
    <div class="card-header border-secondary d-flex justify-content-between align-items-center">
        <span class="fw-bold text-light"><i class="fas fa-archive me-2 text-primary"></i> Available Database Snapshots</span>
        <span class="badge bg-secondary"><?= count($backups) ?> Backup Files</span>
    </div>
    <div class="table-responsive">
        <table class="table table-dark table-hover table-bordered mb-0 align-middle text-center">
            <thead class="table-secondary text-light small text-uppercase">
                <tr>
                    <th class="text-start">Backup Filename</th>
                    <th>File Size</th>
                    <th>Created At</th>
                    <th style="width: 140px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($backups)): ?>
                    <tr>
                        <td colspan="4" class="text-center py-5 text-muted">
                            <i class="fas fa-database fa-3x mb-3 d-block opacity-25"></i>
                            No database backups found in storage. Click <strong>"Create Full Backup Now"</strong> above.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($backups as $b): ?>
                        <tr>
                            <td class="text-start">
                                <i class="fas fa-file-code text-warning me-2"></i>
                                <strong class="font-monospace text-light"><?= e($b['filename']) ?></strong>
                            </td>
                            <td class="font-monospace text-info"><?= $b['size_kb'] ?> KB</td>
                            <td class="small text-muted font-monospace"><?= $b['created_at'] ?></td>
                            <td>
                                <a href="<?= url('backup') ?>&download=<?= urlencode($b['filename']) ?>" class="btn btn-outline-success btn-sm py-0 px-2" title="Download SQL File">
                                    <i class="fas fa-download me-1"></i> Download
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card bg-dark border-secondary mt-4">
    <div class="card-body p-3 small text-muted">
        <h6 class="text-warning mb-2"><i class="fas fa-shield-alt me-1"></i> Backup & Disaster Recovery Guidelines:</h6>
        <ul class="mb-0 ps-3" style="line-height: 1.8;">
            <li>Backups contain complete table schemas and row datasets encoded in UTF-8 (utf8mb4).</li>
            <li>Backups are stored inside <code>storage/backups/</code> with direct URL protection.</li>
            <li>To restore in a clean environment, use phpMyAdmin or MySQL CLI: <code>mysql -u root -p PTM &lt; backup_file.sql</code>.</li>
        </ul>
    </div>
</div>
