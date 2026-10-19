<?php
require_once __DIR__ . '/../includes/db.php';
if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1','::1'], true)) { http_response_code(403); exit('Run first administrator setup from this computer.'); }
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$error = '';
try { $count = (int)db()->query('SELECT COUNT(*) FROM admins')->fetchColumn(); }
catch (Throwable $e) { http_response_code(503); exit('Database unavailable. Import database/schema.sql and configure PDR_DB_* first.'); }
if ($count > 0) { header('Location: login.php'); exit; }
if (empty($_SESSION['setup_csrf'])) $_SESSION['setup_csrf'] = bin2hex(random_bytes(32));
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['setup_csrf'], $_POST['csrf'] ?? '')) $error = 'Invalid request. Reload and try again.';
    else {
        $name = trim($_POST['name'] ?? ''); $email = strtolower(trim($_POST['email'] ?? '')); $password = $_POST['password'] ?? '';
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 12) $error = 'Enter a name, valid email, and password of at least 12 characters.';
        else {
            try { $q = db()->prepare('INSERT INTO admins (name,email,password_hash) VALUES (?,?,?)'); $q->execute([$name,$email,password_hash($password,PASSWORD_DEFAULT)]); header('Location: login.php?created=1'); exit; }
            catch (Throwable $e) { $error = 'Unable to create administrator. Check the email and database configuration.'; }
        }
    }
}
?>
<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Administrator Setup</title><link rel="stylesheet" href="dashboard.css"><body><main style="max-width:480px;margin:8vh auto;padding:2rem;background:white;border-radius:12px"><h1>Create first administrator</h1><p>This one-time setup is disabled after the first admin account exists.</p><?php if($error): ?><p role="alert"><?=htmlspecialchars($error,ENT_QUOTES,'UTF-8')?></p><?php endif ?><form method="post"><input type="hidden" name="csrf" value="<?=htmlspecialchars($_SESSION['setup_csrf'],ENT_QUOTES,'UTF-8')?>"><p><label>Name <input name="name" required maxlength="120"></label></p><p><label>Email <input name="email" type="email" required maxlength="190"></label></p><p><label>Password (12+ characters) <input name="password" type="password" minlength="12" required></label></p><button type="submit">Create administrator</button></form></main></body></html>
