// =====================================================
// CUSTOMER BOOKING STATUS PAGE
// Read-only: it only READS the stored reservation through the backend area of
// services.js (findReservation — Reservation ID + Booking PIN). It never
// changes a status — only the Admin page can confirm a reservation.
//
// After a successful ID + PIN check the credentials are kept in SESSION
// storage (this tab only) so the separate payment module can RE-VERIFY the
// reservation against the same trusted source. A status shown in a URL or a
// hidden field is never trusted.
// =====================================================
(function(){
  "use strict";

  // sessionStorage key holding {id, pin} of the reservation verified on THIS tab.
  const CTX_KEY = "pdr_verified_booking";
  // Prototype storage key of the BACKEND AREA in services.js — used only to
  // know WHEN to re-read the reservation (Admin may have changed the status).
  const RESERVATIONS_KEY = "pdr_reservations";

  // One entry per status. The page uses ONE template and fills it from here,
  // so adding a status later (e.g. "Cancelled") means adding one entry.
  const STATUS_UI = {
    Pending:   { cls:"status-pending",   label:"PENDING",   sub:"Awaiting Confirmation",
                 heading:"Your reservation is pending",
                 message:"Awaiting confirmation from Pio De Roda Cafe and Restaurant.",
                 full:"Pending — Awaiting Confirmation" },
    Confirmed: { cls:"status-confirmed", label:"CONFIRMED", sub:"Reservation Confirmed",
                 heading:"Reservation Confirmed!",
                 message:"Your reservation has been confirmed by Pio De Roda Cafe and Restaurant.",
                 full:"Confirmed" }
  };

  const root = document.getElementById("bookingStatusRoot");
  const verifyCard = document.getElementById("verifyCard");
  const esc = pdrEscape;

  /* ---------- verified-booking context (this tab only) ---------- */
  function saveCtx(id, pin){
    try{ sessionStorage.setItem(CTX_KEY, JSON.stringify({ id:id, pin:pin })); }catch(e){ /* private mode */ }
  }
  function readCtx(){
    try{ return JSON.parse(sessionStorage.getItem(CTX_KEY) || "null"); }catch(e){ return null; }
  }
  function clearCtx(){ try{ sessionStorage.removeItem(CTX_KEY); }catch(e){} }

  /* ---------- small formatting helpers (never print "undefined") ---------- */
  const has = v => v !== undefined && v !== null && String(v).trim() !== "";
  const val = v => has(v) ? esc(v) : "—";
  function fmtDay(v){
    const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(v || "");
    if(!m) return val(v);
    return new Date(+m[1], +m[2]-1, +m[3]).toLocaleDateString("en-US", {month:"long", day:"numeric", year:"numeric"});
  }
  function fmtSubmitted(v){
    if(/^\d{4}-\d{2}-\d{2}$/.test(v || "")) return fmtDay(v); // older records stored only a date
    const d = new Date(v);
    return isNaN(d) ? "—" : d.toLocaleDateString("en-US", {month:"long", day:"numeric", year:"numeric"});
  }
  // Event Time ("09:00" -> "9:00 AM"); records saved before the field existed show "—".
  function fmtTime(v){ return has(v) ? (esc(fmtEventTime(v)) || "—") : "—"; }
  const money = n => (n !== null && n !== "" && isFinite(Number(n))) ? "₱" + Number(n).toLocaleString() : "—";
  const row = (label, html) => `<div class="bs-row"><span>${label}</span><span>${html}</span></div>`;
  const optRow = (label, v) => has(v) ? row(label, esc(v)) : ""; // only show optional fields that were collected

  function servicesList(list){
    if(!Array.isArray(list) || !list.length) return "None";
    return `<ul class="bs-services-list">${list.map(s=>{
      const name = typeof s === "string" ? s : s && s.name;
      const qty  = s && s.quantity > 1 ? ` × ${esc(s.quantity)}` : "";
      return has(name) ? `<li>${esc(name)}${qty}</li>` : "";
    }).join("")}</ul>`;
  }

  /* ---------- verification errors ---------- */
  function setVerifyError(msg){
    const box = document.getElementById("verifyError");
    if(box) box.textContent = msg || "";
  }

  function reasonMessage(result){
    if(result && result.reason === "TOO_MANY_ATTEMPTS"){
      return `Too many failed attempts. Please wait ${result.retryAfterSeconds || 120} seconds before trying again.`;
    }
    if(result && result.reason === "NOT_FOUND"){
      // Wrong ID and wrong PIN look exactly the same, by design.
      return "No reservation found. Please check your Reservation ID and Booking PIN.";
    }
    return "Sorry, we could not check your booking right now. Please check your connection and try again.";
  }

  /* ---------- rendering ---------- */
  function showVerifyForm(message){
    verifyCard.style.display = "";
    root.innerHTML = "";
    setVerifyError(message || "");
  }

  function renderBookingStatus(reservation){
    if(!reservation || !has(reservation.reservationId)){ showVerifyForm(); return; }

    const ui = STATUS_UI[reservation.status] || {
      cls:"status-unknown", label: has(reservation.status) ? String(reservation.status).toUpperCase() : "UNKNOWN",
      sub:"", heading:"Booking Status", full: has(reservation.status) ? reservation.status : "Unknown",
      message:"Please contact Pio De Roda Cafe and Restaurant for the latest update on your reservation."
    };
    const confirmed = reservation.status === "Confirmed";

    root.innerHTML = `
      <div class="bs-grid">
        <article class="bs-card bs-status-card">
          <p class="bs-kicker">BOOKING STATUS</p>
          <h2>${esc(ui.heading)}</h2>
          <p class="bs-label">Reservation ID</p>
          <p class="bs-refnum">${esc(reservation.reservationId)}</p>
          <p class="bs-label">Status</p>
          <div class="status-badge ${ui.cls}"><span class="dot" aria-hidden="true"></span><span class="status-text">${esc(ui.label)}</span></div>
          ${ui.sub ? `<p class="bs-sub">${esc(ui.sub)}</p>` : ""}
          <p class="bs-message">${esc(ui.message)}</p>
          <button type="button" class="btn btn-primary" id="viewSummaryBtn">View Reservation Summary</button>
          ${confirmed
            ? `<a class="btn btn-primary bs-pay-btn" id="proceedToPaymentBtn" href="payment/payment.html?id=${encodeURIComponent(reservation.reservationId)}">Proceed to Payment</a>`
            : `<p class="bs-note" id="paymentNote">Payment becomes available once Pio De Roda staff confirm your reservation. Please check back later.</p>`}
          <p class="bs-note">This page shows the latest status saved for your reservation. Only Pio De Roda staff can confirm a reservation.</p>
          <button type="button" class="bs-clear" id="clearBtn">Clear</button>
        </article>

        <article class="bs-card bs-summary" id="reservationSummary" tabindex="-1">
          <p class="bs-kicker">RESERVATION SUMMARY</p>
          <p class="bs-label">Reservation ID</p>
          <p class="bs-refnum">${esc(reservation.reservationId)}</p>

          <h3 class="bs-section-title">CUSTOMER INFORMATION</h3>
          ${row("Customer Name", val(reservation.customerName))}
          ${row("Contact Person", val(reservation.contactPerson))}
          ${row("Contact Number", val(reservation.contactNumberMasked || reservation.contactNumber))}
          ${row("Email Address", val(reservation.emailMasked || reservation.email))}

          <h3 class="bs-section-title">EVENT INFORMATION</h3>
          ${row("Event Date", fmtDay(reservation.eventDate))}
          ${optRow("Event Time", reservation.eventTime && fmtEventTime(reservation.eventTime))}
          ${row("Event Type", val(reservation.eventType))}
          ${row("Number of Guests", has(reservation.guests) ? esc(reservation.guests) + " pax" : "—")}

          <h3 class="bs-section-title">SERVICES</h3>
          ${row("Main Service", val(reservation.mainService))}
          ${row("Food / Package", val(reservation.selectedPackage))}
          ${optRow("Main Courses", reservation.mainCourses && reservation.mainCourses.length ? reservation.mainCourses.join(", ") : "")}
          ${row("Additional Services", servicesList(reservation.additionalServices))}

          <h3 class="bs-section-title">RESERVATION INFORMATION</h3>
          ${row("Date Submitted", fmtSubmitted(reservation.submittedAt))}
          ${row("Booking Status", esc(ui.full))}

          <h3 class="bs-section-title">ESTIMATED TOTAL</h3>
          <div class="bs-total"><span>${money(reservation.estimatedTotal)}</span><small>Subject to confirmation</small></div>
        </article>
      </div>`;

    document.getElementById("viewSummaryBtn").onclick = ()=>{
      const box = document.getElementById("reservationSummary");
      box.scrollIntoView({behavior:"smooth", block:"start"});
      box.focus({preventScroll:true});
    };
    document.getElementById("clearBtn").onclick = ()=>{
      clearCtx();
      document.getElementById("resId").value = "";
      document.getElementById("resPin").value = "";
      showVerifyForm();
      verifyCard.scrollIntoView({behavior:"smooth", block:"center"});
    };
  }

  /* ---------- loading (always reads the LATEST stored data) ---------- */
  // Re-verifies with the saved ID + PIN every time, so an Admin confirmation
  // made in another tab shows up here — and a deleted reservation disappears.
  async function loadBookingStatus(){
    const ctx = readCtx();
    if(!ctx) return; // nothing verified yet — the verification form stays visible
    let result;
    try{ result = await findReservation(ctx.id, ctx.pin); }
    catch(e){ result = { ok:false, reason:"NETWORK" }; }
    if(result.ok){ verifyCard.style.display = "none"; renderBookingStatus(result.reservation); return; }
    if(result.reason === "TOO_MANY_ATTEMPTS"){ setVerifyError(reasonMessage(result)); return; } // keep the session, just warn
    clearCtx();
    showVerifyForm("Your saved booking could not be verified. Please enter your Reservation ID and Booking PIN again.");
  }

  function bindVerify(){
    document.getElementById("verifyForm").addEventListener("submit", async ev=>{
      ev.preventDefault();
      const id  = document.getElementById("resId").value.trim();
      const pin = document.getElementById("resPin").value.trim();
      setVerifyError("");
      if(!id){ setVerifyError("Please enter your Reservation ID."); return; }
      if(!pin){ setVerifyError("Please enter your 6-digit Booking PIN."); return; }

      const btn = document.getElementById("verifyBtn");
      btn.disabled = true; btn.textContent = "Checking…";
      let result;
      try{ result = await findReservation(id, pin); }
      catch(e){ result = { ok:false, reason:"NETWORK" }; }
      btn.disabled = false; btn.textContent = "Check Booking Status";

      if(!result.ok){ setVerifyError(reasonMessage(result)); return; }

      saveCtx(id, pin);
      verifyCard.style.display = "none";
      renderBookingStatus(result.reservation);
      root.scrollIntoView({behavior:"smooth", block:"start"});
    });

    document.getElementById("showPin").addEventListener("change", e=>{
      document.getElementById("resPin").type = e.target.checked ? "text" : "password";
    });
  }

  function bindNav(){
    const toggle = document.getElementById("navToggle"), links = document.getElementById("navLinks");
    toggle.addEventListener("click", ()=>{
      const open = links.classList.toggle("open");
      toggle.classList.toggle("open", open);
      toggle.setAttribute("aria-expanded", open);
    });
    const header = document.getElementById("site-header");
    const onScroll = ()=>header.classList.toggle("scrolled", window.scrollY > 10);
    window.addEventListener("scroll", onScroll); onScroll();
  }

  function init(){
    // Arriving from the success popup: ?id=PDR-2026-0001 (prefill only —
    // the reservation itself is still verified with ID + PIN below).
    const idParam = new URLSearchParams(location.search).get("id");
    if(idParam) document.getElementById("resId").value = idParam;

    bindNav();
    bindVerify();
    loadBookingStatus();

    // Pick up Admin changes: other tab saves -> "storage" event; returning to this tab/page -> reload.
    window.addEventListener("storage", ev=>{
      if(ev.key === null || ev.key === RESERVATIONS_KEY || ev.key === CTX_KEY) loadBookingStatus();
    });
    window.addEventListener("pageshow", loadBookingStatus);
    document.addEventListener("visibilitychange", ()=>{ if(!document.hidden) loadBookingStatus(); });
  }

  document.addEventListener("DOMContentLoaded", init);
})();
