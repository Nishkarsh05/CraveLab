
<?php
require_once __DIR__ . '/includes/session.php';

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

require_once 'includes/db.php';
require_once 'includes/security.php';
$csrfToken = cravelab_csrf_token();

$userId = (int)$_SESSION["user_id"];

// Mark notifications as read.
$stmt = $conn->prepare("
    UPDATE notifications
    SET is_read = 1
    WHERE user_id = ?
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$stmt->close();

// Get notifications and matching pending follow requests.
$stmt = $conn->prepare("
    SELECT
        n.id,
        n.actor_id,
        n.type,
        n.created_at,
        u.name AS actor_name,
        f.id AS follow_id
    FROM notifications n
    JOIN users u ON u.id = n.actor_id
    LEFT JOIN follows f
        ON f.follower_id = n.actor_id
        AND f.following_id = n.user_id
        AND f.status = 'pending'
    WHERE n.user_id = ?
    ORDER BY n.created_at DESC
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$notifications = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Notifications | CraveLab</title>

<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=Playfair+Display:wght@600;700;800&display=swap" rel="stylesheet">

<style>
*{box-sizing:border-box;margin:0;padding:0}
body{background:#faf8f4;color:#29251f;font-family:'DM Sans',sans-serif}
header{background:white;border-bottom:1px solid #eee8df;padding:16px 7%;display:flex;justify-content:space-between;align-items:center}
.brand{font-size:25px;font-weight:800;color:#29251f;text-decoration:none}
.brand span{color:#e56b45}
nav{display:flex;gap:22px}
nav a{color:#514b43;text-decoration:none;font-size:14px;font-weight:600}
.container{max-width:750px;margin:45px auto;padding:0 20px}
h1{font-family:'Playfair Display',serif;font-size:32px;margin-bottom:8px}
.subtitle{color:#8b8175;font-size:14px;margin-bottom:25px}
.notification{background:white;border:1px solid #eee8df;border-radius:12px;padding:18px;margin-bottom:12px;display:flex;justify-content:space-between;align-items:center;gap:15px}
.message{font-size:14px;line-height:1.6}
.message a{color:#d96542;font-weight:800;text-decoration:none}
.time{display:block;color:#9a9186;font-size:11px;margin-top:4px}
.actions{display:flex;gap:8px;flex-shrink:0}
button{border:0;border-radius:7px;padding:9px 13px;font-size:12px;font-weight:700;cursor:pointer}
.accept{background:#29251f;color:white}
.decline{background:#eee7df;color:#51483f}
.empty{text-align:center;padding:65px 15px;color:#8b8175}
.empty h2{font-family:'Playfair Display',serif;color:#39332b;margin-bottom:8px}
@media(max-width:600px){
 header{padding:15px 5%}
 nav{gap:12px}
 nav a{font-size:12px}
 .container{margin-top:28px}
 .notification{align-items:flex-start;flex-direction:column}
 .actions{width:100%}
}
</style>
</head>

<body>

<header>
    <a href="index.php" class="brand">Crave<span>Lab</span></a>
    <nav>
        <a href="index.php">Home</a>
        <a href="recipes.php">Recipes</a>
        <a href="dashboard.php">Dashboard</a>
        <a href="logout.php">Log out</a>
    </nav>
</header>

<main class="container">

    <h1>Notifications 🔔</h1>
    <p class="subtitle">See what's happening in your CraveLab community.</p>

    <?php if ($notifications->num_rows > 0): ?>

        <?php while ($item = $notifications->fetch_assoc()): ?>

            <div class="notification">

                <div class="message">

                    <a href="creator.php?id=<?php echo (int)$item['actor_id']; ?>">
                        <?php echo htmlspecialchars($item['actor_name']); ?>
                    </a>

                    <?php if ($item["type"] === "follow"): ?>
                        started following you.
                    <?php elseif ($item["type"] === "follow_request"): ?>
                        requested to follow you.
                    <?php elseif ($item["type"] === "request_accepted"): ?>
                        accepted your follow request.
                    <?php else: ?>
                        interacted with your profile.
                    <?php endif; ?>

                    <span class="time">
                        <?php echo date("d M Y, g:i A", strtotime($item["created_at"])); ?>
                    </span>

                </div>

                <?php if ($item["type"] === "follow_request" && $item["follow_id"]): ?>

                    <div class="actions">

                        <form action="respond_follow.php" method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                            <input type="hidden" name="follow_id" value="<?php echo (int)$item['follow_id']; ?>">
                            <input type="hidden" name="action" value="accept">
                            <button class="accept" type="submit">Accept</button>
                        </form>

                        <form action="respond_follow.php" method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                            <input type="hidden" name="follow_id" value="<?php echo (int)$item['follow_id']; ?>">
                            <input type="hidden" name="action" value="decline">
                            <button class="decline" type="submit">Decline</button>
                        </form>

                    </div>

                <?php endif; ?>

            </div>

        <?php endwhile; ?>

    <?php else: ?>

        <div class="empty">
            <h2>It's quiet in here!</h2>
            <p>Your notifications will show up here.</p>
        </div>

    <?php endif; ?>

</main>

</body>
</html>

<?php
$stmt->close();
?>
