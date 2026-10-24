/* Shared service catalog, add-ons, and guidelines for Pio De Roda reservation pages */
/* ayusin mo pa ung "add to cart" feature mo, kung ilalagay mo rin ba ung mismong services sa gilid" */
const SERVICES = [
  {
    id: "catering-menu",
    name: "Catering with Menu",
    rates: [
      ["2 Main Courses", "₱1,000 per head"],
      ["3 Main Courses", "₱1,100 per head"],
      ["4 Main Courses", "₱1,300 per head"],
    ],
    price: "₱1,000 per head",
    /* `perHeadByPkg:true` means each package's price is a PER-HEAD rate and the
       booking total = rate × number of guests (2 choices ₱1,000/head,
       3 ₱1,100/head, 4 ₱1,300/head). The customer picks the main courses in
       the booking panel (minimum 2); the matching package is then selected
       automatically from the count — see syncMainCoursePackage in
       reservation2.js. */
    perHeadByPkg: true,
    inclusions: [
      "Tables and chairs",
      "Food and beverages",
      "Basic decorations",
      "Serving staff",
      "Venue setup",
      "Cleanup",
    ],
    /* Fixed menu included in EVERY catering package (display-only — no Select
       button, unlike Dine-In's `menu`). `mainCourses` below are the only
       dishes the customer actually chooses. */
    menu: [
      {
        category: "Included Dishes",
        items: [
          {
            name: "Included Dishes",
            price: null,
            dishes: [
              "Chips for Pica-Pica",
              "Steamed Pandan Rice",
              "Soup",
              "Vegetable Recipe",
              "Beverages",
              "Dessert",
              "Pancit/Pasta Recipe",
            ],
          },
        ],
      },
    ],
    mainCourses: [
      "Chicken Recipe",
      "Beef Recipe",
      "Pork Recipe",
      "Fish or Seafood Recipe",
    ],
    packages: [
      ["2 Main Courses", 1000],
      ["3 Main Courses", 1100],
      ["4 Main Courses", 1300],
    ],
    maxCap: 150,
    /* Example of SERVICE-SPECIFIC NOTES (see point 1 below). Placeholder — replace with real notes. */
    notes: [
      "A minimum of 50 pax applies for this package.",
      "Menu tasting may be arranged upon request, subject to availability.",
    ],
  },

  {
    id: "venue",
    name: "Venue Rentals",
    rates: [
      ["Extension Hall (30 to 35 persons)", "₱15,000"],
      ["Extension Hall (36 - 50 Persons)", "₱20,000"],
      ["Main Hall (50 - 65 Persons)", "₱25,000"],
      ["Main Hall (66 - 100 Persons)", "₱30,000"],
    ],
    price: "₱15,000",
    inclusions: [
      "Use of air-conditioned venue for 4 hours",
      "Basic sound system set-up (player, speaker and microphone)",
      "Basic Catering Set-up",
      "Decorative stage and throne",
      "Elegantly skirted buffet table",
      "Tables for cake and gifts",
      "Round table with floor length table cover",
      "Table numbers & centerpiece for each table",
      "Tiffany Chairs with thematic ribbon",
      "Complete silverware, glassware and flatware",
      "Professionally trained and uniformed staff",
      "Provision for parking space",
    ],
    packages: [
      ["Extension Hall (30 - 35 Persons)", 15000],
      ["Extension Hall (36 - 50 Persons)", 20000],
      ["Main Hall (50 - 65 Persons)", 25000],
      ["Main Hall (66 - 100 Persons)", 30000],
    ],
    maxCap: 120,
  },

  {
    id: "dinein",
    name: "Dine-In Menu",
    rates: [
      ["Per Head", "₱600"],
      ["10 Persons", "₱6,000"],
      ["20 Persons", "₱12,000"],
      ["30 Persons", "₱18,000"],
    ],
    price: "600",
    perHead: 600,
    inclusions: [
      "Dine-in at Al-Fresco Restaurant Area for 1-1.5 hours",
      "Complete silverware, glassware,and flatware",
      "Professionally trained and uniformed staff",
      "Provision for parking space",
    ],
    /* See point 3 below: this is the only service with a `menu` field.
       Its `packages` array is auto-generated from `menu` further down this file,
       so the booking panel's package list always matches this table.
       `perHead:600` is the per-guest rate from the Rates table above: when a
       set menu (Menu A / Menu B) is selected, the total adds ₱600 × guests. */
    menu: [
      {
        category: "Menu A",
        items: [
          {
            name: "Menu A",
            price: null,
            dishes: [
              "Plain Rice",
              "Crab & Corn Soup",
              "Creamy Mixed Veggies w/ Quail Eggs",
              "Garlic Chicken w/ Gravy Sauce",
              "Pork w/ Mushroom Sauce",
              "Lemon Iced Tea",
              "Peach Mango Graham",
            ],
          },
        ],
      },
      {
        category: "Menu B",
        items: [
          {
            name: "Menu B",
            price: null,
            dishes: [
              "Plain Rice",
              "Creamy Mushroom Soup",
              "Special Chopseuy w/ Quail Eggs",
              "Chicken Cordon Bleu w/ White Sauce",
              "Pork w/ Oyster Sauce & Brocolli",
              "Blue Lemonade",
              "Frozen Cookies & Cream",
            ],
          },
        ],
      },
    ],
    packages: [],
    maxCap: null,
  },

  {
    id: "noncoffee",
    name: "Non-Coffee Booth Package",
    rates: [
      ["Sparkling Refreshers", "₱85 per cup"],
      ["Creamy Milk Tea", "₱85 per cup"],
    ],
    price: "₱85 per cup",
    inclusions: ["Booth setup", "Assigned staff", "Assorted drinks menu"],
    packages: [
      ["Sparkling Refreshers (50 Cups)", 4250],
      ["Sparkling Refreshers (100 Cups)", 8500],
      ["Creamy Milk Tea (50 Cups)", 4250],
      ["Creamy Milk Tea (100 Cups)", 8500],
    ],
    maxCap: 80,
    inclusionsTable: {
      headers: ["Sparkling Refreshers", "Creamy Milk Tea"],
      rows: [
        ["16 oz Iced Drinks", "16 oz Iced Drinks"],
        [
          "5 Flavors: Strawberry, Green Apple, Peach, Passion Fruit, Blue Raspberry",
          "5 Flavors: Matcha, Cookies & Cream, Dark Chocolate, Salted Caramel, Okinawa",
        ],
      ],
    },
    notes: [
      "Transportation Fee applies outside Indang, Cavite",
      "50% Down Payment to book your event",
      "50% Full Payment must be made 1 day before the event",
      "Additional charge of 500.00 per hour for every succeeding hour",
      "Ensure that there will be a nearby power outlet",
      "Corkage or electricity fees shall be shouldered by the client",
    ],
  },

  /* gusto q maglagay nung inclusions mismo ung may check check pero ask mo pa */
  {
    id: "coffee",
    name: "Coffee Booth Package",
    rates: [
      ["Intimate", "₱125 per cup"],
      ["Classic", "₱145 per cup"],
      ["Ultimate", "₱165 per cup"],
    ],
    price: "₱145 per cup",
    inclusions: [
      "Premium Quality Coffee Beans",
      "Premium Sauces and Syrups",
      "Quality Espresso Machine and Grinder",
      "Complete Coffee Booth Set-up",
      "3-4 Hours of Service",
      "2-3 Experienced and Uniformed Barista",
      "Claiming Stub for the guests (optional)",
      "Free Transportation for 100 cups and above within Indang, Trece, Mendez or Tagaytay",
    ],
    packages: [
      ["Intimate Package (50 Cups)", 6250],
      ["Intimate Package (100 Cups)", 12500],
      ["Classic Package (50 Cups)", 7250],
      ["Classic Package (100 Cups)", 14500],
      ["Ultimate Package (50 Cups)", 8250],
      ["Ultimate Package (100 Cups)", 16500],
    ],
    maxCap: 80,
    inclusionsTable: {
      headers: ["Intimate", "Classic", "Ultimate"],
      rows: [
        ["12 oz Hot Drinks", "12 oz Hot Drinks", "12 oz Hot Drinks"],
        ["12 oz Iced Drinks", "12 oz Iced Drinks", "12 oz Iced Drinks"],
        [
          "4 Coffee Flavors: Americano, Caffe Latte, Cappuccino, Caramel Macchiato",
          "5 Coffee Flavors: Americano, Caffe Latte, Cappuccino, Caramel Macchiato, Vanilla Latte",
          "7 Coffee Flavors: Americano, Caffe Latte, Cappuccino, Caramel Macchiato, Vanilla Latte, Spanish Latte, Mocha, 1 Non-Coffee Flavor: Chocolate",
        ],
      ],
    },
    notes: [
      "Transportation Fee applies outside Indang, Cavite",
      "50% Down Payment to book your event",
      "50% Full Payment must be made 1 day before the event",
      "Additional charge of 500.00 per hour for every succeeding hour",
      "Ensure that there will be a nearby power outlet",
      "Corkage or electricity fees shall be shouldered by the client",
    ],
  },

  {
    id: "pizza",
    name: "Pizza Booth",
    rates: [
      ["15 Pizza", "₱7,500"],
      ["20 Pizza", "₱10,000"],
      ["25 Pizza", "₱12,000"],
      ["30 Pizza", "₱14,500"],
    ],
    price: "₱7,500",
    inclusions: [
      "Freshly Made Pizza Dough",
      "Premium Quality Mozarella Cheese, Sauces, & Ingredients",
      "Pugon-Quality Pizza Oven for Authentic Wood-Fired Flavor",
      "Complete Pizza Booth Set-up",
      "2-3 hours of Service",
      "2-3 Experienced and Uniformed Pizza Masters",
      "Claiming Stub for Guests (optional)",
      "Free Transportation within Indang, Cavite",
      "Minimal Transportation for Trece, Amadeo, Mendez, Tagaytay, Silang, and Alfonso",
    ],
    packages: [
      ["Intimate Package (50 Persons) with 15 Pizza", 7500],
      ["Intimate Package (50 Persons) with 20 Pizza", 10000],
      ["Classic Package (80 Persons) with 25 Pizza", 12000],
      ["Classic Package (80 Persons) with 30 Pizza", 14500],
    ],
    maxCap: 80,
    notes: [
      "50% Down Payment to book your event",
      "50% Full Payment must be made 1 day before the event",
      "Additional charge of ₱500 per hour for every succeeding hour",
      "Ensure that the venue allows LPG tank for the pugon pizza oven",
      "In such case, corkage or electricity fees shall be shouldered by the client",
    ],
  },

  {
    id: "intimate",
    name: "Intimate Celebration Package",
    rates: [
      ["Intimate", "₱4,000"],
      ["Ultimate", "₱14,000"],
      ["Classic", "₱8,000"],
    ],
    price: "₱4,000",
    packages: [
      ["Intimate Package", 4000],
      ["Ultimate Package", 14000],
      ["Classic Package", 8000],
    ],
    maxCap: 20,
    inclusionsTable: {
      headers: ["Intimate", "Ultimate", "Classic"],
      rows: [
        ["Coffee Shop", "Extension Hall with 25-35 Persons", "Kamayan 1"],
        ["6-8 Persons", "Air-Conditioned", "15-20 Persons"],
        ["Air-Conditioned", "Basic Sound System", "Al-Fresco Area"],
        [
          "2 Hours Exclusive Events Place",
          "2 Hours Exclusive Events Place",
          "2 Hours Exclusive Events Place",
        ],
        [
          "Consumable Drinks and Food",
          "Consumable Drinks and Food",
          "Consumable Drinks and Food",
        ],
      ],
    },
    notes: [
      "For intimate celebrations such as Bridal Shower, Gender Reveal, Baby SHower, and small birthday parties only.",
      "Provision of one (1) rectangular table with table cover is included for buffet set-up.",
      "Outside food and drinks are highly discouraged. Food corkage will be implmented.",
      "Booking will be confrimed upon receipt of a 50% downpayment.",
      "Additional charges apply for themed set-ups. Guests may bring their own decor, but only limited decorations are allowed.",
      "Use of nails, glue, double sided tape, confetti, or any materials that may damage the property is strictly prohibited.",
      "Standard party duration is 2 hours. Extension is subject to availability and additional charges.",
      "Use of venue equipment (e.g. sound system) must be requested in advance.",
    ],
  },

  {
    id: "equipment",
    name: "Equipment Rental",
    rates: [["Per Item", "₱300"]],
    price: "300",
    inclusions: ["Delivery within service area", "Basic setup"],
    packages: [["Standard Rate", 300]],
    maxCap: null,
  },
];

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
  equipment: "equipment.png",
};

