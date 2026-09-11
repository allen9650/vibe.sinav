<?php
declare(strict_types=1);

/**
 * PTM Assessment System — Device Allocation & Lab Activation View
 * Counter input, visual lab grid with status dots, and one-click lab activation.
 */

$assessmentId = (int)($_GET['id'] ?? 0);
if ($assessmentId <= 0) {
    Session::flash('error', 'Valid assessment ID is required.');
    redirectTo('assessments');
}

$assessment = AssessmentService::getAssessment($assessmentId);
if (!$assessment) {
    Session::flash('error', 'Assessment not found.');
    redirectTo('assessments');
}

$campusId = (int)$assessment['campus_id'];

// Handle POST: Allocation Limit Setting, PC Name Assignment, and Unassignment
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    CSRF::validateOrFail();

    $action = $_POST['action'] ?? '';
    $deviceCount = max(0, (int)($_POST['device_count'] ?? 0));

    if ($action === 'set_allocation_limit') {
        try {
            StationService::setAssessmentDeviceLimit($assessmentId, $deviceCount);
            Session::flash('success', sprintf("Device allocation limit set to %d workstation slot(s).", $deviceCount));
            redirectTo('assessments-devices&id=' . $assessmentId);
        } catch (Throwable $e) {
            Session::flash('error', 'Failed to update allocation limit: ' . $e->getMessage());
        }
    } elseif ($action === 'activate_lab') {
        try {
            // Save allocation limit first
            if ($deviceCount > 0) {
                StationService::setAssessmentDeviceLimit($assessmentId, $deviceCount);
            }

            // Launch this assessment to lab (stops any other live exam)
            $launchResult = AssessmentService::launchAssessment($assessmentId);
            if (!$launchResult['success']) {
                throw new RuntimeException($launchResult['message']);
            }

            Session::flash('success', sprintf("Lab activated successfully! '%s' is now LIVE for candidate intake.", $assessment['title']));
            redirectTo('assessments-devices&id=' . $assessmentId);
        } catch (Throwable $e) {
            Session::flash('error', 'Activation failed: ' . $e->getMessage());
        }
    } elseif ($action === 'assign_station') {
        try {
            $stationCode = trim((string)($_POST['station_code'] ?? ''));
            if ($stationCode === '') {
                throw new InvalidArgumentException("Please enter a PC name or workstation code.");
            }
            $station = StationService::assignStationToAssessment($assessmentId, $stationCode, $campusId);
            Session::flash('success', sprintf("Workstation '%s' assigned to exam slot.", $station['station_code']));
            redirectTo('assessments-devices&id=' . $assessmentId);
        } catch (Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
    } elseif ($action === 'unassign_station') {
        try {
            $stationId = (int)($_POST['station_id'] ?? 0);
            if ($stationId <= 0) {
                throw new InvalidArgumentException("Invalid workstation ID.");
            }
            StationService::unassignStation($assessmentId, $stationId);
            Session::flash('success', "Workstation unassigned. The allocation slot is now free for another PC name.");
            redirectTo('assessments-devices&id=' . $assessmentId);
        } catch (Throwable $e) {
            Session::flash('error', 'Failed to unassign workstation: ' . $e->getMessage());
        }
    } elseif ($action === 'delete_station') {
        try {
            $stationId = (int)($_POST['station_id'] ?? 0);
            if ($stationId <= 0) {
                throw new InvalidArgumentException("Invalid workstation ID.");
            }
            StationService::deleteStation($stationId, $assessmentId);
            Session::flash('success', "Workstation removed successfully.");
            redirectTo('assessments-devices&id=' . $assessmentId);
        } catch (Throwable $e) {
            Session::flash('error', 'Failed to remove workstation: ' . $e->getMessage());
        }
    } elseif ($action === 'clear_idle_stations') {
        try {
            $cleared = StationService::clearIdleStations($assessmentId, $campusId);
            Session::flash('success', sprintf("Cleared %d idle workstations from the lab.", $cleared));
            redirectTo('assessments-devices&id=' . $assessmentId);
        } catch (Throwable $e) {
            Session::flash('error', 'Failed to clear workstations: ' . $e->getMessage());
        }
    }
}

// Fetch allocation data and slots
$allocationData = StationService::getAssessmentAllocationSlots($assessmentId);
$deviceLimit = $allocationData['device_limit'];
$assignedCount = $allocationData['assigned_count'];
$availableCount = $allocationData['available_count'];
$slots = $allocationData['slots'];

