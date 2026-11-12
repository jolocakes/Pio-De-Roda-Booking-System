// Availability comes from AVAIL (services.js) — one shared object holding the
// current month's taken dates, per-date booking counts and the time slots each
// service already holds. It backs BOTH this booking calendar and the homepage
// calendar, so they can never disagree. Refreshed via refreshAvailability().

/* ---------------- FIELD VALIDATION HELPERS ---------------- */
// Contact number: digits only, exactly 11 (e.g. 09171234567).
const PHONE_RE = /^09\d{9}$/; /* to start at 09*/
function cleanPhone(raw) {
  let out = "";
  for (const ch of raw.replace(/\D/g, "")) {
    if (out.length === 0 && ch !== "0") continue;
    if (out.length === 1 && ch !== "9") continue;
    out += ch;
    if (out.length === 11) break;
  }
  return out;
}
// Email: something@domain.tld — works for gmail.com, yahoo.com, outlook.com, etc.
const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;
const isValidPhone = (v) => PHONE_RE.test(v);
const isValidEmail = (v) => EMAIL_RE.test(v.trim());
// Shows/hides an error message and outlines its input in red.
function showFieldError(errId, inputId, show) {
  const err = document.getElementById(errId);
  if (err) err.style.display = show ? "block" : "none";
  const inp = document.getElementById(inputId);
  if (inp) inp.classList.toggle("invalid", !!show);
}

/* ---------------- T-45: CLOUDFLARE TURNSTILE ---------------- */
// The public config endpoint exposes only the site key (never the secret).
// When the keys are blank — the default on a fresh clone — captchaEnabled is
// false and the booking flow behaves exactly as it did before.
const PDR_PUBLIC_CONFIG = {
  loaded: false,
  captchaEnabled: false,
  turnstileSiteKey: "",
};
let pdrTurnstileWidgetId = null;
let pdrCaptchaToken = "";

async function loadPublicConfig() {
  try {
    const res = await fetch("../api/public_config.php", { cache: "no-store" });
    const cfg = await res.json();
    if (cfg && cfg.ok) {
      PDR_PUBLIC_CONFIG.loaded = true;
      PDR_PUBLIC_CONFIG.captchaEnabled = !!cfg.captchaEnabled;
      PDR_PUBLIC_CONFIG.turnstileSiteKey = cfg.turnstileSiteKey || "";
    }
  } catch (e) {
    /* fail-open: never block a booking because the config call failed */
  }
  if (PDR_PUBLIC_CONFIG.captchaEnabled) loadTurnstileScript();
}

function loadTurnstileScript() {
  if (document.getElementById("pdrTurnstileScript")) return;
  const script = document.createElement("script");
  script.id = "pdrTurnstileScript";
  script.src = "https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit";
  script.async = true;
  script.defer = true;
  script.onload = () => {
    // The review modal may already be open while the script finishes loading.
    const modal = document.getElementById("reviewModal");
    if (modal && modal.classList.contains("open")) {
      ensureCaptchaWidget();
      checkChecks();
    }
  };
  document.head.appendChild(script);
}

function ensureCaptchaWidget() {
  const modal = document.querySelector("#reviewModal .modal");
  if (!modal || !PDR_PUBLIC_CONFIG.captchaEnabled) return;

  let host = document.getElementById("pdrCaptcha");
  if (!host) {
    host = document.createElement("div");
    host.id = "pdrCaptcha";
    host.className = "pdr-captcha";
    host.style.margin = "12px 0";
    const firstCheckbox = modal.querySelector(".chk");
    modal.insertBefore(host, firstCheckbox || null);
  }

  if (window.turnstile && pdrTurnstileWidgetId === null) {
    pdrCaptchaToken = "";
    pdrTurnstileWidgetId = window.turnstile.render(host, {
      sitekey: PDR_PUBLIC_CONFIG.turnstileSiteKey,
      action: "reservation",
      theme: "auto",
      callback: (token) => {
        pdrCaptchaToken = token || "";
        checkChecks();
      },
      "expired-callback": () => {
        pdrCaptchaToken = "";
        checkChecks();
      },
      "error-callback": () => {
        pdrCaptchaToken = "";
        checkChecks();
      },
    });
  } else if (window.turnstile && pdrTurnstileWidgetId !== null) {
    pdrCaptchaToken = "";
    window.turnstile.reset(pdrTurnstileWidgetId);
  }
}

function resetCaptcha() {
  pdrCaptchaToken = "";
  if (window.turnstile && pdrTurnstileWidgetId !== null) {
    window.turnstile.reset(pdrTurnstileWidgetId);
  }
}

const pdrConfigReady = loadPublicConfig();

/* ---------------- STATE ---------------- */
// The calendar opens on the CURRENT month (calculated in Asia/Manila), never a
// hard-coded one, so past months are not the default view.
const _todayDS = getTodayLocalDate();
let state = {
  service: null,
  catalogReady: false,
  customerName: "",
  contactNumber: "",
  email: "",
  address: "", // delivery/setup address — collected only when the service requires it
  eventDate: null,
  eventTime: "",
  eventType: "",
  eventTypeOther: "",
  guests: "",
  selectedPackage: null,
  mainCourses: [], // Catering with Menu: chosen main courses (minimum 2)
  cart: {}, // id -> {qty, item, pkgName, unitPrice}  (ADDITIONAL services only — never the main service)
  picking: null, // add-on id whose inline package picker is currently open
  pickingPkg: 0, // selected package row inside that picker
  notice: "", // one-time message shown in the Additional Services section
  guidelinesOpen: {}, // serviceId -> true/false  (each service expands/collapses INDEPENDENTLY)
  calYear: parseInt(_todayDS.slice(0, 4), 10),
  calMonth: parseInt(_todayDS.slice(5, 7), 10) - 1, // 0-indexed
};

/* ---------------- RENDER: SERVICE DETAILS ---------------- */
function renderDetails() {
  const s = state.service;
  const el = document.getElementById("serviceDetails");
  if (!el || !s) return;
  const ratesRows = Array.isArray(s.rates)
    ? s.rates.map((r) => `<tr><td>${r[0]}</td><td>${r[1]}</td></tr>`).join("")
    : "";
  const hasMenu = Array.isArray(s.menu) && s.menu.length > 0;
  const hasMainCourses =
    Array.isArray(s.mainCourses) && s.mainCourses.length > 0;
  const startingPriceText =
    s.price !== null && s.price !== undefined && String(s.price).trim() !== ""
      ? s.price
      : "Price to be confirmed";
  const inclusionsList = Array.isArray(s.inclusions) ? s.inclusions : [];
  el.innerHTML = `
    <h1>${s.name}</h1>
    <div class="venue-img">${SERVICE_IMAGES[s.id] ? `<img src="${SERVICE_IMAGES[s.id]}" alt="${s.name}" loading="lazy" decoding="async">` : "[Pio De Roda Venue Image - placeholder]"}</div>
    <div class="section-label">RATES</div>
    <table class="rates">${ratesRows}</table>
    ${hasMenu ? renderMenuTable(s) : ""}
    ${hasMainCourses ? renderMainCourses(s) : ""}
    <div class="section-label">STARTING PRICE</div>
    <div class="price-hero"><div class="amt">${startingPriceText}</div></div>
    <div class="section-label">INCLUSIONS</div>
    ${s.inclusionsTable ? renderInclusionsTable(s.inclusionsTable) : `<ul class="plain check">${inclusionsList.map((i) => `<li>${i}</li>`).join("")}</ul>`}
    <div class="section-label">GUIDELINES</div>
    ${renderGuidelines(s)}
  `;
  attachDetailsEvents();
}

/* ---------------- GUIDELINES: "See More" / "See Less" ---------------- */
// How many guideline items stay visible before the "See More" button.
const GUIDELINE_PREVIEW_ITEMS = 3;

/* Reusable renderer for EVERY service's Guidelines section (this file is
   shared by all 9 service pages, so one implementation covers them all).
   - The original guideline text is rendered ONCE and never duplicated.
   - Only the first GUIDELINE_PREVIEW_ITEMS items show initially; everything
     after that lives in a collapsible .gl-rest block.
   - Each service keeps its OWN expanded/collapsed state
     (state.guidelinesOpen[serviceId]), so opening one service's guidelines
     never expands another's. */
