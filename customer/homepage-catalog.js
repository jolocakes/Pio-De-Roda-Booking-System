/* Homepage service-card catalog sync for nav.html (Pio De Roda).
 *
 * The 8 service cards on the homepage are static HTML marked with
 * data-service-id (catering-menu, venue, dinein, noncoffee, coffee, pizza,
 * intimate, equipment). This script syncs what customers see with the live
 * catalog served by ../api/services.php — the same database the admin page
 * edits — after services.js has loaded (nav.html loads it first).
 *
 * What it syncs on every load:
 *   - h3        <- services.name        (database service name)
 *   - <p>       <- services.description (database description)
 *   - .price    <- services.base_price  "Starting at ₱X [unit]"  (see CARD_UNITS)
 *   - cards whose service is NOT in the active catalog are hidden, so
 *     deactivating a service in the admin/DB removes its homepage card.
 *
 * Deliberate design points:
 *   - The starting price amount comes from services.base_price, the same
 *     "Starting at" figure the approved catalog publishes; the unit suffix
 *     (" / head", " / package", " / item") is presentation-only and lives
 *     here, NOT in the database. Admin edits must therefore keep base_price
 *     in sync with the displayed price (the admin page's display fields).
 *   - If the catalog API is unreachable or returns an unusable payload, the
 *     script does nothing: the static card text remains as the fallback.
 *   - loadCatalogFromApi() caches its result, so nothing on this page ever
 *     fetches the catalog twice.
 */
(function () {
  /* Unit suffix shown after each card's starting price. Amounts ALWAYS come
     from the database; these strings only describe how the service is
     priced. No entry means a bare amount (Venue Rentals). */
  const CARD_UNITS = {
    "catering-menu": " / head",
    venue: "",
    dinein: " / head",
    noncoffee: " / package",
    coffee: " / package",
    pizza: " / package",
    intimate: " / package",
    equipment: " / item",
  };

  function fmtPeso(amount) {
    return `₱${amount.toLocaleString("en-US")}`;
  }

  /* The "Starting at" amount for one service. base_price is the established
     semantic; the per-head rate and the cheapest bookable package are only
     fallbacks for services whose base_price is somehow unset. */
  function startingPrice(svc) {
    if (svc.basePrice !== null && svc.basePrice !== undefined) {
      return svc.basePrice;
    }
    if (svc.perHead !== null && svc.perHead !== undefined) {
      return svc.perHead;
    }
    const pkgPrices = Array.isArray(svc.packages)
      ? svc.packages
          .map((p) => p[1])
          .filter((v) => typeof v === "number" && Number.isFinite(v))
      : [];
    if (pkgPrices.length) return Math.min(...pkgPrices);
    return null;
  }

  function updateCard(card, svc) {
    const title = card.querySelector("h3");
    const body = card.querySelector(".card-body");
    const desc = body ? body.querySelector("p:not(.price)") : null;
    const price = body ? body.querySelector("p.price") : null;

    if (title && svc.name) title.textContent = svc.name;
    if (desc && svc.description) desc.textContent = svc.description;
    if (price) {
      const amount = startingPrice(svc);
      if (amount !== null) {
        const unit = Object.prototype.hasOwnProperty.call(CARD_UNITS, svc.id)
          ? CARD_UNITS[svc.id]
          : "";
        price.textContent = `Starting at ${fmtPeso(amount)}${unit}`;
      }
    }
  }

  function hideCard(card) {
    card.hidden = true;
    card.style.display = "none"; // wins over any class-based display rule
  }

  async function syncHomepageCards() {
    if (typeof loadCatalogFromApi !== "function") {
      return; // services.js missing — static cards remain untouched
    }

    let result;
    try {
      result = await loadCatalogFromApi();
    } catch (err) {
      return; // API failure — static fallback stays
    }
    if (!result || result.ok !== true || !Array.isArray(result.services)) {
      return; // unusable catalog — static fallback stays
    }

    const activeIds = new Set(result.services.map((s) => s.id));
    const cards = document.querySelectorAll(
      "#servicesGrid .service-card[data-service-id]",
    );

    cards.forEach((card) => {
      const id = card.getAttribute("data-service-id");
      if (!activeIds.has(id)) {
        // Service is deactivated (or unknown) — remove the card from view.
        hideCard(card);
        return;
      }
      const svc = result.services.find((s) => s.id === id);
      if (svc) updateCard(card, svc);
    });
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", syncHomepageCards);
  } else {
    syncHomepageCards();
  }
})();
