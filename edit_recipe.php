
<?php
require_once __DIR__ . '/includes/session.php';
require_once 'includes/db.php';
require_once 'includes/security.php';
$csrfToken = cravelab_csrf_token();

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

$id = (int)($_GET["id"] ?? $_POST["recipe_id"] ?? 0);
$userId = (int)$_SESSION["user_id"];

if ($id <= 0) {
    die("Invalid recipe.");
}

$csrfToken = cravelab_csrf_token();

// Fetch only the logged-in user's recipe
$stmt = $conn->prepare(
    "SELECT * FROM recipes WHERE id = ? AND user_id = ?"
);
$stmt->bind_param("ii", $id, $userId);
$stmt->execute();
$recipe = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$recipe) {
    http_response_code(403);
    die("Recipe not found or you don't have permission to edit it.");
}

$message = "";

function uploadRecipeImage($file) {
    if (!$file || $file["error"] === UPLOAD_ERR_NO_FILE) {
        return ["path" => "", "error" => ""];
    }

    if ($file["error"] !== UPLOAD_ERR_OK) {
        return ["path" => "", "error" => "Image upload failed. Please try again."];
    }

    if ($file["size"] > 5 * 1024 * 1024) {
        return ["path" => "", "error" => "Image must be smaller than 5 MB."];
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file["tmp_name"]);
    finfo_close($finfo);

    $allowed = [
        "image/jpeg" => "jpg",
        "image/png" => "png",
        "image/webp" => "webp"
    ];

    if (!isset($allowed[$mime])) {
        return ["path" => "", "error" => "Only JPG, PNG and WEBP images are allowed."];
    }

    $uploadDir = __DIR__ . "/uploads/recipes/";

    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
        return ["path" => "", "error" => "Could not create image folder."];
    }

    $filename = bin2hex(random_bytes(16)) . "." . $allowed[$mime];
    $destination = $uploadDir . $filename;

    if (!move_uploaded_file($file["tmp_name"], $destination)) {
        return ["path" => "", "error" => "Could not save uploaded image."];
    }

    return [
        "path" => "uploads/recipes/" . $filename,
        "error" => ""
    ];
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    cravelab_verify_csrf();

    $title = trim($_POST["title"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $ingredients = trim($_POST["ingredients"] ?? "");
    $instructions = trim($_POST["instructions"] ?? "");
    $cookingTime = (int)($_POST["cooking_time"] ?? 0);
    $difficulty = $_POST["difficulty"] ?? "";
    $categoryId = (int)($_POST["category_id"] ?? 0);

    if (
        $title === "" ||
        $description === "" ||
        $ingredients === "" ||
        $instructions === "" ||
        $cookingTime < 1 ||
        $categoryId < 1
    ) {
        $message = "Please complete all required fields.";

    } elseif (strlen($title) > 150 || strlen($description) > 2000 ||
              strlen($ingredients) > 10000 || strlen($instructions) > 20000 ||
              $cookingTime > 1440) {
        $message = "Please keep the recipe details within the allowed limits.";
    } elseif (!in_array($difficulty, ["Easy", "Medium", "Hard"], true)) {
        $message = "Invalid difficulty selected.";

    } else {

        // Confirm category exists
        $categoryCheck = $conn->prepare(
            "SELECT id FROM categories WHERE id = ?"
        );
        $categoryCheck->bind_param("i", $categoryId);
        $categoryCheck->execute();
        $categoryExists = $categoryCheck->get_result()->num_rows > 0;
        $categoryCheck->close();

        if (!$categoryExists) {
            $message = "Please select a valid category.";
        } else {

            $upload = uploadRecipeImage($_FILES["image"] ?? null);

            if ($upload["error"] !== "") {
                $message = $upload["error"];
            } else {

                $oldImage = $recipe["image"] ?? "";
                $newImage = $oldImage;

                if (isset($_POST["remove_image"]) && $_POST["remove_image"] === "1") {
                    $newImage = "";
                }

                if ($upload["path"] !== "") {
                    $newImage = $upload["path"];
                }

                $update = $conn->prepare(
                    "UPDATE recipes
                     SET title = ?,
                         description = ?,
                         ingredients = ?,
                         instructions = ?,
                         cooking_time = ?,
                         difficulty = ?,
                         category_id = ?,
                         image = ?
                     WHERE id = ? AND user_id = ?"
                );

                $update->bind_param(
                    "ssssisisii",
                    $title,
                    $description,
                    $ingredients,
                    $instructions,
                    $cookingTime,
                    $difficulty,
                    $categoryId,
                    $newImage,
                    $id,
                    $userId
                );

                if ($update->execute()) {

                    if (
                        $oldImage !== "" &&
                        $oldImage !== $newImage &&
                        strpos($oldImage, "uploads/recipes/") === 0 &&
                        is_file(__DIR__ . "/" . $oldImage)
                    ) {
                        unlink(__DIR__ . "/" . $oldImage);
                    }

                    $update->close();

                    header("Location: recipe.php?id=" . $id);
                    exit();

                } else {

                    if (
                        $upload["path"] !== "" &&
                        is_file(__DIR__ . "/" . $upload["path"])
                    ) {
                        unlink(__DIR__ . "/" . $upload["path"]);
                    }

                    $message = "Could not update the recipe.";
                    $update->close();
                }
            }
        }
    }

    $recipe["title"] = $title;
    $recipe["description"] = $description;
    $recipe["ingredients"] = $ingredients;
    $recipe["instructions"] = $instructions;
    $recipe["cooking_time"] = $cookingTime;
    $recipe["difficulty"] = $difficulty;
    $recipe["category_id"] = $categoryId;

    if (
        isset($_POST["remove_image"]) &&
        $_POST["remove_image"] === "1"
    ) {
        $recipe["image"] = "";
    }
}

$categories = $conn->query(
    "SELECT * FROM categories ORDER BY category_name"
);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Recipe | CraveLab</title>
    <link rel="stylesheet" href="assets/css/style.css">

    <style>
        .edit-wrap {
            max-width: 800px;
            margin: 50px auto;
            padding: 0 22px;
        }

        .edit-card {
            background: white;
            padding: 35px;
            border: 1px solid #eee;
            border-radius: 12px;
        }

        .edit-card h1 {
            font-family: Georgia, serif;
            font-size: 36px;
        }

        .field {
            margin-top: 18px;
        }

        .field label {
            display: block;
            font-weight: bold;
            font-size: 13px;
            margin-bottom: 7px;
        }

        .field input,
        .field textarea,
        .field select {
            width: 100%;
            padding: 13px;
            border: 1px solid #e5e3dc;
            border-radius: 6px;
            font: inherit;
            box-sizing: border-box;
        }

        .field textarea {
            min-height: 110px;
        }

        .edit-card button {
            margin-top: 22px;
            border: 0;
            cursor: pointer;
        }

        .message {
            background: #f1f0e9;
            padding: 12px;
            margin-top: 15px;
            border-radius: 6px;
        }

        .current-image {
            max-width: 220px;
            max-height: 160px;
            object-fit: cover;
            border-radius: 8px;
            margin: 10px 0;
            display: block;
        }

        .upload-help {
            font-size: 12px;
            color: #777;
            margin-top: 7px;
        }

        .remove-image {
            display: flex !important;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            margin-top: 10px;
        }

        .remove-image input {
            width: auto;
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

        <a href="dashboard.php" class="text-link">← Dashboard</a>
    </nav>
</header>

<main class="edit-wrap">

    <div class="edit-card">

        <div class="eyebrow">A LITTLE KITCHEN MAKEOVER</div>

        <h1>Edit your recipe.</h1>

        <?php if ($message): ?>
            <div class="message">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">

            <input type="hidden" name="recipe_id" value="<?= $id ?>">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

            <div class="field">
                <label>Recipe name</label>
                <input type="text" name="title" required
                    value="<?= htmlspecialchars($recipe["title"]) ?>">
            </div>

            <div class="field">
                <label>Description</label>
                <textarea name="description" required><?= htmlspecialchars($recipe["description"]) ?></textarea>
            </div>

            <div class="field">
                <label>Category</label>
                <select name="category_id" required>
                    <?php while ($cat = $categories->fetch_assoc()): ?>
                        <option value="<?= (int)$cat["id"] ?>"
                            <?= $recipe["category_id"] == $cat["id"] ? "selected" : "" ?>>
                            <?= htmlspecialchars($cat["category_name"]) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="field">
                <label>Cooking time (minutes)</label>
                <input type="number" name="cooking_time" min="1" required
                    value="<?= (int)$recipe["cooking_time"] ?>">
            </div>

            <div class="field">
                <label>Difficulty</label>
                <select name="difficulty" required>
                    <?php foreach (["Easy", "Medium", "Hard"] as $level): ?>
                        <option value="<?= $level ?>"
                            <?= $recipe["difficulty"] === $level ? "selected" : "" ?>>
                            <?= $level ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label>Recipe image</label>

                <?php
                $currentImage = $recipe["image"] ?? "";
                $isLocalImage = (
                    strpos($currentImage, "uploads/recipes/") === 0 &&
                    is_file(__DIR__ . "/" . $currentImage)
                );
                ?>

                <?php if ($isLocalImage): ?>
                    <p>Current image:</p>

                    <img src="<?= htmlspecialchars($currentImage) ?>"
                         alt="Current recipe image"
                         class="current-image">

                    <label class="remove-image">
                        <input type="checkbox" name="remove_image" value="1">
                        Remove current image
                    </label>
                <?php else: ?>
                    <p>No image currently uploaded.</p>
                <?php endif; ?>

                <input type="file" name="image"
                    accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">

                <div class="upload-help">
                    Leave empty to keep the current image.
                    Choose a new image to replace it.
                    JPG, PNG or WEBP · Max 5 MB.
                </div>
            </div>

            <div class="field">
                <label>Ingredients</label>
                <textarea name="ingredients" required><?= htmlspecialchars($recipe["ingredients"]) ?></textarea>
            </div>

            <div class="field">
                <label>Instructions</label>
                <textarea name="instructions" required><?= htmlspecialchars($recipe["instructions"]) ?></textarea>
            </div>

            <button class="btn btn-orange" type="submit">
                Save Changes ↗
            </button>

        </form>

    </div>

</main>

</body>
</html>