$inProgressCount = 0;
$submittedCount = 0;
foreach ($slots as $slot) {
    if ($slot['is_assigned'] && $slot['station']) {
        if (($slot['station']['attempt_status'] ?? '') === 'in_progress') {
            $inProgressCount++;
        } elseif (($slot['station']['status'] ?? '') === 'submitted') {
            $submittedCount++;
        }
    }
}

$questionsCount = (int)Database::fetchColumn(
    "SELECT COUNT(*) FROM assessment_questions WHERE assessment_id = ?",
    [$assessmentId]
);

$displayLimit = $deviceLimit > 0 ? $deviceLimit : 3;
?>

<div class="container-fluid py-4">
    <!-- Breadcrumb & Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="<?= url('assessments') ?>" class="text-decoration-none text-muted">Assessments</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Device Allocation</li>
                </ol>
            </nav>
            <h3 class="fw-bold mb-0">
                <i class="fas fa-network-wired text-primary me-2"></i> Lab Device Allocation & Activation
            </h3>
        </div>
        <div class="d-flex gap-2">
            <?php if ($deviceLimit > 0 && $assignedCount < $deviceLimit): ?>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#assignStationModal">
                    <i class="fas fa-plus me-1"></i> Assign PC Name
                </button>
            <?php endif; ?>
            <a href="<?= url('assessments-results&id=' . $assessmentId) ?>" class="btn btn-outline-info">
                <i class="fas fa-chart-column me-1"></i> View Results
            </a>
            <a href="<?= url('assessments') ?>" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i> Back
            </a>
        </div>
    </div>

    <!-- Assessment Overview Card -->
    <div class="card border mb-4 shadow-sm">
        <div class="card-body">
            <div class="row g-3 align-items-center">
                <div class="col-md-6">
                    <h5 class="fw-bold mb-1"><?= e($assessment['title']) ?></h5>
                    <div class="text-muted small">
                        Subject: <span class="text-info fw-medium"><?= e($assessment['subject_name']) ?></span> | 
                        Class: <span class="fw-medium"><?= e($assessment['target_class']) ?></span> | 
                        Questions: <span class="<?= $questionsCount > 0 ? 'text-success' : 'text-danger' ?> fw-bold"><?= $questionsCount ?> Questions</span> |
                        Duration: <span class="text-warning fw-medium"><?= (int)$assessment['duration_minutes'] ?> Mins</span>
                    </div>
                </div>
                <div class="col-md-6 text-md-end">
                    <span class="badge <?= $assessment['status'] === 'published' ? 'bg-success' : 'bg-secondary' ?> px-3 py-2 fs-6 fw-semibold text-uppercase me-2">
                        Status: <?= e($assessment['status']) ?>
                    </span>
                    <span class="badge bg-primary bg-opacity-25 text-primary border border-primary px-3 py-2 fs-6">
                        <?= $assignedCount ?> / <?= $deviceLimit > 0 ? $deviceLimit : 'Unset' ?> Devices Allocated
                    </span>
                </div>
            </div>
        </div>
    </div>

    <?php if ($questionsCount === 0): ?>
        <div class="alert alert-warning border-warning d-flex align-items-center justify-content-between mb-4 shadow-sm">
            <div class="d-flex align-items-center gap-3">
                <i class="fas fa-triangle-exclamation fa-2x text-warning"></i>
                <div>
                    <div class="fw-bold">This assessment currently has 0 questions attached.</div>
                    <div class="small text-muted">Candidates will not see any questions until questions are added. Please use the Assessment Builder to attach questions before opening the lab.</div>
                </div>
            </div>
            <a href="<?= url('assessments-builder&id=' . $assessmentId) ?>" class="btn btn-warning fw-bold text-dark">
                <i class="fas fa-plus-circle me-1"></i> Add Questions in Builder
            </a>
        </div>
    <?php endif; ?>

    <!-- Activation & Allocation Form -->
    <div class="row g-4">
        <!-- Control Box -->
        <div class="col-lg-4">
            <div class="card border h-100 shadow-sm">
                <div class="card-header border-bottom">
                    <h5 class="card-title fw-bold mb-0">
                        <i class="fas fa-sliders text-warning me-2"></i> Device Allocation Limit
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST" id="deviceAllocForm">
                        <?= CSRF::field() ?>

                        <!-- Simple Counter Input -->
                        <div class="mb-4">
                            <label for="device_count" class="form-label fw-bold">
                                Number of Devices Allowed (Limit):
                            </label>
                            <div class="input-group input-group-lg">
                                <span class="input-group-text">
                                    <i class="fas fa-desktop"></i>
                                </span>
                                <input type="number" name="device_count" id="device_count" 
                                       class="form-control font-monospace fw-bold fs-4" 
                                       value="<?= $displayLimit ?>" 
                                       min="1" max="500" required>
                            </div>
                            <div class="form-text text-muted small mt-2">
                                <i class="fas fa-shield-halved text-primary me-1"></i><strong>Allocation Quota:</strong> Only this number of computers (e.g. 1, 2, 3) can access this exam. When a PC name is entered at student intake or below, it claims one of these limited slots.
                            </div>
                        </div>

                        <!-- Quick Count Presets -->
                        <div class="mb-4">
                            <label class="form-label text-muted small text-uppercase fw-bold">Quick Presets:</label>
                            <div class="d-flex flex-wrap gap-2">
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setCount(1)">1 PC</button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setCount(2)">2 PCs</button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setCount(3)">3 PCs</button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setCount(5)">5 PCs</button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setCount(10)">10 PCs</button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setCount(15)">15 PCs</button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setCount(20)">20 PCs</button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setCount(25)">25 PCs</button>
                            </div>
                        </div>

                        <hr class="my-4">

                        <!-- Legend -->
                        <div class="mb-4">
                            <div class="text-muted small text-uppercase fw-bold mb-2">Slot Status Legend:</div>
                            <div class="d-flex flex-column gap-2 small">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge bg-secondary" style="font-size: 0.65rem;">Dashed</span>
                                    <span>Empty Slot (Available / Waiting PC Name)</span>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="status-dot dot-assigned"></span>
                                    <span>Blue: PC Assigned (Waiting Candidate)</span>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="status-dot dot-active"></span>
                                    <span>Green: Live Candidate In Progress</span>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="status-dot dot-submitted"></span>
                                    <span>Purple: Test Completed / Submitted</span>
                                </div>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="d-grid gap-2 pt-2">
                            <button type="submit" name="action" value="activate_lab" class="btn btn-success btn-lg fw-bold py-3">
                                <i class="fas fa-bolt me-2"></i> Save Limit & Open Lab
                            </button>
                            <button type="submit" name="action" value="set_allocation_limit" class="btn btn-outline-primary fw-semibold py-2">
                                <i class="fas fa-save me-1"></i> Update Allocation Limit Only
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Visual Lab Grid -->
        <div class="col-lg-8">
            <div class="card border h-100 shadow-sm">
                <div class="card-header border-bottom d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-2">
                        <h5 class="card-title fw-bold mb-0">
                            <i class="fas fa-grip text-info me-2"></i> Allocated Device Slots
                        </h5>
                        <span class="badge bg-primary bg-opacity-25 text-primary border border-primary small">
                            <?= $assignedCount ?> / <?= $deviceLimit ?> Slots Assigned
                        </span>
                    </div>
                    <div class="d-flex gap-2">
                        <?php if ($deviceLimit > 0 && $assignedCount < $deviceLimit): ?>
                            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#assignStationModal">
                                <i class="fas fa-plus me-1"></i> Assign PC Name
                            </button>
                        <?php elseif ($deviceLimit > 0 && $assignedCount >= $deviceLimit): ?>
                            <span class="badge bg-success py-2 px-3"><i class="fas fa-check-circle me-1"></i> All Slots Full</span>
                        <?php endif; ?>
                        <?php if ($assignedCount > 0): ?>
                            <form method="POST" class="d-inline" onsubmit="return confirm('Clear idle workstations from this assessment?');">
                                <?= CSRF::field() ?>
                                <input type="hidden" name="action" value="clear_idle_stations">
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Clear idle workstations">
                                    <i class="fas fa-broom me-1"></i> Clear Idle
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body">
                    <?php if ($deviceLimit === 0 && count($slots) === 0): ?>
                        <div class="text-center py-5">
                            <div class="mb-3">
                                <span class="d-inline-flex align-items-center justify-content-center bg-primary bg-opacity-10 text-primary rounded-circle" style="width: 72px; height: 72px;">
                                    <i class="fas fa-sliders fa-2x"></i>
                                </span>
                            </div>
                            <h5 class="fw-bold mb-2">No Device Allocation Limit Set</h5>
                            <p class="text-muted small mx-auto" style="max-width: 480px;">
                                Set the maximum number of computers allowed for this test (e.g. 1, 2, 3, or 5). When users enter PC names at candidate intake or here, they are assigned to those limited slots.
                            </p>
                            <div class="d-flex justify-content-center flex-wrap gap-2 mt-3">
                                <button type="button" class="btn btn-outline-primary btn-sm px-3" onclick="setCount(1); document.getElementById('deviceAllocForm').submit();">
                                    <i class="fas fa-bolt me-1"></i> Allocate 1 PC
                                </button>
                                <button type="button" class="btn btn-outline-primary btn-sm px-3" onclick="setCount(2); document.getElementById('deviceAllocForm').submit();">
                                    <i class="fas fa-bolt me-1"></i> Allocate 2 PCs
                                </button>
                                <button type="button" class="btn btn-outline-primary btn-sm px-3" onclick="setCount(3); document.getElementById('deviceAllocForm').submit();">
                                    <i class="fas fa-bolt me-1"></i> Allocate 3 PCs
                                </button>
                                <button type="button" class="btn btn-outline-primary btn-sm px-3" onclick="setCount(5); document.getElementById('deviceAllocForm').submit();">
                                    <i class="fas fa-bolt me-1"></i> Allocate 5 PCs
                                </button>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="station-grid">
                            <?php foreach ($slots as $slot): ?>
                                <?php
                                $slotNum = $slot['slot_number'];
                                $isAssigned = $slot['is_assigned'];
                                $st = $slot['station'];
                                ?>
                                <?php if ($isAssigned && $st): ?>
                                    <?php
                                    $alloc = $st['status'] ?? 'idle';
                                    $cardClass = 'station-card-assigned';
                                    $statusDotClass = 'dot-assigned';
                                    $statusLabel = 'Assigned';

                                    if (($st['attempt_status'] ?? '') === 'in_progress') {
                                        $cardClass = 'station-card-active';
                                        $statusDotClass = 'dot-active';
                                        $statusLabel = 'In Progress';
                                    } elseif ($alloc === 'submitted') {
                                        $cardClass = 'station-card-submitted';
                                        $statusDotClass = 'dot-submitted';
                                        $statusLabel = 'Submitted';
                                    }
                                    ?>
                                    <div class="station-box <?= $cardClass ?>" data-id="<?= (int)$st['station_id'] ?>">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="badge bg-primary text-white" style="font-size: 0.7rem;">Slot #<?= $slotNum ?></span>
                                            <div class="d-flex align-items-center gap-1">
                                                <span class="status-dot <?= $statusDotClass ?>" title="<?= $statusLabel ?>"></span>
                                                <?php if (($st['attempt_status'] ?? '') !== 'in_progress'): ?>
                                                    <form method="POST" class="d-inline ms-1" onsubmit="return confirm('Unassign <?= e($st['station_code']) ?> from Slot #<?= $slotNum ?>?');">
                                                        <?= CSRF::field() ?>
                                                        <input type="hidden" name="action" value="unassign_station">
                                                        <input type="hidden" name="station_id" value="<?= (int)$st['station_id'] ?>">
                                                        <button type="submit" class="btn btn-link text-danger p-0 border-0" style="font-size: 0.75rem;" title="Unassign PC name">
                                                            <i class="fas fa-times"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="station-code fw-bold font-monospace fs-5 text-truncate" title="<?= e($st['station_code']) ?>">
                                            <?= e($st['station_code']) ?>
                                        </div>
                                        <div class="station-ip text-muted small font-monospace">
                                            <?= !empty($st['ip_address']) ? e($st['ip_address']) : '<span class="text-muted opacity-75">Workstation Code</span>' ?>
                                        </div>
                                        <?php if (!empty($st['student_name'])): ?>
                                            <div class="student-pill text-truncate mt-1" title="<?= e($st['student_name']) ?>">
                                                <i class="fas fa-user-graduate me-1 text-info"></i><?= e($st['student_name']) ?>
                                            </div>
                                        <?php else: ?>
                                            <div class="text-muted mt-1" style="font-size: 0.7rem;">
                                                <?= $statusLabel ?> &bull; Ready
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="station-box slot-card-available">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="badge bg-secondary" style="font-size: 0.7rem;">Slot #<?= $slotNum ?></span>
                                            <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25" style="font-size: 0.65rem;">Available</span>
                                        </div>
                                        <div class="my-2 text-center">
                                            <i class="fas fa-desktop text-muted opacity-50" style="font-size: 1.6rem;"></i>
                                            <div class="fw-semibold small mt-1">Waiting for PC Name</div>
                                            <div class="text-muted" style="font-size: 0.7rem;">Student enters on intake, or click below</div>
                                        </div>
                                        <button type="button" class="btn btn-outline-primary btn-sm w-100 mt-1" onclick="openAssignModal(<?= $slotNum ?>)">
                                            <i class="fas fa-plus me-1"></i> Enter PC Name
                                        </button>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="card-footer border-top text-muted small d-flex justify-content-between">
                    <span><i class="fas fa-circle-info me-1 text-primary"></i> Students enter their PC name at intake to claim an open slot up to the limit of <?= $deviceLimit > 0 ? $deviceLimit : 'N' ?> devices.</span>
                    <button type="button" class="btn btn-link btn-sm text-decoration-none text-muted p-0" onclick="window.location.reload()">
                        <i class="fas fa-rotate me-1"></i> Refresh Grid
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Assign PC Name -->
<div class="modal fade" id="assignStationModal" tabindex="-1" aria-labelledby="assignStationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border shadow">
            <form method="POST">
                <?= CSRF::field() ?>
                <input type="hidden" name="action" value="assign_station">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="assignStationModalLabel">
                        <i class="fas fa-desktop text-primary me-2"></i> Assign PC Name to Allocation Slot
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="station_code_input" class="form-label fw-semibold">Enter PC Name / Workstation Code:</label>
                        <input type="text" name="station_code" id="station_code_input" class="form-control font-monospace form-control-lg" placeholder="e.g. PC-01, LAB1-05, DESK-10" required autofocus>
                        <div class="form-text small text-muted mt-2">
                            <i class="fas fa-info-circle me-1"></i> This PC name will be assigned to an available slot out of your <?= $deviceLimit ?> allocated device slots (<?= $assignedCount ?> of <?= $deviceLimit ?> currently assigned).
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-bold">
                        <i class="fas fa-check-circle me-1"></i> Assign to Slot
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.station-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(145px, 1fr));
    gap: 0.85rem;
}
.station-box {
    border-radius: 8px;
    padding: 0.85rem;
    transition: all 0.15s ease;
    position: relative;
}
.station-box:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
}

