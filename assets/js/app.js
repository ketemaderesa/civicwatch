/* CivicWatch – shared front-end behaviour */
(function () {
    'use strict';

    var root = document.documentElement;
    var THEME_KEY = 'civicwatch-theme';

    function currentTheme() {
        return root.getAttribute('data-bs-theme') === 'dark' ? 'dark' : 'light';
    }

    function applyTheme(theme) {
        root.setAttribute('data-bs-theme', theme);
        try { localStorage.setItem(THEME_KEY, theme); } catch (e) { /* storage may be disabled */ }
        document.querySelectorAll('.theme-toggle').forEach(function (button) {
            var icon = button.querySelector('i');
            if (icon) {
                icon.className = theme === 'dark' ? 'fa-solid fa-sun' : 'fa-solid fa-moon';
            }
            button.setAttribute('title', theme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode');
            button.setAttribute('aria-label', theme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode');
        });
        window.dispatchEvent(new CustomEvent('civicwatch:theme', { detail: { theme: theme } }));
    }

    applyTheme(currentTheme());

    document.querySelectorAll('.theme-toggle').forEach(function (button) {
        button.addEventListener('click', function () {
            applyTheme(currentTheme() === 'dark' ? 'light' : 'dark');
        });
    });

    // -----------------------------------------------------------------------
    // Responsive sidebar
    // -----------------------------------------------------------------------
    var sidebar = document.getElementById('appSidebar');
    var backdrop = document.getElementById('sidebarBackdrop');
    var toggle = document.getElementById('sidebarToggle');
    var close = document.getElementById('sidebarClose');

    function openSidebar() {
        if (!sidebar) return;
        sidebar.classList.add('open');
        if (backdrop) backdrop.classList.add('show');
        document.body.style.overflow = 'hidden';
    }

    function closeSidebar() {
        if (!sidebar) return;
        sidebar.classList.remove('open');
        if (backdrop) backdrop.classList.remove('show');
        document.body.style.overflow = '';
    }

    if (toggle) toggle.addEventListener('click', openSidebar);
    if (close) close.addEventListener('click', closeSidebar);
    if (backdrop) backdrop.addEventListener('click', closeSidebar);
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') closeSidebar();
    });
    window.addEventListener('resize', function () {
        if (window.innerWidth >= 992) closeSidebar();
    });

    // -----------------------------------------------------------------------
    // Confirmation prompts
    // -----------------------------------------------------------------------
    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            var message = form.getAttribute('data-confirm') || 'Are you sure?';
            if (!window.confirm(message)) {
                event.preventDefault();
                event.stopImmediatePropagation();
            }
        });
    });

    // -----------------------------------------------------------------------
    // Image upload previews
    // -----------------------------------------------------------------------
    document.querySelectorAll('input[type="file"][data-preview]').forEach(function (input) {
        var targetSelector = input.getAttribute('data-preview');
        var target = targetSelector ? document.querySelector(targetSelector) : null;

        function renderPreview() {
            if (!target) return;
            target.innerHTML = '';
            var files = Array.prototype.slice.call(input.files || []);
            var limit = parseInt(input.getAttribute('data-max-files') || '20', 10);
            files.slice(0, limit).forEach(function (file) {
                if (!file.type || file.type.indexOf('image/') !== 0) return;
                var item = document.createElement('div');
                item.className = 'preview-item';
                var image = document.createElement('img');
                image.alt = file.name;
                image.src = URL.createObjectURL(file);
                image.onload = function () { URL.revokeObjectURL(image.src); };
                item.appendChild(image);
                target.appendChild(item);
            });
        }

        input.addEventListener('change', renderPreview);

        var dropZone = input.closest('.upload-drop');
        if (dropZone) {
            ['dragenter', 'dragover'].forEach(function (name) {
                dropZone.addEventListener(name, function (event) {
                    event.preventDefault();
                    dropZone.classList.add('dragover');
                });
            });
            ['dragleave', 'drop'].forEach(function (name) {
                dropZone.addEventListener(name, function (event) {
                    event.preventDefault();
                    dropZone.classList.remove('dragover');
                });
            });
            dropZone.addEventListener('drop', function (event) {
                if (!event.dataTransfer || !event.dataTransfer.files.length) return;
                try {
                    var transfer = new DataTransfer();
                    var max = parseInt(input.getAttribute('data-max-files') || '20', 10);
                    Array.prototype.slice.call(event.dataTransfer.files).slice(0, max).forEach(function (file) {
                        transfer.items.add(file);
                    });
                    input.files = transfer.files;
                    renderPreview();
                } catch (e) {
                    // DataTransfer is not supported in some older browsers; the
                    // normal file picker still works.
                }
            });
        }
    });

    // -----------------------------------------------------------------------
    // Password visibility
    // -----------------------------------------------------------------------
    document.querySelectorAll('[data-toggle-password]').forEach(function (button) {
        button.addEventListener('click', function () {
            var target = document.querySelector(button.getAttribute('data-toggle-password'));
            if (!target) return;
            var isPassword = target.getAttribute('type') === 'password';
            target.setAttribute('type', isPassword ? 'text' : 'password');
            var icon = button.querySelector('i');
            if (icon) icon.className = isPassword ? 'fa-solid fa-eye-slash' : 'fa-solid fa-eye';
        });
    });

    // -----------------------------------------------------------------------
    // Auto-dismiss alerts after a while
    // -----------------------------------------------------------------------
    document.querySelectorAll('.app-alert').forEach(function (alert) {
        window.setTimeout(function () {
            if (!alert.isConnected || !window.bootstrap) return;
            var instance = window.bootstrap.Alert.getOrCreateInstance(alert);
            instance.close();
        }, 7500);
    });

    // -----------------------------------------------------------------------
    // Bootstrap tooltips
    // -----------------------------------------------------------------------
    if (window.bootstrap) {
        document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (element) {
            window.bootstrap.Tooltip.getOrCreateInstance(element);
        });
    }

    // -----------------------------------------------------------------------
    // Copy-to-clipboard helpers
    // -----------------------------------------------------------------------
    document.querySelectorAll('[data-copy]').forEach(function (button) {
        button.addEventListener('click', function () {
            var value = button.getAttribute('data-copy');
            if (!value) return;
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(value).then(function () {
                    var original = button.innerHTML;
                    button.innerHTML = '<i class="fa-solid fa-check"></i>';
                    window.setTimeout(function () { button.innerHTML = original; }, 1400);
                });
            }
        });
    });

    // -----------------------------------------------------------------------
    // Shared Chart.js styles
    // -----------------------------------------------------------------------
    function chartPalette() {
        var dark = currentTheme() === 'dark';
        return {
            text: dark ? '#95a6bd' : '#68778c',
            grid: dark ? 'rgba(148,166,189,.12)' : 'rgba(104,119,140,.12)',
            teal: '#0f9b8e',
            tealLight: 'rgba(15,155,142,.16)',
            navy: '#0b1f3a',
            blue: '#2a76d2',
            amber: '#e0a02a',
            green: '#1a9e64',
            red: '#d64545',
            indigo: '#5b58c9',
            sky: '#4c9de0'
        };
    }

    window.CivicWatch = {
        theme: currentTheme,
        palette: chartPalette,
        applyTheme: applyTheme
    };
})();
