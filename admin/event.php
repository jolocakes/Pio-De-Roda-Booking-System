<?php require_once __DIR__ . '/auth_check.php'; ?>
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
            <li class="nav-item" style="list-style: none;"><a href="#"><i class="fa-solid fa-right-from-bracket"></i> Logout</a></li>
            
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
                    <p>Organize, view, and record all catering and venue event specifications from customer reservations.</p>
                </div>
                <div style="font-size: 13px; color: var(--text-muted); background: var(--bg-card, #fff); padding: 8px 12px; border-radius: 6px; border: 1px solid var(--border-color);">
                    <i class="fa-solid fa-info-circle" style="color: var(--primary);"></i> Events originate automatically from customer reservations.
                </div>
            </div>

            <!-- Events Table -->
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Reservation ID</th>
                            <th>Event / Service</th>
                            <th>Customer</th>
                            <th>Type</th>
                            <th>Date & Time</th>
                            <th>Guests</th>
                            <th>Package</th>
                            <th>Status</th>
                            <th style="text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="eventsTableBody">
                        <!-- Rendered dynamically via JavaScript -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- View Event Details Modal -->
    <div class="modal-overlay" id="viewModal">
        <div class="modal-box">
            <h3 id="view_event_title">Event Details</h3>
            
            <div class="detail-row"><strong>Reservation ID:</strong> <span id="v_id"></span></div>
            <div class="detail-row"><strong>Customer:</strong> <span id="v_customer"></span></div>
            <div class="detail-row"><strong>Contact Number:</strong> <span id="v_phone"></span></div>
            <div class="detail-row"><strong>Email:</strong> <span id="v_email"></span></div>
            <div class="detail-row"><strong>Event Type:</strong> <span id="v_type"></span></div>
            <div class="detail-row"><strong>Event Date:</strong> <span id="v_date"></span></div>
            <div class="detail-row"><strong>Schedule:</strong> <span id="v_time"></span></div>
            <div class="detail-row"><strong>Guest Count:</strong> <span id="v_guests"></span></div>
            <div class="detail-row"><strong>Status:</strong> <span id="v_status"></span></div>
            <hr style="border:0; border-top: 1px solid var(--border-color); margin: 15px 0;">
            <div class="detail-row"><strong>Catering Package:</strong> <span id="v_package"></span></div>
            <div class="detail-row"><strong>Additional Services:</strong> <span id="v_services"></span></div>
            <div class="detail-row"><strong>Estimated Total:</strong> <span id="v_total"></span></div>

            <div class="modal-actions" style="margin-top: 20px; display: flex; gap: 10px; justify-content: flex-end;">
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