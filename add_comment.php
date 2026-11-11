
<?php
require_once __DIR__ . '/includes/session.php';
require_once "includes/db.php";
require_once "includes/security.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: recipes.php");
    exit();
}

cravelab_verify_csrf();

$recipe_id = (int)($_POST["recipe_id"] ?? 0);
$comment = trim($_POST["comment"] ?? "");
$user_id = (int)$_SESSION["user_id"];

if ($recipe_id <= 0 || $comment === "" || strlen($comment) > 1000) {
    die("Invalid comment. Please go back and try again.");
}

// Check recipe visibility and ownership
$check = $conn->prepare(
    "SELECT recipes.id, recipes.user_id, users.is_private
     FROM recipes
     JOIN users ON recipes.user_id = users.id
     WHERE recipes.id = ?
     LIMIT 1"
);

$check->bind_param("i", $recipe_id);
$check->execute();

$recipe = $check->get_result()->fetch_assoc();
$check->close();

if (!$recipe) {
    die("Recipe not found.");
}

// Check whether viewer can access this recipe
if (
    (int)$recipe["is_private"] === 1 &&
    (int)$recipe["user_id"] !== $user_id
) {
    $followCheck = $conn->prepare(
        "SELECT id FROM follows
         WHERE follower_id = ?
         AND following_id = ?
         AND status = 'accepted'
         LIMIT 1"
    );

    $creatorId = (int)$recipe["user_id"];
    $followCheck->bind_param("ii", $user_id, $creatorId);
    $followCheck->execute();

    $allowed = $followCheck->get_result()->num_rows > 0;
    $followCheck->close();

    if (!$allowed) {
        die("You cannot comment on this private recipe.");
    }
}

// Insert comment
$stmt = $conn->prepare(
    "INSERT INTO comments (user_id, recipe_id, comment)
     VALUES (?, ?, ?)"
);

if (!$stmt) {
    die("Database error. Please try again.");
}

$stmt->bind_param("iis", $user_id, $recipe_id, $comment);

if (!$stmt->execute()) {
    die("Comment could not be saved. Please try again.");
}

$stmt->close();

header("Location: recipe.php?id=" . $recipe_id . "#comments");
exit();
?>
