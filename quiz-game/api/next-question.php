<?php
ob_start();

// Enable error reporting for debugging (set display_errors to 0 in production)
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$userId = $_SESSION['user_id'] ?? null;
$sessionId = session_id();
session_write_close();

error_log("📥 INIT next-question.php, session_id=$sessionId, user_id=$userId");

require_once $_SERVER['DOCUMENT_ROOT'] . '/quiz-game/includes/db.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/quiz-game/includes/auth.php';

date_default_timezone_set('Asia/Karachi');

header('Content-Type: application/json');

$startTime = microtime(true);
error_log("📥 POST: " . json_encode($_POST));

function respond($statusCode, $data) {
    error_log("next-question.php: Responding with status=$statusCode, data=" . json_encode($data));
    http_response_code($statusCode);
    ob_clean();
    echo json_encode($data);
    ob_end_flush();
    exit;
}

try {
    if (!$pdo) {
        throw new Exception("Database connection is null. Check includes/db.php configuration.");
    }

    // Validate POST parameters
    $questionAttemptId = isset($_POST['question_attempt_id']) ? (int)$_POST['question_attempt_id'] : null;
    $attemptId = isset($_POST['attempt_id']) ? (int)$_POST['attempt_id'] : null;
    $token = trim($_POST['token'] ?? '');

    if (!$userId || !$attemptId || !$questionAttemptId || !$token) {
        $missing = [];
        if (!$userId) $missing[] = "userId=$userId";
        if (!$attemptId) $missing[] = "attemptId=$attemptId";
        if (!$questionAttemptId) $missing[] = "questionAttemptId=$questionAttemptId";
        if (!$token) $missing[] = "token=$token";
        error_log("next-question.php: Missing fields: " . implode(", ", $missing));
        respond(400, ["status" => "error", "message" => "Missing required fields: " . implode(", ", $missing)]);
    }

    // Verify token
    if (!isset($_SESSION['question_token']) || $_SESSION['question_token'] !== $token) {
        error_log("next-question.php: Invalid token - received=$token, expected=" . ($_SESSION['question_token'] ?? 'none'));
        respond(403, ["status" => "error", "message" => "Invalid or missing token."]);
    }

    $pdo->exec("SET time_zone = '+05:00'");

    // Verify quiz attempt
    $stmt = $pdo->prepare("
        SELECT status 
        FROM quiz_attempts 
        WHERE attempt_id = ? AND user_id = ? AND status = 'in_progress'
    ");
    $stmt->execute([$attemptId, $userId]);
    $attempt = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$attempt) {
        error_log("next-question.php: No matching in_progress attempt found for attempt_id=$attemptId, user_id=$userId");
        respond(404, ["status" => "error", "message" => "No matching in_progress quiz attempt found."]);
    }

    // Verify question attempt
    $stmt = $pdo->prepare("
        SELECT status 
        FROM question_attempts 
        WHERE question_attempt_id = ? AND attempt_id = ? AND status = 'in_progress'
    ");
    $stmt->execute([$questionAttemptId, $attemptId]);
    $questionAttempt = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$questionAttempt) {
        error_log("next-question.php: No matching in_progress question attempt found for question_attempt_id=$questionAttemptId, attempt_id=$attemptId");
        respond(404, ["status" => "error", "message" => "No matching in_progress question attempt found."]);
    }

    // Update question attempt to 'refresh'
    $stmt = $pdo->prepare("
        UPDATE question_attempts 
        SET status = 'refresh', end_time = NOW()
        WHERE question_attempt_id = ? AND status = 'in_progress'
    ");
    $stmt->execute([$questionAttemptId]);

    if ($stmt->rowCount() === 0) {
        error_log("next-question.php: Failed to update question_attempt_id=$questionAttemptId to refresh, status={$questionAttempt['status']}");
        respond(500, ["status" => "error", "message" => "Failed to update question attempt status."]);
    }

    error_log("next-question.php: Successfully updated question_attempt_id=$questionAttemptId to status=refresh, end_time set");

    // Do not clear session variables for refresh cases (handled by sendBeacon)
    error_log("next-question.php: Preserved session variables for refresh case");

    respond(200, ["status" => "success", "message" => "Question attempt marked as refreshed."]);

} catch (Exception $e) {
    error_log("❌ ERROR in next-question.php: " . $e->getMessage());
    error_log("❌ TRACE: " . $e->getTraceAsString());
    respond(500, ["status" => "error", "message" => "Server error: " . $e->getMessage()]);
} finally {
    error_log("⏱️ Execution time: " . round(microtime(true) - $startTime, 3) . "s");
    $pdo = null;
    ob_end_flush();
}
?>