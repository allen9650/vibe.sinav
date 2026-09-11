<?php
/**
 * Competitions Management — Edit Competition
 */

$competitionId = (int)($_GET['id'] ?? 0);
$competition = Database::fetch("SELECT * FROM competitions WHERE id = ?", [$competitionId]);

if (!$competition) {
    Session::flash('error', 'Competition not found.');
    redirectTo('competitions');
}

$errors = [];
$formData = [
    'name'             => $competition['name'],
    'code'             => $competition['code'],
    'description'      => $competition['description'] ?? '',
    'competition_date' => $competition['competition_date'],
    'start_time'       => $competition['start_time'] ? substr($competition['start_time'], 0, 5) : '',
    'end_time'         => $competition['end_time'] ? substr($competition['end_time'], 0, 5) : '',
    'venue'            => $competition['venue'] ?? '',
    'instructions'     => $competition['instructions'] ?? '',
    'max_candidates'   => $competition['max_candidates'] ?? '',
    'status'           => $competition['status'],
];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    CSRF::validateOrFail();

    $formData = [
        'name'             => trim($_POST['name'] ?? ''),
        'code'             => strtoupper(trim($_POST['code'] ?? '')),
        'description'      => trim($_POST['description'] ?? ''),
        'competition_date' => trim($_POST['competition_date'] ?? ''),
        'start_time'       => trim($_POST['start_time'] ?? ''),
        'end_time'         => trim($_POST['end_time'] ?? ''),
        'venue'            => trim($_POST['venue'] ?? ''),
        'instructions'     => trim($_POST['instructions'] ?? ''),
        'max_candidates'   => trim($_POST['max_candidates'] ?? ''),
        'status'           => trim($_POST['status'] ?? 'draft'),
    ];

    // Validate inputs
    $validator = new Validator($formData);
    $validator->required('name', 'Competition Title')
              ->required('code', 'Competition Code')
              ->required('competition_date', 'Competition Date');

    if ($validator->fails()) {
        $errors = $validator->errors();
    }

    // Validate unique code (excluding current record)
    if (empty($errors['code'])) {
        $existing = Database::fetch("SELECT id FROM competitions WHERE code = ? AND id != ?", [$formData['code'], $competitionId]);
        if ($existing) {
            $errors['code'] = "Competition code '{$formData['code']}' is already in use by another competition.";
        }
    }

    // Validate start & end time
    if (!empty($formData['start_time']) && !empty($formData['end_time'])) {
        if (strtotime($formData['start_time']) >= strtotime($formData['end_time'])) {
            $errors['end_time'] = "End time must be after start time.";
        }
    }

    // Validate status
    $allowedStatuses = ['draft', 'upcoming', 'active', 'completed', 'cancelled'];
    if (!in_array($formData['status'], $allowedStatuses, true)) {
        $errors['status'] = "Invalid status selected.";
    }

    if (empty($errors)) {
        $oldData = $competition;

        Database::update('competitions', [
            'name'             => $formData['name'],
            'code'             => $formData['code'],
            'description'      => $formData['description'] ?: null,
            'competition_date' => $formData['competition_date'],
            'start_time'       => $formData['start_time'] ?: null,
            'end_time'         => $formData['end_time'] ?: null,
            'venue'            => $formData['venue'] ?: null,
            'instructions'     => $formData['instructions'] ?: null,
            'max_candidates'   => $formData['max_candidates'] !== '' ? (int)$formData['max_candidates'] : null,
            'status'           => $formData['status'],
            'updated_by'       => Session::get('user_id'),
        ], 'id = ?', [$competitionId]);

        AuditLog::log('competition_updated', 'competitions', 
            "Updated competition '{$formData['name']}' ({$formData['code']})", 
            Session::get('user_id'), 
            $oldData, 
            $formData
        );

        Session::flash('success', "Competition '{$formData['name']}' updated successfully.");
        CSRF::regenerate();
        redirectTo('competitions-view&id=' . $competitionId);
    }
}
?>

