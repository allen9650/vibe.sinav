<?php
/**
 * Candidate Management — Register Candidate
 */

$selectedCompId = (int)($_GET['competition_id'] ?? 0);
$competitions = Database::fetchAll("SELECT id, name, code, competition_date FROM competitions WHERE status != 'cancelled' ORDER BY competition_date DESC, id DESC");

if ($selectedCompId === 0 && !empty($competitions)) {
    $selectedCompId = (int)$competitions[0]['id'];
}

if (!function_exists('generateCandidateRegNumber')) {
    function generateCandidateRegNumber($competitionId) {
        if ($competitionId <= 0) {
            return 'MITC-' . date('Y') . '-0001';
        }
        $comp = Database::fetch("SELECT code, competition_date FROM competitions WHERE id = ?", [$competitionId]);
        $year = $comp ? date('Y', strtotime($comp['competition_date'])) : date('Y');
        $prefix = "MITC-{$year}-";

        $last = Database::fetchColumn(
            "SELECT registration_number FROM candidates WHERE registration_number LIKE ? ORDER BY id DESC LIMIT 1",
            [$prefix . '%']
        );

        if ($last) {
            $num = (int)substr($last, strlen($prefix));
            $next = $num + 1;
        } else {
            $next = 1;
        }

        return $prefix . str_pad((string)$next, 4, '0', STR_PAD_LEFT);
    }
}

$errors = [];
$formData = [
    'competition_id'      => $selectedCompId,
    'registration_number' => generateCandidateRegNumber($selectedCompId),
    'roll_number'         => '',
    'seat_number'         => '',
    'full_name'           => '',
    'father_name'         => '',
    'cnic'                => '',
    'phone'               => '',
    'email'               => '',
    'gender'              => 'male',
    'course'              => 'CIT',
    'batch'               => date('Y') . '-A',
    'shift'               => 'morning',
    'branch'              => 'Main Campus',
    'status'              => 'registered',
];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    CSRF::validateOrFail();

    $formData = [
        'competition_id'      => (int)($_POST['competition_id'] ?? 0),
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
        'status'              => trim($_POST['status'] ?? 'registered'),
    ];

    // If registration number is empty, auto-generate it
    if (empty($formData['registration_number'])) {
        $formData['registration_number'] = generateCandidateRegNumber($formData['competition_id']);
    }

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

    // Check unique registration number in database
    if (empty($errors['registration_number'])) {
        $existingReg = Database::fetch("SELECT id FROM candidates WHERE registration_number = ?", [$formData['registration_number']]);
        if ($existingReg) {
            $errors['registration_number'] = "Registration Number '{$formData['registration_number']}' is already assigned.";
        }
    }

    // Check unique roll number within the competition (if roll number provided)
    if (!empty($formData['roll_number']) && empty($errors['roll_number'])) {
        $existingRoll = Database::fetch(
            "SELECT id FROM candidates WHERE competition_id = ? AND roll_number = ?",
            [$formData['competition_id'], $formData['roll_number']]
        );
        if ($existingRoll) {
            $errors['roll_number'] = "Roll Number '{$formData['roll_number']}' is already taken in this competition.";
        }
    }

    // Handle Photo Upload
    $photoPath = null;
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
                $photoPath = 'candidates/' . $filename;
            } else {
                $errors['photo'] = "Failed to upload photo. Please check folder permissions.";
            }
        }
    }

    if (empty($errors)) {
        $candidateId = Database::insert('candidates', [
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
            'registered_by'       => Session::get('user_id'),
        ]);

        AuditLog::log('candidate_created', 'candidates', 
            "Registered candidate '{$formData['full_name']}' ({$formData['registration_number']}) in competition #{$formData['competition_id']}", 
            Session::get('user_id'), 
            null, 
            $formData
        );

        Session::flash('success', "Candidate '{$formData['full_name']}' registered successfully!");
        CSRF::regenerate();
        redirectTo('candidates-view&id=' . $candidateId);
    }
}
?>

