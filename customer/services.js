/* Shared service catalog, add-ons, and guidelines for Pio De Roda reservation pages */
/* Loaded dynamically from ../api/test_services_stage4c5.php (Stage 4D database catalog integration). */

const CATALOG_API_URL = "../api/services.php";

/* Original established customer service ordering */
const SERVICE_ORDER = [
  "catering-menu",
  "venue",
  "dinein",
  "noncoffee",
  "coffee",
  "pizza",
  "intimate",
  "equipment",
];

/* Off-site / delivery services that require a setup or delivery address */
const ADDRESS_REQUIRED_SERVICES = [
  "catering-menu",
  "noncoffee",
  "coffee",
  "pizza",
  "equipment",
];

/* Established customer-facing descriptions for the Additional Services cards */
const ADDON_UI_DESCRIPTIONS = {
  "catering-menu": "Add full catering with menu to your event.",
  venue: "Add exclusive use of an additional venue/area.",
  dinein: "Add a set menu (Menu A or Menu B) served per guest.",
  noncoffee: "Add a non-coffee drinks booth to your event.",
  coffee: "Add a coffee booth to your event.",
  pizza: "Add a live pizza booth to your event.",
  intimate: "Add intimate-celebration elements to your event.",
  equipment: "Add extra equipment rental. Priced per item.",
};

/* Populated exclusively from the catalog API response — never hardcoded fallback data */
const SERVICES = [];
const addonCatalog = [];
const guidelines = [];

let _catalogLoadPromise = null;
let _catalogState = {
  loaded: false,
  ok: false,
  reason: null,
};

/* Service photos — the SAME pictures used on the homepage service cards
   (nav.html), shown at the top of each service's details panel in place of
   the old "[Pio De Roda Venue Image — placeholder]" text. One map keeps the
   homepage and every service page in sync. */
const SERVICE_IMAGES = {
  "catering-menu": "cateringwmenu.jpg",
  venue: "venuerent.jpg",
  dinein: "dinein.jpg",
  noncoffee: "noncoffee.jpg",
  coffee: "coffeebooth.jpg",
  pizza: "pizza.jpg",
  intimate: "intimate.jpg",
  equipment: "equipment.jpg",
};

function _toNullableNumber(v) {
  if (v === null || v === undefined || v === "") return null;
  const n = Number(v);
  return Number.isFinite(n) ? n : null;
}

function _normalizePackageTuple(pkg) {
  if (!Array.isArray(pkg) || typeof pkg[0] !== "string" || !pkg[0].trim()) {
    return null;
  }
  const name = pkg[0].trim();
  const price = _toNullableNumber(pkg[1]);
  const code =
    pkg[2] !== null && pkg[2] !== undefined && String(pkg[2]).trim() !== ""
      ? String(pkg[2]).trim()
      : null;
  const maxCap = _toNullableNumber(pkg[3]);
  const menuCode =
    pkg[4] !== null && pkg[4] !== undefined && String(pkg[4]).trim() !== ""
      ? String(pkg[4]).trim()
      : null;
  return [name, price, code, maxCap, menuCode];
}

function _normalizeMenus(rawMenus) {
  if (!Array.isArray(rawMenus)) return [];
  return rawMenus
    .filter((m) => m && typeof m === "object" && m.is_active !== false)
    .map((m) => ({
      menu_code: m.menu_code ? String(m.menu_code) : null,
      category_name: m.category_name ? String(m.category_name) : "",
      item_name: m.item_name ? String(m.item_name) : "",
      price: _toNullableNumber(m.price),
      dishes: Array.isArray(m.dishes) ? m.dishes.map((d) => String(d)) : [],
      is_active: m.is_active !== false,
    }))
    .filter((m) => m.category_name && m.item_name);
}

