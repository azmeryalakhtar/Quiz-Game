<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
require_once 'includes/auth.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
$userId = $_SESSION['user_id'] ?? null;

// Fetch user's full name and coins
$stmt = $pdo->prepare("SELECT name, coins FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    throw new Exception("User not found for user_id=$userId");
}

// Extract and capitalize first name
$fullName = trim($user['name']);
$firstName = ucfirst(explode(' ', $fullName)[0]);

$userCoins = (int)$user['coins'];

error_log("index.php: Fetched user_id=$userId, first_name=$firstName, coins=$userCoins");


$userId = $_SESSION['user_id'];
$category = $_GET['category'] ?? 9;
$difficulty = $_GET['difficulty'] ?? 'Easy';

$categories = [
  9 => 'General Knowledge',
  10 => 'Entertainment: Books'
];

$difficulties = ['easy', 'medium', 'hard'];

$unlockedLevels = getUnlockedLevels($pdo, $userId, $category, $difficulty);
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
  <meta charset="UTF-8">
  <title>Start Quiz</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <?php if (!$isApp): ?>
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
<?php endif; ?>

    <link rel="stylesheet" href="/quiz-game/assets/css/index.css" />
</head>
<?php if ($isApp): ?>
<script>
// Just in case some AdSense slipped through (caching etc.)
document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll('ins.adsbygoogle, iframe[src*="ads"], iframe[src*="doubleclick"]')
        .forEach(el => el.remove());
});
</script>
<?php endif; ?>

<body>
  
<canvas id="stars-canvas"></canvas>
<div class="top-bar">
    <div class="user-info home-section">
        <a href="/quiz-game"><img src="/quiz-game/images/home_icon.svg" alt="Home button" class="home-icon"></a>
    </div>
    <div class="user-info username-section">
        <a href="dashboard"><img src="/quiz-game/images/my-account-mobile.svg" alt="User Account Icon" class="user-icon">
        <span id="username"><?= htmlspecialchars($firstName) ?></span></a>
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

  <div class="quiz-setup">
    <h1>🎮 Start Quiz Game</h1>

    <form action="" method="get" id="level-filter-form">
      <label for="category">Select Category:</label>
      <select name="category" id="category" onchange="document.getElementById('level-filter-form').submit()">
        <?php foreach ($categories as $id => $name): ?>
          <option value="<?= $id ?>" <?= $category == $id ? 'selected' : '' ?>><?= $name ?></option>
        <?php endforeach; ?>
      </select>
      <label for="difficulty">Select Difficulty:</label>
      <select name="difficulty" id="difficulty" onchange="document.getElementById('level-filter-form').submit()">
        <?php foreach ($difficulties as $level): ?>
          <option value="<?= $level ?>" <?= $difficulty == $level ? 'selected' : '' ?>><?= ucfirst($level) ?></option>
        <?php endforeach; ?>
      </select>
    </form>
    <form action="quiz" method="get">
      <input type="hidden" name="category" value="<?= $category ?>">
      <input type="hidden" name="difficulty" value="<?= $difficulty ?>">

      <label for="level">Select Level:</label>
      <select name="level" id="level">
    <option disabled selected>Loading levels... <span class="loader"></span></option>
</select>
      <button type="submit">Start Game</button>
    </form>
  </div>
 
<script src="/quiz-game/assets/js/main.js?v=<?php echo time(); ?>" defer></script>
<script src="/quiz-game/assets/js/index.js?v=<?php echo time(); ?>" defer></script>
<?php if (!$isApp): ?>
  <script src="/quiz-game/assets/js/security.js?v=<?php echo time(); ?>" defer></script>
<?php endif; ?>
</body>
</html>