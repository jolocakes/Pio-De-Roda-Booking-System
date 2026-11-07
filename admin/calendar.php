<?php require_once __DIR__ . '/auth_check.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendar & Availability - Pio De Roda</title>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,600;0,9..144,700;1,9..144,600&family=Work+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="calendar.css" />
    <link rel="stylesheet" href="responsive.css" />

    
</head>
<body>

    <!-- Sidebar Navigation -->
    <div class="sidebar">
        <div>
            <div class="brand-header">
                <div class="brand-logo-container">
                    <img src="pioderoda.jpg" alt="Pio De Roda Logo" class="brand-logo-img">
                </div>
                <h2>PIO DE RODA</h2>
                <p>RESERVATION SYSTEM</p>
            </div>
            
            <ul class="nav-menu">
                <li class="nav-item">
                    <a href="dashboard.php"><i class="fa-solid fa-house"></i> Dashboard</a>
                </li>

                <li class="nav-item ">
                    <a href="reservation.php"><i class="fa-solid fa-calendar-check"></i> Reservations</a>
                </li>

                <li class="nav-item active">
                    <a href="calendar.php"><i class="fa-solid fa-calendar-days"></i> Calendar</a>
                </li>

                <li class="nav-item">
                    <a href="service.php"><i class="fa-solid fa-concierge-bell"></i> Services</a>
                </li>
                <li class="nav-item">
                    <a href="event.php"><i class="fa-solid fa-calendar-days"></i> Events</a>
                </li>

                <li class="nav-item">
                    <a href="customer.php"><i class="fa-solid fa-users"></i> Customers</a>
                </li>

                <li class="nav-item">
                    <a href="payment.php"><i class="fa-solid fa-money-bill-wave"></i> Payment</a>
                </li>

                <li class="nav-item">
                    <a href="reports.php"><i class="fa-solid fa-chart-column"></i> Reports</a>
                </li>

                <li class="nav-item">
                    <a href="archive.php"><i class="fa-solid fa-box-archive"></i> Archive</a>
                </li>
            </ul>

        </div>

        <div class="sidebar-footer">
            <li class="nav-item" style="list-style: none;"><a href="logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a></li>
        </div>
    </div>

    <!-- Main Wrapper -->
    <div class="main-wrapper">
        <div class="top-navbar">
            <h1 style="font-size: 18px;">Calendar & Availability Schedule</h1>
            <div class="user-profile">
                <div class="user-avatar"><i class="fa-solid fa-user"></i></div>
                <div class="user-info">
                    <p>Admin User</p>
                    <span>Administrator</span>
                </div>
            </div>
        </div>

        <div class="content-body">
            <div class="calendar-layout">
                
                

                <!-- Main Calendar Section -->
<div class="card">
    <div class="calendar-header">
        <!-- Dynamic Month & Year Header -->
        <h2 id="monthYearHeader">September 2026</h2>
        <div>
            <!-- Button controls -->
            <button class="calendar-nav-btn" onclick="changeMonth(-1)"><i class="fa-solid fa-chevron-left"></i> Prev</button>
            <button class="calendar-nav-btn" onclick="changeMonth(1)">Next <i class="fa-solid fa-chevron-right"></i></button>
        </div>
    </div>

    <!-- Calendar Grid -->
    <div class="calendar-legend" aria-label="Reservation status legend">
        <span class="legend-item"><span class="legend-dot confirmed"></span> Confirmed</span>
        <span class="legend-item"><span class="legend-dot pending"></span> Pending</span>
        <span class="legend-item"><span class="legend-dot cancelled"></span> Cancelled</span>
        <span class="legend-item"><span class="legend-dot completed"></span> Completed</span>
    </div>

    <div class="calendar-grid" id="calendarDaysGrid">
        <!-- I-ge-generate ito ng JavaScript -->
    </div>
</div>

                    

                <!-- Right Side Information Panel -->
                <div>
                    <!-- Venue Availability Tracker -->
                    <!-- Filled by updateSidePanels() from the SAME availability
                         rules the customer calendars use. Placeholder only. -->
                    <div class="card" id="venueAvailabilityCard" style="margin-bottom: 20px;">
                        <div class="panel-section-title">
                            <span>Availability</span>
                            <span id="availDateLabel" style="font-size: 11px; color: var(--text-muted);">—</span>
                        </div>
                        <div id="availabilityPanel">
                            <p style="font-size: 12px; color: #718096; padding: 10px 0;">Loading availability…</p>
                        </div>
                    </div>

                    <!-- Events & Pending List -->
                    <!-- Filled by updateSidePanels() with this date's real bookings. -->
                    <div class="card" id="scheduleDetailsCard">
                        <div class="panel-section-title">
                            <span>Schedule Details</span>
                        </div>
                        <div id="scheduleDetailsPanel">
                            <p style="font-size: 12px; color: #718096; padding: 10px 0;">Loading bookings…</p>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
    <script>
