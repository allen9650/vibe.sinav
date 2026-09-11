<?php
/**
 * Typing Paragraph Management — Visual Candidate Test Preview
 */

$paragraphId = (int)($_GET['id'] ?? 0);
$paragraph = Database::fetch("SELECT * FROM typing_paragraphs WHERE id = ?", [$paragraphId]);

if (!$paragraph) {
    Session::flash('error', 'Paragraph not found.');
    redirectTo('paragraphs');
}
?>

<div class="dashboard-header d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <h1 class="page-title mb-0">Preview: <?= e($paragraph['title']) ?></h1>
            <?php
            $diffBadge = match($paragraph['difficulty']) {
                'easy' => 'bg-success',
                'hard' => 'bg-warning text-dark',
                'expert' => 'bg-danger',
                default => 'bg-primary'
            };
            ?>
            <span class="badge <?= $diffBadge ?>"><?= ucfirst(e($paragraph['difficulty'])) ?></span>
            <span class="badge bg-secondary"><?= number_format($paragraph['word_count']) ?> Words</span>
            <span class="badge bg-secondary"><?= number_format($paragraph['char_count']) ?> Chars</span>
        </div>
        <p class="page-subtitle">Visual preview of how this passage will appear to candidates on the typing test interface</p>
    </div>

    <div class="d-flex gap-2">
        <a href="<?= url('paragraphs-edit') ?>&id=<?= $paragraph['id'] ?>" class="btn btn-outline-primary">
            <i class="fas fa-edit me-1"></i> Edit Paragraph
        </a>
        <a href="<?= url('paragraphs') ?>" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i> Back to Paragraphs
        </a>
    </div>
</div>

<div class="alert alert-info d-flex align-items-center mb-4">
    <i class="fas fa-info-circle fa-2x me-3"></i>
    <div>
        <strong>Visual Preview Mode:</strong> This screen previews the layout, typography, and contrast candidates experience during their test. Timers, WPM scoring, and auto-submission are active during actual candidate sessions.
    </div>
</div>

<!-- Mockup Candidate Test Screen -->
<div class="card bg-black border-2 border-primary shadow-lg mb-4">
    <!-- Test Screen Header Bar -->
    <div class="card-header bg-dark border-bottom border-secondary d-flex flex-wrap justify-content-between align-items-center p-3">
        <div class="d-flex align-items-center gap-3">
            <div class="fw-bold text-primary fs-5">
                <i class="fas fa-keyboard me-2"></i> Marr Typing Competition
            </div>
            <span class="badge bg-secondary">Demo Candidate: Ali Ahmed (Roll #101)</span>
        </div>
        <div class="d-flex align-items-center gap-3">
            <div class="bg-dark p-2 px-3 rounded border border-warning text-warning fw-bold font-monospace fs-4">
                <i class="fas fa-clock me-2"></i> 05:00
            </div>
            <button type="button" class="btn btn-danger btn-sm" disabled>
                <i class="fas fa-paper-plane me-1"></i> Submit Test
            </button>
        </div>
    </div>

    <div class="card-body p-4">
        <!-- Reference Paragraph Box -->
        <div class="mb-4">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="text-uppercase small text-muted fw-bold">Reference Passage</span>
                <span class="badge bg-dark border border-secondary"><?= e(strtoupper($paragraph['language'])) ?></span>
            </div>
            <div class="p-4 rounded border border-secondary font-monospace" 
                 style="background: #121826; color: #e2e8f0; font-size: 18px; line-height: 1.8; user-select: none; max-height: 280px; overflow-y: auto;">
                <?= htmlspecialchars($paragraph['content']) ?>
            </div>
        </div>

        <!-- Typing Input Box -->
        <div>
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="text-uppercase small text-muted fw-bold">Typing Area</span>
                <span class="text-muted small">Auto-focus enabled</span>
            </div>
            <textarea class="form-control font-monospace border-2 border-primary text-light" 
                      rows="6" 
                      style="background: #0f172a; font-size: 18px; line-height: 1.8;" 
                      placeholder="Type the passage above as fast and accurately as you can... (Preview Only)"></textarea>
        </div>
    </div>
</div>
