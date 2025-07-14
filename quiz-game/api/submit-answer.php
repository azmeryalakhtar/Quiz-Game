<?php
ob_start();

// Enable error reporting for debugging (set display_errors to 0 in production)
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$userId = $_SESSION['user_id'] ?? null;
$sessionId = session_id();
session_write_close();

error_log("📥 INIT submit-answer.php, session_id=$sessionId, user_id=$userId");

// Use absolute paths for includes
$basePath = $_SERVER['DOCUMENT_ROOT'] . '/quiz-game/';
require_once $basePath . 'includes/db.php';
require_once $basePath . 'includes/auth.php';

date_default_timezone_set('Asia/Karachi');

header('Content-Type: application/json');

$startTime = microtime(true);
error_log("📥 POST: " . json_encode($_POST));

function respond($statusCode, $data) {
    error_log("submit-answer.php: Responding with status=$statusCode, data=" . json_encode($data));
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
    $questionId = isset($_POST['question_id']) ? (int)$_POST['question_id'] : null;
    $selectedAnswer = trim($_POST['selected_answer'] ?? '');
    $questionAttemptId = isset($_POST['question_attempt_id']) ? (int)$_POST['question_attempt_id'] : null;
    $token = trim($_POST['token'] ?? '');

    if (!$userId || !$questionId || !$selectedAnswer || !$questionAttemptId || !$token) {
        $missing = [];
        if (!$userId) $missing[] = "userId=$userId";
        if (!$questionId) $missing[] = "questionId=$questionId";
        if (!$selectedAnswer) $missing[] = "selectedAnswer=$selectedAnswer";
        if (!$questionAttemptId) $missing[] = "questionAttemptId=$questionAttemptId";
        if (!$token) $missing[] = "token=$token";
        error_log("submit-answer.php: Missing fields: " . implode(", ", $missing));
        respond(400, ["status" => "error", "message" => "Missing required fields: " . implode(", ", $missing)]);
    }

    // Verify token (assuming stored in session from get-questions.php)
    if (!isset($_SESSION['question_token']) || $_SESSION['question_token'] !== $token) {
        error_log("submit-answer.php: Invalid token - received=$token, expected=" . ($_SESSION['question_token'] ?? 'none'));
        respond(403, ["status" => "error", "message" => "Invalid or missing token."]);
    }

    $pdo->exec("SET time_zone = '+05:00'");

    // Validate question attempt
// Validate question attempt
$stmt = $pdo->prepare("
    SELECT qa.attempt_id, qa.start_time, qa.status, qa.question_id
    FROM question_attempts qa
    JOIN quiz_attempts quiz ON qa.attempt_id = quiz.attempt_id
    WHERE qa.question_attempt_id = ? AND quiz.user_id = ? AND quiz.status = 'in_progress'
");
if (!$stmt) {
    throw new Exception("Failed to prepare SELECT query: " . implode(", ", $pdo->errorInfo()));
}
$stmt->execute([$questionAttemptId, $userId]);
$attempt = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$attempt) {
    error_log("submit-answer.php: Invalid attempt - question_attempt_id=$questionAttemptId, user_id=$userId");
    respond(404, ["status" => "error", "message" => "Invalid or expired question attempt."]);
}

// Fetch correct answer for the question
$stmt = $pdo->prepare("SELECT option_text FROM question_options WHERE question_id = ? AND is_correct = 1 LIMIT 1");
$stmt->execute([$attempt['question_id']]);
$correctAnswer = $stmt->fetchColumn();

if (!$correctAnswer) {
    throw new Exception("Correct answer not found for question_id=" . $attempt['question_id']);
}

$attempt['correct_answer'] = $correctAnswer;


    if ((int)$attempt['question_id'] !== $questionId || $attempt['status'] !== 'in_progress') {
        error_log("submit-answer.php: Question not active - question_id=$questionId, attempt_status=" . $attempt['status']);
        respond(409, ["status" => "error", "message" => "This question has already been answered or is not active."]);
    }

    // Check elapsed time
    $start = new DateTime($attempt['start_time'], new DateTimeZone('Asia/Karachi'));
    $now = new DateTime('now', new DateTimeZone('Asia/Karachi'));
    $elapsed = $now->getTimestamp() - $start->getTimestamp();

    error_log("submit-answer.php: question_attempt_id=$questionAttemptId, start_time={$attempt['start_time']}, current_time={$now->format('Y-m-d H:i:s')}, elapsed=$elapsed");

    if ($elapsed < 0 || $elapsed > 3600) {
        error_log("submit-answer.php: Invalid elapsed time detected - elapsed=$elapsed, assuming within time limit");
        $elapsed = 0;
    }

    if ($elapsed > 10) {
        $stmt = $pdo->prepare("
            UPDATE question_attempts 
            SET status = 'timed_out', end_time = NOW()
            WHERE question_attempt_id = ? AND status = 'in_progress'
        ");
        $stmt->execute([$questionAttemptId]);
        if ($stmt->rowCount() === 0) {
            error_log("submit-answer.php: Failed to update question_attempt_id=$questionAttemptId to timed_out, status=" . $attempt['status']);
        } else {
            error_log("submit-answer.php: Updated question_attempt_id=$questionAttemptId to timed_out, end_time set");
            $stmt = $pdo->prepare("SELECT end_time FROM question_attempts WHERE question_attempt_id = ?");
            $stmt->execute([$questionAttemptId]);
            $endTime = $stmt->fetchColumn();
            error_log("submit-answer.php: Verified end_time=$endTime for question_attempt_id=$questionAttemptId");
        }
        respond(408, ["status" => "error", "message" => "Time limit exceeded."]);
    }

    // Update answer
    $isCorrect = $selectedAnswer === $attempt['correct_answer'];
    $stmt = $pdo->prepare("
        UPDATE question_attempts 
        SET status = 'answered', selected_answer = ?, is_correct = ?, end_time = NOW()
        WHERE question_attempt_id = ? AND status = 'in_progress'
    ");
    if (!$stmt) {
        throw new Exception("Failed to prepare UPDATE query: " . implode(", ", $pdo->errorInfo()));
    }
    $stmt->execute([$selectedAnswer, $isCorrect ? 1 : 0, $questionAttemptId]);

    if ($stmt->rowCount() === 0) {
        error_log("submit-answer.php: Failed to update answer for question_attempt_id=$questionAttemptId, status=" . $attempt['status']);
        respond(500, ["status" => "error", "message" => "Failed to update answer."]);
    }

    error_log("submit-answer.php: Updated answer for question_attempt_id=$questionAttemptId, selected_answer=$selectedAnswer, is_correct=$isCorrect, end_time set");

    respond(200, [
        "status" => "success",
        "is_correct" => $isCorrect,
        "correct_answer" => $attempt['correct_answer']
    ]);

} catch (Exception $e) {
    error_log("❌ ERROR in submit-answer.php: " . $e->getMessage());
    error_log("❌ TRACE: " . $e->getTraceAsString());
    respond(500, ["status" => "error", "message" => "Server error: " . $e->getMessage()]);
} finally {
    error_log("⏱️ Execution time: " . round(microtime(true) - $startTime, 3) . "s");
    $pdo = null;
    ob_end_flush();
}
?>