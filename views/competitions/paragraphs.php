<?php
/**
 * Competition Management — Paragraph Assignment & Selection Mode
 */

$competitionId = (int)($_GET['id'] ?? ($_POST['competition_id'] ?? 0));
$competition = Database::fetch("SELECT * FROM competitions WHERE id = ?", [$competitionId]);

if (!$competition) {
    Session::flash('error', 'Competition not found.');
    redirectTo('competitions');
}

// Fetch test settings for this competition to get current paragraph_mode
$settings = Database::fetch("SELECT * FROM test_settings WHERE competition_id = ?", [$competitionId]);

// If no test settings exist yet, create default record
if (!$settings) {
    $settingsId = Database::insert('test_settings', [
        'competition_id'        => $competitionId,
        'duration_minutes'      => 5,
        'duration_seconds'      => 300,
        'passing_wpm'           => 30,
        'passing_accuracy'      => 90.00,
        'paragraph_mode'        => 'fixed',
        'selected_paragraph_id' => null,
    ]);
    $settings = Database::fetch("SELECT * FROM test_settings WHERE id = ?", [$settingsId]);
}

// Handle POST actions: Assign, Remove, Toggle Active, Save Selection Mode
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    CSRF::validateOrFail();

    $action = $_POST['action'] ?? '';

    // Action 1: Assign Paragraph
    if ($action === 'assign_paragraph') {
        $paragraphId = (int)($_POST['paragraph_id'] ?? 0);
        $para = Database::fetch("SELECT * FROM typing_paragraphs WHERE id = ?", [$paragraphId]);

        if (!$para) {
            Session::flash('error', 'Selected paragraph does not exist.');
        } else {
            $existing = Database::fetch(
                "SELECT id FROM competition_paragraphs WHERE competition_id = ? AND paragraph_id = ?",
                [$competitionId, $paragraphId]
            );

            if ($existing) {
                Session::flash('warning', "Paragraph '{$para['title']}' is already assigned to this competition.");
            } else {
                Database::insert('competition_paragraphs', [
                    'competition_id' => $competitionId,
                    'paragraph_id'   => $paragraphId,
                    'is_active'      => 1,
                ]);

                // If fixed mode and no selected paragraph yet, set it
                if ($settings['paragraph_mode'] === 'fixed' && empty($settings['selected_paragraph_id'])) {
                    Database::update('test_settings', ['selected_paragraph_id' => $paragraphId], 'competition_id = ?', [$competitionId]);
                }

                AuditLog::log('paragraph_assigned_to_competition', 'competitions', 
                    "Assigned paragraph '{$para['title']}' (#{$paragraphId}) to competition #{$competitionId}", 
                    Session::get('user_id'), 
                    null,
                    ['competition_id' => $competitionId, 'paragraph_id' => $paragraphId]
                );

                Session::flash('success', "Paragraph '{$para['title']}' assigned to competition.");
            }
        }
        CSRF::regenerate();
        redirectTo('competitions-paragraphs&id=' . $competitionId);
    }

    // Action 2: Remove Assignment
    if ($action === 'remove_assignment') {
        $assignmentId = (int)($_POST['assignment_id'] ?? 0);
        $assigned = Database::fetch(
            "SELECT cp.*, p.title FROM competition_paragraphs cp JOIN typing_paragraphs p ON cp.paragraph_id = p.id WHERE cp.id = ? AND cp.competition_id = ?",
            [$assignmentId, $competitionId]
        );

        if ($assigned) {
            Database::delete('competition_paragraphs', 'id = ?', [$assignmentId]);

            // If the removed paragraph was the fixed selected paragraph, reset it
            if ((int)$settings['selected_paragraph_id'] === (int)$assigned['paragraph_id']) {
                $remainingFirst = Database::fetchColumn("SELECT paragraph_id FROM competition_paragraphs WHERE competition_id = ? AND is_active = 1 LIMIT 1", [$competitionId]);
                Database::update('test_settings', ['selected_paragraph_id' => $remainingFirst ?: null], 'competition_id = ?', [$competitionId]);
            }

            AuditLog::log('paragraph_unassigned_from_competition', 'competitions', 
                "Removed paragraph '{$assigned['title']}' (#{$assigned['paragraph_id']}) from competition #{$competitionId}", 
                Session::get('user_id'), 
                ['competition_id' => $competitionId, 'paragraph_id' => (int)$assigned['paragraph_id']],
                null
            );

            Session::flash('success', "Paragraph '{$assigned['title']}' removed from competition.");
        }
        CSRF::regenerate();
        redirectTo('competitions-paragraphs&id=' . $competitionId);
    }

    // Action 3: Toggle Active in Competition
    if ($action === 'toggle_active') {
        $assignmentId = (int)($_POST['assignment_id'] ?? 0);
        $assigned = Database::fetch("SELECT * FROM competition_paragraphs WHERE id = ? AND competition_id = ?", [$assignmentId, $competitionId]);

        if ($assigned) {
            $newActive = $assigned['is_active'] ? 0 : 1;
            Database::update('competition_paragraphs', ['is_active' => $newActive], 'id = ?', [$assignmentId]);
            Session::flash('success', "Paragraph assignment updated.");
        }
        CSRF::regenerate();
        redirectTo('competitions-paragraphs&id=' . $competitionId);
    }

    // Action 4: Save Mode & Selection
    if ($action === 'save_selection_mode') {
        $mode = trim($_POST['paragraph_mode'] ?? 'fixed');
        $selectedParaId = (int)($_POST['selected_paragraph_id'] ?? 0);

        if (!in_array($mode, ['fixed', 'random'], true)) {
            $mode = 'fixed';
        }

        if ($mode === 'fixed' && $selectedParaId > 0) {
            // Verify selected paragraph is assigned to this competition
            $isAssigned = Database::fetch(
                "SELECT id FROM competition_paragraphs WHERE competition_id = ? AND paragraph_id = ?",
                [$competitionId, $selectedParaId]
            );
            if (!$isAssigned) {
                Session::flash('error', 'Selected fixed paragraph must be assigned to this competition.');
                CSRF::regenerate();
                redirectTo('competitions-paragraphs&id=' . $competitionId);
            }
        } else {
            $selectedParaId = null;
        }

        $oldValues = [
            'paragraph_mode'        => $settings['paragraph_mode'] ?? null,
            'selected_paragraph_id' => $settings['selected_paragraph_id'] ?? null,
        ];
        $newValues = [
            'competition_id'        => $competitionId,
            'paragraph_mode'        => $mode,
            'selected_paragraph_id' => $selectedParaId,
            'randomize_paragraphs'  => $mode === 'random' ? 1 : 0,
        ];

        Database::update('test_settings', [
            'paragraph_mode'        => $mode,
            'selected_paragraph_id' => $selectedParaId,
            'randomize_paragraphs'  => $mode === 'random' ? 1 : 0,
        ], 'competition_id = ?', [$competitionId]);

        AuditLog::log('paragraph_selection_mode_changed', 'test_settings', 
            "Updated paragraph mode to '{$mode}' for competition #{$competitionId}", 
            Session::get('user_id'), 
            $oldValues,
            $newValues
        );

        Session::flash('success', "Paragraph selection configuration saved successfully.");
        CSRF::regenerate();
        redirectTo('competitions-paragraphs&id=' . $competitionId);
    }
}

