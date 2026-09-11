<?php
/**
 * Candidate Management — Edit Candidate
 */

$candidateId = (int)($_GET['id'] ?? 0);
$candidate = Database::fetch("SELECT * FROM candidates WHERE id = ?", [$candidateId]);

if (!$candidate) {
    Session::flash('error', 'Candidate not found.');
    redirectTo('candidates');
}

$competitions = Database::fetchAll("SELECT id, name, code, competition_date FROM competitions ORDER BY competition_date DESC, id DESC");

$errors = [];
$formData = [
    'competition_id'      => (int)$candidate['competition_id'],
    'registration_number' => $candidate['registration_number'],
    'roll_number'         => $candidate['roll_number'] ?? '',
    'seat_number'         => $candidate['seat_number'] ?? '',
    'full_name'           => $candidate['full_name'],
    'father_name'         => $candidate['father_name'] ?? '',
    'cnic'                => $candidate['cnic'] ?? '',
    'phone'               => $candidate['phone'] ?? '',
    'email'               => $candidate['email'] ?? '',
    'gender'              => $candidate['gender'] ?? 'male',
    'course'              => $candidate['course'] ?? '',
    'batch'               => $candidate['batch'] ?? '',
    'shift'               => $candidate['shift'] ?? 'morning',
    'branch'              => $candidate['branch'] ?? '',
    'status'              => $candidate['status'],
];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    CSRF::validateOrFail();

    $formData = [
        'competition_id'      => (int)($_POST['competition_id'] ?? $candidate['competition_id']),
        'registration_number' => trim($_POST['registration_number'] ?? ''),
        'roll_number'         => trim($_POST['roll_number'] ?? ''),
        'seat_number'         => trim($_POST['seat_number'] ?? ''),
        'full_name'           => trim($_POST['full_name'] ?? ''),
        'father_name'         => trim($_POST['father_name'] ?? ''),
        'cnic'                => trim($_POST['cnic'] ?? ''),
        'phone'               => trim($_POST['phone'] ?? ''),
        'email'               => trim($_POST['email'] ?? ''),
        'gender'              => trim($_POST['gender'] ?? 'male'),
        'course'              => trim($_POST['course'] ?? ''),
        'batch'               => trim($_POST['batch'] ?? ''),
        'shift'               => trim($_POST['shift'] ?? ''),
        'branch'              => trim($_POST['branch'] ?? ''),
        'status'              => trim($_POST['status'] ?? $candidate['status']),
    ];

    // Validate inputs
    $validator = new Validator($formData);
    $validator->required('competition_id', 'Competition')
              ->required('full_name', 'Full Name')
              ->required('registration_number', 'Registration Number');

    if (!empty($formData['email'])) {
        $validator->email('email', 'Email Address');
    }

    if ($validator->fails()) {
        $errors = $validator->errors();
    }

    // Check unique registration number in database (excluding current)
    if (empty($errors['registration_number'])) {
        $existingReg = Database::fetch(
            "SELECT id FROM candidates WHERE registration_number = ? AND id != ?",
            [$formData['registration_number'], $candidateId]
        );
        if ($existingReg) {
            $errors['registration_number'] = "Registration Number '{$formData['registration_number']}' is already assigned to another candidate.";
        }
    }

    // Check unique roll number in competition (excluding current)
    if (!empty($formData['roll_number']) && empty($errors['roll_number'])) {
        $existingRoll = Database::fetch(
            "SELECT id FROM candidates WHERE competition_id = ? AND roll_number = ? AND id != ?",
            [$formData['competition_id'], $formData['roll_number'], $candidateId]
        );
        if ($existingRoll) {
            $errors['roll_number'] = "Roll Number '{$formData['roll_number']}' is already in use in this competition.";
        }
    }

    // Handle photo removal or update
    $photoPath = $candidate['photo'];

    if (!empty($_POST['remove_photo']) && $_POST['remove_photo'] == '1') {
        if ($photoPath && file_exists(UPLOADS_PATH . '/' . $photoPath)) {
            @unlink(UPLOADS_PATH . '/' . $photoPath);
        }
        $photoPath = null;
    }

    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['photo'];
        $allowedExts = ['jpg', 'jpeg', 'png', 'webp'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];

        if (!in_array($ext, $allowedExts, true) || !in_array($mime, $allowedMimes, true)) {
            $errors['photo'] = "Invalid image file. Only JPG, PNG, and WebP are allowed.";
        } elseif ($file['size'] > 2 * 1024 * 1024) {
            $errors['photo'] = "Photo size must not exceed 2MB.";
        } else {
            $uploadDir = UPLOADS_PATH . '/candidates';
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0755, true);
            }

            $filename = 'cand_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $dest = $uploadDir . '/' . $filename;

            if (move_uploaded_file($file['tmp_name'], $dest)) {
                // Delete old photo
                if ($photoPath && file_exists(UPLOADS_PATH . '/' . $photoPath)) {
                    @unlink(UPLOADS_PATH . '/' . $photoPath);
                }
                $photoPath = 'candidates/' . $filename;
            } else {
                $errors['photo'] = "Failed to upload photo.";
            }
        }
    }

    if (empty($errors)) {
        $oldData = $candidate;

        Database::update('candidates', [
            'competition_id'      => $formData['competition_id'],
            'registration_number' => $formData['registration_number'],
            'roll_number'         => $formData['roll_number'] ?: null,
            'seat_number'         => $formData['seat_number'] ?: null,
            'full_name'           => $formData['full_name'],
            'father_name'         => $formData['father_name'] ?: null,
            'cnic'                => $formData['cnic'] ?: null,
            'phone'               => $formData['phone'] ?: null,
            'email'               => $formData['email'] ?: null,
            'gender'              => $formData['gender'] ?: null,
            'course'              => $formData['course'] ?: null,
            'batch'               => $formData['batch'] ?: null,
            'shift'               => $formData['shift'] ?: null,
            'branch'              => $formData['branch'] ?: null,
            'photo'               => $photoPath,
            'status'              => $formData['status'],
        ], 'id = ?', [$candidateId]);

        AuditLog::log('candidate_updated', 'candidates', 
            "Updated candidate #{$candidateId} '{$formData['full_name']}' ({$formData['registration_number']})", 
            Session::get('user_id'), 
            $oldData, 
            $formData
        );

        Session::flash('success', "Candidate '{$formData['full_name']}' updated successfully.");
        CSRF::regenerate();
        redirectTo('candidates-view&id=' . $candidateId);
    }
}
?>

