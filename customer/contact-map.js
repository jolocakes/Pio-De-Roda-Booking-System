/* ==========================================================================
   CONTACT MAP TOGGLE
   Clicking the word "Address" in the Contact section shows a separate Google
   Maps block under the contact details (clicking it again hides the map).
   Kept in its own file because this directory's CSP allows no inline scripts.
   ========================================================================== */
(function () {
  "use strict";

  var toggle = document.getElementById("addressToggle");
  var panel = document.getElementById("contactMapPanel");
  if (!toggle || !panel) {
    return;
  }

  function setOpen(open) {
    panel.hidden = !open;
    toggle.setAttribute("aria-expanded", open ? "true" : "false");
  }

  toggle.addEventListener("click", function () {
    var open = toggle.getAttribute("aria-expanded") !== "true";
    setOpen(open);
    if (open) {
      panel.scrollIntoView({ behavior: "smooth", block: "nearest" });
    }
  });

  // role="button" does not get Enter/Space for free like a real <button> does
  toggle.addEventListener("keydown", function (event) {
    if (event.key === "Enter" || event.key === " ") {
      event.preventDefault();
      toggle.click();
    }
  });
})();