<div class="dashboard-header mb-4">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1 class="page-title">
                <i class="fas fa-user-plus me-2 text-primary"></i> Register Candidate
            </h1>
            <p class="page-subtitle">Enrol a new participant into a typing competition</p>
        </div>
        <div>
            <a href="<?= url('candidates') ?>" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i> Back to Candidates
            </a>
        </div>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-lg-10">
        <div class="content-card">
            <div class="card-header-custom">
                <h5><i class="fas fa-id-card me-2"></i> Candidate Registration Form</h5>
            </div>
            <div class="card-body-custom">
                <?php if (empty($competitions)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-exclamation-triangle fa-3x mb-3 text-warning"></i>
                        <h4 class="text-light">No Competitions Available</h4>
                        <p class="text-muted mb-4">Please create a competition first before enrolling candidates.</p>
                        <a href="<?= url('competitions-create') ?>" class="btn btn-primary">
                            <i class="fas fa-plus me-1"></i> Create Competition
                        </a>
                    </div>
                <?php else: ?>
                <form method="POST" action="<?= url('candidates-create') ?>" enctype="multipart/form-data" id="createCandidateForm">
                    <?= CSRF::field() ?>

                    <!-- Competition Selection -->
                    <div class="row g-3 mb-4 p-3 bg-dark border border-secondary rounded">
                        <div class="col-md-6">
                            <label for="competition_id" class="form-label fw-bold text-light">Competition <span class="text-danger">*</span></label>
                            <select class="form-select <?= isset($errors['competition_id']) ? 'is-invalid' : '' ?>" 
                                    id="competition_id" name="competition_id" required>
                                <?php foreach ($competitions as $cmp): ?>
                                    <option value="<?= $cmp['id'] ?>" <?= (int)$formData['competition_id'] === (int)$cmp['id'] ? 'selected' : '' ?>>
                                        <?= e($cmp['name']) ?> (<?= e($cmp['code']) ?>) — <?= formatDate($cmp['competition_date']) ?>
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
                            <small class="text-muted">Auto-generated format</small>
                        </div>

                        <div class="col-md-3">
                            <label for="roll_number" class="form-label fw-bold text-light">Roll Number</label>
                            <input type="text" class="form-control <?= isset($errors['roll_number']) ? 'is-invalid' : '' ?>" 
                                   id="roll_number" name="roll_number" value="<?= e($formData['roll_number']) ?>" 
                                   placeholder="e.g. 101 or R-01">
                            <?php if (isset($errors['roll_number'])): ?>
                                <div class="invalid-feedback"><?= e($errors['roll_number']) ?></div>
                            <?php endif; ?>
                            <small class="text-muted">Unique in competition</small>
                        </div>
                    </div>

                    <!-- Personal Information -->
                    <h6 class="text-primary mb-3"><i class="fas fa-user me-2"></i> Personal Information</h6>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="full_name" class="form-label">Candidate Full Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control <?= isset($errors['full_name']) ? 'is-invalid' : '' ?>" 
                                   id="full_name" name="full_name" value="<?= e($formData['full_name']) ?>" 
                                   placeholder="Full Name" required autofocus>
                            <?php if (isset($errors['full_name'])): ?>
                                <div class="invalid-feedback"><?= e($errors['full_name']) ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="col-md-6">
                            <label for="father_name" class="form-label">Father's Name</label>
                            <input type="text" class="form-control" id="father_name" name="father_name" 
                                   value="<?= e($formData['father_name']) ?>" placeholder="Father's Name">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label for="cnic" class="form-label">CNIC / B-Form</label>
                            <input type="text" class="form-control" id="cnic" name="cnic" 
                                   value="<?= e($formData['cnic']) ?>" placeholder="e.g. 45203-1234567-1">
                        </div>

                        <div class="col-md-4">
                            <label for="phone" class="form-label">Mobile Number</label>
                            <input type="text" class="form-control" id="phone" name="phone" 
                                   value="<?= e($formData['phone']) ?>" placeholder="e.g. 0300-1234567">
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
                                   id="email" name="email" value="<?= e($formData['email']) ?>" placeholder="candidate@example.com">
                            <?php if (isset($errors['email'])): ?>
                                <div class="invalid-feedback"><?= e($errors['email']) ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="col-md-6">
                            <label for="photo" class="form-label">Candidate Photo</label>
                            <input type="file" class="form-control <?= isset($errors['photo']) ? 'is-invalid' : '' ?>" 
                                   id="photo" name="photo" accept="image/jpeg,image/png,image/webp">
                            <?php if (isset($errors['photo'])): ?>
                                <div class="invalid-feedback d-block"><?= e($errors['photo']) ?></div>
                            <?php endif; ?>
                            <small class="text-muted">Optional (JPG, PNG, WebP — max 2MB)</small>
                        </div>
                    </div>

                    <!-- Academic / Seat Allocation Details -->
                    <h6 class="text-primary mb-3"><i class="fas fa-graduation-cap me-2"></i> Course & Allocation Details</h6>
                    <div class="row g-3 mb-4">
                        <div class="col-md-3">
                            <label for="course" class="form-label">Course</label>
                            <input type="text" class="form-control" id="course" name="course" 
                                   value="<?= e($formData['course']) ?>" placeholder="e.g. CIT, DIT, Typing">
                        </div>

                        <div class="col-md-3">
                            <label for="batch" class="form-label">Batch</label>
                            <input type="text" class="form-control" id="batch" name="batch" 
                                   value="<?= e($formData['batch']) ?>" placeholder="e.g. 2026-A">
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
                                   value="<?= e($formData['seat_number']) ?>" placeholder="e.g. S-01">
                        </div>

                        <div class="col-md-2">
                            <label for="branch" class="form-label">Branch</label>
                            <input type="text" class="form-control" id="branch" name="branch" 
                                   value="<?= e($formData['branch']) ?>" placeholder="e.g. Main">
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2 border-top border-secondary pt-3">
                        <a href="<?= url('candidates') ?>" class="btn btn-outline-secondary">
                            <i class="fas fa-times me-1"></i> Cancel
                        </a>
                        <button type="submit" class="btn btn-primary" id="btnSaveCandidate">
                            <i class="fas fa-save me-1"></i> Register Candidate
                        </button>
                    </div>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