/* Dine-In Menu: auto-generate its selectable packages from the grouped `menu`
   above, so the Rates/Menu table and the "Selected Food / Package" list in the
   booking panel always stay in sync with a single source of truth. */
(function () {
  const dinein = SERVICES.find((s) => s.id === "dinein");
  dinein.packages = dinein.menu.flatMap((cat) =>
    cat.items.map((item) => [item.name, item.price]),
  );
})();

// =====================================================
// SHARED AVAILABILITY SOURCE
// Both the homepage availability calendar (availability.js) and the
// per-service booking calendar (reservation2.js) call this SAME function,
// so there is exactly one place that defines "unavailable" and both
// calendars always agree. Do not maintain a second unavailableDates list
// anywhere else in the project.
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

// Is this time still offered for the given date? Time slots are currently a
// static placeholder list, so this only checks that the time is a known slot.
// PRODUCTION: the backend must re-check date+time conflicts before accepting
// a reservation (see BACKEND note in createReservation below).
function isTimeAvailable(dateStr, timeStr) {
  return EVENT_TIME_SLOTS.includes(timeStr);
}

/* ---------------- DATA (placeholder — replace with real info) ---------------- */

// =====================================================
// ADDITIONAL SERVICES ("Add-ons" cart)
// Services that already have their own page/packages (Coffee Booth, Pizza
// Booth, Catering with Menu, Venue Rentals, Non-Coffee Booth, Intimate
// Celebration) reuse that service's own FIRST package price via `priceFrom`
// below, instead of a second, separately-invented number — so there is only
// ONE place to update a price. Services with no single flat price yet
// (Dine-In Menu is priced per menu item; Equipment Rental is priced per
// item) are left as `price:null` and shown as "Price to be confirmed"
// rather than guessing a number.
// The plain physical add-ons (Tables, Chairs, Tents, Food Warmers, Sound
// System, Decorations) were prototype placeholder prices, NOT confirmed
// Pio De Roda prices — they are now `price:null` too. Replace null with an
// actual number (e.g. `price:150`) once the real rate is confirmed.
// =====================================================
const addonCatalog = [
  // `serviceId` links each add-on to the matching entry in SERVICES (same id
  // used by SERVICE_ID on each service page). The booking panel hides the
  // add-on whose serviceId equals the service currently being booked.
  {
    id: "catering-menu-addon",
    serviceId: "catering-menu",
    name: "Catering with Menu",
    desc: "Add full catering with menu to your event.",
    priceFrom: "catering-menu",
    qty: false,
  },
  {
    id: "venue-addon",
    serviceId: "venue",
    name: "Venue Rentals",
    desc: "Add exclusive use of an additional venue/area.",
    priceFrom: "venue",
    qty: false,
  },
  {
    id: "dinein-addon",
    serviceId: "dinein",
    name: "Dine-In Menu",
    desc: "Add dine-in menu service. See the Dine-In Menu page to choose specific items.",
    price: null,
    qty: false,
  },
  {
    id: "noncoffee-booth-addon",
    serviceId: "noncoffee",
    name: "Non-Coffee Booth Package",
    desc: "Add a non-coffee drinks booth to your event.",
    priceFrom: "noncoffee",
    qty: false,
  },
  {
    id: "coffee-booth-addon",
    serviceId: "coffee",
    name: "Coffee Booth Package",
    desc: "Add a coffee booth to your event.",
    priceFrom: "coffee",
    qty: false,
  },
  {
    id: "pizza-booth-addon",
    serviceId: "pizza",
    name: "Pizza Booth",
    desc: "Add a live pizza booth to your event.",
    priceFrom: "pizza",
    qty: false,
  },
  {
    id: "intimate-addon",
    serviceId: "intimate",
    name: "Intimate Celebration Package",
    desc: "Add intimate-celebration elements to your event.",
    priceFrom: "intimate",
    qty: false,
  },
  {
    id: "equipment-addon",
    serviceId: "equipment",
    name: "Equipment Rental",
    desc: "Add extra equipment rental. Priced per item.",
    price: null,
    qty: false,
  },
];

