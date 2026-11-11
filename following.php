
<?php
require_once __DIR__ . '/includes/session.php';

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

require_once 'includes/db.php';

$userId = (int)$_SESSION["user_id"];

$stmt = $conn->prepare("
    SELECT
        r.id,
        r.title,
        r.image,
        r.description,
        u.id AS creator_id,
        u.name AS creator_name,
        u.profile_image AS creator_image
    FROM follows f
    JOIN users u ON u.id = f.following_id
    JOIN recipes r ON r.user_id = u.id
    WHERE f.follower_id = ?
      AND f.status = 'accepted'
      AND (
          u.is_private = 0
          OR EXISTS (
              SELECT 1
              FROM follows access_check
              WHERE access_check.follower_id = ?
                AND access_check.following_id = u.id
                AND access_check.status = 'accepted'
          )
      )
    ORDER BY r.id DESC
");

$stmt->bind_param("ii", $userId, $userId);
$stmt->execute();
$recipes = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Following | CraveLab</title>

<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=Playfair+Display:wght@600;700;800&display=swap" rel="stylesheet">

<style>
*{box-sizing:border-box;margin:0;padding:0}
body{background:#faf8f4;color:#29251f;font-family:'DM Sans',sans-serif}
header{background:white;border-bottom:1px solid #eee8df;padding:16px 7%;display:flex;justify-content:space-between;align-items:center}
.brand{font-size:25px;font-weight:800;color:#29251f;text-decoration:none}
.brand span{color:#e56b45}
nav{display:flex;gap:20px}
nav a{color:#514b43;text-decoration:none;font-size:14px;font-weight:600}
.container{max-width:1050px;margin:40px auto;padding:0 20px}
h1{font-family:'Playfair Display',serif;font-size:34px;margin-bottom:8px}
.subtitle{color:#8b8175;font-size:14px;margin-bottom:28px}
.grid{display:grid;grid-template-columns:repeat(3,1fr);gap:20px}
.card{background:white;border:1px solid #eee8df;border-radius:13px;overflow:hidden;text-decoration:none;color:#29251f;transition:.2s}
.card:hover{transform:translateY(-3px)}
.food-image{width:100%;height:220px;object-fit:cover;background:#eee5da;display:block}
.card-content{padding:15px}
.card h2{font-size:17px;margin-bottom:12px}
.creator{display:flex;align-items:center;gap:9px;color:#81786d;font-size:12px}
.creator img,.creator-avatar{width:30px;height:30px;border-radius:50%;object-fit:cover;background:#f0dfd2;display:flex;align-items:center;justify-content:center;font-weight:800;color:#a34e31}
.empty{grid-column:1/-1;text-align:center;padding:65px 15px;color:#8b8175}
.empty h2{font-family:'Playfair Display',serif;color:#39332b;margin-bottom:9px}
.empty a{display:inline-block;margin-top:15px;color:#e56b45;text-decoration:none;font-weight:700}
@media(max-width:750px){.grid{grid-template-columns:repeat(2,1fr)}}
@media(max-width:520px){
 header{padding:15px 5%}nav{gap:10px}nav a{font-size:11px}
 .container{margin-top:25px}.grid{grid-template-columns:1fr}
 .food-image{height:230px}
}
</style>
</head>

<body>

<header>
    <a href="index.php" class="brand">Crave<span>Lab</span></a>
    <nav>
        <a href="index.php">Home</a>
        <a href="recipes.php">Recipes</a>
        <a href="discover.php">Discover</a>
        <a href="notifications.php">Notifications</a>
        <a href="dashboard.php">Dashboard</a>
    </nav>
</header>

<main class="container">

    <h1>Your food circle 🍽️</h1>
    <p class="subtitle">Fresh recipes from the creators you follow.</p>

    <section class="grid">

        <?php if ($recipes->num_rows > 0): ?>

            <?php while ($recipe = $recipes->fetch_assoc()): ?>

                <a class="card" href="recipe.php?id=<?php echo (int)$recipe["id"]; ?>">

                    <?php if (!empty($recipe["image"])): ?>
                        <img class="food-image"
                             src="<?php echo htmlspecialchars($recipe["image"]); ?>"
                             alt="<?php echo htmlspecialchars($recipe["title"]); ?>">
                    <?php else: ?>
                        <div class="food-image"></div>
                    <?php endif; ?>

                    <div class="card-content">
                        <h2><?php echo htmlspecialchars($recipe["title"]); ?></h2>

                        <div class="creator">

                            <?php if (!empty($recipe["creator_image"]) && strpos($recipe["creator_image"], "uploads/profiles/") === 0 && is_file(__DIR__ . "/" . $recipe["creator_image"])): ?>
                                <img src="<?php echo htmlspecialchars($recipe["creator_image"]); ?>" alt="">
                            <?php else: ?>
                                <div class="creator-avatar">
                                    <?php echo strtoupper(substr($recipe["creator_name"], 0, 1)); ?>
                                </div>
                            <?php endif; ?>

                            <span>
                                By <?php echo htmlspecialchars($recipe["creator_name"]); ?>
                            </span>

                        </div>
                    </div>

                </a>

            <?php endwhile; ?>

        <?php else: ?>

            <div class="empty">
                <h2>Your feed is looking a little empty!</h2>
                <p>Discover creators and follow them to see their latest recipes here.</p>
                <a href="discover.php">Discover creators →</a>
            </div>

        <?php endif; ?>

    </section>

</main>

</body>
</html>

<?php $stmt->close(); ?>
