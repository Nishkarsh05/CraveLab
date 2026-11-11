
<?php
require_once __DIR__ . '/includes/session.php';
require_once 'includes/db.php';
require_once 'includes/security.php';
$csrfToken = cravelab_csrf_token();

$message = "";
$success = false;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    cravelab_verify_csrf();
    $name = trim($_POST["name"]);
    $email = trim($_POST["email"]);
    $password = $_POST["password"];
    $confirm = $_POST["confirm_password"];

    if ($name === "" || $email === "" || $password === "") {
        $message = "Please fill in all fields.";
    } elseif (strlen($name) > 100 || strlen($email) > 150 || strlen($password) > 4096) {
        $message = "Please check the length of your details and password.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Please enter a valid email.";
    } elseif (strlen($password) < 8) {
        $message = "Password must be at least 8 characters.";
    } elseif ($password !== $confirm) {
        $message = "Passwords do not match.";
    } else {
        $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        $result = $check->get_result();

        if ($result->num_rows > 0) {
            $message = "This email is already registered.";
        } else {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $conn->prepare(
                "INSERT INTO users (name, email, password) VALUES (?, ?, ?)"
            );
            $stmt->bind_param("sss", $name, $email, $hashedPassword);

            if ($stmt->execute()) {
                $success = true;
                $message = "Account created! Welcome to the kitchen.";
            } else {
                $message = "Something went wrong. Please try again.";
            }
            $stmt->close();
        }
        $check->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Join CraveLab</title>
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
        <div class="eyebrow">YOUR FOOD PEOPLE ARE HERE</div>
        <h1>Join the kitchen.</h1>
        <p>Create an account and start sharing your delicious experiments.</p>

        <?php if ($message): ?>
            <div class="auth-message">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <?php if (!$success): ?>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <label>Full name</label>
            <input type="text" name="name" maxlength="100" required
                   value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">

            <label>Email address</label>
            <input type="email" name="email" maxlength="150" required
                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">

            <label>Password</label>
            <input type="password" name="password" required minlength="8">

            <label>Confirm password</label>
            <input type="password" name="confirm_password" required>

            <button class="btn btn-orange" type="submit">
                Create my account ↗
            </button>
        </form>
        <?php endif; ?>

        <div class="auth-footer">
            <p>Already part of the kitchen? <a href="login.php">Log in</a></p>
        </div>
    </div>
</main>

</body>
</html>
