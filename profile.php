
<?php
require_once __DIR__ . '/includes/session.php';

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

require_once 'includes/db.php';

$userId = (int) $_SESSION["user_id"];

$stmt = $conn->prepare("
    SELECT id, name, email, profile_image, bio, is_private, created_at
    FROM users
    WHERE id = ?
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    session_destroy();
    header("Location: login.php");
    exit();
}

$name = htmlspecialchars($user["name"]);
$bio = htmlspecialchars($user["bio"] ?? "");
$profileImage = $user["profile_image"] ?? "";
$isPrivate = (int)($user["is_private"] ?? 0);

$avatarPath = "";
if ($profileImage && strpos($profileImage, "uploads/profiles/") === 0 && is_file(__DIR__ . "/" . $profileImage)) {
    $avatarPath = $profileImage;
}

$tab = $_GET["tab"] ?? "posts";
if (!in_array($tab, ["posts", "saved"])) {
    $tab = "posts";
}

// Counts
$stmt = $conn->prepare("SELECT COUNT(*) AS total FROM recipes WHERE user_id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$postsCount = (int)$stmt->get_result()->fetch_assoc()["total"];
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) AS total FROM saved_recipes WHERE user_id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$savedCount = (int)$stmt->get_result()->fetch_assoc()["total"];
$stmt->close();

$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM follows
    WHERE following_id = ? AND status = 'accepted'
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$followersCount = (int)$stmt->get_result()->fetch_assoc()["total"];
$stmt->close();

$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM follows
    WHERE follower_id = ? AND status = 'accepted'
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$followingCount = (int)$stmt->get_result()->fetch_assoc()["total"];
$stmt->close();