function _normalizeGroupedMenu(rawMenu, normalizedMenus) {
  let groups = [];
  if (Array.isArray(rawMenu) && rawMenu.length > 0) {
    groups = rawMenu
      .filter(
        (cat) => cat && typeof cat === "object" && Array.isArray(cat.items),
      )
      .map((cat) => ({
        category: String(cat.category || ""),
        items: cat.items
          .filter((item) => item && typeof item === "object" && item.name)
          .map((item) => ({
            name: String(item.name),
            price: _toNullableNumber(item.price),
            dishes: Array.isArray(item.dishes)
              ? item.dishes.map((d) => String(d))
              : [],
            menuCode:
              item.menuCode !== undefined && item.menuCode !== null
                ? String(item.menuCode)
                : item.menu_code !== undefined && item.menu_code !== null
                  ? String(item.menu_code)
                  : null,
          })),
      }))
      .filter((cat) => cat.category && cat.items.length > 0);
  } else if (Array.isArray(normalizedMenus) && normalizedMenus.length > 0) {
    const byCat = new Map();
    normalizedMenus.forEach((m) => {
      if (!byCat.has(m.category_name)) {
        byCat.set(m.category_name, { category: m.category_name, items: [] });
      }
      byCat.get(m.category_name).items.push({
        name: m.item_name,
        price: m.price,
        dishes: m.dishes.slice(),
        menuCode: m.menu_code,
      });
    });
    groups = Array.from(byCat.values());
  }
  return groups.length > 0 ? groups : undefined;
}

function _normalizeService(raw) {
  if (!raw || typeof raw !== "object") return null;
  const id = typeof raw.id === "string" ? raw.id.trim() : "";
  const name = typeof raw.name === "string" ? raw.name.trim() : "";
  if (
    !id ||
    !name ||
    !/^[a-z0-9-]+$/i.test(id) ||
    !SERVICE_ORDER.includes(id) ||
    !Array.isArray(raw.packages) ||
    !Array.isArray(raw.menus) ||
    !Array.isArray(raw.addons) ||
    !Array.isArray(raw.inclusions)
  ) {
    return null;
  }

  const basePrice = _toNullableNumber(raw.base_price);
  const priceDisplay =
    raw.price_display !== null &&
    raw.price_display !== undefined &&
    String(raw.price_display).trim() !== ""
      ? String(raw.price_display).trim()
      : raw.price !== null &&
          raw.price !== undefined &&
          String(raw.price).trim() !== ""
        ? String(raw.price).trim()
        : basePrice !== null
          ? `₱${basePrice.toLocaleString()}`
          : null;

  const perHead = _toNullableNumber(
    raw.per_head !== undefined ? raw.per_head : raw.perHead,
  );
  const perHeadByPkg = Boolean(
    raw.per_head_by_pkg !== undefined ? raw.per_head_by_pkg : raw.perHeadByPkg,
  );
  const maxCap = _toNullableNumber(
    raw.max_cap !== undefined ? raw.max_cap : raw.maxCap,
  );

  const menus = _normalizeMenus(raw.menus);
  const menu = _normalizeGroupedMenu(raw.menu, menus);

  let packages = Array.isArray(raw.packages)
    ? raw.packages.map(_normalizePackageTuple).filter(Boolean)
    : [];

  // Dine-In Menu: if packages are not explicitly populated, derive selectable
  // 5-element tuples from the grouped menu while keeping NULL menu prices intact.
  if (id === "dinein" && packages.length === 0 && menu) {
    packages = menu.flatMap((cat) =>
      cat.items.map((item) => [
        item.name,
        item.price,
        item.menuCode || null,
        maxCap,
        item.menuCode || null,
      ]),
    );
  }

  const rates = Array.isArray(raw.rates)
    ? raw.rates
        .filter((r) => Array.isArray(r) && r.length >= 2)
        .map((r) => [String(r[0]), String(r[1])])
    : [];

  const inclusions = Array.isArray(raw.inclusions)
    ? raw.inclusions.map((i) => String(i))
    : [];

  const inclusionsTable =
    raw.inclusionsTable &&
    typeof raw.inclusionsTable === "object" &&
    Array.isArray(raw.inclusionsTable.headers) &&
    Array.isArray(raw.inclusionsTable.rows)
      ? {
          headers: raw.inclusionsTable.headers.map((h) => String(h)),
          rows: raw.inclusionsTable.rows.map((row) =>
            Array.isArray(row) ? row.map((cell) => String(cell)) : [],
          ),
        }
      : undefined;

  const mainCourses =
    Array.isArray(raw.mainCourses) && raw.mainCourses.length > 0
      ? raw.mainCourses.map((c) => String(c))
      : undefined;

  const notes = Array.isArray(raw.notes) ? raw.notes.map((n) => String(n)) : [];

  const serviceGuidelines = Array.isArray(raw.guidelines)
    ? raw.guidelines.map((g) => String(g))
    : [];

  const requiresAddress =
    raw.requires_address !== undefined
      ? Boolean(raw.requires_address)
      : raw.requiresAddress !== undefined
        ? Boolean(raw.requiresAddress)
        : ADDRESS_REQUIRED_SERVICES.includes(id);

  const svc = {
    id,
    name,
    requiresAddress,
    category: raw.category ? String(raw.category) : null,
    base_price: basePrice,
    basePrice,
    price_display: raw.price_display ?? null,
    price: priceDisplay,
    per_head: perHead,
    perHead,
    per_head_by_pkg: perHeadByPkg,
    perHeadByPkg,
    max_cap: maxCap,
    maxCap,
    description: raw.description ? String(raw.description) : "",
    inclusions,
    guidelines: serviceGuidelines,
    sort_order: Number.isFinite(Number(raw.sort_order))
      ? Number(raw.sort_order)
      : 0,
    rates,
    packages,
    menus,
    notes,
  };

  if (menu) svc.menu = menu;
  if (mainCourses) svc.mainCourses = mainCourses;
  if (inclusionsTable) svc.inclusionsTable = inclusionsTable;

  return svc;
}

