
<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/includes/session.php';

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

require_once 'includes/db.php';
require_once 'includes/security.php';

$recipes = [];
$ingredients = [];
$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $rawIngredients = trim($_POST["ingredients"] ?? "");

    $parts = explode(",", $rawIngredients);

    foreach ($parts as $part) {
        $part = trim($part);

        if ($part !== "") {
            $ingredients[] = $part;
        }
    }

    $ingredients = array_slice(array_unique($ingredients), 0, 15);

    if (empty($ingredients)) {

        $message = "Please enter at least one ingredient.";

    } else {

        $conditions = [];
        $params = [];

        foreach ($ingredients as $ingredient) {
            $conditions[] = "r.ingredients LIKE ?";
            $params[] = "%" . $ingredient . "%";
        }

        $sql = "
            SELECT r.*, u.name AS chef, c.category_name
            FROM recipes r
            JOIN users u ON r.user_id = u.id
            LEFT JOIN categories c ON r.category_id = c.id
            WHERE (" . implode(" OR ", $conditions) . ")
              AND (
                  u.is_private = 0
                  OR r.user_id = ?
                  OR EXISTS (
                      SELECT 1 FROM follows f
                      WHERE f.follower_id = ?
                        AND f.following_id = u.id
                        AND f.status = 'accepted'
                  )
              )";

        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            die("Database query error: " . htmlspecialchars($conn->error));
        }

        $params[] = (int)$_SESSION["user_id"];
        $params[] = (int)$_SESSION["user_id"];
        $types = str_repeat("s", count($ingredients)) . "ii";

        $stmt->bind_param($types, ...$params);

        if (!$stmt->execute()) {
            die("Search error: " . htmlspecialchars($stmt->error));
        }

        $result = $stmt->get_result();

        while ($recipe = $result->fetch_assoc()) {

            $matches = 0;

            foreach ($ingredients as $ingredient) {
                if (stripos($recipe["ingredients"], $ingredient) !== false) {
                    $matches++;
                }
            }

            $recipe["match_count"] = $matches;
            $recipes[] = $recipe;
        }

        $stmt->close();

        usort($recipes, function ($a, $b) {
            return $b["match_count"] - $a["match_count"];
        });

        if (empty($recipes)) {
            $message = "No matching recipes found. Try adding different ingredients!";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Fridge Roulette | CraveLab</title>

    <link rel="stylesheet" href="assets/css/style.css">

    <style>
        body {
            background: #fbfaf6;
        }

        .roulette-container {
            max-width: 1100px;
            margin: 55px auto;
            padding: 0 22px;
        }

        .roulette-hero {
            background: #f4eee3;
            padding: 55px 30px;
            border-radius: 16px;
            text-align: center;
        }

        .roulette-hero .eyebrow {
            color: #e36b42;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 2px;
        }

        .roulette-hero h1 {
            font-family: Georgia, serif;
            font-size: clamp(36px, 6vw, 55px);
            margin: 15px 0;
            color: #20231f;
        }

        .roulette-hero p {
            color: #777b70;
            line-height: 1.7;
            max-width: 580px;
            margin: 0 auto 25px;
        }

        .ingredient-form {
            max-width: 700px;
            margin: auto;
        }

        .ingredient-form textarea {
            width: 100%;
            min-height: 100px;
            padding: 16px;
            border: 1px solid #ddd8cc;
            border-radius: 9px;
            font: inherit;
            box-sizing: border-box;
            resize: vertical;
        }

        .ingredient-form button {
            margin-top: 15px;
            border: none;
            cursor: pointer;
        }

        .hint {
            font-size: 12px;
            color: #777;
            margin-top: 9px;
        }

        .results-heading {
            margin: 40px 0 22px;
        }

        .results-heading h2 {
            font-family: Georgia, serif;
            font-size: 30px;
        }

        .results-heading p {
            color: #777;
        }

        .roulette-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 22px;
        }

        .roulette-card {
            background: white;
            border: 1px solid #eee;
            border-radius: 12px;
            overflow: hidden;
            transition: .2s;
        }

        .roulette-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 30px rgba(0,0,0,.07);
        }

        .roulette-card img,
        .recipe-placeholder {
            width: 100%;
            height: 205px;
            object-fit: cover;
        }

        .recipe-placeholder {
            display: grid;
            place-items: center;
            background: #f4eee3;
            font-size: 55px;
        }

        .roulette-card-content {
            padding: 20px;
        }

        .match-label {
            display: inline-block;
            background: #eaf3e7;
            color: #3d7442;
            padding: 6px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 800;
        }

        .roulette-card h3 {
            font-family: Georgia, serif;
            font-size: 22px;
        }

        .roulette-card p {
            font-size: 13px;
            color: #777;
            line-height: 1.6;
        }

        .recipe-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            font-size: 12px;
            color: #777;
            margin: 15px 0;
        }

        .view-recipe {
            color: #e36b42;
            font-weight: 800;
            text-decoration: none;
        }

        .empty-message {
            background: white;
            border: 1px solid #eee;
            padding: 22px;
            border-radius: 10px;
            color: #777;
            text-align: center;
            margin-top: 25px;
        }

        @media(max-width: 800px) {
            .roulette-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media(max-width: 550px) {
            .roulette-grid {
                grid-template-columns: 1fr;
            }

            .roulette-hero {
                padding: 38px 20px;
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

        <a href="dashboard.php" class="text-link">
            ← Dashboard
        </a>

    </nav>
</header>

<main class="roulette-container">

    <section class="roulette-hero">

        <div class="eyebrow">THE FRIDGE KNOWS BEST</div>

        <h1>What's cooking?</h1>

        <p>
            Got random ingredients and absolutely no plan?
            Enter what you have. We'll find recipes from the CraveLab community.
        </p>

        <form method="POST" class="ingredient-form">

            <textarea
                name="ingredients"
                placeholder="Potato, onion, tomato, cheese..."
                required><?= htmlspecialchars(implode(", ", $ingredients)) ?></textarea>

            <div class="hint">
                Separate ingredients with commas.
            </div>

            <button type="submit" class="btn btn-orange">
                Find my recipes ↗
            </button>

        </form>

    </section>

    <?php if ($message !== ""): ?>
        <div class="empty-message">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($recipes)): ?>

        <section>

            <div class="results-heading">
                <h2>Your fridge, your possibilities.</h2>
                <p>
                    Found <?= count($recipes) ?> matching recipe(s).
                </p>
            </div>

            <div class="roulette-grid">

                <?php foreach ($recipes as $recipe): ?>

                    <?php
                    $imagePath = trim($recipe["image"] ?? "");

                    $imageExists = (
                        $imagePath !== "" &&
                        strpos($imagePath, "uploads/recipes/") === 0 &&
                        is_file(__DIR__ . "/" . $imagePath)
                    );
                    ?>

                    <article class="roulette-card">

                        <?php if ($imageExists): ?>

                            <img
                                src="<?= htmlspecialchars($imagePath, ENT_QUOTES, 'UTF-8') ?>"
                                alt="<?= htmlspecialchars($recipe["title"]) ?>">

                        <?php else: ?>

                            <div class="recipe-placeholder">🍲</div>

                        <?php endif; ?>

                        <div class="roulette-card-content">

                            <span class="match-label">
                                <?= (int)$recipe["match_count"] ?> ingredient matches
                            </span>

                            <h3>
                                <?= htmlspecialchars($recipe["title"]) ?>
                            </h3>

                            <p>
                                <?= htmlspecialchars($recipe["description"]) ?>
                            </p>

                            <div class="recipe-meta">
                                <span>⏱ <?= (int)$recipe["cooking_time"] ?> min</span>
                                <span><?= htmlspecialchars($recipe["difficulty"]) ?></span>
                                <span>By <?= htmlspecialchars($recipe["chef"]) ?></span>
                            </div>

                            <a
                                class="view-recipe"
                                href="recipe.php?id=<?= (int)$recipe["id"] ?>">
                                View recipe →
                            </a>

                        </div>

                    </article>

                <?php endforeach; ?>

            </div>

        </section>

    <?php endif; ?>

</main>

</body>
</html>
