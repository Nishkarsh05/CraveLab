
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
$message = "";
$error = "";

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$stmt = $conn->prepare(
    "SELECT name, email, profile_image, bio, is_private
     FROM users WHERE id = ?"
);
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    header("Location: logout.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    cravelab_verify_csrf();

    $name = trim($_POST["name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $bio = trim($_POST["bio"] ?? "");
    $isPrivate = isset($_POST["is_private"]) ? 1 : 0;
    $newImage = $user["profile_image"] ?? "";
    $uploadedNewImage = "";

    if ($name === "" || $email === "") {
        $error = "Name and email are required.";
    } elseif (strlen($name) > 100 || strlen($email) > 150) {
        $error = "Name or email is too long.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Enter a valid email address.";
    } elseif (strlen($bio) > 250) {
        $error = "Bio cannot exceed 250 characters.";
    } else {

        $check = $conn->prepare(
            "SELECT id FROM users WHERE email = ? AND id != ?"
        );
        $check->bind_param("si", $email, $userId);
        $check->execute();
        $emailExists = $check->get_result()->num_rows > 0;
        $check->close();

        if ($emailExists) {
            $error = "This email is already registered.";
        }

        // Handle profile picture
        if (
            $error === "" &&
            isset($_FILES["profile_image"]) &&
            $_FILES["profile_image"]["error"] !== UPLOAD_ERR_NO_FILE
        ) {
            if ($_FILES["profile_image"]["error"] !== UPLOAD_ERR_OK) {
                $error = "Image upload failed.";
            } elseif ($_FILES["profile_image"]["size"] > 5 * 1024 * 1024) {
                $error = "Image must be smaller than 5 MB.";
            } else {
                $allowed = [
                    "image/jpeg" => "jpg",
                    "image/png" => "png",
                    "image/webp" => "webp"
                ];

                $mime = mime_content_type($_FILES["profile_image"]["tmp_name"]);

                if (!isset($allowed[$mime])) {
                    $error = "Only JPG, PNG and WEBP images are allowed.";
                } else {
                    $uploadDir = __DIR__ . "/uploads/profiles/";

                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }

                    $filename = "profile_" . $userId . "_" .
                        bin2hex(random_bytes(8)) . "." . $allowed[$mime];

                    if (move_uploaded_file(
                        $_FILES["profile_image"]["tmp_name"],
                        $uploadDir . $filename
                    )) {
                        $newImage = "uploads/profiles/" . $filename;
                        $uploadedNewImage = $newImage;
                    } else {
                        $error = "Could not save the uploaded image.";
                    }
                }
            }
        }

        if ($error === "") {

            $update = $conn->prepare(
                "UPDATE users
                 SET name = ?, email = ?, profile_image = ?,
                     bio = ?, is_private = ?
                 WHERE id = ?"
            );

            $update->bind_param(
                "ssssii",
                $name,
                $email,
                $newImage,
                $bio,
                $isPrivate,
                $userId
            );

            if ($update->execute()) {

                $oldImage = $user["profile_image"] ?? "";

                if (
                    $oldImage !== "" &&
                    $oldImage !== $newImage &&
                    strpos($oldImage, "uploads/profiles/") === 0 &&
                    is_file(__DIR__ . "/" . $oldImage)
                ) {
                    unlink(__DIR__ . "/" . $oldImage);
                }

                $user["name"] = $name;
                $user["email"] = $email;
                $user["profile_image"] = $newImage;
                $user["bio"] = $bio;
                $user["is_private"] = $isPrivate;

                $message = "Profile updated successfully!";
            } else {
                if ($uploadedNewImage !== "" && is_file(__DIR__ . "/" . $uploadedNewImage)) {
                    unlink(__DIR__ . "/" . $uploadedNewImage);
                }
                $error = "Could not update your profile.";
            }

            $update->close();
        }
    }
}

$image = trim($user["profile_image"] ?? "");

$imageExists = (
    $image !== "" &&
    strpos($image, "uploads/profiles/") === 0 &&
    is_file(__DIR__ . "/" . $image)
);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Edit Profile | CraveLab</title>
<link rel="stylesheet" href="assets/css/style.css">

<style>
* {
    box-sizing: border-box;
}

body {
    background: #faf7f1;
    color: #292722;
}

.edit-container {
    max-width: 600px;
    margin: 45px auto;
    padding: 32px;
    background: white;
    border: 1px solid #eee7dc;
    border-radius: 14px;
}

.edit-container h1 {
    font-family: Georgia, serif;
    font-size: 30px;
    margin-bottom: 8px;
}

.subtitle {
    color: #82796d;
    font-size: 13px;
    margin-bottom: 28px;
}

.avatar {
    width: 115px;
    height: 115px;
    border-radius: 50%;
    overflow: hidden;
    background: #f0e4d4;
    display: grid;
    place-items: center;
    font-size: 48px;
    margin: 0 auto 15px;
    border: 3px solid #e36b42;
}

.avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.photo-area {
    text-align: center;
    margin-bottom: 28px;
}

.photo-area label {
    color: #e36b42;
    font-weight: 700;
    font-size: 14px;
    cursor: pointer;
}

input[type="file"] {
    display: none;
}

.field {
    margin-bottom: 19px;
}

.field label {
    display: block;
    font-size: 13px;
    font-weight: 700;
    margin-bottom: 8px;
}

.field input,
.field textarea {
    width: 100%;
    padding: 13px;
    border: 1px solid #ded6ca;
    border-radius: 8px;
    font: inherit;
    font-size: 14px;
}

.field textarea {
    min-height: 90px;
    resize: vertical;
}

.privacy-box {
    background: #faf7f1;
    border: 1px solid #eee7dc;
    border-radius: 9px;
    padding: 16px;
    margin-bottom: 22px;
}

.privacy-box label {
    display: flex;
    gap: 10px;
    align-items: center;
    font-weight: 700;
    font-size: 14px;
    cursor: pointer;
}

.privacy-box p {
    color: #777;
    font-size: 12px;
    margin: 9px 0 0 27px;
    line-height: 1.5;
}

.save-button {
    width: 100%;
    border: 0;
    padding: 14px;
    border-radius: 8px;
    background: #e36b42;
    color: white;
    font-weight: 800;
    cursor: pointer;
    font-size: 14px;
}

.message {
    padding: 12px;
    background: #e9f5e9;
    color: #27632b;
    border-radius: 7px;
    margin-bottom: 18px;
    font-size: 13px;
}

.error {
    padding: 12px;
    background: #fff0ed;
    color: #a33b27;
    border-radius: 7px;
    margin-bottom: 18px;
    font-size: 13px;
}

.back-link {
    display: inline-block;
    margin-top: 20px;
    color: #e36b42;
    font-weight: 700;
    text-decoration: none;
    font-size: 13px;
}

@media(max-width: 600px) {
    .edit-container {
        margin: 20px 12px;
        padding: 22px;
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

        <a href="profile.php" class="text-link">← My Profile</a>
    </nav>
</header>

<main class="edit-container">

    <h1>Edit your profile.</h1>
    <p class="subtitle">Your corner of the CraveLab community.</p>

    <?php if ($message): ?>
        <div class="message"><?= e($message) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

        <div class="photo-area">

            <div class="avatar">
                <?php if ($imageExists): ?>
                    <img src="<?= e($image) ?>" alt="Profile picture">
                <?php else: ?>
                    👤
                <?php endif; ?>
            </div>

            <label for="profile_image">Change profile picture</label>
            <input type="file"
                   id="profile_image"
                   name="profile_image"
                   accept="image/jpeg,image/png,image/webp">

        </div>

        <div class="field">
            <label>Full name</label>
            <input type="text"
                   name="name"
                   maxlength="100"
                   value="<?= e($user["name"]) ?>"
                   required>
        </div>

        <div class="field">
            <label>Email address</label>
            <input type="email"
                   name="email"
                   maxlength="150"
                   value="<?= e($user["email"]) ?>"
                   required>
        </div>

        <div class="field">
            <label>Bio</label>
            <textarea name="bio"
                      maxlength="250"
                      placeholder="Tell the food community a little about yourself..."><?= e($user["bio"] ?? "") ?></textarea>
        </div>

        <div class="privacy-box">
            <label>
                <input type="checkbox"
                       name="is_private"
                       value="1"
                       <?= (int)$user["is_private"] === 1 ? "checked" : "" ?>>
                Make my account private
            </label>

            <p>
                Private accounts require approval before someone can follow
                you and view your private recipes.
            </p>
        </div>

        <button type="submit" class="save-button">
            Save Changes
        </button>

    </form>

    <a href="profile.php" class="back-link">← Back to profile</a>

</main>

<script>
document.getElementById("profile_image").addEventListener("change", function() {
    if (this.files.length > 0) {
        const reader = new FileReader();

        reader.onload = function(event) {
            document.querySelector(".avatar").innerHTML =
                '<img src="' + event.target.result + '" alt="Preview">';
        };

        reader.readAsDataURL(this.files[0]);
    }
});
</script>

</body>
</html>
