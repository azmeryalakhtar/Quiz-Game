<?php
ob_start();
ini_set('display_errors', 0); // Set to 1 during development
ini_set('log_errors', 1);
ini_set('error_log', $_SERVER['DOCUMENT_ROOT'] . '/quiz-game/logs/error.log');
error_reporting(E_ALL);

require_once $_SERVER['DOCUMENT_ROOT'] . '/quiz-game/includes/db.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/quiz-game/includes/auth.php';

date_default_timezone_set('Asia/Karachi');

// Session & CSRF
if (session_status() === PHP_SESSION_NONE) session_start();
session_regenerate_id(true);
$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    header("Location: /quiz-game/login.php");
    exit;
}
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];
$ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

session_write_close();
error_log("withdraw.php: INIT, user_id=$userId, ip=$ipAddress");

$errorMessage = null;
$totalCoins = 0;

try {
    if (!$pdo) throw new Exception("Database connection failed.");

    $pdo->exec("SET TRANSACTION ISOLATION LEVEL SERIALIZABLE");

    $stmt = $pdo->prepare("SELECT username, email, phone, coins FROM users WHERE id = ? FOR UPDATE");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) throw new Exception("User not found.");

    $totalCoins = (int)$user['coins'];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if ($_POST['csrf_token'] !== $csrfToken) throw new Exception("Invalid CSRF token");

        $coins = (int)($_POST['coins'] ?? 0);
        $bank = $_POST['bank'] ?? '';
        $accountName = trim($_POST['account_name'] ?? '');
        $phoneNumber = trim($_POST['phone_number'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $amountPKR = $coins / 45;

        // Validations
        if ($totalCoins < 3600) throw new Exception("Earn at least 3600 coins to withdraw");
        if ($coins < 3600 || $coins > $totalCoins || !is_numeric($_POST['coins'])) throw new Exception("Invalid or insufficient coin amount");
        if (!in_array($bank, ['EasyPaisa', 'JazzCash'])) throw new Exception("Invalid bank");
        if (strlen($accountName) < 2) throw new Exception("Account name too short");
        if (!preg_match('/^03[0-9]{9}$/', $phoneNumber)) throw new Exception("Invalid phone format");
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new Exception("Invalid email");

        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            INSERT INTO withdrawals (user_id, amount, bank, account_name, phone_number, email, status, created_at, updated_at, ip_address)
            VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW(), NOW(), ?)
        ");
        $stmt->execute([$userId, $amountPKR, $bank, $accountName, $phoneNumber, $email, $ipAddress]);

        $stmt = $pdo->prepare("UPDATE users SET coins = ? WHERE id = ?");
        $stmt->execute([$totalCoins - $coins, $userId]);

        $pdo->commit();

        header("Location: /quiz-game/dashboard?message=Withdrawal+request+submitted+successfully");
        exit;
    }

} catch (Exception $e) {
    if ($pdo && $pdo->inTransaction()) $pdo->rollBack();
    error_log("withdraw.php: Error - " . $e->getMessage());
    $errorMessage = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Withdraw Coins - Quiz Game</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="/quiz-game/assets/css/withdraw.css?v=<?= time() ?>">
</head>
<body>
<canvas id="stars-canvas"></canvas>
<div class="container">
    <div class="user-info home-section">
        <a href="/quiz-game"><img src="/quiz-game/images/home_icon.svg" alt="Home" class="home-icon"></a>
    </div>
    <h1>Withdraw Your Coins</h1>

    <?php if ($errorMessage): ?>
        <div id="error-message" class="error-box"><?= htmlspecialchars($errorMessage) ?></div>
    <?php else: ?>
        <div id="error-message" class="error-box hidden"></div>
    <?php endif; ?>

    <div id="success-message" class="success-box hidden">Withdrawal request submitted successfully</div>

    <div class="total-coins">
        <p>Total Coins Available: <span id="total-coins"><?= $totalCoins ?></span></p>
    </div>

    <form method="POST" id="withdraw-form" class="form">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

        <div class="form-group">
            <label for="coins">Coins to Withdraw</label>
            <div class="coin-conversion">
                <input type="number" name="coins" id="coins" required min="3600" step="45" placeholder="Enter coins (min 3600)">
                <span>= <span id="pkr-amount">0.00</span> PKR</span>
            </div>
        </div>

        <div class="form-group">
    <label>Select Bank</label>
    <div class="bank-options">
        <label>
            <input type="radio" name="bank" value="JazzCash" required>
            <span class="jazzcash-icon"></span>
            <span>JazzCash</span>
        </label>
        <label>
            <input type="radio" name="bank" value="EasyPaisa" required>
            <span class="easypaisa-icon"></span>
            <span>EasyPaisa</span>
        </label>
    </div>
</div>


        <div class="form-group">
            <label for="account_name">Account Name</label>
            <input type="text" name="account_name" id="account_name" required>
        </div>

        <div class="form-group">
            <label for="phone_number">Phone Number</label>
            <input type="text" name="phone_number" id="phone_number" required placeholder="03012345678" value="<?= htmlspecialchars($user['phone']) ?>">
        </div>

        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" name="email" id="email" required value="<?= htmlspecialchars($user['email']) ?>">
        </div>

        <button type="submit" id="withdraw-btn" disabled>Withdraw</button>
    </form>
</div>

<script>
    const totalCoins = <?= json_encode($totalCoins) ?>;
    const coinsInput = document.getElementById('coins');
    const pkrAmount = document.getElementById('pkr-amount');
    const totalCoinsDisplay = document.getElementById('total-coins');
    const phoneInput = document.getElementById('phone_number');
    const accountInput = document.getElementById('account_name');
    const emailInput = document.getElementById('email');
    const form = document.getElementById('withdraw-form');
    const errorMessageDiv = document.getElementById('error-message');
    const successMessage = document.getElementById('success-message');
    const withdrawBtn = document.getElementById('withdraw-btn');

    function showError(msg) {
        errorMessageDiv.textContent = msg;
        errorMessageDiv.classList.remove('hidden');
        successMessage.classList.add('hidden');
        withdrawBtn.disabled = true;
    }

    function clearError() {
        errorMessageDiv.textContent = '';
        errorMessageDiv.classList.add('hidden');
        withdrawBtn.disabled = false;
    }

    function validateForm(showErrors = false) {
        const coins = parseInt(coinsInput.value) || 0;
        const phone = phoneInput.value;
        const account = accountInput.value;
        const email = emailInput.value;
        const bankSelected = document.querySelector('input[name="bank"]:checked');

        if (totalCoins < 3600) return showErrors && showError("Earn at least 3600 coins to withdraw"), false;
        if (coins < 3600) return showErrors && showError("Minimum withdrawal is 3600 coins"), false;
        if (coins > totalCoins) return showErrors && showError("Insufficient coins"), false;
        if (!bankSelected) return showErrors && showError("Please select a bank"), false;
        if (!account || account.length < 2) return showErrors && showError("Account name must be at least 2 characters"), false;
        if (!/^03[0-9]{9}$/.test(phone)) return showErrors && showError("Phone must be 11 digits starting with 03"), false;
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) return showErrors && showError("Invalid email address"), false;

        if (showErrors) clearError();
        return true;
    }

    function updateDisplay() {
        const coins = parseInt(coinsInput.value) || 0;
        pkrAmount.textContent = (coins / 45).toFixed(2);
        totalCoinsDisplay.textContent = totalCoins - coins;
        validateForm();
    }

    // Event bindings
    document.querySelectorAll('input').forEach(input => input.addEventListener('input', validateForm));
    document.querySelectorAll('input[name="bank"]').forEach(r => r.addEventListener('change', validateForm));
    coinsInput.addEventListener('input', updateDisplay);

    form.addEventListener('submit', e => {
        if (!validateForm(true)) e.preventDefault();
    });

    // Initial validation
    updateDisplay();
</script>
<script src="/quiz-game/assets/js/main.js?v=<?= time() ?>" defer></script>
</body>
</html>
<?php $pdo = null; ob_end_flush(); ?>
