<?php
/**
 * Live Test Monitor — Proctoring Dashboard View
 */

Middleware::requirePermission('live_tests.view');

$competitions = Database::fetchAll("SELECT id, name, code, status FROM competitions ORDER BY id DESC");
$selectedCompId = (int)($_GET['competition_id'] ?? 0);
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1"><i class="fas fa-desktop text-primary me-2"></i> Live Test Proctoring & Monitor</h4>
        <div class="text-muted small">Real-time candidate telemetry, live countdowns, keystroke progress, and violation alerts</div>
    </div>
    <div class="d-flex align-items-center gap-2">
        <span class="badge bg-success bg-opacity-25 text-success border border-success border-opacity-50 py-2 px-3">
            <i class="fas fa-circle fa-beat text-success me-1"></i> Live Polling Active (<span id="pollSecs">3</span>s)
        </span>
        <a href="<?= url('security-events') ?>" class="btn btn-outline-danger btn-sm">
            <i class="fas fa-shield-alt me-1"></i> View Violations
        </a>
    </div>
</div>

<!-- Filter & Summary Counters Card -->
<div class="card bg-dark border-secondary mb-4">
    <div class="card-body py-3">
        <div class="row g-3 align-items-center">
            <div class="col-md-3">
                <select id="compSelector" class="form-select bg-dark text-light border-secondary" 
                        onchange="loadLiveMonitor()">
                    <option value="">All Competitions</option>
                    <?php foreach ($competitions as $cmp): ?>
                        <option value="<?= $cmp['id'] ?>" <?= $selectedCompId === (int)$cmp['id'] ? 'selected' : '' ?>>
                            <?= e($cmp['name']) ?> (<?= e($cmp['code']) ?>) [<?= strtoupper($cmp['status']) ?>]
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <select id="viewModeSelector" class="form-select bg-dark text-light border-secondary" 
                        onchange="loadLiveMonitor()">
                    <option value="active" selected>Active / Current Sessions</option>
                    <option value="latest">Latest Attempt Per Candidate</option>
                    <option value="all">All Attempt History</option>
                </select>
            </div>
            <div class="col-md-6 d-flex flex-wrap justify-content-md-end gap-2">
                <div class="badge bg-primary bg-opacity-25 text-primary border border-primary border-opacity-50 p-2 fs-6">
                    <span id="cntActive">0</span> Active
                </div>
                <div class="badge bg-success bg-opacity-25 text-success border border-success border-opacity-50 p-2 fs-6">
                    <span id="cntCompleted">0</span> Completed
                </div>
                <div class="badge bg-warning bg-opacity-25 text-warning border border-warning border-opacity-50 p-2 fs-6">
                    <span id="cntTimedOut">0</span> Timed Out
                </div>
                <div class="badge bg-danger bg-opacity-25 text-danger border border-danger border-opacity-50 p-2 fs-6">
                    <span id="cntDisqualified">0</span> Disqualified
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Live Table Card -->
<div class="card bg-dark border-secondary">
    <div class="card-header border-secondary d-flex justify-content-between align-items-center">
        <span class="fw-bold"><i class="fas fa-users me-1"></i> Candidate Sessions Telemetry</span>
        <span class="small text-muted" id="lastUpdatedText">Updating...</span>
    </div>
    <div class="table-responsive">
        <table class="table table-dark table-hover table-bordered mb-0 align-middle text-center" id="monitorTable">
            <thead class="table-secondary text-light small text-uppercase">
                <tr>
                    <th style="width: 100px;">Roll #</th>
                    <th class="text-start">Candidate</th>
                    <th>Competition</th>
                    <th>Started</th>
                    <th style="width: 130px;">Timer</th>
                    <th>Typed Chars</th>
                    <th style="width: 110px;">Violations</th>
                    <th style="width: 130px;">Status</th>
                    <th style="width: 100px;">Action</th>
                </tr>
            </thead>
            <tbody id="monitorBody">
                <tr>
                    <td colspan="9" class="py-4 text-muted">
                        <i class="fas fa-spinner fa-spin fa-2x mb-2 d-block"></i> Loading live session data...
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<script>
let monitorInterval = null;