<div class="dashboard-header mb-4">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1 class="page-title">
                <i class="fas fa-user-edit me-2 text-primary"></i> Edit Candidate
            </h1>
            <p class="page-subtitle">Update registration details for <?= e($candidate['full_name']) ?></p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= url('candidates-view') ?>&id=<?= $candidate['id'] ?>" class="btn btn-outline-info">
                <i class="fas fa-eye me-1"></i> View Profile
            </a>
            <a href="<?= url('candidates') ?>" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i> Back to List
            </a>
        </div>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-lg-10">
        <div class="content-card">
            <div class="card-header-custom">
                <h5><i class="fas fa-id-card me-2"></i> Edit Candidate Profile #<?= $candidate['id'] ?></h5>
            </div>
            <div class="card-body-custom">
                <form method="POST" action="<?= url('candidates-edit') ?>&id=<?= $candidate['id'] ?>" enctype="multipart/form-data" id="editCandidateForm">
                    <?= CSRF::field() ?>

                    <!-- Competition Selection -->
                    <div class="row g-3 mb-4 p-3 bg-dark border border-secondary rounded">
                        <div class="col-md-6">
                            <label for="competition_id" class="form-label fw-bold text-light">Competition <span class="text-danger">*</span></label>
                            <select class="form-select <?= isset($errors['competition_id']) ? 'is-invalid' : '' ?>" 
                                    id="competition_id" name="competition_id" required>
                                <?php foreach ($competitions as $cmp): ?>
                                    <option value="<?= $cmp['id'] ?>" <?= (int)$formData['competition_id'] === (int)$cmp['id'] ? 'selected' : '' ?>>
                                        <?= e($cmp['name']) ?> (<?= e($cmp['code']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['competition_id'])): ?>
                                <div class="invalid-feedback"><?= e($errors['competition_id']) ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="col-md-3">
                            <label for="registration_number" class="form-label fw-bold text-light">Reg # <span class="text-danger">*</span></label>
                            <input type="text" class="form-control font-monospace text-info <?= isset($errors['registration_number']) ? 'is-invalid' : '' ?>" 
                                   id="registration_number" name="registration_number" value="<?= e($formData['registration_number']) ?>" required>
                            <?php if (isset($errors['registration_number'])): ?>
                                <div class="invalid-feedback"><?= e($errors['registration_number']) ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="col-md-3">
                            <label for="roll_number" class="form-label fw-bold text-light">Roll Number</label>
                            <input type="text" class="form-control <?= isset($errors['roll_number']) ? 'is-invalid' : '' ?>" 
                                   id="roll_number" name="roll_number" value="<?= e($formData['roll_number']) ?>">
                            <?php if (isset($errors['roll_number'])): ?>
                                <div class="invalid-feedback"><?= e($errors['roll_number']) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Personal Information -->
                    <h6 class="text-primary mb-3"><i class="fas fa-user me-2"></i> Personal Information</h6>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="full_name" class="form-label">Candidate Full Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control <?= isset($errors['full_name']) ? 'is-invalid' : '' ?>" 
                                   id="full_name" name="full_name" value="<?= e($formData['full_name']) ?>" required>
                            <?php if (isset($errors['full_name'])): ?>
                                <div class="invalid-feedback"><?= e($errors['full_name']) ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="col-md-6">
                            <label for="father_name" class="form-label">Father's Name</label>
                            <input type="text" class="form-control" id="father_name" name="father_name" 
                                   value="<?= e($formData['father_name']) ?>">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label for="cnic" class="form-label">CNIC / B-Form</label>
                            <input type="text" class="form-control" id="cnic" name="cnic" 
                                   value="<?= e($formData['cnic']) ?>">
                        </div>

                        <div class="col-md-4">
                            <label for="phone" class="form-label">Mobile Number</label>
                            <input type="text" class="form-control" id="phone" name="phone" 
                                   value="<?= e($formData['phone']) ?>">
                        </div>

                        <div class="col-md-4">
                            <label for="gender" class="form-label">Gender</label>
                            <select class="form-select" id="gender" name="gender">
                                <option value="male" <?= $formData['gender'] === 'male' ? 'selected' : '' ?>>Male</option>
                                <option value="female" <?= $formData['gender'] === 'female' ? 'selected' : '' ?>>Female</option>
                                <option value="other" <?= $formData['gender'] === 'other' ? 'selected' : '' ?>>Other</option>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>" 
                                   id="email" name="email" value="<?= e($formData['email']) ?>">
                            <?php if (isset($errors['email'])): ?>
                                <div class="invalid-feedback"><?= e($errors['email']) ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="col-md-6">
                            <label for="photo" class="form-label">Candidate Photo</label>
                            <?php if (!empty($candidate['photo'])): ?>
                                <div class="d-flex align-items-center gap-3 mb-2">
                                    <img src="<?= upload(e($candidate['photo'])) ?>" alt="Current Photo" class="rounded object-fit-cover" style="width: 50px; height: 50px; border: 1px solid var(--border-color);">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="remove_photo" value="1" id="remove_photo">
                                        <label class="form-check-label text-danger small" for="remove_photo">
                                            Remove current photo
                                        </label>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <input type="file" class="form-control <?= isset($errors['photo']) ? 'is-invalid' : '' ?>" 
                                   id="photo" name="photo" accept="image/jpeg,image/png,image/webp">
                            <?php if (isset($errors['photo'])): ?>
                                <div class="invalid-feedback d-block"><?= e($errors['photo']) ?></div>
                            <?php endif; ?>
                            <small class="text-muted">Upload to change (JPG, PNG, WebP — max 2MB)</small>
                        </div>
                    </div>

                    <!-- Academic & Seat Allocation Details -->
                    <h6 class="text-primary mb-3"><i class="fas fa-graduation-cap me-2"></i> Course & Status Details</h6>
                    <div class="row g-3 mb-4">
                        <div class="col-md-3">
                            <label for="course" class="form-label">Course</label>
                            <input type="text" class="form-control" id="course" name="course" 
                                   value="<?= e($formData['course']) ?>">
                        </div>

                        <div class="col-md-2">
                            <label for="batch" class="form-label">Batch</label>
                            <input type="text" class="form-control" id="batch" name="batch" 
                                   value="<?= e($formData['batch']) ?>">
                        </div>

                        <div class="col-md-2">
                            <label for="shift" class="form-label">Shift</label>
                            <select class="form-select" id="shift" name="shift">
                                <option value="morning" <?= $formData['shift'] === 'morning' ? 'selected' : '' ?>>Morning</option>
                                <option value="afternoon" <?= $formData['shift'] === 'afternoon' ? 'selected' : '' ?>>Afternoon</option>
                                <option value="evening" <?= $formData['shift'] === 'evening' ? 'selected' : '' ?>>Evening</option>
                            </select>
                        </div>

                        <div class="col-md-2">
                            <label for="seat_number" class="form-label">Seat Number</label>
                            <input type="text" class="form-control" id="seat_number" name="seat_number" 
                                   value="<?= e($formData['seat_number']) ?>">
                        </div>

                        <div class="col-md-3">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="registered" <?= $formData['status'] === 'registered' ? 'selected' : '' ?>>Registered</option>
                                <option value="present" <?= $formData['status'] === 'present' ? 'selected' : '' ?>>Present</option>
                                <option value="absent" <?= $formData['status'] === 'absent' ? 'selected' : '' ?>>Absent</option>
                                <option value="test_started" <?= $formData['status'] === 'test_started' ? 'selected' : '' ?>>Test Started</option>
                                <option value="completed" <?= $formData['status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
                                <option value="disqualified" <?= $formData['status'] === 'disqualified' ? 'selected' : '' ?>>Disqualified</option>
                            </select>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2 border-top border-secondary pt-3">
                        <a href="<?= url('candidates') ?>" class="btn btn-outline-secondary">
                            <i class="fas fa-times me-1"></i> Cancel
                        </a>
                        <button type="submit" class="btn btn-primary" id="btnUpdateCandidate">
                            <i class="fas fa-save me-1"></i> Update Candidate
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
