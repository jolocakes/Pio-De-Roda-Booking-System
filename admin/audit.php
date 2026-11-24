<?php
require_once __DIR__ . '/auth_check.php';
pdrRequirePageCapability('view_audit');

$adminName = pdrAdminDisplayName();
$adminRole = pdrAdminDisplayRole();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit Log - Pio De Roda Cafe</title>
    <link rel="icon" href="../favicon.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,600;0,9..144,700;1,9..144,600&family=Work+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo pdrAsset('reservation.css'); ?>">
    <link rel="stylesheet" href="<?php echo pdrAsset('filters.css'); ?>">
    <link rel="stylesheet" href="<?php echo pdrAsset('audit.css'); ?>">
    <link rel="stylesheet" href="<?php echo pdrAsset('responsive.css'); ?>">
</head>
<body>
    <!-- Shared sidebar: admin/_sidebar.php (T-60) -->
    <?php $activeNav = 'audit'; require __DIR__ . '/_sidebar.php'; ?>

    <main class="main-wrapper">
        <header class="top-navbar">
            <div class="header-title-section">
                <h1>Audit Log</h1>
            </div>
            <div class="user-profile">
                <div class="user-avatar"><i class="fa-solid fa-user"></i></div>
                <div class="user-info">
                    <p><?php echo $adminName; ?></p>
                    <span><?php echo $adminRole; ?></span>
                </div>
            </div>
        </header>

        <div class="content-body">
            <div class="page-header-container">
                <div class="page-title-group">
                    <h1>Audit Log</h1>
                    <p>A read-only record of every state-changing action taken in the admin console. Client IPs are stored as one-way hashes.</p>
                </div>
            </div>

            <div id="pageAlert" class="page-alert" role="status" aria-live="polite"></div>

            <form class="filter-bar" id="auditFilterForm">
                <input type="search" id="auditSearch" class="search-input"
                    placeholder="Search actor, action, target or details..." autocomplete="off">

                <label class="filter-field"><span>Action</span>
                    <select id="auditAction" class="filter-select">
                        <option value="">All actions</option>
                    </select>
                </label>

                <label class="filter-field"><span>Actor</span>
                    <select id="auditActor" class="filter-select">
                        <option value="">All actors</option>
                    </select>
                </label>

                <label class="filter-field"><span>From</span>
                    <input type="date" id="auditFrom" class="filter-date">
                </label>

                <label class="filter-field"><span>To</span>
                    <input type="date" id="auditTo" class="filter-date">
                </label>

                <button type="submit" class="btn-filter">Filter</button>
                <button type="button" class="btn-reset" id="auditReset">Reset</button>
            </form>

            <div class="dashboard-card">
                <div class="card-header">
                    <h3>Activity</h3>
                    <span style="font-size:12.5px;color:#718096;">Newest first</span>
                </div>
                <div class="table-responsive">
                    <table class="data-table audit-table">
                        <thead>
                            <tr>
                                <th>When</th>
                                <th>Actor</th>
                                <th>Action</th>
                                <th>Target</th>
                                <th>Details</th>
                                <th>IP</th>
                                <th class="audit-col-ua">User agent</th>
                            </tr>
                        </thead>
                        <tbody id="auditTableBody">
                            <tr><td colspan="7" class="audit-empty">Loading audit entries&hellip;</td></tr>
                        </tbody>
                    </table>
                </div>

                <div style="display:flex;gap:8px;align-items:center;justify-content:space-between;padding:12px 4px 2px;">
                    <span id="auditRange" style="font-size:13px;color:#718096;"></span>
                    <span style="display:flex;gap:6px;align-items:center;">
                        <button type="button" class="btn-reset" id="auditPrev" style="padding:7px 12px;">&lsaquo; Prev</button>
                        <span id="auditPage" style="font-size:13px;color:#4a5568;"></span>
                        <button type="button" class="btn-reset" id="auditNext" style="padding:7px 12px;">Next &rsaquo;</button>
                    </span>
                </div>
            </div>
        </div>
    </main>

    <script src="<?php echo pdrAsset('date-format.js'); ?>"></script>
    <script>
        (function () {
            'use strict';

            const $ = (id) => document.getElementById(id);
            const state = {
                page: 1,
                perPage: 50,
                filters: { q: '', action: '', actor: '', from: '', to: '' }
            };
            let filtersLoaded = false;

            function esc(value) {
                return String(value ?? '').replace(/[&<>"']/g, (ch) => ({
                    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
                }[ch]));
            }

            function truncate(value, max) {
                const text = String(value ?? '');
                return text.length > max ? text.slice(0, max - 1) + '…' : text;
            }

            function showAlert(message, type) {
                const box = $('pageAlert');
                box.textContent = message;
                box.className = 'page-alert ' + (type === 'error' ? 'error' : 'success');
                box.style.display = 'block';
                window.clearTimeout(showAlert._timer);
                showAlert._timer = window.setTimeout(() => { box.style.display = 'none'; }, 6000);
            }

            function setOptions(select, values, placeholder) {
                const current = select.value;
                select.innerHTML = '<option value="">' + esc(placeholder) + '</option>' +
                    values.map((v) => '<option value="' + esc(v) + '">' + esc(v) + '</option>').join('');
                if (current && values.indexOf(current) !== -1) {
                    select.value = current;
                }
            }

            function populateFilters(filters) {
                if (!filters) return;
                setOptions($('auditAction'), Array.isArray(filters.actions) ? filters.actions : [], 'All actions');
                setOptions($('auditActor'), Array.isArray(filters.actors) ? filters.actors : [], 'All actors');
            }

            function readFilters() {
                return {
                    q: $('auditSearch').value.trim(),
                    action: $('auditAction').value,
                    actor: $('auditActor').value,
                    from: $('auditFrom').value,
                    to: $('auditTo').value
                };
            }

            function buildParams() {
                const params = new URLSearchParams();
                params.set('page', state.page);
                params.set('per_page', state.perPage);

                Object.keys(state.filters).forEach((key) => {
                    if (state.filters[key]) {
                        params.set(key, state.filters[key]);
                    }
                });

                return params;
            }

            function actorCell(log) {
                if (log.actor_username) {
                    return '<strong>' + esc(log.actor_username) + '</strong>';
                }
                if (log.actor_id) {
                    return '<span class="audit-muted">User #' + esc(log.actor_id) + '</span>';
                }
                return '<span class="audit-muted">Customer / system</span>';
            }

            function actionCell(action) {
                const raw = String(action || '');
                const prefix = raw.split('.')[0] || 'other';
                const known = ['reservation', 'payment', 'staff', 'account', 'service', 'catalog',
                    'package', 'menu', 'addon', 'customer', 'blackout', 'archive', 'review'];
                const cls = known.indexOf(prefix) !== -1 ? prefix : 'other';
                return '<span class="audit-badge audit-' + cls + '" title="' + esc(raw) + '">'
                    + esc(raw || '—') + '</span>';
            }

            function targetCell(log) {
                const parts = [log.target_type, log.target_id].filter(Boolean);
                return parts.length
                    ? '<span class="audit-code">' + esc(parts.join(': ')) + '</span>'
                    : '<span class="audit-muted">—</span>';
            }

            function detailsCell(details) {
                if (details === null || details === undefined || details === '') {
                    return '<span class="audit-muted">—</span>';
                }

                let text;
                let inline;

                if (typeof details === 'object') {
                    const keys = Object.keys(details);
                    if (keys.length === 0) {
                        return '<span class="audit-muted">—</span>';
                    }
                    text = JSON.stringify(details, null, 2);
                    inline = keys.map((key) => {
                        const value = details[key];
                        const rendered = value === null
                            ? 'null'
                            : (typeof value === 'object' ? JSON.stringify(value) : String(value));
                        return key + ': ' + rendered;
                    }).join(' · ');
                } else {
                    text = String(details);
                    inline = text;
                }

                return '<details class="audit-details">'
                    + '<summary title="' + esc(inline) + '">' + esc(truncate(inline, 70)) + '</summary>'
                    + '<pre>' + esc(text) + '</pre>'
                    + '</details>';
            }

            function ipCell(hash) {
                if (!hash) return '<span class="audit-muted">—</span>';
                return '<code class="audit-code" title="' + esc(hash) + '">'
                    + esc(String(hash).slice(0, 12)) + '…</code>';
            }

            function uaCell(userAgent) {
                if (!userAgent) return '<span class="audit-muted">—</span>';
                return '<span class="audit-ua" title="' + esc(userAgent) + '">' + esc(userAgent) + '</span>';
            }

            function renderRows(logs) {
                const tbody = $('auditTableBody');

                if (!Array.isArray(logs) || logs.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="7" class="audit-empty">No audit entries match these filters.</td></tr>';
                    return;
                }

                tbody.innerHTML = logs.map((log) => {
                    const when = (window.PDRFormat && window.PDRFormat.formatDateTime)
                        ? window.PDRFormat.formatDateTime(log.created_at)
                        : String(log.created_at || '');

                    return '<tr>'
                        + '<td class="audit-when" title="' + esc(log.created_at || '') + '">' + esc(when) + '</td>'
                        + '<td>' + actorCell(log) + '</td>'
                        + '<td>' + actionCell(log.action) + '</td>'
                        + '<td>' + targetCell(log) + '</td>'
                        + '<td>' + detailsCell(log.details) + '</td>'
                        + '<td>' + ipCell(log.ip_hash) + '</td>'
                        + '<td class="audit-col-ua">' + uaCell(log.user_agent) + '</td>'
                        + '</tr>';
                }).join('');
            }

            function renderPagination(meta) {
                const from = meta.total === 0 ? 0 : (meta.page - 1) * meta.perPage + 1;
                const to = Math.min(meta.page * meta.perPage, meta.total);

                $('auditRange').textContent = 'Showing ' + from + '–' + to + ' of ' + meta.total
                    + ' entr' + (meta.total === 1 ? 'y' : 'ies');
                $('auditPage').textContent = 'Page ' + meta.page + ' of ' + meta.totalPages;
                $('auditPrev').disabled = meta.page <= 1;
                $('auditNext').disabled = meta.page >= meta.totalPages;
            }

            async function load(page) {
                state.page = Math.max(1, page || 1);
                const tbody = $('auditTableBody');
                tbody.innerHTML = '<tr><td colspan="7" class="audit-empty">Loading audit entries&hellip;</td></tr>';

                try {
                    const res = await fetch('../api/admin/audit.php?' + buildParams().toString(), {
                        cache: 'no-store'
                    });

                    if (res.status === 401) {
                        window.location.href = 'index.php';
                        return;
                    }

                    const data = await res.json().catch(() => ({}));
                    if (!data.ok) {
                        tbody.innerHTML = '<tr><td colspan="7" class="audit-empty">Could not load the audit log.</td></tr>';
                        showAlert('Could not load the audit log. Please try again.', 'error');
                        return;
                    }

                    if (!filtersLoaded) {
                        populateFilters(data.filters);
                        filtersLoaded = true;
                    }

                    renderRows(data.logs);
                    state.page = data.meta.page;
                    renderPagination(data.meta);
                } catch (error) {
                    tbody.innerHTML = '<tr><td colspan="7" class="audit-empty">Could not load the audit log.</td></tr>';
                    showAlert('Could not reach the server. Check your connection and try again.', 'error');
                }
            }

            $('auditFilterForm').addEventListener('submit', (event) => {
                event.preventDefault();
                state.filters = readFilters();
                load(1);
            });

            $('auditReset').addEventListener('click', () => {
                $('auditFilterForm').reset();
                state.filters = readFilters();
                load(1);
            });

            $('auditPrev').addEventListener('click', () => load(state.page - 1));
            $('auditNext').addEventListener('click', () => load(state.page + 1));

            state.filters = readFilters();
            load(1);
        })();
    </script>
    <script src="<?php echo pdrAsset('responsive.js'); ?>"></script>
    <script src="<?php echo pdrAsset('a11y.js'); ?>"></script>
</body>
</html>
