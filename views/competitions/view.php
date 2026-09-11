<?php
/**
 * Competitions Management — Detail View
 */

$competitionId = (int)($_GET['id'] ?? 0);
$competition = Database::fetch(
    "SELECT c.*, u.full_name as creator_name, u2.full_name as updater_name
     FROM competitions c
     LEFT JOIN users u ON c.created_by = u.id
     LEFT JOIN users u2 ON c.updated_by = u2.id
     WHERE c.id = ?",
    [$competitionId]
);

if (!$competition) {
    Session::flash('error', 'Competition not found.');
    redirectTo('competitions');
}

// Compute live metrics for this competition
$totalCandidates = (int)Database::fetchColumn("SELECT COUNT(*) FROM candidates WHERE competition_id = ?", [$competitionId]);
$presentCandidates = (int)Database::fetchColumn("SELECT COUNT(*) FROM attendance WHERE competition_id = ? AND status = 'present'", [$competitionId]);
$absentCandidates = (int)Database::fetchColumn("SELECT COUNT(*) FROM attendance WHERE competition_id = ? AND status = 'absent'", [$competitionId]);
$completedTests = (int)Database::fetchColumn("SELECT COUNT(*) FROM test_results WHERE competition_id = ?", [$competitionId]);

// Recent candidates enrolled in this competition
$recentCandidates = Database::fetchAll(
    "SELECT c.*, a.status as attendance_status, a.check_in_time
     FROM candidates c
     LEFT JOIN attendance a ON c.id = a.candidate_id AND a.competition_id = c.competition_id
     WHERE c.competition_id = ?
     ORDER BY c.id DESC
     LIMIT 10",
    [$competitionId]
);
// Paragraphs assigned to this competition
$assignedParagraphsCount = (int)Database::fetchColumn(
    "SELECT COUNT(*) FROM competition_paragraphs WHERE competition_id = ? AND is_active = 1",
    [$competitionId]
);

// Test settings for this competition
$testSettings = Database::fetch("SELECT * FROM test_settings WHERE competition_id = ?", [$competitionId]);

// Readiness checklist calculations
$candidateReady = $totalCandidates > 0;
$paragraphReady = $assignedParagraphsCount > 0 && ($testSettings && ($testSettings['paragraph_mode'] === 'random' || !empty($testSettings['selected_paragraph_id'])));
$settingsReady = !empty($testSettings);
$compStatusReady = in_array($competition['status'], ['active', 'upcoming'], true);
$isReadyForTest = $candidateReady && $paragraphReady && $settingsReady && $compStatusReady;
?>

<div class="dashboard-header d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <h1 class="page-title mb-0"><?= e($competition['name']) ?></h1>
            <?php
            $badgeClass = match($competition['status']) {
                'active' => 'bg-success',
                'upcoming' => 'bg-info',
                'completed' => 'bg-secondary',
                'cancelled' => 'bg-danger',
                default => 'bg-warning text-dark'
            };
            ?>
            <span class="badge <?= $badgeClass ?>"><?= ucfirst(e($competition['status'])) ?></span>
            <?php if ($isReadyForTest): ?>
                <span class="badge bg-success"><i class="fas fa-check-circle me-1"></i> Ready for Typing Test</span>
            <?php else: ?>
                <span class="badge bg-warning text-dark"><i class="fas fa-exclamation-triangle me-1"></i> Setup in Progress</span>
            <?php endif; ?>
        </div>
        <div class="text-muted small">
            <span class="font-monospace text-info me-3"><i class="fas fa-tag me-1"></i> <?= e($competition['code']) ?></span>
            <span class="me-3"><i class="fas fa-calendar me-1"></i> <?= formatDate($competition['competition_date']) ?></span>
            <?php if ($competition['venue']): ?>
                <span><i class="fas fa-map-marker-alt me-1"></i> <?= e($competition['venue']) ?></span>
            <?php endif; ?>
        </div>
    </div>

    <div class="d-flex flex-wrap gap-2">
        <a href="<?= url('competitions-paragraphs') ?>&id=<?= $competition['id'] ?>" class="btn btn-outline-primary btn-sm">
            <i class="fas fa-paragraph me-1"></i> Paragraphs (<?= $assignedParagraphsCount ?>)
        </a>

        <a href="<?= url('competitions-settings') ?>&id=<?= $competition['id'] ?>" class="btn btn-outline-warning btn-sm">
            <i class="fas fa-sliders-h me-1"></i> Test Settings
        </a>

        <?php if (Auth::hasPermission('competitions.edit')): ?>
            <a href="<?= url('competitions-edit') ?>&id=<?= $competition['id'] ?>" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-edit me-1"></i> Edit
            </a>
        <?php endif; ?>

        <?php if (Auth::hasPermission('candidates.create')): ?>
            <a href="<?= url('candidates-create') ?>&competition_id=<?= $competition['id'] ?>" class="btn btn-primary btn-sm">
                <i class="fas fa-user-plus me-1"></i> Add Candidate
            </a>
        <?php endif; ?>

        <?php if (Auth::hasPermission('attendance.view')): ?>
            <a href="<?= url('attendance') ?>&competition_id=<?= $competition['id'] ?>" class="btn btn-outline-info btn-sm">
                <i class="fas fa-clipboard-check me-1"></i> Attendance
            </a>
        <?php endif; ?>

        <a href="<?= url('candidates-print') ?>&competition_id=<?= $competition['id'] ?>" target="_blank" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-print me-1"></i> Print
        </a>
    </div>
