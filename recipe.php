
<?php
require_once __DIR__ . '/includes/session.php';
require_once 'includes/db.php';
require_once 'includes/security.php';
$csrfToken = cravelab_csrf_token();

$id = (int)($_GET["id"] ?? 0);
$userId = (int)($_SESSION["user_id"] ?? 0);

if ($id <= 0) {
    http_response_code(404);
    die("Recipe not found! <a href='recipes.php'>Go back</a>");
}

// FETCH RECIPE AND CREATOR PRIVACY
$stmt = $conn->prepare(
    "SELECT recipes.*, users.name AS chef, users.is_private,
            categories.category_name
     FROM recipes
     JOIN users ON recipes.user_id = users.id
     JOIN categories ON recipes.category_id = categories.id
     WHERE recipes.id = ?"
);

$stmt->bind_param("i", $id);
$stmt->execute();
$recipe = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$recipe) {
    http_response_code(404);
    die("Recipe not found! <a href='recipes.php'>Go back</a>");
}

// CHECK PRIVATE PROFILE ACCESS
$ownerId = (int)$recipe["user_id"];
$isOwner = ($userId > 0 && $userId === $ownerId);
$canView = ((int)$recipe["is_private"] === 0 || $isOwner);

if (!$canView && $userId > 0) {
    $accessStmt = $conn->prepare("
        SELECT id FROM follows
        WHERE follower_id = ?
          AND following_id = ?
          AND status = 'accepted'
        LIMIT 1
    ");
    $accessStmt->bind_param("ii", $userId, $ownerId);
    $accessStmt->execute();
    $canView = ($accessStmt->get_result()->num_rows > 0);
    $accessStmt->close();
}

if (!$canView) {
    http_response_code($userId === 0 ? 401 : 403);

    if ($userId === 0) {
        die("This creator's account is private. <a href='login.php'>Login</a> to continue.");
    }

    die("This creator's account is private. <a href='discover.php'>Discover creators</a>");
}

// SAVE / REMOVE RECIPE
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["save_action"])) {

    cravelab_verify_csrf();

    if ($userId === 0) {
        header("Location: login.php");
        exit();
    }

    $action = $_POST["save_action"];

    if ($action === "save") {
        $saveStmt = $conn->prepare(
            "INSERT IGNORE INTO saved_recipes (user_id, recipe_id)
             VALUES (?, ?)"
        );
        $saveStmt->bind_param("ii", $userId, $id);
        $saveStmt->execute();
        $saveStmt->close();

        $_SESSION["save_message"] = "Recipe saved! Find it in your profile. 🔖";

    } elseif ($action === "remove") {
        $removeStmt = $conn->prepare(
            "DELETE FROM saved_recipes
             WHERE user_id = ? AND recipe_id = ?"
        );
        $removeStmt->bind_param("ii", $userId, $id);
        $removeStmt->execute();
        $removeStmt->close();

        $_SESSION["save_message"] = "Recipe removed from your saved collection.";
    }

    header("Location: recipe.php?id=" . $id);
    exit();
}

// CHECK SAVED STATUS
$isSaved = false;

if ($userId > 0) {
    $savedStmt = $conn->prepare(
        "SELECT id FROM saved_recipes
         WHERE user_id = ? AND recipe_id = ?"
    );

    $savedStmt->bind_param("ii", $userId, $id);
    $savedStmt->execute();
    $isSaved = $savedStmt->get_result()->num_rows > 0;
    $savedStmt->close();
}

// FETCH COMMENTS
$comment_query = $conn->prepare(
    "SELECT comments.comment, comments.created_at, users.name
     FROM comments
     LEFT JOIN users ON comments.user_id = users.id
     WHERE comments.recipe_id = ?
     ORDER BY comments.created_at DESC"
);

$comment_query->bind_param("i", $id);
$comment_query->execute();
$comments = $comment_query->get_result();

