
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
$search = trim($_GET["q"] ?? "");

$searchTerm = "%" . $search . "%";

$stmt = $conn->prepare("
    SELECT id, name, profile_image, bio, is_private
    FROM users
    WHERE id != ? AND name LIKE ?
    ORDER BY name ASC
");
$stmt->bind_param("is", $userId, $searchTerm);
$stmt->execute();
$creators = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Discover Creators | CraveLab</title>

<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=Playfair+Display:wght@600;700;800&display=swap" rel="stylesheet">

<style>
*{box-sizing:border-box;margin:0;padding:0}
body{background:#faf8f4;color:#29251f;font-family:'DM Sans',sans-serif}
header{background:white;border-bottom:1px solid #eee8df;padding:16px 7%;display:flex;justify-content:space-between;align-items:center}
.brand{font-size:25px;font-weight:800;color:#29251f;text-decoration:none}
.brand span{color:#e56b45}
nav{display:flex;gap:22px}
nav a{color:#514b43;text-decoration:none;font-size:14px;font-weight:600}
.container{max-width:950px;margin:45px auto;padding:0 20px}
h1{font-family:'Playfair Display',serif;font-size:34px;margin-bottom:8px}
.subtitle{color:#8b8175;font-size:14px;margin-bottom:25px}
.search{display:flex;gap:10px;margin-bottom:28px}
.search input{flex:1;min-width:0;padding:13px 15px;border:1px solid #e5ddd3;border-radius:9px;background:white;font:inherit;outline:none}
.search input:focus{border-color:#e56b45}
.search button{border:0;border-radius:9px;padding:0 22px;background:#29251f;color:white;font-weight:700;cursor:pointer}
.grid{display:grid;grid-template-columns:repeat(2,1fr);gap:15px}
.creator{background:white;border:1px solid #eee8df;border-radius:13px;padding:18px;display:flex;align-items:center;gap:15px;min-width:0}
.avatar{width:65px;height:65px;border-radius:50%;object-fit:cover;background:#f0dfd2;display:flex;align-items:center;justify-content:center;font-size:25px;font-weight:800;color:#a34e31;flex-shrink:0}
.details{flex:1;min-width:0}
.details h2{font-size:16px;margin-bottom:5px}
.details h2 a{color:#29251f;text-decoration:none}
.details p{font-size:12px;color:#82796e;line-height:1.5;overflow-wrap:anywhere}
.private{font-size:10px;color:#a15c3d;font-weight:800;margin-top:5px}
.actions{display:flex;flex-direction:column;gap:7px;flex-shrink:0}
.btn{display:inline-block;text-align:center;border:0;border-radius:7px;padding:9px 12px;background:#29251f;color:white;text-decoration:none;font-size:11px;font-weight:700;cursor:pointer}
.btn.secondary{background:#eee7df;color:#51483f}
.empty{grid-column:1/-1;text-align:center;padding:60px 15px;color:#8b8175}
.empty h2{font-family:'Playfair Display',serif;color:#39332b;margin-bottom:8px}
@media(max-width:700px){
 header{padding:15px 5%}nav{gap:12px}nav a{font-size:12px}
 .container{margin-top:28px}.grid{grid-template-columns:1fr}
 .creator{padding:14px}.avatar{width:55px;height:55px}
 .btn{padding:8px 10px}
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
        <a href="notifications.php">Notifications</a>
        <a href="logout.php">Log out</a>
    </nav>
</header>

<main class="container">

    <h1>Find your food people. 🍜</h1>
    <p class="subtitle">Discover creators, explore their kitchens and follow their recipes.</p>

    <form class="search" method="GET" action="discover.php">
        <input
            type="search"
            name="q"
            placeholder="Search creators by name..."
            value="<?php echo htmlspecialchars($search); ?>"
        >
        <button type="submit">Search</button>
    </form>

    <section class="grid">

        <?php if ($creators->num_rows > 0): ?>

            <?php while ($creator = $creators->fetch_assoc()): ?>

                <?php
                $creatorId = (int)$creator["id"];

                $followStmt = $conn->prepare("
                    SELECT status FROM follows
                    WHERE follower_id = ? AND following_id = ?
                ");
                $followStmt->bind_param("ii", $userId, $creatorId);
                $followStmt->execute();
                $follow = $followStmt->get_result()->fetch_assoc();
                $followStmt->close();

                $followStatus = $follow["status"] ?? null;
                $avatarPath = $creator["profile_image"] ?? "";
                ?>

                <article class="creator">

                    <?php if ($avatarPath && strpos($avatarPath, "uploads/profiles/") === 0 && is_file(__DIR__ . "/" . $avatarPath)): ?>
                        <img class="avatar" src="<?php echo htmlspecialchars($avatarPath); ?>" alt="Profile picture">
                    <?php else: ?>
                        <div class="avatar">
                            <?php echo strtoupper(substr($creator["name"], 0, 1)); ?>
                        </div>
                    <?php endif; ?>

                    <div class="details">
                        <h2>
                            <a href="creator.php?id=<?php echo $creatorId; ?>">
                                <?php echo htmlspecialchars($creator["name"]); ?>
                            </a>
                        </h2>

                        <p>
                            <?php echo htmlspecialchars($creator["bio"] ?: "Cooking up something delicious."); ?>
                        </p>

                        <?php if ((int)$creator["is_private"] === 1): ?>
                            <div class="private">PRIVATE ACCOUNT</div>
                        <?php endif; ?>
                    </div>

                    <div class="actions">

                        <a class="btn secondary" href="creator.php?id=<?php echo $creatorId; ?>">
                            View Profile
                        </a>

                        <form action="toggle_follow.php" method="POST">
                            <input type="hidden" name="creator_id" value="<?php echo $creatorId; ?>">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">

                            <?php if ($followStatus === "accepted"): ?>
                                <button class="btn secondary" type="submit">Following</button>
                            <?php elseif ($followStatus === "pending"): ?>
                                <button class="btn secondary" type="submit">Requested</button>
                            <?php else: ?>
                                <button class="btn" type="submit">
                                    <?php echo (int)$creator["is_private"] === 1 ? "Request" : "+ Follow"; ?>
                                </button>
                            <?php endif; ?>
                        </form>

                    </div>

                </article>

            <?php endwhile; ?>

        <?php else: ?>

            <div class="empty">
                <h2>No creators found.</h2>
                <p>Try searching with another name.</p>
            </div>

        <?php endif; ?>

    </section>

</main>

</body>
</html>

<?php $stmt->close(); ?>
