<?php
/**
 * Candidate Management — Profile / Detail View
 */

$candidateId = (int)($_GET['id'] ?? 0);
$candidate = Database::fetch(
    "SELECT c.*, cmp.name as competition_name, cmp.code as competition_code, cmp.competition_date, cmp.venue,
            u.full_name as registrar_name
     FROM candidates c
     JOIN competitions cmp ON c.competition_id = cmp.id
     LEFT JOIN users u ON c.registered_by = u.id
     WHERE c.id = ?",
    [$candidateId]
);

if (!$candidate) {
    Session::flash('error', 'Candidate not found.');
    redirectTo('candidates');
}

// Attendance record
$attendance = Database::fetch(
    "SELECT a.*, u.full_name as marked_by_name
     FROM attendance a
     LEFT JOIN users u ON a.marked_by = u.id
     WHERE a.candidate_id = ? AND a.competition_id = ?",
    [$candidateId, $candidate['competition_id']]
);
?>

<div class="dashboard-header d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <h1 class="page-title mb-0"><?= e($candidate['full_name']) ?></h1>
            <span class="badge bg-dark border border-secondary"><?= e($candidate['registration_number']) ?></span>
            <?php
            $stBadge = match($candidate['status']) {
                'present' => 'bg-success',
                'completed' => 'bg-info',
                'test_started' => 'bg-primary',
                'disqualified' => 'bg-danger',
                'absent' => 'bg-secondary',
                default => 'bg-warning text-dark'
            };
            ?>
            <span class="badge <?= $stBadge ?>"><?= ucfirst(str_replace('_', ' ', e($candidate['status']))) ?></span>
        </div>
        <p class="page-subtitle">Candidate profile for <?= e($candidate['competition_name']) ?> (<?= e($candidate['competition_code']) ?>)</p>
    </div>

    <div class="d-flex flex-wrap gap-2">
        <?php if (Auth::hasPermission('candidates.edit')): ?>
            <a href="<?= url('candidates-edit') ?>&id=<?= $candidate['id'] ?>" class="btn btn-outline-primary btn-sm">
                <i class="fas fa-edit me-1"></i> Edit Candidate
            </a>
        <?php endif; ?>

        <?php if (Auth::hasPermission('attendance.view')): ?>
            <a href="<?= url('attendance') ?>&competition_id=<?= $candidate['competition_id'] ?>" class="btn btn-outline-warning btn-sm">
                <i class="fas fa-clipboard-check me-1"></i> Attendance
            </a>
        <?php endif; ?>

        <a href="<?= url('candidates') ?>&competition_id=<?= $candidate['competition_id'] ?>" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-arrow-left me-1"></i> Candidates List
        </a>
    </div>
</div>

