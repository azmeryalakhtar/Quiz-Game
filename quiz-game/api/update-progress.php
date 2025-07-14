<?php
ob_start();
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', $_SERVER['DOCUMENT_ROOT'] . '/quiz-game/logs/error.log');
error_reporting(E_ALL);

require_once $_SERVER['DOCUMENT_ROOT'] . '/quiz-game/includes/db.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/quiz-game/includes/auth.php';

date_default_timezone_set('Asia/Karachi');
header('Content-Type: application/json');

try {
    $userId = $_SESSION['user_id'] ?? null;
    $category = isset($_POST['category']) ? (int)$_POST['category'] : null;
    $difficulty = $_POST['difficulty'] ?? null;
    $level = isset($_POST['level']) ? (int)$_POST['level'] : null;
    $status = $_POST['status'] ?? null;
    $attemptId = isset($_POST['attempt_id']) ? (int)$_POST['attempt_id'] : null;

    error_log("update-progress.php: userId=$userId, category=$category, difficulty=$difficulty, level=$level, status=$status, attempt_id=$attemptId, session_id=" . session_id());

    if (!$userId || !$category || !$difficulty || !$level || !$status || !$attemptId) {
        $missing = [];
        if (!$userId) $missing[] = "userId=$userId";
        if (!$category) $missing[] = "category=$category";
        if (!$difficulty) $missing[] = "difficulty=$difficulty";
        if (!$level) $missing[] = "level=$level";
        if (!$status) $missing[] = "status=$status";
        if (!$attemptId) $missing[] = "attempt_id=$attemptId";
        throw new Exception("Missing required parameters: " . implode(", ", $missing));
    }

    if (!in_array($status, ['completed', 'failed'])) {
        throw new Exception("Invalid status: $status");
    }

    $pdo->exec("SET time_zone = '+05:00'");

    // Verify attempt
    $stmt = $pdo->prepare("SELECT status, correct_count FROM quiz_attempts WHERE attempt_id = ? AND user_id = ?");
    if (!$stmt) {
        throw new Exception("Failed to prepare SELECT query: " . implode(", ", $pdo->errorInfo()));
    }
    $stmt->execute([$attemptId, $userId]);
    $attempt = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$attempt) {
        throw new Exception("Invalid attempt_id=$attemptId for user_id=$userId");
    }

    if ($attempt['status'] === 'completed' || $attempt['status'] === 'failed') {
        error_log("update-progress.php: Attempt already finalized, attempt_id=$attemptId, status={$attempt['status']}");
        echo json_encode([
            "status" => "success",
            "is_passed" => $attempt['status'] === 'completed',
            "correct_count" => (int)$attempt['correct_count'],
            "total_questions" => 10
        ]);
        exit;
    }

    // Count correct answers and total attempts (including refresh)
    $stmt = $pdo->prepare("
        SELECT 
            SUM(CASE WHEN is_correct = 1 THEN 1 ELSE 0 END) as correct_count,
            COUNT(*) as total_attempts
        FROM question_attempts 
        WHERE attempt_id = ? AND status IN ('answered', 'refresh', 'timed_out')
    ");
    $stmt->execute([$attemptId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $correctCount = (int)($result['correct_count'] ?? 0);
    $totalAttempts = (int)$result['total_attempts'];

// Dynamically count total questions from questions table
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM questions 
    WHERE category_id = ? AND difficulty = ? AND level = ?
");
$stmt->execute([$category, $difficulty, $level]);
$totalQuestions = (int)$stmt->fetchColumn();

// Calculate pass threshold based on total (e.g. 80% required)
$passThreshold = ceil($totalQuestions * 0.8);
$isPassed = $correctCount >= $passThreshold && $status === 'completed';
    $finalStatus = ($totalAttempts >= $totalQuestions || $status === 'completed' || $status === 'failed') ? ($isPassed ? 'completed' : 'failed') : 'in_progress';

    error_log("update-progress.php: attempt_id=$attemptId, correct_count=$correctCount, total_attempts=$totalAttempts, final_status=$finalStatus");

    // Calculate coins based on level
    $percentageCorrect = $totalQuestions > 0 ? ($correctCount / $totalQuestions) * 100 : 0;

    // Automatically set max coins as 100 × level number
    $maxCoins = $level * 100;
    
    // Award coins proportionally (only if passed)
    $coins = $isPassed ? round(($percentageCorrect / 100) * $maxCoins) : 0;
    
    // Update quiz_attempts
    $stmt = $pdo->prepare("
        UPDATE quiz_attempts 
        SET status = ?, correct_count = ?, end_time = NOW()
        WHERE attempt_id = ? AND status = 'in_progress'
    ");
    if (!$stmt) {
        throw new Exception("Failed to prepare UPDATE query: " . implode(", ", $pdo->errorInfo()));
    }
    $stmt->execute([$finalStatus, $correctCount, $attemptId]);

    if ($stmt->rowCount() === 0 && $attempt['status'] !== 'in_progress') {
        error_log("update-progress.php: Failed to update attempt_id=$attemptId, status={$attempt['status']}");
    } else {
        error_log("update-progress.php: Updated attempt_id=$attemptId, status=$finalStatus, correct_count=$correctCount");
    }

    // Update user_progress and coins only if passed
    if ($isPassed) {
        $stmt = $pdo->prepare("
            INSERT INTO user_progress (user_id, category_id, difficulty, level_id, completed_at)
            VALUES (?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE completed_at = NOW()
        ");
        $stmt->execute([$userId, $category, $difficulty, $level]);
        error_log("update-progress.php: Updated user_progress for user_id=$userId, category=$category, difficulty=$difficulty, level=$level");

        // Award coins
        $stmt = $pdo->prepare("
            INSERT INTO user_level_coins (user_id, category_id, difficulty, level_id, coins_awarded, awarded_at)
            VALUES (?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE coins_awarded = ?, awarded_at = NOW()
        ");
        $stmt->execute([$userId, $category, $difficulty, $level, $coins, $coins]);
        error_log("update-progress.php: Awarded $coins coins for user_id=$userId, level=$level, multiplier=$coinMultiplier");

        // Update users.coins
        $stmt = $pdo->prepare("SELECT SUM(coins_awarded) as total_coins FROM user_level_coins WHERE user_id = ?");
        $stmt->execute([$userId]);
        $totalCoins = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total_coins'];

        $stmt = $pdo->prepare("UPDATE users SET coins = ? WHERE id = ?");
        $stmt->execute([$totalCoins, $userId]);
        error_log("update-progress.php: Updated user coins for user_id=$userId, total_coins=$totalCoins");
    } else {
        error_log("update-progress.php: Skipped user_progress update for user_id=$userId, level=$level due to failure (correct_count=$correctCount)");
    }

    echo json_encode([
        "status" => "success",
        "is_passed" => $isPassed,
        "correct_count" => $correctCount,
        "total_questions" => $totalQuestions,
        "coins_added" => $isPassed ? $coins : 0
    ]);
} catch (Exception $e) {
    error_log("Error in update-progress.php: " . $e->getMessage());
    error_log("Trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Server error: " . $e->getMessage()
    ]);
} finally {
    $pdo = null;
    ob_end_flush();
}
?>