function _serviceOrderRank(serviceId) {
  const idx = SERVICE_ORDER.indexOf(serviceId);
  return idx >= 0 ? idx : SERVICE_ORDER.length + 100;
}

async function loadCatalogFromApi(forceReload = false) {
  if (_catalogLoadPromise && !forceReload) {
    return _catalogLoadPromise;
  }

  _catalogLoadPromise = (async () => {
    SERVICES.length = 0;
    addonCatalog.length = 0;
    guidelines.length = 0;
    _catalogState = { loaded: false, ok: false, reason: null };

    let res;
    try {
      res = await fetch(CATALOG_API_URL, {
        method: "GET",
        headers: { Accept: "application/json" },
        cache: "no-store",
      });
    } catch (err) {
      _catalogState = { loaded: true, ok: false, reason: "NETWORK_ERROR" };
      return { ok: false, reason: "NETWORK_ERROR" };
    }

    if (!res.ok) {
      _catalogState = { loaded: true, ok: false, reason: `HTTP_${res.status}` };
      return { ok: false, reason: `HTTP_${res.status}` };
    }

    let data;
    try {
      const text = await res.text();
      data = JSON.parse(text);
    } catch (err) {
      _catalogState = { loaded: true, ok: false, reason: "INVALID_JSON" };
      return { ok: false, reason: "INVALID_JSON" };
    }

    if (
      !data ||
      data.ok !== true ||
      !Array.isArray(data.services) ||
      data.services.length === 0 ||
      !Array.isArray(data.guidelines)
    ) {
      _catalogState = {
        loaded: true,
        ok: false,
        reason: (data && data.reason) || "INVALID_CATALOG_PAYLOAD",
      };
      return { ok: false, reason: _catalogState.reason };
    }

    const rawServicesById = new Map();
    const normalizedServices = [];

    for (const rawSvc of data.services) {
      const norm = _normalizeService(rawSvc);
      if (!norm || rawServicesById.has(norm.id)) {
        _catalogState = {
          loaded: true,
          ok: false,
          reason: "INVALID_SERVICE_IDENTIFIER",
        };
        return { ok: false, reason: "INVALID_SERVICE_IDENTIFIER" };
      }
      rawServicesById.set(norm.id, rawSvc);
      normalizedServices.push(norm);
    }

    normalizedServices.sort((a, b) => {
      const rankDiff = _serviceOrderRank(a.id) - _serviceOrderRank(b.id);
      if (rankDiff !== 0) return rankDiff;
      if (a.sort_order !== b.sort_order) return a.sort_order - b.sort_order;
      return a.id.localeCompare(b.id);
    });

    normalizedServices.forEach((s) => SERVICES.push(s));

    // Flatten nested service add-ons in the established service order and
    // hydrate package choices and per-head rates from their linked parent service.
    normalizedServices.forEach((svc) => {
      const rawSvc = rawServicesById.get(svc.id);
      const rawAddons =
        rawSvc && Array.isArray(rawSvc.addons) ? rawSvc.addons : [];
      rawAddons.forEach((rawAddon) => {
        if (!rawAddon || typeof rawAddon !== "object") return;
        if (rawAddon.is_active === false) return;
        const addonId =
          typeof rawAddon.id === "string" ? rawAddon.id.trim() : "";
        const serviceId =
          typeof (rawAddon.serviceId || rawAddon.service_id) === "string"
            ? String(rawAddon.serviceId || rawAddon.service_id).trim()
            : svc.id;
        if (!addonId || !serviceId) return;

        const priceFrom =
          rawAddon.price_from !== null &&
          rawAddon.price_from !== undefined &&
          String(rawAddon.price_from).trim() !== ""
            ? String(rawAddon.price_from).trim()
            : rawAddon.priceFrom !== null &&
                rawAddon.priceFrom !== undefined &&
                String(rawAddon.priceFrom).trim() !== ""
              ? String(rawAddon.priceFrom).trim()
              : null;

        const linkedSvc = SERVICES.find(
          (s) => s.id === (priceFrom || serviceId),
        );

        const pkgs = linkedSvc
          ? linkedSvc.packages.map((p) => p.slice())
          : Array.isArray(rawAddon.pkgs)
            ? rawAddon.pkgs.map(_normalizePackageTuple).filter(Boolean)
            : [];

        const perHead = linkedSvc
          ? linkedSvc.perHead
          : _toNullableNumber(rawAddon.perHead);
        const perHeadByPkg = linkedSvc
          ? Boolean(linkedSvc.perHeadByPkg)
          : Boolean(rawAddon.perHeadByPkg);
        const fixedPrice = _toNullableNumber(rawAddon.fixed_price);

        let startingPrice = fixedPrice;
        if (startingPrice === null && priceFrom && linkedSvc) {
          const firstPkg = pkgs[0];
          if (firstPkg && firstPkg[1] !== null && firstPkg[1] !== undefined) {
            startingPrice = firstPkg[1];
          } else if (perHead !== null) {
            startingPrice = perHead;
          }
        }

        const allowQty = Boolean(
          rawAddon.allow_qty !== undefined ? rawAddon.allow_qty : rawAddon.qty,
        );

        addonCatalog.push({
          id: addonId,
          service_id: serviceId,
          serviceId,
          name: rawAddon.name ? String(rawAddon.name) : svc.name,
          description:
            rawAddon.description !== undefined && rawAddon.description !== null
              ? String(rawAddon.description)
              : "",
          desc:
            ADDON_UI_DESCRIPTIONS[serviceId] ||
            (rawAddon.desc ? String(rawAddon.desc) : "") ||
            (rawAddon.description ? String(rawAddon.description) : ""),
          price_from: priceFrom,
          priceFrom,
          fixed_price: fixedPrice,
          allow_qty: allowQty,
          qty: allowQty,
          is_active: true,
          pkgs,
          perHead,
          perHeadByPkg,
          price: startingPrice,
        });
      });
    });

    data.guidelines.forEach((g) => {
      if (
        !g ||
        typeof g !== "object" ||
        !g.heading ||
        !Array.isArray(g.items)
      ) {
        return;
      }
      guidelines.push({
        id: g.id ? String(g.id) : undefined,
        heading: String(g.heading),
        items: g.items.map((item) => String(item)),
        sort_order: Number.isFinite(Number(g.sort_order))
          ? Number(g.sort_order)
          : 0,
      });
    });

    _catalogState = { loaded: true, ok: true, reason: null };
    return { ok: true, services: SERVICES, addons: addonCatalog, guidelines };
  })();

  return _catalogLoadPromise;
}