let eventsData = [];

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, function (ch) {
        return {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#39;'
        }[ch];
    });
}

function getBadgeInfo(status) {
    const s = (status || '').toLowerCase();
    if (s === 'confirmed') return { cls: 'chip-confirmed', label: 'Confirmed Event' };
    if (s === 'pending') return { cls: 'chip-pending', label: 'Pending Reservation' };
    if (s === 'cancelled') return { cls: 'chip-cancelled', label: 'Cancelled' };
    if (s === 'completed') return { cls: 'chip-completed', label: 'Completed' };
    return { cls: 'chip-pending', label: status || 'Pending' };
}



let currentDate = new Date();
let selectedDateStr = new Date().toISOString().split('T')[0];

let fetchSequence = 0;

/* ------------------------------------------------------------------
   AVAILABILITY — the SAME endpoint the customer landing page and the
   booking section use (api/availability.php -> api/capacity.php).
   Reading one shared source is what keeps the admin calendar in sync:
   max 2 services/day, venue exclusive per date, per-service time slots.
   ------------------------------------------------------------------ */
let avail = { dates: [], times: {}, meta: {}, rules: null };

function isAvailTaken(dateStr) {
    return avail.dates.indexOf(dateStr) !== -1;
}

// Reason a date is closed, or how full it is, in plain words.
function availHint(dateStr) {
    const m = avail.meta[dateStr];
    if (!m) return '';
    if (m.hasVenue) return 'Venue booked — date closed';
    const cap = (avail.rules && avail.rules.maxPerDay) || 2;
    if (m.count >= cap) return `${m.count} of ${cap} slots used — date closed`;
    if (m.count > 0) return `${m.count} of ${cap} slots used`;
    return '';
}

async function fetchAvailability() {
    const y = currentDate.getFullYear();
    const m = currentDate.getMonth() + 1;
    try {
        const res = await fetch(`../api/availability.php?year=${y}&month=${m}`, { cache: 'no-store' });
        if (!res.ok) throw new Error('availability request failed');
        const data = await res.json();
        avail = {
            dates: data.dates || [],
            times: data.times || {},
            meta: data.meta || {},
            rules: data.rules || null
        };
    } catch (e) {
        // keep the last known picture rather than blanking the calendar
    }
    renderCalendar();
}

async function fetchAdminReservations() {
    const currentSeq = ++fetchSequence;
    try {
        const res = await fetch('../api/admin/reservations.php', { cache: 'no-store' });
        if (!res.ok) throw new Error('Network response was not ok');
        const rows = await res.json();
        
        if (currentSeq !== fetchSequence) return;

        eventsData = (rows || []).map(r => ({
            id: r.id,
            date: r.eventDate,
            title: r.service || 'Reservation',
            time: r.eventTime || 'All Day',
            venue: r.service || 'Events Place',
            status: (r.status || 'Pending').toLowerCase(),
            rawStatus: r.status || 'Pending',
            customer: r.customerName,
            contactNumber: r.contactNumber || 'Not provided',
            email: r.email || 'Not provided',
            address: r.address || '',
            eventType: r.eventType || 'Not provided',
            guests: r.guests || 0,
            selectedPackage: r.selectedPackage || 'Not provided',
            additionalServices: r.additionalServices || []
        }));
        renderCalendar();
    } catch (e) {
        if (eventsData.length === 0) {
            renderCalendar();
        }
    }
}

document.addEventListener('DOMContentLoaded', () => {
    fetchAdminReservations();
    fetchAvailability();
});

window.addEventListener('pageshow', (event) => {
    fetchAdminReservations();
    fetchAvailability();
});

document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') {
        fetchAdminReservations();
        fetchAvailability();
    }
});


