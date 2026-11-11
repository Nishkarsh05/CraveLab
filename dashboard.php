
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

// Get current user's basic information for navigation
$userStmt = $conn->prepare(
    "SELECT name, profile_image FROM users WHERE id = ?"
);
$userStmt->bind_param("i", $userId);
$userStmt->execute();
$user = $userStmt->get_result()->fetch_assoc();
$userStmt->close();

if (!$user) {
    session_destroy();
    header("Location: login.php");
    exit();
}

$_SESSION["user_name"] = $user["name"];

$profileImage = trim($user["profile_image"] ?? "");
$profileImageExists = (
    $profileImage !== "" &&
    strpos($profileImage, "uploads/profiles/") === 0 &&
    is_file(__DIR__ . "/" . $profileImage)
);

// Count uploaded recipes
$stmt = $conn->prepare(
    "SELECT COUNT(*) AS total FROM recipes WHERE user_id = ?"
);
$stmt->bind_param("i", $userId);
$stmt->execute();
$recipeCount = (int)$stmt->get_result()->fetch_assoc()["total"];
$stmt->close();

// Count saved recipes
$stmt = $conn->prepare(
    "SELECT COUNT(*) AS total FROM saved_recipes WHERE user_id = ?"
);
$stmt->bind_param("i", $userId);
$stmt->execute();
$savedCount = (int)$stmt->get_result()->fetch_assoc()["total"];
$stmt->close();

// Count followers
$stmt = $conn->prepare(
    "SELECT COUNT(*) AS total FROM follows
     WHERE following_id = ? AND status = 'accepted'"
);
$stmt->bind_param("i", $userId);
$stmt->execute();
$followerCount = (int)$stmt->get_result()->fetch_assoc()["total"];
$stmt->close();

// Count following
$stmt = $conn->prepare(
    "SELECT COUNT(*) AS total FROM follows
     WHERE follower_id = ? AND status = 'accepted'"
);
$stmt->bind_param("i", $userId);
$stmt->execute();
$followingCount = (int)$stmt->get_result()->fetch_assoc()["total"];
$stmt->close();

// Get own recipes
$myRecipesStmt = $conn->prepare(
    "SELECT id, title, description, image, cooking_time, difficulty
     FROM recipes
     WHERE user_id = ?
     ORDER BY created_at DESC"
);
$myRecipesStmt->bind_param("i", $userId);
$myRecipesStmt->execute();
$myRecipes = $myRecipesStmt->get_result();

// Get saved recipes
$savedStmt = $conn->prepare(
    "SELECT r.id, r.title, r.description, r.image,
            r.cooking_time, r.difficulty, u.name AS chef
     FROM saved_recipes s
     JOIN recipes r ON s.recipe_id = r.id
     JOIN users u ON r.user_id = u.id
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
     ORDER BY s.created_at DESC"
);
$savedStmt->bind_param("iii", $userId, $userId, $userId);
$savedStmt->execute();
$savedRecipes = $savedStmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>My Kitchen | CraveLab</title>
<link rel="stylesheet" href="assets/css/style.css">

<style>
body {
    background: #fbfaf6;
}

.dashboard {
    max-width: 1150px;
    margin: 35px auto;
    padding: 0 22px;
}

.nav-profile-actions {
    display: flex;
    align-items: center;
    gap: 18px;
}

.nav-profile-button {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    overflow: hidden;
    display: grid;
    place-items: center;
    background: #f5eee4;
    border: 2px solid #e36b42;
    text-decoration: none;
    color: #333;
    font-size: 20px;
}

.nav-profile-button img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.logout-link {
    color: #f36b3f;
    text-decoration: none;
    font-weight: bold;
}

.dashboard-heading {
    margin-bottom: 25px;
}

.dashboard-heading h1 {
    font-family: Georgia, serif;
    font-size: 34px;
    margin: 8px 0;
}

.dashboard-heading p {
    color: #777;
    font-size: 13px;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 12px;
    margin-bottom: 32px;
}

.stat-card {
    background: white;
    border: 1px solid #eee;
    border-radius: 10px;
    padding: 15px;
}

.stat-card span {
    font-size: 21px;
}

.stat-card h2 {
    font-family: Georgia, serif;
    font-size: 25px;
    margin: 7px 0 3px;
}

.stat-card p {
    color: #777;
    font-size: 11px;
    margin: 0;
}

.section-heading {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin: 30px 0 16px;
    gap: 15px;
}

.section-heading h2 {
    font-family: Georgia, serif;
    font-size: 27px;
    margin: 0;
}