// =====================================================
// SHARED AVAILABILITY SOURCE — see loadAvailability() in SECTION A below.
// Both the homepage availability calendar (availability.js) and the
// per-service booking calendar (reservation2.js) read the SAME AVAIL object,
// so there is exactly one place that defines "unavailable" and both calendars
// always agree. Do not maintain a second availability list anywhere else.
// =====================================================
// =====================================================
// SHARED CONSTANTS + UI HELPERS (front-end — NOT part of the backend area)
// =====================================================
const PDR_STATUS = { PENDING: "Pending", CONFIRMED: "Confirmed" };

// Escapes text before it is placed into innerHTML (customer-typed names etc.).
function pdrEscape(v) {
  return String(v == null ? "" : v).replace(
    /[&<>"']/g,
    (c) =>
      ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" })[
        c
      ],
  );
}

// =====================================================
// SHARED LOCAL-DATE HELPERS (Asia/Manila — the business's timezone)
// Used by the booking calendars (reservation2.js), the homepage
// availability calendar (availability.js) and every re-validation before
// submit. Always compare the plain "YYYY-MM-DD" LOCAL date strings — never
// Date objects or UTC values — so the calendar never disables the wrong day.
// =====================================================

// Today's date in the Philippines as "YYYY-MM-DD". Calculated on every call
// so it is never hard-coded and stays correct after midnight.
function getTodayLocalDate() {
  try {
    return new Intl.DateTimeFormat("en-CA", {
      timeZone: "Asia/Manila",
      year: "numeric",
      month: "2-digit",
      day: "2-digit",
    }).format(new Date());
  } catch (e) {
    // very old browsers: fall back to the device's local date
    const t = new Date();
    return `${t.getFullYear()}-${String(t.getMonth() + 1).padStart(2, "0")}-${String(t.getDate()).padStart(2, "0")}`;
  }
}