// Fill in each `priceFrom` add-on's price from that service's own first
// package in SERVICES, so the two never go out of sync.
addonCatalog.forEach((a) => {
  if (a.priceFrom) {
    const svc = SERVICES.find((s) => s.id === a.priceFrom);
    a.price =
      svc && svc.packages && svc.packages[0] ? svc.packages[0][1] : null;
  }
});

// The ONE place that decides which services may be offered as add-ons:
// every add-on EXCEPT the service currently being booked (main service).
function getAdditionalServices(currentServiceId) {
  return addonCatalog.filter((a) => a.serviceId !== currentServiceId);
}

const guidelines = [
  {
    heading: "Booking & Reservation",
    items: [
      "In order to reserve your chosen date, time and venue, we are requiring a non-refundable deposit of ₱2,000.00 which will be deducted from your final bill. No booking or reservation will be considered confirmed until a deposit is made.",
      "Client should finalize the details of their event at least 2 weeks before the event and make a partial payment of 50% of their total bill.",
      "A final confirmation of the total number of guests shall be made no later than 5 working days prior to the date of the event. This confirmed number will be the minimum number which will be charged. Additional guests shall be served and charged accordingly on a per plate / per head basis.",
      "The remaining 50% shall be paid in full 1 day before the day of the event.",
      "Additional and other incidental charges shall be settled on the day of the event, right after the said event.",
      "Only the reserved venue shall be used for your event. Additional charges shall be imposed for the use of other venues.",
    ],
  },
  {
    heading: "Cancellation, Changes to the Reservation",
    items: [
      "All changes to your reservation must be made no later than 5 working days prior to the date of the event. All changes will be considered; however, we hold the right not to accept the change if prior preparation is already done.",
    ],
  },
  {
    heading: "Food, Catering Set-Up and Others",
    items: [
      "All allocations are based on experience in the food industry. We usually cater for more than necessary, but we cannot account for over average eaters.",
      "Client may bring their own food to be served during their event provided prior arrangements are done. Though, we will not take responsibility for any other food and drinks not provided by Pio de Roda Café on the day of the event.",
      "Pio de Roda Café will assist with any additional items during the event. However, we are not responsible for any items provided by the client for the event such as but not limited to flowers, cakes, souvenirs, etc.",
      "Pio de Roda Café will serve you with our basic catering set-up which includes celebrant's stage and throne, buffet table, cake and gift tables, tables and chairs, complete silverware, flatware and dinnerware, basic sound system, and trained waiters. However, should you require more intricate details it will be under our event styling arrangements which will be on a separate transaction.",
      "Outside providers or suppliers such as lights and sound system, projectors, LED screens, food carts, etc. shall have a corkage for the energy consumption.",
      "Pio de Roda Café is practicing a per head and plate basis, however, children below 7 years old are free of charge. Please refrain from using another plate. Saucers will be provided for your desserts and other food use.",
      "We are following the IATF regulation to limit the guests for every event. Senior Citizens and children are encouraged to take precautionary measures for their own safety. Please limit their exposure as well. Children shall be limited to a maximum of 5 kids only.",
      "Remind your guests to bring and wear their face masks and face shields at all times. And also bring their vaccination cards and present at the contact tracing booth.",
    ],
  },
];

