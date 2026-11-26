/* ==========================================================================
   PIO DE RODA HOSPITALITY RESERVATION & EVENT BOOKING SYSTEM
   Homepage Availability Calendar Component (customer/availability.js)
   ==========================================================================
   Availability data comes from loadAvailability() in services.js, which reads
   api/availability.php. A date grays out only when NO service can take it:
     - staff blocked the date, or
     - every service is already at its own daily/concurrency limit.
   A venue booking therefore no longer hides a date from the booths. The
   booking page disables the individual slots each service cannot take.
   ========================================================================== */

(function () {
  let calYear = new Date().getFullYear();
  let calMonth = new Date().getMonth();

  function daysInMonth(y, m) {
    return window.PDRDates ? PDRDates.daysInMonth(y, m) : new Date(y, m + 1, 0).getDate();
  }

  function fmtDate(y, m, d) {
    return window.PDRDates
      ? PDRDates.toISODate(new Date(y, m, d))
      : `${y}-${String(m + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
  }

  // Human hint for a partially-booked date, e.g. "3 bookings on this date".
  function dayHint(ds) {
    const meta = (typeof AVAIL !== 'undefined' && AVAIL.meta) ? AVAIL.meta[ds] : null;
    if (!meta) return '';
    if (meta.blackout) return ' — closed by staff';
    if (meta.hasVenue) {
      return meta.taken
        ? ' — fully booked'
        : ' — venue booked, other services available';
    }
    if (meta.count > 0) {
      return ` — ${meta.count} booking${meta.count === 1 ? '' : 's'} on this date`;
    }
    return '';
  }

  function renderAvailabilityCalendar() {
    const container = document.getElementById('availabilityCalendar');
    if (!container) return;

    const first = new Date(calYear, calMonth, 1).getDay();
    const total = daysInMonth(calYear, calMonth);
    const monthName = window.PDRDates
      ? PDRDates.monthName(calYear, calMonth, false)
      : new Date(calYear, calMonth, 1).toLocaleString('default', { month: 'long' });
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
      // T-40: dates outside the booking window are shown but not bookable.
      const windowErr = bookingWindowError(ds);
      if (windowErr) {
        const why = windowErr === 'TOO_SOON'
          ? `requires ${PDR_MIN_LEAD_DAYS} days' notice`
          : `more than ${PDR_MAX_HORIZON_DAYS} days ahead`;
        cells += `<div class="day past" aria-disabled="true" aria-label="${ds} — ${why}">${d}</div>`;
        continue;
      }
      const unavail = isDateTaken(ds);
      const hint = dayHint(ds).trim(); // "" | "— venue booked..." | "— 3 bookings on this date"
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