// True when the given "YYYY-MM-DD" date is before today (Asia/Manila).
function isPastDate(dateStr) {
  return (
    typeof dateStr === "string" &&
    /^\d{4}-\d{2}-\d{2}$/.test(dateStr) &&
    dateStr < getTodayLocalDate()
  );
}

// =====================================================
// BOOKING WINDOW (T-40) — must match api/capacity.php.
// The server is the authority; these helpers only stop the customer from
// picking a date the server would reject.
// =====================================================
const PDR_MIN_LEAD_DAYS = 3;
const PDR_MAX_HORIZON_DAYS = 365;

function shiftLocalDate(dateStr, days) {
  const parts = String(dateStr || "").split("-").map(Number);
  if (parts.length !== 3 || parts.some((n) => !Number.isFinite(n))) return "";
  const d = new Date(Date.UTC(parts[0], parts[1] - 1, parts[2]));
  d.setUTCDate(d.getUTCDate() + days);
  return d.toISOString().slice(0, 10);
}

// null when bookable, otherwise "TOO_SOON" | "TOO_FAR" | "INVALID".
function bookingWindowError(dateStr) {
  if (typeof dateStr !== "string" || !/^\d{4}-\d{2}-\d{2}$/.test(dateStr)) {
    return "INVALID";
  }
  const today = getTodayLocalDate();
  if (dateStr < shiftLocalDate(today, PDR_MIN_LEAD_DAYS)) return "TOO_SOON";
  if (dateStr > shiftLocalDate(today, PDR_MAX_HORIZON_DAYS)) return "TOO_FAR";
  return null;
}

