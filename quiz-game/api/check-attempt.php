<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once $_SERVER['DOCUMENT_ROOT'] . '/quiz-game/includes/db.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/quiz-game/includes/auth.php';

header('Content-Type: application/json');

$userId = $_SESSION['user_id'] ?? null;
$category = isset($_POST['category']) ? (int)$_POST['category'] : null;
$difficulty = $_POST['difficulty'] ?? null;
$level = isset($_POST['level']) ? (int)$_POST['level'] : null;

error_log("check-attempt.php: userId=$userId, category=$category, difficulty=$difficulty, level=$level");

if (!$userId || !$category || !$difficulty || !$level) {
    error_log("Missing parameters in check-attempt.php: " . json_encode($_POST));
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "Missing required parameters."
    ]);
    ob_end_flush();
    exit;
}

try {
    $pdo->exec("SET time_zone = '+05:00'");

    // Get latest attempt
    $stmt = $pdo->prepare("
        SELECT attempt_id, status, correct_count
        FROM quiz_attempts
        WHERE user_id = ? AND category_id = ? AND difficulty = ? AND level_id = ?
        ORDER BY start_time DESC
        LIMIT 1
    ");
    $stmt->execute([$userId, $category, $difficulty, $level]);
    $attempt = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$attempt) {
        error_log("No attempt found for userId=$userId, category=$category, difficulty=$difficulty, level=$level");
        echo json_encode([
            "status" => "no_attempt",
            "message" => "No attempt found for this level."
        ]);
    } else {
        // Count answered questions
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as answered_count 
            FROM question_attempts 
            WHERE attempt_id = ? AND status IN ('answered', 'timed_out', 'refresh')
        ");
        $stmt->execute([$attempt['attempt_id']]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $answeredCount = (int)$result['answered_count'];

        error_log("Found attempt: attempt_id={$attempt['attempt_id']}, status={$attempt['status']}, correct_count={$attempt['correct_count']}, answered_count=$answeredCount");
        echo json_encode([
            "status" => "success",
            "attempt_status" => $attempt['status'],
            "correct_count" => (int)$attempt['correct_count'],
            "attempt_id" => (int)$attempt['attempt_id'],
            "answered_count" => $answeredCount
        ]);
    }
} catch (Exception $e) {
    error_log("Error in check-attempt.php: " . $e->getMessage() . " | File: " . $e->getFile() . " | Line: " . $e->getLine());
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Server error: " . $e->getMessage()
    ]);
}

ob_end_flush();
?>