
<?php
require_once __DIR__ . '/includes/session.php';
require_once 'includes/db.php';
require_once 'includes/security.php';

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: notifications.php");
    exit();
}

cravelab_verify_csrf();

$userId = (int)$_SESSION["user_id"];
$followId = filter_input(INPUT_POST, "follow_id", FILTER_VALIDATE_INT);
$action = $_POST["action"] ?? "";

if (!$followId || !in_array($action, ["accept", "decline"], true)) {
    header("Location: notifications.php");
    exit();
}

// Only the account receiving the request can respond.
$stmt = $conn->prepare("
    SELECT follower_id
    FROM follows
    WHERE id = ? AND following_id = ? AND status = 'pending'
");
$stmt->bind_param("ii", $followId, $userId);
$stmt->execute();
$request = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$request) {
    header("Location: notifications.php");
    exit();
}

$followerId = (int)$request["follower_id"];

if ($action === "accept") {

    $stmt = $conn->prepare("
        UPDATE follows
        SET status = 'accepted'
        WHERE id = ? AND following_id = ? AND status = 'pending'
    ");
    $stmt->bind_param("ii", $followId, $userId);
    $stmt->execute();
    $stmt->close();

    $type = "request_accepted";

    $stmt = $conn->prepare("
        INSERT INTO notifications (user_id, actor_id, type, reference_id)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->bind_param("iisi", $followerId, $userId, $type, $followId);
    $stmt->execute();
    $stmt->close();

} else {

    $stmt = $conn->prepare("
        DELETE FROM follows
        WHERE id = ? AND following_id = ? AND status = 'pending'
    ");
    $stmt->bind_param("ii", $followId, $userId);
    $stmt->execute();
    $stmt->close();
}

header("Location: notifications.php");
exit();
