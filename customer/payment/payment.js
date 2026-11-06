(function () {
  "use strict";
  const CTX_KEY = "pdr_verified_booking";
  const $ = (id) => document.getElementById(id);
  const peso = (n) => "₱" + Number(n || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

  function getCtx() {
    try { return JSON.parse(sessionStorage.getItem(CTX_KEY) || "null"); } catch (e) { return null; }
  }
  function getParam(name) { return new URLSearchParams(location.search).get(name); }
  function showError(msg) {
    $("stateLoading").hidden = true;
    $("stateError").hidden = false;
    $("stateError").textContent = msg;
  }

  async function postJson(url, body) {
    const res = await fetch(url, { method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify(body) });
    const data = await res.json().catch(() => ({}));
    return { res, data };
  }

  function bindNav() {
    const toggle = $("navToggle"), links = $("navLinks");
    if (!toggle || !links) return;
    toggle.addEventListener("click", () => {
      const open = links.classList.toggle("open");
      toggle.classList.toggle("open", open);
      toggle.setAttribute("aria-expanded", open);
    });
    const header = $("site-header");
    const onScroll = () => header && header.classList.toggle("scrolled", window.scrollY > 10);
    window.addEventListener("scroll", onScroll); onScroll();
  }

  async function init() {
    bindNav();
    const idParam = getParam("id");
    const ctx = getCtx();
    const reservationId = (ctx && ctx.id) || idParam;
    const bookingPin = ctx && ctx.pin;

    if (!reservationId || !bookingPin) {
      $("stateLoading").hidden = true;
      $("stateError").hidden = false;
      $("stateError").innerHTML = 'Please verify your booking first. <a href="../booking-status.html?id=' + encodeURIComponent(reservationId || "") + '">Go to Booking Status</a>.';
      return;
    }
    $("backLink").href = "../booking-status.html?id=" + encodeURIComponent(reservationId);

    const { res, data } = await postJson("../../api/payments/status.php", { reservationId, bookingPin });
    if (!res.ok || !data.ok) {
      if (data && data.reason === "NOT_FOUND") {
        sessionStorage.removeItem(CTX_KEY);
        showError("Booking could not be verified. Please check your Reservation ID and PIN on the Booking Status page.");
      } else {
        showError("Could not load payment details. Please try again.");
      }
      return;
    }

    const r = data.reservation || {};
    $("rId").textContent = r.reservationId || "—";
    $("rStatus").textContent = r.status || "—";
    $("rTotal").textContent = peso(r.estimatedTotal);
    $("rPaid").textContent = peso(r.amountPaid);
    $("rBalance").textContent = peso(r.balance);
    $("rPayStatus").textContent = r.paymentStatus || "—";

    $("stateLoading").hidden = true;
    $("payBody").hidden = false;

    if ((r.status || "") !== "Confirmed") {
      $("payDone").hidden = false;
      $("payDone").textContent = "Payment becomes available once Pio De Roda staff confirm your reservation. Current status: " + (r.status || "Unknown") + ".";
      return;
    }
    if ((r.balance || 0) <= 0) {
      $("payDone").hidden = false;
      $("payDone").textContent = "This booking is fully paid. Thank you!";
      return;
    }

    const depPct = Number(data.depositPercent || 50);
    const depAmt = Math.min(r.balance, Math.max(1, Math.round((r.estimatedTotal * depPct / 100) * 100) / 100));
    $("depPct").textContent = depPct;
    $("fullAmt").textContent = "(" + peso(r.balance) + ")";
    $("depAmt").textContent = "(" + peso(depAmt) + ")";
    if ((r.amountPaid || 0) > 0) { $("depositChoice").hidden = true; }

    $("payAction").hidden = false;
    $("payForm").addEventListener("submit", async (ev) => {
      ev.preventDefault();
      const paymentType = (new FormData($("payForm")).get("paymentType")) || "full";
      const btn = $("payBtn");
      btn.disabled = true; btn.textContent = "Creating checkout…";
      try {
        const out = await postJson("../../api/payments/create_invoice.php", { reservationId, bookingPin, paymentType });
        if (out.data && out.data.already_paid) {
          $("payDone").hidden = false; $("payDone").textContent = out.data.message || "Already paid."; $("payAction").hidden = true; return;
        }
        if (!out.res.ok || !out.data || !out.data.ok || !out.data.invoice_url) {
          const reason = (out.data && (out.data.detail || out.data.reason)) || "Could not start payment.";
          showError("Payment could not be started: " + reason);
          $("stateError").hidden = false;
        } else {
          window.location.href = out.data.invoice_url;
          return;
        }
      } catch (e) {
        showError("Network error. Please check your connection and try again.");
      }
      btn.disabled = false; btn.textContent = "Proceed to Xendit Checkout";
    });
  }

  document.addEventListener("DOMContentLoaded", init);
})();