// Reload fresh test settings
$settings = Database::fetch("SELECT * FROM test_settings WHERE competition_id = ?", [$competitionId]);

// Fetch currently assigned paragraphs
$assignedParagraphs = Database::fetchAll(
    "SELECT cp.id as assignment_id, cp.is_active as comp_active, cp.display_order,
            p.id as paragraph_id, p.title, p.difficulty, p.language, p.word_count, p.char_count, p.status as global_status
     FROM competition_paragraphs cp
     JOIN typing_paragraphs p ON cp.paragraph_id = p.id
     WHERE cp.competition_id = ?
     ORDER BY cp.display_order ASC, cp.id ASC",
    [$competitionId]
);

// Fetch all available paragraphs not yet assigned
$availableParagraphs = Database::fetchAll(
    "SELECT id, title, difficulty, language, word_count, char_count
     FROM typing_paragraphs
     WHERE status = 'active' AND id NOT IN (
         SELECT paragraph_id FROM competition_paragraphs WHERE competition_id = ?
     )
     ORDER BY title ASC",
    [$competitionId]
);
?>

<div class="dashboard-header d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <h1 class="page-title mb-0">Assigned Paragraphs & Content</h1>
            <span class="badge bg-dark border border-secondary"><?= e($competition['code']) ?></span>
        </div>
        <p class="page-subtitle"><?= e($competition['name']) ?> — Configure test reading materials and candidate assignment mode</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= url('competitions-settings') ?>&id=<?= $competition['id'] ?>" class="btn btn-outline-warning">
            <i class="fas fa-sliders-h me-1"></i> Test Settings
        </a>
        <a href="<?= url('competitions-view') ?>&id=<?= $competition['id'] ?>" class="btn btn-outline-info">
            <i class="fas fa-trophy me-1"></i> View Competition
        </a>
        <a href="<?= url('competitions') ?>" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i> Competitions
        </a>
    </div>
