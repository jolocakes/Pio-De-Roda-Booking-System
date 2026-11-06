/* Admin responsive navigation
   Adds a mobile menu button and turns the sidebar into an off-canvas drawer.
   Desktop behaviour is unchanged. */
(function () {
    'use strict';

    var mobileQuery = window.matchMedia('(max-width: 1024px)');

    function init() {
        var body = document.body;
        var sidebar = document.querySelector('.sidebar');
        var topbar = document.querySelector('.top-navbar');

        if (!sidebar || !topbar) return;

        /* Mobile menu button */
        var toggle = topbar.querySelector('.admin-menu-toggle');
        if (!toggle) {
            toggle = document.createElement('button');
            toggle.type = 'button';
            toggle.className = 'admin-menu-toggle';
            toggle.setAttribute('aria-label', 'Open navigation menu');
            toggle.setAttribute('aria-expanded', 'false');
            toggle.innerHTML = '<i class="fa-solid fa-bars" aria-hidden="true"></i>';
            topbar.insertBefore(toggle, topbar.firstChild);
        }

        /* Close button inside the drawer */
        var closeBtn = sidebar.querySelector('.admin-nav-close');
        if (!closeBtn) {
            closeBtn = document.createElement('button');
            closeBtn.type = 'button';
            closeBtn.className = 'admin-nav-close';
            closeBtn.setAttribute('aria-label', 'Close navigation menu');
            closeBtn.innerHTML = '&times;';
            sidebar.appendChild(closeBtn);
        }

        /* Backdrop */
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

        toggle.addEventListener('click', function () {
            if (!mobileQuery.matches) return;
            setOpen(!body.classList.contains('admin-nav-open'));
        });

        closeBtn.addEventListener('click', closeNav);
        overlay.addEventListener('click', closeNav);

        /* Existing decorative hamburger on Dashboard / Reservations */
        topbar.querySelectorAll('.header-title-section > .fa-bars').forEach(function (icon) {
            icon.setAttribute('role', 'button');
            icon.setAttribute('tabindex', '0');
            icon.setAttribute('aria-label', 'Open navigation menu');
            icon.addEventListener('click', function () {
                if (!mobileQuery.matches) return;
                setOpen(!body.classList.contains('admin-nav-open'));
            });
            icon.addEventListener('keydown', function (event) {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    icon.click();
                }
            });
        });

        /* Tapping a navigation link closes the drawer */
        sidebar.querySelectorAll('a').forEach(function (link) {
            link.addEventListener('click', closeNav);
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') closeNav();
        });

        function onViewportChange(event) {
            if (!event.matches) closeNav();
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