// Recipes
if ($tab === "saved") {
    $stmt = $conn->prepare("
        SELECT r.id, r.title, r.image
        FROM saved_recipes s
        JOIN recipes r ON s.recipe_id = r.id
        JOIN users u ON u.id = r.user_id
        WHERE s.user_id = ?
          AND (
              u.is_private = 0
              OR r.user_id = ?
              OR EXISTS (
                  SELECT 1 FROM follows f
                  WHERE f.follower_id = ?
                    AND f.following_id = u.id
                    AND f.status = 'accepted'
              )
          )
        ORDER BY s.created_at DESC
    ");
} else {
    $stmt = $conn->prepare("
        SELECT id, title, image
        FROM recipes
        WHERE user_id = ?
        ORDER BY id DESC
    ");
}

if ($tab === "saved") {
    $stmt->bind_param("iii", $userId, $userId, $userId);
} else {
    $stmt->bind_param("i", $userId);
}
$stmt->execute();
$recipes = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Profile | CraveLab</title>

<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=Playfair+Display:wght@600;700;800&display=swap" rel="stylesheet">

<style>
* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

body {
    background: #faf8f4;
    color: #29251f;
    font-family: 'DM Sans', sans-serif;
}

.site-header {
    background: #fff;
    border-bottom: 1px solid #eee8df;
    padding: 16px 7%;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.brand {
    color: #29251f;
    text-decoration: none;
    font-size: 25px;
    font-weight: 800;
}

.brand span {
    color: #e56b45;
}

.nav {
    display: flex;
    align-items: center;
    gap: 22px;
}

.nav a {
    text-decoration: none;
    color: #514b43;
    font-size: 14px;
    font-weight: 600;
}

.nav a:hover {
    color: #e56b45;
}

.container {
    max-width: 1050px;
    margin: 45px auto;
    padding: 0 22px;
}

.profile-header {
    display: flex;
    align-items: center;
    gap: 35px;
    padding: 25px 0 35px;
    border-bottom: 1px solid #e9e2d8;
}

.avatar {
    width: 125px;
    height: 125px;
    border-radius: 50%;
    object-fit: cover;
    background: #f0dfd2;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #a34e31;
    font-size: 45px;
    font-weight: 800;
    flex-shrink: 0;
}

.profile-info {
    flex: 1;
}

.profile-info h1 {
    font-family: 'Playfair Display', serif;
    font-size: 32px;
    margin-bottom: 8px;
}

.profile-meta {
    color: #81796e;
    font-size: 13px;
    margin-bottom: 18px;
}

.stats {
    display: flex;
    gap: 35px;
    flex-wrap: wrap;
}

.stat {
    color: #756d62;
    font-size: 14px;
}

.stat strong {
    color: #29251f;
    font-size: 18px;
    margin-right: 5px;
}

.bio {
    margin-top: 17px;
    font-size: 14px;
    color: #5f584e;
    white-space: pre-line;
}

.private-label {
    display: inline-block;
    margin-top: 10px;
    background: #f2e9df;
    color: #8a573e;
    padding: 5px 11px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 700;
}

.edit-button {
    display: inline-block;
    background: #29251f;
    color: white;
    text-decoration: none;
    padding: 10px 18px;
    border-radius: 9px;
    font-size: 13px;
    font-weight: 700;
}

.edit-button:hover {
    background: #e56b45;
}

.tabs {
    display: flex;
    justify-content: center;
    gap: 55px;
    margin: 25px 0;
    border-bottom: 1px solid #e9e2d8;
}

.tabs a {
    padding: 13px 5px;
    text-decoration: none;
    color: #92897e;
    font-size: 13px;
    font-weight: 800;
    letter-spacing: 1px;
}

.tabs a.active {
    color: #e56b45;
    border-bottom: 2px solid #e56b45;
}

.recipe-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 18px;
}

.recipe-card {
    position: relative;
    aspect-ratio: 1 / 1;
    overflow: hidden;
    border-radius: 12px;
    background: #eee5da;
}

.recipe-card img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform .3s;
}

.recipe-card:hover img {
    transform: scale(1.06);
}

.recipe-overlay {
    position: absolute;
    inset: 0;
    background: linear-gradient(transparent 45%, rgba(0,0,0,.75));
    display: flex;
    align-items: flex-end;
    padding: 17px;
    color: white;
    font-weight: 700;
    font-size: 15px;
}

.empty {
    text-align: center;
    padding: 65px 15px;
    color: #8b8175;
    grid-column: 1 / -1;
}

.empty h2 {
    font-family: 'Playfair Display', serif;
    color: #39332b;
    margin-bottom: 8px;
}

.empty a {
    display: inline-block;
    margin-top: 18px;
    color: #e56b45;
    text-decoration: none;
    font-weight: 700;
}

@media(max-width: 650px) {
    .site-header {
        padding: 15px 5%;
    }

    .nav {
        gap: 12px;
    }

    .nav a {
        font-size: 12px;
    }

    .container {
        margin-top: 25px;
    }

    .profile-header {
        gap: 18px;
        align-items: flex-start;
    }

    .avatar {
        width: 85px;
        height: 85px;
        font-size: 32px;
    }

    .profile-info h1 {
        font-size: 24px;
    }

    .stats {
        gap: 12px 20px;
    }

    .stat {
        font-size: 12px;
    }

    .stat strong {
        font-size: 15px;
    }

    .edit-button {
        margin-top: 15px;
    }

    .recipe-grid {
        gap: 8px;
    }

    .recipe-overlay {
        font-size: 11px;
        padding: 9px;
    }
}
</style>
</head>

<body>

<header class="site-header">
    <a href="index.php" class="brand">Crave<span>Lab</span></a>

    <nav class="nav">
        <a href="index.php">Home</a>
        <a href="recipes.php">Recipes</a>
        <a href="dashboard.php">Dashboard</a>
        <a href="logout.php">Log out</a>
    </nav>
</header>

<main class="container">

    <section class="profile-header">

        <?php if ($avatarPath): ?>
            <img src="<?php echo htmlspecialchars($avatarPath); ?>" class="avatar" alt="Profile picture">
        <?php else: ?>
            <div class="avatar">
                <?php echo strtoupper(substr($name, 0, 1)); ?>
            </div>
        <?php endif; ?>

        <div class="profile-info">

            <h1><?php echo $name; ?></h1>

            <div class="profile-meta">
                CraveLab member since <?php echo date("M Y", strtotime($user["created_at"])); ?>
            </div>

            <div class="stats">
                <div class="stat"><strong><?php echo $postsCount; ?></strong> Posts</div>
                <div class="stat"><strong><?php echo $followersCount; ?></strong> Followers</div>
                <div class="stat"><strong><?php echo $followingCount; ?></strong> Following</div>
                <div class="stat"><strong><?php echo $savedCount; ?></strong> Saved</div>
            </div>

            <?php if ($bio): ?>
                <div class="bio"><?php echo $bio; ?></div>
            <?php endif; ?>

            <?php if ($isPrivate): ?>
                <span class="private-label">PRIVATE ACCOUNT</span>
            <?php endif; ?>

            <br><br>
            <a href="edit_profile.php" class="edit-button">Edit Profile</a>

        </div>
    </section>

    <div class="tabs">
        <a href="profile.php?tab=posts" class="<?php echo $tab === 'posts' ? 'active' : ''; ?>">
            MY RECIPES
        </a>

        <a href="profile.php?tab=saved" class="<?php echo $tab === 'saved' ? 'active' : ''; ?>">
            SAVED
        </a>
    </div>

    <section class="recipe-grid">

        <?php if ($recipes->num_rows > 0): ?>

            <?php while ($recipe = $recipes->fetch_assoc()): ?>
                <a href="recipe.php?id=<?php echo (int)$recipe['id']; ?>" class="recipe-card">

                    <?php if (!empty($recipe["image"])): ?>
                        <img src="<?php echo htmlspecialchars($recipe["image"]); ?>" alt="<?php echo htmlspecialchars($recipe["title"]); ?>">
                    <?php else: ?>
                        <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;color:#8b8175;">
                            No image
                        </div>
                    <?php endif; ?>

                    <div class="recipe-overlay">
                        <?php echo htmlspecialchars($recipe["title"]); ?>
                    </div>

                </a>
            <?php endwhile; ?>

        <?php else: ?>

            <div class="empty">
                <h2>
                    <?php echo $tab === "saved" ? "Nothing saved yet!" : "Your kitchen is waiting."; ?>
                </h2>

                <p>
                    <?php echo $tab === "saved"
                        ? "Save recipes you want to try later."
                        : "Share your first recipe with the CraveLab community."; ?>
                </p>

                <a href="recipes.php">Explore recipes →</a>
            </div>

        <?php endif; ?>

    </section>

</main>

</body>
</html>

<?php
$stmt->close();
?>