function renderGuidelines(s) {
  // Existing content, unchanged and in its original order:
  // service-specific notes first, then the shared guideline groups.
  const blocks = [];
  if (s.notes && s.notes.length)
    blocks.push({ heading: `Notes Specific to ${s.name}`, items: s.notes });
  guidelines.forEach((g) =>
    blocks.push({ heading: g.heading, items: g.items }),
  );

  const totalItems = blocks.reduce((n, b) => n + b.items.length, 0);
  const canToggle = totalItems > GUIDELINE_PREVIEW_ITEMS;
  const open = canToggle && !!state.guidelinesOpen[s.id];
  const restId = `glRest-${s.id}`; // unique per service

  let out = "",
    shown = 0,
    restOpened = false,
    inList = false;
  const closeList = () => {
    if (inList) {
      out += "</ul>";
      inList = false;
    }
  };
  const openRest = () => {
    if (canToggle && !restOpened) {
      closeList(); // finish the preview list
      out += `<div class="gl-rest" id="${restId}"${open ? "" : " hidden"}>`;
      restOpened = true;
    }
  };
  const openList = () => {
    if (!inList) {
      out += `<ul class="plain dot">`;
      inList = true;
    }
  };

  blocks.forEach((block) => {
    closeList();
    if (shown >= GUIDELINE_PREVIEW_ITEMS) openRest(); // this heading belongs to the expanded part
    out += `<h3 class="guideline-heading">${block.heading}</h3>`;
    openList();
    block.items.forEach((item) => {
      if (shown >= GUIDELINE_PREVIEW_ITEMS) {
        // preview cut-off reached
        openRest();
        openList(); // continue the list inside .gl-rest
      }
      out += `<li>${item}</li>`;
      shown++;
    });
  });
  closeList();
  if (restOpened) out += "</div>";

  if (canToggle) {
    out += `<button type="button" class="gl-toggle" id="glToggle"
              aria-expanded="${open}" aria-controls="${restId}"
              aria-label="${open ? "See Less" : "See More"} guidelines for ${s.name}">${open ? "See Less" : "See More"}</button>`;
  }
  return out;
}

/* Generic inclusions table renderer — pass any service's `inclusionsTable`
   ({headers:[...], rows:[[...]]}) to reuse this for other services. */
function renderInclusionsTable(t) {
  return `<div class="table-scroll"><table class="rates inclusions-table">
    <thead><tr>${t.headers.map((h) => `<th>${h}</th>`).join("")}</tr></thead>
    <tbody>${t.rows.map((row) => `<tr>${row.map((cell) => `<td>${cell}</td>`).join("")}</tr>`).join("")}</tbody>
  </table></div>`;
}

/* Dine-In + Catering: renders each service's `menu` as tables. When a menu
   item matches an entry in `packages` (Dine-In's set menus), it gets a Select
   button centered on the right and stays in sync with the booking panel.
   Catering's fixed menu has no matching package, so it renders display-only. */
function renderMenuTable(s) {
  const anySelectable = s.menu.some((cat) =>
    cat.items.some((item) => s.packages.some((p) => p[0] === item.name)),
  );
  return `
    <div class="section-label">${anySelectable ? "MENU — SELECT YOUR SET MENU" : "MENU — INCLUDED IN EVERY PACKAGE"}</div>
    ${s.menu
      .map((cat) =>
        cat.items
          .map((item) => {
            const idx = s.packages.findIndex((p) => p[0] === item.name);
            const selectable = idx >= 0;
            const sel = selectable && state.selectedPackage === idx;
            const dishes =
              item.dishes && item.dishes.length
                ? item.dishes
                : ["To be confirmed by staff"];
            return `
      <h4 class="guideline-heading">${item.name}</h4>
      <table class="rates menu-table">
        ${dishes
          .map(
            (dish, i) => `
          <tr class="menu-row ${sel ? "sel" : ""}">
            <td>${dish}</td>
            ${selectable && i === 0 ? `<td class="menu-pick" rowspan="${dishes.length}"><button type="button" class="menu-select ${sel ? "sel" : ""}" data-idx="${idx}">${sel ? "Selected ✓" : "Select"}</button></td>` : ""}
          </tr>`,
          )
          .join("")}
      </table>
    `;
          })
          .join(""),
      )
      .join("")}
  `;
}

/* Catering with Menu: main-course picker (minimum 2 selections). Each click
   re-matches the package in the booking panel — the per-head rate follows the
   number of choices (2 → ₱1,000, 3 → ₱1,100, 4 → ₱1,300 per head). */
function renderMainCourses(s) {
  const n = state.mainCourses.length;
  const rateNote = s.packages
    .map((p, i) => `${i + 2} = ₱${(p[1] || 0).toLocaleString()}`)
    .join(" · ");
  return `
    <div class="mc-section">
      <div class="section-label">MAIN COURSE — SELECT AT LEAST 2</div>
      <div class="mc-list">
        ${s.mainCourses
          .map((name) => {
            const on = state.mainCourses.includes(name);
            return `<button type="button" class="mc-opt ${on ? "sel" : ""}" data-name="${name}" aria-pressed="${on}">${on ? "✓ " : ""}${name}</button>`;
          })
          .join("")}
      </div>
      <div class="cap">${n} of ${s.mainCourses.length} selected — per-head rate: ${rateNote}</div>
      <div class="err" id="errMain" style="display:none">Please select at least 2 main courses.</div>
    </div>
  `;
}

/* The package follows the main-course count: 2 → "2 Main Courses", 3 → "3
   Main Courses", 4 → "4 Main Courses"; fewer than 2 clears it until the
   minimum is met. */
function syncMainCoursePackage() {
  const n = state.mainCourses.length;
  state.selectedPackage =
    n >= 2 && n - 2 < state.service.packages.length ? n - 2 : null;
}

function toggleMainCourse(name) {
  const i = state.mainCourses.indexOf(name);
  if (i >= 0) state.mainCourses.splice(i, 1);
  else state.mainCourses.push(name);
  syncMainCoursePackage();
  renderDetails(); // refresh the ✓ marks and count caption
  renderPanel(); // refresh the auto-selected package + summary
}

function attachDetailsEvents() {
  // Catering: toggling a main course re-matches the package + total.
  document
    .querySelectorAll(".mc-opt")
    .forEach((b) => (b.onclick = () => toggleMainCourse(b.dataset.name)));

  document.querySelectorAll(".menu-select").forEach(
    (btn) =>
      (btn.onclick = () => {
        state.selectedPackage = parseInt(btn.dataset.idx);
        renderDetails();
        renderPanel();
      }),
  );

  // Guidelines "See More" / "See Less" — toggles only THIS service's block and
  // remembers the state so re-renders (e.g. selecting a package) keep it.
  const glBtn = document.getElementById("glToggle");
  if (glBtn) {
    glBtn.onclick = () => {
      const rest = document.getElementById(glBtn.getAttribute("aria-controls"));
      if (!rest) return;
      const nowOpen = rest.hidden; // collapsed -> expand
      rest.hidden = !nowOpen;
      state.guidelinesOpen[state.service.id] = nowOpen;
      glBtn.textContent = nowOpen ? "See Less" : "See More";
      glBtn.setAttribute("aria-expanded", String(nowOpen));
      glBtn.setAttribute(
        "aria-label",
        (nowOpen ? "See Less" : "See More") +
          " guidelines for " +
          state.service.name,
      );
    };
  }
}