<div class="dashboard-header mb-4">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1 class="page-title">
                <i class="fas fa-edit me-2 text-primary"></i> Edit Competition
            </h1>
            <p class="page-subtitle">Update competition settings and scheduling</p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= url('competitions-view') ?>&id=<?= $competition['id'] ?>" class="btn btn-outline-info">
                <i class="fas fa-eye me-1"></i> View Details
            </a>
            <a href="<?= url('competitions') ?>" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i> Back to List
            </a>
        </div>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-lg-10">
        <div class="content-card">
            <div class="card-header-custom">
                <h5><i class="fas fa-calendar-alt me-2"></i> Edit Competition #<?= $competition['id'] ?></h5>
            </div>
            <div class="card-body-custom">
                <form method="POST" action="<?= url('competitions-edit') ?>&id=<?= $competition['id'] ?>" id="editCompetitionForm">
                    <?= CSRF::field() ?>

                    <div class="row g-3 mb-3">
                        <div class="col-md-8">
                            <label for="name" class="form-label">Competition Title <span class="text-danger">*</span></label>
                            <input type="text" class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>" 
                                   id="name" name="name" value="<?= e($formData['name']) ?>" required>
                            <?php if (isset($errors['name'])): ?>
                                <div class="invalid-feedback"><?= e($errors['name']) ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="col-md-4">
                            <label for="code" class="form-label">Competition Code <span class="text-danger">*</span></label>
                            <input type="text" class="form-control text-uppercase font-monospace <?= isset($errors['code']) ? 'is-invalid' : '' ?>" 
                                   id="code" name="code" value="<?= e($formData['code']) ?>" required>
                            <?php if (isset($errors['code'])): ?>
                                <div class="invalid-feedback"><?= e($errors['code']) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label for="competition_date" class="form-label">Competition Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control <?= isset($errors['competition_date']) ? 'is-invalid' : '' ?>" 
                                   id="competition_date" name="competition_date" value="<?= e($formData['competition_date']) ?>" required>
                            <?php if (isset($errors['competition_date'])): ?>
                                <div class="invalid-feedback"><?= e($errors['competition_date']) ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="col-md-4">
                            <label for="start_time" class="form-label">Start Time</label>
                            <input type="time" class="form-control <?= isset($errors['start_time']) ? 'is-invalid' : '' ?>" 
                                   id="start_time" name="start_time" value="<?= e($formData['start_time']) ?>">
                        </div>

                        <div class="col-md-4">
                            <label for="end_time" class="form-label">End Time</label>
                            <input type="time" class="form-control <?= isset($errors['end_time']) ? 'is-invalid' : '' ?>" 
                                   id="end_time" name="end_time" value="<?= e($formData['end_time']) ?>">
                            <?php if (isset($errors['end_time'])): ?>
                                <div class="invalid-feedback d-block"><?= e($errors['end_time']) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="venue" class="form-label">Venue / Hall</label>
                            <input type="text" class="form-control" id="venue" name="venue" 
                                   value="<?= e($formData['venue']) ?>">
                        </div>

                        <div class="col-md-3">
                            <label for="max_candidates" class="form-label">Max Candidates</label>
                            <input type="number" class="form-control" id="max_candidates" name="max_candidates" 
                                   value="<?= e($formData['max_candidates']) ?>" min="1">
                        </div>

                        <div class="col-md-3">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select <?= isset($errors['status']) ? 'is-invalid' : '' ?>" id="status" name="status">
                                <option value="draft" <?= $formData['status'] === 'draft' ? 'selected' : '' ?>>Draft</option>
                                <option value="upcoming" <?= $formData['status'] === 'upcoming' ? 'selected' : '' ?>>Upcoming</option>
                                <option value="active" <?= $formData['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                                <option value="completed" <?= $formData['status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
                                <option value="cancelled" <?= $formData['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                            </select>
                            <?php if (isset($errors['status'])): ?>
                                <div class="invalid-feedback"><?= e($errors['status']) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="2"><?= e($formData['description']) ?></textarea>
                    </div>

                    <div class="mb-4">
                        <label for="instructions" class="form-label">Candidate Instructions</label>
                        <textarea class="form-control font-monospace small" id="instructions" name="instructions" rows="4"><?= e($formData['instructions']) ?></textarea>
                    </div>

                    <div class="d-flex justify-content-end gap-2 border-top border-secondary pt-3">
                        <a href="<?= url('competitions') ?>" class="btn btn-outline-secondary">
                            <i class="fas fa-times me-1"></i> Cancel
                        </a>
                        <button type="submit" class="btn btn-primary" id="btnUpdateCompetition">
                            <i class="fas fa-save me-1"></i> Update Competition
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
