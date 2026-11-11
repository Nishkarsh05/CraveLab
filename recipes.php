
<?php
require_once __DIR__ . '/includes/session.php';
require_once 'includes/db.php';

$userId = (int)($_SESSION["user_id"] ?? 0);

$search = trim($_GET["search"] ?? "");
$categoryInput = trim($_GET["category"] ?? "");

$category = 0;

// Get available categories
$categories = $conn->query(
    "SELECT * FROM categories ORDER BY category_name"
);

// Support numeric category IDs and homepage category links
if ($categoryInput !== "" && ctype_digit($categoryInput)) {
    $category = (int)$categoryInput;
} else {
    $categoryAliases = [
        "comfort" => ["comfort"],
        "quick" => ["quick", "easy"],
        "sweet" => ["sweet", "dessert"],
        "experiments" => ["experiment", "chaos"]
    ];

    if (isset($categoryAliases[$categoryInput])) {
        $aliases = $categoryAliases[$categoryInput];

        while ($cat = $categories->fetch_assoc()) {
            $name = strtolower($cat["category_name"]);

            foreach ($aliases as $alias) {
                if (strpos($name, $alias) !== false) {
                    $category = (int)$cat["id"];
                    break 2;
                }
            }
        }
    }
}

// Reset category pointer
$categories->data_seek(0);

// Build recipe query
$sql = "SELECT recipes.*, users.name AS chef,
        categories.category_name
        FROM recipes
        JOIN users ON recipes.user_id = users.id
        JOIN categories ON recipes.category_id = categories.id
        WHERE (
            users.is_private = 0
            OR recipes.user_id = ?
            OR EXISTS (
                SELECT 1
                FROM follows
                WHERE follows.follower_id = ?
                AND follows.following_id = users.id
                AND follows.status = 'accepted'
            )
        )";

if ($search !== "") {
    $sql .= " AND (recipes.title LIKE ? OR recipes.description LIKE ?)";
}

if ($category > 0) {
    $sql .= " AND recipes.category_id = ?";
}

$sql .= " ORDER BY recipes.created_at DESC";

$stmt = $conn->prepare($sql);

if ($search !== "" && $category > 0) {
    $term = "%$search%";
    $stmt->bind_param("iissi", $userId, $userId, $term, $term, $category);
} elseif ($search !== "") {
    $term = "%$search%";
    $stmt->bind_param("iiss", $userId, $userId, $term, $term);
} elseif ($category > 0) {
    $stmt->bind_param("iii", $userId, $userId, $category);
} else {
    $stmt->bind_param("ii", $userId, $userId);
}

$stmt->execute();
$recipes = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Explore Recipes | CraveLab</title>

    <link rel="stylesheet" href="assets/css/style.css">

    <style>
        .explore {
            max-width: 1150px;
            margin: 55px auto;
            padding: 0 24px;
        }

        .explore-heading {
            margin-bottom: 30px;
        }

        .explore-heading h1 {
            font-family: Georgia, serif;
            font-size: 43px;
            margin: 12px 0;
        }

        .explore-heading p {
            color: #777;
        }

        .search-bar {
            display: flex;
            gap: 10px;
            margin: 25px 0 35px;
            flex-wrap: wrap;
        }

        .search-bar input,
        .search-bar select {
            padding: 13px;
            border: 1px solid #e5e3dc;
            border-radius: 6px;
            font: inherit;
            background: white;
        }

        .search-bar input {
            flex: 1;
            min-width: 180px;
        }

        .search-bar button {
            border: none;
            cursor: pointer;
        }

        .recipe-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
        }

        .recipe-card {
            background: white;
            border: 1px solid #eee;
            border-radius: 10px;
            overflow: hidden;
            transition: 0.25s;
        }

        .recipe-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 30px #00000010;
        }

        .recipe-card img {
            width: 100%;
            height: 210px;
            object-fit: cover;
            display: block;
        }

        .recipe-placeholder {
            height: 210px;
            display: grid;
            place-items: center;
            background: #f5eee4;
            font-size: 55px;
        }

        .recipe-info {
            padding: 20px;
        }

        .recipe-info h2 {
            font-family: Georgia, serif;
            font-size: 23px;
            margin: 10px 0;
        }

        .recipe-info p {
            color: #777;
            font-size: 13px;
            line-height: 1.6;
        }

        .recipe-meta {
            font-size: 12px;
            color: #777;
            margin-top: 15px;
        }

        .recipe-info a {
            color: #f36b3f;
            font-weight: bold;
            text-decoration: none;
            display: inline-block;
            margin-top: 15px;
        }

        .empty-state {
            background: #f5eee4;
            padding: 40px;
            text-align: center;
            border-radius: 10px;
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

        <a href="dashboard.php" class="text-link">My Kitchen</a>

    </nav>
</header>

<main class="explore">

    <section class="explore-heading">

        <div class="eyebrow">THE COMMUNITY COOKBOOK</div>

        <h1>Discover something delicious.</h1>

        <p>Recipes from real people. Hopefully fewer kitchen disasters.</p>

    </section>

    <form class="search-bar" method="GET">

        <input
            type="text"
            name="search"
            placeholder="Search recipes..."
            value="<?= htmlspecialchars($search) ?>">

        <select name="category">

            <option value="0">All categories</option>

            <?php while ($cat = $categories->fetch_assoc()): ?>

                <option
                    value="<?= (int)$cat['id'] ?>"
                    <?= $category === (int)$cat['id'] ? 'selected' : '' ?>>

                    <?= htmlspecialchars($cat['category_name']) ?>

                </option>

            <?php endwhile; ?>

        </select>

        <button class="btn btn-orange" type="submit">
            Search ↗
        </button>

    </form>

    <?php if ($recipes->num_rows > 0): ?>

        <div class="recipe-grid">

            <?php while ($recipe = $recipes->fetch_assoc()): ?>

                <?php
                $imagePath = trim($recipe["image"] ?? "");

                $imageExists = (
                    $imagePath !== "" &&
                    strpos($imagePath, "uploads/recipes/") === 0 &&
                    is_file(__DIR__ . "/" . $imagePath)
                );
                ?>

                <article class="recipe-card">

                    <?php if ($imageExists): ?>

                        <img
                            src="<?= htmlspecialchars($imagePath) ?>"
                            alt="<?= htmlspecialchars($recipe["title"]) ?>">

                    <?php else: ?>

                        <div class="recipe-placeholder">🍲</div>

                    <?php endif; ?>

                    <div class="recipe-info">

                        <div class="eyebrow">
                            <?= htmlspecialchars($recipe["category_name"]) ?>
                        </div>

                        <h2>
                            <?= htmlspecialchars($recipe["title"]) ?>
                        </h2>

                        <p>
                            <?= htmlspecialchars($recipe["description"]) ?>
                        </p>

                        <div class="recipe-meta">

                            ⏱ <?= (int)$recipe["cooking_time"] ?> min

                            &nbsp; · &nbsp;

                            <?= htmlspecialchars($recipe["difficulty"]) ?>

                            <br>

                            By <?= htmlspecialchars($recipe["chef"]) ?>

                        </div>

                        <a href="recipe.php?id=<?= (int)$recipe["id"] ?>">
                            View recipe →
                        </a>

                    </div>

                </article>

            <?php endwhile; ?>

        </div>

    <?php else: ?>

        <div class="empty-state">

            <h2>No recipes found! 🥲</h2>

            <p>
                Try another search, or be the first to add something delicious.
            </p>

        </div>

    <?php endif; ?>

</main>

</body>
</html>
