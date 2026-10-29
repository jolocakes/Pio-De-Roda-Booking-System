<?php require_once __DIR__ . '/auth_check.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendar & Availability - Pio De Roda</title>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <link rel="stylesheet" href="calendar.css" />

    
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
            <li class="nav-item" style="list-style: none;"><a href="#"><i class="fa-solid fa-right-from-bracket"></i> Logout</a></li>
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
    <div class="calendar-grid" id="calendarDaysGrid">
        <!-- I-ge-generate ito ng JavaScript -->
    </div>
</div>

                    

                <!-- Right Side Information Panel -->
                <div>
                    <!-- Venue Availability Tracker -->
                    <div class="card" id="venueAvailabilityCard" style="margin-bottom: 20px;">
                        <div class="panel-section-title">
                            <span>Venue Availability</span>
                            <span style="font-size: 11px; color: var(--text-muted);">Sept 20, 2026</span>
                        </div>

                        <div class="availability-card">
                            <div class="venue-name">Events Place</div>
                            <div class="venue-status text-booked">Booked (10:00 AM - 02:00 PM)</div>
                            <div class="time-slot">Available after 03:00 PM</div>
                        </div>

                        <div class="availability-card">
                            <div class="venue-name">Restaurant</div>
                            <div class="venue-status text-booked">Available All Day</div>
                            <div class="time-slot">Full Open Slot</div>
                        </div>

                        <div class="availability-card">
                            <div class="venue-name">Cafe</div>
                            <div class="venue-status text-available">Available All Day</div>
                            <div class="time-slot">Full Open Slot</div>
                        </div>
                    </div>

                    <!-- Events & Pending List -->
                    <div class="card" id="scheduleDetailsCard">
                        <div class="panel-section-title">
                            <span>Schedule Details</span>
                        </div>

                        <div class="event-detail-item">
                            <div class="event-title">Wedding Reception</div>
                            <div class="event-meta"><i class="fa-solid fa-calendar"></i> Sept 18, 2026</div>
                            <div class="event-meta"><i class="fa-solid fa-clock"></i> 05:00 PM - 10:00 PM</div>
                            <div class="event-meta"><i class="fa-solid fa-location-dot"></i>Events PLace</div>
                            <span class="event-chip chip-confirmed" style="display:inline-block; margin-top:5px;">Confirmed Event</span>
                        </div>

                        <div class="event-detail-item">
                            <div class="event-title">Cafe</div>
                            <div class="event-meta"><i class="fa-solid fa-calendar"></i> Sept 20, 2026</div>
                            <div class="event-meta"><i class="fa-solid fa-clock"></i> 02:00 PM - 03:30 PM</div>
                            <div class="event-meta"><i class="fa-solid fa-location-dot"></i>Cafe</div>
                            <span class="event-chip chip-pending" style="display:inline-block; margin-top:5px;">Pending Reservation</span>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
    <script>
let eventsData = [];
const venueList = ['Events Place', 'Restaurant', 'Cafe'];
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

document.addEventListener('DOMContentLoaded', fetchAdminReservations);

window.addEventListener('pageshow', (event) => {
    fetchAdminReservations();
});

document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') {
        fetchAdminReservations();
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

        // Dagdag styling kapag napili ang araw
        if (dateString === selectedDateStr) {
            cell.style.border = '2px solid #500a18';
            cell.style.backgroundColor = '#fff5f7';
        }

        let eventsHTML = `<div class="day-number">${i}</div>`;
        const dayEvents = eventsData.filter(e => e.date === dateString && (e.status === 'confirmed' || e.status === 'pending'));
        dayEvents.forEach(evt => {
            const badge = getBadgeInfo(evt.rawStatus);
            eventsHTML += `<span class="event-chip ${badge.cls}">${evt.title}</span>`;
        });

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
    renderCalendar();
}

// Function para mag-update ang Venue Availability at Schedule Details sa Side Panels
function updateSidePanels(dateStr) {
    const d = new Date(dateStr + 'T00:00:00');
    const options = { month: 'short', day: 'numeric', year: 'numeric' };
    const formattedDate = d.toLocaleDateString('en-US', options);

    // 1. Update Venue Availability Section Header Date
    const availHeader = document.querySelector('.availability-card')?.parentElement?.querySelector('span:last-child');
    if(availHeader) availHeader.innerText = formattedDate;

    // 2. Filter Events sa napiling araw (Pending and Confirmed only)
    const dayEvents = eventsData.filter(e => e.date === dateStr && (e.status === 'confirmed' || e.status === 'pending'));

    // Update Schedule Details Container
    const scheduleCard = document.querySelectorAll('.card')[2]; // Pangatlong card sa layout
    if (scheduleCard) {
        let detailsHTML = `<div class="panel-section-title"><span>Schedule Details (${formattedDate})</span></div>`;
        
        if (dayEvents.length === 0) {
            detailsHTML += `<p style="font-size: 12px; color: #718096; padding: 10px 0;">No bookings or events for this date.</p>`;
        } else {
            dayEvents.forEach(evt => {
                const badgeClass = evt.status === 'confirmed' ? 'chip-confirmed' : 'chip-pending';
                const statusLabel = evt.status === 'confirmed' ? 'Confirmed Event' : 'Pending Reservation';
                detailsHTML += `
                    <div class="event-detail-item">
                        <div class="event-title">${evt.title}</div>
                        <div class="event-meta"><i class="fa-solid fa-calendar"></i> ${formattedDate}</div>
                        <div class="event-meta"><i class="fa-solid fa-clock"></i> ${evt.time}</div>
                        <div class="event-meta"><i class="fa-solid fa-location-dot"></i> ${evt.venue}</div>
                        <span class="event-chip ${badgeClass}" style="display:inline-block; margin-top:5px;">${statusLabel}</span>
                    </div>
                `;
            });
        }
        scheduleCard.innerHTML = detailsHTML;
    }

    // 3. Dynamic Venue Availability Cards
    const availCardContainer = document.querySelectorAll('.card')[1]; // Pangalawang card sa layout
    if (availCardContainer) {
        let availHTML = `
            <div class="panel-section-title">
                <span>Venue Availability</span>
                <span style="font-size: 11px; color: var(--text-muted);">${formattedDate}</span>
            </div>`;

        venueList.forEach(v => {
            const bookedEvt = dayEvents.find(e => e.status === 'confirmed' && (e.venue === v || v === 'Events Place'));
            if (bookedEvt) {
                availHTML += `
                    <div class="availability-card">
                        <div class="venue-name">${v}</div>
                        <div class="venue-status text-booked">Booked (${bookedEvt.time})</div>
                        <div class="time-slot">Slot occupied</div>
                    </div>`;
            } else {
                availHTML += `
                    <div class="availability-card">
                        <div class="venue-name">${v}</div>
                        <div class="venue-status text-available">Available All Day</div>
                        <div class="time-slot">Full Open Slot</div>
                    </div>`;
            }
        });

        availCardContainer.innerHTML = availHTML;
    }
}

document.addEventListener('DOMContentLoaded', renderCalendar);
</script>

</body>
</html>