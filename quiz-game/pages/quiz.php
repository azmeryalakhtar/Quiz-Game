<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', $_SERVER['DOCUMENT_ROOT'] . '/quiz-game/logs/error.log');

require_once $_SERVER['DOCUMENT_ROOT'] . '/quiz-game/includes/db.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/quiz-game/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/quiz-game/includes/functions.php';

// Get user and quiz parameters
$userId = $_SESSION['user_id'] ?? null;
$category = isset($_GET['category']) ? (int)$_GET['category'] : 9;
$difficulty = $_GET['difficulty'] ?? 'medium';
$level = isset($_GET['level']) ? (int)$_GET['level'] : 1;
$isRestart = isset($_GET['restart']) && $_GET['restart'] === 'true';

error_log("Quiz.php loaded: userId=$userId, category=$category, difficulty=$difficulty, level=$level, restart=$isRestart");

if (!$userId) {
    error_log("Quiz.php: No user_id in session");
    echo "<p style='color:red;text-align:center;'>⚠️ Please log in to continue.</p>";
    exit;
}

// Fetch user's full name and coins
$stmt = $pdo->prepare("SELECT name, coins FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    throw new Exception("User not found for user_id=$userId");
}

// Extract and capitalize first name
$fullName = trim($user['name']);
$username = ucfirst(explode(' ', $fullName)[0]);

$userCoins = (int)$user['coins'];

error_log("quiz.php: Fetched user_id=$userId, username=$username, coins=$userCoins");

// Fetch category name
$stmt = $pdo->prepare("SELECT name FROM categories WHERE id = ?");
$stmt->execute([$category]);
$categoryData = $stmt->fetch(PDO::FETCH_ASSOC);
$categoryName = $categoryData ? htmlspecialchars($categoryData['name']) : 'Unknown Category';

// Validate level access
if (!canAccessLevel($pdo, $userId, $category, $difficulty, $level)) {
    error_log("Level access denied for user $userId, category $category, difficulty $difficulty, level $level");
    echo "<p style='color:red;text-align:center;'>❌ Level locked. Please complete previous level to continue.</p>";
    exit;
}

// Check latest attempt status
$attemptStatus = 'in_progress';
$attemptId = null;
$stmt = $pdo->prepare("
    SELECT attempt_id, status 
    FROM quiz_attempts 
    WHERE user_id = ? AND category_id = ? AND difficulty = ? AND level_id = ?
    ORDER BY start_time DESC 
    LIMIT 1
");
$stmt->execute([$userId, $category, $difficulty, $level]);
$latestAttempt = $stmt->fetch(PDO::FETCH_ASSOC);

if ($latestAttempt) {
    $attemptStatus = $latestAttempt['status'];
    $attemptId = $latestAttempt['attempt_id'];
}

// Create new quiz attempt only if restarting or no in_progress attempt exists
if ($isRestart || !$attemptId || $attemptStatus === 'completed' || $attemptStatus === 'failed') {
    if ($isRestart && $attemptStatus === 'in_progress') {
        error_log("Marking attempt $attemptId as restarted for user $userId");
        $stmt = $pdo->prepare("UPDATE quiz_attempts SET status = 'restarted' WHERE attempt_id = ?");
        $stmt->execute([$attemptId]);
    }
    if ($isRestart || !$latestAttempt || $attemptStatus === 'completed' || $attemptStatus === 'failed') {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO quiz_attempts (user_id, level_id, category_id, difficulty, start_time, status) 
                VALUES (?, ?, ?, ?, NOW(), 'in_progress')
            ");
            $stmt->execute([$userId, $level, $category, $difficulty]);
            $attemptId = $pdo->lastInsertId();
            $attemptStatus = 'in_progress';
            error_log("Created new attempt $attemptId for user $userId");
        } catch (Exception $e) {
            error_log("Error creating quiz attempt: " . $e->getMessage());
            echo "<p style='color:red;text-align:center;'>⚠️ Error starting quiz. Please try again.</p>";
            exit;
        }
    }
}

$_SESSION['current_attempt_id'] = $attemptId;

// Define number of questions per level
$stmt = $pdo->prepare("SELECT COUNT(*) FROM questions WHERE category_id = ? AND difficulty = ? AND level = ?");
$stmt->execute([$category, $difficulty, $level]);
$questionCount = (int) $stmt->fetchColumn();
error_log("Question count for category $category, difficulty $difficulty, level $level: $questionCount");

if ($questionCount == 0) {
    error_log("No questions available for category $category, difficulty $difficulty, level $level");
    echo "<p style='color:red;text-align:center;font-weight:bold;padding: 20px;'>⚠️ Not enough questions available for this level. Please choose another level or ask admin to add questions.</p>";
    exit;
}
?>
<?php
$isApp = false;