/* ---------------- RENDER: CALENDAR ---------------- */
function daysInMonth(y, m) {
  return new Date(y, m + 1, 0).getDate();
}
function fmtDate(y, m, d) {
  return `${y}-${String(m + 1).padStart(2, "0")}-${String(d).padStart(2, "0")}`;
}
function renderCalendar() {
  const y = state.calYear,
    m = state.calMonth;
  const first = new Date(y, m, 1).getDay();
  const total = daysInMonth(y, m);
  const monthName = new Date(y, m, 1).toLocaleString("default", {
    month: "long",
  });
  const today = getTodayLocalDate(); // Asia/Manila — recalculated on every render
  let cells = `<div class="dow">Su</div><div class="dow">Mo</div><div class="dow">Tu</div><div class="dow">We</div><div class="dow">Th</div><div class="dow">Fr</div><div class="dow">Sa</div>`;
  for (let i = 0; i < first; i++) cells += `<div class="day blank"></div>`;
  for (let d = 1; d <= total; d++) {
    const ds = fmtDate(y, m, d);
    const isToday = ds === today;
    if (ds < today) {
      // PAST DATE — never selectable. No data-date and no .avail class, so both
      // the CSS and the click handler (which only binds .day.avail) skip it.
      cells += `<div class="day past" aria-disabled="true" aria-label="${ds} — past date">${d}</div>`;
      continue;
    }
    // T-40: outside the booking window — same visual treatment as a past date.
    const windowErr = bookingWindowError(ds);
    if (windowErr) {
      const why = windowErr === "TOO_SOON"
        ? `requires at least ${PDR_MIN_LEAD_DAYS} days' notice`
        : `more than ${PDR_MAX_HORIZON_DAYS} days ahead`;
      cells += `<div class="day past" aria-disabled="true" aria-label="${ds} — ${why}">${d}</div>`;
      continue;
    }
    const unavail = isDateTaken(ds);
    const sel = state.eventDate === ds;
    const meta = AVAIL.meta[ds];
    const hint = unavail
      ? meta && meta.blackout
        ? "closed by staff"
        : meta && meta.hasVenue
          ? "exclusive service booked"
          : "fully booked"
      : meta && meta.count > 0
        ? `${meta.count} of ${(AVAIL.rules && AVAIL.rules.maxPerDay) || 2} slots booked`
        : "";
    cells += `<div class="day ${unavail ? "unavail" : "avail"} ${sel ? "sel" : ""} ${isToday ? "today" : ""}"
      data-date="${unavail ? "" : ds}" ${unavail ? 'aria-disabled="true" ' : ""}role="button" tabindex="0"
      ${hint ? `title="${hint}" ` : ""}aria-label="${ds} — ${unavail ? "unavailable" : isToday ? "today, available" : "available"}${hint ? ", " + hint : ""}${sel ? ", selected" : ""}">${d}</div>`;
  }
  return `
    <div class="cal-head"><button type="button" data-nav="-1" aria-label="Previous month">‹</button><span>${monthName} ${y}</span><button type="button" data-nav="1" aria-label="Next month">›</button></div>
    <div class="cal-grid">${cells}</div>
  `;
}

/* The service the customer is currently booking — the per-service time rule
   needs it. Falls back to whatever mainService the form last held. */
function currentServiceName() {
  if (state.service && state.service.name) return state.service.name;
  return state.mainService || "";
}

/* Options for the Event Time select. Slots already held by THIS service on
   the chosen date are rendered disabled, so the picker itself is the first
   line of defence — submit_reservation.php re-checks the same rule. */
function timeOptionsHTML() {
  const svc = currentServiceName();
  const open = state.eventDate ? timeSlotState(state.eventDate, svc) : null;
  let html = `<option value="">-- Select Time --</option>`;
  html += EVENT_TIME_SLOTS.map((t) => {
    const taken = open ? open.some((s) => s.time === t && s.taken) : false;
    const sel = state.eventTime === t;
    const label = fmtEventTime(t) + (taken ? " — unavailable" : "");
    return `<option value="${t}" ${sel ? "selected" : ""} ${taken ? "disabled" : ""}>${label}</option>`;
  }).join("");
  return html;
}

/* Re-render ONLY the time select (keeps the date calendar open state intact) */
function refreshTimeOptions() {
  const sel = document.getElementById("fTime");
  if (!sel) return;
  sel.innerHTML = timeOptionsHTML();
}

/* ---------------- RENDER: BOOKING PANEL ---------------- */
function renderPanel() {
  const s = state.service;
  const el = document.getElementById("bookingPanel");
  if (!el || !s) return;
  el.innerHTML = `
    <h2>Book Your Event</h2>
    <p class="req-legend"><span class="req">*</span> Required field</p>
    <div class="field">
      <label>Customer Name / Contact Person <span class="req" title="Required">*</span></label>
      <input type="text" id="fName" placeholder="Enter your full name" value="${state.customerName}">
      <div class="err" id="errName">Please enter your name.</div>
    </div>
    <div class="field">
      <label>Contact Number <span class="req" title="Required">*</span></label>
      <input type="tel" id="fPhone" inputmode="numeric" maxlength="11" autocomplete="tel" placeholder="09XXXXXXXXX" value="${state.contactNumber}">
      <div class="cap">Please enter your 11 digits phone number</div>
      <div class="err" id="errPhone">Please enter an 11-digit contact number</div>
    </div>
    <div class="field">
      <label>Email Address <span class="req" title="Required">*</span></label>
      <input type="email" id="fEmail" autocomplete="email" placeholder="you@gmail.com" value="${state.email}">
      <div class="err" id="errEmail">Please enter a valid email address, including @ and the domain (e.g. name@gmail.com)</div>
    </div>
    ${
      s.requiresAddress
        ? `
    <div class="field">
      <label>Address <span class="req" title="Required">*</span></label>
      <input type="text" id="fAddress" autocomplete="street-address" placeholder="Enter Address" value="${state.address}">
      <div class="cap">Complete address where the service will be set up or delivered (street, barangay, municipality)</div>
      <div class="err" id="errAddress">Please enter the address for setup or delivery.</div>
    </div>`
        : ""
    }
    <div class="field">
      <label>Event Date <span class="req" title="Required">*</span></label>
      <input type="text" id="fDate" class="date-input" readonly placeholder="Select a date" value="${state.eventDate || ""}">
      <div class="cal" id="calBox">${renderCalendar()}</div>
      <div class="err" id="errDate">Please select an event date.</div>
    </div>
    <div class="field">
      <label>Event Time <span class="req" title="Required">*</span></label>
      <select id="fTime">
        ${timeOptionsHTML()}
      </select>
      <div class="cap">${
        state.eventDate
          ? "Unavailable times are already booked by another customer for your service."
          : "Pick an event date first — unavailable times appear once a date is chosen."
      }</div>
      <div class="err" id="errTime">Please select an event time.</div>
    </div>
    <div class="field">
      <label>Event Type <span class="req" title="Required">*</span></label>
      <select id="fType">
        <option value="">-- Select --</option>
        ${[
          "Birthday",
          "Wedding",
          "Reunion",
          "Christmas Party",
          "Corporate Event",
          "Family Gathering",
          "Private Celebration",
          "Other",
        ]
          .map(
            (t) =>
              `<option value="${t}" ${state.eventType === t ? "selected" : ""}>${t}</option>`,
          )
          .join("")}
      </select>
      ${state.eventType === "Other" ? `<input type="text" id="fTypeOther" placeholder="Please specify" value="${state.eventTypeOther}" style="margin-top:8px;">` : ""}
      <div class="err" id="errType">Please select an event type</div>
    </div>
    <div class="field">
      <label>Number of Guests / Pax <span class="req" title="Required">*</span></label>
      <input type="number" id="fGuests" min="1" placeholder="Enter number of guests" value="${state.guests}">
      ${s.maxCap ? `<div class="cap">Please indicate the exact number of guests carefully</div>` : ""}
      <div class="err" id="errGuests">Please enter a valid number of guests</div>
    </div>
    <div class="field">
      <label>Selected Food / Package <span class="req" title="Required">*</span></label>
      ${s.packages
        .map(
          (p, i) => `
        <div class="pkg-opt ${state.selectedPackage === i ? "sel" : ""}" data-idx="${i}">
          <span class="nm">${p[0]}</span><span>${pkgPerHeadRate(s, p) > 0 ? "₱" + pkgPerHeadRate(s, p).toLocaleString() + " per head" : p[1] === null || p[1] === undefined ? "" : Number.isFinite(Number(p[1])) && Number(p[1]) > 0 ? "₱" + Number(p[1]).toLocaleString() : "Price to be confirmed"}</span>
        </div>`,
        )
        .join("")}
        <div class="cap">${s.mainCourses ? "Package is set automatically from your Main Course selection (minimum 2)." : "Please select the package according to the number of guests"}</div>
      <div class="err" id="errPkg">Please select a food/package</div>
    </div>
    <div class="field">
      <label>Additional Services</label>
      <div id="additionalServicesList"></div>
    </div>
    <div id="summaryBox"></div>
    <button class="btn-main" id="continueBtn">Continue Reservation</button>
  `;
  attachPanelEvents();
  renderAdditionalServices(state.service.id);
  renderSummary();
}

/* ---------------- ADDITIONAL SERVICES (dynamic) ---------------- */
/* Line pricing for one cart entry. Per-head add-ons (Dine-In, Catering)
   multiply their rate by the guest count; quantity add-ons (Equipment)
   multiply by the chosen quantity; everything else is a flat price. */