</div>

<!-- Competition Readiness Checklist Banner -->
<div class="content-card mb-4 border <?= $isReadyForTest ? 'border-success' : 'border-warning' ?>">
    <div class="card-body-custom p-3">
        <div class="row g-3 align-items-center">
            <div class="col-md-3">
                <div class="d-flex align-items-center gap-2">
                    <i class="fas fa-2x <?= $candidateReady ? 'fa-check-circle text-success' : 'fa-times-circle text-danger' ?>"></i>
                    <div>
                        <div class="fw-bold small text-light">1. Candidate Setup</div>
                        <div class="small <?= $candidateReady ? 'text-success' : 'text-danger' ?>"><?= $totalCandidates ?> candidate(s) enrolled</div>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="d-flex align-items-center gap-2">
                    <i class="fas fa-2x <?= $paragraphReady ? 'fa-check-circle text-success' : 'fa-times-circle text-danger' ?>"></i>
                    <div>
                        <div class="fw-bold small text-light">2. Paragraph Assignment</div>
                        <div class="small <?= $paragraphReady ? 'text-success' : 'text-danger' ?>">
                            <?= $assignedParagraphsCount > 0 ? "{$assignedParagraphsCount} passage(s) active" : 'No passages assigned' ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="d-flex align-items-center gap-2">
                    <i class="fas fa-2x <?= $settingsReady ? 'fa-check-circle text-success' : 'fa-times-circle text-danger' ?>"></i>
                    <div>
                        <div class="fw-bold small text-light">3. Test Settings</div>
                        <div class="small <?= $settingsReady ? 'text-success' : 'text-danger' ?>">
                            <?= $testSettings ? "Configured (" . round($testSettings['duration_seconds']/60, 1) . " mins)" : 'Not configured' ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-3 text-md-end">
                <?php if ($isReadyForTest): ?>
                    <a href="<?= url('test-portal') ?>&competition_id=<?= $competition['id'] ?>" target="_blank" class="btn btn-success btn-sm w-100">
                        <i class="fas fa-external-link-alt me-1"></i> Open Candidate Portal
                    </a>
                <?php else: ?>
                    <span class="badge bg-warning text-dark p-2 w-100 text-wrap">Complete setup items above to enable test</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Metrics Overview Cards -->
