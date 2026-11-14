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
                 full:"Confirmed" },
    Cancelled:  { cls:"status-cancelled", label:"CANCELLED", sub:"Reservation Cancelled",
                 heading:"Your reservation was cancelled",
                 message:"This reservation has been cancelled. Please contact Pio De Roda Cafe and Restaurant if you believe this is a mistake.",
                 full:"Cancelled" },
    Rejected:   { cls:"status-cancelled", label:"NOT APPROVED", sub:"Reservation Not Approved",
                 heading:"We could not approve your reservation",
                 message:"Our staff reviewed this reservation and could not approve it. Please contact us to arrange another date.",
                 full:"Not Approved" },
    Completed:  { cls:"status-confirmed", label:"COMPLETED", sub:"Event Completed",
                 heading:"Thank you for visiting!",
                 message:"This event is complete. We would love to hear about your experience.",
                 full:"Completed" }
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
    if(!has(v)) return val(v);
    const formatted = window.PDRDates ? PDRDates.format(v) : "";
    return formatted || val(v);
  }
  function fmtSubmitted(v){
    if(!has(v)) return "—";
    const formatted = window.PDRDates ? PDRDates.format(v) : "";
    return formatted || "—";
  }
  // Event Time ("09:00" -> "9:00 AM"); records saved before the field existed show "—".
  function fmtTime(v){ return has(v) ? (esc(fmtEventTime(v)) || "—") : "—"; }
  // Accepts a raw number (preferred) or a value the server already formatted
  // as "₱1,234.00". Number("₱1,234.00") is NaN, which used to render "—".
  const money = n => {
    if (n === null || n === undefined || n === "") return "—";
    if (typeof n === "string" && n.indexOf("₱") !== -1) return n.trim();
    const v = Number(String(n).replace(/,/g, ""));
    return isFinite(v) ? "₱" + v.toLocaleString() : "—";
  };
  const row = (label, html) => `<div class="bs-row"><span>${label}</span><span>${html}</span></div>`;
  const optRow = (label, v) => has(v) ? row(label, esc(v)) : ""; // only show optional fields that were collected

  function servicesList(list){
    if(!Array.isArray(list) || !list.length) return "None";
    return `<ul class="bs-services-list">${list.map(s=>{
      const name = typeof s === "string" ? s : s && s.name;
      const qty  = s && s.quantity > 1 ? ` × ${esc(s.quantity)}` : "";
      // packageName/totalPrice come from the inline package picker — show the
      // exact chosen package and what it cost when the record has them.
      const pkg  = s && s.packageName ? ` <small>${esc(s.packageName)}</small>` : "";
      const amt  = s && s.totalPrice != null ? ` — ${money(s.totalPrice)}` : "";
      return has(name) ? `<li>${esc(name)}${pkg}${qty}${amt}</li>` : "";
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

          ${reservation.status === "Completed" ? `
          <div class="bs-review" id="reviewBox">
            <p class="bs-kicker">TELL US ABOUT YOUR EXPERIENCE</p>
            <form id="reviewForm" novalidate>
              <label for="reviewRating">Rating</label>
              <select id="reviewRating" required>
                <option value="5">★★★★★ &nbsp;Excellent</option>
                <option value="4">★★★★☆ &nbsp;Very good</option>
                <option value="3">★★★☆☆ &nbsp;Good</option>
                <option value="2">★★☆☆☆ &nbsp;Fair</option>
                <option value="1">★☆☆☆☆ &nbsp;Poor</option>
              </select>
              <label for="reviewFeedback">Your feedback</label>
              <textarea id="reviewFeedback" rows="4" maxlength="2000"
                placeholder="What did you enjoy? What could we improve?" required></textarea>
              <button type="submit" class="btn btn-primary" id="reviewSubmitBtn">Submit Review</button>
              <p class="bs-note" id="reviewMessage" role="status" aria-live="polite"></p>
            </form>
          </div>` : ""}
          ${(reservation.canCancel || reservation.canReschedule) ? `
          <div class="bs-manage" id="manageBooking">
            <p class="bs-kicker">MANAGE BOOKING</p>
            <p class="bs-note">
              Cancel at least ${esc(String(reservation.cancelMinDays))} days before the event, or
              reschedule at least ${esc(String(reservation.rescheduleMinDays))} days before
              (${esc(String(reservation.maxReschedules))} reschedule per booking).
            </p>
            <div class="bs-manage-actions">
              ${reservation.canReschedule ? `<button type="button" class="btn btn-primary" id="openRescheduleBtn">Reschedule</button>` : ""}
              ${reservation.canCancel ? `<button type="button" class="bs-clear" id="cancelBookingBtn">Cancel booking</button>` : ""}
            </div>
            ${reservation.canReschedule ? `
            <form id="rescheduleForm" hidden novalidate>
              <label for="rescheduleDate">New event date</label>
              <input type="date" id="rescheduleDate" required>
              <label for="rescheduleTime">New event time</label>
              <input type="time" id="rescheduleTime" required value="${esc(String(reservation.eventTime || "").slice(0,5))}">
              <div class="bs-manage-actions">
                <button type="submit" class="btn btn-primary" id="rescheduleSubmitBtn">Confirm new date</button>
                <button type="button" class="bs-clear" id="rescheduleCancelBtn">Keep current date</button>
              </div>
            </form>` : ""}
            <p class="bs-note" id="manageMessage" role="status" aria-live="polite"></p>
          </div>` : ""}

          <p class="bs-note">This page shows the latest status saved for your reservation. Only Pio De Roda staff can confirm a reservation.</p>
          <div class="bs-manage-actions">
            <button type="button" class="bs-clear" id="viewReceiptBtn">View / Print Receipt</button>
            <button type="button" class="bs-clear" id="clearBtn">Clear</button>
          </div>
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
          ${optRow("Address", reservation.address)}

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

    // T-38: printable receipt. The verified ID + PIN are re-sent to a POST-only
    // endpoint, so the receipt can never be opened by guessing a URL.
    const receiptBtn = document.getElementById("viewReceiptBtn");
    if(receiptBtn){
      receiptBtn.onclick = ()=>{
        const ctx = readCtx();
        if(!ctx) return;
        const form = document.createElement("form");
        form.method = "POST";
        form.action = "receipt.php";
        form.target = "_blank";
        form.style.display = "none";
        [["reservationId", ctx.id], ["bookingPin", ctx.pin]].forEach(([name, value])=>{
          const input = document.createElement("input");
          input.type = "hidden";
          input.name = name;
          input.value = value;
          form.appendChild(input);
        });
        document.body.appendChild(form);
        form.submit();
        form.remove();
      };
    }

    // T-33: review form (Completed bookings only). The ID + PIN already
    // verified for this page are sent along so the server can re-check them.
    const reviewForm = document.getElementById("reviewForm");
    if(reviewForm){
      reviewForm.onsubmit = async (ev)=>{
        ev.preventDefault();
        const msg = document.getElementById("reviewMessage");
        const btn = document.getElementById("reviewSubmitBtn");
        const rating = parseInt(document.getElementById("reviewRating").value, 10);
        const feedback = document.getElementById("reviewFeedback").value.trim();
        const ctx = readCtx();

        if(!ctx){ msg.textContent = "Please verify your booking again."; return; }
        if(!feedback){ msg.textContent = "Please write a short feedback message."; return; }

        btn.disabled = true;
        btn.textContent = "Submitting…";
        try{
          const res = await fetch(`${API_BASE_URL}/submit_review.php`, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({
              reservationId: ctx.id,
              bookingPin: ctx.pin,
              rating: rating,
              feedback: feedback
            })
          });
          const data = await res.json().catch(()=>({}));

          if(res.ok && data.ok){
            msg.textContent = "Thank you! Your review was submitted and will appear after staff review.";
            reviewForm.reset();
            btn.textContent = "Review submitted";
            return;
          }

          const errors = {
            ALREADY_SUBMITTED: "You have already submitted a review for this booking.",
            NOT_COMPLETED: "Reviews can be submitted once the event is completed.",
            TOO_MANY_ATTEMPTS: "Too many attempts. Please wait a few minutes and try again.",
            NOT_FOUND: "We could not verify this booking. Please check your details."
          };
          msg.textContent = errors[data.reason] || "Could not submit your review. Please try again.";
          btn.disabled = false;
          btn.textContent = "Submit Review";
        }catch(err){
          msg.textContent = "Network error. Please try again.";
          btn.disabled = false;
          btn.textContent = "Submit Review";
        }
      };
    }

    // T-37: self-service cancel / reschedule. The ID + PIN stored for this tab
    // are re-sent, and the server re-checks the policy windows and capacity.
    const manageMessage = document.getElementById("manageMessage");
    const cancelBtn = document.getElementById("cancelBookingBtn");
    const openRescheduleBtn = document.getElementById("openRescheduleBtn");
    const rescheduleForm = document.getElementById("rescheduleForm");
    const rescheduleCancelBtn = document.getElementById("rescheduleCancelBtn");

    function manageError(data){
      const errors = {
        TOO_LATE_TO_CANCEL: "It is too late to cancel online. Please contact Pio De Roda Cafe directly.",
        TOO_LATE_TO_RESCHEDULE: "It is too late to reschedule online. Please contact Pio De Roda Cafe directly.",
        RESCHEDULE_LIMIT: "This booking has already used its one allowed reschedule.",
        NO_CHANGE: "Please choose a different date or time.",
        DATE_TAKEN: "That date is no longer available. Please pick another date.",
        DATE_BLACKOUT: "Pio De Roda is closed on that date. Please pick another date.",
        TIME_TAKEN: "That time is already taken for this service. Please pick another time.",
        TOO_SOON: "That date is too soon. Please choose a later date.",
        TOO_FAR: "That date is too far ahead. Please choose a nearer date.",
        NOT_CHANGEABLE: "This booking can no longer be changed online.",
        TOO_MANY_ATTEMPTS: "Too many attempts. Please wait a few minutes and try again.",
        NOT_FOUND: "We could not verify this booking. Please verify your details again."
      };
      return errors[data.reason] || "Could not update the booking. Please try again.";
    }

    function localDate(d){
      return window.PDRDates
        ? PDRDates.toISODate(d)
        : d.getFullYear() + "-" + String(d.getMonth() + 1).padStart(2, "0") + "-" + String(d.getDate()).padStart(2, "0");
    }

    if(cancelBtn){
      cancelBtn.onclick = async ()=>{
        if(!confirm("Cancel this booking? This cannot be undone online.")) return;
        const ctx = readCtx();
        if(!ctx){ manageMessage.textContent = "Please verify your booking again."; return; }

        cancelBtn.disabled = true;
        cancelBtn.textContent = "Cancelling…";
        try{
          const res = await fetch(`${API_BASE_URL}/self_service.php`, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ reservationId: ctx.id, bookingPin: ctx.pin, action: "cancel" })
          });
          const data = await res.json().catch(()=>({}));
          if(res.ok && data.ok){
            manageMessage.textContent = "Your booking was cancelled. Refreshing…";
            setTimeout(loadBookingStatus, 900);
            return;
          }
          manageMessage.textContent = manageError(data);
        }catch(err){
          manageMessage.textContent = "Network error. Please try again.";
        }
        cancelBtn.disabled = false;
        cancelBtn.textContent = "Cancel booking";
      };
    }

    if(openRescheduleBtn && rescheduleForm){
      const dateInput = document.getElementById("rescheduleDate");
      const now = new Date();
      const minDate = new Date(now.getFullYear(), now.getMonth(), now.getDate() + Number(reservation.rescheduleMinDays || 3));
      const maxDate = new Date(now.getFullYear(), now.getMonth(), now.getDate() + 365);
      dateInput.min = localDate(minDate);
      dateInput.max = localDate(maxDate);

      openRescheduleBtn.onclick = ()=>{
        rescheduleForm.hidden = false;
        dateInput.value = dateInput.value || localDate(minDate);
        dateInput.focus();
      };
      if(rescheduleCancelBtn){
        rescheduleCancelBtn.onclick = ()=>{
          rescheduleForm.hidden = true;
          manageMessage.textContent = "";
        };
      }

      rescheduleForm.onsubmit = async (ev)=>{
        ev.preventDefault();
        const ctx = readCtx();
        if(!ctx){ manageMessage.textContent = "Please verify your booking again."; return; }
        if(!dateInput.value){
          manageMessage.textContent = "Please choose a new date.";
          return;
        }

        const submitBtn = document.getElementById("rescheduleSubmitBtn");
        submitBtn.disabled = true;
        submitBtn.textContent = "Saving…";
        try{
          const res = await fetch(`${API_BASE_URL}/self_service.php`, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({
              reservationId: ctx.id,
              bookingPin: ctx.pin,
              action: "reschedule",
              newDate: dateInput.value,
              newTime: document.getElementById("rescheduleTime").value
            })
          });
          const data = await res.json().catch(()=>({}));
          if(res.ok && data.ok){
            manageMessage.textContent = "Booking moved to " + data.eventDate + " at " + data.eventTime + ". Refreshing…";
            setTimeout(loadBookingStatus, 1100);
            return;
          }
          manageMessage.textContent = manageError(data);
        }catch(err){
          manageMessage.textContent = "Network error. Please try again.";
        }
        submitBtn.disabled = false;
        submitBtn.textContent = "Confirm new date";
      };
    }
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

    // Pick up Admin changes when the customer returns to this page.
    window.addEventListener("pageshow", loadBookingStatus);
    document.addEventListener("visibilitychange", ()=>{ if(!document.hidden) loadBookingStatus(); });
  }

  document.addEventListener("DOMContentLoaded", init);
})();