function isOutsideBookingWindow(dateStr) {
  return bookingWindowError(dateStr) !== null;
}

// =====================================================
// EVENT TIME SLOTS — PLACEHOLDER, PENDING STAFF CONFIRMATION.
// The business has not yet published its per-event time slots. These hourly
// slots are derived ONLY from the opening hours already shown on the homepage
// (8:00 AM – 10:00 PM). Staff must review/replace this list before launch —
// the booking form labels them as a placeholder schedule.
// =====================================================
const EVENT_TIME_SLOTS = [
  "08:00",
  "09:00",
  "10:00",
  "11:00",
  "12:00",
  "13:00",
  "14:00",
  "15:00",
  "16:00",
  "17:00",
  "18:00",
  "19:00",
  "20:00",
  "21:00",
];

// "09:00" -> "9:00 AM" for display everywhere (review, status, admin, payment, e-mail).
function fmtEventTime(t) {
  if (t === undefined || t === null || String(t).trim() === "") return "";
  const m = /^(\d{1,2}):(\d{2})/.exec(String(t).trim());
  if (!m) return String(t); // already formatted (e.g. "9:00 AM")
  let h = parseInt(m[1], 10);
  const ap = h >= 12 ? "PM" : "AM";
  h = h % 12;
  if (h === 0) h = 12;
  return `${h}:${m[2]} ${ap}`;
}

// Is this time still offered for the given date?
// With a service name (the normal path) it applies the PER-SERVICE rule: the
// slot is blocked only if that same service already holds it that day.
// Without one it falls back to "is this a known slot at all", for legacy
// callers that predate service-aware availability.
function isTimeAvailable(dateStr, timeStr, serviceName) {
  if (EVENT_TIME_SLOTS.indexOf(timeStr) === -1) return false;
  if (!serviceName) return true;
  return !isTimeTakenFor(dateStr, timeStr, serviceName);
}

/* ---------------- ADDITIONAL SERVICES HELPERS ---------------- */

// Every choice the inline picker shows for an add-on (usually the linked
// service's packages; Dine-In uses its Menu A / Menu B packages).
function addonOptions(a) {
  if (!a) return [];
  if (a.pkgs && a.pkgs.length) return a.pkgs;
  if (a.perHead !== null && a.perHead !== undefined && a.perHead > 0) {
    return [[a.name, a.perHead, null, null, null]];
  }
  return [[a.name, a.price ?? null, null, null, null]];
}

// The per-unit price of ONE option: per-head-by-package services use the
// package's rate, plain per-head services use the service rate, everything
// else uses the package's flat price. Preserves NULL when a price is unresolved
// (never converts NULL into 0).
function addonOptionRate(a, pkg) {
  if (!a) return null;
  if (a.perHeadByPkg) {
    return pkg && pkg[1] !== null && pkg[1] !== undefined
      ? _toNullableNumber(pkg[1])
      : null;
  }
  if (a.perHead !== null && a.perHead !== undefined) {
    return _toNullableNumber(a.perHead);
  }
  if (pkg && pkg[1] !== null && pkg[1] !== undefined) {
    return _toNullableNumber(pkg[1]);
  }
  if (a.price !== null && a.price !== undefined) {
    return _toNullableNumber(a.price);
  }
  return null;
}

// The ONE place that decides which services may be offered as add-ons:
// every add-on EXCEPT the service currently being booked (main service).
function getAdditionalServices(currentServiceId) {
  return addonCatalog.filter((a) => a.serviceId !== currentServiceId);
}

/* ############################################################################
   ##                          API CLIENT AREA                               ##
   ##                                                                        ##
   ##  Every function here calls the real PHP API. There is no localStorage  ##
   ##  prototype anymore (T-27): reservations live only in the database, so  ##
   ##  a booking made on a phone is visible on any other device.             ##
   ############################################################################ */

