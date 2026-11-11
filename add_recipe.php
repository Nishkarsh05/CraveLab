
<?php
require_once __DIR__ . '/includes/session.php';

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

require_once 'includes/db.php';
require_once 'includes/security.php';
$csrfToken = cravelab_csrf_token();

// Create default categories if none exist
$categoryCheck = $conn->query("SELECT COUNT(*) AS total FROM categories");
$categoryTotal = $categoryCheck->fetch_assoc()["total"];

if ($categoryTotal == 0) {
    $defaults = ["Breakfast", "Lunch", "Dinner", "Desserts", "Snacks", "Drinks"];

    $insertCategory = $conn->prepare(
        "INSERT IGNORE INTO categories (category_name) VALUES (?)"
    );

    foreach ($defaults as $category) {
        $insertCategory->bind_param("s", $category);
        $insertCategory->execute();
    }

    $insertCategory->close();
}

$categories = $conn->query(
    "SELECT id, category_name FROM categories ORDER BY category_name"
);

$message = "";
$success = false;

// Handle recipe image upload
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

    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            return ["path" => "", "error" => "Could not create image folder."];
        }
    }

    $filename = bin2hex(random_bytes(16)) . "." . $allowed[$mime];

    $destination = $uploadDir . $filename;

    if (!move_uploaded_file($file["tmp_name"], $destination)) {
        return ["path" => "", "error" => "Could not save the uploaded image."];
    }

    return [
        "path" => "uploads/recipes/" . $filename,
        "error" => ""
    ];
}

