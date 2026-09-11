/**
 * PTM Assessment System — Theme Engine
 * Manages Light Mode, Dark Mode, and System Auto detection.
 * Persists user preference in localStorage ('ptm_theme').
 * Zero dependencies, high-performance, FOUC-free.
 */

(function () {
    'use strict';

    const STORAGE_KEY = 'ptm_theme';

    const PTMTheme = {
        /**
         * Get the user's stored preference ('light', 'dark', 'auto').
         * Defaults to 'auto' (takes from device OS).
         */
        getSavedTheme: function () {
            const stored = localStorage.getItem(STORAGE_KEY);
            return (stored === 'light' || stored === 'dark' || stored === 'auto') ? stored : 'auto';
        },

        /**
         * Detect device OS preference.
         * Returns 'dark' or 'light'.
         */
        getSystemTheme: function () {
            return (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) ? 'dark' : 'light';
        },

        /**
         * Get the active effective theme ('light' or 'dark').
         */
        getEffectiveTheme: function () {
            const saved = this.getSavedTheme();
            return (saved === 'auto') ? this.getSystemTheme() : saved;
        },

        /**
         * Set and persist theme mode.
         * @param {'light'|'dark'|'auto'} mode 
         */
        setTheme: function (mode) {
            if (mode !== 'light' && mode !== 'dark' && mode !== 'auto') {
                mode = 'auto';
            }
            try {
                localStorage.setItem(STORAGE_KEY, mode);
            } catch (e) {
                // Ignore localStorage errors in private browsing
            }
            this.applyTheme(mode);
        },

        /**
         * Apply theme to DOM and sync UI.
         * @param {'light'|'dark'|'auto'} [mode]
         */
        applyTheme: function (mode) {
            if (!mode) mode = this.getSavedTheme();
            const effective = (mode === 'auto') ? this.getSystemTheme() : mode;

            // Apply to document root for Bootstrap 5 & custom CSS
            document.documentElement.setAttribute('data-bs-theme', effective);
            document.documentElement.setAttribute('data-theme-mode', mode);

            // Synchronize any theme controls in DOM
            this.syncUI(mode, effective);

            // Dispatch event for components that need to respond (e.g. charts, live monitors)
            try {
                window.dispatchEvent(new CustomEvent('ptm-theme-changed', {
                    detail: { mode: mode, effective: effective }
                }));
            } catch (e) {}
        },

        /**
         * Synchronize UI controls (icons, active classes, dropdowns)
         */
        syncUI: function (mode, effective) {
            // 1. Dropdown items or buttons with data-theme-value
            document.querySelectorAll('[data-theme-value]').forEach(function (btn) {
                const val = btn.getAttribute('data-theme-value');
                if (val === mode) {
                    btn.classList.add('active');
                    btn.setAttribute('aria-pressed', 'true');
                } else {
                    btn.classList.remove('active');
                    btn.setAttribute('aria-pressed', 'false');
                }
            });

            // 2. Navbar Theme Trigger Icon and Label
            const iconEl = document.getElementById('currentThemeIcon');
            const textEl = document.getElementById('currentThemeText');

            if (iconEl) {
                iconEl.className = '';
                if (mode === 'light') {
                    iconEl.className = 'fas fa-sun text-warning';
                } else if (mode === 'dark') {
                    iconEl.className = 'fas fa-moon text-info';
                } else {
                    // Auto: show desktop icon with indicator of current effective state
                    iconEl.className = effective === 'dark' ? 'fas fa-desktop text-info' : 'fas fa-desktop text-warning';
                }
            }

            if (textEl) {
                textEl.textContent = mode === 'auto' ? 'Auto (' + effective + ')' : mode.charAt(0).toUpperCase() + mode.slice(1);
            }

            // 3. Simple toggle buttons (toggles between light and dark)
            document.querySelectorAll('.theme-quick-toggle').forEach(function (toggleBtn) {
                toggleBtn.setAttribute('title', 'Switch to ' + (effective === 'dark' ? 'Light' : 'Dark') + ' Mode');
                const quickIcon = toggleBtn.querySelector('i');
                if (quickIcon) {
                    quickIcon.className = effective === 'dark' ? 'fas fa-sun text-warning' : 'fas fa-moon text-primary';
                }
            });
        },

        /**
         * Fast early execution in <head> to prevent Flash of Unstyled Content (FOUC).
         */
        initEarly: function () {
            const effective = this.getEffectiveTheme();
            document.documentElement.setAttribute('data-bs-theme', effective);
            document.documentElement.setAttribute('data-theme-mode', this.getSavedTheme());
        },

        /**
         * Full initialization on DOM ready: attach click handlers and OS change listener.
         */
        init: function () {
            const self = this;
            this.applyTheme(this.getSavedTheme());

            // Listen for OS scheme change
            if (window.matchMedia) {
                const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
                const handleOsChange = function () {
                    if (self.getSavedTheme() === 'auto') {
                        self.applyTheme('auto');
                    }
                };
                if (mediaQuery.addEventListener) {
                    mediaQuery.addEventListener('change', handleOsChange);
                } else if (mediaQuery.addListener) {
                    mediaQuery.addListener(handleOsChange);
                }
            }

            // Delegate clicks on theme buttons
            document.addEventListener('click', function (e) {
                // 1. Selection in dropdowns
                const themeBtn = e.target.closest('[data-theme-value]');
                if (themeBtn) {
                    const chosen = themeBtn.getAttribute('data-theme-value');
                    self.setTheme(chosen);
                    return;
                }

                // 2. Quick toggle button
                const quickToggle = e.target.closest('.theme-quick-toggle');
                if (quickToggle) {
                    const currentEffective = self.getEffectiveTheme();
                    const newMode = currentEffective === 'dark' ? 'light' : 'dark';
                    self.setTheme(newMode);
                }
            });
        }
    };

    // Run early init immediately when script is evaluated in <head>
    PTMTheme.initEarly();

    // Attach to window
    window.PTMTheme = PTMTheme;

    // Run DOM-dependent init when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            PTMTheme.init();
        });
    } else {
        PTMTheme.init();
    }
})();
