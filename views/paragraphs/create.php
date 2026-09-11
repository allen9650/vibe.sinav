<?php
/**
 * Typing Paragraph Management — Add Paragraph
 */

$errors = [];
$formData = [
    'title'      => '',
    'content'    => '',
    'difficulty' => 'medium',
    'language'   => 'english',
    'category'   => 'General',
    'status'     => 'active',
];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    CSRF::validateOrFail();

    $formData = [
        'title'      => trim($_POST['title'] ?? ''),
        'content'    => $_POST['content'] ?? '', // Preserve internal spacing and newlines exactly
        'difficulty' => trim($_POST['difficulty'] ?? 'medium'),
        'language'   => trim($_POST['language'] ?? 'english'),
        'category'   => trim($_POST['category'] ?? 'General'),
        'status'     => trim($_POST['status'] ?? 'active'),
    ];

    if ($formData['title'] === '') {
        $errors['title'] = 'Paragraph title is required.';
    } elseif (mb_strlen($formData['title']) > 200) {
        $errors['title'] = 'Title cannot exceed 200 characters.';
    }

    if (trim($formData['content']) === '') {
        $errors['content'] = 'Paragraph content text is required.';
    } elseif (mb_strlen(trim($formData['content'])) < 20) {
        $errors['content'] = 'Paragraph text is too short (minimum 20 characters required).';
    } elseif (mb_strlen($formData['content']) > 50000) {
        $errors['content'] = 'Paragraph text exceeds maximum allowed size (50,000 characters).';
    }

    if (!in_array($formData['difficulty'], ['easy', 'medium', 'hard', 'expert'], true)) {
        $errors['difficulty'] = 'Invalid difficulty level selected.';
    }

    if (!in_array($formData['status'], ['active', 'inactive'], true)) {
        $errors['status'] = 'Invalid status selected.';
    }

    if (empty($errors)) {
        // Authoritative server-side word and character calculations
        $rawText = $formData['content'];
        $trimmed = trim($rawText);
        $wordCount = $trimmed === '' ? 0 : count(preg_split('/\s+/', $trimmed));
        $charCount = mb_strlen($rawText);

        $paragraphId = Database::insert('typing_paragraphs', [
            'title'       => $formData['title'],
            'content'     => $formData['content'],
            'word_count'  => $wordCount,
            'char_count'  => $charCount,
            'difficulty'  => $formData['difficulty'],
            'language'    => $formData['language'] ?: 'english',
            'category'    => $formData['category'] ?: null,
            'status'      => $formData['status'],
            'created_by'  => Session::get('user_id'),
        ]);

        $newValues = [
            'paragraph_id' => $paragraphId,
            'title'        => $formData['title'],
            'word_count'   => $wordCount,
            'char_count'   => $charCount,
            'difficulty'   => $formData['difficulty'],
            'status'       => $formData['status'],
        ];

        AuditLog::log('paragraph_created', 'paragraphs', 
            "Created paragraph '{$formData['title']}' ({$wordCount} words, {$charCount} chars, difficulty: {$formData['difficulty']})", 
            Session::get('user_id'), 
            null, 
            $newValues
        );

        Session::flash('success', "Paragraph '{$formData['title']}' created successfully with {$wordCount} words.");
        CSRF::regenerate();
        redirectTo('paragraphs');
    }
}
?>

