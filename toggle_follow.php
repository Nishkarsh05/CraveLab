
<?php
require_once __DIR__ . '/includes/session.php';
require_once 'includes/db.php';
require_once 'includes/security.php';

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: discover.php");
    exit();
}

cravelab_verify_csrf();

$followerId = (int)$_SESSION["user_id"];
$creatorId = filter_input(INPUT_POST, "creator_id", FILTER_VALIDATE_INT);

if (!$creatorId || $creatorId < 1 || $creatorId === $followerId) {
    header("Location: discover.php");
    exit();
}

// Check creator exists
$stmt = $conn->prepare(
    "SELECT id, is_private FROM users WHERE id = ?"
);
$stmt->bind_param("i", $creatorId);
$stmt->execute();
$creator = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$creator) {
    header("Location: discover.php");
    exit();
}

// Check existing relationship
$stmt = $conn->prepare("
    SELECT id FROM follows
    WHERE follower_id = ? AND following_id = ?
");

$stmt->bind_param("ii", $followerId, $creatorId);
$stmt->execute();
$existing = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($existing) {

    // Unfollow or cancel pending request
    $stmt = $conn->prepare("
        DELETE FROM follows
        WHERE follower_id = ? AND following_id = ?
    ");

    $stmt->bind_param("ii", $followerId, $creatorId);
    $stmt->execute();
    $stmt->close();

    header("Location: creator.php?id=" . $creatorId);
    exit();
}

// Public = accepted, private = pending
$status = ((int)$creator["is_private"] === 1)
    ? "pending"
    : "accepted";

// Create relationship
$stmt = $conn->prepare("
    INSERT INTO follows (follower_id, following_id, status)
    VALUES (?, ?, ?)
");

$stmt->bind_param("iis", $followerId, $creatorId, $status);

if (!$stmt->execute()) {
    $stmt->close();
    header("Location: creator.php?id=" . $creatorId);
    exit();
}

$stmt->close();

// Create notification
$notificationType = ($status === "pending")
    ? "follow_request"
    : "follow";

$stmt = $conn->prepare("
    INSERT INTO notifications (user_id, actor_id, type)
    VALUES (?, ?, ?)
");

$stmt->bind_param("iis", $creatorId, $followerId, $notificationType);
$stmt->execute();
$stmt->close();

header("Location: creator.php?id=" . $creatorId);
exit();
?>