if (isset($_SERVER['HTTP_USER_AGENT']) && strpos($_SERVER['HTTP_USER_AGENT'], 'QuizGameApp') !== false) {
    $isApp = true; // This is from the WebView app
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link rel="icon" href="data:;base64,=">
    <title>Quiz Game - <?= $categoryName ?> - Level <?= $level ?></title>
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>

<!-- Google tag (gtag.js) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=G-R3GJ0VP6FJ"></script>
<script>
    window.adsbygoogleLoaded = false;
    window.dataLayer = window.dataLayer || [];
    function gtag(){ dataLayer.push(arguments); }
    gtag('js', new Date());
    gtag('config', 'G-R3GJ0VP6FJ');
</script>

<script async
    src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-4182308742558451"
    crossorigin="anonymous"
    onload="adsbygoogleLoaded = true;"
    onerror="adsbygoogleLoaded = false;">
</script>

<link rel="stylesheet" href="/quiz-game/assets/css/style.css" />
</head>


<body>
<canvas id="stars-canvas"></canvas>
<div class="top-bar">
    <div class="user-info home-section">
        <a href="/quiz-game"><img src="/quiz-game/images/home_icon.svg" alt="Home button" class="home-icon"></a>
    </div>
    <div class="user-info username-section">
        <a href="dashboard"><img src="/quiz-game/images/my-account-mobile.svg" alt="User Account Icon" class="user-icon">
        <span id="username"><?= htmlspecialchars($username) ?></span></a>
    </div>
    <div class="withdraw username-section">
        <a href="withdraw"><img src="/quiz-game/images/withdraw.svg" alt="Withdraw Icon" class="withdraw-icon">
        <span id="withdraw">Withdraw</span></a>
    </div>
    <div class="coins">
        <img src="/quiz-game/images/gold_coin.svg" alt="Gold Coin Icon" class="coin-icon intermittent-spin">
        <span id="coin-count"><?= $userCoins ?></span>Coins
    </div>
</div>
<!-- Question Box -->
<div class="question-box">
<button id="fullscreen-btn" aria-label="Toggle Fullscreen">
  <img id="fullscreen-icon" src="/quiz-game/images/fullscreen-icon.svg" alt="Enter Fullscreen">
</button>
    <div class="level-category">
        <div class="level-info">Level: <span id="level-display"><?= $level ?></span></div>
        <div class="category-info">Category: <span id="category-display"><?= htmlspecialchars($categoryName) ?></span></div>
    </div>
    <div id="timer" class="timer" style="display: <?= $attemptStatus === 'in_progress' ? 'block' : 'none' ?>;">⏱️ 10s</div>
    <div id="level-badge" style="display: none; text-align: center; margin: 15px 0px; font-size: 2rem;">
    🏆 <span style="color: gold; font-weight: bold;">Level Passed!</span>
</div>
    <div id="question-text">Loading question...</div>
    <div class="options">
        <button class="option-btn" data-choice="A"></button>
        <button class="option-btn" data-choice="B"></button>
        <button class="option-btn" data-choice="C"></button>
        <button class="option-btn" data-choice="D"></button>
    </div>
    <!-- Feedback & Next -->
    <div class="feedback-box" id="feedback-box" style="display: none;">
        <div id="feedback-message"></div>
        <button id="next-btn" disabled>Next Question</button>
        <button id="restart-btn" style="display: none;">🔁 Restart Level</button>
        <button id="next-level-btn" style="display: none;">➡ Next Level</button>
    </div>
</div>


    <!-- Ad container -->
    <div id="quiz-ad" style="margin: 20px auto; text-align: center;">
    <?php if (!$isApp): ?>
        <ins class="adsbygoogle"
             style="display:block"
             data-ad-client="ca-pub-4182308742558451"
             data-ad-slot="3816443115"
             data-ad-format="auto"
             data-full-width-responsive="true"></ins>
        <script>
            (adsbygoogle = window.adsbygoogle || []).push({});
        </script>
    <?php endif; ?>
</div>
<script>
  window.quizConfig = {
    currentLevel: <?= (int)$level ?>,
    category: <?= (int)$category ?>,
    difficulty: "<?= $difficulty ?>",
    maxQuestions: <?= (int)$questionCount ?>,
    username: "<?= htmlspecialchars($username) ?>",
    attemptId: <?= (int)$attemptId ?>,
    attemptStatus: "<?= $attemptStatus ?>",
    userCoins: <?= $userCoins ?>,
    passThreshold: 0.8 // 80% pass threshold
  };
</script>

<script src="/quiz-game/assets/js/quiz.js?v=<?php echo time(); ?>" defer></script>
<script src="/quiz-game/assets/js/main.js?v=<?php echo time(); ?>" defer></script>
  <script src="/quiz-game/assets/js/security.js?v=<?php echo time(); ?>" defer></script>
<audio id="correct-sound" src="/quiz-game/assets/sounds/correct.mp3" preload="auto"></audio>
<audio id="correct-auto" src="/quiz-game/assets/sounds/correct-auto.mp3" preload="auto"></audio>
<audio id="wrong-sound" src="/quiz-game/assets/sounds/wrong-sound.mp3" preload="auto"></audio>
<div id="red-flash"></div>
</body>
</html>