
<?php
require_once __DIR__ . '/includes/session.php';

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

require_once 'includes/db.php';
require_once 'includes/security.php';
$csrfToken = cravelab_csrf_token();

$viewerId = (int)$_SESSION["user_id"];
$creatorId = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);

if (!$creatorId || $creatorId < 1) {
    header("Location: discover.php");
    exit();
}

$stmt = $conn->prepare("
    SELECT id, name, profile_image, bio, is_private, created_at
    FROM users
    WHERE id = ?
");
$stmt->bind_param("i", $creatorId);
$stmt->execute();
$creator = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$creator) {
    header("Location: discover.php");
    exit();
}

$isOwnProfile = ($viewerId === $creatorId);
$isPrivate = (int)$creator["is_private"];

$followStatus = null;

if (!$isOwnProfile) {
    $stmt = $conn->prepare("
        SELECT status FROM follows
        WHERE follower_id = ? AND following_id = ?
    ");
    $stmt->bind_param("ii", $viewerId, $creatorId);
    $stmt->execute();
    $followRow = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $followStatus = $followRow["status"] ?? null;
}

$canViewRecipes = $isOwnProfile || !$isPrivate || $followStatus === "accepted";

$stmt = $conn->prepare("
    SELECT COUNT(*) AS total FROM follows
    WHERE following_id = ? AND status = 'accepted'
");
$stmt->bind_param("i", $creatorId);
$stmt->execute();
$followers = (int)$stmt->get_result()->fetch_assoc()["total"];
$stmt->close();

$stmt = $conn->prepare("
    SELECT COUNT(*) AS total FROM follows
    WHERE follower_id = ? AND status = 'accepted'
");
$stmt->bind_param("i", $creatorId);
$stmt->execute();
$following = (int)$stmt->get_result()->fetch_assoc()["total"];
$stmt->close();

$stmt = $conn->prepare("
    SELECT COUNT(*) AS total FROM recipes WHERE user_id = ?
");
$stmt->bind_param("i", $creatorId);
$stmt->execute();
$postCount = (int)$stmt->get_result()->fetch_assoc()["total"];
if (!$canViewRecipes) { $postCount = 0; }
$stmt->close();

$recipes = null;

if ($canViewRecipes) {
    $stmt = $conn->prepare("
        SELECT id, title, image
        FROM recipes
        WHERE user_id = ?
        ORDER BY id DESC
    ");
    $stmt->bind_param("i", $creatorId);
    $stmt->execute();
    $recipes = $stmt->get_result();
}

$name = htmlspecialchars($creator["name"]);
$bio = htmlspecialchars($creator["bio"] ?? "");
$avatar = $creator["profile_image"] ?? "";
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo $name; ?> | CraveLab</title>

<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=Playfair+Display:wght@600;700;800&display=swap" rel="stylesheet">

<style>
*{box-sizing:border-box;margin:0;padding:0}
body{background:#faf8f4;color:#29251f;font-family:'DM Sans',sans-serif}
header{background:white;border-bottom:1px solid #eee8df;padding:16px 7%;display:flex;justify-content:space-between;align-items:center}
.brand{font-size:25px;font-weight:800;color:#29251f;text-decoration:none}
.brand span{color:#e56b45}
nav{display:flex;gap:22px}
nav a{color:#514b43;text-decoration:none;font-size:14px;font-weight:600}
.container{max-width:1000px;margin:45px auto;padding:0 22px}
.profile{display:flex;align-items:center;gap:30px;padding:20px 0 35px;border-bottom:1px solid #e9e2d8}
.avatar{width:120px;height:120px;border-radius:50%;object-fit:cover;background:#f0dfd2;display:flex;align-items:center;justify-content:center;font-size:42px;font-weight:800;color:#a34e31;flex-shrink:0}
.info{flex:1}
h1{font-family:'Playfair Display',serif;font-size:32px;margin-bottom:15px}
.stats{display:flex;gap:30px;flex-wrap:wrap;color:#756d62;font-size:14px}
.stats strong{color:#29251f;font-size:18px;margin-right:5px}
.bio{margin-top:16px;color:#625a50;font-size:14px;white-space:pre-line}
.actions{margin-top:18px}
.btn{display:inline-block;border:0;border-radius:8px;padding:10px 20px;background:#29251f;color:white;text-decoration:none;font-size:13px;font-weight:700;cursor:pointer}
.btn:hover{background:#e56b45}
.btn.secondary{background:#eee7df;color:#51483f}
.tabs{margin:25px 0 20px;text-align:center;color:#8b8175;font-size:12px;font-weight:800;letter-spacing:1.5px}
.grid{display:grid;grid-template-columns:repeat(3,1fr);gap:18px}
.card{position:relative;aspect-ratio:1/1;overflow:hidden;border-radius:12px;background:#eee5da;text-decoration:none}
.card img{width:100%;height:100%;object-fit:cover;transition:.3s}
.card:hover img{transform:scale(1.06)}
.overlay{position:absolute;inset:0;background:linear-gradient(transparent 45%,rgba(0,0,0,.75));display:flex;align-items:flex-end;padding:16px;color:white;font-weight:700}
.private-box,.empty{padding:65px 15px;text-align:center;color:#81786d}
.private-box h2,.empty h2{font-family:'Playfair Display',serif;color:#39332b;margin-bottom:10px}
@media(max-width:650px){
 header{padding:15px 5%}nav{gap:12px}nav a{font-size:12px}
 .container{margin-top:25px}.profile{gap:16px;align-items:flex-start}
 .avatar{width:85px;height:85px;font-size:30px}
 h1{font-size:24px}.stats{gap:12px 18px}.stats strong{font-size:15px}
 .grid{gap:8px}.overlay{font-size:11px;padding:9px}
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

<section class="profile">

    <?php if ($avatar && strpos($avatar, "uploads/profiles/") === 0 && is_file(__DIR__ . "/" . $avatar)): ?>
        <img class="avatar" src="<?php echo htmlspecialchars($avatar); ?>" alt="Profile picture">
    <?php else: ?>
        <div class="avatar"><?php echo strtoupper(substr($name, 0, 1)); ?></div>
    <?php endif; ?>

    <div class="info">
        <h1><?php echo $name; ?></h1>

        <div class="stats">
            <span><strong><?php echo $postCount; ?></strong> Posts</span>
            <span><strong><?php echo $followers; ?></strong> Followers</span>
            <span><strong><?php echo $following; ?></strong> Following</span>
        </div>

        <?php if ($bio): ?>
            <div class="bio"><?php echo $bio; ?></div>
        <?php endif; ?>

        <div class="actions">
        <?php if ($isOwnProfile): ?>
            <a href="edit_profile.php" class="btn">Edit Profile</a>
        <?php elseif ($followStatus === "accepted"): ?>
            <form action="toggle_follow.php" method="POST">
                <input type="hidden" name="creator_id" value="<?php echo $creatorId; ?>">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                <button class="btn secondary" type="submit">Following · Unfollow</button>
            </form>
        <?php elseif ($followStatus === "pending"): ?>
            <form action="toggle_follow.php" method="POST">
                <input type="hidden" name="creator_id" value="<?php echo $creatorId; ?>">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                <button class="btn secondary" type="submit">Requested · Cancel</button>
            </form>
        <?php elseif ($isPrivate): ?>
            <form action="toggle_follow.php" method="POST">
                <input type="hidden" name="creator_id" value="<?php echo $creatorId; ?>">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                <button class="btn" type="submit">Request to Follow</button>
            </form>
        <?php else: ?>
            <form action="toggle_follow.php" method="POST">
                <input type="hidden" name="creator_id" value="<?php echo $creatorId; ?>">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                <button class="btn" type="submit">+ Follow</button>
            </form>
        <?php endif; ?>
        </div>
    </div>
</section>

<div class="tabs">RECIPES</div>

<?php if (!$canViewRecipes): ?>

    <div class="private-box">
        <h2>This kitchen is private 🔒</h2>
        <p>Follow this creator to see their recipes.</p>
    </div>

<?php elseif ($recipes && $recipes->num_rows > 0): ?>

    <section class="grid">
        <?php while ($recipe = $recipes->fetch_assoc()): ?>
            <a class="card" href="recipe.php?id=<?php echo (int)$recipe['id']; ?>">
                <?php if (!empty($recipe["image"])): ?>
                    <img src="<?php echo htmlspecialchars($recipe["image"]); ?>" alt="<?php echo htmlspecialchars($recipe["title"]); ?>">
                <?php endif; ?>
                <div class="overlay"><?php echo htmlspecialchars($recipe["title"]); ?></div>
            </a>
        <?php endwhile; ?>
    </section>

<?php else: ?>

    <div class="empty">
        <h2>No recipes yet.</h2>
        <p>This creator hasn't shared anything yet.</p>
    </div>

<?php endif; ?>

</main>
</body>
</html>

<?php
if (isset($stmt) && $stmt instanceof mysqli_stmt) {
    $stmt->close();
}
?>

