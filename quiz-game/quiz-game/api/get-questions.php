<?php
// File: /quiz-game/api/get-questions.php
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

error_log("📥 INIT get-questions.php, session_id=$sessionId, user_id=$userId");

require_once $_SERVER['DOCUMENT_ROOT'] . '/quiz-game/includes/db.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/quiz-game/includes/auth.php';

date_default_timezone_set('Asia/Karachi');

header('Content-Type: application/json');

$startTime = microtime(true);
error_log("📥 POST: " . json_encode($_POST));

try {
    // Validate POST parameters
    $category = isset($_POST['category']) ? (int)$_POST['category'] : null;
    $difficulty = $_POST['difficulty'] ?? null;
    $level = isset($_POST['level']) ? (int)$_POST['level'] : null;
    $attemptId = isset($_POST['attempt_id']) ? (int)$_POST['attempt_id'] : null;

    error_log("get-questions.php: userId=$userId, category Alder, category=$category, difficulty=$difficulty, level=$level, attempt_id=$attemptId");

    if (!$userId || !$category || !$difficulty || !$level || !$attemptId) {
        $missing = [];
        if (!$userId) $missing[] = "userId=$userId";
        if (!$category) $missing[] = "category=$category";
        if (!$difficulty) $missing[] = "difficulty=$difficulty";
        if (!$level) $missing[] = "level=$level";
        if (!$attemptId) $missing[] = "attempt_id=$attemptId";
        throw new Exception("Missing required parameters: " . implode(", ", $missing));
    }

    if (!$pdo) {
        throw new Exception("Database connection is null. Check includes/db.php configuration.");
    }

    $pdo->exec("SET time_zone = '+05:00'");

    // Verify quiz attempt exists and is in_progress
    $stmt = $pdo->prepare("
        SELECT attempt_id, status
        FROM quiz_attempts
        WHERE attempt_id = ? AND user_id = ? AND category_id = ? AND difficulty = ? AND level_id = ? AND status = 'in_progress'
    ");
    $stmt->execute([$attemptId, $userId, $category, $difficulty, $level]);
    $attempt = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$attempt) {
        error_log("get-questions.php: Invalid or non-in_progress attempt_id=$attemptId for user_id=$userId");
        respond(404, [
            "status" => "error",
            "message" => "Invalid quiz attempt or attempt is not in progress."
        ]);
    }

// Dynamically determine the number of available questions for this level, category, and difficulty
$stmt = $pdo->prepare("
    SELECT COUNT(*) as total_questions 
    FROM questions 
    WHERE category_id = ? AND difficulty = ? AND level = ?
");
$stmt->execute([$category, $difficulty, $level]);
$total = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total_questions'];

// If no questions found, return error
if ($total === 0) {
    respond(404, [
        "status" => "error",
        "message" => "No questions found for this level, category, and difficulty."
    ]);
}

$maxQuestions = $total;

// Check how many questions have already been attempted
$stmt = $pdo->prepare("
    SELECT COUNT(*) as question_count 
    FROM question_attempts 
    WHERE attempt_id = ? AND status IN ('answered', 'timed_out', 'refresh')
");
$stmt->execute([$attemptId]);
$count = $stmt->fetch(PDO::FETCH_ASSOC)['question_count'];

error_log("get-questions.php: question_count=$count, maxQuestions=$maxQuestions, attempt_id=$attemptId");

if ($count >= $maxQuestions) {
    error_log("get-questions.php: Max questions reached for attempt_id=$attemptId, question_count=$count");
    respond(200, ["status" => "complete"]);
}


    error_log("get-questions.php: question_count=$count, maxQuestions=$maxQuestions, attempt_id=$attemptId");

    if ($count >= $maxQuestions) {
        error_log("get-questions.php: Max questions reached for attempt_id=$attemptId, question_count=$count");
        respond(200, ["status" => "complete"]);
    }

    // Mark existing in_progress question attempt as refresh
    $stmt = $pdo->prepare("
        SELECT question_attempt_id
        FROM question_attempts
        WHERE attempt_id = ? AND status = 'in_progress'
        ORDER BY start_time DESC
        LIMIT 1
    ");
    $stmt->execute([$attemptId]);
    $existingAttempt = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existingAttempt) {
        $stmt = $pdo->prepare("
            UPDATE question_attempts 
            SET status = 'refresh', end_time = NOW()
            WHERE question_attempt_id = ? AND status = 'in_progress'
        ");
        $stmt->execute([$existingAttempt['question_attempt_id']]);
        error_log("get-questions.php: Marked in_progress question_attempt_id={$existingAttempt['question_attempt_id']} as refresh");
    }

    // Get a new question, excluding previously attempted questions
// Get a new question, excluding previously attempted questions
$stmt = $pdo->prepare("
    SELECT q.id AS question_id, q.question_text
    FROM questions q
    LEFT JOIN question_attempts qa ON q.id = qa.question_id AND qa.attempt_id = ?
    WHERE q.category_id = ? AND q.difficulty = ? AND q.level = ? AND qa.question_id IS NULL
    ORDER BY RAND()
    LIMIT 1
");
$stmt->execute([$attemptId, $category, $difficulty, $level]);
$question = $stmt->fetch(PDO::FETCH_ASSOC);


    if (!$question) {
        error_log("get-questions.php: No questions available for category=$category, difficulty=$difficulty, level=$level, attempt_id=$attemptId");
        // Check again if max questions reached to avoid race condition
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as question_count 
            FROM question_attempts 
            WHERE attempt_id = ? AND status IN ('answered', 'timed_out', 'refresh')
        ");
        $stmt->execute([$attemptId]);
        $count = $stmt->fetch(PDO::FETCH_ASSOC)['question_count'];
        if ($count >= $maxQuestions) {
            error_log("get-questions.php: Max questions confirmed for attempt_id=$attemptId, question_count=$count");
            respond(200, ["status" => "complete"]);
        }
        respond(404, [
            "status" => "error",
            "message" => "No more questions available for this quiz."
        ]);
    }

    // Parse incorrect_answers (JSON)
// Fetch options from question_options table
$stmt = $pdo->prepare("
    SELECT option_text 
    FROM question_options 
    WHERE question_id = ?
");
$stmt->execute([$question['question_id']]);
$options = $stmt->fetchAll(PDO::FETCH_COLUMN);

if (count($options) !== 4) {
    throw new Exception("Invalid number of options (" . count($options) . ") for question_id={$question['question_id']}");
}

shuffle($options);


    // Generate token
    $token = bin2hex(random_bytes(16));
    $_SESSION['question_token'] = $token;

    // Log new question attempt with PKT time
    $stmt = $pdo->prepare("
        INSERT INTO question_attempts (attempt_id, question_id, start_time, status)
        VALUES (?, ?, NOW(), 'in_progress')
    ");
    $stmt->execute([$attemptId, $question['question_id']]);
    $questionAttemptId = $pdo->lastInsertId();
    $_SESSION['question_attempt_id'] = $questionAttemptId;

    // Verify start_time
    $stmt = $pdo->prepare("SELECT start_time FROM question_attempts WHERE question_attempt_id = ?");
    $stmt->execute([$questionAttemptId]);
    $startTime = $stmt->fetchColumn();
    error_log("get-questions.php: Inserted question_attempt_id=$questionAttemptId, question_id={$question['question_id']}, start_time=$startTime, server_time=" . date('Y-m-d H:i:s') . ", token=$token");

    respond(200, [
        "status" => "success",
        "question_id" => $question['question_id'],
        "question_text" => $question['question_text'],
        "options" => $options,
        "question_attempt_id" => $questionAttemptId,
        "token" => $token
    ]);
} catch (Exception $e) {
    error_log("❌ ERROR in get-questions.php: " . $e->getMessage());
    error_log("❌ TRACE: " . $e->getTraceAsString());
    respond(500, [
        "status" => "error",
        "message" => "Server error: " . $e->getMessage()
    ]);
} finally {
    error_log("⏱️ Execution time: " . round(microtime(true) - $startTime, 3) . "s");
    $pdo = null;
    ob_end_flush();
}

function respond($statusCode, $data) {
    http_response_code($statusCode);
    ob_clean();
    echo json_encode($data);
    ob_end_flush();
    exit;
}
?>