.section-heading a {
    color: #e36b42;
    text-decoration: none;
    font-size: 13px;
    font-weight: 700;
}

.recipe-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 17px;
}

.recipe-card {
    background: white;
    border: 1px solid #eee;
    border-radius: 10px;
    overflow: hidden;
    transition: .2s;
}

.recipe-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 20px #00000010;
}

.recipe-card img,
.recipe-placeholder {
    width: 100%;
    height: 145px;
    object-fit: cover;
    display: block;
}

.recipe-placeholder {
    background: #f4eee3;
    display: grid;
    place-items: center;
    font-size: 40px;
}

.recipe-content {
    padding: 13px;
}

.recipe-content h3 {
    font-family: Georgia, serif;
    font-size: 18px;
    margin: 0 0 6px;
}

.recipe-content p {
    color: #777;
    font-size: 11px;
    line-height: 1.5;
    margin: 5px 0;
}

.recipe-meta {
    color: #777;
    font-size: 10px;
    margin: 9px 0;
}

.recipe-actions {
    display: flex;
    gap: 12px;
    margin-top: 11px;
    flex-wrap: wrap;
    align-items: center;
}

.recipe-actions a,
.recipe-actions button {
    text-decoration: none;
    color: #e36b42;
    font-weight: 700;
    font-size: 11px;
}

.recipe-actions button {
    border: 0;
    background: none;
    padding: 0;
    cursor: pointer;
    font-family: inherit;
}

.empty-state {
    background: white;
    padding: 25px 18px;
    text-align: center;
    border: 1px solid #eee;
    border-radius: 10px;
    color: #777;
}

.empty-state h3 {
    color: #20231f;
    font-family: Georgia, serif;
}

.empty-state a {
    color: #e36b42;
    font-weight: bold;
    text-decoration: none;
}

.dashboard-links {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 13px;
    margin: 32px 0;
}

.dash-link {
    display: block;
    background: white;
    padding: 15px;
    border: 1px solid #eee;
    border-radius: 10px;
    text-decoration: none;
    color: inherit;
    transition: .2s;
}

.dash-link:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 20px #00000010;
}

.dash-link strong {
    display: block;
    margin: 7px 0;
    font-size: 13px;
}

.dash-link p {
    font-size: 11px;
    color: #777;
    margin: 0;
}

