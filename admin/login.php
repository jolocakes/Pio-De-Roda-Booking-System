<?php
require_once __DIR__ . '/../includes/db.php';
if (session_status() !== PHP_SESSION_ACTIVE) { ini_set('session.use_strict_mode','1'); session_set_cookie_params(['httponly'=>true,'secure'=>!empty($_SERVER['HTTPS']),'samesite'=>'Lax','path'=>'/']); session_start(); }
if (!empty($_SESSION['admin_id'])) { header('Location: dashboard.php'); exit; }
if (empty($_SESSION['login_csrf'])) $_SESSION['login_csrf'] = bin2hex(random_bytes(32));
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['login_csrf'], $_POST['csrf'] ?? '')) $error = 'Invalid request. Reload and try again.';
    else {
        try {
            $q = db()->prepare('SELECT id,name,password_hash,role FROM admins WHERE email=? AND active=1 LIMIT 1'); $q->execute([strtolower(trim($_POST['email'] ?? ''))]); $a=$q->fetch();
            if ($a && password_verify($_POST['password'] ?? '',$a['password_hash'])) { session_regenerate_id(true); $_SESSION['admin_id']=(int)$a['id']; $_SESSION['admin_name']=$a['name']; $_SESSION['role']=$a['role']; $_SESSION['csrf']=bin2hex(random_bytes(32)); unset($_SESSION['login_csrf']); header('Location: dashboard.php'); exit; }
        } catch (Throwable $e) { /* keep authentication errors generic */ }
        $error='Email or password is incorrect.';
    }
}
?>
<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Admin Login | Pio De Roda</title><link rel="stylesheet" href="dashboard.css"><body><main style="max-width:440px;margin:8vh auto;padding:2rem;background:white;border-radius:12px"><h1>Administrator Login</h1><?php if(isset($_GET['created'])):?><p>Administrator account created. Sign in.</p><?php endif ?><?php if($error):?><p role="alert"><?=htmlspecialchars($error,ENT_QUOTES,'UTF-8')?></p><?php endif ?><form method="post"><input type="hidden" name="csrf" value="<?=htmlspecialchars($_SESSION['login_csrf'],ENT_QUOTES,'UTF-8')?>"><p><label>Email <input name="email" type="email" required autocomplete="username"></label></p><p><label>Password <input name="password" type="password" required autocomplete="current-password"></label></p><button type="submit">Sign in</button></form><p><a href="setup.php">First-time setup</a></p></main></body></html>
