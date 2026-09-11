<?php
/**
 * System Diagnostics & Health Check
 */

Middleware::requireAnyRole(['super-admin', 'admin']);

$pdo = Database::getInstance();
$dbVersion = $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);

$requiredExtensions = [
    'pdo_mysql' => 'PDO MySQL Driver',
    'mbstring'  => 'Multibyte String Support (UTF-8)',
    'session'   => 'Session Management',
    'json'      => 'JSON Serialization',
    'openssl'   => 'OpenSSL Cryptography',
    'filter'    => 'Data Filtering & Sanitization',
];

$extStatus = [];
$allExtsOk = true;
foreach ($requiredExtensions as $ext => $label) {
    $loaded = extension_loaded($ext);
    $extStatus[] = [
        'ext'    => $ext,
        'label'  => $label,
        'loaded' => $loaded,
    ];
    if (!$loaded) $allExtsOk = false;
}

$dirsToCheck = [
    ROOT_PATH . '/storage'          => 'Storage Root Directory',
    ROOT_PATH . '/storage/backups'  => 'Database Backups Directory',
    ROOT_PATH . '/storage/uploads'  => 'Candidate Uploads Directory',
];

$dirStatus = [];
$allDirsOk = true;
foreach ($dirsToCheck as $path => $name) {
    $writable = is_writable($path);
    $dirStatus[] = [
        'path'     => $path,
        'name'     => $name,
        'writable' => $writable,
        'exists'   => is_dir($path),
    ];
    if (!$writable) $allDirsOk = false;
}

$lockFile = ROOT_PATH . '/storage/installed.lock';
$isLocked = file_exists($lockFile);
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1"><i class="fas fa-heartbeat text-danger me-2"></i> System Diagnostics & Environment Health</h4>
        <div class="text-muted small">Server runtime parameters, required PHP extensions, directory permissions, and security locks</div>
    </div>
    <div>
        <a href="<?= url('backup') ?>" class="btn btn-outline-warning btn-sm">
            <i class="fas fa-database me-1"></i> Database Backups
        </a>
    </div>
</div>

<div class="row g-4">
    <!-- Server Runtime Card -->
    <div class="col-md-6">
        <div class="card bg-dark border-secondary h-100">
            <div class="card-header border-secondary text-light fw-bold">
                <i class="fas fa-server me-2 text-primary"></i> Runtime Environment
            </div>
            <div class="card-body p-0">
                <table class="table table-dark table-borderless mb-0 small">
                    <tbody>
                        <tr>
                            <td class="text-muted">PHP Version:</td>
                            <td class="text-end fw-bold font-monospace text-light">
                                <?= PHP_VERSION ?>
                                <?php if (version_compare(PHP_VERSION, '8.0.0', '>=')): ?>
                                    <span class="badge bg-success ms-1">Supported</span>
                                <?php else: ?>
                                    <span class="badge bg-danger ms-1">Upgrade Recommended</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td class="text-muted">Database Engine:</td>
                            <td class="text-end font-monospace text-light">MySQL / MariaDB v<?= e($dbVersion) ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Application Environment:</td>
                            <td class="text-end"><span class="badge bg-primary"><?= strtoupper(APP_ENV ?? 'development') ?></span></td>
                        </tr>
                        <tr>
                            <td class="text-muted">System Timezone:</td>
                            <td class="text-end font-monospace text-light"><?= date_default_timezone_get() ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Server Time:</td>
                            <td class="text-end font-monospace text-light"><?= date('Y-m-d H:i:s') ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Memory Limit:</td>
                            <td class="text-end font-monospace text-light"><?= ini_get('memory_limit') ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Max Execution Time:</td>
                            <td class="text-end font-monospace text-light"><?= ini_get('max_execution_time') ?>s</td>
                        </tr>
                        <tr class="border-top border-secondary">
                            <td class="text-muted">Installation Safety Lock:</td>
                            <td class="text-end">
                                <?php if ($isLocked): ?>
                                    <span class="badge bg-success"><i class="fas fa-lock me-1"></i> ACTIVE (installed.lock)</span>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark"><i class="fas fa-unlock me-1"></i> UNLOCKED</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Required Extensions Card -->
    <div class="col-md-6">
        <div class="card bg-dark border-secondary h-100">
            <div class="card-header border-secondary text-light fw-bold">
                <i class="fas fa-puzzle-piece me-2 text-info"></i> Required PHP Extensions
            </div>
            <div class="card-body p-0">
                <table class="table table-dark table-borderless mb-0 small">
                    <tbody>
                        <?php foreach ($extStatus as $ext): ?>
                            <tr>
                                <td>
                                    <strong class="font-monospace text-light"><?= e($ext['ext']) ?></strong>
                                    <div class="text-muted small"><?= e($ext['label']) ?></div>
                                </td>
                                <td class="text-end align-middle">
                                    <?php if ($ext['loaded']): ?>
                                        <span class="badge bg-success"><i class="fas fa-check me-1"></i> Loaded</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger"><i class="fas fa-times me-1"></i> Missing</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Storage Directories Writable Status -->
    <div class="col-12">
        <div class="card bg-dark border-secondary">
            <div class="card-header border-secondary text-light fw-bold">
                <i class="fas fa-folder-open me-2 text-warning"></i> Storage & Uploads Write Permissions
            </div>
            <div class="card-body p-0">
                <table class="table table-dark table-bordered mb-0 small align-middle">
                    <thead class="table-secondary text-light text-uppercase">
                        <tr>
                            <th>Directory Purpose</th>
                            <th>Filesystem Path</th>
                            <th style="width: 130px;" class="text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($dirStatus as $d): ?>
                            <tr>
                                <td class="fw-bold text-light"><?= e($d['name']) ?></td>
                                <td class="font-monospace small text-muted"><?= e($d['path']) ?></td>
                                <td class="text-center">
                                    <?php if ($d['writable']): ?>
                                        <span class="badge bg-success"><i class="fas fa-check-circle me-1"></i> Writable</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger"><i class="fas fa-times-circle me-1"></i> Not Writable</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
