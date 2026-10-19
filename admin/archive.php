<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Archive Management - Pio De Roda Cafe</title>
    <!-- Font Awesome Icons & Google Fonts (Fraunces & Work Sans) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,600;0,9..144,700;1,9..144,600&family=Work+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="archive.css" />
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

                <li class="nav-item">
                    <a href="event.php"><i class="fa-solid fa-calendar-days"></i> Events</a>
                </li>

                <li class="nav-item ">
                    <a href="customer.php"><i class="fa-solid fa-users"></i> Customers</a>
                </li>

                <li class="nav-item">
                    <a href="payment.php"><i class="fa-solid fa-money-bill-wave"></i> Payment</a>
                </li>
                
                <li class="nav-item">
                    <a href="reports.php"><i class="fa-solid fa-chart-column"></i> Reports</a>
                </li>
                <li class="nav-item active">
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
            <span style="font-size: 18px; color: var(--text-dark); font-weight: 700;">Archive Management</span>
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
                    
                    <p>View, restore, or permanently remove archived records across all system modules.</p>
                </div>
            </div>

            <!-- Controls: Category Filter Tabs & Search -->
            <div class="controls-card">
                <div class="filter-tabs">
                    <button class="tab-btn active" onclick="filterArchive('reservations', event)">Reservations</button>
                    <button class="tab-btn" onclick="filterArchive('customers', event)">Customers</button>
                    
                    <button class="tab-btn" onclick="filterArchive('reviews', event)">Reviews & Feedback</button>
                </div>

                <div class="search-box">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" id="searchInput" placeholder="Search archive records..." onkeyup="handleSearch()">
                </div>
            </div>

            <!-- Archive Table Container -->
            <div class="table-container">
                <table>
                    <thead id="archiveTableHead">
                        <!-- Dynamic Head Rendered via JS -->
                    </thead>
                    <tbody id="archiveTableBody">
                        <!-- Dynamic Rows Rendered via JS -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Client-Side JavaScript Logic -->
    <script>
        // Archive Datasets
        let currentTab = 'reservations';

        const archiveData = {
            reservations: [
                { id: 101, title: 'Dignos Family Dinner', client: 'Khean Digno', date: '2026-08-14', archived_date: '2026-08-20', reason: 'Completed & Auto-Archived' },
                { id: 102, title: 'Dimaranans Reunion', client: 'Puso Dimaranan', date: '2026-07-02', archived_date: '2026-07-05', reason: 'Completed & Auto-Archived' }
            ],
            customers: [
                { id: 201, name: 'Ruzelle Peji', email: 'ruz.p@email.com', phone: '09191234567', archived_date: '2026-06-12', reason: 'Inactive Account' },
                { id: 202, name: 'Jolo Cruzada', email: 'jolo.c@email.com', phone: '09209876543', archived_date: '2026-05-30', reason: 'Requested Removal' }
            ],
            reviews: [
                { id: 401, client: 'Anonymous User', rating: '1.0 Stars', comment: 'Spam comment with invalid contact details.', archived_date: '2026-10-01', reason: 'Inappropriate Content' }
            ]
        };

        function renderArchiveTable() {
            const thead = document.getElementById('archiveTableHead');
            const tbody = document.getElementById('archiveTableBody');
            const query = document.getElementById('searchInput').value.toLowerCase();

            tbody.innerHTML = '';

            if (currentTab === 'reservations') {
                thead.innerHTML = `
                    <tr>
                        <th>Event Title</th>
                        <th>Client Name</th>
                        <th>Event Date</th>
                        <th>Archived On</th>
                        <th>Archive Reason</th>
                        
                    </tr>
                `;

                const filtered = archiveData.reservations.filter(item => 
                    item.title.toLowerCase().includes(query) || item.client.toLowerCase().includes(query)
                );

                if(filtered.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; color: var(--text-muted); padding:30px;">No archived reservations found.</td></tr>';
                    return;
                }

                filtered.forEach((item, index) => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td><strong>${item.title}</strong></td>
                        <td>${item.client}</td>
                        <td>${item.date}</td>
                        <td>${item.archived_date}</td>
                        <td><span class="category-badge">${item.reason}</span></td>
                        
                    `;
                    tbody.appendChild(tr);
                });
            } 
            else if (currentTab === 'customers') {
                thead.innerHTML = `
                    <tr>
                        <th>Customer Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Archived On</th>
                        <th>Archive Reason</th>
                        
                    </tr>
                `;

                const filtered = archiveData.customers.filter(item => 
                    item.name.toLowerCase().includes(query) || item.email.toLowerCase().includes(query)
                );

                if(filtered.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; color: var(--text-muted); padding:30px;">No archived customer records found.</td></tr>';
                    return;
                }

                filtered.forEach((item, index) => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td><strong>${item.name}</strong></td>
                        <td>${item.email}</td>
                        <td>${item.phone}</td>
                        <td>${item.archived_date}</td>
                        <td><span class="category-badge">${item.reason}</span></td>
                        
                    `;
                    tbody.appendChild(tr);
                });
            }
            else if (currentTab === 'services') {
                thead.innerHTML = `
                    <tr>
                        <th>Package / Service Name</th>
                        <th>Price</th>
                        <th>Unit</th>
                        <th>Archived On</th>
                        <th>Archive Reason</th>
                        
                    </tr>
                `;

                const filtered = archiveData.services.filter(item => 
                    item.name.toLowerCase().includes(query)
                );

                if(filtered.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; color: var(--text-muted); padding:30px;">No archived services found.</td></tr>';
                    return;
                }

                filtered.forEach((item, index) => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td><strong>${item.name}</strong></td>
                        <td style="color:var(--maroon); font-weight:700;">${item.price}</td>
                        <td>${item.unit}</td>
                        <td>${item.archived_date}</td>
                        <td><span class="category-badge">${item.reason}</span></td>
                        
                    `;
                    tbody.appendChild(tr);
                });
            }
            else if (currentTab === 'reviews') {
                thead.innerHTML = `
                    <tr>
                        <th>Customer / Author</th>
                        <th>Rating</th>
                        <th>Feedback Text</th>
                        <th>Archived On</th>
                        <th>Archive Reason</th>
                        
                    </tr>
                `;

                const filtered = archiveData.reviews.filter(item => 
                    item.client.toLowerCase().includes(query) || item.comment.toLowerCase().includes(query)
                );

                if(filtered.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; color: var(--text-muted); padding:30px;">No archived feedback found.</td></tr>';
                    return;
                }

                filtered.forEach((item, index) => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td><strong>${item.client}</strong></td>
                        <td><span style="color:var(--gold); font-weight:600;">${item.rating}</span></td>
                        <td>"${item.comment}"</td>
                        <td>${item.archived_date}</td>
                        <td><span class="category-badge">${item.reason}</span></td>
                        
                    `;
                    tbody.appendChild(tr);
                });
            }
        }

        function filterArchive(type, event) {
            currentTab = type;
            document.querySelectorAll('.filter-tabs .tab-btn').forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');
            renderArchiveTable();
        }

        function handleSearch() {
            renderArchiveTable();
        }

        function restoreRecord(type, index) {
            if(confirm('Restore this record back to active management?')) {
                archiveData[type].splice(index, 1);
                renderArchiveTable();
                alert('Record restored successfully!');
            }
        }

        function deletePermanently(type, index) {
            if(confirm('Warning: This action cannot be undone. Are you sure you want to permanently delete this record?')) {
                archiveData[type].splice(index, 1);
                renderArchiveTable();
            }
        }

        document.addEventListener('DOMContentLoaded', renderArchiveTable);
    </script>
</body>
</html>