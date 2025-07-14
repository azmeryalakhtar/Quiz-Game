<?php
// File: /quiz-game/api/check-timer.php
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

error_log("📥 INIT check-timer.php, session_id=$sessionId, user_id=$userId");

require_once $_SERVER['DOCUMENT_ROOT'] . '/quiz-game/includes/db.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/quiz-game/includes/auth.php';

date_default_timezone_set('Asia/Karachi');

header('Content-Type: application/json');

$startTime = microtime(true);
error_log("📥 POST: " . json_encode($_POST));

function respond($statusCode, $data) {
    error_log("check-timer.php: Responding with status=$statusCode, data=" . json_encode($data));
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
    $token = trim($_POST['token'] ?? '');

    if (!$userId || !$questionAttemptId || !$token) {
        $missing = [];
        if (!$userId) $missing[] = "userId=$userId";
        if (!$questionAttemptId) $missing[] = "questionAttemptId=$questionAttemptId";
        if (!$token) $missing[] = "token=$token";
        error_log("check-timer.php: Missing fields: " . implode(", ", $missing));
        respond(400, ["status" => "error", "message" => "Missing required fields: " . implode(", ", $missing)]);
    }

    // Verify token
    if (!isset($_SESSION['question_token']) || $_SESSION['question_token'] !== $token) {
        error_log("check-timer.php: Invalid token - received=$token, expected=" . ($_SESSION['question_token'] ?? 'none'));
        respond(403, ["status" => "error", "message" => "Invalid or missing token."]);
    }

    $pdo->exec("SET time_zone = '+05:00'");

    // Verify question attempt
    $stmt = $pdo->prepare("
        SELECT qa.attempt_id, qa.status, qa.start_time
        FROM question_attempts qa
        JOIN quiz_attempts quiz ON qa.attempt_id = quiz.attempt_id
        WHERE qa.question_attempt_id = ? AND quiz.user_id = ? AND qa.status = 'in_progress'
    ");
    $stmt->execute([$questionAttemptId, $userId]);
    $attempt = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$attempt) {
        error_log("check-timer.php: No matching in_progress question attempt found for question_attempt_id=$questionAttemptId, user_id=$userId");
        respond(404, ["status" => "error", "message" => "No matching in_progress question attempt found."]);
    }

    // Calculate time left (server-side validation)
    $startTime = new DateTime($attempt['start_time'], new DateTimeZone('Asia/Karachi'));
    $currentTime = new DateTime('now', new DateTimeZone('Asia/Karachi'));
    $interval = $currentTime->getTimestamp() - $startTime->getTimestamp();
    $timeLeft = max(0, 10 - $interval);

    error_log("check-timer.php: question_attempt_id=$questionAttemptId, start_time={$attempt['start_time']}, current_time={$currentTime->format('Y-m-d H:i:s')}, interval=$interval, timeLeft=$timeLeft");

    if ($interval < 0 || $interval > 3600) {
        error_log("check-timer.php: Invalid interval detected - interval=$interval, assuming timed out");
        $timeLeft = 0;
    }

    if ($timeLeft <= 0 && $attempt['status'] === 'in_progress') {
        $stmt = $pdo->prepare("
            UPDATE question_attempts 
            SET status = 'timed_out', end_time = NOW()
            WHERE question_attempt_id = ? AND status = 'in_progress'
        ");
        $stmt->execute([$questionAttemptId]);

        if ($stmt->rowCount() === 0) {
            error_log("check-timer.php: Failed to update question_attempt_id=$questionAttemptId to timed_out, status={$attempt['status']}");
            respond(500, ["status" => "error", "message" => "Failed to update question attempt status."]);
        }

        error_log("check-timer.php: Successfully updated question_attempt_id=$questionAttemptId to status=timed_out, end_time set");
    }

    respond(200, [
        "status" => "success",
        "time_left" => $timeLeft,
        "message" => $timeLeft <= 0 ? "Question attempt marked as timed out." : "Time still remaining."
    ]);

} catch (Exception $e) {
    error_log("❌ ERROR in check-timer.php: " . $e->getMessage());
    error_log("❌ TRACE: " . $e->getTraceAsString());
    respond(500, ["status" => "error", "message" => "Server error: " . $e->getMessage()]);
} finally {
    error_log("⏱️ Execution time: " . round(microtime(true) - $startTime, 3) . "s");
    $pdo = null;
    ob_end_flush();
}
?>