/* ############################################################################
   ############################################################################
   ##                                                                        ##
   ##                     B A C K E N D   A R E A   —   START                ##
   ##                                                                        ##
   ##  Everything between this banner and the "BACKEND AREA — END" banner    ##
   ##  is the ONLY code that talks to where reservations are stored.         ##
   ##                                                                        ##
   ##  The rest of the website (booking pages, Booking Status page,          ##
   ##  homepage calendar, admin page) ONLY calls the async functions in      ##
   ##  Sections A and B below. It never reads localStorage directly.         ##
   ##                                                                        ##
   ##  WHEN THE BACKEND TEAM IS READY:                                       ##
   ##   1. Keep every function NAME, its PARAMETERS and its RETURN SHAPE.    ##
   ##   2. Replace the body of each function in Sections A and B with the    ##
   ##      fetch() call shown in its "REAL BACKEND" comment.                 ##
   ##   3. Delete Section C (the localStorage prototype) completely.         ##
   ##   4. Nothing outside this area needs to change.                        ##
   ##  Full request/response details: BACKEND_CONTRACT.md                    ##
   ##                                                                        ##
   ############################################################################
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

async function getUnavailableDates() {
  try {
    const res = await fetch(`${API_BASE_URL}/availability.php`);
    const data = await res.json();
    return data.dates || [];
  } catch (e) {
    return [];
  }
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

async function adminGetReservations() {
  try {
    const res = await fetch(`${API_BASE_URL}/admin/reservations.php`);
    return await res.json();
  } catch (e) {
    return [];
  }
}

async function adminUpdateReservationStatus(reservationId, newStatus) {
  try {
    const res = await fetch(`${API_BASE_URL}/admin/update_status.php`, {
      method: "PATCH",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ reservationId, status: newStatus }),
    });
    return await res.json();
  } catch (e) {
    return { ok: false, reason: "NETWORK" };
  }
}

/* ===========================================================================
   SECTION C — PROTOTYPE ONLY (browser localStorage pretending to be a server)
   DELETE THIS WHOLE SECTION when the real backend is connected.
   localStorage is per-device, so reservations made on a phone are NOT visible on
   a desktop here — that is exactly the limitation the real backend removes.
   Everything the real server must do is marked  "SERVER JOB:".
   =========================================================================== */
