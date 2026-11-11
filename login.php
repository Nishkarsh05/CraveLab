
<?php
require_once __DIR__ . '/includes/session.php';
require_once 'includes/db.php';
require_once 'includes/security.php';
$csrfToken = cravelab_csrf_token();

$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    cravelab_verify_csrf();
    $email = trim($_POST["email"]);
    $password = $_POST["password"];

    $stmt = $conn->prepare("SELECT id, name, email, password FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        if (password_verify($password, $user["password"])) {
            session_regenerate_id(true);

            $_SESSION["user_id"] = $user["id"];
            $_SESSION["user_name"] = $user["name"];
            $_SESSION["user_email"] = $user["email"];

            header("Location: dashboard.php");
            exit();
        }
    }

    $message = "Incorrect email or password. Try again!";
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | CraveLab</title>
    <link rel="stylesheet" href="assets/css/style.css">

    <style>
        .auth-wrap {
            min-height: 85vh;
            display: grid;
            place-items: center;
            padding: 50px 20px;
        }

        .auth-card {
            background: white;
            width: 100%;
            max-width: 440px;
            padding: 38px;
            border-radius: 12px;
            border: 1px solid #eee;
            box-shadow: 0 15px 45px #20231f0d;
        }

        .auth-card h1 {
            font-family: Georgia, serif;
            font-size: 36px;
            margin: 15px 0 8px;
        }

        .auth-card p {
            color: #777b70;
            font-size: 13px;
            line-height: 1.6;
        }

        .auth-card label {
            display: block;
            font-size: 12px;
            font-weight: 700;
            margin: 17px 0 7px;
        }

        .auth-card input {
            width: 100%;
            padding: 13px;
            border: 1px solid #e5e3dc;
            border-radius: 6px;
            font: inherit;
            outline: none;
        }

        .auth-card input:focus {
            border-color: #f36b3f;
        }

        .auth-card button {
            width: 100%;
            border: 0;
            cursor: pointer;
            margin-top: 24px;
        }

        .auth-message {
            background: #f1f0e9;
            padding: 12px;
            border-radius: 6px;
            font-size: 12px;
            margin-top: 15px;
        }

        .auth-footer {
            text-align: center;
            margin-top: 20px;
        }
    </style>
</head>

<body>

<header class="site-header">
    <nav class="nav container">
        <a class="brand" href="index.php">
            <span class="brand-mark">C</span>
            crave<span>lab.</span>
        </a>
        <a href="index.php" class="text-link">← Back home</a>
    </nav>
</header>

<main class="auth-wrap">
    <div class="auth-card">

        <div class="eyebrow">WELCOME BACK, FOODIE</div>

        <h1>Back to the kitchen.</h1>

        <p>Your recipes missed you. Probably.</p>

        <?php if ($message): ?>
            <div class="auth-message">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

            <label>Email address</label>
            <input type="email" name="email" required>

            <label>Password</label>
            <input type="password" name="password" required>

            <button class="btn btn-orange" type="submit">
                Let's get cooking ↗
            </button>

        </form>

        <div class="auth-footer">
            <p>New here? <a href="register.php">Create an account</a></p>
        </div>

    </div>
</main>

</body>
</html>
