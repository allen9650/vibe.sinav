<?php
/**
 * Competitions Management — Duplicate Competition
 */

$sourceId = (int)($_GET['id'] ?? 0);
$source = Database::fetch("SELECT * FROM competitions WHERE id = ?", [$sourceId]);

if (!$source) {
    Session::flash('error', 'Source competition not found.');
    redirectTo('competitions');
}

$errors = [];
$formData = [
    'name'             => $source['name'] . ' (Copy)',
    'code'             => $source['code'] . '-COPY',
    'description'      => $source['description'] ?? '',
    'competition_date' => date('Y-m-d'),
    'start_time'       => $source['start_time'] ? substr($source['start_time'], 0, 5) : '',
    'end_time'         => $source['end_time'] ? substr($source['end_time'], 0, 5) : '',
    'venue'            => $source['venue'] ?? '',
    'instructions'     => $source['instructions'] ?? '',
    'max_candidates'   => $source['max_candidates'] ?? '',
    'status'           => 'draft',
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
        'status'           => 'draft',
    ];

    // Validate inputs
    $validator = new Validator($formData);
    $validator->required('name', 'Competition Title')
              ->required('code', 'Competition Code')
              ->required('competition_date', 'Competition Date');

    if ($validator->fails()) {
        $errors = $validator->errors();
    }

    // Validate unique code
    if (empty($errors['code'])) {
        $existing = Database::fetch("SELECT id FROM competitions WHERE code = ?", [$formData['code']]);
        if ($existing) {
            $errors['code'] = "Competition code '{$formData['code']}' is already in use.";
        }
    }

    if (empty($errors)) {
        $newId = Database::insert('competitions', [
            'name'             => $formData['name'],
            'code'             => $formData['code'],
            'description'      => $formData['description'] ?: null,
            'competition_date' => $formData['competition_date'],
            'start_time'       => $formData['start_time'] ?: null,
            'end_time'         => $formData['end_time'] ?: null,
            'venue'            => $formData['venue'] ?: null,
            'instructions'     => $formData['instructions'] ?: null,
            'max_candidates'   => $formData['max_candidates'] !== '' ? (int)$formData['max_candidates'] : null,
            'status'           => 'draft',
            'created_by'       => Session::get('user_id'),
        ]);

        AuditLog::log('competition_duplicated', 'competitions', 
            "Duplicated competition #{$sourceId} into new competition '{$formData['name']}' ({$formData['code']})", 
            Session::get('user_id'), 
            ['source_id' => $sourceId], 
            $formData
        );

        Session::flash('success', "Competition duplicated successfully as Draft.");
        CSRF::regenerate();
        redirectTo('competitions-view&id=' . $newId);
    }
}
?>

<div class="dashboard-header mb-4">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1 class="page-title">
                <i class="fas fa-copy me-2 text-warning"></i> Duplicate Competition
            </h1>
            <p class="page-subtitle">Clone configuration from '<?= e($source['name']) ?>'</p>
        </div>
        <div>
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
                <h5><i class="fas fa-clone me-2"></i> Configure Duplicated Competition</h5>
            </div>
            <div class="card-body-custom">
                <div class="alert alert-info mb-4">
                    <i class="fas fa-info-circle me-2"></i>
                    Cloning from <strong><?= e($source['name']) ?></strong> (<?= e($source['code']) ?>). Candidates and attendance records will not be copied.
                </div>

                <form method="POST" action="<?= url('competitions-duplicate') ?>&id=<?= $source['id'] ?>" id="duplicateCompetitionForm">
                    <?= CSRF::field() ?>

                    <div class="row g-3 mb-3">
                        <div class="col-md-8">
                            <label for="name" class="form-label">New Competition Title <span class="text-danger">*</span></label>
                            <input type="text" class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>" 
                                   id="name" name="name" value="<?= e($formData['name']) ?>" required autofocus>
                            <?php if (isset($errors['name'])): ?>
                                <div class="invalid-feedback"><?= e($errors['name']) ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="col-md-4">
                            <label for="code" class="form-label">New Competition Code <span class="text-danger">*</span></label>
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
                            <input type="time" class="form-control" id="start_time" name="start_time" value="<?= e($formData['start_time']) ?>">
                        </div>

                        <div class="col-md-4">
                            <label for="end_time" class="form-label">End Time</label>
                            <input type="time" class="form-control" id="end_time" name="end_time" value="<?= e($formData['end_time']) ?>">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-8">
                            <label for="venue" class="form-label">Venue / Hall</label>
                            <input type="text" class="form-control" id="venue" name="venue" value="<?= e($formData['venue']) ?>">
                        </div>

                        <div class="col-md-4">
                            <label for="max_candidates" class="form-label">Max Candidates</label>
                            <input type="number" class="form-control" id="max_candidates" name="max_candidates" value="<?= e($formData['max_candidates']) ?>" min="1">
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
                        <button type="submit" class="btn btn-warning text-dark" id="btnConfirmDuplicate">
                            <i class="fas fa-copy me-1"></i> Create Duplicate
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