const API_BASE_URL = window.location.pathname.includes("/customer")
  ? "../api"
  : window.location.pathname.includes("/admin")
    ? "../api"
    : "api";

/* ===========================================================================
   SECTION A — CUSTOMER-FACING API CALLS
   Every function returns a Promise. "ok:false" results carry a `reason` code.
   =========================================================================== */

/* ---- Availability (shared by both calendars and the time picker) ----------
   AVAIL holds the CURRENT month's picture: which dates are fully taken and
   which services already hold which time slots on each date. Every surface
   reads this one object, so the landing page, the booking calendar and the
   time picker can never disagree. Rules live in api/capacity.php. */
let AVAIL = {
  dates: [],
  times: {},
  meta: {},
  rules: null,
  year: null,
  month: null,
};

// Fetch one month's availability into AVAIL. Safe to call repeatedly; a
// failed fetch keeps the previous data rather than blanking the calendar.
async function loadAvailability(year, month) {
  try {
    const res = await fetch(
      `${API_BASE_URL}/availability.php?year=${year}&month=${month}`,
    );
    const data = await res.json();
    if (!data || !Array.isArray(data.dates)) return AVAIL;
    AVAIL = {
      dates: data.dates || [],
      times: data.times || {},
      meta: data.meta || {},
      rules: data.rules || null,
      year: year,
      month: month,
    };
  } catch (e) {
    /* offline / API down — keep whatever we already have */
  }
  return AVAIL;
}

// Is this date fully taken? (venue-exclusive OR at the per-day service cap)
function isDateTaken(dateStr) {
  return AVAIL.dates.indexOf(dateStr) !== -1;
}

// Is this time blocked ON THIS DATE for THIS SERVICE? (per-service rule:
// another service sharing the same time does NOT block it)
function isTimeTakenFor(dateStr, timeStr, serviceName) {
  if (!dateStr || !timeStr || !serviceName) return false;
  const byTime = AVAIL.times[dateStr];
  if (!byTime || !byTime[timeStr]) return false;
  return byTime[timeStr].indexOf(serviceName) !== -1;
}

// Time slots offered to the customer, each flagged available for their service.
// Used to render the Event Time select with taken slots disabled.
function timeSlotState(dateStr, serviceName) {
  const byTime = (dateStr && AVAIL.times[dateStr]) || {};
  return EVENT_TIME_SLOTS.map(function (t) {
    return { time: t, taken: (byTime[t] || []).indexOf(serviceName) !== -1 };
  });
}

async function createReservation(data) {
  try {
    const res = await fetch(`${API_BASE_URL}/submit_reservation.php`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(data),
    });
    const text = await res.text();
    let json;
    try {
      json = JSON.parse(text);
    } catch (err) {
      json = {
        ok: false,
        reason:
          res.status === 409
            ? "DATE_TAKEN"
            : res.status === 400
              ? "INVALID"
              : "SERVER_ERROR",
      };
    }
    if (!json.ok && !json.reason) {
      json.reason =
        res.status === 409
          ? "DATE_TAKEN"
          : res.status === 400
            ? "INVALID"
            : "SERVER_ERROR";
    }
    return json;
  } catch (e) {
    return { ok: false, reason: "NETWORK" };
  }
}

async function findReservation(reservationId, bookingPin) {
  try {
    const res = await fetch(`${API_BASE_URL}/lookup_reservation.php`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ reservationId, bookingPin }),
    });
    return await res.json();
  } catch (e) {
    return { ok: false, reason: "NETWORK" };
  }
}

async function getReservationStatus(reservationId, bookingPin) {
  try {
    const res = await fetch(`${API_BASE_URL}/status_reservation.php`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ reservationId, bookingPin }),
    });
    return await res.json();
  } catch (e) {
    return { ok: false, reason: "NETWORK" };
  }
}

/* End of services.js */
