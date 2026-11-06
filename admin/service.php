<?php require_once __DIR__ . '/auth_check.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Service Management - Pio De Roda Cafe</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="service.css" />
    <style>
        /* ---- Additions for the editable catalog (kept scoped to this page) ---- */
        .page-title-group h1 {
            font-size: 26px;
            color: var(--text-dark);
            margin-bottom: 6px;
        }
        .page-title-group .page-sub {
            font-size: 13px;
            color: var(--text-muted);
            max-width: 720px;
            line-height: 1.55;
        }
        .alert {
            margin: 0 0 20px;
            padding: 12px 16px;
            border-radius: var(--radius-md);
            font-size: 13px;
            font-weight: 600;
            border: 1px solid transparent;
        }
        .alert.success { background: #e8f1e3; color: #3c5a2f; border-color: #c6ddb8; }
        .alert.error { background: #fdeaea; color: #8a1c1c; border-color: #f0c4c4; }
        .badge {
            display: inline-block;
            padding: 3px 9px;
            border-radius: 999px;
            font-size: 10.5px;
            font-weight: 700;
            letter-spacing: .3px;
            text-transform: uppercase;
        }
        .badge-on { background: #e3efdb; color: #3f5f2f; }
        .badge-off { background: #f3dede; color: #8a2626; }
        .svc-title-row { display: flex; align-items: flex-start; justify-content: space-between; gap: 8px; }
        .svc-title-row .service-title { margin-bottom: 8px; }
        .svc-id, .svc-counts { font-size: 11px; color: var(--text-muted); margin-bottom: 4px; }
        .svc-id code { background: rgba(91,8,8,.07); padding: 1px 5px; border-radius: 4px; }
        .service-card.is-inactive { opacity: .78; }
        .img-flag {
            position: absolute; top: 10px; left: 10px;
            background: rgba(91,8,8,.92); color: #fff;
            font-size: 10px; font-weight: 700; letter-spacing: .4px;
            padding: 3px 9px; border-radius: 999px; text-transform: uppercase;
        }
        .svc-form { border-top: 1px dashed var(--border-soft); margin-top: 16px; padding-top: 16px; }
        .form-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px 12px; }
        @media (max-width: 560px) { .form-grid { grid-template-columns: 1fr; } }
        .svc-form label, .item-form label {
            display: flex; flex-direction: column; gap: 4px;
            font-size: 11.5px; font-weight: 600; color: var(--text-muted);
        }
        .svc-form input, .svc-form textarea,
        .item-form input, .item-form textarea, .item-form select {
            width: 100%;
            padding: 8px 10px;
            border: 1.5px solid var(--border-soft);
            border-radius: 8px;
            font-family: var(--font-body);
            font-size: 13px;
            color: var(--text-dark);
            background: #fff;
        }
        .svc-form input:focus, .svc-form textarea:focus,
        .item-form input:focus, .item-form textarea:focus, .item-form select:focus {
            outline: none; border-color: var(--maroon);
            box-shadow: 0 0 0 3px rgba(91,8,8,.12);
        }
        .form-row { margin-bottom: 12px; }
        .form-actions { display: flex; gap: 8px; margin-top: 14px; }
        .hint { font-size: 11px; color: var(--text-muted); margin-top: 6px; line-height: 1.45; }
        .cd-box { margin-top: 14px; border: 1px solid var(--border-soft); border-radius: 10px; padding: 10px 12px; background: rgba(255,255,255,.5); }
        .cd-box summary { cursor: pointer; font-size: 12px; font-weight: 700; color: var(--maroon); }
        .rates-editor { margin: 12px 0; display: flex; flex-direction: column; gap: 7px; }
        .rates-head, .rate-row { display: grid; grid-template-columns: 1fr 1fr 26px; gap: 7px; align-items: center; }
        .rates-head { font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .3px; color: var(--text-muted); }
        .rate-remove {
            border: none; background: #f1e2e2; color: #8a2626;
            width: 26px; height: 26px; border-radius: 6px; cursor: pointer; font-size: 14px; line-height: 1;
        }
        .rate-remove:hover { background: #e6cccc; }
        .mini-btn {
            align-self: flex-start; border: 1px dashed var(--maroon); color: var(--maroon);
            background: transparent; border-radius: 7px; padding: 6px 10px;
            font-size: 11.5px; font-weight: 600; cursor: pointer;
        }
        .mini-btn:hover { background: rgba(91,8,8,.06); }

        /* ---- Catalog manager ---- */
        .catalog-panel {
            margin-top: 40px;
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-soft);
            padding: 24px;
        }
        .catalog-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 20px; flex-wrap: wrap; }
        .catalog-head h2 { font-size: 20px; margin-bottom: 4px; }
        .catalog-head p { font-size: 12.5px; color: var(--text-muted); max-width: 560px; line-height: 1.5; }
        .svc-select { display: flex; flex-direction: column; gap: 5px; font-size: 11.5px; font-weight: 700; color: var(--text-muted); }
        .svc-select select {
            min-width: 220px; padding: 9px 11px; border-radius: 9px;
            border: 1.5px solid var(--border-soft); background: #fff;
            font-size: 13px; font-weight: 600; color: var(--text-dark);
        }
        .catalog-toolbar {
            display: flex; align-items: center; justify-content: space-between;
            gap: 14px; flex-wrap: wrap; margin: 20px 0 14px;
            border-bottom: 1px solid var(--border-color); padding-bottom: 14px;
        }
        .catalog-tabs { display: flex; gap: 6px; }
        .tab-btn {
            border: 1.5px solid var(--border-soft); background: transparent;
            color: var(--text-muted); padding: 8px 16px; border-radius: 999px;
            font-size: 12.5px; font-weight: 700; cursor: pointer;
        }
        .tab-btn:hover { border-color: var(--maroon); color: var(--maroon); }
        .tab-btn.active { background: var(--primary-burgundy); border-color: var(--primary-burgundy); color: var(--text-light); }
        .catalog-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .catalog-table th {
            text-align: left; font-size: 10.5px; text-transform: uppercase; letter-spacing: .4px;
            color: var(--text-muted); padding: 8px 10px; border-bottom: 1.5px solid var(--border-soft);
        }
        .catalog-table td { padding: 10px; border-bottom: 1px solid var(--border-soft); vertical-align: top; }
        .catalog-table tr:last-child td { border-bottom: none; }
        .catalog-table .nm { font-weight: 700; color: var(--text-dark); }
        .catalog-table .code { display: block; font-size: 10.5px; color: var(--text-muted); margin-top: 2px; }
        .catalog-table .row-actions { display: flex; gap: 6px; flex-wrap: wrap; }
        .catalog-table .row-actions button {
            border: 1.5px solid var(--maroon); background: transparent; color: var(--maroon);
            padding: 5px 10px; border-radius: 7px; font-size: 11px; font-weight: 600; cursor: pointer;
        }
        .catalog-table .row-actions button:hover { background: var(--maroon); color: var(--text-light); }
        .catalog-table .row-actions button.danger { border-color: #8a2626; color: #8a2626; }
        .catalog-table .row-actions button.danger:hover { background: #8a2626; color: #fff; }
        .catalog-table .row-actions button.good { border-color: #3f5f2f; color: #3f5f2f; }
        .catalog-table .row-actions button.good:hover { background: #3f5f2f; color: #fff; }
        .edit-row td { background: rgba(91,8,8,.035); }
        .item-form { display: flex; flex-direction: column; gap: 10px; }
        .item-form .form-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        @media (max-width: 900px) { .item-form .form-grid { grid-template-columns: 1fr; } }
        .item-form .form-grid.two { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .item-form .checks { display: flex; gap: 18px; align-items: center; flex-wrap: wrap; }
        .item-form .checks label { flex-direction: row; align-items: center; gap: 7px; font-size: 12px; color: var(--text-dark); }
        .item-form .checks input { width: auto; }
        .empty-note { font-size: 13px; color: var(--text-muted); padding: 14px 4px; }
        .dishes-count { display: inline-block; background: rgba(91,8,8,.07); border-radius: 999px; padding: 2px 8px; font-size: 11px; font-weight: 600; }
    </style>
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
                <div class="page-title-group">
                    <h1>Service Management</h1>
                    <p class="page-sub">
                        Edit service details, starting prices, customer display text and visibility, then manage the
                        packages, menus and add-ons customers choose from. Deactivating an item hides it from new
                        bookings without deleting anything &mdash; historical reservations keep their original details.
                    </p>
                </div>
                <button class="btn-add-new" id="btnReload"><i class="fa-solid fa-rotate"></i> Reload</button>
            </div>

            <div class="alert" id="alertBox" hidden></div>

            <div class="services-grid" id="servicesGrid">
                <p class="empty-note">Loading services&hellip;</p>
            </div>

            <!-- ============ PACKAGES / MENUS / ADD-ONS MANAGER ============ -->
            <section class="catalog-panel" id="catalogPanel">
                <div class="catalog-head">
                    <div>
                        <h2>Packages, Menus &amp; Add-ons</h2>
                        <p>
                            These are the exact rows customers book from, and the prices here drive new booking
                            totals. The customer RATES table is separate display copy &mdash; update it with
                            &ldquo;Edit details&rdquo;. Deactivated items are hidden from the customer site and
                            rejected by the booking API; they are never permanently deleted.
                        </p>
                    </div>
                    <label class="svc-select">Service
                        <select id="catalogService"></select>
                    </label>
                </div>

                <div class="catalog-toolbar">
                    <div class="catalog-tabs" id="catalogTabs">
                        <button type="button" class="tab-btn active" data-tab="packages">Packages</button>
                        <button type="button" class="tab-btn" data-tab="menus">Menus</button>
                        <button type="button" class="tab-btn" data-tab="addons">Add-ons</button>
                    </div>
                    <button type="button" class="btn-add-new" id="btnAddItem"><i class="fa-solid fa-plus"></i> Add Package</button>
                </div>

                <div id="catalogTable">
                    <p class="empty-note">Loading catalog&hellip;</p>
                </div>
            </section>

        </div>
    </div>

    <script>
    (function () {
        'use strict';

        /* ------------------------------------------------------------------ state */
        const state = {
            csrf: '',
            services: [],
            catalogServiceId: null,
            tab: 'packages',
            itemDraft: null, // { kind, id } ; id === null means "new item"
            editServiceId: null,
        };

        const SERVICE_ORDER = [
            'catering-menu', 'venue', 'dinein', 'noncoffee',
            'coffee', 'pizza', 'intimate', 'equipment',
        ];

        /* Same pictures the cards already used (kept in the admin folder). */
        const SERVICE_IMAGES = {
            'catering-menu': 'cateringwithmenu.jpg',
            venue: 'venueRentals.jpg',
            dinein: 'dineInMenu.jpg',
            noncoffee: 'nonCoffee.jpg',
            coffee: 'coffeeBooth.jpg',
            pizza: 'pio1.jpg',
            intimate: 'pio1.jpg',
            equipment: 'pio1.jpg',
        };

        /* Presentation-only price suffixes (amounts always come from the DB). */
        const PRICE_UNITS = {
            'catering-menu': ' / head',
            venue: '',
            dinein: ' / head',
            noncoffee: ' / package',
            coffee: ' / package',
            pizza: ' / package',
            intimate: ' / package',
            equipment: ' / item',
        };

        const TAB_LABELS = { packages: 'Package', menus: 'Menu', addons: 'Add-on' };
        const TAB_ACTION = { packages: 'package_save', menus: 'menu_save', addons: 'addon_save' };
        const TAB_KIND = { packages: 'package', menus: 'menu', addons: 'addon' };

        const REASON_TEXT = {
            UNAUTHORIZED: 'Your session expired. Please log in again.',
            INVALID_CSRF_TOKEN: 'Your session token expired — reload the page and try again.',
            INVALID_NAME: 'Service name is required (maximum 100 characters).',
            INVALID_DESCRIPTION: 'The description is too long.',
            INVALID_BASE_PRICE: 'Enter a valid base price (0 or higher).',
            INVALID_PER_HEAD: 'Enter a valid per-head rate (0 or higher) or leave it blank.',
            INVALID_MAX_CAP: 'Enter a valid maximum capacity (1 or higher) or leave it blank.',
            INVALID_PRICE_DISPLAY: 'The price display is too long (maximum 50 characters).',
            INVALID_CUSTOMER_DISPLAY: 'Check the customer display fields: every RATES row needs a label and value, and notes/main courses must stay within the limits.',
            NOTHING_TO_UPDATE: 'No changes were detected.',
            INVALID_PRICE: 'Enter a valid price (0 or higher).',
            INVALID_PACKAGE_NAME: 'Package name is required (maximum 150 characters).',
            INVALID_PACKAGE_CODE: 'Package codes may only contain letters, numbers and dashes.',
            DUPLICATE_CODE: 'That code is already used by another item of this service.',
            INVALID_ITEM_NAME: 'Menu item name is required (maximum 150 characters).',
            INVALID_CATEGORY_NAME: 'Menu category is required (maximum 100 characters).',
            INVALID_MENU_CODE: 'Menu codes may only contain letters, numbers and dashes.',
            INVALID_DISHES: 'Check the dishes list (one dish per line, maximum 100 dishes).',
            INVALID_ADDON_NAME: 'Add-on name is required (maximum 100 characters).',
            INVALID_FIXED_PRICE: 'Enter a valid fixed price (0 or higher).',
            UNPRICEABLE_ADDON: 'Choose a pricing source: a linked service or a fixed price.',
            AMBIGUOUS_PRICING: 'Use only one pricing source (linked service or fixed price).',
            INVALID_PRICE_FROM: 'The linked pricing service does not exist.',
            INVALID_SORT_ORDER: 'Sort order must be a whole number (0 or higher).',
            UNKNOWN_ACTION: 'Unsupported action.',
            SERVER_ERROR: 'The server could not complete the request. Please try again.',
        };

        /* ---------------------------------------------------------------- helpers */
        const $ = (sel, root) => (root || document).querySelector(sel);

        function esc(value) {
            return String(value == null ? '' : value).replace(/[&<>"']/g, (ch) => ({
                '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
            })[ch]);
        }

        function fmtPeso(value) {
            const n = Number(value);
            if (!Number.isFinite(n)) return '₱0';
            return '₱' + n.toLocaleString('en-US');
        }

        function splitLines(text) {
            return String(text == null ? '' : text)
                .split(/\r?\n/)
                .map((line) => line.trim())
                .filter((line) => line !== '');
        }

        function showAlert(type, message) {
            const box = $('#alertBox');
            if (!box) return;
            box.className = 'alert ' + type;
            box.textContent = message;
            box.hidden = false;
            if (type === 'success') {
                window.clearTimeout(showAlert._timer);
                showAlert._timer = window.setTimeout(() => { box.hidden = true; }, 4500);
            }
            try { box.scrollIntoView({ behavior: 'smooth', block: 'nearest' }); } catch (e) { /* older browsers */ }
        }

        function friendlyError(reason) {
            return REASON_TEXT[reason] || ('Request failed (' + reason + ').');
        }

        async function apiRequest(method, body) {
            const headers = { Accept: 'application/json', 'X-CSRF-Token': state.csrf };
            if (body) headers['Content-Type'] = 'application/json';
            const res = await fetch('../api/admin/services.php', {
                method,
                headers,
                credentials: 'same-origin',
                body: body ? JSON.stringify(body) : undefined,
            });
            if (res.status === 401) {
                window.location.href = 'index.php';
                throw new Error('UNAUTHORIZED');
            }
            let data;
            try {
                data = await res.json();
            } catch (e) {
                throw new Error('INVALID_RESPONSE');
            }
            if (!data || data.ok !== true) {
                throw new Error((data && data.reason) || 'SERVER_ERROR');
            }
            return data;
        }

        function sortedServices() {
            return state.services.slice().sort((a, b) => {
                const ra = SERVICE_ORDER.indexOf(a.id);
                const rb = SERVICE_ORDER.indexOf(b.id);
                const va = ra === -1 ? SERVICE_ORDER.length + 100 : ra;
                const vb = rb === -1 ? SERVICE_ORDER.length + 100 : rb;
                if (va !== vb) return va - vb;
                return String(a.name).localeCompare(String(b.name));
            });
        }

        function currentService() {
            return state.services.find((svc) => svc.id === state.catalogServiceId) || null;
        }

        /* ------------------------------------------------------------------ load */
        async function loadCatalog() {
            const data = await apiRequest('GET');
            state.csrf = data.csrf_token || state.csrf;
            state.services = Array.isArray(data.services) ? data.services : [];

            if (!state.catalogServiceId || !state.services.some((svc) => svc.id === state.catalogServiceId)) {
                const first = sortedServices()[0];
                state.catalogServiceId = first ? first.id : null;
            }

            renderServices();
            renderServiceSelect();
            renderTabs();
            renderCatalog();
        }

        /* ============================================================ service cards */
        function renderServices() {
            const grid = $('#servicesGrid');
            if (!grid) return;
            if (!state.services.length) {
                grid.innerHTML = '<p class="empty-note">No services found in the database.</p>';
                return;
            }
            grid.innerHTML = sortedServices().map((svc) => (
                state.editServiceId === svc.id ? renderServiceEditCard(svc) : renderServiceViewCard(svc)
            )).join('');
        }

        function renderServiceViewCard(svc) {
            const unit = esc(PRICE_UNITS[svc.id] || '');
            const priceText = svc.base_price === null
                ? 'Price to be confirmed'
                : 'Starting at ' + fmtPeso(svc.base_price);
            const itemCount = svc.packages.length + svc.menus.length + svc.addons.length;

            return `
                <article class="service-card${svc.is_active ? '' : ' is-inactive'}" data-service-id="${esc(svc.id)}">
                    <div class="card-image-wrap">
                        <img src="${esc(SERVICE_IMAGES[svc.id] || 'pio1.jpg')}" alt="${esc(svc.name)}">
                        ${svc.is_active ? '' : '<span class="img-flag">Inactive</span>'}
                    </div>
                    <div class="card-body">
                        <div class="svc-title-row">
                            <h3 class="service-title">${esc(svc.name)}</h3>
                            <span class="badge ${svc.is_active ? 'badge-on' : 'badge-off'}">${svc.is_active ? 'Active' : 'Inactive'}</span>
                        </div>
                        <p class="service-desc">${esc(svc.description || '')}</p>
                        <div class="price-label">${esc(priceText)}<span class="price-unit">${unit}</span></div>
                        <p class="svc-id">Service ID: <code>${esc(svc.id)}</code></p>
                        <p class="svc-counts">${svc.packages.length} packages &middot; ${svc.menus.length} menus &middot; ${svc.addons.length} add-ons</p>
                        <div class="card-actions">
                            <button type="button" class="btn-edit-price" data-action="edit-service"><i class="fa-solid fa-pen"></i> Edit details</button>
                            <button type="button" class="${svc.is_active ? 'btn-cancel-price' : 'btn-save-price'}" data-action="toggle-service">
                                <i class="fa-solid ${svc.is_active ? 'fa-eye-slash' : 'fa-eye'}"></i> ${svc.is_active ? 'Deactivate' : 'Activate'}
                            </button>
                            <button type="button" class="btn-edit-price" data-action="manage-items"><i class="fa-solid fa-boxes-stacked"></i> Manage items (${itemCount})</button>
                        </div>
                    </div>
                </article>`;
        }

        function rateRowHtml(row) {
            const label = esc(Array.isArray(row) ? row[0] : '');
            const value = esc(Array.isArray(row) ? row[1] : '');
            return `
                <div class="rate-row">
                    <input type="text" class="rate-label" maxlength="80" placeholder="Label (e.g. Classic Package)" value="${label}">
                    <input type="text" class="rate-value" maxlength="80" placeholder="Value (e.g. ₱7,250)" value="${value}">
                    <button type="button" class="rate-remove" data-action="remove-rate" aria-label="Remove rate row">&times;</button>
                </div>`;
        }

        function renderServiceEditCard(svc) {
            const cd = svc.customer_display && typeof svc.customer_display === 'object' ? svc.customer_display : {};
            const rates = Array.isArray(cd.rates) ? cd.rates : [];
            const notes = Array.isArray(cd.notes) ? cd.notes : [];
            const mainCourses = Array.isArray(cd.mainCourses) ? cd.mainCourses : [];

            return `
                <article class="service-card" data-service-id="${esc(svc.id)}">
                    <div class="card-image-wrap">
                        <img src="${esc(SERVICE_IMAGES[svc.id] || 'pio1.jpg')}" alt="${esc(svc.name)}">
                        <span class="img-flag">Editing</span>
                    </div>
                    <div class="card-body">
                        <form class="svc-form" data-service-id="${esc(svc.id)}">
                            <div class="form-row">
                                <label>Service name
                                    <input type="text" name="name" maxlength="100" required value="${esc(svc.name)}">
                                </label>
                            </div>
                            <div class="form-row">
                                <label>Description
                                    <textarea name="description" rows="3" maxlength="5000">${esc(svc.description || '')}</textarea>
                                </label>
                            </div>
                            <div class="form-grid">
                                <label>Base price (₱)
                                    <input type="number" name="base_price" min="0" step="0.01" value="${svc.base_price === null ? '' : esc(svc.base_price)}">
                                </label>
                                <label>Price display
                                    <input type="text" name="price_display" maxlength="50" value="${esc(svc.price_display || '')}">
                                </label>
                                <label>Per-head rate (₱)
                                    <input type="number" name="per_head" min="0" step="0.01" value="${svc.per_head === null ? '' : esc(svc.per_head)}">
                                </label>
                                <label>Max capacity
                                    <input type="number" name="max_cap" min="1" step="1" value="${svc.max_cap === null ? '' : esc(svc.max_cap)}">
                                </label>
                            </div>
                            <p class="hint">The price display is the &ldquo;starting price&rdquo; text customers see. It follows the base price unless you type custom text.</p>

                            <details class="cd-box">
                                <summary>Customer display &mdash; RATES table, notes &amp; main courses</summary>
                                <div class="rates-editor">
                                    <div class="rates-head"><span>Rates label</span><span>Rates value</span><span></span></div>
                                    ${rates.map(rateRowHtml).join('')}
                                    <button type="button" class="mini-btn" data-action="add-rate"><i class="fa-solid fa-plus"></i> Add rate row</button>
                                </div>
                                <div class="form-grid two">
                                    <label>Notes (one per line)
                                        <textarea name="notes" rows="5" maxlength="15000">${esc(notes.join('\n'))}</textarea>
                                    </label>
                                    <label>Main courses (one per line)
                                        <textarea name="main_courses" rows="5" maxlength="15000">${esc(mainCourses.join('\n'))}</textarea>
                                    </label>
                                </div>
                                <p class="hint">Leave a list empty to clear it. The inclusions table and other display blocks are preserved unchanged.</p>
                            </details>

                            <div class="form-actions">
                                <button type="submit" class="btn-save-price"><i class="fa-solid fa-check"></i> Save service</button>
                                <button type="button" class="btn-cancel-price" data-action="cancel-edit"><i class="fa-solid fa-xmark"></i> Cancel</button>
                            </div>
                        </form>
                    </div>
                </article>`;
        }

        /* ============================================================ catalog tables */
        function renderServiceSelect() {
            const select = $('#catalogService');
            if (!select) return;
            select.innerHTML = sortedServices().map((svc) => (
                '<option value="' + esc(svc.id) + '"' + (svc.id === state.catalogServiceId ? ' selected' : '') + '>'
                + esc(svc.name) + (svc.is_active ? '' : ' (inactive)')
                + '</option>'
            )).join('');
        }

        function renderTabs() {
            document.querySelectorAll('#catalogTabs .tab-btn').forEach((btn) => {
                btn.classList.toggle('active', btn.dataset.tab === state.tab);
            });
            const addBtn = $('#btnAddItem');
            if (addBtn) {
                addBtn.innerHTML = '<i class="fa-solid fa-plus"></i> Add ' + TAB_LABELS[state.tab];
            }
        }

        function renderCatalog() {
            const wrap = $('#catalogTable');
            if (!wrap) return;
            const svc = currentService();
            if (!svc) {
                wrap.innerHTML = '<p class="empty-note">No service selected.</p>';
                return;
            }

            if (state.tab === 'packages') wrap.innerHTML = renderPackagesTable(svc);
            else if (state.tab === 'menus') wrap.innerHTML = renderMenusTable(svc);
            else wrap.innerHTML = renderAddonsTable(svc);
        }

        function statusBadge(active) {
            return '<span class="badge ' + (active ? 'badge-on' : 'badge-off') + '">' + (active ? 'Active' : 'Inactive') + '</span>';
        }

        function isDraft(kind, id) {
            return state.itemDraft && state.itemDraft.kind === kind && state.itemDraft.id === id;
        }

        /* ---------------------------------------------------------------- packages */
        function renderPackagesTable(svc) {
            const rows = [];
            if (isDraft('packages', null)) rows.push(packageFormRow(null));
            svc.packages.forEach((pkg) => {
                if (isDraft('packages', pkg.id)) rows.push(packageFormRow(pkg));
                else rows.push(packageRow(pkg));
            });

            return `
                <table class="catalog-table">
                    <thead><tr><th>Package</th><th>Price</th><th>Max cap</th><th>Status</th><th>Actions</th></tr></thead>
                    <tbody>
                        ${rows.length ? rows.join('') : '<tr><td colspan="5"><p class="empty-note">No packages yet for this service.</p></td></tr>'}
                    </tbody>
                </table>`;
        }

        function packageRow(pkg) {
            return `
                <tr>
                    <td><span class="nm">${esc(pkg.package_name)}</span><span class="code">${esc(pkg.package_code)}</span></td>
                    <td>${fmtPeso(pkg.price)}</td>
                    <td>${pkg.max_cap === null ? '&mdash;' : esc(pkg.max_cap)}</td>
                    <td>${statusBadge(pkg.is_active)}</td>
                    <td>
                        <div class="row-actions">
                            <button type="button" data-action="edit-item" data-id="${esc(pkg.id)}">Edit</button>
                            <button type="button" class="${pkg.is_active ? 'danger' : 'good'}" data-action="toggle-item" data-id="${esc(pkg.id)}" data-active="${pkg.is_active ? '1' : '0'}">${pkg.is_active ? 'Deactivate' : 'Activate'}</button>
                        </div>
                    </td>
                </tr>`;
        }

        function packageFormRow(pkg) {
            const isNew = !pkg;
            return `
                <tr class="edit-row">
                    <td colspan="5">
                        <form class="item-form" data-kind="packages" data-id="${isNew ? '' : esc(pkg.id)}">
                            <div class="form-grid">
                                <label>Package name
                                    <input type="text" name="package_name" maxlength="150" required value="${esc(isNew ? '' : pkg.package_name)}">
                                </label>
                                <label>Package code (optional)
                                    <input type="text" name="package_code" maxlength="100" placeholder="auto-generated from name" value="${esc(isNew ? '' : pkg.package_code)}">
                                </label>
                                <label>Price (₱)
                                    <input type="number" name="price" min="0" step="0.01" required value="${isNew ? '' : esc(pkg.price)}">
                                </label>
                                <label>Max capacity (optional)
                                    <input type="number" name="max_cap" min="1" step="1" value="${isNew || pkg.max_cap === null ? '' : esc(pkg.max_cap)}">
                                </label>
                                <label>Sort order
                                    <input type="number" name="sort_order" min="0" step="1" value="${isNew ? '' : esc(pkg.sort_order)}">
                                </label>
                            </div>
                            <div class="checks">
                                <label><input type="checkbox" name="is_active" ${!isNew && !pkg.is_active ? '' : 'checked'}> Active (visible to customers)</label>
                            </div>
                            <div class="form-actions">
                                <button type="submit" class="btn-save-price"><i class="fa-solid fa-check"></i> ${isNew ? 'Create package' : 'Save package'}</button>
                                <button type="button" class="btn-cancel-price" data-action="cancel-item">Cancel</button>
                            </div>
                        </form>
                    </td>
                </tr>`;
        }

        /* ------------------------------------------------------------------- menus */
        function renderMenusTable(svc) {
            const rows = [];
            if (isDraft('menus', null)) rows.push(menuFormRow(null));
            svc.menus.forEach((menu) => {
                if (isDraft('menus', menu.id)) rows.push(menuFormRow(menu));
                else rows.push(menuRow(menu));
            });

            return `
                <table class="catalog-table">
                    <thead><tr><th>Menu</th><th>Code</th><th>Price</th><th>Dishes</th><th>Status</th><th>Actions</th></tr></thead>
                    <tbody>
                        ${rows.length ? rows.join('') : '<tr><td colspan="6"><p class="empty-note">No menus yet for this service.</p></td></tr>'}
                    </tbody>
                </table>`;
        }

        function menuRow(menu) {
            return `
                <tr>
                    <td><span class="nm">${esc(menu.item_name)}</span><span class="code">${esc(menu.category_name)}</span></td>
                    <td><span class="code">${esc(menu.menu_code)}</span></td>
                    <td>${menu.price === null ? '<em>per-head rate</em>' : fmtPeso(menu.price)}</td>
                    <td><span class="dishes-count">${menu.dishes.length} dishes</span></td>
                    <td>${statusBadge(menu.is_active)}</td>
                    <td>
                        <div class="row-actions">
                            <button type="button" data-action="edit-item" data-id="${esc(menu.id)}">Edit</button>
                            <button type="button" class="${menu.is_active ? 'danger' : 'good'}" data-action="toggle-item" data-id="${esc(menu.id)}" data-active="${menu.is_active ? '1' : '0'}">${menu.is_active ? 'Deactivate' : 'Activate'}</button>
                        </div>
                    </td>
                </tr>`;
        }

        function menuFormRow(menu) {
            const isNew = !menu;
            return `
                <tr class="edit-row">
                    <td colspan="6">
                        <form class="item-form" data-kind="menus" data-id="${isNew ? '' : esc(menu.id)}">
                            <div class="form-grid">
                                <label>Menu / item name
                                    <input type="text" name="item_name" maxlength="150" required value="${esc(isNew ? '' : menu.item_name)}">
                                </label>
                                <label>Category
                                    <input type="text" name="category_name" maxlength="100" required value="${esc(isNew ? '' : menu.category_name)}">
                                </label>
                                <label>Menu code (optional)
                                    <input type="text" name="menu_code" maxlength="100" placeholder="auto-generated from name" value="${esc(isNew ? '' : menu.menu_code)}">
                                </label>
                                <label>Price (optional)
                                    <input type="number" name="price" min="0" step="0.01" placeholder="Leave blank for per-head pricing" value="${isNew || menu.price === null ? '' : esc(menu.price)}">
                                </label>
                            </div>
                            <label>Dishes (one per line)
                                <textarea name="dishes" rows="5" placeholder="Steamed Pandan Rice&#10;Soup&#10;Vegetable Recipe">${esc(isNew ? '' : menu.dishes.join('\n'))}</textarea>
                            </label>
                            <div class="checks">
                                <label><input type="checkbox" name="is_active" ${!isNew && !menu.is_active ? '' : 'checked'}> Active (visible to customers)</label>
                            </div>
                            <div class="form-actions">
                                <button type="submit" class="btn-save-price"><i class="fa-solid fa-check"></i> ${isNew ? 'Create menu' : 'Save menu'}</button>
                                <button type="button" class="btn-cancel-price" data-action="cancel-item">Cancel</button>
                            </div>
                        </form>
                    </td>
                </tr>`;
        }

        /* ----------------------------------------------------------------- add-ons */
        function renderAddonsTable(svc) {
            const rows = [];
            if (isDraft('addons', null)) rows.push(addonFormRow(null));
            svc.addons.forEach((addon) => {
                if (isDraft('addons', addon.id)) rows.push(addonFormRow(addon));
                else rows.push(addonRow(addon));
            });

            return `
                <table class="catalog-table">
                    <thead><tr><th>Add-on</th><th>Pricing</th><th>Quantity</th><th>Status</th><th>Actions</th></tr></thead>
                    <tbody>
                        ${rows.length ? rows.join('') : '<tr><td colspan="5"><p class="empty-note">No add-ons yet for this service.</p></td></tr>'}
                    </tbody>
                </table>`;
        }

        function addonPricingText(addon) {
            if (addon.price_from) {
                const linked = state.services.find((svc) => svc.id === addon.price_from);
                return 'Linked: ' + (linked ? linked.name : addon.price_from);
            }
            return addon.fixed_price === null ? 'Unpriced' : 'Fixed ' + fmtPeso(addon.fixed_price);
        }

        function addonRow(addon) {
            return `
                <tr>
                    <td><span class="nm">${esc(addon.name)}</span><span class="code">${esc(addon.id)}</span></td>
                    <td>${esc(addonPricingText(addon))}</td>
                    <td>${addon.allow_qty ? 'Per item' : 'Single'}</td>
                    <td>${statusBadge(addon.is_active)}</td>
                    <td>
                        <div class="row-actions">
                            <button type="button" data-action="edit-item" data-id="${esc(addon.id)}">Edit</button>
                            <button type="button" class="${addon.is_active ? 'danger' : 'good'}" data-action="toggle-item" data-id="${esc(addon.id)}" data-active="${addon.is_active ? '1' : '0'}">${addon.is_active ? 'Deactivate' : 'Activate'}</button>
                        </div>
                    </td>
                </tr>`;
        }

        function addonFormRow(addon) {
            const isNew = !addon;
            const mode = !isNew && !addon.price_from && addon.fixed_price !== null ? 'fixed' : 'linked';
            const serviceOptions = sortedServices().map((svc) => (
                '<option value="' + esc(svc.id) + '"'
                + (!isNew && addon.price_from === svc.id ? ' selected' : '')
                + '>' + esc(svc.name) + '</option>'
            )).join('');

            return `
                <tr class="edit-row">
                    <td colspan="5">
                        <form class="item-form" data-kind="addons" data-id="${isNew ? '' : esc(addon.id)}">
                            <div class="form-grid">
                                <label>Add-on name
                                    <input type="text" name="name" maxlength="100" required value="${esc(isNew ? '' : addon.name)}">
                                </label>
                                <label>Description (optional)
                                    <input type="text" name="description" maxlength="2000" value="${esc(isNew ? '' : (addon.description || ''))}">
                                </label>
                                <label>Pricing source
                                    <select name="pricing_mode">
                                        <option value="linked" ${mode === 'linked' ? 'selected' : ''}>Linked service rate</option>
                                        <option value="fixed" ${mode === 'fixed' ? 'selected' : ''}>Fixed price</option>
                                    </select>
                                </label>
                                <label class="linked-field" style="${mode === 'linked' ? '' : 'display:none'}">Linked service
                                    <select name="price_from">${serviceOptions}</select>
                                </label>
                                <label class="fixed-field" style="${mode === 'fixed' ? '' : 'display:none'}">Fixed price (₱)
                                    <input type="number" name="fixed_price" min="0" step="0.01" value="${isNew || addon.fixed_price === null ? '' : esc(addon.fixed_price)}">
                                </label>
                            </div>
                            <div class="checks">
                                <label><input type="checkbox" name="allow_qty" ${!isNew && addon.allow_qty ? 'checked' : ''}> Allow quantity (per item)</label>
                                <label><input type="checkbox" name="is_active" ${!isNew && !addon.is_active ? '' : 'checked'}> Active (visible to customers)</label>
                            </div>
                            <div class="form-actions">
                                <button type="submit" class="btn-save-price"><i class="fa-solid fa-check"></i> ${isNew ? 'Create add-on' : 'Save add-on'}</button>
                                <button type="button" class="btn-cancel-price" data-action="cancel-item">Cancel</button>
                            </div>
                        </form>
                    </td>
                </tr>`;
        }

        /* ============================================================ interactions */
        function bindEvents() {
            const grid = $('#servicesGrid');
            const catalogTable = $('#catalogTable');

            /* ---- service cards: buttons ---- */
            grid.addEventListener('click', async (event) => {
                const btn = event.target.closest('button[data-action]');
                if (!btn) return;
                const card = btn.closest('.service-card');
                if (!card) return;
                const svcId = card.dataset.serviceId;
                const svc = state.services.find((s) => s.id === svcId);
                if (!svc) return;
                const action = btn.dataset.action;

                if (action === 'edit-service') {
                    state.editServiceId = svcId;
                    renderServices();
                    return;
                }
                if (action === 'cancel-edit') {
                    state.editServiceId = null;
                    renderServices();
                    return;
                }
                if (action === 'add-rate') {
                    const editor = btn.closest('.rates-editor');
                    if (editor) btn.insertAdjacentHTML('beforebegin', rateRowHtml(['', '']));
                    return;
                }
                if (action === 'remove-rate') {
                    const row = btn.closest('.rate-row');
                    if (row) row.remove();
                    return;
                }
                if (action === 'manage-items') {
                    state.catalogServiceId = svcId;
                    state.tab = 'packages';
                    state.itemDraft = null;
                    renderServiceSelect();
                    renderTabs();
                    renderCatalog();
                    const panel = $('#catalogPanel');
                    if (panel) {
                        try { panel.scrollIntoView({ behavior: 'smooth', block: 'start' }); } catch (e) { /* noop */ }
                    }
                    return;
                }
                if (action === 'toggle-service') {
                    if (svc.is_active && !window.confirm('Deactivate "' + svc.name + '"?\n\nIt disappears from the customer site, but historical reservations are kept intact.')) return;
                    btn.disabled = true;
                    try {
                        await apiRequest('POST', { action: 'set_active', kind: 'service', id: svcId, is_active: svc.is_active ? 0 : 1 });
                        await loadCatalog();
                        showAlert('success', 'Service ' + (svc.is_active ? 'deactivated' : 'activated') + '.');
                    } catch (err) {
                        showAlert('error', friendlyError(err.message));
                    } finally {
                        btn.disabled = false;
                    }
                }
            });

            /* ---- service cards: price display follows the base price ---- */
            grid.addEventListener('input', (event) => {
                if (event.target.name !== 'base_price') return;
                const form = event.target.closest('form.svc-form');
                if (!form) return;
                const display = form.querySelector('[name="price_display"]');
                if (!display) return;
                const raw = event.target.value;
                if (raw === '' || !Number.isFinite(Number(raw))) return;
                const amount = fmtPeso(Number(raw));
                const current = display.value.trim();
                display.value = current.indexOf('₱') !== -1
                    ? current.replace(/₱\s*[\d,]+(?:\.\d+)?/, amount)
                    : amount;
            });

            /* ---- service cards: save ---- */
            grid.addEventListener('submit', async (event) => {
                const form = event.target.closest('form.svc-form');
                if (!form) return;
                event.preventDefault();

                const formData = new FormData(form);
                const rates = Array.prototype.map.call(form.querySelectorAll('.rate-row'), (row) => ([
                    row.querySelector('.rate-label').value.trim(),
                    row.querySelector('.rate-value').value.trim(),
                ])).filter((pair) => pair[0] !== '' || pair[1] !== '');

                const payload = {
                    id: form.dataset.serviceId,
                    name: String(formData.get('name') || '').trim(),
                    description: String(formData.get('description') || '').trim(),
                    base_price: Number(formData.get('base_price')),
                    price_display: String(formData.get('price_display') || '').trim(),
                    per_head: String(formData.get('per_head') || '').trim() === '' ? null : Number(formData.get('per_head')),
                    max_cap: String(formData.get('max_cap') || '').trim() === '' ? null : parseInt(formData.get('max_cap'), 10),
                    customer_display: {
                        rates: rates,
                        notes: splitLines(formData.get('notes')),
                        mainCourses: splitLines(formData.get('main_courses')),
                    },
                };

                if (!payload.name) { showAlert('error', REASON_TEXT.INVALID_NAME); return; }
                if (!Number.isFinite(payload.base_price) || payload.base_price < 0) { showAlert('error', REASON_TEXT.INVALID_BASE_PRICE); return; }

                const submitBtn = form.querySelector('button[type="submit"]');
                if (submitBtn) submitBtn.disabled = true;
                try {
                    await apiRequest('PUT', payload);
                    state.editServiceId = null;
                    await loadCatalog();
                    showAlert('success', 'Service saved.');
                } catch (err) {
                    showAlert('error', friendlyError(err.message));
                } finally {
                    if (submitBtn) submitBtn.disabled = false;
                }
            });

            /* ---- catalog tabs / service select / add button ---- */
            document.querySelectorAll('#catalogTabs .tab-btn').forEach((btn) => {
                btn.addEventListener('click', () => {
                    state.tab = btn.dataset.tab;
                    state.itemDraft = null;
                    renderTabs();
                    renderCatalog();
                });
            });

            const select = $('#catalogService');
            if (select) {
                select.addEventListener('change', () => {
                    state.catalogServiceId = select.value;
                    state.itemDraft = null;
                    renderCatalog();
                });
            }

            const addBtn = $('#btnAddItem');
            if (addBtn) {
                addBtn.addEventListener('click', () => {
                    state.itemDraft = { kind: state.tab, id: null };
                    renderCatalog();
                });
            }

            const reloadBtn = $('#btnReload');
            if (reloadBtn) {
                reloadBtn.addEventListener('click', async () => {
                    reloadBtn.disabled = true;
                    try {
                        await loadCatalog();
                        showAlert('success', 'Catalog reloaded.');
                    } catch (err) {
                        showAlert('error', friendlyError(err.message));
                    } finally {
                        reloadBtn.disabled = false;
                    }
                });
            }

            /* ---- catalog table: edit / cancel / toggle ---- */
            catalogTable.addEventListener('click', async (event) => {
                const btn = event.target.closest('button[data-action]');
                if (!btn) return;
                const action = btn.dataset.action;

                if (action === 'cancel-item') {
                    state.itemDraft = null;
                    renderCatalog();
                    return;
                }
                if (action === 'edit-item') {
                    state.itemDraft = { kind: state.tab, id: btn.dataset.id };
                    renderCatalog();
                    return;
                }
                if (action === 'toggle-item') {
                    const itemId = btn.dataset.id;
                    const activeNow = btn.dataset.active === '1';
                    if (activeNow && !window.confirm('Deactivate this item?\n\nIt disappears from the customer site, but existing reservations keep their details.')) return;
                    btn.disabled = true;
                    try {
                        await apiRequest('POST', {
                            action: 'set_active',
                            kind: TAB_KIND[state.tab],
                            id: state.tab === 'addons' ? itemId : Number(itemId),
                            is_active: activeNow ? 0 : 1,
                        });
                        await loadCatalog();
                        showAlert('success', 'Item ' + (activeNow ? 'deactivated' : 'activated') + '.');
                    } catch (err) {
                        showAlert('error', friendlyError(err.message));
                    } finally {
                        btn.disabled = false;
                    }
                }
            });

            /* ---- catalog table: add-on pricing mode toggling ---- */
            catalogTable.addEventListener('change', (event) => {
                if (event.target.name !== 'pricing_mode') return;
                const form = event.target.closest('form.item-form');
                if (!form) return;
                const mode = event.target.value;
                const linked = form.querySelector('.linked-field');
                const fixed = form.querySelector('.fixed-field');
                if (linked) linked.style.display = mode === 'linked' ? '' : 'none';
                if (fixed) fixed.style.display = mode === 'fixed' ? '' : 'none';
            });

            /* ---- catalog table: save ---- */
            catalogTable.addEventListener('submit', async (event) => {
                const form = event.target.closest('form.item-form');
                if (!form) return;
                event.preventDefault();

                const kind = form.dataset.kind;
                const itemId = form.dataset.id;
                const formData = new FormData(form);
                const payload = {
                    action: TAB_ACTION[kind],
                    service_id: state.catalogServiceId,
                };
                if (itemId !== '') payload.id = kind === 'addons' ? itemId : Number(itemId);

                if (kind === 'packages') {
                    payload.package_name = String(formData.get('package_name') || '').trim();
                    payload.package_code = String(formData.get('package_code') || '').trim();
                    payload.price = Number(formData.get('price'));
                    payload.max_cap = String(formData.get('max_cap') || '').trim() === '' ? null : Number(formData.get('max_cap'));
                    payload.sort_order = String(formData.get('sort_order') || '').trim() === '' ? null : Number(formData.get('sort_order'));
                    payload.is_active = form.querySelector('[name="is_active"]').checked ? 1 : 0;
                } else if (kind === 'menus') {
                    payload.item_name = String(formData.get('item_name') || '').trim();
                    payload.category_name = String(formData.get('category_name') || '').trim();
                    payload.menu_code = String(formData.get('menu_code') || '').trim();
                    payload.price = String(formData.get('price') || '').trim() === '' ? null : Number(formData.get('price'));
                    payload.dishes = splitLines(formData.get('dishes'));
                    payload.is_active = form.querySelector('[name="is_active"]').checked ? 1 : 0;
                } else {
                    const mode = formData.get('pricing_mode');
                    payload.name = String(formData.get('name') || '').trim();
                    payload.description = String(formData.get('description') || '').trim();
                    payload.price_from = mode === 'linked' ? String(formData.get('price_from') || '') : '';
                    payload.fixed_price = mode === 'fixed'
                        ? (String(formData.get('fixed_price') || '').trim() === '' ? null : Number(formData.get('fixed_price')))
                        : null;
                    payload.allow_qty = form.querySelector('[name="allow_qty"]').checked ? 1 : 0;
                    payload.is_active = form.querySelector('[name="is_active"]').checked ? 1 : 0;
                }

                const submitBtn = form.querySelector('button[type="submit"]');
                if (submitBtn) submitBtn.disabled = true;
                try {
                    await apiRequest('POST', payload);
                    state.itemDraft = null;
                    await loadCatalog();
                    showAlert('success', TAB_LABELS[kind] + ' saved.');
                } catch (err) {
                    showAlert('error', friendlyError(err.message));
                } finally {
                    if (submitBtn) submitBtn.disabled = false;
                }
            });
        }

        /* ------------------------------------------------------------------- boot */
        document.addEventListener('DOMContentLoaded', async () => {
            bindEvents();
            try {
                await loadCatalog();
            } catch (err) {
                showAlert('error', friendlyError(err.message) + ' Could not load the catalog.');
                const grid = $('#servicesGrid');
                if (grid) grid.innerHTML = '<p class="empty-note">Could not load services. Check the API and reload.</p>';
            }
        });
    })();
    </script>

</body>
</html>
