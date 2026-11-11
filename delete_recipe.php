<?php
require_once __DIR__ . '/includes/session.php';
require_once 'includes/db.php';
require_once 'includes/security.php';

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: dashboard.php");
    exit();
}
cravelab_verify_csrf();

$id = filter_input(INPUT_POST, "id", FILTER_VALIDATE_INT);
$userId = (int)$_SESSION["user_id"];

if (!$id || $id < 1) {
    header("Location: dashboard.php");
    exit();
}

// Read the image path only for the recipe owned by this user.
$stmt = $conn->prepare("SELECT image FROM recipes WHERE id = ? AND user_id = ? LIMIT 1");
$stmt->bind_param("ii", $id, $userId);
$stmt->execute();
$recipe = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$recipe) {
    header("Location: dashboard.php");
    exit();
}

$stmt = $conn->prepare("DELETE FROM recipes WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $id, $userId);
$stmt->execute();
$deleted = ($stmt->affected_rows === 1);
$stmt->close();

if ($deleted) {
    $image = $recipe["image"] ?? "";
    if ($image !== "" && strpos($image, "uploads/recipes/") === 0) {
        $fullPath = __DIR__ . "/" . $image;
        if (is_file($fullPath)) {
            unlink($fullPath);
        }
    }
    header("Location: dashboard.php?deleted=1");
} else {
    header("Location: dashboard.php");
}
exit();
