<?php
session_start();
header('Content-Type: application/json');

if (!isset($_POST['index']) || !isset($_POST['userAnswer'])) {
  echo json_encode(['error' => 'Missing data']);
  exit;
}

$index = (int)$_POST['index'];
$userAnswer = trim($_POST['userAnswer']);
$questions = $_SESSION['quiz_questions'] ?? [];

if (!isset($questions[$index])) {
  echo json_encode(['error' => 'Invalid question index']);
  exit;
}

$correctAnswer = html_entity_decode($questions[$index]['correct_answer']);

$isCorrect = $userAnswer === $correctAnswer;

$response = [
  'correct' => $isCorrect,
  'message' => $isCorrect ? "✅ Correct!" : "❌ Wrong! Correct was: $correctAnswer",
  'coins' => $isCorrect ? 10 : 0
];

echo json_encode($response);