<div class="row g-3 mb-4">
    <div class="col-xl-3 col-sm-6">
        <div class="stat-card stat-card-primary">
            <div class="stat-card-body">
                <div class="stat-icon"><i class="fas fa-users"></i></div>
                <div class="stat-info">
                    <h3 class="stat-value"><?= $totalCandidates ?></h3>
                    <span class="stat-label">Total Candidates</span>
                </div>
            </div>
            <div class="stat-card-footer">
                <a href="<?= url('candidates') ?>&competition_id=<?= $competition['id'] ?>">
                    <span>View Candidates</span>
                    <i class="fas fa-arrow-right"></i>
                </a>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-sm-6">
        <div class="stat-card stat-card-success">
            <div class="stat-card-body">
                <div class="stat-icon"><i class="fas fa-user-check"></i></div>
                <div class="stat-info">
                    <h3 class="stat-value"><?= $presentCandidates ?></h3>
                    <span class="stat-label">Present Candidates</span>
                </div>
            </div>
            <div class="stat-card-footer">
                <a href="<?= url('attendance') ?>&competition_id=<?= $competition['id'] ?>">
                    <span>Manage Attendance</span>
                    <i class="fas fa-arrow-right"></i>
                </a>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-sm-6">
        <div class="stat-card stat-card-warning">
            <div class="stat-card-body">
                <div class="stat-icon"><i class="fas fa-user-times"></i></div>
                <div class="stat-info">
                    <h3 class="stat-value"><?= $absentCandidates ?></h3>
                    <span class="stat-label">Absent Candidates</span>
                </div>
            </div>
            <div class="stat-card-footer">
                <a href="<?= url('attendance') ?>&competition_id=<?= $competition['id'] ?>">
                    <span>View Attendance</span>
                    <i class="fas fa-arrow-right"></i>
                </a>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-sm-6">
        <div class="stat-card stat-card-info">
            <div class="stat-card-body">
                <div class="stat-icon"><i class="fas fa-award"></i></div>
                <div class="stat-info">
                    <h3 class="stat-value"><?= $completedTests ?></h3>
                    <span class="stat-label">Completed Tests</span>
                </div>
            </div>
            <div class="stat-card-footer">
                <a href="<?= url('candidates') ?>&competition_id=<?= $competition['id'] ?>">
                    <span>Test Status</span>
                    <i class="fas fa-arrow-right"></i>
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Competition Details & Instructions -->
<div class="row g-4 mb-4">
    <div class="col-lg-5">
        <div class="content-card h-100">
            <div class="card-header-custom">
                <h5><i class="fas fa-info-circle me-2"></i> Competition Information</h5>
            </div>
            <div class="card-body-custom">
                <ul class="info-list">
                    <li>
                        <span class="info-label">Title</span>
                        <span class="info-value fw-bold text-light"><?= e($competition['name']) ?></span>
                    </li>
                    <li>
                        <span class="info-label">Code</span>
                        <span class="info-value font-monospace text-info"><?= e($competition['code']) ?></span>
                    </li>
                    <li>
                        <span class="info-label">Date</span>
                        <span class="info-value"><?= formatDate($competition['competition_date'], 'l, d F Y') ?></span>
                    </li>
                    <li>
                        <span class="info-label">Timing</span>
                        <span class="info-value">
                            <?= $competition['start_time'] ? date('h:i A', strtotime($competition['start_time'])) : '—' ?>
                            <?= $competition['end_time'] ? ' to ' . date('h:i A', strtotime($competition['end_time'])) : '' ?>
                        </span>
                    </li>
                    <li>
                        <span class="info-label">Venue</span>
                        <span class="info-value"><?= e($competition['venue'] ?: '—') ?></span>
                    </li>
                    <li>
                        <span class="info-label">Max Limit</span>
                        <span class="info-value"><?= $competition['max_candidates'] ? e($competition['max_candidates']) . ' candidates' : 'Unlimited' ?></span>
                    </li>
                    <li>
                        <span class="info-label">Created By</span>
                        <span class="info-value"><?= e($competition['creator_name'] ?: 'System') ?></span>
                    </li>
                    <li>
                        <span class="info-label">Created At</span>
                        <span class="info-value"><?= formatDateTime($competition['created_at']) ?></span>
                    </li>
                </ul>

                <?php if ($competition['description']): ?>
                    <div class="mt-3 pt-3 border-top border-secondary">
                        <label class="small text-muted mb-1">Description:</label>
                        <p class="small mb-0"><?= nl2br(e($competition['description'])) ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="content-card h-100">
            <div class="card-header-custom d-flex justify-content-between align-items-center">
                <h5><i class="fas fa-file-alt me-2"></i> Instructions for Candidates</h5>
            </div>
            <div class="card-body-custom">
                <?php if ($competition['instructions']): ?>
                    <div class="p-3 bg-dark border border-secondary rounded font-monospace small" style="white-space: pre-wrap; line-height: 1.6;"><?= e($competition['instructions']) ?></div>
                <?php else: ?>
                    <p class="text-muted mb-0">No specific candidate instructions configured for this competition.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Candidates Enrolled Table Preview -->