function loadLiveMonitor() {
    const compId = document.getElementById('compSelector').value;
    const viewMode = document.getElementById('viewModeSelector').value;
    let url = '<?= url('live-tests-api') ?>';
    if (compId) url += '&competition_id=' + encodeURIComponent(compId);
    if (viewMode) url += '&view_mode=' + encodeURIComponent(viewMode);

    fetch(url)
        .then(res => res.json())
        .then(data => {
            if (data.status === 'ok') {
                updateMonitorUI(data);
            }
        })
        .catch(err => {
            document.getElementById('lastUpdatedText').textContent = 'Connection interrupted. Retrying...';
        });
}

function updateMonitorUI(data) {
    document.getElementById('cntActive').textContent = data.counts.in_progress || 0;
    document.getElementById('cntCompleted').textContent = data.counts.completed || 0;
    document.getElementById('cntTimedOut').textContent = data.counts.timed_out || 0;
    document.getElementById('cntDisqualified').textContent = data.counts.disqualified || 0;
    document.getElementById('lastUpdatedText').textContent = 'Last synced: ' + data.server_time;

    const tbody = document.getElementById('monitorBody');
    if (!data.attempts || data.attempts.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="9" class="py-4 text-muted">
                    <i class="fas fa-info-circle fa-2x mb-2 d-block"></i>
                    No test sessions found for the selected view and competition.
                </td>
            </tr>
        `;
        return;
    }

    let html = '';
    data.attempts.forEach(row => {
        let statusBadge = '';
        if (row.status === 'in_progress') {
            if (row.is_disconnected) {
                statusBadge = '<span class="badge bg-warning text-dark"><i class="fas fa-wifi text-danger me-1"></i> LAG / AFK</span>';
            } else {
                statusBadge = '<span class="badge bg-primary"><i class="fas fa-keyboard me-1"></i> ACTIVE</span>';
            }
        } else if (row.status === 'completed') {
            statusBadge = '<span class="badge bg-success"><i class="fas fa-check me-1"></i> COMPLETED</span>';
        } else if (row.status === 'timed_out') {
            statusBadge = '<span class="badge bg-secondary">TIMED OUT</span>';
        } else if (row.status === 'disqualified') {
            statusBadge = '<span class="badge bg-danger"><i class="fas fa-ban me-1"></i> DISQUALIFIED</span>';
        } else {
            statusBadge = `<span class="badge bg-dark border">${row.status.toUpperCase()}</span>`;
        }

        let timerClass = 'text-warning font-monospace fw-bold';
        if (row.status === 'in_progress' && row.remaining_seconds <= 30) {
            timerClass = 'text-danger font-monospace fw-bold';
        }

        let violationBadge = row.violation_count > 0 
            ? `<span class="badge bg-danger">${row.violation_count}</span>` 
            : `<span class="badge bg-dark border border-secondary text-muted">0</span>`;

        html += `
            <tr>
                <td><strong class="font-monospace text-primary">${escapeHtml(row.roll_number)}</strong></td>
                <td class="text-start">
                    <strong class="text-light">${escapeHtml(row.candidate_name)}</strong>
                    <span class="badge bg-dark border border-secondary text-muted small ms-1">#${row.attempt_number}</span>
                </td>
                <td class="small font-monospace">${escapeHtml(row.competition_code)}</td>
                <td class="small text-muted">${escapeHtml(row.started_at)}</td>
                <td class="${timerClass}">
                    ${row.status === 'in_progress' ? '<i class="fas fa-stopwatch me-1"></i>' + row.remaining_display : '—'}
                </td>
                <td class="font-monospace">${row.char_count.toLocaleString()} chars</td>
                <td>${violationBadge}</td>
                <td>${statusBadge}</td>
                <td>
                    <a href="<?= url('security-events') ?>&search=${encodeURIComponent(row.roll_number)}" class="btn btn-outline-info btn-sm py-0 px-2" title="View Security History">
                        <i class="fas fa-eye"></i>
                    </a>
                </td>
            </tr>
        `;
    });

    tbody.innerHTML = html;
}

function escapeHtml(text) {
    if (!text) return '';
    return String(text).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

document.addEventListener('DOMContentLoaded', function() {
    loadLiveMonitor();
    monitorInterval = setInterval(loadLiveMonitor, 3000);
});
</script>
