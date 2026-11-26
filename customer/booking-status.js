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
              <label id="rescheduleDateLabel">New event date</label>
              <input type="hidden" id="rescheduleDate" value="">
              <div class="rs-selected-date" id="rescheduleDateDisplay" aria-labelledby="rescheduleDateLabel">Select an available date</div>
              <div class="cal rs-cal" id="rescheduleCalendar" role="group" aria-labelledby="rescheduleDateLabel"></div>
              <label for="rescheduleTime">New event time</label>
              <select id="rescheduleTime" required>
                <option value="">-- Select Time --</option>
              </select>
              <p class="bs-note rs-note">Unavailable dates and times are disabled.</p>
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

          <h3 class="bs-section-title">PAYMENT</h3>
          ${row("Payment Status", val(reservation.paymentStatus || "Unpaid"))}
          ${row("Amount Paid", money(reservation.amountPaid))}
          ${row("Balance", money(reservation.balance))}
          ${optRow("Payment Method", reservation.paymentMethod)}

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
      const dateDisplay = document.getElementById("rescheduleDateDisplay");
      const calBox = document.getElementById("rescheduleCalendar");
      const timeSelect = document.getElementById("rescheduleTime");
      const svcName = reservation.mainService || "";
      const now = new Date();
      const minDate = new Date(now.getFullYear(), now.getMonth(), now.getDate() + Number(reservation.rescheduleMinDays || 3));
      const maxDate = new Date(now.getFullYear(), now.getMonth(), now.getDate() + 365);
      const minDateStr = localDate(minDate);
      const maxDateStr = localDate(maxDate);
      let calYear = minDate.getFullYear();
      let calMonth = minDate.getMonth();
      let selectedDate = "";
      let preferredTime = "";

      function rsDaysInMonth(y, m){
        return window.PDRDates ? PDRDates.daysInMonth(y, m) : new Date(y, m + 1, 0).getDate();
      }
      function rsFmtDate(y, m, d){
        return window.PDRDates
          ? PDRDates.toISODate(new Date(y, m, d))
          : y + "-" + String(m + 1).padStart(2, "0") + "-" + String(d).padStart(2, "0");
      }
      function rsServiceStatus(ds, name){
        if(typeof availServiceStatus === "function"){
          const st = availServiceStatus(ds, name);
          if(st) return st;
        }
        if(!ds || !name || typeof AVAIL === "undefined" || !AVAIL.services) return null;
        const want = String(name).trim().toLowerCase();
        for(const id in AVAIL.services){
          const svc = AVAIL.services[id];
          if(svc && String(svc.name || "").trim().toLowerCase() === want && svc.dates){
            return svc.dates[ds] || null;
          }
        }
        return null;
      }
      function rsIsDateTaken(ds, name){
        const st = rsServiceStatus(ds, name);
        if(st) return !!st.taken;
        if(typeof isDateTaken === "function") return !!isDateTaken(ds, name);
        return false;
      }
      function rsBlockedReason(ds){
        if(ds < minDateStr || ds > maxDateStr) return "outside booking window";
        if(typeof bookingWindowError === "function"){
          const w = bookingWindowError(ds);
          if(w === "TOO_SOON") return "too soon to book";
          if(w === "TOO_FAR") return "too far ahead";
        }
        const meta = (typeof AVAIL !== "undefined" && AVAIL.meta) ? AVAIL.meta[ds] : null;
        if(meta && meta.blackout) return "closed by staff";
        if(rsIsDateTaken(ds, svcName)) return "fully booked for this service";
        // Fallback: if service lookup missed but the date is globally closed/unavailable
        if(typeof AVAIL !== "undefined" && Array.isArray(AVAIL.dates) && AVAIL.dates.indexOf(ds) !== -1){
          return "fully booked";
        }
        return "";
      }

      function renderRescheduleTimeOptions(){
        if(!timeSelect) return;
        const prev = timeSelect.value || preferredTime;
        const slots = typeof EVENT_TIME_SLOTS !== "undefined" ? EVENT_TIME_SLOTS : [];
        if(!selectedDate){
          timeSelect.innerHTML = `<option value="">Select a date first</option>`;
          timeSelect.disabled = true;
          return;
        }
        timeSelect.disabled = false;
        const svcStatus = rsServiceStatus(selectedDate, svcName);
        let list;
        if(svcStatus){
          const blocked = svcStatus.blockedTimes || [];
          list = slots.map(t => ({ time: t, taken: blocked.indexOf(t) !== -1 }));
        } else {
          const open = (typeof timeSlotState === "function") ? timeSlotState(selectedDate, svcName) : null;
          list = open || slots.map(t => ({ time: t, taken: false }));
        }
        const globallyClosed = typeof AVAIL !== "undefined" && Array.isArray(AVAIL.dates) && AVAIL.dates.indexOf(selectedDate) !== -1;
        if(!svcStatus && globallyClosed){
          list = slots.map(t => ({ time: t, taken: true }));
        }
        let html = `<option value="">-- Select Time --</option>`;
        html += list.map(s => {
          const t = s.time;
          const taken = !!s.taken;
          const label = (typeof fmtEventTime === "function" ? fmtEventTime(t) : t) + (taken ? " — unavailable" : "");
          const sel = prev === t && !taken ? " selected" : "";
          return `<option value="${esc(t)}"${sel}${taken ? " disabled" : ""}>${esc(label)}</option>`;
        }).join("");
        timeSelect.innerHTML = html;
        if(timeSelect.value !== prev && prev){
          timeSelect.value = "";
        }
      }

      function renderRescheduleCalendar(){
        if(!calBox) return;
        const y = calYear, m = calMonth;
        const first = new Date(y, m, 1).getDay();
        const total = rsDaysInMonth(y, m);
        const monthName = window.PDRDates
          ? PDRDates.monthName(y, m, false)
          : new Date(y, m, 1).toLocaleString("default", { month: "long" });
        const todayStr = typeof getTodayLocalDate === "function"
          ? getTodayLocalDate()
          : localDate(new Date());
        let cells = `<div class="dow">Su</div><div class="dow">Mo</div><div class="dow">Tu</div><div class="dow">We</div><div class="dow">Th</div><div class="dow">Fr</div><div class="dow">Sa</div>`;
        for(let i = 0; i < first; i++) cells += `<div class="day blank"></div>`;
        for(let d = 1; d <= total; d++){
          const ds = rsFmtDate(y, m, d);
          const isToday = ds === todayStr;
          if(ds < todayStr){
            cells += `<div class="day past" aria-disabled="true" aria-label="${esc(ds)} — past date">${d}</div>`;
            continue;
          }
          const reason = rsBlockedReason(ds);
          if(reason){
            const cls = reason.indexOf("fully booked") !== -1 ? "unavail" : "outside-window";
            cells += `<div class="day ${cls}" aria-disabled="true" aria-label="${esc(ds)} — unavailable, ${esc(reason)}">${d}</div>`;
            continue;
          }
          const sel = selectedDate === ds;
          cells += `<div class="day avail ${sel ? "sel" : ""} ${isToday ? "today" : ""}" data-date="${esc(ds)}" role="button" tabindex="0" aria-label="${esc(ds)} — available${sel ? ", selected" : ""}">${d}</div>`;
        }
        calBox.innerHTML = `
          <div class="cal-head">
            <button type="button" data-rs-nav="-1" aria-label="Previous month">‹</button>
            <span>${esc(monthName)} ${y}</span>
            <button type="button" data-rs-nav="1" aria-label="Next month">›</button>
          </div>
          <div class="cal-grid">${cells}</div>`;
      }

      async function refreshRescheduleCalendar(){
        if(typeof loadAvailability === "function"){
          try{ await loadAvailability(calYear, calMonth + 1); }catch(e){ /* keep last known */ }
        }
        if(selectedDate && rsBlockedReason(selectedDate)){
          selectedDate = "";
          dateInput.value = "";
          if(dateDisplay) dateDisplay.textContent = "Select an available date";
        }
        renderRescheduleCalendar();
        renderRescheduleTimeOptions();
      }

      function selectRescheduleDate(ds){
        if(!ds || rsBlockedReason(ds)) return;
        selectedDate = ds;
        dateInput.value = ds;
        if(dateDisplay){
          const formatted = window.PDRDates ? PDRDates.format(ds) : ds;
          dateDisplay.textContent = formatted || ds;
        }
        renderRescheduleCalendar();
        renderRescheduleTimeOptions();
      }

      if(calBox){
        calBox.addEventListener("click", async (e)=>{
          const nav = e.target.closest("button[data-rs-nav]");
          if(nav){
            const dir = parseInt(nav.getAttribute("data-rs-nav"), 10) || 0;
            calMonth += dir;
            if(calMonth > 11){ calMonth = 0; calYear++; }
            if(calMonth < 0){ calMonth = 11; calYear--; }
            await refreshRescheduleCalendar();
            return;
          }
          const day = e.target.closest(".day.avail[data-date]");
          if(day) selectRescheduleDate(day.getAttribute("data-date"));
        });
        calBox.addEventListener("keydown", (e)=>{
          if(e.key !== "Enter" && e.key !== " ") return;
          const day = e.target.closest(".day.avail[data-date]");
          if(!day) return;
          e.preventDefault();
          selectRescheduleDate(day.getAttribute("data-date"));
        });
      }

      openRescheduleBtn.onclick = async ()=>{
        rescheduleForm.hidden = false;
        selectedDate = "";
        preferredTime = String(reservation.eventTime || "").slice(0,5);
        dateInput.value = "";
        if(dateDisplay) dateDisplay.textContent = "Select an available date";
        calYear = minDate.getFullYear();
        calMonth = minDate.getMonth();
        await refreshRescheduleCalendar();
        calBox && calBox.scrollIntoView({behavior:"smooth", block:"nearest"});
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
          manageMessage.textContent = "Please choose an available new date.";
          return;
        }
        if(timeSelect && !timeSelect.value){
          manageMessage.textContent = "Please choose a new time.";
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
              newTime: timeSelect ? timeSelect.value : ""
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

  // Nav behavior is handled by nav.js (shared across customer pages).

  function init(){
    // Arriving from the success popup: ?id=PDR-2026-0001 (prefill only —
    // the reservation itself is still verified with ID + PIN below).
    const idParam = new URLSearchParams(location.search).get("id");
    if(idParam) document.getElementById("resId").value = idParam;

    bindVerify();
    loadBookingStatus();

    // Pick up Admin changes when the customer returns to this page.
    window.addEventListener("pageshow", loadBookingStatus);
    document.addEventListener("visibilitychange", ()=>{ if(!document.hidden) loadBookingStatus(); });
  }

  document.addEventListener("DOMContentLoaded", init);
})();