<div class="dashboard-header mb-4">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1 class="page-title">
                <i class="fas fa-plus-circle me-2 text-primary"></i> Add Typing Paragraph
            </h1>
            <p class="page-subtitle">Add a new test passage to the typing competition library</p>
        </div>
        <div>
            <a href="<?= url('paragraphs') ?>" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i> Back to Paragraphs
            </a>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="content-card mb-4">
            <div class="card-header-custom">
                <h5><i class="fas fa-keyboard me-2"></i> Paragraph Content & Details</h5>
            </div>
            <div class="card-body-custom">
                <form method="POST" action="<?= url('paragraphs-create') ?>" id="createParagraphForm">
                    <?= CSRF::field() ?>

                    <div class="mb-3">
                        <label for="title" class="form-label fw-bold">Paragraph Title <span class="text-danger">*</span></label>
                        <input type="text" class="form-control <?= isset($errors['title']) ? 'is-invalid' : '' ?>" 
                               id="title" name="title" value="<?= e($formData['title']) ?>" 
                               placeholder="e.g. The Evolution of Computing Machines" required>
                        <?php if (isset($errors['title'])): ?>
                            <div class="invalid-feedback"><?= e($errors['title']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label for="difficulty" class="form-label fw-bold">Difficulty Level <span class="text-danger">*</span></label>
                            <select class="form-select <?= isset($errors['difficulty']) ? 'is-invalid' : '' ?>" 
                                    id="difficulty" name="difficulty" required>
                                <option value="easy" <?= $formData['difficulty'] === 'easy' ? 'selected' : '' ?>>Easy (Simple vocabulary)</option>
                                <option value="medium" <?= $formData['difficulty'] === 'medium' ? 'selected' : '' ?>>Medium (Standard text)</option>
                                <option value="hard" <?= $formData['difficulty'] === 'hard' ? 'selected' : '' ?>>Hard (Punctuation & numbers)</option>
                                <option value="expert" <?= $formData['difficulty'] === 'expert' ? 'selected' : '' ?>>Expert (Complex syntax)</option>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label for="language" class="form-label fw-bold">Language <span class="text-danger">*</span></label>
                            <select class="form-select" id="language" name="language">
                                <option value="english" <?= $formData['language'] === 'english' ? 'selected' : '' ?>>English</option>
                                <option value="urdu" <?= $formData['language'] === 'urdu' ? 'selected' : '' ?>>Urdu</option>
                                <option value="sindhi" <?= $formData['language'] === 'sindhi' ? 'selected' : '' ?>>Sindhi</option>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label for="status" class="form-label fw-bold">Status <span class="text-danger">*</span></label>
                            <select class="form-select" id="status" name="status">
                                <option value="active" <?= $formData['status'] === 'active' ? 'selected' : '' ?>>Active (Available for testing)</option>
                                <option value="inactive" <?= $formData['status'] === 'inactive' ? 'selected' : '' ?>>Inactive (Draft / Hidden)</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label for="content" class="form-label fw-bold mb-0">Paragraph Text <span class="text-danger">*</span></label>
                            <span class="small text-muted">Preserves exact spacing, newlines and symbols</span>
                        </div>
                        <textarea class="form-control font-monospace <?= isset($errors['content']) ? 'is-invalid' : '' ?>" 
                                  id="content" name="content" rows="12" 
                                  placeholder="Type or paste the exact paragraph content here..." required 
                                  style="font-size: 15px; line-height: 1.6; resize: vertical;"><?= htmlspecialchars($formData['content']) ?></textarea>
                        <?php if (isset($errors['content'])): ?>
                            <div class="invalid-feedback"><?= e($errors['content']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="d-flex justify-content-end gap-2 border-top border-secondary pt-3">
                        <a href="<?= url('paragraphs') ?>" class="btn btn-outline-secondary">
                            <i class="fas fa-times me-1"></i> Cancel
                        </a>
                        <button type="submit" class="btn btn-primary" id="btnSaveParagraph">
                            <i class="fas fa-save me-1"></i> Save Paragraph
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Live Analytics & Metrics Sidebar -->
    <div class="col-lg-4">
        <div class="content-card mb-4">
            <div class="card-header-custom">
                <h5><i class="fas fa-chart-bar me-2"></i> Real-Time Text Analytics</h5>
            </div>
            <div class="card-body-custom">
                <div class="row g-3 text-center mb-3">
                    <div class="col-6">
                        <div class="p-3 bg-dark rounded border border-secondary">
                            <div class="h3 mb-0 text-primary fw-bold" id="statWords">0</div>
                            <div class="small text-muted">Total Words</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-3 bg-dark rounded border border-secondary">
                            <div class="h3 mb-0 text-info fw-bold" id="statChars">0</div>
                            <div class="small text-muted">Characters (with spaces)</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-3 bg-dark rounded border border-secondary">
                            <div class="h4 mb-0 text-light" id="statCharsNoSpace">0</div>
                            <div class="small text-muted">Chars (no spaces)</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-3 bg-dark rounded border border-secondary">
                            <div class="h4 mb-0 text-light" id="statLines">0</div>
                            <div class="small text-muted">Lines / Paragraphs</div>
                        </div>
                    </div>
                </div>

                <div class="alert alert-info small mb-0">
                    <i class="fas fa-info-circle me-1"></i>
                    <strong>Punctuation & Accuracy:</strong> Punctuation, capitalization, and spaces are stored authoritatively for accurate typing comparisons during candidate tests.
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const textarea = document.getElementById('content');
    const statWords = document.getElementById('statWords');
    const statChars = document.getElementById('statChars');
    const statCharsNoSpace = document.getElementById('statCharsNoSpace');
    const statLines = document.getElementById('statLines');

    function updateStats() {
        const text = textarea.value;
        const trimmed = text.trim();
        
        // Words
        const words = trimmed ? trimmed.split(/\s+/).length : 0;
        statWords.textContent = words.toLocaleString();

        // Characters (total)
        statChars.textContent = text.length.toLocaleString();

        // Chars without spaces
        const noSpaces = text.replace(/\s+/g, '').length;
        statCharsNoSpace.textContent = noSpaces.toLocaleString();

        // Lines
        const lines = trimmed ? text.split(/\r\n|\r|\n/).length : 0;
        statLines.textContent = lines.toLocaleString();
    }

    textarea.addEventListener('input', updateStats);
    updateStats();
});
</script>
