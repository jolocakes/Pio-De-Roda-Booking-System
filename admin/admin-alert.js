/**
 * Shared admin alert/confirm popup.
 * Separate UI component (not browser alert/confirm, not top page-alert bar).
 * API:
 *   showPageAlert(message, type?, title?)
 *   pdrConfirm(message, opts?) -> Promise<boolean>
 *     opts: { title?, confirmText?, cancelText?, danger? }
 * Aliases: window.showAlert, window.alertBox
 */
(function () {
    'use strict';

    var confirmResolver = null;

    function resolveConfirm(value) {
        if (typeof confirmResolver === 'function') {
            var fn = confirmResolver;
            confirmResolver = null;
            fn(value);
        }
    }

    function closePdrAlert(fromConfirmCancel) {
        var modal = document.getElementById('pdrAlertModal');
        if (modal) {
            modal.classList.remove('is-open', 'is-success', 'is-error', 'is-info', 'is-confirm');
            modal.setAttribute('aria-hidden', 'true');
        }
        if (!fromConfirmCancel) resolveConfirm(false);
    }

    function setAlertMode() {
        var okBtn = document.getElementById('pdrAlertOkBtn');
        var cancelBtn = document.getElementById('pdrAlertCancelBtn');
        var confirmBtn = document.getElementById('pdrAlertConfirmBtn');
        if (okBtn) okBtn.style.display = '';
        if (cancelBtn) cancelBtn.style.display = 'none';
        if (confirmBtn) confirmBtn.style.display = 'none';
    }

    function setConfirmMode(opts) {
        var okBtn = document.getElementById('pdrAlertOkBtn');
        var cancelBtn = document.getElementById('pdrAlertCancelBtn');
        var confirmBtn = document.getElementById('pdrAlertConfirmBtn');
        if (okBtn) okBtn.style.display = 'none';
        if (cancelBtn) {
            cancelBtn.style.display = '';
            cancelBtn.textContent = (opts && opts.cancelText) || 'Cancel';
        }
        if (confirmBtn) {
            confirmBtn.style.display = '';
            confirmBtn.textContent = (opts && opts.confirmText) || 'Confirm';
            confirmBtn.className = 'pdr-alert-btn ' + ((opts && opts.danger) ? 'pdr-alert-btn-danger' : 'pdr-alert-btn-primary');
        }
    }

    function showPageAlert(message, type, title) {
        resolveConfirm(false);
        var modal = document.getElementById('pdrAlertModal');
        var msgEl = document.getElementById('pdrAlertMessage');
        var titleEl = document.getElementById('pdrAlertTitle');
        var iconEl = document.getElementById('pdrAlertIcon');
        if (!modal || !msgEl || !titleEl) {
            if (type === 'error') { console.error(message); }
            else { console.log(message); }
            return;
        }

        var isError = type === 'error';
        var isSuccess = type === 'success';
        var state = isError ? 'error' : isSuccess ? 'success' : 'info';

        setAlertMode();
        modal.classList.remove('is-success', 'is-error', 'is-info', 'is-confirm');
        modal.classList.add('is-open', 'is-' + state);
        modal.setAttribute('aria-hidden', 'false');

        titleEl.textContent = title || (isError ? 'Error' : isSuccess ? 'Success' : 'Notice');
        msgEl.textContent = message == null ? '' : String(message);

        if (iconEl) {
            var icon = isError ? 'fa-circle-xmark' : isSuccess ? 'fa-circle-check' : 'fa-circle-info';
            iconEl.innerHTML = '<i class="fa-solid ' + icon + '"></i>';
        }

        var okBtn = document.getElementById('pdrAlertOkBtn');
        if (okBtn) {
            okBtn.className = 'pdr-alert-btn ' + (isError ? 'pdr-alert-btn-danger' : 'pdr-alert-btn-primary');
            okBtn.textContent = 'OK';
            try { okBtn.focus(); } catch (e) {}
        }

        window.clearTimeout(showPageAlert._timer);
        if (isSuccess) {
            showPageAlert._timer = window.setTimeout(function () { closePdrAlert(true); }, 4000);
        }
    }

    function pdrConfirm(message, opts) {
        opts = opts || {};
        resolveConfirm(false);
        var modal = document.getElementById('pdrAlertModal');
        var msgEl = document.getElementById('pdrAlertMessage');
        var titleEl = document.getElementById('pdrAlertTitle');
        var iconEl = document.getElementById('pdrAlertIcon');
        if (!modal || !msgEl || !titleEl) {
            return Promise.resolve(window.confirm(String(message == null ? '' : message)));
        }

        setConfirmMode(opts);
        modal.classList.remove('is-success', 'is-error', 'is-info');
        modal.classList.add('is-open', 'is-confirm', 'is-info');
        modal.setAttribute('aria-hidden', 'false');
        titleEl.textContent = opts.title || 'Please Confirm';
        msgEl.textContent = message == null ? '' : String(message);
        if (iconEl) iconEl.innerHTML = '<i class="fa-solid fa-circle-question"></i>';

        window.clearTimeout(showPageAlert._timer);
        return new Promise(function (resolve) {
            confirmResolver = resolve;
            var confirmBtn = document.getElementById('pdrAlertConfirmBtn');
            if (confirmBtn) { try { confirmBtn.focus(); } catch (e) {} }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var closeX = document.getElementById('pdrAlertCloseX');
        var okBtn = document.getElementById('pdrAlertOkBtn');
        var cancelBtn = document.getElementById('pdrAlertCancelBtn');
        var confirmBtn = document.getElementById('pdrAlertConfirmBtn');
        var modal = document.getElementById('pdrAlertModal');

        if (closeX) closeX.addEventListener('click', function () { closePdrAlert(); });
        if (okBtn) okBtn.addEventListener('click', function () { closePdrAlert(true); });
        if (cancelBtn) cancelBtn.addEventListener('click', function () { closePdrAlert(); });
        if (confirmBtn) confirmBtn.addEventListener('click', function () { resolveConfirm(true); closePdrAlert(true); });
        if (modal) {
            modal.addEventListener('click', function (e) {
                if (e.target === modal) closePdrAlert();
            });
        }
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                var m = document.getElementById('pdrAlertModal');
                if (m && m.classList.contains('is-open')) closePdrAlert();
            }
        });
    });

    window.showPageAlert = showPageAlert;
    window.showAlert = showPageAlert;
    window.alertBox = showPageAlert;
    window.pdrConfirm = pdrConfirm;
})();