function addonLineUnit(c) {
  return c &&
    c.unitPrice !== null &&
    c.unitPrice !== undefined &&
    Number.isFinite(Number(c.unitPrice))
    ? Number(c.unitPrice)
    : null;
}
function addonLineTotal(c) {
  const unit = addonLineUnit(c);
  if (unit === null) return null;
  const per = c.item.perHead || c.item.perHeadByPkg;
  const base = per ? unit * (parseInt(state.guests) || 0) : unit;
  return base * (c.item.qty ? c.qty : 1);
}

/* Shared row markup for the summary box AND the review modal: shows the
   chosen package, the computed amount, and how a per-head amount derives. */
function addonCartRows(kind) {
  const guests = parseInt(state.guests) || 0;
  return Object.values(state.cart)
    .map((c) => {
      const per = c.item.perHead || c.item.perHeadByPkg;
      const unit = addonLineUnit(c);
      const lineTotal = addonLineTotal(c);
      const note = c.pkgName
        ? ` <small class="line-note">${c.pkgName}${
            per && guests > 0 && unit !== null
              ? ` — ${guests} pax × ₱${unit.toLocaleString()} per head`
              : ""
          }</small>`
        : "";
      const label = c.item.name + (c.item.qty ? " × " + c.qty : "") + note;
      const amountLabel =
        lineTotal !== null
          ? `₱${lineTotal.toLocaleString()}`
          : "Price to be confirmed";
      return `<div class="${kind}"><span>${label}</span><span>${amountLabel}</span></div>`;
    })
    .join("");
}

/* Adds (or updates) a cart entry from the picker's selected package row. */
function addAddonToCart(item, pkgIdx) {
  if (!item || !state.service || item.serviceId === state.service.id) return;
  const opts = addonOptions(item);
  const pkg = opts[Math.max(0, Math.min(pkgIdx || 0, opts.length - 1))] || [
    item.name,
    item.price,
  ];
  const unitPrice = addonOptionRate(item, pkg);
  if (unitPrice === null || !Number.isFinite(unitPrice) || unitPrice <= 0) {
    state.picking = null;
    state.notice = `${item.name} (${pkg[0]}) does not have a confirmed price yet and cannot be added to the reservation total.`;
    renderAdditionalServices(state.service.id);
    return;
  }
  const prev = state.cart[item.id];
  state.cart[item.id] = {
    qty: prev && item.qty ? prev.qty : 1,
    item,
    pkgName: pkg[0],
    unitPrice,
  };
  state.picking = null;
  renderAdditionalServices(state.service.id);
  renderSummary();
}

// Renders every add-on EXCEPT the main service currently being booked.
// Pass `animate=true` when the main service just changed (subtle fade-in).
// Add-ons with several packages open an INLINE PICKER (state.picking), so
// the customer always chooses the exact package — never a hidden "cheapest".
function renderAdditionalServices(currentServiceId, animate = false) {
  const box = document.getElementById("additionalServicesList");
  if (!box) return;
  const list = getAdditionalServices(currentServiceId);

  const notice = state.notice ? `<p class="note">${state.notice}</p>` : "";
  state.notice = "";

  if (!list.length) {
    box.innerHTML =
      notice +
      `<p class="note">No additional services are available for this selection.</p>`;
    return;
  }

  const guests = parseInt(state.guests) || 0;

  box.innerHTML =
    notice +
    list
      .map((a) => {
        const inCart = state.cart[a.id];
        const opts = addonOptions(a);
        const rates = opts
          .map((o) => addonOptionRate(a, o))
          .filter((r) => r !== null && Number.isFinite(r) && r > 0);
        const min = rates.length ? Math.min(...rates) : null;
        const perHeadish = a.perHead || a.perHeadByPkg;
        const anim = animate ? " fade-in" : "";

        // ---- inline package picker open for this add-on ----
        if (state.picking === a.id) {
          const rows = opts
            .map((o, i) => {
              const rate = addonOptionRate(a, o);
              const sel = i === state.pickingPkg ? " sel" : "";
              const amt =
                rate === null || !Number.isFinite(rate) || rate <= 0
                  ? "Price to be confirmed"
                  : perHeadish
                    ? `₱${rate.toLocaleString()} / head` +
                      (guests > 0
                        ? ` · ₱${(rate * guests).toLocaleString()} (${guests} pax)`
                        : "")
                    : `₱${rate.toLocaleString()}`;
              return `<div class="addon-pkg${sel}" data-idx="${i}" role="radio" aria-checked="${sel ? "true" : "false"}" tabindex="0">
                <span class="nm">${o[0]}</span><span class="amt">${amt}</span></div>`;
            })
            .join("");
          return `<div class="addon picking${anim}">
            <div class="pick-head"><div class="nm">${a.name}</div><div class="desc">Choose a package to add:</div></div>
            <div class="pick-rows">${rows}</div>
            <div class="pick-actions">
              <button type="button" class="add" data-confirm="${a.id}">Add to Booking</button>
              <button type="button" class="cancel" data-cancel="1">Cancel</button>
            </div>
          </div>`;
        }

        // ---- already in the cart: chosen package + computed amount ----
        if (inCart) {
          const unit = addonLineUnit(inCart);
          const lineTotal = addonLineTotal(inCart);
          const breakdown = perHeadish
            ? guests > 0 && unit !== null
              ? `${guests} pax × ₱${unit.toLocaleString()} per head`
              : "per guest"
            : a.qty && unit !== null
              ? `₱${unit.toLocaleString()} per item`
              : "";
          const totalDisplay =
            lineTotal !== null
              ? `₱${lineTotal.toLocaleString()}`
              : "Price to be confirmed";
          return `<div class="addon in-cart${anim}">
            <div style="flex:1">
              <div class="nm">✓ ${a.name}</div>
              <div class="price">${inCart.pkgName || ""} — ${totalDisplay}</div>
              ${breakdown ? `<div class="desc">${breakdown}</div>` : ""}
            </div>
            ${
              a.qty
                ? `<div class="qty"><button type="button" data-qty="-1" data-id="${a.id}">-</button><span>${inCart.qty}</span><button type="button" data-qty="1" data-id="${a.id}">+</button></div>`
                : ""
            }
            <div class="cart-actions">
              ${
                a.qty
                  ? ""
                  : `<button type="button" class="mini" data-change="${a.id}">Change</button>`
              }
              <button type="button" class="mini danger" data-remove="${a.id}" aria-label="Remove ${a.name}">✕</button>
            </div>
          </div>`;
        }

        // ---- default (not yet added) card ----
        let priceLabel;
        if (min === null) priceLabel = "Price to be confirmed";
        else if (a.perHeadByPkg)
          priceLabel = `From ₱${min.toLocaleString()} per head${
            opts.length > 1 ? " · " + opts.length + " packages" : ""
          }`;
        else if (a.perHead)
          priceLabel = `₱${min.toLocaleString()} per head${
            opts.length > 1 ? " · choose menu" : ""
          }`;
        else if (a.qty) priceLabel = `₱${min.toLocaleString()} per item`;
        else
          priceLabel =
            opts.length > 1
              ? `From ₱${min.toLocaleString()} · ${opts.length} packages`
              : `₱${min.toLocaleString()}`;

        return `<div class="addon${anim}">
          <div style="flex:1"><div class="nm">${a.name}</div><div class="desc">${a.desc}</div><div class="price">${priceLabel}</div></div>
          <button type="button" class="add" data-add="${a.id}">${opts.length > 1 ? "Choose" : "+ Add"}</button>
        </div>`;
      })
      .join("");

  /* --- bindings --- */
  box.querySelectorAll("[data-add]").forEach(
    (btn) =>
      (btn.onclick = () => {
        const item = addonCatalog.find((a) => a.id === btn.dataset.add);
        if (!item || item.serviceId === state.service.id) return;
        if (addonOptions(item).length > 1) {
          state.picking = item.id;
          state.pickingPkg = 0;
          renderAdditionalServices(state.service.id);
        } else {
          addAddonToCart(item, 0); // single-option add-ons add instantly
        }
      }),
  );

  box.querySelectorAll(".addon-pkg").forEach((row) => {
    const pick = () => {
      state.pickingPkg = parseInt(row.dataset.idx) || 0;
      renderAdditionalServices(state.service.id);
    };
    row.onclick = pick;
    row.onkeydown = (e) => {
      if (e.key === "Enter" || e.key === " ") {
        e.preventDefault();
        pick();
      }
    };
  });

  box.querySelectorAll("[data-confirm]").forEach(
    (btn) =>
      (btn.onclick = () => {
        const item = addonCatalog.find((a) => a.id === btn.dataset.confirm);
        addAddonToCart(item, state.pickingPkg);
      }),
  );

  box.querySelectorAll("[data-cancel]").forEach(
    (btn) =>
      (btn.onclick = () => {
        state.picking = null;
        renderAdditionalServices(state.service.id);
      }),
  );

  box.querySelectorAll("[data-change]").forEach(
    (btn) =>
      (btn.onclick = () => {
        const id = btn.dataset.change;
        const item = addonCatalog.find((a) => a.id === id);
        const c = state.cart[id];
        if (!item || !c) return;
        const opts = addonOptions(item);
        const at = opts.findIndex((o) => o[0] === c.pkgName);
        state.picking = id;
        state.pickingPkg = at >= 0 ? at : 0;
        renderAdditionalServices(state.service.id);
      }),
  );

  box.querySelectorAll("[data-remove]").forEach(
    (btn) =>
      (btn.onclick = () => {
        delete state.cart[btn.dataset.remove];
        renderAdditionalServices(state.service.id);
        renderSummary();
      }),
  );

  box.querySelectorAll(".qty button").forEach(
    (btn) =>
      (btn.onclick = (ev) => {
        ev.stopPropagation();
        const c = state.cart[btn.dataset.id];
        if (!c) return;
        c.qty = Math.max(1, c.qty + parseInt(btn.dataset.qty));
        renderAdditionalServices(state.service.id);
        renderSummary();
      }),
  );
}