.slot-card-available {
    border: 2px dashed #94a3b8;
    background-color: rgba(148, 163, 184, 0.05);
}
.slot-card-available:hover {
    border-color: #0284c7;
    background-color: rgba(2, 132, 199, 0.05);
}

/* Light theme station cards */
[data-bs-theme="light"] .station-card-idle {
    border: 2px solid #cbd5e1;
    background-color: #f8fafc;
    color: #1e293b;
}
[data-bs-theme="light"] .station-card-assigned {
    border: 2px solid #0284c7;
    background-color: #f0f9ff;
    color: #0369a1;
}
[data-bs-theme="light"] .station-card-active {
    border: 2px solid #10b981;
    background-color: #ecfdf5;
    color: #065f46;
}
[data-bs-theme="light"] .station-card-submitted {
    border: 2px solid #8b5cf6;
    background-color: #f5f3ff;
    color: #5b21b6;
}
[data-bs-theme="light"] .student-pill {
    background: rgba(0, 0, 0, 0.07);
    color: #0f172a;
    font-weight: 600;
}

/* Dark theme station cards */
[data-bs-theme="dark"] .slot-card-available {
    border: 2px dashed #4b5563;
    background-color: rgba(75, 85, 99, 0.1);
}
[data-bs-theme="dark"] .slot-card-available:hover {
    border-color: #38bdf8;
    background-color: rgba(56, 189, 248, 0.08);
}
[data-bs-theme="dark"] .station-card-idle {
    border: 2px solid #374151;
    background-color: #111827;
    color: #e2e8f0;
}
[data-bs-theme="dark"] .station-card-assigned {
    border: 2px solid #0284c7;
    background-color: #082f49;
    color: #bae6fd;
}
[data-bs-theme="dark"] .station-card-active {
    border: 2px solid #10b981;
    background-color: #064e3b;
    color: #a7f3d0;
}
[data-bs-theme="dark"] .station-card-submitted {
    border: 2px solid #8b5cf6;
    background-color: #4c1d95;
    color: #ddd6fe;
}
[data-bs-theme="dark"] .student-pill {
    background: rgba(0, 0, 0, 0.45);
    color: #f1f5f9;
}

.status-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    display: inline-block;
}
.dot-idle { background-color: #94a3b8; }
.dot-assigned { background-color: #0284c7; box-shadow: 0 0 6px rgba(2, 132, 199, 0.6); }
.dot-active { background-color: #10b981; box-shadow: 0 0 8px rgba(16, 185, 129, 0.6); }
.dot-submitted { background-color: #8b5cf6; }

.student-pill {
    font-size: 0.75rem;
    padding: 2px 4px;
    border-radius: 4px;
}
</style>

<script>
function setCount(val) {
    const input = document.getElementById('device_count');
    if (input) {
        input.value = val;
    }
}

function openAssignModal(slotNum) {
    const modalEl = document.getElementById('assignStationModal');
    if (modalEl) {
        const modal = new bootstrap.Modal(modalEl);
        modal.show();
        setTimeout(() => {
            const input = document.getElementById('station_code_input');
            if (input) input.focus();
        }, 350);
    }
}
</script>

