// Filled from getUnavailableDates() (services.js backend area), so this booking
// calendar and the homepage availability calendar always agree. Starts empty and
// is refreshed as soon as the data arrives (see refreshUnavailableDates below).
let unavailableDates = [];

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

/* ---------------- STATE ---------------- */
// The calendar opens on the CURRENT month (calculated in Asia/Manila), never a
// hard-coded one, so past months are not the default view.
const _todayDS = getTodayLocalDate();
let state = {
  service: SERVICES.find((s) => s.id === SERVICE_ID) || SERVICES[0],
  customerName: "",
  contactNumber: "",
  email: "",
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
  document.getElementById("serviceDetails").innerHTML = `
    <h1>${s.name}</h1>
    <div class="venue-img">${SERVICE_IMAGES[s.id] ? `<img src="${SERVICE_IMAGES[s.id]}" alt="${s.name}">` : "[Pio De Roda Venue Image — placeholder]"}</div>
    <div class="section-label">RATES</div>
    <table class="rates">${s.rates.map((r) => `<tr><td>${r[0]}</td><td>${r[1]}</td></tr>`).join("")}</table>
    ${s.menu ? renderMenuTable(s) : ""}
    ${s.mainCourses ? renderMainCourses(s) : ""}
    <div class="section-label">STARTING PRICE</div>
    <div class="price-hero"><div class="amt">${s.price}</div></div>
    <div class="section-label">INCLUSIONS</div>
    ${s.inclusionsTable ? renderInclusionsTable(s.inclusionsTable) : `<ul class="plain check">${s.inclusions.map((i) => `<li>${i}</li>`).join("")}</ul>`}
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
    const unavail = unavailableDates.includes(ds);
    const sel = state.eventDate === ds;
    cells += `<div class="day ${unavail ? "unavail" : "avail"} ${sel ? "sel" : ""} ${isToday ? "today" : ""}"
      data-date="${unavail ? "" : ds}" ${unavail ? 'aria-disabled="true" ' : ""}role="button" tabindex="0"
      aria-label="${ds} — ${unavail ? "unavailable" : isToday ? "today, available" : "available"}${sel ? ", selected" : ""}">${d}</div>`;
  }
  return `
    <div class="cal-head"><button type="button" data-nav="-1" aria-label="Previous month">‹</button><span>${monthName} ${y}</span><button type="button" data-nav="1" aria-label="Next month">›</button></div>
    <div class="cal-grid">${cells}</div>
  `;
}

/* ---------------- RENDER: BOOKING PANEL ---------------- */
function renderPanel() {
  const s = state.service;
  const el = document.getElementById("bookingPanel");
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
    <div class="field">
      <label>Event Date <span class="req" title="Required">*</span></label>
      <input type="text" id="fDate" class="date-input" readonly placeholder="Select a date" value="${state.eventDate || ""}">
      <div class="cal" id="calBox">${renderCalendar()}</div>
      <div class="err" id="errDate">Please select an event date.</div>
    </div>
    <div class="field">
      <label>Event Time <span class="req" title="Required">*</span></label>
      <select id="fTime">
        <option value="">-- Select Time --</option>
        ${EVENT_TIME_SLOTS.map((t) => `<option value="${t}" ${state.eventTime === t ? "selected" : ""}>${fmtEventTime(t)}</option>`).join("")}
      </select>
      <div class="cap">Placeholder schedule — event time slots are subject to confirmation by Pio De Roda staff.</div>
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
          <span class="nm">${p[0]}</span><span>${pkgPerHeadRate(s, p) > 0 ? "₱" + pkgPerHeadRate(s, p).toLocaleString() + " per head" : p[1] === null ? "" : p[1] ? "₱" + p[1].toLocaleString() : "₱XXX"}</span>
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
  return c.unitPrice || 0;
}
function addonLineTotal(c) {
  const per = c.item.perHead || c.item.perHeadByPkg;
  const base = per
    ? addonLineUnit(c) * (parseInt(state.guests) || 0)
    : addonLineUnit(c);
  return base * (c.item.qty ? c.qty : 1);
}

/* Shared row markup for the summary box AND the review modal: shows the
   chosen package, the computed amount, and how a per-head amount derives. */
function addonCartRows(kind) {
  const guests = parseInt(state.guests) || 0;
  return Object.values(state.cart)
    .map((c) => {
      const per = c.item.perHead || c.item.perHeadByPkg;
      const note = c.pkgName
        ? ` <small class="line-note">${c.pkgName}${
            per && guests > 0
              ? ` — ${guests} pax × ₱${addonLineUnit(c).toLocaleString()} per head`
              : ""
          }</small>`
        : "";
      const label =
        c.item.name + (c.item.qty ? " × " + c.qty : "") + note;
      return `<div class="${kind}"><span>${label}</span><span>₱${addonLineTotal(c).toLocaleString()}</span></div>`;
    })
    .join("");
}

/* Adds (or updates) a cart entry from the picker's selected package row. */
function addAddonToCart(item, pkgIdx) {
  if (!item || item.serviceId === state.service.id) return;
  const opts = addonOptions(item);
  const pkg =
    opts[Math.max(0, Math.min(pkgIdx || 0, opts.length - 1))] ||
    [item.name, item.price];
  const prev = state.cart[item.id];
  state.cart[item.id] = {
    qty: prev && item.qty ? prev.qty : 1,
    item,
    pkgName: pkg[0],
    unitPrice: addonOptionRate(item, pkg),
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
        const rates = opts.map((o) => addonOptionRate(a, o)).filter((r) => r > 0);
        const min = rates.length ? Math.min(...rates) : null;
        const perHeadish = a.perHead || a.perHeadByPkg;
        const anim = animate ? " fade-in" : "";

        // ---- inline package picker open for this add-on ----
        if (state.picking === a.id) {
          const rows = opts
            .map((o, i) => {
              const rate = addonOptionRate(a, o);
              const sel = i === state.pickingPkg ? " sel" : "";
              const amt = perHeadish
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
          const breakdown = perHeadish
            ? guests > 0
              ? `${guests} pax × ₱${addonLineUnit(inCart).toLocaleString()} per head`
              : "per guest"
            : a.qty
              ? `₱${addonLineUnit(inCart).toLocaleString()} per item`
              : "";
          return `<div class="addon in-cart${anim}">
            <div style="flex:1">
              <div class="nm">✓ ${a.name}</div>
              <div class="price">${inCart.pkgName || ""} — ₱${addonLineTotal(inCart).toLocaleString()}</div>
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
  if (s.perHeadByPkg) return pkg ? pkg[1] || 0 : 0;
  if (s.perHead) return s.perHead;
  return 0;
}

