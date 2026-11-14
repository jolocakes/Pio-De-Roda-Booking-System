<?php
/**
 * Admin Login
 * Pio De Roda Hospitality Reservation and Event Booking System
 */
require_once __DIR__ . '/../config/csrf.php';
require_once __DIR__ . '/../config/assets.php';

pdrSessionStart();

if (isset($_SESSION['admin_id'])) {
    header("Location: dashboard.php");
    exit();
}

require_once __DIR__ . '/../config/db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!pdrCsrfValid(pdrRequestCsrfToken())) {
        $error = 'Your session expired. Please reload the page and try again.';
    } else {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!empty($username) && !empty($password)) {
        try {
            require_once __DIR__ . '/../api/login_guard.php';

            // T-17: throttle before touching the password hash. The check is
            // fail-open (see login_guard.php) so a missing table never locks
            // the real administrator out.
            $guard = pdrLoginGuardCheck($pdo, $username);

            if (!$guard['allowed']) {
                $minutes = max(1, (int) ceil($guard['retryAfter'] / 60));
                $error = sprintf('Too many failed login attempts. Please try again in about %d minute(s).', $minutes);
                error_log('Admin login temporarily locked for username=' . $username . ' ip=' . pdrLoginIpHash());
            } else {
                // Progressive delay on repeated failures.
                if ($guard['delay'] > 0) {
                    sleep($guard['delay']);
                }

                $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE username = ?");
                $stmt->execute([$username]);
                $admin = $stmt->fetch();

                if ($admin && password_verify($password, $admin['password_hash'])) {
                    pdrLoginGuardRecord($pdo, $username, true);
                    session_regenerate_id(true);
                    $_SESSION['admin_id'] = $admin['id'];
                    $_SESSION['admin_username'] = $admin['username'];
                    $_SESSION['admin_name'] = $admin['full_name'];
                    $_SESSION['admin_role'] = $admin['role'];
                    // Rotate the CSRF token after the privilege level changes.
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                    header("Location: dashboard.php");
                    exit();
                } else {
                    pdrLoginGuardRecord($pdo, $username, false);
                    $error = "Invalid username or password.";
                }
            }
        } catch (Exception $e) {
            $error = "An error occurred. Please try again later.";
        }
    } else {
        $error = "Please enter both username and password.";
    }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Pio De Roda Reservation System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo pdrAsset('reservation.css'); ?>">
    <style>
        body.login-body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background-color: var(--cream);
        }
        .login-card {
            background: var(--cream-soft);
            padding: 40px;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-soft);
            width: 100%;
            max-width: 420px;
            border: 1px solid var(--border-soft);
        }
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .login-header h1 {
            font-size: 24px;
            color: var(--maroon);
            margin-top: 10px;
        }
        .login-header p {
            font-size: 13px;
            color: var(--text-muted);
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 8px;
        }
        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid var(--border-soft);
            border-radius: var(--radius-md);
            font-size: 14px;
            background: #fff;
            color: var(--text-dark);
        }
        .form-group input:focus {
            outline: none;
            border-color: var(--maroon);
        }
        .btn-login {
            width: 100%;
            padding: 12px;
            background: var(--maroon);
            color: var(--text-light);
            border: none;
            border-radius: var(--radius-md);
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }
        .btn-login:hover {
            background: var(--maroon-light);
        }
        .alert-error {
            background: #fee2e2;
            color: #dc2626;
            padding: 12px;
            border-radius: var(--radius-md);
            font-size: 13px;
            margin-bottom: 20px;
            text-align: center;
        }
    </style>
    <link rel="stylesheet" href="<?php echo pdrAsset('responsive.css'); ?>" />
</head>
<body class="login-body">
    <div class="login-card">
        <div class="login-header">
            <div style="font-size: 36px; color: var(--maroon); margin-bottom: 10px;">
                <i class="fa-solid fa-hotel"></i>
            </div>
            <h1>Pio De Roda</h1>
            <p>Admin Portal Authentication</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="index.php">
            <?php echo pdrCsrfField(); ?>
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required autofocus autocomplete="username">
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required autocomplete="current-password">
            </div>
            <button type="submit" class="btn-login"><i class="fa-solid fa-right-to-bracket"></i> Login to Dashboard</button>
        </form>
    </div>
</body>
</html>