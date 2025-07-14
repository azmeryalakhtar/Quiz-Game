<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

error_log("get-user-coins.php: 🔄 Starting file");

require_once $_SERVER['DOCUMENT_ROOT'] . '/quiz-game/includes/db.php';
error_log("get-user-coins.php: ✅ db.php loaded");

require_once $_SERVER['DOCUMENT_ROOT'] . '/quiz-game/includes/auth.php';
error_log("get-user-coins.php: ✅ auth.php loaded");

try {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $userId = $_SESSION['user_id'] ?? null;
    error_log("get-user-coins.php: Resolved user_id = " . var_export($userId, true));

    if (!$userId || !is_numeric($userId)) {
        throw new Exception("Missing or invalid user ID: " . var_export($userId, true));
    }

    $stmt = $pdo->prepare("SELECT coins FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        throw new Exception("User not found with ID $userId");
    }

    echo json_encode([
        "status" => "success",
        "coins" => (int)$user['coins']
    ]);
} catch (Exception $e) {
    error_log("❌ Error in get-user-coins.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage()
    ]);
} finally {
    $pdo = null;
}
