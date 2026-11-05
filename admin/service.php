<?php require_once __DIR__ . '/auth_check.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Service Management - Pio De Roda Cafe</title>
 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="service.css" />
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
                <li class="nav-item">
                    <a href="reservation.php"><i class="fa-solid fa-calendar-check"></i> Reservations</a>
                </li>
                <li class="nav-item">
                    <a href="calendar.php"><i class="fa-solid fa-calendar-days"></i> calendar</a>
                </li>
                <li class="nav-item active">
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
 
    <!-- Main Content Area -->
    <div class="main-wrapper">
        <div class="top-navbar">
            <span style="font-size: 18px; color: var(--text-dark); font-weight: 700;">Our Services &amp; Packages</span>
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
                <div class="page-title-group"></div>
                <button class="btn-add-new"><i class="fa-solid fa-plus"></i> Add New Package</button>
            </div>
 
            <div class="services-grid">
 
                <!-- Card 1: Catering with Menu -->
                <div class="service-card" data-id="1">
                    <div class="card-image-wrap">
                        <img src="cateringwithmenu.jpg" alt="Catering with Menu">
                    </div>
                    <div class="card-body">
                        <h3 class="service-title">Catering with Menu</h3>
                        <p class="service-desc">Full-course catering with a customizable menu for any occasion, served at your chosen venue.</p>
 
                        <!-- Price (view mode) -->
                        <div class="price-label" data-price="1000">
                            Starting at ₱<span class="price-value">1,000</span> <span class="price-unit">/ head</span>
                        </div>
 
                        <!-- Price (edit mode) -->
                        <div class="price-edit" style="display:none;">
                            <span class="peso">₱</span>
                            <input type="number" class="price-input" min="0" step="1">
                            <span class="price-unit-edit">/ head</span>
                        </div>
 
                        <div class="card-actions">
                            <button type="button" class="btn-edit-price"><i class="fa-solid fa-pen"></i> Edit Price</button>
                            <button type="button" class="btn-save-price" style="display:none;"><i class="fa-solid fa-check"></i> Save</button>
                            <button type="button" class="btn-cancel-price" style="display:none;"><i class="fa-solid fa-xmark"></i> Cancel</button>
                        </div>
                    </div>
                </div>
 
                <!-- Card 2: Venue Rentals -->
                <div class="service-card" data-id="2">
                    <div class="card-image-wrap">
                        <img src="venueRentals.jpg" alt="Venue Rentals">
                    </div>
                    <div class="card-body">
                        <h3 class="service-title">Venue Rentals</h3>
                        <p class="service-desc">A comfortable and welcoming space for celebrations, gatherings, and memorable special events.</p>
 
                        <!-- Price (view mode) -->
                        <div class="price-label" data-price="15000">
                            Starting at ₱<span class="price-value">15,000</span> <span class="price-unit"></span>
                        </div>
 
                        <!-- Price (edit mode) -->
                        <div class="price-edit" style="display:none;">
                            <span class="peso">₱</span>
                            <input type="number" class="price-input" min="0" step="1">
                            <span class="price-unit-edit"></span>
                        </div>
 
                        <div class="card-actions">
                            <button type="button" class="btn-edit-price"><i class="fa-solid fa-pen"></i> Edit Price</button>
                            <button type="button" class="btn-save-price" style="display:none;"><i class="fa-solid fa-check"></i> Save</button>
                            <button type="button" class="btn-cancel-price" style="display:none;"><i class="fa-solid fa-xmark"></i> Cancel</button>
                        </div>
                    </div>
                </div>
 
                <!-- Card 3: Dine-In Menu -->
                <div class="service-card" data-id="3">
                    <div class="card-image-wrap">
                        <img src="dineInMenu.jpg" alt="Dine-In Menu">
                    </div>
                    <div class="card-body">
                        <h3 class="service-title">Dine-In Menu</h3>
                        <p class="service-desc">A selection of delicious meals and refreshing drinks served fresh for all dine-in guests to enjoy and savor.</p>
 
                        <!-- Price (view mode) -->
                        <div class="price-label" data-price="600">
                            Starting at ₱<span class="price-value">600</span> <span class="price-unit">/ head</span>
                        </div>
 
                        <!-- Price (edit mode) -->
                        <div class="price-edit" style="display:none;">
                            <span class="peso">₱</span>
                            <input type="number" class="price-input" min="0" step="1">
                            <span class="price-unit-edit">/ head</span>
                        </div>
 
                        <div class="card-actions">
                            <button type="button" class="btn-edit-price"><i class="fa-solid fa-pen"></i> Edit Price</button>
                            <button type="button" class="btn-save-price" style="display:none;"><i class="fa-solid fa-check"></i> Save</button>
                            <button type="button" class="btn-cancel-price" style="display:none;"><i class="fa-solid fa-xmark"></i> Cancel</button>
                        </div>
                    </div>
                </div>
 
                <!-- Card 4: Non-Coffee Booth Package -->
                <div class="service-card" data-id="4">
                    <div class="card-image-wrap">
                        <img src="nonCoffee.jpg" alt="Non-Coffee Booth Package">
                    </div>
                    <div class="card-body">
                        <h3 class="service-title">Non-Coffee Booth Package</h3>
                        <p class="service-desc">A refreshment booth serving fruit teas, milk drinks, and mocktails for your guests.</p>
 
                        <!-- Price (view mode) -->
                        <div class="price-label" data-price="4250">
                            Starting at ₱<span class="price-value">4,250</span> <span class="price-unit">/ package</span>
                        </div>
 
                        <!-- Price (edit mode) -->
                        <div class="price-edit" style="display:none;">
                            <span class="peso">₱</span>
                            <input type="number" class="price-input" min="0" step="1">
                            <span class="price-unit-edit">/ package</span>
                        </div>
 
                        <div class="card-actions">
                            <button type="button" class="btn-edit-price"><i class="fa-solid fa-pen"></i> Edit Price</button>
                            <button type="button" class="btn-save-price" style="display:none;"><i class="fa-solid fa-check"></i> Save</button>
                            <button type="button" class="btn-cancel-price" style="display:none;"><i class="fa-solid fa-xmark"></i> Cancel</button>
                        </div>
                    </div>
                </div>
 
                <!-- Card 5: Coffee Booth Package -->
                <div class="service-card" data-id="5">
                    <div class="card-image-wrap">
                        <img src="coffeeBooth.jpg" alt="Coffee Booth Package">
                    </div>
                    <div class="card-body">
                        <h3 class="service-title">Coffee Booth Package</h3>
                        <p class="service-desc">A coffee station serving freshly brewed coffee and refreshing specialty drinks for your guests.</p>
 
                        <!-- Price (view mode) -->
                        <div class="price-label" data-price="6250">
                            Starting at ₱<span class="price-value">6,250</span> <span class="price-unit">/ package</span>
                        </div>
 
                        <!-- Price (edit mode) -->
                        <div class="price-edit" style="display:none;">
                            <span class="peso">₱</span>
                            <input type="number" class="price-input" min="0" step="1">
                            <span class="price-unit-edit">/ package</span>
                        </div>
 
                        <div class="card-actions">
                            <button type="button" class="btn-edit-price"><i class="fa-solid fa-pen"></i> Edit Price</button>
                            <button type="button" class="btn-save-price" style="display:none;"><i class="fa-solid fa-check"></i> Save</button>
                            <button type="button" class="btn-cancel-price" style="display:none;"><i class="fa-solid fa-xmark"></i> Cancel</button>
                        </div>
                    </div>
                </div>
 
                <!-- Card 6: Pizza Booth -->
                <div class="service-card" data-id="6">
                    <div class="card-image-wrap">
                        <img src="pio1.jpg" alt="Pizza Booth">
                    </div>
                    <div class="card-body">
                        <h3 class="service-title">Pizza Booth</h3>
                        <p class="service-desc">A pizza station serving freshly prepared, delicious pizzas and tasty treats, perfect for sharing with your guests.</p>
 
                        <!-- Price (view mode) -->
                        <div class="price-label" data-price="7500">
                            Starting at ₱<span class="price-value">7,500</span> <span class="price-unit">/ package</span>
                        </div>
 
                        <!-- Price (edit mode) -->
                        <div class="price-edit" style="display:none;">
                            <span class="peso">₱</span>
                            <input type="number" class="price-input" min="0" step="1">
                            <span class="price-unit-edit">/ package</span>
                        </div>
 
                        <div class="card-actions">
                            <button type="button" class="btn-edit-price"><i class="fa-solid fa-pen"></i> Edit Price</button>
                            <button type="button" class="btn-save-price" style="display:none;"><i class="fa-solid fa-check"></i> Save</button>
                            <button type="button" class="btn-cancel-price" style="display:none;"><i class="fa-solid fa-xmark"></i> Cancel</button>
                        </div>
                    </div>
                </div>
 
                <!-- Card 7: Intimate Celebration Package -->
                <div class="service-card" data-id="7">
                    <div class="card-image-wrap">
                        <img src="pio1.jpg" alt="Intimate Celebration Package">
                    </div>
                    <div class="card-body">
                        <h3 class="service-title">Intimate Celebration Package</h3>
                        <p class="service-desc">A cozy, scaled-down package for smaller celebrations with close family and friends.</p>
 
                        <!-- Price (view mode) -->
                        <div class="price-label" data-price="4000">
                            Starting at ₱<span class="price-value">4,000</span> <span class="price-unit">/ package</span>
                        </div>
 
                        <!-- Price (edit mode) -->
                        <div class="price-edit" style="display:none;">
                            <span class="peso">₱</span>
                            <input type="number" class="price-input" min="0" step="1">
                            <span class="price-unit-edit">/ package</span>
                        </div>
 
                        <div class="card-actions">
                            <button type="button" class="btn-edit-price"><i class="fa-solid fa-pen"></i> Edit Price</button>
                            <button type="button" class="btn-save-price" style="display:none;"><i class="fa-solid fa-check"></i> Save</button>
                            <button type="button" class="btn-cancel-price" style="display:none;"><i class="fa-solid fa-xmark"></i> Cancel</button>
                        </div>
                    </div>
                </div>
 
                <!-- Card 8: Equipment Rental -->
                <div class="service-card" data-id="8">
                    <div class="card-image-wrap">
                        <img src="pio1.jpg" alt="Equipment Rental">
                    </div>
                    <div class="card-body">
                        <h3 class="service-title">Equipment Rental</h3>
                        <p class="service-desc">Tables, chairs, tents, food warmers, and other essentials to keep your event comfortable, organized, and ready for any occasion.</p>
 
                        <!-- Price (view mode) -->
                        <div class="price-label" data-price="300">
                            Starting at ₱<span class="price-value">300</span> <span class="price-unit">/ item</span>
                        </div>
 
                        <!-- Price (edit mode) -->
                        <div class="price-edit" style="display:none;">
                            <span class="peso">₱</span>
                            <input type="number" class="price-input" min="0" step="1">
                            <span class="price-unit-edit">/ item</span>
                        </div>
 
                        <div class="card-actions">
                            <button type="button" class="btn-edit-price"><i class="fa-solid fa-pen"></i> Edit Price</button>
                            <button type="button" class="btn-save-price" style="display:none;"><i class="fa-solid fa-check"></i> Save</button>
                            <button type="button" class="btn-cancel-price" style="display:none;"><i class="fa-solid fa-xmark"></i> Cancel</button>
                        </div>
                    </div>
                </div>
 
            </div>
 
        </div>
    </div>
 
    <script>
    let csrfToken = '';

    const formatPeso = n => Number(n).toLocaleString('en-PH');

    function loadServicesFromDatabase() {
        fetch('../api/admin/services.php', {
            method: 'GET',
            headers: { 'Accept': 'application/json' },
            credentials: 'same-origin'
        })
        .then(res => {
            if (res.status === 401 || res.status === 403) {
                window.location.href = 'index.php';
                throw new Error('Unauthorized');
            }
            if (!res.ok) {
                throw new Error('Failed to load services');
            }
            const tokenHeader = res.headers.get('X-CSRF-Token');
            if (tokenHeader) {
                csrfToken = tokenHeader;
            }
            return res.json();
        })
        .then(data => {
            if (!data.ok || !data.services) {
                throw new Error('Invalid server response');
            }
            if (data.csrf_token) {
                csrfToken = data.csrf_token;
            }

            data.services.forEach(svc => {
                const card = document.querySelector(`.service-card[data-service-id="${svc.id}"]`) || 
                             document.querySelector(`.service-card[data-id="${svc.id}"]`);
                if (card) {
                    card.dataset.id = svc.id;
                    const priceLabel = card.querySelector('.price-label');
                    const priceValue = card.querySelector('.price-value');
                    if (priceLabel && priceValue) {
                        priceLabel.dataset.price = svc.base_price;
                        priceValue.textContent = formatPeso(svc.base_price);
                    }
                }
            });
        })
        .catch(err => {
            console.error('Error loading services:', err);
        });
    }

    function savePriceToServer(id, price) {
        return fetch('../api/admin/services.php', {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken
            },
            credentials: 'same-origin',
            body: JSON.stringify({ id: id, base_price: price })
        })
        .then(res => {
            if (res.status === 401 || res.status === 403) {
                alert('Session expired or unauthorized. Please log in again.');
                window.location.href = 'index.php';
                throw new Error('Unauthorized');
            }
            return res.json();
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        loadServicesFromDatabase();
    });

    document.querySelectorAll('.service-card').forEach(card => {
        const priceLabel = card.querySelector('.price-label');
        const priceValue = card.querySelector('.price-value');
        const priceEdit  = card.querySelector('.price-edit');
        const priceInput = card.querySelector('.price-input');
        const btnEdit    = card.querySelector('.btn-edit-price');
        const btnSave    = card.querySelector('.btn-save-price');
        const btnCancel  = card.querySelector('.btn-cancel-price');

        function enterEditMode() {
            priceInput.value = priceLabel.dataset.price;
            priceLabel.style.display = 'none';
            priceEdit.style.display  = 'flex';
            btnEdit.style.display    = 'none';
            btnSave.style.display    = 'inline-flex';
            btnCancel.style.display  = 'inline-flex';
            priceInput.focus();
            priceInput.select();
        }

        function exitEditMode() {
            priceLabel.style.display = '';
            priceEdit.style.display  = 'none';
            btnEdit.style.display    = 'inline-flex';
            btnSave.style.display    = 'none';
            btnCancel.style.display  = 'none';
        }

        function applyPrice(newPrice) {
            priceLabel.dataset.price = newPrice;
            priceValue.textContent   = formatPeso(newPrice);
            exitEditMode();
        }

        btnEdit.addEventListener('click', enterEditMode);
        btnCancel.addEventListener('click', exitEditMode);

        btnSave.addEventListener('click', () => {
            const newPrice = parseFloat(priceInput.value);

            if (isNaN(newPrice) || newPrice < 0) {
                alert('Maglagay ng valid na presyo.');
                return;
            }

            btnSave.disabled = true;
            const originalHtml = btnSave.innerHTML;
            btnSave.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';

            const serviceId = card.dataset.id || card.dataset.serviceId;

            savePriceToServer(serviceId, newPrice)
                .then(data => {
                    if (data && data.ok) {
                        applyPrice(newPrice);
                    } else {
                        alert('Hindi na-save ang presyo: ' + (data.reason || 'Unknown error'));
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert('May error sa connection.');
                })
                .finally(() => {
                    btnSave.disabled = false;
                    btnSave.innerHTML = originalHtml;
                });
        });

        // Enter = save, Escape = cancel
        priceInput.addEventListener('keydown', e => {
            if (e.key === 'Enter')  btnSave.click();
            if (e.key === 'Escape') btnCancel.click();
        });
    });
    </script>
 
</body>
</html>