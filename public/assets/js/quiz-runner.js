/**
 * PTM Assessment System — Client-Side Exam Runner Controller
 * Pure Vanilla JavaScript (ES6+), Zero External CDNs, Server-Authoritative Sync
 */

(function() {
    'use strict';

    // State Configuration passed via global window.EXAM_CONFIG
    const config = window.EXAM_CONFIG || {};
    const attemptId = config.attemptId;
    const questions = config.questions || [];
    const autosaveUrl = config.autosaveUrl || 'index.php?page=api-autosave';
    const submitUrl = config.submitUrl || 'index.php?page=test-submit';
    const telemetryUrl = config.telemetryUrl || 'index.php?page=api-quiz-telemetry';
    const anomalyTimer = (config.anomalyTimer && config.anomalyTimer > 0) ? parseInt(config.anomalyTimer, 10) : 5;
    const detectBlur = config.detectBlur !== false;
    const strictFullscreen = config.strictFullscreen !== false;
    const storageKey = `ptm_answers_attempt_${attemptId}`;

    // Local runtime state
    let answers = {};
    let flaggedQuestions = new Set();
    let currentQuestionIndex = 0;
    let isSubmitting = false;
    let isSaving = false;
    let isOnline = navigator.onLine;
    let pendingDirtyQuestions = new Set();

    // DOM Elements
    const elements = {
        timerDisplay: document.getElementById('countdownClock'),
        networkBadge: document.getElementById('networkStatusBadge'),
        networkText: document.getElementById('networkStatusText'),
        qNumber: document.getElementById('activeQuestionNumber'),
        qMarks: document.getElementById('activeQuestionMarks'),
        qTypeBadge: document.getElementById('activeQuestionTypeBadge'),
        qStatement: document.getElementById('activeQuestionStatement'),
        qContainer: document.getElementById('activeQuestionInputContainer'),
        flagBtn: document.getElementById('btnFlagReview'),
        prevBtn: document.getElementById('btnPrev'),
        nextBtn: document.getElementById('btnNext'),
        submitModalBtn: document.getElementById('btnConfirmSubmit'),
        navGrid: document.getElementById('questionNavigatorGrid'),
        cntAnswered: document.getElementById('statCountAnswered'),
        cntUnanswered: document.getElementById('statCountUnanswered'),
        cntFlagged: document.getElementById('statCountFlagged'),
        modalAnswered: document.getElementById('modalSummaryAnswered'),
        modalUnanswered: document.getElementById('modalSummaryUnanswered'),
    };

    /**
     * Escape HTML entities to prevent XSS and rendering breakages
     */
    function escapeHtml(str) {
        if (str === null || str === undefined) return '';
        const div = document.createElement('div');
        div.textContent = String(str);
        return div.innerHTML;
    }

    const OPTION_LETTERS = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'];

    // -------------------------------------------------------------
    // 1. Initialization & LocalStorage Cache Recovery
    // -------------------------------------------------------------
    function init() {
        if (!questions || questions.length === 0) {
            if (elements.qStatement) {
                elements.qStatement.innerHTML = '<div class="alert alert-warning py-3 text-center"><i class="fas fa-exclamation-triangle me-2"></i> No questions have been attached to this assessment yet.<br>Please notify your teacher or proctor.</div>';
            }
            if (elements.qContainer) {
                elements.qContainer.innerHTML = '';
            }
            return;
        }

        loadCachedAnswers();
        initContainerEvents();
        renderNavigatorGrid();
        loadQuestion(0);
        initTimer();
        initSecurityGuards();
        initNetworkListeners();
    }

    function initContainerEvents() {
        if (!elements.qContainer) return;

        // Change event (fires when input is ticked or toggled)
        elements.qContainer.addEventListener('change', function(e) {
            const q = questions[currentQuestionIndex];
            if (!q) return;

            if (q.question_type === 'single_choice' || q.question_type === 'true_false') {
                const checked = elements.qContainer.querySelector('input[type="radio"]:checked');
                if (checked) {
                    const optId = parseInt(checked.value, 10);
                    answers[q.id] = optId;
                    persistLocalCache();
                    sendAutosave(q.id, optId);
                    updateOptionStyles();
                    updatePaletteVisuals();
                }
            } else if (q.question_type === 'multiple_choice') {
                const checkedBoxes = elements.qContainer.querySelectorAll('input[type="checkbox"]:checked');
                const optIds = Array.from(checkedBoxes).map(cb => parseInt(cb.value, 10));
                answers[q.id] = optIds;
                persistLocalCache();
                sendAutosave(q.id, optIds);
                updateOptionStyles();
                updatePaletteVisuals();
            }
        });

        // Click event on card (for clicking outside the direct radio/checkbox input)
        elements.qContainer.addEventListener('click', function(e) {
            const card = e.target.closest('.option-item');
            if (!card) return;

            // If clicked on input itself or label, let browser handle natively
            if (e.target.tagName === 'INPUT' || e.target.tagName === 'LABEL' || e.target.closest('label')) {
                setTimeout(updateOptionStyles, 10);
                return;
            }

            const input = card.querySelector('input');
            if (!input) return;

            if (input.type === 'radio') {
                input.checked = true;
                input.dispatchEvent(new Event('change', { bubbles: true }));
            } else if (input.type === 'checkbox') {
                input.checked = !input.checked;
                input.dispatchEvent(new Event('change', { bubbles: true }));
            }
        });
    }

    function updateOptionStyles() {
        if (!elements.qContainer) return;
        const cards = elements.qContainer.querySelectorAll('.option-item');
        cards.forEach(card => {
            const input = card.querySelector('input');
            if (input && input.checked) {
                card.classList.add('selected');
            } else if (input) {
                card.classList.remove('selected');
            }
        });
    }

    function loadCachedAnswers() {
        // Hydrate initially from server-provided existing answers
        if (config.initialAnswers && typeof config.initialAnswers === 'object') {
            answers = Object.assign({}, config.initialAnswers);
        }

        // Merge with local offline cache if exists
        try {
            const cached = localStorage.getItem(storageKey);
            if (cached) {
                const parsed = JSON.parse(cached);
                answers = Object.assign({}, answers, parsed);
            }
        } catch (e) {
            console.warn('LocalStorage unavailable:', e);
        }
    }

    function persistLocalCache() {
        try {
            localStorage.setItem(storageKey, JSON.stringify(answers));
        } catch (e) {
            console.warn('Could not save to localStorage:', e);
        }
    }

    // -------------------------------------------------------------
    // 2. Question Navigation & Rendering
    // -------------------------------------------------------------
    function renderNavigatorGrid() {
        if (!elements.navGrid) return;
        elements.navGrid.innerHTML = '';

        questions.forEach((q, idx) => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'nav-cell';
            btn.id = `navBtn_${q.id}`;
            btn.textContent = idx + 1;
            btn.onclick = () => loadQuestion(idx);
            elements.navGrid.appendChild(btn);
        });

        updatePaletteVisuals();
    }

    function updatePaletteVisuals() {
        let answeredCount = 0;
        let flaggedCount = 0;

        questions.forEach((q, idx) => {
            const btn = document.getElementById(`navBtn_${q.id}`);
            if (!btn) return;

            const hasAns = hasValidAnswer(q.id);
            const isFlagged = flaggedQuestions.has(q.id);

            btn.className = 'nav-cell';
            if (idx === currentQuestionIndex) {
                btn.classList.add('active');
            }

            if (isFlagged) {
                btn.classList.add('flagged');
                flaggedCount++;
            } else if (hasAns) {
                btn.classList.add('answered');
                answeredCount++;
            } else {
                btn.classList.add('unanswered');
            }
        });

        const total = questions.length;
        if (elements.cntAnswered) elements.cntAnswered.textContent = answeredCount;
        if (elements.cntUnanswered) elements.cntUnanswered.textContent = total - answeredCount;
        if (elements.cntFlagged) elements.cntFlagged.textContent = flaggedCount;
    }

    function hasValidAnswer(qid) {
        const a = answers[qid];
        if (a === undefined || a === null) return false;
        if (Array.isArray(a)) return a.length > 0;
        if (typeof a === 'string') return a.trim().length > 0;
        return true;
    }

    function loadQuestion(idx) {
        if (idx < 0 || idx >= questions.length) return;
        currentQuestionIndex = idx;
        const q = questions[idx];

        if (elements.qNumber) elements.qNumber.textContent = `Question ${idx + 1} of ${questions.length}`;
        if (elements.qMarks) elements.qMarks.textContent = `+${parseFloat(q.marks).toFixed(2)} Marks`;
        if (elements.qTypeBadge) elements.qTypeBadge.textContent = formatQuestionType(q.question_type);
        if (elements.qStatement) elements.qStatement.innerHTML = escapeHtml(q.question_text).replace(/\n/g, '<br>');

        renderQuestionInputs(q);

        // Prev / Next button states
        if (elements.prevBtn) elements.prevBtn.disabled = (idx === 0);
        if (elements.nextBtn) {
            elements.nextBtn.innerHTML = (idx === questions.length - 1) 
                ? 'Review & Finish <i class="fas fa-flag-checkered ms-1"></i>' 
                : 'Next Question <i class="fas fa-arrow-right ms-1"></i>';
        }

        // Review flag button state
        updateFlagButton();
        updatePaletteVisuals();
    }

    function formatQuestionType(type) {
        const labels = {
            'single_choice': 'Single Choice',
            'multiple_choice': 'Multiple Choice (Select all that apply)',
            'true_false': 'True / False',
            'fill_blank': 'Fill in the Blank',
            'short_answer': 'Short Answer / Written Response'
        };
        return labels[type] || 'Question';
    }

    function renderQuestionInputs(q) {
        if (!elements.qContainer) return;
        elements.qContainer.innerHTML = '';
        const currentAns = answers[q.id];

        switch (q.question_type) {
            case 'single_choice':
            case 'true_false':
                (q.options || []).forEach((opt, optIdx) => {
                    const letter = OPTION_LETTERS[optIdx] || String.fromCharCode(65 + optIdx);
                    let isSelected = false;
                    if (Array.isArray(currentAns)) {
                        isSelected = currentAns.some(id => String(id) === String(opt.id));
                    } else if (currentAns !== undefined && currentAns !== null) {
                        isSelected = String(currentAns) === String(opt.id);
                    }

                    const card = document.createElement('div');
                    card.className = `option-item ${isSelected ? 'selected' : ''}`;
                    card.dataset.optId = opt.id;
                    card.innerHTML = `
                        <label class="d-flex align-items-center gap-3 w-100 mb-0 py-1" for="radio_opt_${opt.id}" style="cursor: pointer;">
                            <input type="radio" 
                                   name="question_opt_${q.id}" 
                                   id="radio_opt_${opt.id}" 
                                   value="${opt.id}" 
                                   class="form-check-input exam-input" 
                                   ${isSelected ? 'checked' : ''}>
                            <span class="option-badge">${letter}</span>
                            <span class="option-text fs-5">${escapeHtml(opt.option_text)}</span>
                        </label>
                    `;
                    elements.qContainer.appendChild(card);
                });
                break;

            case 'multiple_choice':
                const selectedSet = new Set(
                    Array.isArray(currentAns) 
                        ? currentAns.map(String) 
                        : (currentAns !== undefined && currentAns !== null ? [String(currentAns)] : [])
                );
                (q.options || []).forEach((opt, optIdx) => {
                    const letter = OPTION_LETTERS[optIdx] || String.fromCharCode(65 + optIdx);
                    const isSelected = selectedSet.has(String(opt.id));

                    const card = document.createElement('div');
                    card.className = `option-item ${isSelected ? 'selected' : ''}`;
                    card.dataset.optId = opt.id;
                    card.innerHTML = `
                        <label class="d-flex align-items-center gap-3 w-100 mb-0 py-1" for="check_opt_${opt.id}" style="cursor: pointer;">
                            <input type="checkbox" 
                                   name="question_opt_${q.id}[]" 
                                   id="check_opt_${opt.id}" 
                                   value="${opt.id}" 
                                   class="form-check-input exam-input" 
                                   ${isSelected ? 'checked' : ''}>
                            <span class="option-badge">${letter}</span>
                            <span class="option-text fs-5">${escapeHtml(opt.option_text)}</span>
                        </label>
                    `;
                    elements.qContainer.appendChild(card);
                });
                break;

            case 'fill_blank':
                const textVal = (typeof currentAns === 'string') ? currentAns : '';
                const wrapper = document.createElement('div');
                wrapper.className = 'py-2';
                wrapper.innerHTML = `
                    <label class="form-label text-muted small text-uppercase fw-bold mb-2">
                        Type Your Answer:
                    </label>
                    <input type="text" class="form-control form-control-lg font-monospace" 
                           id="fillBlankInput" placeholder="Type answer here..." value="${escapeHtml(textVal)}" autocomplete="off">
                    <div class="form-text text-muted small mt-2">
                        <i class="fas fa-info-circle me-1"></i> Spelling counts. Case does not matter.
                    </div>
                `;
                elements.qContainer.appendChild(wrapper);

                const inputEl = document.getElementById('fillBlankInput');
                if (inputEl) {
                    inputEl.focus();
                    let debounceTimer;
                    inputEl.addEventListener('input', (e) => {
                        const val = e.target.value;
                        answers[q.id] = val;
                        persistLocalCache();
                        updatePaletteVisuals();

                        clearTimeout(debounceTimer);
                        debounceTimer = setTimeout(() => {
                            sendAutosave(q.id, val);
                        }, 400);
                    });
                }
                break;

            case 'short_answer':
                const shortAnsVal = (typeof currentAns === 'string') ? currentAns : '';
                const saWrapper = document.createElement('div');
                saWrapper.className = 'py-2';
                saWrapper.innerHTML = `
                    <label class="form-label text-muted small text-uppercase fw-bold mb-2">
                        Type Your Written Response:
                    </label>
                    <textarea class="form-control font-monospace fs-5" 
                              id="shortAnswerInput" rows="6" placeholder="Enter your detailed response here..." autocomplete="off">${escapeHtml(shortAnsVal)}</textarea>
                    <div class="form-text text-muted small mt-2">
                        <i class="fas fa-feather-alt me-1"></i> Responses are automatically saved as you write and reviewed by the instructor.
                    </div>
                `;
                elements.qContainer.appendChild(saWrapper);

                const saInputEl = document.getElementById('shortAnswerInput');
                if (saInputEl) {
                    saInputEl.focus();
                    let saDebounceTimer;
                    saInputEl.addEventListener('input', (e) => {
                        const val = e.target.value;
                        answers[q.id] = val;
                        persistLocalCache();
                        updatePaletteVisuals();

                        clearTimeout(saDebounceTimer);
                        saDebounceTimer = setTimeout(() => {
                            sendAutosave(q.id, val);
                        }, 500);
                    });
                }
                break;
        }
    }

    function toggleFlag() {
        const q = questions[currentQuestionIndex];
        if (!q) return;

        if (flaggedQuestions.has(q.id)) {
            flaggedQuestions.delete(q.id);
        } else {
            flaggedQuestions.add(q.id);
        }
        updateFlagButton();
        updatePaletteVisuals();
    }

    function updateFlagButton() {
        if (!elements.flagBtn) return;
        const q = questions[currentQuestionIndex];
        if (!q) return;

        const isFlagged = flaggedQuestions.has(q.id);
        if (isFlagged) {
            elements.flagBtn.className = 'btn btn-warning btn-sm fw-bold';
            elements.flagBtn.innerHTML = '<i class="fas fa-flag me-1"></i> Flagged';
        } else {
            elements.flagBtn.className = 'btn btn-outline-secondary btn-sm';
            elements.flagBtn.innerHTML = '<i class="far fa-flag me-1"></i> Flag for Review';
        }
    }

    // -------------------------------------------------------------
    // 3. Real-Time Server Autosave with Local Fallback
    // -------------------------------------------------------------
    function sendAutosave(questionId, answerValue) {
        pendingDirtyQuestions.add(questionId);

        if (!navigator.onLine) {
            setNetworkStatus(false, 'Offline (Saved to PC)');
            return;
        }

        setNetworkStatus(true, 'Saving...');

        const payload = {
            attempt_id: attemptId,
            question_id: questionId,
            answer: answerValue
        };

        fetch(autosaveUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(payload)
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                pendingDirtyQuestions.delete(questionId);
                setNetworkStatus(true, 'All answers saved');
            } else {
                throw new Error(data.error || 'Server rejected save');
            }
        })
        .catch(err => {
            console.warn('Autosave network issue:', err);
            setNetworkStatus(false, 'Offline (Saved to PC)');
        });
    }

    function syncPendingAnswers() {
        if (pendingDirtyQuestions.size === 0 || !navigator.onLine) return;

        setNetworkStatus(true, 'Syncing offline answers...');
        const pendingArray = Array.from(pendingDirtyQuestions);

        Promise.all(pendingArray.map(qid => {
            return fetch(autosaveUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    attempt_id: attemptId,
                    question_id: qid,
                    answer: answers[qid]
                })
            }).then(r => r.json()).then(d => {
                if (d.success) pendingDirtyQuestions.delete(qid);
            }).catch(() => {});
        })).then(() => {
            if (pendingDirtyQuestions.size === 0) {
                setNetworkStatus(true, 'All answers saved');
            }
        });
    }

    function setNetworkStatus(online, text) {
        if (!elements.networkBadge || !elements.networkText) return;
        if (online) {
            elements.networkBadge.className = 'badge bg-success-subtle text-success border border-success';
            elements.networkText.textContent = text || 'Connected';
        } else {
            elements.networkBadge.className = 'badge bg-danger-subtle text-danger border border-danger animate-pulse';
            elements.networkText.textContent = text || 'Offline - Retrying...';
        }
    }

    function initNetworkListeners() {
        window.addEventListener('online', () => {
            setNetworkStatus(true, 'Reconnected. Syncing...');
            syncPendingAnswers();
        });
        window.addEventListener('offline', () => {
            setNetworkStatus(false, 'Offline (Saved to PC)');
        });
    }

    // -------------------------------------------------------------
    // 4. Server-Authoritative Timer Countdown
    // -------------------------------------------------------------
    function initTimer() {
        if (!elements.timerDisplay || !config.expectedEndEpoch) return;

        function tick() {
            const now = Math.floor(Date.now() / 1000);
            const remaining = Math.max(0, config.expectedEndEpoch - now);

            const m = Math.floor(remaining / 60);
            const s = remaining % 60;
            elements.timerDisplay.textContent = `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;

            if (remaining <= 60) {
                elements.timerDisplay.classList.add('text-danger', 'animate-pulse');
            } else if (remaining <= 300) {
                elements.timerDisplay.classList.add('text-warning');
            }

            if (remaining <= 0) {
                clearInterval(timerInterval);
                performFinalSubmit(true);
            }
        }

        tick();
        const timerInterval = setInterval(tick, 1000);
    }

    // -------------------------------------------------------------
    // 5. Anti-Cheating & Accidental Exit Guards
    // -------------------------------------------------------------
    let blurStartTime = null;
    let blurTimeout = null;
    let violationTriggered = false;

    function sendTelemetryViolation(eventType, durationSec) {
        if (!attemptId) return;
        const payload = {
            attempt_id: attemptId,
            event_type: eventType || 'TAB_SWITCH',
            severity: 'high',
            description: `Violations have been detected: Student navigated away from assessment window for ${durationSec}s (threshold: ${anomalyTimer}s)`,
            metadata: {
                duration_seconds: durationSec,
                threshold: anomalyTimer,
                timestamp: new Date().toISOString()
            }
        };

        fetch(telemetryUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(payload)
        }).then(r => r.json()).then(d => {
            if (d && d.violations_count) {
                console.warn(`Proctoring violation logged. Total incidents: ${d.violations_count}`);
            }
        }).catch(err => {
            console.warn('Could not transmit proctoring telemetry:', err);
        });
    }

    function showProctoringViolationModal(durationSec) {
        const durEl = document.getElementById('proctoringViolationDuration');
        if (durEl) durEl.textContent = String(durationSec);

        const modalEl = document.getElementById('proctoringViolationModal');
        if (modalEl && window.bootstrap) {
            let instance = window.bootstrap.Modal.getInstance(modalEl);
            if (!instance) {
                instance = new window.bootstrap.Modal(modalEl, { backdrop: 'static', keyboard: false });
            }
            instance.show();
        }
    }

    window.dismissProctoringViolationAlert = function() {
        const modalEl = document.getElementById('proctoringViolationModal');
        if (modalEl && window.bootstrap) {
            const instance = window.bootstrap.Modal.getInstance(modalEl);
            if (instance) instance.hide();
        }
        if (strictFullscreen && !document.fullscreenElement) {
            window.requestFullscreenExam();
        }
    };

    function initSecurityGuards() {
        // Prevent accidental back/refresh
        window.addEventListener('beforeunload', function(e) {
            if (!isSubmitting) {
                e.preventDefault();
                e.returnValue = 'Your assessment is currently active. Are you sure you want to leave?';
                return e.returnValue;
            }
        });

        // Fullscreen Enforcement
        if (strictFullscreen) {
            document.addEventListener('fullscreenchange', function() {
                if (!document.fullscreenElement && !isSubmitting) {
                    showFullscreenModal();
                    sendTelemetryViolation('FULLSCREEN_EXIT', 0);
                }
            });
        }

        // Window blur and away timer tracking
        function handleAwayStart() {
            if (isSubmitting || !detectBlur) return;
            if (blurStartTime !== null) return; // already in away state
            blurStartTime = Date.now();
            violationTriggered = false;

            clearTimeout(blurTimeout);
            blurTimeout = setTimeout(() => {
                if (blurStartTime !== null) {
                    violationTriggered = true;
                    const awayDuration = Math.max(anomalyTimer, Math.round((Date.now() - blurStartTime) / 1000));
                    sendTelemetryViolation('TAB_SWITCH', awayDuration);
                    showProctoringViolationModal(awayDuration);
                }
            }, anomalyTimer * 1000);
        }

        function handleAwayEnd() {
            if (isSubmitting || !detectBlur) return;
            if (blurStartTime === null) return;

            const totalAwayDuration = Math.round((Date.now() - blurStartTime) / 1000);
            clearTimeout(blurTimeout);

            if (totalAwayDuration >= anomalyTimer) {
                if (!violationTriggered) {
                    violationTriggered = true;
                    sendTelemetryViolation('TAB_SWITCH', totalAwayDuration);
                    showProctoringViolationModal(totalAwayDuration);
                } else {
                    const durEl = document.getElementById('proctoringViolationDuration');
                    if (durEl) durEl.textContent = String(totalAwayDuration);
                }
            }

            blurStartTime = null;
        }

        window.addEventListener('blur', handleAwayStart);
        window.addEventListener('focus', handleAwayEnd);
        document.addEventListener('visibilitychange', function() {
            if (document.hidden) {
                handleAwayStart();
            } else {
                handleAwayEnd();
            }
        });

        // Disable copy-paste & right click
        document.addEventListener('contextmenu', e => e.preventDefault());
        ['copy', 'cut', 'paste'].forEach(evt => {
            document.addEventListener(evt, e => e.preventDefault());
        });
    }

    function showFullscreenModal() {
        const modalEl = document.getElementById('fullscreenModal');
        if (modalEl && window.bootstrap) {
            const modal = new window.bootstrap.Modal(modalEl, { backdrop: 'static', keyboard: false });
            modal.show();
        }
    }

    window.requestFullscreenExam = function() {
        const elem = document.documentElement;
        if (elem.requestFullscreen) {
            elem.requestFullscreen().then(() => {
                const modalEl = document.getElementById('fullscreenModal');
                if (modalEl && window.bootstrap) {
                    const instance = window.bootstrap.Modal.getInstance(modalEl);
                    if (instance) instance.hide();
                }
            }).catch(() => {});
        }
    };

    // -------------------------------------------------------------
    // 6. Terminal Submission
    // -------------------------------------------------------------
    window.confirmSubmitExam = function() {
        let answered = 0;
        questions.forEach(q => {
            if (hasValidAnswer(q.id)) answered++;
        });

        if (elements.modalAnswered) elements.modalAnswered.textContent = answered;
        if (elements.modalUnanswered) elements.modalUnanswered.textContent = questions.length - answered;

        const modalEl = document.getElementById('confirmSubmitModal');
        if (modalEl && window.bootstrap) {
            new window.bootstrap.Modal(modalEl).show();
        }
    };

    window.performFinalSubmit = function(isTimeout = false) {
        if (isSubmitting) return;
        isSubmitting = true;

        if (elements.submitModalBtn) {
            elements.submitModalBtn.disabled = true;
            elements.submitModalBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Submitting...';
        }

        // Create and submit standard POST form to trigger clean redirect
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = submitUrl;

        const addField = (name, val) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = val;
            form.appendChild(input);
        };

        addField('attempt_id', attemptId);
        addField('csrf_token', config.csrfToken || '');
        addField('all_answers_payload', JSON.stringify(answers));
        if (isTimeout) addField('is_timeout', '1');

        document.body.appendChild(form);
        form.submit();
    };

    // -------------------------------------------------------------
    // 7. Event Wireups & Global Navigation Helpers
    // -------------------------------------------------------------
    if (elements.prevBtn) {
        elements.prevBtn.onclick = () => loadQuestion(currentQuestionIndex - 1);
    }
    if (elements.nextBtn) {
        elements.nextBtn.onclick = () => {
            if (currentQuestionIndex === questions.length - 1) {
                window.confirmSubmitExam();
            } else {
                loadQuestion(currentQuestionIndex + 1);
            }
        };
    }
    if (elements.flagBtn) {
        elements.flagBtn.onclick = toggleFlag;
    }

    // Run on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();

