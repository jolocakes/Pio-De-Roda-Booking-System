/* ==========================================================================
   PIO DE RODA HOSPITALITY RESERVATION & EVENT BOOKING SYSTEM
   Homepage Availability Calendar Component (customer/availability.js)
   ========================================================================== */

(function () {
  let calYear = new Date().getFullYear();
  let calMonth = new Date().getMonth();
  let unavailableDates = [];

  function daysInMonth(y, m) {
    return new Date(y, m + 1, 0).getDate();
  }

  function fmtDate(y, m, d) {
    return `${y}-${String(m + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
  }

  function renderAvailabilityCalendar() {
    const container = document.getElementById('availabilityCalendar');
    if (!container) return;

    const first = new Date(calYear, calMonth, 1).getDay();
    const total = daysInMonth(calYear, calMonth);
    const monthName = new Date(calYear, calMonth, 1).toLocaleString('default', { month: 'long' });
    const today = getTodayLocalDate();

    let cells = `<div class=\"dow\">Su</div><div class=\"dow\">Mo</div><div class=\"dow\">Tu</div><div class=\"dow\">We</div><div class=\"dow\">Th</div><div class=\"dow\">Fr</div><div class=\"dow\">Sa</div>`;
    for (let i = 0; i < first; i++) {
      cells += `<div class=\"day blank\"></div>`;
    }
    for (let d = 1; d <= total; d++) {
      const ds = fmtDate(calYear, calMonth, d);
      const isToday = ds === today;
      if (ds < today) {
        cells += `<div class=\"day past\" aria-disabled=\"true\" aria-label=\"${ds} — past date\">${d}</div>`;
        continue;
      }
      const unavail = unavailableDates.includes(ds);
      cells += `<div class=\"day ${unavail ? 'unavail' : 'avail'} ${isToday ? 'today' : ''}\" aria-disabled=\"${unavail}\" aria-label=\"${ds} — ${unavail ? 'unavailable' : (isToday ? 'today, available' : 'available')}\">${d}</div>`;
    }

    container.innerHTML = `
      <div class=\"cal-head\"><button type=\"button\" data-nav=\"-1\" aria-label=\"Previous month\">‹</button><span>${monthName} ${calYear}</span><button type=\"button\" data-nav=\"1\" aria-label=\"Next month\">›</button></div>
      <div class=\"cal-grid\">${cells}</div>
    `;
  }

  async function initAvailabilityCalendar() {
    try {
      if (typeof getUnavailableDates === 'function') {
        unavailableDates = await getUnavailableDates();
      }
    } catch (e) {
      unavailableDates = [];
    }
    renderAvailabilityCalendar();

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
        try {
          if (typeof getUnavailableDates === 'function') {
            unavailableDates = await getUnavailableDates();
          }
        } catch (err) {}
        renderAvailabilityCalendar();
      });
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAvailabilityCalendar);
  } else {
    initAvailabilityCalendar();
  }
})();
