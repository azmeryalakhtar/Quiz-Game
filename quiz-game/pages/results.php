<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';

$userId = $_SESSION['user_id'];
$category = $_POST['category'];
$difficulty = $_POST['difficulty'];
$level = (int)$_POST['level'];
$score = (int)$_POST['score'];
$total = (int)$_POST['total'];

$passed = ($score / $total) >= 0.7 ? 1 : 0;

$stmt = $conn->prepare("
  INSERT INTO user_quiz_progress (user_id, category, difficulty, level, score, passed)
  VALUES (?, ?, ?, ?, ?, ?)
  ON DUPLICATE KEY UPDATE score = ?, passed = ?, updated_at = NOW()
");
$stmt->bind_param("iisiiiii", $userId, $category, $difficulty, $level, $score, $passed, $score, $passed);
$stmt->execute();

header("Location: dashboard.php");