// Removes any cart entry that duplicates the main service. Returns the
// names removed (empty array = nothing was duplicated).
function removeMainServiceFromCart() {
  const removed = [];
  Object.keys(state.cart).forEach((id) => {
    if (state.cart[id].item.serviceId === state.service.id) {
      removed.push(state.cart[id].item.name);
      delete state.cart[id];
    }
  });
  return removed;
}

/* PER-HEAD PRICING — some services charge per guest instead of a flat price:
   - Dine-In: one fixed rate on the service (`perHead`: ₱600 per guest)
   - Catering with Menu: the rate depends on the chosen package
     (`perHeadByPkg`: 2 courses ₱1,000 / 3 ₱1,100 / 4 ₱1,300 per guest)
   Every other service keeps its flat package price (this returns 0). */
function pkgPerHeadRate(s, pkg) {
  if (!s) return 0;
  if (s.perHeadByPkg) {
    return pkg &&
      pkg[1] !== null &&
      pkg[1] !== undefined &&
      Number.isFinite(Number(pkg[1])) &&
      Number(pkg[1]) > 0
      ? Number(pkg[1])
      : 0;
  }
  if (
    s.perHead !== null &&
    s.perHead !== undefined &&
    Number.isFinite(Number(s.perHead)) &&
    Number(s.perHead) > 0
  ) {
    return Number(s.perHead);
  }
  return 0;
}

function isPackagePriceResolved(s, pkg) {
  if (!s || !pkg) return false;
  if (pkgPerHeadRate(s, pkg) > 0) return true;
  if (s.perHeadByPkg || (s.perHead !== null && s.perHead !== undefined)) {
    return false;
  }
  return (
    pkg[1] !== null &&
    pkg[1] !== undefined &&
    Number.isFinite(Number(pkg[1])) &&
    Number(pkg[1]) > 0
  );
}

function hasUnresolvedPricing() {
  if (!state.service || state.selectedPackage === null) return true;
  const pkg = state.service.packages[state.selectedPackage];
  if (!isPackagePriceResolved(state.service, pkg)) return true;
  for (const c of Object.values(state.cart)) {
    const unit = addonLineUnit(c);
    const lineTotal = addonLineTotal(c);
    if (
      unit === null ||
      unit <= 0 ||
      lineTotal === null ||
      !Number.isFinite(lineTotal)
    ) {
      return true;
    }
  }
  return false;
}

/* The selected package's contribution to the estimated total:
   per-head services multiply their rate by the guest count; all other
   services just add their flat package price. Never treats NULL as 0 when checking resolved status. */
function pkgTotal(s, pkg, guests) {
  if (!isPackagePriceResolved(s, pkg)) return 0;
  const per = pkgPerHeadRate(s, pkg);
  if (per > 0) return per * (parseInt(guests) || 0);
  return Number(pkg[1]);
}

/* How the selected package is shown in the summary and review:
   per-head services show the computed amount (rate × pax); other services
   keep their flat package price, or "Price to be confirmed" if NULL. */
function pkgLabel(s, pkg, guests) {
  if (!pkg) return "—";
  const per = pkgPerHeadRate(s, pkg);
  if (per > 0) {
    const n = parseInt(guests) || 0;
    return n > 0
      ? pkg[0] +
          " — ₱" +
          (per * n).toLocaleString() +
          " (" +
          n +
          " pax × ₱" +
          per.toLocaleString() +
          " per head)"
      : pkg[0] + " — ₱" + per.toLocaleString() + " per head";
  }
  if (pkg[1] === null || pkg[1] === undefined) {
    return pkg[0] + " — Price to be confirmed";
  }
  return pkg[0] + " — ₱" + Number(pkg[1]).toLocaleString();
}

function computeTotal() {
  let total = 0;
  if (state.service && state.selectedPackage !== null) {
    total += pkgTotal(
      state.service,
      state.service.packages[state.selectedPackage],
      state.guests,
    );
  }
  Object.values(state.cart).forEach((c) => {
    const lineTotal = addonLineTotal(c);
    if (lineTotal !== null && Number.isFinite(lineTotal)) {
      total += lineTotal;
    }
  });
  return total;
}

function renderSummary() {
  const box = document.getElementById("summaryBox");
  if (!box || !state.service) return;
  const pkg =
    state.selectedPackage !== null
      ? state.service.packages[state.selectedPackage]
      : null;
  const cartRows = addonCartRows("row");
  const totalDisplay =
    state.selectedPackage !== null && hasUnresolvedPricing()
      ? "Price to be confirmed"
      : `₱${computeTotal().toLocaleString()}`;
  box.innerHTML = `
    <div class="summary">
      <div class="row"><span>Customer</span><span>${state.customerName || "—"}</span></div>
      <div class="row"><span>Event Date</span><span>${state.eventDate || "—"}</span></div>
      <div class="row"><span>Event Time</span><span>${state.eventTime ? fmtEventTime(state.eventTime) : "—"}</span></div>
      <div class="row"><span>Event Type</span><span>${state.eventType === "Other" ? state.eventTypeOther || "Other" : state.eventType || "—"}</span></div>
      <div class="row"><span>Guests</span><span>${state.guests || "—"}</span></div>
      <div class="row"><span>Main Service</span><span>${state.service.name}</span></div>
      <div class="row"><span>Food Package</span><span>${pkgLabel(state.service, pkg, state.guests)}</span></div>
      ${state.service.mainCourses && state.mainCourses.length ? `<div class="row"><span>Main Courses</span><span>${state.mainCourses.join(", ")}</span></div>` : ""}
      ${cartRows ? `<div class="row"><span><strong>Additional Services</strong></span><span></span></div>${cartRows}` : ""}
      <div class="row total"><span>Estimated Total</span><span>${totalDisplay}</span></div>
    </div>`;
}