function renderCalendar() {
    const year = currentDate.getFullYear();
    const month = currentDate.getMonth();

    const monthNames = ["January", "February", "March", "April", "May", "June", 
                        "July", "August", "September", "October", "November", "December"];
    document.getElementById('monthYearHeader').innerText = `${monthNames[month]} ${year}`;

    const grid = document.getElementById('calendarDaysGrid');
    grid.innerHTML = '';

    // Day Headers
    ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].forEach(day => {
        const dh = document.createElement('div');
        dh.className = 'day-name';
        dh.innerText = day;
        grid.appendChild(dh);
    });

    const firstDayIndex = new Date(year, month, 1).getDay();
    const lastDay = new Date(year, month + 1, 0).getDate();
    const prevLastDay = new Date(year, month, 0).getDate();

    // Previous Month Days
    for (let x = firstDayIndex; x > 0; x--) {
        const cell = document.createElement('div');
        cell.className = 'day-cell other-month';
        cell.innerHTML = `<div class="day-number">${prevLastDay - x + 1}</div>`;
        grid.appendChild(cell);
    }

    // Current Month Days
    for (let i = 1; i <= lastDay; i++) {
        const cell = document.createElement('div');
        cell.className = 'day-cell';
        
        const formattedMonth = String(month + 1).padStart(2, '0');
        const formattedDay = String(i).padStart(2, '0');
        const dateString = `${year}-${formattedMonth}-${formattedDay}`;

        let eventsHTML = `<div class="day-number">${i}</div>`;
        // Show every status here. Availability still uses the separate
        // capacity endpoint, which only counts Pending and Confirmed bookings.
        const dayEvents = eventsData.filter(e => e.date === dateString);

        // Same rules as the customer calendars: a date is CLOSED when the venue
        // is booked or the day has hit its service cap. Gray it out + say why.
        const taken = isAvailTaken(dateString);
        const hint = availHint(dateString);

        // Dagdag styling kapag napili ang araw.
        // NOTE: the selected-day background must NEVER touch a closed date —
        // an inline style would beat the .day-taken gray and make a closed
        // day look open. The border still marks it as selected.
        if (dateString === selectedDateStr) {
            cell.style.border = '2px solid #500a18';
            if (!taken) cell.style.backgroundColor = '#fff5f7';
        }

        if (taken) {
            cell.classList.add('day-taken');
            cell.title = hint || 'Fully booked';
        } else if (hint) {
            cell.title = hint;
        }

        dayEvents.forEach(evt => {
            const badge = getBadgeInfo(evt.rawStatus);
            const customer = evt.customer || 'Unknown customer';
            const service = evt.title || 'Reservation';
            const chipTitle = `${customer} — ${service} (${evt.time || 'All Day'}) · ${evt.rawStatus || 'Pending'}`;
            eventsHTML += `<span class="event-chip ${badge.cls}" title="${escapeHtml(chipTitle)}">` +
                `<span class="chip-customer">${escapeHtml(customer)}</span> — ${escapeHtml(service)}` +
                `</span>`;
        });

        if (taken) {
            eventsHTML += `<span class="closed-tag">Date closed</span>`;
        } else if (hint) {
            eventsHTML += `<span class="slots-tag">${hint}</span>`;
        }

        cell.innerHTML = eventsHTML;
        
        // CLICK EVENT: Para mag-update ang venue availability & schedule details sa gilid!
        cell.onclick = () => selectDate(dateString);
        grid.appendChild(cell);
    }

    // Next Month Days
    const totalCells = firstDayIndex + lastDay;
    const nextDays = (7 - (totalCells % 7)) % 7;
    for (let j = 1; j <= nextDays; j++) {
        const cell = document.createElement('div');
        cell.className = 'day-cell other-month';
        cell.innerHTML = `<div class="day-number">${j}</div>`;
        grid.appendChild(cell);
    }

    // I-update din ang Side Panels para sa napiling araw
    updateSidePanels(selectedDateStr);
}

function selectDate(dateStr) {
    selectedDateStr = dateStr;
    renderCalendar();
}

function changeMonth(direction) {
    currentDate.setMonth(currentDate.getMonth() + direction);
    // Kusa ring pipiliin ang unang araw ng bagong buwan
    const year = currentDate.getFullYear();
    const month = String(currentDate.getMonth() + 1).padStart(2, '0');
    selectedDateStr = `${year}-${month}-01`;
    renderCalendar(); // draw immediately with what we know...
    fetchAvailability(); // ...then refresh with the new month's real rules
}