</div>

<div class="row g-4">
    <!-- Left: Paragraph Selection Mode & Assignment -->
    <div class="col-lg-8">
        <!-- Paragraph Selection Mode Card -->
        <div class="content-card mb-4 border-2 border-primary">
            <div class="card-header-custom d-flex justify-content-between align-items-center">
                <h5><i class="fas fa-cogs me-2 text-primary"></i> Candidate Paragraph Selection Mode</h5>
                <span class="badge bg-primary text-uppercase"><?= $settings['paragraph_mode'] ?> MODE</span>
            </div>
            <div class="card-body-custom">
                <form method="POST" action="<?= url('competitions-paragraphs') ?>&id=<?= $competition['id'] ?>">
                    <?= CSRF::field() ?>
                    <input type="hidden" name="action" value="save_selection_mode">
                    <input type="hidden" name="competition_id" value="<?= $competition['id'] ?>">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="form-check p-3 bg-dark rounded border <?= $settings['paragraph_mode'] === 'fixed' ? 'border-primary' : 'border-secondary' ?>">
                                <input class="form-check-input ms-0 me-2" type="radio" name="paragraph_mode" id="modeFixed" value="fixed" 
                                       <?= $settings['paragraph_mode'] === 'fixed' ? 'checked' : '' ?> onchange="toggleModeControls()">
                                <label class="form-check-label fw-bold text-light" for="modeFixed">
                                    Mode A — Fixed Paragraph
                                </label>
                                <div class="small text-muted mt-1 ps-4">
                                    Every candidate in this competition receives the exact same fixed passage.
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-check p-3 bg-dark rounded border <?= $settings['paragraph_mode'] === 'random' ? 'border-primary' : 'border-secondary' ?>">
                                <input class="form-check-input ms-0 me-2" type="radio" name="paragraph_mode" id="modeRandom" value="random" 
                                       <?= $settings['paragraph_mode'] === 'random' ? 'checked' : '' ?> onchange="toggleModeControls()">
                                <label class="form-check-label fw-bold text-light" for="modeRandom">
                                    Mode B — Random Pool Mode
                                </label>
                                <div class="small text-muted mt-1 ps-4">
                                    Candidates randomly receive one passage from the active assigned paragraph pool.
                                </div>
                            </div>
                        </div>

                        <div class="col-12" id="fixedSelectorArea" style="<?= $settings['paragraph_mode'] === 'random' ? 'display: none;' : '' ?>">
                            <label for="selected_paragraph_id" class="form-label fw-bold small text-muted">Select Specific Fixed Paragraph</label>
                            <select class="form-select" id="selected_paragraph_id" name="selected_paragraph_id">
                                <?php if (empty($assignedParagraphs)): ?>
                                    <option value="">No paragraphs assigned yet — please assign below</option>
                                <?php else: ?>
                                    <?php foreach ($assignedParagraphs as $ap): ?>
                                        <option value="<?= $ap['paragraph_id'] ?>" <?= (int)$settings['selected_paragraph_id'] === (int)$ap['paragraph_id'] ? 'selected' : '' ?>>
                                            <?= e($ap['title']) ?> (<?= $ap['word_count'] ?> words, <?= ucfirst($ap['difficulty']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>

                        <div class="col-12 text-end">
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="fas fa-save me-1"></i> Save Selection Mode
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Assigned Paragraphs List -->
        <div class="content-card">
            <div class="card-header-custom d-flex justify-content-between align-items-center">
                <h5>
                    <i class="fas fa-list-check me-2"></i> Assigned Competition Paragraphs
                    <span class="badge bg-secondary ms-2"><?= count($assignedParagraphs) ?> assigned</span>
                </h5>
            </div>
            <div class="card-body-custom p-0">
                <?php if (empty($assignedParagraphs)): ?>
                    <div class="text-center text-muted py-5">
                        <i class="fas fa-paragraph fa-3x mb-3 text-secondary"></i>
                        <h5 class="text-light">No Paragraphs Assigned</h5>
                        <p class="small text-muted mb-0">Use the form on the right to assign passages from the library.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-dark table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Passage Title</th>
                                    <th width="100">Difficulty</th>
                                    <th width="80">Words</th>
                                    <th width="80">Chars</th>
                                    <th width="120" class="text-center">Status in Comp</th>
                                    <th width="140" class="text-end pe-3">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($assignedParagraphs as $p): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-bold text-light"><?= e($p['title']) ?></div>
                                            <div class="small text-muted">ID: #<?= $p['paragraph_id'] ?> &bull; <?= e(ucfirst($p['language'])) ?></div>
                                        </td>
                                        <td>
                                            <?php
                                            $diffBadge = match($p['difficulty']) {
                                                'easy' => 'bg-success',
                                                'hard' => 'bg-warning text-dark',
                                                'expert' => 'bg-danger',
                                                default => 'bg-primary'
                                            };
                                            ?>
                                            <span class="badge <?= $diffBadge ?> text-capitalize"><?= e($p['difficulty']) ?></span>
                                        </td>
                                        <td class="fw-bold"><?= number_format($p['word_count']) ?></td>
                                        <td class="text-muted small"><?= number_format($p['char_count']) ?></td>
                                        <td class="text-center">
                                            <form method="POST" action="<?= url('competitions-paragraphs') ?>&id=<?= $competition['id'] ?>" class="d-inline">
                                                <?= CSRF::field() ?>
                                                <input type="hidden" name="action" value="toggle_active">
                                                <input type="hidden" name="assignment_id" value="<?= $p['assignment_id'] ?>">
                                                <button type="submit" class="badge border-0 cursor-pointer <?= $p['comp_active'] ? 'bg-success' : 'bg-secondary' ?>" title="Click to toggle in this competition">
                                                    <?= $p['comp_active'] ? 'Active' : 'Disabled' ?>
                                                </button>
                                            </form>
                                        </td>
                                        <td class="text-end pe-3">
                                            <div class="btn-group btn-group-sm">
                                                <a href="<?= url('paragraphs-preview') ?>&id=<?= $p['paragraph_id'] ?>" class="btn btn-outline-info" title="Preview Layout">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <form method="POST" action="<?= url('competitions-paragraphs') ?>&id=<?= $competition['id'] ?>" class="d-inline"
                                                      onsubmit="return confirm('Remove paragraph \'<?= e($p['title']) ?>\' from this competition?');">
                                                    <?= CSRF::field() ?>
                                                    <input type="hidden" name="action" value="remove_assignment">
                                                    <input type="hidden" name="assignment_id" value="<?= $p['assignment_id'] ?>">
                                                    <button type="submit" class="btn btn-outline-danger" title="Unassign">
                                                        <i class="fas fa-unlink"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Right: Assign New Paragraph -->
    <div class="col-lg-4">
        <div class="content-card mb-4">
            <div class="card-header-custom">
                <h5><i class="fas fa-plus me-2 text-primary"></i> Assign From Library</h5>
            </div>
            <div class="card-body-custom">
                <?php if (empty($availableParagraphs)): ?>
                    <div class="text-center text-muted py-4">
                        <p class="small mb-3">All available active library passages are already assigned to this competition.</p>
                        <a href="<?= url('paragraphs-create') ?>" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-plus me-1"></i> Create New Library Paragraph
                        </a>
                    </div>
                <?php else: ?>
                    <form method="POST" action="<?= url('competitions-paragraphs') ?>&id=<?= $competition['id'] ?>">
                        <?= CSRF::field() ?>
                        <input type="hidden" name="action" value="assign_paragraph">
                        <input type="hidden" name="competition_id" value="<?= $competition['id'] ?>">

                        <div class="mb-3">
                            <label for="paragraph_id" class="form-label fw-bold small text-muted">Select Paragraph</label>
                            <select class="form-select" id="paragraph_id" name="paragraph_id" required>
                                <option value="">-- Choose Passage --</option>
                                <?php foreach ($availableParagraphs as $ap): ?>
                                    <option value="<?= $ap['id'] ?>">
                                        <?= e($ap['title']) ?> (<?= $ap['word_count'] ?> words, <?= ucfirst($ap['difficulty']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-link me-1"></i> Assign to Competition
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <div class="content-card">
            <div class="card-header-custom">
                <h5><i class="fas fa-shield-alt me-2"></i> Integrity Guarantee</h5>
            </div>
            <div class="card-body-custom small text-muted">
                <p class="mb-2"><i class="fas fa-check-circle text-success me-1"></i> <strong>Snapshot Storage:</strong> When a candidate launches a test, the server snapshots the exact text into their test attempt record.</p>
                <p class="mb-0"><i class="fas fa-check-circle text-success me-1"></i> <strong>Random Mode Persistence:</strong> If random pool mode is selected, once an attempt begins, the selected paragraph is permanently stored so page refreshes never switch texts.</p>
            </div>
        </div>
    </div>
</div>

<script>
function toggleModeControls() {
    const isFixed = document.getElementById('modeFixed').checked;
    document.getElementById('fixedSelectorArea').style.display = isFixed ? 'block' : 'none';
}
</script>
