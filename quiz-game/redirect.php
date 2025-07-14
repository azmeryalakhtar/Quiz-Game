<?php
$game = $_GET['game'] ?? '';

$map = [
  'quiz' => 'quiz-game',
  'ludo' => 'ludo-game',
  'chess' => 'chess-game'
];

if (array_key_exists($game, $map)) {
    header("Location: /" . $map[$game] . "/");
    exit;
} else {
    // fallback or 404
    echo "⚠️ Game not found.";
}