// Function para mag-update ang Venue Availability at Schedule Details sa Side Panels
function updateSidePanels(dateStr) {
    const d = new Date(dateStr + 'T00:00:00');
    const options = { month: 'short', day: 'numeric', year: 'numeric' };
    const formattedDate = d.toLocaleDateString('en-US', options);

    // 1. Header date on the availability card
    const availDateLabel = document.getElementById('availDateLabel');
    if (availDateLabel) availDateLabel.innerText = formattedDate;

    // 2. Show every booking on the selected day. The availability panel
    //    below still counts only Pending/Confirmed via api/availability.php.
    const dayEvents = eventsData.filter(e => e.date === dateStr);

    // Update Schedule Details Container (by id — no fragile card indexing)
    const scheduleCard = document.getElementById('scheduleDetailsCard');
    if (scheduleCard) {
        let detailsHTML = `<div class="panel-section-title"><span>Schedule Details (${formattedDate})</span></div>`;

        if (dayEvents.length === 0) {
            detailsHTML += `<p style="font-size: 12px; color: #718096; padding: 10px 0;">No bookings or events for this date.</p>`;
        } else {
            dayEvents.forEach(evt => {
                const badge = getBadgeInfo(evt.rawStatus);
                const customer = evt.customer || 'Unknown customer';
                const service = evt.title || 'Reservation';
                const guests = Number(evt.guests) || 0;

                detailsHTML += `
                    <div class="event-detail-item">
                        <div class="event-title">${escapeHtml(customer)}</div>
                        <div class="event-meta"><i class="fa-solid fa-concierge-bell"></i> ${escapeHtml(service)}${evt.eventType ? ' · ' + escapeHtml(evt.eventType) : ''}</div>
                        <div class="event-meta"><i class="fa-solid fa-calendar"></i> ${formattedDate}</div>
                        <div class="event-meta"><i class="fa-solid fa-clock"></i> ${escapeHtml(evt.time || 'All Day')}</div>
                        <div class="event-meta"><i class="fa-solid fa-users"></i> ${guests} guests</div>
                        <div class="event-meta"><i class="fa-solid fa-phone"></i> ${escapeHtml(evt.contactNumber || 'Not provided')}</div>
                        <div class="event-meta"><i class="fa-solid fa-envelope"></i> ${escapeHtml(evt.email || 'Not provided')}</div>
                        <div class="event-meta"><i class="fa-solid fa-box"></i> ${escapeHtml(evt.selectedPackage || 'Not provided')}</div>
                        ${evt.address ? `<div class="event-meta"><i class="fa-solid fa-house"></i> ${escapeHtml(evt.address)}</div>` : ""}
                        <span class="event-chip ${badge.cls}" style="display:inline-block; margin-top:5px;">${escapeHtml(badge.label)}</span>
                    </div>
                `;
            });
        }
        scheduleCard.innerHTML = detailsHTML;
    }

    // 3. Availability panel — the SAME three rules the customer side enforces:
    //    max 2 services/day, venue exclusive per date, per-service time slots.
    const availCard = document.getElementById('venueAvailabilityCard');
    if (availCard) {
        const meta = avail.meta[dateStr] || { count: 0, hasVenue: false, taken: false };
        const cap = (avail.rules && avail.rules.maxPerDay) || 2;
        const exclusive = (avail.rules && avail.rules.exclusiveService) || 'Venue Rentals';
        const closed = isAvailTaken(dateStr);

        let html = `
            <div class="panel-section-title">
                <span>Availability</span>
                <span style="font-size: 11px; color: var(--text-muted);">${formattedDate}</span>
            </div>`;

        // Date status
        if (meta.hasVenue) {
            html += `
            <div class="availability-card">
                <div class="venue-name">Date status</div>
                <div class="venue-status text-booked">Closed — venue booked</div>
                <div class="time-slot">${exclusive} is exclusive: one booking closes the whole date</div>
            </div>`;
        } else if (meta.count >= cap) {
            html += `
            <div class="availability-card">
                <div class="venue-name">Date status</div>
                <div class="venue-status text-booked">Closed — ${meta.count} of ${cap} slots used</div>
                <div class="time-slot">No further bookings accepted for this date</div>
            </div>`;
        } else {
            html += `
            <div class="availability-card">
                <div class="venue-name">Date status</div>
                <div class="venue-status text-available">Open</div>
                <div class="time-slot">${meta.count} of ${cap} slots used — ${cap - meta.count} remaining</div>
            </div>`;
        }

        // Slot usage
        html += `
            <div class="availability-card">
                <div class="venue-name">Daily slots</div>
                <div class="venue-status ${meta.count >= cap ? 'text-booked' : 'text-available'}">${meta.count} / ${cap} used</div>
                <div class="time-slot">Maximum ${cap} services per day</div>
            </div>`;

        // Venue exclusivity
        html += `
            <div class="availability-card">
                <div class="venue-name">${exclusive}</div>
                <div class="venue-status ${meta.hasVenue ? 'text-booked' : 'text-available'}">${meta.hasVenue ? 'Booked' : 'Not booked'}</div>
                <div class="time-slot">${meta.hasVenue ? 'Closes this date for every service' : 'Date remains open to other services'}</div>
            </div>`;

        // Taken time slots (per-service — a slot blocks only its own service)
        const slots = avail.times[dateStr];
        const slotKeys = slots ? Object.keys(slots).sort() : [];
        html += `
            <div class="availability-card">
                <div class="venue-name">Booked time slots</div>`;
        if (slotKeys.length === 0) {
            html += `<div class="venue-status text-available">None</div>
                     <div class="time-slot">Every time is open for all services</div>`;
        } else {
            html += `<div class="venue-status text-booked">${slotKeys.length} booked</div>`;
            slotKeys.forEach(t => {
                html += `<div class="time-slot">${t} — ${(slots[t] || []).join(', ')}</div>`;
            });
        }
        html += `</div>`;

        availCard.innerHTML = html;
    }
}

document.addEventListener('DOMContentLoaded', renderCalendar);
</script>

<script src="responsive.js"></script>
</body>
</html>