/* The selected package's contribution to the estimated total:
   per-head services multiply their rate by the guest count; all other
   services just add their flat package price. */
function pkgTotal(s, pkg, guests) {
  const per = pkgPerHeadRate(s, pkg);
  if (per > 0) return per * (parseInt(guests) || 0);
  return (pkg && pkg[1]) || 0;
}

/* How the selected package is shown in the summary and review:
   per-head services show the computed amount (rate × pax); other services
   keep their flat package price, or just the name if the package has none. */
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
  return pkg[1] === null ? pkg[0] : pkg[0] + " — ₱" + pkg[1].toLocaleString();
}

function computeTotal() {
  let total = 0;
  if (state.selectedPackage !== null) {
    total += pkgTotal(
      state.service,
      state.service.packages[state.selectedPackage],
      state.guests,
    );
  }
  Object.values(state.cart).forEach((c) => {
    total += addonLineTotal(c);
  });
  return total;
}

function renderSummary() {
  const box = document.getElementById("summaryBox");
  if (!box) return;
  const pkg =
    state.selectedPackage !== null
      ? state.service.packages[state.selectedPackage]
      : null;
  const cartRows = addonCartRows("row");
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
      <div class="row total"><span>Estimated Total</span><span>₱${computeTotal().toLocaleString()}</span></div>
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
      (b.onclick = () => {
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
      revalidateEventTime(); // the DATE changed — re-check the chosen time
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
  if (isTimeAvailable(state.eventDate, state.eventTime)) return true;
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
  let ok = true;
  const setErr = (id, show) => {
    document.getElementById(id).style.display = show ? "block" : "none";
    if (show) ok = false;
  };
  setErr("errName", !state.customerName.trim());

  // EVENT DATE — required, must not be in the past, must still be available.
  // The calendar already blocks those days; this is the SECOND check that runs
  // at submission time (a date can become past/taken after it was selected).
  let dateMsg = "";
  if (!state.eventDate) dateMsg = "Please select an event date.";
  else if (isPastDate(state.eventDate))
    dateMsg =
      "That date has already passed. Please select a current or future date.";
  else if (unavailableDates.includes(state.eventDate))
    dateMsg = "That date is no longer available. Please choose another date.";
  if (dateMsg) document.getElementById("errDate").textContent = dateMsg;
  setErr("errDate", !!dateMsg);

  // EVENT TIME — required and still offered for the chosen date.
  let timeMsg = "";
  if (!state.eventTime) timeMsg = "Please select an event time.";
  else if (!isTimeAvailable(state.eventDate, state.eventTime))
    timeMsg =
      "The selected event time is not available for this date. Please choose another event time.";
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
  if (phoneBad || emailBad) ok = false;
  if (!ok) {
    const firstErr = document.querySelector(".err[style*='block'], .invalid");
    if (firstErr)
      firstErr.scrollIntoView({ behavior: "smooth", block: "center" });
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
  const pkg = s.packages[state.selectedPackage];
  const cartRows =
    addonCartRows("confirm-row") ||
    `<div class="confirm-row"><span>None selected</span><span>—</span></div>`;
  document.getElementById("reviewBody").innerHTML = `
    <div class="confirm-row"><span>Customer</span><span>${state.customerName}</span></div>
    <div class="confirm-row"><span>Contact Number</span><span>${state.contactNumber}</span></div>
    <div class="confirm-row"><span>Email</span><span>${state.email}</span></div>
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
}

function checkChecks() {
  const c1 = document.getElementById("chkCorrect").checked;
  const c2 = document.getElementById("chkGuidelines").checked;
  document.getElementById("submitBtn").disabled = !(c1 && c2);
}
document.getElementById("chkCorrect").onchange = checkChecks;
document.getElementById("chkGuidelines").onchange = checkChecks;
document.getElementById("editBtn").onclick = () =>
  document.getElementById("reviewModal").classList.remove("open");

/* ---------------- SUBMIT ---------------- */
document.getElementById("submitBtn").onclick = async () => {
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
  // FINAL re-validation before submission (the calendar may have changed since
  // the customer picked the date — a day can become past or taken meanwhile).
  if (isPastDate(state.eventDate)) {
    alert(
      "The selected event date has already passed. Please go back and choose a current or future date.",
    );
    document.getElementById("reviewModal").classList.remove("open");
    state.eventDate = null;
    renderPanel();
    return;
  }
  if (!isTimeAvailable(state.eventDate, state.eventTime)) {
    alert(
      "The selected event time is not available for this date. Please go back and choose another event time.",
    );
    document.getElementById("reviewModal").classList.remove("open");
    state.eventTime = "";
    renderPanel();
    return;
  }
  if (unavailableDates.includes(state.eventDate)) {
    alert("Please select an available event date.");
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
    if (result.reason === "DATE_TAKEN") {
      alert(
        "Sorry, that date was just reserved by someone else. Please choose another date.",
      );
      document.getElementById("reviewModal").classList.remove("open");
      state.eventDate = null;
      await refreshUnavailableDates();
      renderPanel();
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

  unavailableDates.push(state.eventDate);
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
    refreshUnavailableDates(); // reload the taken dates for the fresh form
  };
}

/* ---------------- UNAVAILABLE DATES ---------------- */
// Load the taken dates from the backend area, then redraw the open/closed calendar.
// (Top-level so the submit handler can also call it after a DATE_TAKEN rejection.)
async function refreshUnavailableDates() {
  try {
    unavailableDates = await getUnavailableDates();
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
}

/* ---------------- INIT ---------------- */
function renderAll() {
  renderDetails();
  renderPanel();
}
renderAll();
refreshUnavailableDates(); // fill the calendar as soon as the data arrives
