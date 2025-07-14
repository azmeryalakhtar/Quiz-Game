<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/quiz-game/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/quiz-game/includes/db.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/quiz-game/includes/functions.php';

header('Content-Type: application/json');

// Get user session
$userId = $_SESSION['user_id'] ?? null;
$category = isset($_GET['category']) ? (int)$_GET['category'] : 9;
$difficulty = $_GET['difficulty'] ?? 'medium';

if (!$userId) {
    echo json_encode([]);
    exit;
}

$unlockedLevels = getUnlockedLevels($pdo, $userId, $category, $difficulty);

echo json_encode($unlockedLevels);