// CHECK RECIPE IMAGE
$imagePath = trim($recipe["image"] ?? "");

$imageExists = (
    $imagePath !== "" &&
    strpos($imagePath, "uploads/recipes/") === 0 &&
    is_file(__DIR__ . "/" . $imagePath)
);

// FLASH MESSAGE
$saveMessage = $_SESSION["save_message"] ?? "";
unset($_SESSION["save_message"]);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= htmlspecialchars($recipe["title"]) ?> | CraveLab</title>

    <link rel="stylesheet" href="assets/css/style.css">

    <style>
        .recipe-detail {
            max-width: 950px;
            margin: 55px auto;
            padding: 0 22px;
        }

        .recipe-detail h1 {
            font-family: Georgia, serif;
            font-size: 44px;
            margin: 15px 0;
        }

        .recipe-detail .description {
            color: #777;
            line-height: 1.7;
        }

        .detail-image {
            width: 100%;
            max-height: 430px;
            object-fit: cover;
            border-radius: 12px;
            margin: 25px 0;
        }

        .detail-placeholder {
            background: #f5eee4;
            padding: 90px;
            text-align: center;
            font-size: 65px;
            border-radius: 12px;
            margin: 25px 0;
        }

        .detail-meta {
            color: #777;
            margin: 20px 0;
            font-size: 14px;
        }

        .detail-meta a {
            color: #e56b45;
            text-decoration: none;
            font-weight: bold;
        }

        .detail-sections {
            display: grid;
            grid-template-columns: 1fr 1.5fr;
            gap: 35px;
            margin-top: 35px;
        }

        .detail-box {
            background: white;
            border: 1px solid #eee;
            padding: 25px;
            border-radius: 10px;
        }

        .detail-box h2 {
            font-family: Georgia, serif;
            margin-bottom: 18px;
        }

        .detail-box p {
            white-space: pre-line;
            line-height: 1.9;
            color: #555;
        }

        .save-form {
            margin: 15px 0 22px;
        }

        .save-button {
            background: #f47735;
            color: white;
            border: none;
            padding: 12px 22px;
            border-radius: 10px;
            cursor: pointer;
            font-weight: bold;
            font-size: 14px;
            transition: 0.2s;
        }

        .save-button:hover {
            transform: translateY(-2px);
            opacity: 0.9;
        }

        .save-button.saved {
            background: #333;
        }

        .save-message {
            background: #fff4e9;
            color: #a84c20;
            padding: 12px 16px;
            border-radius: 10px;
            margin: 15px 0;
        }

        .comments-section {
            margin-top: 35px;
        }

        .comments-section textarea {
            width: 100%;
            min-height: 100px;
            padding: 14px;
            margin: 12px 0;
            border: 1px solid #ddd;
            border-radius: 10px;
            font-family: inherit;
            resize: vertical;
            box-sizing: border-box;
        }

        .comments-section button {
            background: #f47735;
            color: white;
            border: none;
            padding: 12px 22px;
            border-radius: 10px;
            cursor: pointer;
            font-weight: bold;
        }

        .comment-card {
            background: #fff8f2;
            padding: 16px;
            border-radius: 12px;
            margin-top: 14px;
        }

        .comment-card p {
            margin: 10px 0;
        }

        .comment-card small {
            color: #777;
        }

        @media(max-width: 650px) {
            .detail-sections {
                grid-template-columns: 1fr;
            }

            .recipe-detail h1 {
                font-size: 32px;
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

        <a href="recipes.php" class="text-link">← All recipes</a>

    </nav>
</header>

<main class="recipe-detail">

    <div class="eyebrow">
        <?= htmlspecialchars($recipe["category_name"]) ?>
    </div>

    <h1><?= htmlspecialchars($recipe["title"]) ?></h1>

    <p class="description">
        <?= htmlspecialchars($recipe["description"]) ?>
    </p>

    <div class="detail-meta">
        👨‍🍳 By
        <a href="creator.php?id=<?= $ownerId ?>">
            <?= htmlspecialchars($recipe["chef"]) ?>
        </a>
        &nbsp; · &nbsp;
        ⏱ <?= (int)$recipe["cooking_time"] ?> minutes
        &nbsp; · &nbsp;
        <?= htmlspecialchars($recipe["difficulty"]) ?>
    </div>

    <?php if ($saveMessage): ?>
        <div class="save-message">
            <?= htmlspecialchars($saveMessage) ?>
        </div>
    <?php endif; ?>

    <?php if ($userId > 0): ?>

        <form method="POST" class="save-form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

            <?php if ($isSaved): ?>
                <button type="submit"
                        name="save_action"
                        value="remove"
                        class="save-button saved">
                    ✓ Saved · Remove Bookmark
                </button>
            <?php else: ?>
                <button type="submit"
                        name="save_action"
                        value="save"
                        class="save-button">
                    🔖 Save Recipe
                </button>
            <?php endif; ?>

        </form>

    <?php else: ?>

        <p>
            <a href="login.php">Login</a>
            to save this recipe to your collection.
        </p>

    <?php endif; ?>

    <?php if ($isOwner): ?>

        <a href="edit_recipe.php?id=<?= (int)$recipe["id"] ?>"
           class="btn btn-orange">
            ✏️ Edit Recipe
        </a>

        <form action="delete_recipe.php"
              method="POST"
              onsubmit="return confirm('Are you sure? This recipe will be gone forever!');">

            <input type="hidden"
                   name="id"
                   value="<?= (int)$recipe["id"] ?>">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

            <button type="submit"
                    class="btn btn-orange"
                    style="background:#333;">
                🗑 Delete Recipe
            </button>

        </form>

    <?php endif; ?>

    <?php if ($imageExists): ?>

        <img class="detail-image"
             src="<?= htmlspecialchars($imagePath) ?>"
             alt="<?= htmlspecialchars($recipe["title"]) ?>">

    <?php else: ?>

        <div class="detail-placeholder">🍲</div>

    <?php endif; ?>

    <div class="detail-sections">

        <section class="detail-box">
            <h2>🧂 Ingredients</h2>
            <p><?= htmlspecialchars($recipe["ingredients"]) ?></p>
        </section>

        <section class="detail-box">
            <h2>👨‍🍳 Cooking Instructions</h2>
            <p><?= htmlspecialchars($recipe["instructions"]) ?></p>
        </section>

    </div>

    <section class="detail-box comments-section">

        <h2>💬 Kitchen Confessions</h2>

        <?php if (isset($_SESSION["user_id"])): ?>

            <form action="add_comment.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

                <input type="hidden"
                       name="recipe_id"
                       value="<?= (int)$recipe["id"] ?>">

                <textarea name="comment"
                          placeholder="How did this recipe turn out?"
                          maxlength="1000"
                          required></textarea>

                <button type="submit">Post Comment 🍴</button>

            </form>

        <?php else: ?>

            <p>
                <a href="login.php">Login</a>
                to join the conversation!
            </p>

        <?php endif; ?>

        <hr>

        <h3>What the foodies are saying</h3>

        <?php if ($comments->num_rows > 0): ?>

            <?php while ($row = $comments->fetch_assoc()): ?>

                <div class="comment-card">

                    <strong>
                        👨‍🍳 <?= htmlspecialchars($row["name"] ?? "Foodie") ?>
                    </strong>

                    <p>
                        <?= nl2br(htmlspecialchars($row["comment"])) ?>
                    </p>

                    <small>
                        <?= htmlspecialchars($row["created_at"]) ?>
                    </small>

                </div>

            <?php endwhile; ?>

        <?php else: ?>

            <p>
                No confessions yet. Be the first foodie to comment! 🍕
            </p>

        <?php endif; ?>

    </section>

</main>

</body>
</html>

<?php
$comment_query->close();
?>