<div class="row g-4">
    <!-- Candidate Info Card (Left) -->
    <div class="col-lg-4">
        <div class="content-card text-center mb-4">
            <div class="card-body-custom py-4">
                <div class="mb-3 d-flex justify-content-center">
                    <?php if (!empty($candidate['photo'])): ?>
                        <img src="<?= upload(e($candidate['photo'])) ?>" alt="Candidate Photo" 
                             class="rounded-circle shadow-sm object-fit-cover" style="width: 120px; height: 120px; border: 3px solid var(--accent-primary);">
                    <?php else: ?>
                        <div class="rounded-circle d-flex align-items-center justify-content-center bg-secondary text-light fw-bold mx-auto shadow-sm" 
                             style="width: 120px; height: 120px; font-size: 40px; border: 3px solid var(--accent-primary);">
                            <?= strtoupper(substr($candidate['full_name'], 0, 1)) ?>
                        </div>
                    <?php endif; ?>
                </div>

                <h4 class="text-light mb-1"><?= e($candidate['full_name']) ?></h4>
                <div class="text-muted small mb-2"><?= e($candidate['father_name'] ? "S/D/O " . $candidate['father_name'] : 'Participant') ?></div>

                <div class="d-flex justify-content-center gap-2 mb-3">
                    <span class="badge bg-primary"><?= e($candidate['course'] ?: 'Typing') ?></span>
                    <span class="badge bg-dark border border-secondary"><?= e(ucfirst($candidate['shift'] ?? 'Morning')) ?></span>
                </div>

                <hr class="border-secondary">

                <div class="text-start">
                    <div class="d-flex justify-content-between py-1 border-bottom border-secondary">
                        <span class="text-muted small">Registration #</span>
                        <span class="font-monospace text-info small fw-bold"><?= e($candidate['registration_number']) ?></span>
                    </div>
                    <div class="d-flex justify-content-between py-1 border-bottom border-secondary">
                        <span class="text-muted small">Roll Number</span>
                        <span class="fw-bold small"><?= e($candidate['roll_number'] ?: '—') ?></span>
                    </div>
                    <div class="d-flex justify-content-between py-1 border-bottom border-secondary">
                        <span class="text-muted small">Seat Number</span>
                        <span class="small"><?= e($candidate['seat_number'] ?: '—') ?></span>
                    </div>
                    <div class="d-flex justify-content-between py-1 border-bottom border-secondary">
                        <span class="text-muted small">Branch</span>
                        <span class="small"><?= e($candidate['branch'] ?: 'Main') ?></span>
                    </div>
                    <div class="d-flex justify-content-between py-1 border-bottom border-secondary">
                        <span class="text-muted small">Batch</span>
                        <span class="small"><?= e($candidate['batch'] ?: '—') ?></span>
                    </div>
                    <div class="d-flex justify-content-between py-1">
                        <span class="text-muted small">Gender</span>
                        <span class="small"><?= ucfirst(e($candidate['gender'] ?? '—')) ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Attendance Status Summary Card -->
        <div class="content-card">
            <div class="card-header-custom">
                <h5><i class="fas fa-calendar-check me-2"></i> Attendance Status</h5>
            </div>
            <div class="card-body-custom">
                <?php if ($attendance): ?>
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="display-6">
                            <?php if ($attendance['status'] === 'present'): ?>
                                <i class="fas fa-check-circle text-success"></i>
                            <?php elseif ($attendance['status'] === 'absent'): ?>
                                <i class="fas fa-times-circle text-danger"></i>
                            <?php else: ?>
                                <i class="fas fa-clock text-warning"></i>
                            <?php endif; ?>
                        </div>
                        <div>
                            <h5 class="mb-0 text-capitalize"><?= e($attendance['status']) ?></h5>
                            <small class="text-muted">
                                <?= $attendance['check_in_time'] ? 'Check-in: ' . formatDateTime($attendance['check_in_time']) : 'Marked: ' . formatDateTime($attendance['created_at']) ?>
                            </small>
                        </div>
                    </div>
                    <?php if ($attendance['marked_by_name']): ?>
                        <div class="small text-muted mb-2">Marked by: <strong><?= e($attendance['marked_by_name']) ?></strong></div>
                    <?php endif; ?>
                    <?php if ($attendance['remarks']): ?>
                        <div class="small bg-dark p-2 rounded border border-secondary">Remarks: <?= e($attendance['remarks']) ?></div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="text-muted text-center py-3">
                        <i class="fas fa-clock fa-2x mb-2 text-secondary"></i>
                        <p class="mb-0 small">Attendance not marked yet.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Right Side: Details & Competition Overview -->
    <div class="col-lg-8">
        <!-- Contact & Profile Details -->
        <div class="content-card mb-4">
            <div class="card-header-custom">
                <h5><i class="fas fa-address-book me-2"></i> Contact & Identity Details</h5>
            </div>
            <div class="card-body-custom">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="text-muted small mb-1">Mobile Number</label>
                        <div class="fw-bold text-light"><?= e($candidate['phone'] ?: '—') ?></div>
                    </div>
                    <div class="col-md-6">
                        <label class="text-muted small mb-1">Email Address</label>
                        <div class="fw-bold text-light"><?= e($candidate['email'] ?: '—') ?></div>
                    </div>
                    <div class="col-md-6">
                        <label class="text-muted small mb-1">CNIC / B-Form</label>
                        <div class="fw-bold text-light"><?= e($candidate['cnic'] ?: '—') ?></div>
                    </div>
                    <div class="col-md-6">
                        <label class="text-muted small mb-1">Registered At</label>
                        <div class="fw-bold text-light"><?= formatDateTime($candidate['created_at']) ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Competition Overview -->
        <div class="content-card mb-4">
            <div class="card-header-custom d-flex justify-content-between align-items-center">
                <h5><i class="fas fa-trophy me-2"></i> Enrolled Competition</h5>
                <a href="<?= url('competitions-view') ?>&id=<?= $candidate['competition_id'] ?>" class="btn btn-outline-info btn-sm">
                    View Competition <i class="fas fa-arrow-right ms-1"></i>
                </a>
            </div>
            <div class="card-body-custom">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="text-muted small mb-1">Competition Title</label>
                        <div class="fw-bold text-light"><?= e($candidate['competition_name']) ?></div>
                    </div>
                    <div class="col-md-3">
                        <label class="text-muted small mb-1">Code</label>
                        <div class="font-monospace text-info fw-bold"><?= e($candidate['competition_code']) ?></div>
                    </div>
                    <div class="col-md-3">
                        <label class="text-muted small mb-1">Date</label>
                        <div><?= formatDate($candidate['competition_date']) ?></div>
                    </div>
                    <div class="col-12">
                        <label class="text-muted small mb-1">Venue</label>
                        <div><i class="fas fa-map-marker-alt me-1 text-danger"></i> <?= e($candidate['venue'] ?: 'Marr') ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
