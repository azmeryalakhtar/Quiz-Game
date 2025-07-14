<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once $_SERVER['DOCUMENT_ROOT'] . '/quiz-game/includes/db.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/quiz-game/includes/auth.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: /quiz-game/pages/login.php');
    exit;
}

$userId = $_SESSION['user_id'];

// Fetch user data
try {
    $stmt = $pdo->prepare("SELECT username, coins FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        error_log("dashboard.php: User not found for userId=$userId");
        header('Location: /quiz-game/pages/login.php');
        exit;
    }
    $username = $user['username'];
    $userCoins = (int)$user['coins'];
} catch (Exception $e) {
    error_log("dashboard.php: Error fetching user data: " . $e->getMessage());
    $username = "User";
    $userCoins = 0;
}

// Fetch completed levels
try {
    $stmt = $pdo->prepare("
        SELECT qa.category_id, c.name AS category_name, qa.difficulty, qa.level_id, qa.correct_count
        FROM quiz_attempts qa
        JOIN categories c ON qa.category_id = c.id
        WHERE qa.user_id = ? AND qa.status = 'completed'
        ORDER BY qa.category_id, qa.difficulty, qa.level_id
    ");
    $stmt->execute([$userId]);
    $completedLevels = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("dashboard.php: Error fetching completed levels: " . $e->getMessage());
    $completedLevels = [];
}

// Fetch withdrawal history
try {
    $stmt = $pdo->prepare("
        SELECT id, amount, bank, account_name, phone_number, email, status, created_at
        FROM withdrawals
        WHERE user_id = ?
        ORDER BY created_at DESC
    ");
    $stmt->execute([$userId]);
    $withdrawals = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("dashboard.php: Error fetching withdrawal history: " . $e->getMessage());
    $withdrawals = [];
}
ob_end_flush();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz Dashboard - <?= htmlspecialchars($username) ?></title>
    <link rel="stylesheet" href="/quiz-game/assets/css/dashboard.css"/>
</head>
<body>
<canvas id="stars-canvas"></canvas>
    <div class="dashboard-container">
    <?php if (isset($_GET['message'])): ?>
    <div style="background-color: #dcfce7; color: #15803d; padding: 1rem; border-radius: 4px; margin-bottom: 1rem; transition: all 0.3s ease-in-out; text-align: center;">
        <?php echo htmlspecialchars($_GET['message']); ?>
    </div>
<?php endif; ?>
        <h1>Welcome, <?= htmlspecialchars($username) ?>!</h1>
        <div class="coins">
            <img src="/quiz-game/images/gold_coin.svg" alt="Gold Coin Icon" class="coin-icon intermittent-spin">
            <span id="coin-count"><?= $userCoins ?></span> Coins
        </div>
        <a href="/quiz-game/withdraw" class="withdraw-btn">Withdraw Coins</a>
        <h2>Completed Levels</h2>
        <?php if (empty($completedLevels)): ?>
            <p class="no-levels">No levels completed yet. Start a quiz to earn coins and complete levels!</p>
        <?php else: ?>
            <table class="levels-table">
                <thead>
                    <tr>
                        <th>Category</th>
                        <th>Difficulty</th>
                        <th>Level</th>
                        <th>Correct Answers</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($completedLevels as $level): ?>
                        <tr>
                            <td><?= htmlspecialchars($level['category_name']) ?></td>
                            <td><?= htmlspecialchars($level['difficulty']) ?></td>
                            <td><?= htmlspecialchars($level['level_id']) ?></td>
                            <td><?= htmlspecialchars($level['correct_count']) ?> / 10</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        <h2>Withdrawal History</h2>
<?php if (empty($withdrawals)): ?>
    <p class="no-withdrawals">No withdrawal requests yet.</p>
<?php else: ?>
    <div class="withdrawal-list">
        <?php foreach ($withdrawals as $withdrawal): ?>
            <div class="withdrawal-card">
                <div class="withdrawal-item" data-field="amount">
                    <span class="header">Amount (PKR)</span>
                    <span class="value"><?php echo htmlspecialchars(number_format($withdrawal['amount'], 2)); ?></span>
                </div>
                <div class="withdrawal-item" data-field="bank">
                    <span class="header">Bank</span>
                    <span class="value"><?php echo htmlspecialchars($withdrawal['bank']); ?></span>
                </div>
                <div class="withdrawal-item" data-field="account_name">
                    <span class="header">Account Name</span>
                    <span class="value"><?php echo htmlspecialchars($withdrawal['account_name']); ?></span>
                </div>
                <div class="withdrawal-item" data-field="phone_number">
                    <span class="header">Phone Number</span>
                    <span class="value"><?php echo htmlspecialchars($withdrawal['phone_number']); ?></span>
                </div>
                <div class="withdrawal-item" data-field="email">
                    <span class="header">Email</span>
                    <span class="value"><?php echo htmlspecialchars($withdrawal['email']); ?></span>
                </div>
                <div class="withdrawal-item" data-field="status">
                    <span class="header">Status</span>
                    <span class="value status-<?php echo strtolower($withdrawal['status']); ?>">
                        <?php echo htmlspecialchars(ucfirst($withdrawal['status'])); ?>
                    </span>
                </div>
                <div class="withdrawal-item" data-field="date">
                    <span class="header">Date</span>
                    <span class="value"><?php echo htmlspecialchars(date('Y-m-d H:i:s', strtotime($withdrawal['created_at']))); ?></span>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
    </div>
    <script src="/quiz-game/assets/js/main.js?v=<?php echo time(); ?>" defer></script>
</body>
</html>