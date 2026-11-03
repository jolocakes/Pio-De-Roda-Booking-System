/* ==========================================================================
   PIO DE RODA HOSPITALITY RESERVATION & EVENT BOOKING SYSTEM
   Homepage Availability Calendar Component (customer/availability.js)
   ==========================================================================
   Availability data comes from loadAvailability() in services.js, which reads
   api/availability.php. A date grays out only when it is FULL:
     - the venue is already booked that day (venue is exclusive per date), or
     - the date already holds MAX_SERVICES_PER_DAY (2) active bookings.
   Partially-booked dates stay selectable — the booking page then disables the
   individual time slots that are already taken for the chosen service.
   ========================================================================== */

(function () {
  let calYear = new Date().getFullYear();
  let calMonth = new Date().getMonth();

  function daysInMonth(y, m) {
    return new Date(y, m + 1, 0).getDate();
  }

  function fmtDate(y, m, d) {
    return `${y}-${String(m + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
  }

  // Human hint for a partially-booked date, e.g. "1 of 2 slots used".
  function dayHint(ds) {
    const meta = (typeof AVAIL !== 'undefined' && AVAIL.meta) ? AVAIL.meta[ds] : null;
    if (!meta) return '';
    if (meta.hasVenue) return ' — venue booked, date unavailable';
    if (meta.count > 0) {
      const cap = (AVAIL.rules && AVAIL.rules.maxPerDay) || 2;
      return ` — ${meta.count} of ${cap} slots booked`;
    }
    return '';
  }

  function renderAvailabilityCalendar() {
    const container = document.getElementById('availabilityCalendar');
    if (!container) return;

    const first = new Date(calYear, calMonth, 1).getDay();
    const total = daysInMonth(calYear, calMonth);
    const monthName = new Date(calYear, calMonth, 1).toLocaleString('default', { month: 'long' });
    const today = getTodayLocalDate();

    let cells = `<div class="dow">Su</div><div class="dow">Mo</div><div class="dow">Tu</div><div class="dow">We</div><div class="dow">Th</div><div class="dow">Fr</div><div class="dow">Sa</div>`;
    for (let i = 0; i < first; i++) {
      cells += `<div class="day blank"></div>`;
    }
    for (let d = 1; d <= total; d++) {
      const ds = fmtDate(calYear, calMonth, d);
      const isToday = ds === today;
      if (ds < today) {
        cells += `<div class="day past" aria-disabled="true" aria-label="${ds} — past date">${d}</div>`;
        continue;
      }
      const unavail = isDateTaken(ds);
      const hint = dayHint(ds).trim(); // "" | "— venue booked..." | "— 1 of 2 slots booked"
      const label = `${ds} — ${unavail ? 'unavailable' : (isToday ? 'today, available' : 'available')}${hint ? ', ' + hint : ''}`;
      cells += `<div class="day ${unavail ? 'unavail' : 'avail'} ${isToday ? 'today' : ''}"
        ${hint ? `title="${hint}" ` : ''}aria-disabled="${unavail}" aria-label="${label}">${d}</div>`;
    }

    container.innerHTML = `
      <div class="cal-head"><button type="button" data-nav="-1" aria-label="Previous month">‹</button><span>${monthName} ${calYear}</span><button type="button" data-nav="1" aria-label="Next month">›</button></div>
      <div class="cal-grid">${cells}</div>
    `;
  }

  async function refresh() {
    await loadAvailability(calYear, calMonth + 1);
    renderAvailabilityCalendar();
  }

  async function initAvailabilityCalendar() {
    await refresh();

    const container = document.getElementById('availabilityCalendar');
    if (container) {
      container.addEventListener('click', async (e) => {
        const btn = e.target.closest('button[data-nav]');
        if (!btn) return;
        const dir = parseInt(btn.getAttribute('data-nav'), 10);
        calMonth += dir;
        if (calMonth > 11) {
          calMonth = 0;
          calYear++;
        } else if (calMonth < 0) {
          calMonth = 11;
          calYear--;
        }
        await refresh();
      });
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAvailabilityCalendar);
  } else {
    initAvailabilityCalendar();
  }
})();