@media(max-width: 850px) {
    .stats-grid {
        grid-template-columns: repeat(3, 1fr);
    }

    .recipe-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media(max-width: 550px) {
    .dashboard {
        margin-top: 22px;
    }

    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }

    .recipe-grid,
    .dashboard-links {
        grid-template-columns: 1fr;
    }

    .section-heading h2 {
        font-size: 23px;
    }
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

        <div class="nav-profile-actions">

            <a href="profile.php"
               class="nav-profile-button"
               title="My profile">

                <?php if ($profileImageExists): ?>
                    <img src="<?= htmlspecialchars($profileImage, ENT_QUOTES, 'UTF-8') ?>"
                         alt="My profile">
                <?php else: ?>
                    👤
                <?php endif; ?>

            </a>

            <a href="logout.php" class="logout-link">Log out ↗</a>

        </div>
    </nav>
</header>

<main class="dashboard">

    <section class="dashboard-heading">
        <div class="eyebrow">YOUR PERSONAL KITCHEN</div>
        <h1>Welcome back, <?= htmlspecialchars($user["name"]) ?>!</h1>
        <p>Your recipes, your saves, your food community.</p>
    </section>

    <section class="stats-grid">

        <div class="stat-card">
            <span>👨‍🍳</span>
            <h2><?= $recipeCount ?></h2>
            <p>Recipes shared</p>
        </div>

        <div class="stat-card">
            <span>🔖</span>
            <h2><?= $savedCount ?></h2>
            <p>Saved recipes</p>
        </div>

        <div class="stat-card">
            <span>👥</span>
            <h2><?= $followerCount ?></h2>
            <p>Followers</p>
        </div>

        <div class="stat-card">
            <span>❤️</span>
            <h2><?= $followingCount ?></h2>
            <p>Following</p>
        </div>

        <div class="stat-card">
            <span>🍽️</span>
            <h2><?= $recipeCount + $savedCount ?></h2>
            <p>Kitchen activity</p>
        </div>

    </section>

    <section>
        <div class="section-heading">
            <h2>My Recipes</h2>
            <a href="add_recipe.php">+ Add a recipe</a>
        </div>

        <?php if ($myRecipes->num_rows > 0): ?>

            <div class="recipe-grid">

                <?php while ($recipe = $myRecipes->fetch_assoc()): ?>

                    <?php
                    $image = trim($recipe["image"] ?? "");

                    $imageExists = (
                        $image !== "" &&
                        strpos($image, "uploads/recipes/") === 0 &&
                        is_file(__DIR__ . "/" . $image)
                    );
                    ?>

                    <article class="recipe-card">

                        <?php if ($imageExists): ?>
                            <img src="<?= htmlspecialchars($image, ENT_QUOTES, 'UTF-8') ?>"
                                 alt="<?= htmlspecialchars($recipe["title"]) ?>">
                        <?php else: ?>
                            <div class="recipe-placeholder">🍲</div>
                        <?php endif; ?>

                        <div class="recipe-content">

                            <h3><?= htmlspecialchars($recipe["title"]) ?></h3>

                            <p><?= htmlspecialchars($recipe["description"]) ?></p>

                            <div class="recipe-meta">
                                ⏱ <?= (int)$recipe["cooking_time"] ?> minutes
                                · <?= htmlspecialchars($recipe["difficulty"]) ?>
                            </div>

                            <div class="recipe-actions">

                                <a href="recipe.php?id=<?= (int)$recipe["id"] ?>">
                                    View recipe
                                </a>

                                <a href="edit_recipe.php?id=<?= (int)$recipe["id"] ?>">
                                    Edit
                                </a>

                                <form method="POST"
                                      action="delete_recipe.php"
                                      onsubmit="return confirm('Delete this recipe?');"
                                      style="display:inline;">

                                    <input type="hidden"
                                           name="id"
                                           value="<?= (int)$recipe["id"] ?>">

                                    <input type="hidden"
                                           name="recipe_id"
                                           value="<?= (int)$recipe["id"] ?>">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

                                    <button type="submit">Delete</button>
                                </form>

                            </div>
                        </div>
                    </article>

                <?php endwhile; ?>

            </div>

        <?php else: ?>

            <div class="empty-state">
                <h3>Your kitchen is waiting!</h3>
                <p>You haven't shared any recipes yet.</p>
                <a href="add_recipe.php">Share your first recipe →</a>
            </div>

        <?php endif; ?>
    </section>

    <section>

        <div class="section-heading">
            <h2>Saved Recipes</h2>
            <a href="recipes.php">Explore more →</a>
        </div>

        <?php if ($savedRecipes->num_rows > 0): ?>

            <div class="recipe-grid">

                <?php while ($recipe = $savedRecipes->fetch_assoc()): ?>

                    <?php
                    $image = trim($recipe["image"] ?? "");

                    $imageExists = (
                        $image !== "" &&
                        strpos($image, "uploads/recipes/") === 0 &&
                        is_file(__DIR__ . "/" . $image)
                    );
                    ?>

                    <article class="recipe-card">

                        <?php if ($imageExists): ?>
                            <img src="<?= htmlspecialchars($image, ENT_QUOTES, 'UTF-8') ?>"
                                 alt="<?= htmlspecialchars($recipe["title"]) ?>">
                        <?php else: ?>
                            <div class="recipe-placeholder">🍲</div>
                        <?php endif; ?>

                        <div class="recipe-content">

                            <h3><?= htmlspecialchars($recipe["title"]) ?></h3>

                            <p><?= htmlspecialchars($recipe["description"]) ?></p>

                            <div class="recipe-meta">
                                By <?= htmlspecialchars($recipe["chef"]) ?>
                                · <?= (int)$recipe["cooking_time"] ?> min
                            </div>

                            <div class="recipe-actions">
                                <a href="recipe.php?id=<?= (int)$recipe["id"] ?>">
                                    View recipe →
                                </a>
                            </div>

                        </div>
                    </article>

                <?php endwhile; ?>

            </div>

        <?php else: ?>

            <div class="empty-state">
                <h3>No saved recipes yet.</h3>
                <p>Find recipes you love and save them here.</p>
                <a href="recipes.php">Explore recipes →</a>
            </div>

        <?php endif; ?>

    </section>

    <section class="dashboard-links">

        <a href="add_recipe.php" class="dash-link">
            <span>🍳</span>
            <strong>Add a Recipe</strong>
            <p>Share something delicious.</p>
        </a>

        <a href="recipes.php" class="dash-link">
            <span>📖</span>
            <strong>Explore Recipes</strong>
            <p>Discover the community's creations.</p>
        </a>

        <a href="fridge_roulette.php" class="dash-link">
            <span>🎯</span>
            <strong>Fridge Roulette</strong>
            <p>Find recipes using your ingredients.</p>
        </a>

    </section>

</main>

</body>
</html>
