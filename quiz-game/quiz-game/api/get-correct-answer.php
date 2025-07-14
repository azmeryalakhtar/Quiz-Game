<?php
// File: /quiz-game/api/get-correct-answer.php
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

error_log("📥 INIT get-correct-answer.php, session_id=$sessionId, user_id=$userId");

require_once $_SERVER['DOCUMENT_ROOT'] . '/quiz-game/includes/db.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/quiz-game/includes/auth.php';

date_default_timezone_set('Asia/Karachi');

header('Content-Type: application/json');

$startTime = microtime(true);
error_log("📥 GET: " . json_encode($_GET));

function respond($statusCode, $data) {
    error_log("get-correct-answer.php: Responding with status=$statusCode, data=" . json_encode($data));
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

    // Validate GET parameters
    $questionId = isset($_GET['question_id']) ? (int)$_GET['question_id'] : null;

    if (!$userId || !$questionId) {
        $missing = [];
        if (!$userId) $missing[] = "userId=$userId";
        if (!$questionId) $missing[] = "questionId=$questionId";
        error_log("get-correct-answer.php: Missing fields: " . implode(", ", $missing));
        respond(400, ["status" => "error", "message" => "Missing required fields: " . implode(", ", $missing)]);
    }

    // Fetch correct answer
// Fetch correct answer from question_options table
$stmt = $pdo->prepare("
    SELECT option_text 
    FROM question_options 
    WHERE question_id = ? AND is_correct = 1 
    LIMIT 1
");
$stmt->execute([$questionId]);
$correctAnswer = $stmt->fetchColumn();

if (!$correctAnswer) {
    error_log("get-correct-answer.php: No correct answer found for question_id=$questionId");
    respond(404, ["status" => "error", "message" => "Correct answer not found for the provided question ID."]);
}

error_log("get-correct-answer.php: Retrieved correct_answer for question_id=$questionId");

respond(200, [
    "status" => "success",
    "correct_answer" => $correctAnswer
]);


} catch (Exception $e) {
    error_log("❌ ERROR in get-correct-answer.php: " . $e->getMessage());
    error_log("❌ TRACE: " . $e->getTraceAsString());
    respond(500, ["status" => "error", "message" => "Server error: " . $e->getMessage()]);
} finally {
    error_log("⏱️ Execution time: " . round(microtime(true) - $startTime, 3) . "s");
    $pdo = null;
    ob_end_flush();
}
?>