/* ---------------- EVENTS ---------------- */
function attachPanelEvents() {
  document.getElementById("fName").oninput = (e) => {
    state.customerName = e.target.value;
    renderSummary();
  };
  // Phone: strip anything that isn't a digit (also handles pasted text), max 11.
  document.getElementById("fPhone").oninput = (e) => {
    const clean = cleanPhone(e.target.value);
    if (e.target.value !== clean) e.target.value = clean;
    state.contactNumber = clean;
    if (isValidPhone(clean)) showFieldError("errPhone", "fPhone", false);
  };
  document.getElementById("fPhone").onblur = (e) =>
    showFieldError("errPhone", "fPhone", !isValidPhone(e.target.value));
  document.getElementById("fEmail").oninput = (e) => {
    state.email = e.target.value.trim();
    if (isValidEmail(state.email)) showFieldError("errEmail", "fEmail", false);
  };
  document.getElementById("fEmail").onblur = (e) =>
    showFieldError("errEmail", "fEmail", !isValidEmail(e.target.value));

  // Address exists only on the services that require it (booths, catering,
  // equipment rental) — guard so pages without the field never throw.
  const addrInput = document.getElementById("fAddress");
  if (addrInput) {
    addrInput.oninput = (e) => {
      state.address = e.target.value;
      if (state.address.trim()) showFieldError("errAddress", "fAddress", false);
    };
    addrInput.onblur = (e) =>
      showFieldError("errAddress", "fAddress", !e.target.value.trim());
  }

  const dateInput = document.getElementById("fDate");
  dateInput.onclick = () =>
    document.getElementById("calBox").classList.toggle("open");
  bindCalendar();

  document.getElementById("fTime").onchange = (e) => {
    state.eventTime = e.target.value;
    if (state.eventTime) showFieldError("errTime", "fTime", false);
    renderSummary();
  };

  document.getElementById("fType").onchange = (e) => {
    state.eventType = e.target.value;
    renderPanel();
  };
  const other = document.getElementById("fTypeOther");
  if (other)
    other.oninput = (e) => {
      state.eventTypeOther = e.target.value;
      renderSummary();
    };

  document.getElementById("fGuests").oninput = (e) => {
    let v = e.target.value;
    const err = document.getElementById("errGuests");
    if (v !== "" && (isNaN(v) || parseInt(v) < 1)) {
      err.style.display = "block";
    } else err.style.display = "none";
    state.guests = v;
    renderSummary();
    // keep an open picker's live totals and per-head cart rows in sync
    if (state.picking || Object.keys(state.cart).length)
      renderAdditionalServices(state.service.id);
  };

  document.querySelectorAll(".pkg-opt").forEach(
    (p) =>
      (p.onclick = () => {
        if (state.service.mainCourses) {
          syncMainCoursePackage(); // catering: package follows the main-course count
        } else {
          state.selectedPackage = parseInt(p.dataset.idx);
        }
        renderPanel();
        if (state.service.menu) renderDetails(); // keep the menu table's highlight in sync
      }),
  );

  document.getElementById("continueBtn").onclick = handleContinue;
}

function bindCalendar() {
  const calBox = document.getElementById("calBox");
  calBox.querySelectorAll("[data-nav]").forEach(
    (b) =>
      (b.onclick = async () => {
        let m = state.calMonth + parseInt(b.dataset.nav);
        let y = state.calYear;
        if (m < 0) {
          m = 11;
          y--;
        }
        if (m > 11) {
          m = 0;
          y++;
        }
        state.calMonth = m;
        state.calYear = y;
        // Each month has its own availability — fetch it BEFORE redrawing so
        // the new month never paints with the previous month's data.
        await loadAvailability(y, m + 1);
        calBox.innerHTML = renderCalendar();
        calBox.classList.add("open");
        bindCalendar();
      }),
  );
  // Only .day.avail cells are bound. Past dates (.day.past) and unavailable
  // dates (.day.unavail) carry no data-date and are never wired up here, so
  // they cannot be selected even with JavaScript enabled.
  calBox.querySelectorAll(".day.avail").forEach((d) => {
    const pick = () => {
      state.eventDate = d.dataset.date;
      document.getElementById("fDate").value = state.eventDate;
      document.getElementById("errDate").style.display = "none";
      // The date changed — refresh the time picker and drop a time that the
      // new date no longer offers for this service (the customer is told).
      refreshTimeOptions();
      revalidateEventTime(); // re-check the chosen time against the new date
      calBox.classList.remove("open");
      renderSummary();
      calBox.innerHTML = renderCalendar();
      bindCalendar();
    };
    d.onclick = pick;
    d.onkeydown = (e) => {
      if (e.key === "Enter" || e.key === " ") {
        e.preventDefault();
        pick();
      }
    };
  });
}

/* Re-checks the chosen Event Time after the date changes (or right before
   submit). If the time is no longer offered we NOTIFY the customer and clear
   the field — the selection is never silently changed. */
function revalidateEventTime(notify = true) {
  if (!state.eventTime) return true;
  if (isTimeAvailable(state.eventDate, state.eventTime, currentServiceName()))
    return true;
  state.eventTime = "";
  const sel = document.getElementById("fTime");
  if (sel) sel.value = "";
  showFieldError("errTime", "fTime", true);
  if (notify)
    alert(
      "Your previously selected event time is not available for the new date. Please choose another event time.",
    );
  renderSummary();
  return false;
}

/* ---------------- VALIDATION + CONTINUE ---------------- */
function handleContinue() {
  if (!state.catalogReady || !state.service) return;
  let ok = true;
  const setErr = (id, show) => {
    const el = document.getElementById(id);
    if (el) el.style.display = show ? "block" : "none";
    if (show) ok = false;
  };
  setErr("errName", !state.customerName.trim());

  // EVENT DATE — required, must not be in the past, must still be available.
  // The calendar already blocks those days; this is the SECOND check that runs
  // at submission time (a date can become past/taken after it was selected).
  let dateMsg = "";
  const windowErr = state.eventDate ? bookingWindowError(state.eventDate) : null;
  if (!state.eventDate) dateMsg = "Please select an event date.";
  else if (windowErr === "TOO_SOON")
    dateMsg = `Bookings require at least ${PDR_MIN_LEAD_DAYS} days' notice. Please choose a later date.`;
  else if (windowErr === "TOO_FAR")
    dateMsg = `Bookings open at most ${PDR_MAX_HORIZON_DAYS} days ahead. Please choose an earlier date.`;
  else if (windowErr === "INVALID")
    dateMsg = "Please select a valid event date.";
  else if (isDateTaken(state.eventDate))
    dateMsg =
      AVAIL.meta[state.eventDate] && AVAIL.meta[state.eventDate].blackout
        ? "That date is closed by our staff. Please choose another date."
        : AVAIL.meta[state.eventDate] && AVAIL.meta[state.eventDate].hasVenue
          ? "The venue is already booked for that date. Please choose another date."
          : "That date is fully booked. Please choose another date.";
  if (dateMsg) document.getElementById("errDate").textContent = dateMsg;
  setErr("errDate", !!dateMsg);

  // EVENT TIME — required and still offered for the chosen date.
  let timeMsg = "";
  if (!state.eventTime) timeMsg = "Please select an event time.";
  else if (
    !isTimeAvailable(state.eventDate, state.eventTime, currentServiceName())
  )
    timeMsg =
      "That time is already booked for your service on this date. Please choose another event time.";
  if (timeMsg) document.getElementById("errTime").textContent = timeMsg;
  setErr("errTime", !!timeMsg);

  setErr("errType", !state.eventType);
  setErr(
    "errGuests",
    !state.guests || isNaN(state.guests) || parseInt(state.guests) < 1,
  );
  if (state.service.mainCourses) {
    // Catering: the package follows the main-course count, so the actionable
    // rule is "at least 2 main courses" (which also fills the package).
    setErr("errMain", state.mainCourses.length < 2);
    document.getElementById("errPkg").style.display = "none";
  } else {
    setErr("errPkg", state.selectedPackage === null);
  }
  const phoneBad = !isValidPhone(state.contactNumber);
  const emailBad = !isValidEmail(state.email);
  showFieldError("errPhone", "fPhone", phoneBad);
  showFieldError("errEmail", "fEmail", emailBad);
  // Address is required ONLY on the services that ask for it.
  const addrBad = !!state.service.requiresAddress && !state.address.trim();
  showFieldError("errAddress", "fAddress", addrBad);
  if (phoneBad || emailBad || addrBad) ok = false;
  if (!ok) {
    const firstErr = document.querySelector(".err[style*='block'], .invalid");
    if (firstErr)
      firstErr.scrollIntoView({ behavior: "smooth", block: "center" });
    return;
  }
  // Block submission if any selected package or add-on has an unresolved/NULL price.
  if (hasUnresolvedPricing()) {
    alert(
      "The selected package or additional service has an unconfirmed price and cannot be priced safely right now.",
    );
    return;
  }
  // Final duplicate check: main service must never also be an additional service.
  const removed = removeMainServiceFromCart();
  if (removed.length) {
    renderAdditionalServices(state.service.id);
    renderSummary();
    alert(
      `${removed.join(", ")} is your main service, so it was removed from Additional Services.`,
    );
  }
  openReviewModal();
}