<div class="content-card">
    <div class="card-header-custom d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            <i class="fas fa-users me-2"></i> Registered Candidates Preview
            <span class="badge bg-secondary ms-2"><?= $totalCandidates ?></span>
        </h5>
        <div class="d-flex gap-2">
            <a href="<?= url('candidates-create') ?>&competition_id=<?= $competition['id'] ?>" class="btn btn-sm btn-primary">
                <i class="fas fa-plus me-1"></i> Add Candidate
            </a>
            <a href="<?= url('candidates-import') ?>&competition_id=<?= $competition['id'] ?>" class="btn btn-sm btn-outline-info">
                <i class="fas fa-file-import me-1"></i> CSV Import
            </a>
            <a href="<?= url('candidates') ?>&competition_id=<?= $competition['id'] ?>" class="btn btn-sm btn-outline-secondary">
                View All <i class="fas fa-arrow-right ms-1"></i>
            </a>
        </div>
    </div>
    <div class="card-body-custom p-0">
        <?php if (empty($recentCandidates)): ?>
            <div class="text-center text-muted py-5">
                <i class="fas fa-user-slash fa-3x mb-2 text-secondary"></i>
                <p>No candidates registered yet for this competition.</p>
                <div class="d-flex justify-content-center gap-2">
                    <a href="<?= url('candidates-create') ?>&competition_id=<?= $competition['id'] ?>" class="btn btn-sm btn-primary">
                        <i class="fas fa-user-plus me-1"></i> Register Candidate
                    </a>
                    <a href="<?= url('candidates-import') ?>&competition_id=<?= $competition['id'] ?>" class="btn btn-sm btn-outline-info">
                        <i class="fas fa-file-import me-1"></i> Import from CSV
                    </a>
                </div>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-dark table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Reg #</th>
                            <th>Roll #</th>
                            <th>Full Name</th>
                            <th>Father Name</th>
                            <th>Course & Shift</th>
                            <th class="text-center">Attendance</th>
                            <th class="text-center">Status</th>
                            <th class="text-end pe-3">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentCandidates as $cand): ?>
                            <tr>
                                <td><span class="font-monospace text-info"><?= e($cand['registration_number']) ?></span></td>
                                <td><span class="fw-bold"><?= e($cand['roll_number'] ?: '—') ?></span></td>
                                <td>
                                    <div class="fw-bold text-light"><?= e($cand['full_name']) ?></div>
                                    <?php if ($cand['phone']): ?>
                                        <div class="small text-muted"><?= e($cand['phone']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><?= e($cand['father_name'] ?: '—') ?></td>
                                <td>
                                    <div><?= e($cand['course'] ?: '—') ?></div>
                                    <div class="small text-muted"><?= e(ucfirst($cand['shift'] ?? '')) ?></div>
                                </td>
                                <td class="text-center">
                                    <?php if ($cand['attendance_status'] === 'present'): ?>
                                        <span class="badge bg-success"><i class="fas fa-check me-1"></i> Present</span>
                                    <?php elseif ($cand['attendance_status'] === 'absent'): ?>
                                        <span class="badge bg-danger"><i class="fas fa-times me-1"></i> Absent</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Unmarked</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-dark border border-secondary"><?= ucfirst(e($cand['status'])) ?></span>
                                </td>
                                <td class="text-end pe-3">
                                    <a href="<?= url('candidates-view') ?>&id=<?= $cand['id'] ?>" class="btn btn-outline-info btn-sm" title="View Profile">
                                        <i class="fas fa-eye"></i>
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
