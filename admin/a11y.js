/**
 * T-34: shared accessibility behaviour for admin dialogs.
 *
 * The admin pages open their modals by changing `display` or the `open` class
 * on a `.modal-overlay`. This script keeps that markup working while adding the
 * things keyboard and screen-reader users need:
 *
 *   - role="dialog" + aria-modal="true" (labelled by the dialog heading)
 *   - focus moves into the dialog when it opens
 *   - Tab / Shift+Tab are trapped inside the dialog
 *   - Escape closes the dialog
 *   - focus returns to the button that opened it
 *
 * It is deliberately generic so every current and future modal gets the same
 * behaviour without duplicating code in each page.
 */
(function () {
  "use strict";

  var FOCUSABLE = [
    "a[href]",
    "button:not([disabled])",
    "textarea:not([disabled])",
    'input:not([disabled]):not([type="hidden"])',
    "select:not([disabled])",
    '[tabindex]:not([tabindex="-1"])',
  ].join(",");

  var currentOverlay = null;
  var previousFocus = null;

  function isVisible(el) {
    if (!el) return false;
    var style = window.getComputedStyle(el);
    return (
      style.display !== "none" &&
      style.visibility !== "hidden" &&
      el.getClientRects().length > 0
    );
  }

  function focusable(overlay) {
    return Array.prototype.slice
      .call(overlay.querySelectorAll(FOCUSABLE))
      .filter(function (el) {
        return !el.hasAttribute("disabled") && el.getClientRects().length > 0;
      });
  }

  function labelOverlay(overlay) {
    if (!overlay.hasAttribute("role")) {
      overlay.setAttribute("role", "dialog");
    }
    overlay.setAttribute("aria-modal", "true");

    if (
      !overlay.hasAttribute("aria-labelledby") &&
      !overlay.hasAttribute("aria-label")
    ) {
      var heading = overlay.querySelector('h1, h2, h3, [role="heading"]');
      if (heading) {
        if (!heading.id) {
          heading.id =
            "pdr-a11y-title-" + Math.random().toString(36).slice(2, 8);
        }
        overlay.setAttribute("aria-labelledby", heading.id);
      }
    }
  }

  function openOverlay(overlay) {
    if (currentOverlay === overlay) return;
    if (!overlay.hasAttribute("tabindex")) {
      overlay.setAttribute("tabindex", "-1");
    }
    labelOverlay(overlay);
    previousFocus = document.activeElement;
    currentOverlay = overlay;

    var items = focusable(overlay);
    (items[0] || overlay).focus({ preventScroll: true });
  }

  function closeOverlay(overlay) {
    if (currentOverlay !== overlay) return;
    currentOverlay = null;
    if (
      previousFocus &&
      typeof previousFocus.focus === "function" &&
      document.contains(previousFocus)
    ) {
      previousFocus.focus({ preventScroll: true });
    }
    previousFocus = null;
  }

  function refresh(overlay) {
    if (isVisible(overlay)) {
      openOverlay(overlay);
    } else {
      closeOverlay(overlay);
    }
  }

  function watch(overlay) {
    labelOverlay(overlay);
    if (isVisible(overlay)) openOverlay(overlay);

    var observer = new MutationObserver(function () {
      refresh(overlay);
    });
    observer.observe(overlay, {
      attributes: true,
      attributeFilter: ["style", "class", "hidden"],
    });
  }

  document.addEventListener("keydown", function (event) {
    if (!currentOverlay) return;

    if (event.key === "Escape" || event.key === "Esc") {
      event.preventDefault();
      // Pages close modals by hiding the overlay; the observer restores focus.
      currentOverlay.style.display = "none";
      currentOverlay.classList.remove("open");
      return;
    }

    if (event.key !== "Tab") return;

    var items = focusable(currentOverlay);
    if (!items.length) {
      event.preventDefault();
      currentOverlay.focus();
      return;
    }

    var first = items[0];
    var last = items[items.length - 1];
    var active = document.activeElement;

    if (event.shiftKey && (active === first || active === currentOverlay)) {
      event.preventDefault();
      last.focus();
    } else if (!event.shiftKey && active === last) {
      event.preventDefault();
      first.focus();
    } else if (active === currentOverlay) {
      event.preventDefault();
      first.focus();
    }
  });

  function init() {
    Array.prototype.forEach.call(
      document.querySelectorAll(".modal-overlay"),
      watch,
    );
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