function openReviewModal() {
  const s = state.service;
  if (!s || state.selectedPackage === null) return;
  const pkg = s.packages[state.selectedPackage];
  const cartRows =
    addonCartRows("confirm-row") ||
    `<div class="confirm-row"><span>None selected</span><span>—</span></div>`;
  document.getElementById("reviewBody").innerHTML = `
    <div class="confirm-row"><span>Customer</span><span>${state.customerName}</span></div>
    <div class="confirm-row"><span>Contact Number</span><span>${state.contactNumber}</span></div>
    <div class="confirm-row"><span>Email</span><span>${state.email}</span></div>
    ${state.service.requiresAddress ? `<div class="confirm-row"><span>Address</span><span>${pdrEscape(state.address)}</span></div>` : ""}
    <div class="confirm-row"><span>Event Date</span><span>${state.eventDate}</span></div>
    <div class="confirm-row"><span>Event Time</span><span>${fmtEventTime(state.eventTime)}</span></div>
    <div class="confirm-row"><span>Event Type</span><span>${state.eventType === "Other" ? state.eventTypeOther : state.eventType}</span></div>
    <div class="confirm-row"><span>Guests</span><span>${state.guests} pax</span></div>
    <div class="confirm-row"><span><strong>Main Service</strong></span><span>${s.name}</span></div>
    <div class="confirm-row"><span>Package</span><span>${pkgLabel(s, pkg, state.guests)}</span></div>
    ${state.service.mainCourses ? `<div class="confirm-row"><span>Main Courses</span><span>${state.mainCourses.join(", ") || "—"}</span></div>` : ""}
    <div class="confirm-row"><span><strong>Additional Services</strong></span><span></span></div>
    ${cartRows}
    <div class="confirm-row"><span>Package Subtotal</span><span>₱${pkgTotal(s, pkg, state.guests).toLocaleString()}</span></div>
    <div class="confirm-row"><span>Additional Services Subtotal</span><span>₱${(computeTotal() - pkgTotal(s, pkg, state.guests)).toLocaleString()}</span></div>
    <div class="confirm-row" style="font-weight:bold;color:var(--maroon);"><span>Estimated Total</span><span>₱${computeTotal().toLocaleString()}</span></div>
    <p class="note">This is an estimated total; final pricing is subject to confirmation from Pio De Roda Cafe and Restaurant.</p>
  `;
  document.getElementById("chkCorrect").checked = false;
  document.getElementById("chkGuidelines").checked = false;
  document.getElementById("submitBtn").disabled = true;
  document.getElementById("reviewModal").classList.add("open");
  // T-45: the widget is injected during the async config load; re-check once
  // the config and script are ready.
  pdrConfigReady.then(() => {
    ensureCaptchaWidget();
    checkChecks();
  });
}

function checkChecks() {
  const c1 = document.getElementById("chkCorrect").checked;
  const c2 = document.getElementById("chkGuidelines").checked;
  const captchaOk = !PDR_PUBLIC_CONFIG.captchaEnabled || pdrCaptchaToken !== "";
  document.getElementById("submitBtn").disabled = !(
    c1 &&
    c2 &&
    captchaOk &&
    state.catalogReady &&
    state.service
  );
}
document.getElementById("chkCorrect").onchange = checkChecks;
document.getElementById("chkGuidelines").onchange = checkChecks;
document.getElementById("editBtn").onclick = () =>
  document.getElementById("reviewModal").classList.remove("open");

/* ---------------- SUBMIT ---------------- */
document.getElementById("submitBtn").onclick = async () => {
  if (!state.catalogReady || !state.service) {
    alert(
      "Service catalog is unavailable. Reservation submission is disabled.",
    );
    return;
  }
  if (!isValidPhone(state.contactNumber) || !isValidEmail(state.email)) {
    alert("Please enter a valid 11-digit contact number and email address.");
    document.getElementById("reviewModal").classList.remove("open");
    return;
  }
  if (
    !state.customerName ||
    !state.eventDate ||
    !state.eventTime ||
    !state.eventType ||
    !state.guests ||
    state.selectedPackage === null
  ) {
    alert("Please complete all required fields.");
    return;
  }
  if (hasUnresolvedPricing()) {
    alert(
      "The selected package or additional service has an unconfirmed price and cannot be submitted.",
    );
    document.getElementById("reviewModal").classList.remove("open");
    return;
  }
  // FINAL re-validation before submission (the calendar may have changed since
  // the customer picked the date — a day can become past or taken meanwhile).
  const finalWindowErr = bookingWindowError(state.eventDate);
  if (finalWindowErr) {
    alert(
      finalWindowErr === "TOO_SOON"
        ? `Bookings require at least ${PDR_MIN_LEAD_DAYS} days' notice. Please choose a later date.`
        : finalWindowErr === "TOO_FAR"
          ? `Bookings open at most ${PDR_MAX_HORIZON_DAYS} days ahead. Please choose an earlier date.`
          : "Please choose a valid event date.",
    );
    document.getElementById("reviewModal").classList.remove("open");
    state.eventDate = null;
    renderPanel();
    return;
  }
  if (
    !isTimeAvailable(state.eventDate, state.eventTime, currentServiceName())
  ) {
    alert(
      "That time is already booked for your service on this date. Please go back and choose another event time.",
    );
    document.getElementById("reviewModal").classList.remove("open");
    state.eventTime = "";
    renderPanel();
    return;
  }
  if (isDateTaken(state.eventDate)) {
    alert(
      AVAIL.meta[state.eventDate] && AVAIL.meta[state.eventDate].hasVenue
        ? "The venue is already booked for that date. Please select an available event date."
        : "That date is fully booked. Please select an available event date.",
    );
    return;
  }
  if (removeMainServiceFromCart().length) {
    alert(
      "Your main service cannot also be an additional service. Please review your reservation.",
    );
    renderAdditionalServices(state.service.id);
    renderSummary();
    document.getElementById("reviewModal").classList.remove("open");
    return;
  }
  const btn = document.getElementById("submitBtn");
  btn.disabled = true;
  btn.textContent = "Submitting…";

  // T-45: wait for the public config, then require a Turnstile token when the
  // keys are configured. pdrVerifyCaptcha() enforces the same rule server-side.
  await pdrConfigReady;
  if (PDR_PUBLIC_CONFIG.captchaEnabled && !pdrCaptchaToken) {
    alert("Please complete the verification challenge before submitting.");
    btn.disabled = false;
    btn.textContent = "Submit Reservation";
    return;
  }

  // Structured additional-services list: name, quantity, unit price, total price.
  const additionalServices = Object.values(state.cart).map((c) => ({
    name: c.item.name,
    packageName: c.pkgName || null,
    quantity: c.item.qty ? c.qty : 1,
    unitPrice: c.unitPrice,
    totalPrice: addonLineTotal(c),
  }));

  // Only the FORM data is sent. The backend generates the Reservation ID and the
  // Booking PIN, sets the status to "Pending" and stamps the submission time.
  const payload = {
    customerName: state.customerName,
    contactPerson: state.customerName, // the form has one "Customer Name / Contact Person" field
    contactNumber: state.contactNumber,
    email: state.email,
    // Only sent on the services that collect it; others leave it null server-side.
    address: state.service.requiresAddress ? state.address.trim() : "",
    serviceId: state.service.id, // lets the backend apply per-service rules (e.g. address required)
    eventDate: state.eventDate,
    eventTime: state.eventTime,
    eventType:
      state.eventType === "Other" ? state.eventTypeOther : state.eventType,
    guests: parseInt(state.guests),
    mainService: state.service.name,
    selectedPackage: state.service.packages[state.selectedPackage][0],
    ...(state.service.mainCourses
      ? { mainCourses: state.mainCourses.slice() }
      : {}),
    additionalServices,
    estimatedTotal: computeTotal(),
    // T-45: present only when Turnstile is configured.
    ...(PDR_PUBLIC_CONFIG.captchaEnabled ? { captchaToken: pdrCaptchaToken } : {}),
  };

  let result;
  try {
    result = await createReservation(payload);
  } catch (e) {
    result = { ok: false, reason: "NETWORK" };
  }

  btn.disabled = false;
  btn.textContent = "Submit Reservation";

  if (!result.ok) {
    // Turnstile tokens are single-use, so any failed attempt needs a fresh one.
    resetCaptcha();
    checkChecks();
    if (result.reason === "DATE_TAKEN") {
      // cause distinguishes WHY the date closed: VENUE (exclusive per date)
      // or FULL (2 services already booked).
      alert(
        result.cause === "VENUE"
          ? "Sorry, the venue was just booked for that date by another customer. Please choose another date."
          : "Sorry, that date just reached its booking limit. Please choose another date.",
      );
      document.getElementById("reviewModal").classList.remove("open");
      state.eventDate = null;
      state.eventTime = "";
      await refreshAvailability();
      renderPanel();
    } else if (result.reason === "TIME_TAKEN") {
      alert(
        "Sorry, that time was just booked for your service by another customer. Please choose another event time.",
      );
      document.getElementById("reviewModal").classList.remove("open");
      state.eventTime = "";
      await refreshAvailability();
      renderPanel();
    } else if (result.reason === "CAPTCHA_FAILED") {
      alert(
        "The verification challenge failed or expired. Please complete it again and submit.",
      );
    } else if (result.reason === "INVALID") {
      alert(
        "Some reservation details are missing or invalid. Please review the form.",
      );
    } else {
      alert(
        "Sorry, we could not submit your reservation right now. Please check your connection and try again.",
      );
    }
    return;
  }

  await refreshAvailability(); // this booking may have just closed a date/time
  document.getElementById("reviewModal").classList.remove("open");
  showSuccess(
    Object.assign({}, payload, {
      reservationId: result.reservationId,
      bookingPin: result.bookingPin,
      status: result.status,
    }),
  );
};

