(function () {
  "use strict";
  const CTX_KEY = "pdr_verified_booking";
  const $ = (id) => document.getElementById(id);
  const peso = (n) => "₱" + Number(n || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  function getCtx() { try { return JSON.parse(sessionStorage.getItem(CTX_KEY) || "null"); } catch (e) { return null; } }
  function qp(n) { return new URLSearchParams(location.search).get(n); }

  function bindNav() {
    const toggle = $("navToggle"), links = $("navLinks");
    if (!toggle || !links) return;
    toggle.addEventListener("click", () => { const open = links.classList.toggle("open"); toggle.classList.toggle("open", open); toggle.setAttribute("aria-expanded", open); });
  }

  async function init() {
    bindNav();
    const result = qp("result");
    const idParam = qp("id");
    const ctx = getCtx();
    const reservationId = (ctx && ctx.id) || idParam;
    const bookingPin = ctx && ctx.pin;
    if (reservationId) $("backLink").href = "../booking-status.html?id=" + encodeURIComponent(reservationId);

    if (!reservationId || !bookingPin) {
      $("title").textContent = result === "success" ? "Payment submitted" : "Payment not completed";
      $("msg").className = "notice" + (result === "success" ? " success" : " error");
      $("msg").innerHTML = "Please verify your booking to see the latest payment status. <a href='../booking-status.html?id=" + encodeURIComponent(reservationId||"") + "'>Booking Status</a>.";
      return;
    }

    // Re-check status from server (webhook is source of truth; may take a moment).
    try {
      const res = await fetch("../../api/payments/status.php", { method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify({ reservationId, bookingPin }) });
      const data = await res.json();
      if (!res.ok || !data.ok) throw new Error("status");
      const r = data.reservation || {};
      const paid = (r.balance || 0) <= 0 || (r.paymentStatus || "").toLower().indexOf("paid") !== -1;
      $("title").textContent = paid ? "Payment received" : (result === "success" ? "Payment processing" : "Payment not completed");
      $("msg").className = "notice " + (paid ? "success" : (result === "success" ? "" : "error"));
      $("msg").textContent = paid
        ? "Thank you! Your payment has been recorded."
        : (result === "success"
            ? "Your checkout returned successfully. If payment was completed, it may take a moment to confirm. Refresh or check Booking Status shortly."
            : "The payment was not completed. You can try again from the payment page while your reservation is confirmed.");
      $("summary").hidden = false;
      $("summary").innerHTML = "<div class='row'><span>Reservation</span><strong>" + (r.reservationId||"") + "</strong></div>" +
        "<div class='row'><span>Estimated total</span><strong>" + peso(r.estimatedTotal) + "</strong></div>" +
        "<div class='row'><span>Amount paid</span><strong>" + peso(r.amountPaid) + "</strong></div>" +
        "<div class='row'><span>Balance</span><strong>" + peso(r.balance) + "</strong></div>" +
        "<div class='row'><span>Payment status</span><strong>" + (r.paymentStatus||"—") + "</strong></div>";
    } catch (e) {
      $("title").textContent = "Payment status";
      $("msg").className = "notice";
      $("msg").textContent = "We couldn't confirm the latest status right now. Please check your Booking Status page.";
    }
  }
  document.addEventListener("DOMContentLoaded", init);
})();
