/* Admin navigation
   - Mobile: the sidebar becomes an off-canvas drawer.
   - Desktop: the sidebar is fixed to the viewport and can be collapsed with
     the same toggle button. The choice is remembered per browser. */
(function () {
    'use strict';

    var mobileQuery = window.matchMedia('(max-width: 1024px)');
    var desktopQuery = window.matchMedia('(min-width: 1025px)');
    var STORAGE_KEY = 'pdr.admin.sidebar.collapsed';

    function readCollapsed() {
        try {
            return window.localStorage.getItem(STORAGE_KEY) === '1';
        } catch (error) {
            return false;
        }
    }

    function writeCollapsed(value) {
        try {
            window.localStorage.setItem(STORAGE_KEY, value ? '1' : '0');
        } catch (error) {
            /* Private mode or storage disabled: the toggle still works this visit. */
        }
    }

    /* Apply the saved desktop state before the first paint where possible. */
    if (document.body && desktopQuery.matches && readCollapsed()) {
        document.body.classList.add('admin-sidebar-collapsed');
    }

    function init() {
        var body = document.body;
        var sidebar = document.querySelector('.sidebar');
        var topbar = document.querySelector('.top-navbar');

        if (!sidebar || !topbar) return;

        /* One functional toggle per page. */
        var toggle = topbar.querySelector('.admin-menu-toggle');
        if (!toggle) {
            toggle = document.createElement('button');
            toggle.type = 'button';
            toggle.className = 'admin-menu-toggle';
            toggle.innerHTML = '<i class="fa-solid fa-bars" aria-hidden="true"></i>';
        }

        /* Keep the toggle next to the page title, whatever the page markup is. */
        var titleSection = topbar.querySelector('.header-title-section');
        if (!titleSection) {
            var first = topbar.firstElementChild;
            if (first && first !== toggle) {
                titleSection = document.createElement('div');
                titleSection.className = 'header-title-section';
                titleSection.style.display = 'flex';
                titleSection.style.alignItems = 'center';
                titleSection.style.gap = '12px';
                topbar.insertBefore(titleSection, first);
                titleSection.appendChild(first);
            }
        }

        if (titleSection) {
            Array.prototype.slice.call(titleSection.children).forEach(function (child) {
                if (child.classList && child.classList.contains('fa-bars')) {
                    child.remove();
                }
            });
            if (toggle.parentNode !== titleSection) {
                titleSection.insertBefore(toggle, titleSection.firstChild);
            }
        } else if (!toggle.parentNode) {
            topbar.insertBefore(toggle, topbar.firstChild);
        }

        /* Close button inside the mobile drawer. */
        var closeBtn = sidebar.querySelector('.admin-nav-close');
        if (!closeBtn) {
            closeBtn = document.createElement('button');
            closeBtn.type = 'button';
            closeBtn.className = 'admin-nav-close';
            closeBtn.setAttribute('aria-label', 'Close navigation menu');
            closeBtn.innerHTML = '&times;';
            sidebar.appendChild(closeBtn);
        }

        /* Mobile backdrop. */
        var overlay = document.querySelector('.admin-nav-overlay');
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.className = 'admin-nav-overlay';
            overlay.setAttribute('aria-hidden', 'true');
            document.body.appendChild(overlay);
        }

        function setOpen(open) {
            body.classList.toggle('admin-nav-open', open);
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            toggle.setAttribute('aria-label', open ? 'Close navigation menu' : 'Open navigation menu');
        }

        function closeNav() {
            if (body.classList.contains('admin-nav-open')) setOpen(false);
        }

        function syncDesktopState() {
            var collapsed = readCollapsed();
            body.classList.toggle('admin-sidebar-collapsed', collapsed);
            toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
            toggle.setAttribute('aria-label', collapsed ? 'Expand navigation menu' : 'Collapse navigation menu');
        }

        if (desktopQuery.matches) {
            syncDesktopState();
        } else {
            toggle.setAttribute('aria-expanded', 'false');
            toggle.setAttribute('aria-label', 'Open navigation menu');
        }

        toggle.addEventListener('click', function () {
            if (desktopQuery.matches) {
                var collapsed = body.classList.toggle('admin-sidebar-collapsed');
                writeCollapsed(collapsed);
                toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
                toggle.setAttribute('aria-label', collapsed ? 'Expand navigation menu' : 'Collapse navigation menu');
                return;
            }
            setOpen(!body.classList.contains('admin-nav-open'));
        });

        closeBtn.addEventListener('click', closeNav);
        overlay.addEventListener('click', closeNav);

        /* Tapping a navigation link closes the mobile drawer. */
        sidebar.querySelectorAll('a').forEach(function (link) {
            link.addEventListener('click', closeNav);
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') closeNav();
        });

        function onViewportChange() {
            closeNav();
            if (desktopQuery.matches) {
                syncDesktopState();
            } else {
                body.classList.remove('admin-sidebar-collapsed');
                toggle.setAttribute('aria-expanded', 'false');
                toggle.setAttribute('aria-label', 'Open navigation menu');
            }
        }

        if (typeof mobileQuery.addEventListener === 'function') {
            mobileQuery.addEventListener('change', onViewportChange);
        } else if (typeof mobileQuery.addListener === 'function') {
            mobileQuery.addListener(onViewportChange);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
