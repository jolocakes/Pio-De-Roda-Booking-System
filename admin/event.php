<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Management - Pio De Roda Cafe</title>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <link rel="stylesheet" href="event.css" />


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

                <li class="nav-item">
                    <a href="calendar.php"><i class="fa-solid fa-calendar-days"></i> calendar</a>
                </li>

                <li class="nav-item">
                    <a href="service.php"><i class="fa-solid fa-concierge-bell"></i> Services</a>
                </li>

                <li class="nav-item active">
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
            <li class="nav-item" style="list-style: none;"><a href="#" onclick="document.getElementById('logout-form').submit(); return false;"><i class="fa-solid fa-right-from-bracket"></i> Logout</a><form id="logout-form" method="post" action="logout.php" hidden><input type="hidden" name="csrf" value="<?=htmlspecialchars($_SESSION['csrf'] ?? '', ENT_QUOTES, 'UTF-8')?>"></form></li>
            
        </div>
    </div>

    <!-- Main Wrapper -->
    <div class="main-wrapper">
        <div class="top-navbar">
            <span style="font-size: 18px; color: var(--text-dark); font-weight: 700;">Events Management</span>
            <div class="user-profile">
                <div class="user-avatar"><i class="fa-solid fa-user"></i></div>
                <div class="user-info">
                    <p>Admin User</p>
                    <span>Administrator</span>
                </div>
            </div>
        </div>

        <div class="content-body">
            <div class="page-header-container">
                <div class="page-title-group">
                    <p>Organize, view, and record all catering and venue event specifications.</p>
                </div>
                <button class="btn-add-new" onclick="openModal()"><i class="fa-solid fa-plus"></i> Add New Event</button>
            </div>

            <!-- Events Table -->
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Event Name</th>
                            <th>Customer</th>
                            <th>Type</th>
                            <th>Date & Time</th>
                            <th>Guests</th>
                            <th>Venue</th>
                            <th>Catering Package</th>
                            
                        </tr>
                    </thead>
                    <tbody id="eventsTableBody">
                        <!-- Rendered dynamically via JavaScript -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Add New Event Modal -->
    <div class="modal-overlay" id="addModal">
        <div class="modal-box">
            <h3>Add New Event</h3>
            <form id="addEventForm" onsubmit="handleAddEvent(event)">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Event Name</label>
                        <input type="text" id="event_name" required placeholder="e.g. Santos Wedding Reception">
                    </div>
                    <div class="form-group">
                        <label>Customer Name</label>
                        <input type="text" id="customer_name" required placeholder="e.g. Maria Santos">
                    </div>
                    <div class="form-group">
                        <label>Event Type</label>
                        <select id="event_type" required>
                            <option value="Wedding">Wedding</option>
                            <option value="Birthday / Debut">Birthday / Debut</option>
                            <option value="Corporate Event">Corporate Event</option>
                            <option value="Intimate Gathering">Intimate Gathering</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Event Date</label>
                        <input type="date" id="event_date" required>
                    </div>
                    <div class="form-group">
                        <label>Start Time</label>
                        <input type="time" id="start_time" required>
                    </div>
                    <div class="form-group">
                        <label>End Time</label>
                        <input type="time" id="end_time" required>
                    </div>
                    <div class="form-group">
                        <label>Number of Guests</label>
                        <input type="number" id="number_of_guests" required placeholder="100">
                    </div>
                    <div class="form-group">
                        <label>Venue</label>
                        <select id="venue" required>
                            <option value="Events Place">Events Place</option>
                            <option value="Restaurant / Cafe">Restaurant / Cafe</option>
                            <option value="Client Selected Venue">Client Selected Venue</option>
                        </select>
                    </div>
                    <div class="form-group full-width">
                        <label>Catering Package</label>
                        <input type="text" id="catering_package" required placeholder="e.g. Catering with Menu (Full Course)">
                    </div>
                    <div class="form-group full-width">
                        <label>Additional Services</label>
                        <input type="text" id="additional_services" placeholder="e.g. Coffee Booth Package, Pizza Station">
                    </div>
                    <div class="form-group full-width">
                        <label>Special Requests</label>
                        <textarea id="special_requests" rows="3" placeholder="e.g. Dietary preferences, decoration details..."></textarea>
                    </div>
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn-cancel" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn-add-new">Save Event</button>
                </div>
            </form>
        </div>
    </div>

    <!-- View Event Details Modal -->
    <div class="modal-overlay" id="viewModal">
        <div class="modal-box">
            <h3 id="view_event_title">Event Details</h3>
            
            <div class="detail-row"><strong>Customer:</strong> <span id="v_customer"></span></div>
            <div class="detail-row"><strong>Event Type:</strong> <span id="v_type"></span></div>
            <div class="detail-row"><strong>Event Date:</strong> <span id="v_date"></span></div>
            <div class="detail-row"><strong>Schedule:</strong> <span id="v_time"></span></div>
            <div class="detail-row"><strong>Guest Count:</strong> <span id="v_guests"></span></div>
            <div class="detail-row"><strong>Venue Location:</strong> <span id="v_venue"></span></div>
            <hr style="border:0; border-top: 1px solid var(--border-color); margin: 15px 0;">
            <div class="detail-row"><strong>Catering Package:</strong> <span id="v_package"></span></div>
            <div class="detail-row"><strong>Additional Services:</strong> <span id="v_services"></span></div>
            <div class="detail-row"><strong>Special Requests:</strong> <span id="v_requests"></span></div>

            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeViewModal()">Close</button>
            </div>
        </div>
    </div>

    <!-- Client-Side JavaScript Logic -->
    <script>
        // Sample Static Dataset
        let eventsData = [
            {
                id: 1,
                event_name: 'Cruzada Wedding Reception',
                customer_name: 'Jolo Cruzada',
                event_type: 'Wedding',
                event_date: '2026-10-18',
                start_time: '17:00',
                end_time: '22:00',
                number_of_guests: 100,
                venue: 'Events Place',
                catering_package: 'Catering with Menu (Full Course)',
                additional_services: 'Coffee Booth Package, Pizza Station',
                special_requests: 'Vegetarian options for 10 guests. Elegant floral backdrop.'
            },
            {
                id: 2,
                event_name: 'Ruzelle 18th Debut',
                customer_name: 'Ruzelle Peji',
                event_type: 'Birthday / Debut',
                event_date: '2026-12-06',
                start_time: '16:00',
                end_time: '22:00',
                number_of_guests: 80,
                venue: 'Events Place',
                catering_package: 'Intimate Celebration Package',
                additional_services: 'Non-Coffee Booth Package',
                special_requests: 'Provide high chairs near the main presidential table.'
            },
            {
                id: 3,
                event_name: 'Dimaranans Reunion',
                customer_name: 'Puso Dimaranan',
                event_type: 'Reunion',
                event_date: '2026-10-25',
                start_time: '16:00',
                end_time: '21:00',
                number_of_guests: 100,
                venue: 'Events Place',
                catering_package: 'Intimate Celebration Package',
                additional_services: 'Coffee Booth Package, Pizza Station',
                special_requests: 'Elegant floral backdrop.'
            },
            {
                id: 4,
                event_name: 'Digno Family Dinner',
                customer_name: 'Khean Digno',
                event_type: 'Dinner',
                event_date: '2026-11-5',
                start_time: '18:00',
                end_time: '20:00',
                number_of_guests: 15,
                venue: 'Restaurant Dine',
                catering_package: '4 Main Course',
                additional_services: 'N/A',
                special_requests: 'N/A'
            }
        ];

        // Format Date String for Display
        function formatDate(dateStr) {
            const options = { year: 'numeric', month: 'short', day: 'numeric' };
            return new Date(dateStr).toLocaleDateString('en-US', options);
        }

        // Render Events to Table
        function renderEventsTable() {
            const tbody = document.getElementById('eventsTableBody');
            tbody.innerHTML = '';

            if (eventsData.length === 0) {
                tbody.innerHTML = '<tr><td colspan="8" style="text-align:center; color: var(--text-muted); padding:30px;">No events available.</td></tr>';
                return;
            }

            eventsData.forEach((event, index) => {
                const tr = document.createElement('tr');
                // Inside renderEventsTable() function:
tr.innerHTML = `
    <td><strong>${event.event_name}</strong></td>
    <td>${event.customer_name}</td>
    <td>${event.event_type}</td>
    <td>${formatDate(event.event_date)}<br><small style="color:var(--text-muted);">${event.start_time} - ${event.end_time}</small></td>
    <td>${event.number_of_guests} pax</td>
    <td>${event.venue}</td>
    <td>${event.catering_package}</td>
`;
                
                tbody.appendChild(tr);
            });
        }

        // Handle Adding New Event
        function handleAddEvent(e) {
            e.preventDefault();
            
            const newEvent = {
                id: Date.now(),
                event_name: document.getElementById('event_name').value,
                customer_name: document.getElementById('customer_name').value,
                event_type: document.getElementById('event_type').value,
                event_date: document.getElementById('event_date').value,
                start_time: document.getElementById('start_time').value,
                end_time: document.getElementById('end_time').value,
                number_of_guests: document.getElementById('number_of_guests').value,
                venue: document.getElementById('venue').value,
                catering_package: document.getElementById('catering_package').value,
                additional_services: document.getElementById('additional_services').value,
                special_requests: document.getElementById('special_requests').value
            };

            eventsData.push(newEvent);
            renderEventsTable();
            closeModal();
            document.getElementById('addEventForm').reset();
        }

        // View Event Details
        function viewDetails(index) {
            const data = eventsData[index];
            document.getElementById('view_event_title').innerText = data.event_name;
            document.getElementById('v_customer').innerText = data.customer_name;
            document.getElementById('v_type').innerText = data.event_type;
            document.getElementById('v_date').innerText = formatDate(data.event_date);
            document.getElementById('v_time').innerText = `${data.start_time} - ${data.end_time}`;
            document.getElementById('v_guests').innerText = `${data.number_of_guests} Guests`;
            document.getElementById('v_venue').innerText = data.venue;
            document.getElementById('v_package').innerText = data.catering_package;
            document.getElementById('v_services').innerText = data.additional_services || 'None';
            document.getElementById('v_requests').innerText = data.special_requests || 'None';
            
            document.getElementById('viewModal').style.display = 'flex';
        }

        // Delete Event
        function deleteEvent(index) {
            if (confirm('Are you sure you want to delete this event?')) {
                eventsData.splice(index, 1);
                renderEventsTable();
            }
        }

        // Modal Toggle Handlers
        function openModal() { document.getElementById('addModal').style.display = 'flex'; }
        function closeModal() { document.getElementById('addModal').style.display = 'none'; }
        function closeViewModal() { document.getElementById('viewModal').style.display = 'none'; }

        // Initial Load
        document.addEventListener('DOMContentLoaded', renderEventsTable);
    </script>
</body>
</html>