// Handle form submission
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

        $message = "Please select a valid difficulty.";

    } else {

        $categoryCheck = $conn->prepare("SELECT id FROM categories WHERE id = ? LIMIT 1");
        $categoryCheck->bind_param("i", $categoryId);
        $categoryCheck->execute();
        $categoryExists = $categoryCheck->get_result()->num_rows === 1;
        $categoryCheck->close();

        if (!$categoryExists) {
            $message = "Please select a valid category.";
        }

        $upload = ($message === "") ? uploadRecipeImage($_FILES["image"] ?? null) : ["path" => "", "error" => ""];

        if ($message !== "") {
            // Validation message already set above.
        } elseif ($upload["error"] !== "") {

            $message = $upload["error"];

        } else {

            $image = $upload["path"];
            $userId = (int)$_SESSION["user_id"];

            $stmt = $conn->prepare(
                "INSERT INTO recipes
                (user_id, category_id, title, description, ingredients,
                instructions, cooking_time, difficulty, image)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );

            $stmt->bind_param(
                "iissssiss",
                $userId,
                $categoryId,
                $title,
                $description,
                $ingredients,
                $instructions,
                $cookingTime,
                $difficulty,
                $image
            );

            if ($stmt->execute()) {

                $success = true;
                $message = "Recipe published! The kitchen just got better. 🍳";

            } else {

                if ($image !== "" && file_exists(__DIR__ . "/" . $image)) {
                    unlink(__DIR__ . "/" . $image);
                }

                $message = "Could not save your recipe. Please try again.";
            }

            $stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Add Recipe | CraveLab</title>

    <link rel="stylesheet" href="assets/css/style.css">

    <style>

        .recipe-form-wrap {
            max-width: 850px;
            margin: 55px auto;
            padding: 0 22px;
        }

        .recipe-form-card {
            background: white;
            padding: 38px;
            border: 1px solid #eee;
            border-radius: 12px;
        }

        .recipe-form-card h1 {
            font-family: Georgia, serif;
            font-size: 38px;
            margin: 12px 0;
        }

        .recipe-form-card p {
            color: #777;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
        }

        .field {
            margin-top: 20px;
        }

        .field.full {
            grid-column: 1 / -1;
        }

        .field label {
            display: block;
            font-weight: 700;
            font-size: 13px;
            margin-bottom: 8px;
        }

        .field input,
        .field textarea,
        .field select {
            width: 100%;
            padding: 13px;
            border: 1px solid #e5e3dc;
            border-radius: 6px;
            font: inherit;
            outline: none;
            box-sizing: border-box;
        }

        .field textarea {
            min-height: 115px;
            resize: vertical;
        }

        .field input:focus,
        .field textarea:focus,
        .field select:focus {
            border-color: #f36b3f;
        }

        .recipe-form-card button {
            margin-top: 25px;
            border: 0;
            cursor: pointer;
        }

        .form-message {
            background: #f1f0e9;
            padding: 14px;
            border-radius: 6px;
            margin: 18px 0;
            font-size: 13px;
        }

        .upload-help {
            font-size: 12px;
            color: #777;
            margin-top: 7px;
        }

        #imagePreview {
            display: none;
            width: 100%;
            max-height: 280px;
            object-fit: cover;
            border-radius: 10px;
            margin-top: 15px;
        }

        @media(max-width: 600px) {

            .form-grid {
                grid-template-columns: 1fr;
            }

            .field.full {
                grid-column: auto;
            }

            .recipe-form-card {
                padding: 23px;
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

<main class="recipe-form-wrap">

    <div class="recipe-form-card">

        <div class="eyebrow">
            LET'S MAKE SOMETHING DELICIOUS
        </div>

        <h1>Share your recipe.</h1>

        <p>
            Every great dish deserves an audience. No pressure, chef.
        </p>

        <?php if ($message): ?>

            <div class="form-message">
                <?= htmlspecialchars($message) ?>
            </div>

        <?php endif; ?>

        <?php if (!$success): ?>

        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

            <div class="form-grid">

                <div class="field full">

                    <label>Recipe name *</label>

                    <input
                        type="text"
                        name="title"
                        required
                        value="<?= htmlspecialchars($_POST['title'] ?? '') ?>"
                        placeholder="e.g. Legendary Cheese Sandwich">

                </div>

                <div class="field full">

                    <label>Short description *</label>

                    <textarea
                        name="description"
                        required
                        placeholder="Tell everyone what makes this dish special..."><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>

                </div>

                <div class="field">

                    <label>Category *</label>

                    <select name="category_id" required>

                        <option value="">Choose category</option>

                        <?php while ($cat = $categories->fetch_assoc()): ?>

                            <option
                                value="<?= (int)$cat['id'] ?>"
                                <?= (($_POST['category_id'] ?? '') == $cat['id']) ? 'selected' : '' ?>>

                                <?= htmlspecialchars($cat['category_name']) ?>

                            </option>

                        <?php endwhile; ?>

                    </select>

                </div>

                <div class="field">

                    <label>Cooking time (minutes) *</label>

                    <input
                        type="number"
                        name="cooking_time"
                        min="1"
                        required
                        value="<?= htmlspecialchars($_POST['cooking_time'] ?? '') ?>"
                        placeholder="e.g. 30">

                </div>

                <div class="field">

                    <label>Difficulty *</label>

                    <select name="difficulty" required>

                        <option value="">Choose difficulty</option>

                        <?php foreach (["Easy", "Medium", "Hard"] as $level): ?>

                            <option
                                value="<?= $level ?>"
                                <?= (($_POST['difficulty'] ?? '') === $level) ? 'selected' : '' ?>>

                                <?= $level ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

                <div class="field">

                    <label>Recipe image</label>

                    <input
                        type="file"
                        name="image"
                        id="recipeImage"
                        accept="image/jpeg,image/png,image/webp">

                    <div class="upload-help">
                        Choose from your device · JPG, PNG or WEBP · Max 5 MB
                    </div>

                    <img
                        id="imagePreview"
                        alt="Selected recipe preview">

                </div>

                <div class="field full">

                    <label>Ingredients *</label>

                    <textarea
                        name="ingredients"
                        required
                        placeholder="Write each ingredient on a new line..."><?= htmlspecialchars($_POST['ingredients'] ?? '') ?></textarea>

                </div>

                <div class="field full">

                    <label>Cooking instructions *</label>

                    <textarea
                        name="instructions"
                        required
                        placeholder="Explain the steps, one by one..."><?= htmlspecialchars($_POST['instructions'] ?? '') ?></textarea>

                </div>

            </div>

            <button type="submit" class="btn btn-orange">
                Publish my recipe ↗
            </button>

        </form>

        <?php else: ?>

            <a href="dashboard.php" class="btn btn-orange">
                Back to dashboard
            </a>

        <?php endif; ?>

    </div>

</main>

<script>

const imageInput = document.getElementById("recipeImage");
const imagePreview = document.getElementById("imagePreview");

imageInput.addEventListener("change", function () {

    const file = this.files[0];

    if (!file) {
        imagePreview.style.display = "none";
        imagePreview.removeAttribute("src");
        return;
    }

    const allowedTypes = [
        "image/jpeg",
        "image/png",
        "image/webp"
    ];

    if (!allowedTypes.includes(file.type)) {

        alert("Please select a JPG, PNG or WEBP image.");

        this.value = "";
        imagePreview.style.display = "none";

        return;
    }

    if (file.size > 5 * 1024 * 1024) {

        alert("Image must be smaller than 5 MB.");

        this.value = "";
        imagePreview.style.display = "none";

        return;
    }

    const previewURL = URL.createObjectURL(file);

    imagePreview.src = previewURL;
    imagePreview.style.display = "block";

});

</script>

</body>
</html>
