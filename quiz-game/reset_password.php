<?php
require_once __DIR__ . '/includes/db.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['token'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    try {
        // Check if token is valid and not expired
        $stmt = $pdo->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_token_expiry > NOW()");
        $stmt->execute([$token]);
        $user = $stmt->fetch();

        if ($user) {
            // Update password and clear reset token
            $updateStmt = $pdo->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_token_expiry = NULL WHERE id = ?");
            $updateStmt->execute([$password, $user['id']]);
            $success = "Password reset successfully! You can now Login.";
        } else {
            $error = "Invalid or expired reset link.";
        }
    } catch (PDOException $e) {
        $error = "Error: " . $e->getMessage();
    }
} elseif (!isset($_GET['token'])) {
    $error = "No reset token provided.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reset Password</title>
    <link rel="stylesheet" href="/quiz-game/assets/css/reset_passwrod.css"/>
</head>
<body>
<canvas id="stars-canvas"></canvas>
    <div class="reset-password-container">
        <h2>Reset Password</h2>
        <?php if (!empty($error)): ?>
            <p class="error-message"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>
        <?php if (!empty($success)): ?>
            <p class="success-message"><?= htmlspecialchars($success) ?></p>
        <?php endif; ?>
        <?php if (empty($success) && !empty($_GET['token'])): ?>
            <form method="post">
                <input type="hidden" name="token" value="<?= htmlspecialchars($_GET['token']) ?>">
                <input type="password" name="password" placeholder="New Password" required><br>
                <button type="submit">Reset Password</button>
            </form>
        <?php endif; ?>
        <div class="login-link">
            <p>Return to <a href="login.php">Login</a></p>
        </div>
    </div>
    <script src="/quiz-game/assets/js/main.js?v=<?php echo time(); ?>" defer></script>
</body>
</html>