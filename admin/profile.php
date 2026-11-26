<?php
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../config/password_policy.php';

$adminName = pdrAdminDisplayName();
$adminRole = pdrAdminDisplayRole();
$adminUsername = htmlspecialchars((string) ($_SESSION['admin_username'] ?? ''), ENT_QUOTES, 'UTF-8');
$adminFullName = htmlspecialchars((string) ($_SESSION['admin_name'] ?? ''), ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Account - Pio De Roda Cafe</title>
    <link rel="icon" href="../favicon.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,600;0,9..144,700;1,9..144,600&family=Work+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo pdrAsset('reservation.css'); ?>">
        <link rel="stylesheet" href="<?php echo pdrAsset('admin-base.css'); ?>">
    <link rel="stylesheet" href="<?php echo pdrAsset('admin-alert.css'); ?>">
    <link rel="stylesheet" href="<?php echo pdrAsset('responsive.css'); ?>">
    <style>
        .account-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 18px;
            align-items: start;
        }
        .account-meta {
            display: grid;
            gap: 2px;
        }
        .account-meta div {
            display: flex;
            justify-content: space-between;
            gap: 14px;
            padding: 10px 0;
            border-bottom: 1px dashed var(--border-color);
            font-size: 13px;
        }
        .account-meta div:last-child {
            border-bottom: 0;
        }
        .account-meta span {
            color: #718096;
        }
        .account-form {
            display: grid;
            gap: 14px;
            max-width: 440px;
        }
        .account-form label {
            display: grid;
            gap: 6px;
            font-size: 12.5px;
            font-weight: 600;
            color: #4a5568;
        }
        .account-form input {
            padding: 10px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 400;
            background: #fff;
        }
        .account-form input:focus {
            outline: none;
            border-color: var(--maroon);
            box-shadow: 0 0 0 2px rgba(91, 8, 8, 0.08);
        }
        .account-hint {
            margin: -4px 0 0;
            font-size: 12px;
            color: #718096;
        }
    </style>
</head>
<body>
    <!-- Shared sidebar: admin/_sidebar.php (T-60) -->
    <?php $activeNav = 'profile'; require __DIR__ . '/_sidebar.php'; ?>

    <main class="main-wrapper">
        <header class="top-navbar">
            <div class="header-title-section">
                <h1>My Account</h1>
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
                    <h1>My Account</h1>
                    <p>Review your account details and change your password. The new password takes effect immediately.</p>
                </div>
            </div>

            <div class="account-grid">
                <div class="dashboard-card">
                    <div class="card-header">
                        <h3>Account details</h3>
                    </div>
                    <div class="account-meta">
                        <div><span>Username</span><strong><?php echo $adminUsername; ?></strong></div>
                        <div><span>Full name</span><strong><?php echo $adminFullName !== '' ? $adminFullName : '&mdash;'; ?></strong></div>
                        <div><span>Role</span><strong><?php echo $adminRole; ?></strong></div>
                    </div>
                </div>

                <div class="dashboard-card">
                    <div class="card-header">
                        <h3>Change password</h3>
                    </div>
                    <form id="passwordForm" class="account-form" novalidate>
                        <label>Current password
                            <input type="password" name="current_password" required
                                autocomplete="current-password">
                        </label>
                        <label>New password
                            <input type="password" name="new_password" required
                                minlength="<?php echo (int) PDR_PASSWORD_MIN; ?>"
                                maxlength="<?php echo (int) PDR_PASSWORD_MAX; ?>"
                                autocomplete="new-password">
                        </label>
                        <label>Confirm new password
                            <input type="password" name="confirm_password" required
                                minlength="<?php echo (int) PDR_PASSWORD_MIN; ?>"
                                maxlength="<?php echo (int) PDR_PASSWORD_MAX; ?>"
                                autocomplete="new-password">
                        </label>
                        <p class="account-hint"><?php echo htmlspecialchars(pdrPasswordPolicyText(), ENT_QUOTES, 'UTF-8'); ?></p>
                        <button type="submit" class="btn btn-primary" style="padding:10px 18px;justify-self:start;">
                            <i class="fa-solid fa-key"></i> Update password
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <script>
        const PDR_CSRF_TOKEN = <?php echo json_encode(
            pdrCsrfToken(),
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        ); ?>;

        const reasonText = {
            WRONG_PASSWORD: 'Your current password is incorrect.',
            MISMATCH: 'The new password and confirmation do not match.',
            WEAK_PASSWORD: 'The new password does not meet the password policy.',
            SAME_PASSWORD: 'The new password must be different from the current one.',
            TOO_MANY_ATTEMPTS: 'Too many attempts. Please wait a few minutes and try again.',
            INVALID_CSRF_TOKEN: 'Your session expired. Reload the page and try again.',
            SERVER_ERROR: 'Could not update the password. Please try again.'
        };

        function showAlert(message, type) {
            if (typeof showPageAlert === 'function') return showPageAlert(message, type);
        }

        document.getElementById('passwordForm').addEventListener('submit', async (event) => {
            event.preventDefault();

            const form = event.target;
            const current = form.current_password.value;
            const next = form.new_password.value;
            const confirm = form.confirm_password.value;
            const button = form.querySelector('button[type="submit"]');

            if (next !== confirm) {
                showAlert(reasonText.MISMATCH, 'error');
                form.confirm_password.focus();
                return;
            }

            button.disabled = true;
            const original = button.innerHTML;
            button.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Updating…';

            try {
                const res = await fetch('../api/admin/profile.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': PDR_CSRF_TOKEN
                    },
                    body: JSON.stringify({
                        current_password: current,
                        new_password: next,
                        confirm_password: confirm
                    })
                });

                if (res.status === 401) {
                    window.location.href = 'index.php';
                    return;
                }

                const data = await res.json().catch(() => ({}));
                if (data.ok) {
                    form.reset();
                    showAlert('Password updated. Use the new password the next time you sign in.', 'success');
                } else {
                    showAlert(reasonText[data.reason] || 'Could not update the password. Please try again.', 'error');
                }
            } catch (error) {
                showAlert(reasonText.SERVER_ERROR, 'error');
            } finally {
                button.disabled = false;
                button.innerHTML = original;
            }
        });
    </script>
    <?php require __DIR__ . '/_page_alert_modal.php'; ?>
    <script src="<?php echo pdrAsset('admin-alert.js'); ?>"></script>
    <script src="<?php echo pdrAsset('date-format.js'); ?>"></script>
    <script src="<?php echo pdrAsset('responsive.js'); ?>"></script>
    <script src="<?php echo pdrAsset('a11y.js'); ?>"></script>
</body>
</html>