const BASE_UNAVAILABLE_DATES = []; // real backend: dates the restaurant blocked manually (stored server-side)

const _MOCK = {
  KEY: "pdr_reservations", // the "database table"
  GUARD_KEY: "pdr_verify_guard", // stand-in for server-side rate limiting
  MAX_FAILS: 5,
  LOCK_SECONDS: 120,

  delay(ms = 150) {
    return new Promise((res) => setTimeout(res, ms));
  }, // pretend network time

  read() {
    try {
      const list = JSON.parse(localStorage.getItem(this.KEY) || "[]");
      return Array.isArray(list)
        ? list.filter((r) => r && r.reservationId).map((r) => this.normalize(r))
        : [];
    } catch (e) {
      return [];
    }
  },
  write(list) {
    try {
      localStorage.setItem(this.KEY, JSON.stringify(list));
      return true;
    } catch (e) {
      return false;
    }
  },
  normalize(r) {
    // tolerate older saved records
    const n = Object.assign({}, r);
    if (n.guests === undefined) n.guests = n.numberOfGuests;
    if (n.eventTime === undefined) n.eventTime = ""; // records saved before Event Time existed
    if (!n.contactPerson) n.contactPerson = n.customerName;
    if (!n.status) n.status = PDR_STATUS.PENDING;
    if (!Array.isArray(n.additionalServices)) n.additionalServices = [];
    return n;
  },
  takenDates() {
    return BASE_UNAVAILABLE_DATES.concat(this.read().map((r) => r.eventDate));
  },

  // SERVER JOB: unique ID, e.g. PDR-2026-0001 (one higher than the largest existing number this year).
  nextId(list) {
    const year = new Date().getFullYear();
    const re = new RegExp("^PDR-" + year + "-(\\d+)$");
    let max = 0;
    list.forEach((r) => {
      const m = re.exec(r.reservationId);
      if (m) max = Math.max(max, parseInt(m[1], 10));
    });
    return `PDR-${year}-${String(max + 1).padStart(4, "0")}`;
  },
  // SERVER JOB: random 6-digit PIN. (Real backend should store only a HASH of the PIN.)
  newPin() {
    let n;
    if (window.crypto && crypto.getRandomValues) {
      const a = new Uint32Array(1);
      crypto.getRandomValues(a);
      n = a[0] % 900000;
    } else n = Math.floor(Math.random() * 900000);
    return String(100000 + n);
  },

  // SERVER JOB: validate input, re-check the date, force status "Pending", add id/PIN/timestamp.
  // (Real backend should also RECOMPUTE estimatedTotal from its own price list, never trust the browser's.)
  create(data) {
    if (
      !data ||
      !data.customerName ||
      !data.eventDate ||
      !data.eventTime ||
      !data.mainService ||
      !data.selectedPackage
    )
      return { ok: false, reason: "INVALID" };
    const list = this.read();
    if (isPastDate(data.eventDate)) return { ok: false, reason: "INVALID" }; // past dates are never bookable
    if (!isTimeAvailable(data.eventDate, data.eventTime))
      return { ok: false, reason: "INVALID" };
    if (this.takenDates().includes(data.eventDate))
      return { ok: false, reason: "DATE_TAKEN" };
    const rec = {
      reservationId: this.nextId(list),
      bookingPin: this.newPin(),
      status: PDR_STATUS.PENDING,
      customerName: data.customerName,
      contactPerson: data.contactPerson || data.customerName,
      contactNumber: data.contactNumber,
      email: data.email,
      eventDate: data.eventDate,
      eventTime: data.eventTime,
      eventType: data.eventType,
      guests: data.guests,
      mainService: data.mainService,
      selectedPackage: data.selectedPackage,
      // Catering with Menu: the customer's main-course choices (minimum 2).
      ...(Array.isArray(data.mainCourses) && data.mainCourses.length
        ? { mainCourses: data.mainCourses }
        : {}),
      additionalServices: Array.isArray(data.additionalServices)
        ? data.additionalServices
        : [],
      estimatedTotal: data.estimatedTotal,
      submittedAt: new Date().toISOString(),
    };
    list.push(rec);
    if (!this.write(list)) return { ok: false, reason: "NETWORK" };
    return {
      ok: true,
      reservationId: rec.reservationId,
      bookingPin: rec.bookingPin,
      status: rec.status,
    };
  },

  // SERVER JOB: BOTH values must match; failures look identical; too many failures are throttled.
  verify(reservationId, bookingPin) {
    const wait = this.lockedSeconds();
    if (wait > 0)
      return {
        ok: false,
        reason: "TOO_MANY_ATTEMPTS",
        retryAfterSeconds: wait,
      };
    const want = String(reservationId || "")
      .trim()
      .toUpperCase();
    const rec = this.read().find(
      (r) => String(r.reservationId).toUpperCase() === want,
    );
    if (
      !rec ||
      !rec.bookingPin ||
      rec.bookingPin !== String(bookingPin || "")
    ) {
      this.registerFailure();
      return { ok: false, reason: "NOT_FOUND" };
    }
    this.clearFailures();
    return { ok: true, record: rec };
  },
  guard() {
    try {
      return JSON.parse(localStorage.getItem(this.GUARD_KEY) || "{}");
    } catch (e) {
      return {};
    }
  },
  lockedSeconds() {
    const g = this.guard();
    return g.lockedUntil && g.lockedUntil > Date.now()
      ? Math.ceil((g.lockedUntil - Date.now()) / 1000)
      : 0;
  },
  registerFailure() {
    const g = this.guard();
    const count =
      (g.lockedUntil && g.lockedUntil <= Date.now() ? 0 : g.count || 0) + 1;
    const next =
      count >= this.MAX_FAILS
        ? { count: 0, lockedUntil: Date.now() + this.LOCK_SECONDS * 1000 }
        : { count };
    try {
      localStorage.setItem(this.GUARD_KEY, JSON.stringify(next));
    } catch (e) {}
  },
  clearFailures() {
    try {
      localStorage.removeItem(this.GUARD_KEY);
    } catch (e) {}
  },

  // SERVER JOB: build what the customer is allowed to see. No PIN; phone/email masked.
  maskPhone(v) {
    const s = String(v || "");
    return s.length < 4
      ? "••••"
      : s.slice(0, 2) + "*".repeat(s.length - 3) + s.slice(-1);
  },
  maskEmail(v) {
    const s = String(v || "");
    const at = s.indexOf("@");
    return at < 1 ? (s ? "••••" : "") : s[0] + "***" + s.slice(at);
  },
  customerView(r) {
    return {
      reservationId: r.reservationId,
      status: r.status,
      submittedAt: r.submittedAt,
      customerName: r.customerName,
      contactPerson: r.contactPerson,
      contactNumberMasked: this.maskPhone(r.contactNumber),
      emailMasked: this.maskEmail(r.email),
      eventDate: r.eventDate,
      eventTime: r.eventTime || "",
      eventType: r.eventType,
      guests: r.guests,
      mainService: r.mainService,
      selectedPackage: r.selectedPackage,
      // Catering with Menu only: the chosen main courses (when the record has them)
      ...(Array.isArray(r.mainCourses) && r.mainCourses.length
        ? { mainCourses: r.mainCourses }
        : {}),
      additionalServices: r.additionalServices,
      estimatedTotal: r.estimatedTotal,
    };
  },
};

/* ############################################################################
   ##                     B A C K E N D   A R E A   —   END                  ##
   ############################################################################ */
