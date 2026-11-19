<?php
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../config/db.php';

// Payments are staff/admin data; a read-only Viewer must not open this page.
pdrRequirePageCapability('manage_payments');

// T-15 operational aid: webhook amount mismatches are flagged in the database.
// Surface them here so they cannot be missed.
$reviewPayments = [];
try {
    $reviewStmt = $pdo->query(
        "SELECT reservation_id, amount, paid_amount, paid_at
           FROM payments
          WHERE status = 'For Review'
          ORDER BY id DESC
          LIMIT 20"
    );
    $reviewPayments = $reviewStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $reviewError) {
    $reviewPayments = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Tracking - Pio De Roda Cafe</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,600;0,9..144,700;1,9..144,600&family=Work+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo pdrAsset('payment.css'); ?>" />
    <link rel="stylesheet" href="<?php echo pdrAsset('responsive.css'); ?>" />
</head>
<body>

    <!-- Shared sidebar: admin/_sidebar.php (T-60) -->
    <?php $activeNav = 'payment'; require __DIR__ . '/_sidebar.php'; ?>

    <div class="main-wrapper">
        <div class="top-navbar">
            <span style="font-size: 18px; color: var(--text-dark); font-weight: 700;">Payment Tracking</span>
            <div class="user-profile">
                <div class="user-avatar"><i class="fa-solid fa-user"></i></div>
                <div class="user-info">
                    <p><?php echo pdrAdminDisplayName(); ?></p>
                    <span><?php echo pdrAdminDisplayRole(); ?></span>
                </div>
            </div>
        </div>

        <div class="content-body">
            <div class="page-header-container">
                <div class="page-title-group">
                    <p>Monitor customer transaction records, deposits, remaining balances, and payment status.</p>
                </div>
                <button class="btn-add-new" id="openAddBtn" type="button"><i class="fa-solid fa-plus"></i> Record Payment</button>
            </div>

            <div class="alert-box" id="pageAlert" role="status" aria-live="polite"></div>

            <?php if (!empty($reviewPayments)): ?>
            <div style="margin:0 0 18px;padding:14px 18px;border:1px solid #d93025;border-left:6px solid #d93025;border-radius:8px;background:#fdecea;color:#611a15;">
                <strong>Payments needing review (<?php echo count($reviewPayments); ?>)</strong>
                <p style="margin:6px 0 8px;font-size:13px;">These webhook payments did not match the expected amount and were <strong>not</strong> marked paid.</p>
                <ul style="margin:0;padding-left:18px;font-size:13px;">
                    <?php foreach ($reviewPayments as $rp): ?>
                    <li>
                        <strong><?php echo htmlspecialchars((string) ($rp['reservation_id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></strong>
                        &mdash; expected &#8369;<?php echo number_format((float) ($rp['amount'] ?? 0), 2); ?>,
                        paid <?php echo ($rp['paid_amount'] === null) ? '(unknown)' : '&#8369;' . number_format((float) $rp['paid_amount'], 2); ?>
                        <?php if (!empty($rp['paid_at'])): ?>
                            on <?php echo htmlspecialchars((string) $rp['paid_at'], ENT_QUOTES, 'UTF-8'); ?>
                        <?php endif; ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <!-- Summary Metrics -->
            <div class="metrics-grid">
                <div class="metric-card">
                    <h4>Total Collected</h4>
                    <div class="metric-value" id="m_collected">₱0.00</div>
                </div>
                <div class="metric-card">
                    <h4>Outstanding Balances</h4>
                    <div class="metric-value" id="m_outstanding" style="color: #d93025;">₱0.00</div>
                </div>
                <div class="metric-card">
                    <h4>Fully Paid Records</h4>
                    <div class="metric-value" id="m_paid_count">0</div>
                </div>
                <div class="metric-card">
                    <h4>Partial Settlements</h4>
                    <div class="metric-value" id="m_partial_count">0</div>
                </div>
            </div>

            <!-- Filters -->
            <div class="payment-toolbar">
                <div class="search-box">
                    <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                    <input type="search" id="paymentSearch" placeholder="Search reservation ID, customer, or service…" autocomplete="off" aria-label="Search payments">
                </div>
                <select id="statusFilter" class="filter-select" aria-label="Filter by payment status">
                    <option value="">All statuses</option>
                    <option value="unpaid">Unpaid</option>
                    <option value="partial">Partially paid</option>
                    <option value="paid">Fully paid</option>
                </select>
                <button type="button" class="btn-cancel" id="refreshBtn"><i class="fa-solid fa-rotate"></i> Refresh</button>
            </div>

            <!-- Payments Table -->
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Reservation</th>
                            <th>Customer</th>
                            <th>Total Amount</th>
                            <th>Paid</th>
                            <th>Remaining Balance</th>
                            <th>Payment Method</th>
                            <th>Last Payment</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="paymentsTableBody">
                        <tr><td colspan="9" class="history-empty">Loading payment records…</td></tr>
                    </tbody>
                </table>

                <div class="table-footer">
                    <span id="paymentRange">Showing 0–0 of 0</span>
                    <div class="pager">
                        <button type="button" class="btn-cancel" id="paymentPrev" disabled>Prev</button>
                        <span id="paymentPage">Page 1 of 1</span>
                        <button type="button" class="btn-cancel" id="paymentNext" disabled>Next</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Record Payment Modal -->
    <div class="modal-overlay" id="addModal" role="dialog" aria-modal="true" aria-labelledby="addModalTitle">
        <div class="modal-box">
            <h3 id="addModalTitle">Record Payment</h3>
            <p class="modal-hint">Record a cash, GCash, or bank transfer payment. The customer's payment page and receipt update from the same ledger.</p>

            <form id="addPaymentForm" novalidate>
                <div class="form-group full-width">
                    <label for="p_reservation_picker">Reservation</label>
                    <select id="p_reservation_picker" required>
                        <option value="">Loading reservations…</option>
                    </select>
                </div>

                <div class="payment-summary" id="paymentSummary" hidden>
                    <div>
                        <span>Total</span>
                        <strong id="s_total">₱0.00</strong>
                    </div>
                    <div>
                        <span>Already paid</span>
                        <strong id="s_paid">₱0.00</strong>
                    </div>
                    <div>
                        <span>Remaining balance</span>
                        <strong id="s_balance" style="color:#d93025;">₱0.00</strong>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label for="p_amount">Amount (₱)</label>
                        <input type="number" id="p_amount" min="0.01" step="0.01" required placeholder="0.00">
                    </div>
                    <div class="form-group">
                        <label for="p_method">Payment Method</label>
                        <select id="p_method" required>
                            <option value="Cash">Cash</option>
                            <option value="GCash">GCash</option>
                            <option value="Bank Transfer">Bank Transfer</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="p_date">Payment Date</label>
                        <input type="date" id="p_date" required>
                    </div>
                    <div class="form-group">
                        <label for="p_note">Reference / Note (optional)</label>
                        <input type="text" id="p_note" maxlength="255" placeholder="e.g. GCash ref 123456">
                    </div>
                </div>

                <p class="status-preview" id="statusPreview" aria-live="polite"></p>
                <p class="form-error" id="addPaymentError" role="alert"></p>

                <div class="modal-actions">
                    <button type="button" class="btn-cancel" data-close-add>Cancel</button>
                    <button type="submit" class="btn-add-new" id="savePaymentBtn">Save Payment</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Payment History Modal -->
    <div class="modal-overlay" id="historyModal" role="dialog" aria-modal="true" aria-labelledby="historyTitle">
        <div class="modal-box history-box">
            <h3 id="historyTitle">Payment History</h3>
            <p class="modal-hint" id="historySubtitle"></p>
            <p class="history-notice" id="historyNotice" role="status" aria-live="polite"></p>
            <div id="historyBody"></div>
            <div class="modal-actions">
                <button type="button" class="btn-cancel" data-close-history>Close</button>
                <button type="button" class="btn-add-new" id="historyRecordBtn"><i class="fa-solid fa-plus"></i> Record Payment</button>
            </div>
        </div>
    </div>

    <script>
    (function () {
        "use strict";

        const PDR_CSRF_TOKEN = <?php echo json_encode(pdrCsrfToken(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        const API = "../api/admin/payments.php";
        const $ = (id) => document.getElementById(id);

        const state = {
            page: 1,
            perPage: 20,
            q: "",
            status: "",
            totalPages: 1,
            rows: [],
            picker: [],
            activeReservation: null,
            historyReservationId: null
        };

        function esc(value) {
            return String(value ?? "").replace(/[&<>"']/g, function (ch) {
                return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[ch];
            });
        }

        function money(value) {
            const n = Number(value);
            return isFinite(n)
                ? "₱" + n.toLocaleString("en-PH", { minimumFractionDigits: 2, maximumFractionDigits: 2 })
                : "₱0.00";
        }

        function balanceNum(value) {
            const n = Number(value);
            return isFinite(n) ? n : 0;
        }

        function fmtDateTime(value) {
            if (!value) return "—";
            const d = new Date(String(value).replace(" ", "T"));
            if (isNaN(d.getTime())) return String(value);
            return d.toLocaleString("en-PH", {
                year: "numeric", month: "short", day: "numeric",
                hour: "numeric", minute: "2-digit"
            });
        }

        function todayISO() {
            const d = new Date();
            const pad = (n) => String(n).padStart(2, "0");
            return d.getFullYear() + "-" + pad(d.getMonth() + 1) + "-" + pad(d.getDate());
        }

        function showAlert(message, type) {
            const box = $("pageAlert");
            box.textContent = message;
            box.className = "alert-box " + (type === "error" ? "alert-error" : "alert-success");
            box.style.display = "block";
            if (type !== "error") {
                window.clearTimeout(showAlert._timer);
                showAlert._timer = window.setTimeout(function () {
                    box.style.display = "none";
                }, 5000);
            }
        }

        function statusBadge(status) {
            if (status === "Paid") {
                return '<span class="status-badge status-paid">Fully Paid</span>';
            }
            if (status === "Partially Paid") {
                return '<span class="status-badge status-partial">Partial</span>';
            }
            return '<span class="status-badge status-unpaid">Unpaid</span>';
        }

        function reasonText(data) {
            const reasons = {
                INVALID: "Please check the amount, method, and date.",
                INVALID_DATE: "The payment date is not valid.",
                NOT_FOUND: "That reservation no longer exists.",
                ALREADY_PAID: "This booking is already fully paid.",
                EXCEEDS_BALANCE: "The amount is more than the remaining balance.",
                NO_ESTIMATE: "This reservation has no estimated total yet.",
                NOT_PAYABLE: "This booking is cancelled or rejected, so a payment cannot be recorded.",
                NOT_MANUAL: "Only payments recorded from this page can be deleted here.",
                INVALID_CSRF_TOKEN: "Your session expired. Reload the page and try again.",
                UNAUTHORIZED: "Your session expired. Please sign in again.",
                FORBIDDEN: "You do not have permission to change payments.",
                SERVER_ERROR: "The server could not save that payment. Please try again."
            };
            return reasons[data && data.reason] || "Could not save the payment. Please try again.";
        }

        async function loadReservations() {
            const tbody = $("paymentsTableBody");
            const params = new URLSearchParams({ page: state.page, per_page: state.perPage });
            if (state.q) params.set("q", state.q);
            if (state.status) params.set("status", state.status);

            try {
                const res = await fetch(API + "?" + params.toString(), {
                    headers: { Accept: "application/json" },
                    cache: "no-store"
                });
                const data = await res.json().catch(function () { return {}; });
                if (!res.ok || !data.ok) throw new Error(data.reason || "load");

                state.rows = data.reservations || [];
                state.page = (data.meta && data.meta.page) || 1;
                state.totalPages = (data.meta && data.meta.totalPages) || 1;

                renderMetrics(data.summary || {});
                renderTable();
                renderPagination(data.meta || {});
            } catch (err) {
                state.rows = [];
                tbody.innerHTML = '<tr><td colspan="9" class="history-empty" style="color:#b91c1c;">Could not load payment records. Please refresh.</td></tr>';
                showAlert("Could not load payment records.", "error");
            }
        }

        function renderMetrics(summary) {
            $("m_collected").textContent = money(summary.collected || 0);
            $("m_outstanding").textContent = money(summary.outstanding || 0);
            $("m_paid_count").textContent = String(summary.paidCount || 0);
            $("m_partial_count").textContent = String(summary.partialCount || 0);
        }

        function renderTable() {
            const tbody = $("paymentsTableBody");
            tbody.innerHTML = "";

            if (!state.rows.length) {
                tbody.innerHTML = '<tr><td colspan="9" class="history-empty">No reservations match this filter.</td></tr>';
                return;
            }

            state.rows.forEach(function (item) {
                const tr = document.createElement("tr");
                const canRecord = balanceNum(item.balance) > 0.009 &&
                    ["Pending", "Confirmed", "Completed"].indexOf(item.reservationStatus) !== -1;
                const receiptUrl = "receipt.php?reservationId=" + encodeURIComponent(item.reservationId);

                tr.innerHTML =
                    '<td><strong>' + esc(item.reservationId) + '</strong><br>' +
                        '<small style="color:var(--text-muted);">' + esc(item.mainService || "") + '</small></td>' +
                    '<td>' + esc(item.customerName) + '</td>' +
                    '<td class="money-cell">' + money(item.estimatedTotal) + '</td>' +
                    '<td class="money-cell" style="color:#137333;font-weight:600;">' + money(item.amountPaid) + '</td>' +
                    '<td class="money-cell" style="color:#d93025;font-weight:600;">' + money(item.balance) + '</td>' +
                    '<td>' + esc(item.paymentMethod || "—") + '</td>' +
                    '<td>' + esc(item.lastPaymentAt ? fmtDateTime(item.lastPaymentAt) : "—") + '</td>' +
                    '<td>' + statusBadge(item.paymentStatus) + '</td>' +
                    '<td><div class="action-btns">' +
                        '<button type="button" class="btn-sm-outline" data-history="' + esc(item.reservationId) + '" title="Payment history"><i class="fa-solid fa-clock-rotate-left"></i></button>' +
                        (canRecord ? '<button type="button" class="btn-sm-record" data-record="' + esc(item.reservationId) + '" title="Record payment"><i class="fa-solid fa-plus"></i></button>' : '') +
                        '<a class="btn-sm-outline" href="' + receiptUrl + '" target="_blank" rel="noopener" title="View receipt"><i class="fa-solid fa-receipt"></i></a>' +
                    '</div></td>';

                tbody.appendChild(tr);
            });

            tbody.querySelectorAll("[data-history]").forEach(function (btn) {
                btn.addEventListener("click", function () {
                    openHistory(btn.getAttribute("data-history"));
                });
            });
            tbody.querySelectorAll("[data-record]").forEach(function (btn) {
                btn.addEventListener("click", function () {
                    openModal(btn.getAttribute("data-record"));
                });
            });
        }

        function renderPagination(meta) {
            const total = meta.total || 0;
            const page = meta.page || 1;
            const perPage = meta.perPage || state.perPage;
            const from = total ? (page - 1) * perPage + 1 : 0;
            const to = Math.min(page * perPage, total);

            $("paymentRange").textContent = "Showing " + from + "–" + to + " of " + total + " reservation" + (total === 1 ? "" : "s");
            $("paymentPage").textContent = "Page " + page + " of " + (meta.totalPages || 1);
            $("paymentPrev").disabled = page <= 1;
            $("paymentNext").disabled = page >= (meta.totalPages || 1);
        }

        /* ---------- record payment modal ---------- */
        async function ensurePicker() {
            if (state.picker.length) {
                renderPicker();
                return;
            }

            const res = await fetch(API + "?picker=1", {
                headers: { Accept: "application/json" },
                cache: "no-store"
            });
            const data = await res.json().catch(function () { return {}; });
            if (!res.ok || !data.ok) throw new Error("picker");

            state.picker = data.reservations || [];
            renderPicker();
        }

        function renderPicker() {
            const select = $("p_reservation_picker");
            const current = select.value;
            const options = ['<option value="">Select a reservation…</option>'];

            state.picker.forEach(function (r) {
                const fullyPaid = balanceNum(r.balance) <= 0.009;
                const label = r.reservationId + " — " + r.customerName +
                    (fullyPaid ? " — fully paid" : " — balance " + money(r.balance));
                options.push(
                    '<option value="' + esc(r.reservationId) + '"' + (fullyPaid ? " disabled" : "") + '>' +
                    esc(label) + '</option>'
                );
            });

            select.innerHTML = options.join("");
            if (current) select.value = current;
        }

        async function openModal(reservationId) {
            $("addPaymentForm").reset();
            $("addPaymentError").textContent = "";
            $("statusPreview").textContent = "";
            $("paymentSummary").hidden = true;
            $("p_date").value = todayISO();
            state.activeReservation = null;

            $("addModal").style.display = "flex";

            try {
                await ensurePicker();
            } catch (err) {
                $("p_reservation_picker").innerHTML = '<option value="">Could not load reservations</option>';
                $("addPaymentError").textContent = "Could not load the reservation list. Close this and refresh the page.";
                return;
            }

            if (reservationId) {
                $("p_reservation_picker").value = reservationId;
            }
            syncSelectedReservation();
            $("p_reservation_picker").focus();
        }

        function closeModal() {
            $("addModal").style.display = "none";
        }

        function syncSelectedReservation() {
            const id = $("p_reservation_picker").value;
            const r = state.picker.find(function (item) { return item.reservationId === id; }) || null;
            state.activeReservation = r;

            if (!r) {
                $("paymentSummary").hidden = true;
                $("statusPreview").textContent = "";
                return;
            }

            $("s_total").textContent = money(r.estimatedTotal);
            $("s_paid").textContent = money(r.amountPaid);
            $("s_balance").textContent = money(r.balance);
            $("paymentSummary").hidden = false;
            $("p_amount").max = balanceNum(r.balance).toFixed(2);
            $("p_amount").value = balanceNum(r.balance) > 0 ? balanceNum(r.balance).toFixed(2) : "";
            updateStatusPreview();
        }

        function updateStatusPreview() {
            const r = state.activeReservation;
            const amount = Number($("p_amount").value) || 0;

            if (!r) {
                $("statusPreview").textContent = "";
                return;
            }
            if (amount <= 0) {
                $("statusPreview").textContent = "Enter an amount to see the resulting status.";
                return;
            }

            const newPaid = Math.min(balanceNum(r.estimatedTotal), balanceNum(r.amountPaid) + amount);
            const newBalance = Math.max(0, balanceNum(r.estimatedTotal) - newPaid);
            const status = newBalance <= 0.009 ? "Fully Paid" : "Partially Paid";

            $("statusPreview").textContent = "After saving: " + status + " — remaining balance " + money(newBalance) + ".";
        }

        async function submitPayment(ev) {
            ev.preventDefault();
            const r = state.activeReservation;
            const errorBox = $("addPaymentError");
            errorBox.textContent = "";

            if (!r) {
                errorBox.textContent = "Please choose a reservation.";
                return;
            }

            const amount = Math.round((Number($("p_amount").value) || 0) * 100) / 100;
            if (!isFinite(amount) || amount <= 0) {
                errorBox.textContent = "Enter an amount greater than zero.";
                return;
            }
            if (amount > balanceNum(r.balance) + 0.009) {
                errorBox.textContent = "The amount cannot be more than the remaining balance of " + money(r.balance) + ".";
                return;
            }

            const btn = $("savePaymentBtn");
            btn.disabled = true;
            btn.textContent = "Saving…";

            try {
                const res = await fetch(API, {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "Accept": "application/json",
                        "X-CSRF-Token": PDR_CSRF_TOKEN
                    },
                    body: JSON.stringify({
                        action: "record",
                        reservationId: r.reservationId,
                        amount: amount,
                        method: $("p_method").value,
                        paidAt: $("p_date").value,
                        note: $("p_note").value.trim()
                    })
                });
                const data = await res.json().catch(function () { return {}; });

                if (!res.ok || !data.ok) {
                    errorBox.textContent = reasonText(data);
                    return;
                }

                closeModal();
                state.picker = [];
                showAlert("Payment recorded. The customer now sees " + money(data.reservation && data.reservation.amountPaid) + " paid.", "success");
                await loadReservations();
            } catch (err) {
                errorBox.textContent = "Network error. Please check your connection and try again.";
            } finally {
                btn.disabled = false;
                btn.textContent = "Save Payment";
            }
        }

        /* ---------- payment history ---------- */
        function closeHistory() {
            $("historyModal").style.display = "none";
            $("historyNotice").textContent = "";
        }

        function historyMessage(message, isError) {
            const el = $("historyNotice");
            el.textContent = message || "";
            el.style.color = isError ? "#b91c1c" : "#137333";
        }

        async function openHistory(reservationId) {
            state.historyReservationId = reservationId;
            $("historyTitle").textContent = "Payment History";
            $("historySubtitle").textContent = reservationId;
            $("historyBody").innerHTML = '<p class="history-empty">Loading…</p>';
            $("historyRecordBtn").hidden = true;
            historyMessage("", false);
            $("historyModal").style.display = "flex";

            try {
                const res = await fetch(API + "?id=" + encodeURIComponent(reservationId), {
                    headers: { Accept: "application/json" },
                    cache: "no-store"
                });
                const data = await res.json().catch(function () { return {}; });
                if (!res.ok || !data.ok) {
                    $("historyBody").innerHTML = '<p class="history-empty" style="color:#b91c1c;">Could not load this payment history.</p>';
                    return;
                }

                renderHistory(data.reservation, data.payments || []);
            } catch (err) {
                $("historyBody").innerHTML = '<p class="history-empty" style="color:#b91c1c;">Network error while loading payment history.</p>';
            }
        }

        function renderHistory(reservation, payments) {
            const canRecord = balanceNum(reservation.balance) > 0.009 &&
                ["Pending", "Confirmed", "Completed"].indexOf(reservation.reservationStatus) !== -1;
            $("historyRecordBtn").hidden = !canRecord;

            let html =
                '<div class="history-summary">' +
                    '<div><span>Customer</span><strong>' + esc(reservation.customerName || "—") + '</strong></div>' +
                    '<div><span>Total</span><strong>' + money(reservation.estimatedTotal) + '</strong></div>' +
                    '<div><span>Paid</span><strong style="color:#137333;">' + money(reservation.amountPaid) + '</strong></div>' +
                    '<div><span>Balance</span><strong style="color:#d93025;">' + money(reservation.balance) + '</strong></div>' +
                '</div>';

            if (!payments.length) {
                html += '<p class="history-empty">No payments recorded yet.</p>';
                $("historyBody").innerHTML = html;
                return;
            }

            html += '<table class="history-table"><thead><tr>' +
                '<th>Date</th><th>Source</th><th>Type</th><th>Method</th><th>Amount</th><th>Status</th><th></th>' +
                '</tr></thead><tbody>';

            payments.forEach(function (p) {
                const source = p.provider === "manual" ? "Manual" : (p.provider === "xendit" ? "Xendit" : (p.provider || "—"));
                const type = p.paymentType === "deposit" ? "Deposit" : (p.paymentType === "full" ? "Full" : (p.paymentType === "partial" ? "Partial" : (p.paymentType || "—")));
                const amount = p.paidAmount !== null && p.paidAmount !== undefined && Number(p.paidAmount) > 0 ? p.paidAmount : p.amount;
                const badge = p.status === "Verified" ? "status-paid" : (p.status === "For Review" ? "status-partial" : "status-unpaid");
                const date = p.paidAt || p.createdAt;

                html += '<tr>' +
                    '<td>' + esc(date ? fmtDateTime(date) : "—") + '</td>' +
                    '<td>' + esc(source) + '</td>' +
                    '<td>' + esc(type) + '</td>' +
                    '<td>' + esc(p.paymentChannel || "—") + '</td>' +
                    '<td class="money-cell">' + money(amount) + '</td>' +
                    '<td><span class="status-badge ' + badge + '">' + esc(p.status || "—") + '</span></td>' +
                    '<td>' + (p.canDelete
                        ? '<button type="button" class="btn-delete-sm" data-delete-payment="' + Number(p.id) + '" title="Delete manual payment"><i class="fa-solid fa-trash"></i></button>'
                        : '') + '</td>' +
                    '</tr>';
            });

            html += '</tbody></table>';
            $("historyBody").innerHTML = html;

            $("historyBody").querySelectorAll("[data-delete-payment]").forEach(function (btn) {
                btn.addEventListener("click", function () {
                    deleteManualPayment(Number(btn.getAttribute("data-delete-payment")));
                });
            });
        }

        async function deleteManualPayment(paymentId) {
            if (!window.confirm("Delete this manual payment? The booking totals will be recalculated.")) return;

            historyMessage("Deleting…", false);
            try {
                const res = await fetch(API, {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "Accept": "application/json",
                        "X-CSRF-Token": PDR_CSRF_TOKEN
                    },
                    body: JSON.stringify({ action: "delete", paymentId: paymentId })
                });
                const data = await res.json().catch(function () { return {}; });
                if (!res.ok || !data.ok) {
                    historyMessage(reasonText(data), true);
                    return;
                }

                state.picker = [];
                await loadReservations();
                if (state.historyReservationId) {
                    await openHistory(state.historyReservationId);
                }
                historyMessage("Payment deleted. Balances were recalculated.", false);
            } catch (err) {
                historyMessage("Network error. Please try again.", true);
            }
        }

        /* ---------- wiring ---------- */
        function debounce(fn, wait) {
            let timer = null;
            return function () {
                const args = arguments;
                window.clearTimeout(timer);
                timer = window.setTimeout(function () { fn.apply(null, args); }, wait);
            };
        }

        function init() {
            $("openAddBtn").addEventListener("click", function () { openModal(); });
            $("addPaymentForm").addEventListener("submit", submitPayment);
            $("p_reservation_picker").addEventListener("change", syncSelectedReservation);
            $("p_amount").addEventListener("input", updateStatusPreview);
            $("refreshBtn").addEventListener("click", function () {
                state.picker = [];
                loadReservations();
            });

            $("paymentSearch").addEventListener("input", debounce(function (ev) {
                state.q = ev.target.value.trim();
                state.page = 1;
                loadReservations();
            }, 250));

            $("statusFilter").addEventListener("change", function (ev) {
                state.status = ev.target.value;
                state.page = 1;
                loadReservations();
            });

            $("paymentPrev").addEventListener("click", function () {
                if (state.page > 1) { state.page -= 1; loadReservations(); }
            });
            $("paymentNext").addEventListener("click", function () {
                if (state.page < state.totalPages) { state.page += 1; loadReservations(); }
            });

            document.querySelectorAll("[data-close-add]").forEach(function (el) {
                el.addEventListener("click", closeModal);
            });
            document.querySelectorAll("[data-close-history]").forEach(function (el) {
                el.addEventListener("click", closeHistory);
            });

            $("addModal").addEventListener("click", function (ev) {
                if (ev.target === $("addModal")) closeModal();
            });
            $("historyModal").addEventListener("click", function (ev) {
                if (ev.target === $("historyModal")) closeHistory();
            });

            $("historyRecordBtn").addEventListener("click", function () {
                const id = state.historyReservationId;
                closeHistory();
                if (id) openModal(id);
            });

            document.addEventListener("keydown", function (ev) {
                if (ev.key !== "Escape") return;
                if ($("addModal").style.display === "flex") closeModal();
                if ($("historyModal").style.display === "flex") closeHistory();
            });

            loadReservations();
        }

        document.addEventListener("DOMContentLoaded", init);
    })();
    </script>
<script src="<?php echo pdrAsset('date-format.js'); ?>"></script>
<script src="<?php echo pdrAsset('responsive.js'); ?>"></script>
<script src="<?php echo pdrAsset('a11y.js'); ?>"></script>
</body>
</html>
