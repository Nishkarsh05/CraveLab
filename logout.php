<?php
require_once __DIR__ . '/includes/session.php';
require_once 'includes/security.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    cravelab_verify_csrf();
    $_SESSION = [];

    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), "", time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
    header("Location: login.php");
    exit();
}

$csrfToken = cravelab_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Log out | CraveLab</title>
<link rel="stylesheet" href="assets/css/style.css">
<style>
body{min-height:100vh;display:grid;place-items:center;background:#faf8f4;font-family:Arial,sans-serif;color:#29251f}
.logout-card{background:#fff;border:1px solid #eee8df;border-radius:14px;padding:35px;max-width:420px;width:calc(100% - 36px);text-align:center}
.logout-card h1{font-family:Georgia,serif;margin-bottom:10px}
.logout-card p{color:#81786d;margin-bottom:22px}
.logout-card form{display:inline-block;margin:5px}
.logout-card button,.logout-card a{border:0;border-radius:8px;padding:11px 17px;text-decoration:none;font-weight:700;cursor:pointer}
.logout-card button{background:#29251f;color:#fff}
.logout-card a{background:#eee7df;color:#51483f}
</style>
</head>
<body>
<main class="logout-card">
<h1>Leaving the kitchen?</h1>
<p>You can log back in whenever you're ready.</p>
<form method="POST">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
<button type="submit">Log out</button>
</form>
<a href="index.php">Stay here</a>
</main>
</body>
</html>