function showSuccess(r) {
  const e = pdrEscape;
  const extras = r.additionalServices.length
    ? r.additionalServices
        .map(
          (s) =>
            e(s.name) +
            (s.packageName ? ` — ${e(s.packageName)}` : "") +
            (s.quantity > 1 ? ` × ${s.quantity}` : ""),
        )
        .join(", ")
    : "None";
  document.getElementById("successBody").innerHTML = `
    <h2>Reservation Submitted Successfully!</h2>
    <p>Your reservation has been successfully submitted.</p>
    <div class="credentials-box">
      <div class="section-label">RESERVATION ID</div>
      <p class="refnum">${e(r.reservationId)}</p>
      <div class="section-label">BOOKING PIN</div>
      <p class="refnum pin-digits">${e(r.bookingPin)}</p>
    </div>
    <p class="pin-warn"><strong>Please save your Reservation ID and Booking PIN.</strong> You will need both to check your reservation status later. The PIN will not be shown again.</p>
    <div class="section-label">BOOKING STATUS</div>
    <p><span class="status-pill ${e(String(r.status || "").toLowerCase())}">${e(r.status)} — Awaiting Confirmation</span></p>
    <div class="section-label" style="margin-top:16px;">RESERVATION DETAILS</div>
    <div class="confirm-row"><span>Customer</span><span>${e(r.customerName)}</span></div>
    <div class="confirm-row"><span>Event Date</span><span>${e(r.eventDate)}</span></div>
    ${r.address ? `<div class="confirm-row"><span>Address</span><span>${e(r.address)}</span></div>` : ""}
    <div class="confirm-row"><span>Event Time</span><span>${e(fmtEventTime(r.eventTime))}</span></div>
    <div class="confirm-row"><span>Event Type</span><span>${e(r.eventType)}</span></div>
    <div class="confirm-row"><span>Guests</span><span>${e(r.guests)} pax</span></div>
    <div class="confirm-row"><span>Main Service</span><span>${e(r.mainService)}</span></div>
    <div class="confirm-row"><span>Package</span><span>${e(r.selectedPackage)}</span></div>
    ${r.mainCourses && r.mainCourses.length ? `<div class="confirm-row"><span>Main Courses</span><span>${e(r.mainCourses.join(", "))}</span></div>` : ""}
    <div class="confirm-row"><span>Additional Services</span><span>${extras}</span></div>
    <div class="confirm-row" style="font-weight:bold;"><span>Estimated Total</span><span>₱${r.estimatedTotal.toLocaleString()}</span></div>
    <p class="note">The restaurant will review your request and confirm final details and availability.</p>
    <a class="btn-main" id="viewStatusBtn" href="booking-status.html?id=${encodeURIComponent(r.reservationId)}" style="display:block;text-align:center;text-decoration:none;">View Booking Status</a>
    <a class="btn-ghost" id="homeBtn" href="nav.html" style="display:block;text-align:center;text-decoration:none;">Return to Home</a>
    <button class="btn-ghost" id="anotherBtn">Make Another Reservation</button>
  `;
  document.getElementById("successModal").classList.add("open");
  document.getElementById("anotherBtn").onclick = () => {
    document.getElementById("successModal").classList.remove("open");
    state.customerName = "";
    state.contactNumber = "";
    state.email = "";
    state.address = "";
    state.eventDate = null;
    state.eventTime = "";
    state.eventType = "";
    state.eventTypeOther = "";
    state.guests = "";
    state.selectedPackage = null;
    state.mainCourses = [];
    state.cart = {};
    state.picking = null;
    state.pickingPkg = 0;
    state.notice = "";
    renderAll();
    refreshAvailability(); // reload availability for the fresh form
  };
}

/* ---------------- AVAILABILITY REFRESH ---------------- */
// Load the CURRENTLY DISPLAYED month's availability (taken dates, per-date
// counts, per-service time slots) then redraw the open/closed calendar and the
// time picker. Call it after any change that could alter availability: month
// navigation, a rejected submission, or a fresh form.
// (Top-level so the submit handler can call it after a DATE_TAKEN rejection.)
async function refreshAvailability(year, month) {
  if (!state.catalogReady || !state.service) return;
  const y = year !== undefined && year !== null ? year : state.calYear;
  const m = month !== undefined && month !== null ? month : state.calMonth + 1;
  try {
    await loadAvailability(y, m);
  } catch (e) {
    /* keep what we have */
  }
  const box = document.getElementById("calBox");
  if (box) {
    const wasOpen = box.classList.contains("open");
    box.innerHTML = renderCalendar();
    box.classList.toggle("open", wasOpen);
    bindCalendar();
  }
  refreshTimeOptions();
}

/* ---------------- LOADING & ERROR STATES ---------------- */
function renderLoadingState() {
  const detailsEl = document.getElementById("serviceDetails");
  const panelEl = document.getElementById("bookingPanel");
  if (detailsEl) {
    detailsEl.innerHTML = `<p class="note">Loading service details…</p>`;
  }
  if (panelEl) {
    panelEl.innerHTML = `
      <h2>Book Your Event</h2>
      <p class="note">Loading booking options…</p>
      <button type="button" class="btn-main" disabled>Continue Reservation</button>
    `;
  }
}

function renderCatalogErrorState(heading, message) {
  state.service = null;
  state.catalogReady = false;

  const reviewModal = document.getElementById("reviewModal");
  if (reviewModal) reviewModal.classList.remove("open");
  const submitBtn = document.getElementById("submitBtn");
  if (submitBtn) submitBtn.disabled = true;

  const detailsEl = document.getElementById("serviceDetails");
  const panelEl = document.getElementById("bookingPanel");

  if (detailsEl) {
    detailsEl.innerHTML = `
      <h1>${pdrEscape(heading)}</h1>
      <p class="note">${pdrEscape(message)}</p>
    `;
  }
  if (panelEl) {
    panelEl.innerHTML = `
      <h2>Book Your Event</h2>
      <div class="field">
        <div class="err" style="display:block">${pdrEscape(message)}</div>
      </div>
      <button type="button" class="btn-main" id="continueBtn" disabled>Reservation Unavailable</button>
    `;
  }
}

/* ---------------- INIT ---------------- */
function renderAll() {
  if (!state.catalogReady || !state.service) return;
  renderDetails();
  renderPanel();
}

async function initReservationPage() {
  renderLoadingState();

  if (typeof loadCatalogFromApi !== "function") {
    renderCatalogErrorState(
      "Service Catalog Unavailable",
      "Unable to initialize the service catalog loader. Please try again later.",
    );
    return;
  }

  let catalogResult;
  try {
    catalogResult = await loadCatalogFromApi();
  } catch (e) {
    catalogResult = { ok: false, reason: "LOAD_EXCEPTION" };
  }

  if (!catalogResult || !catalogResult.ok) {
    renderCatalogErrorState(
      "Service Catalog Unavailable",
      "We could not load the service catalog right now. Please check your connection and refresh the page.",
    );
    return;
  }

  const requestedId = typeof SERVICE_ID === "string" ? SERVICE_ID.trim() : "";
  const matchedService = requestedId
    ? SERVICES.find((s) => s.id === requestedId)
    : null;

  if (!matchedService) {
    renderCatalogErrorState(
      "Service Not Available",
      "The requested service is not currently available for online reservation. Please return to the homepage and select an active service.",
    );
    return;
  }

  state.service = matchedService;
  state.catalogReady = true;
  renderAll();
  await refreshAvailability();
}

initReservationPage();
