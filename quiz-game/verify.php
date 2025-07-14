<?php
require_once __DIR__ . '/includes/db.php';

if (isset($_GET['token'])) {
    $token = $_GET['token'];

    try {
        // Check if token exists and user is not verified
        $stmt = $pdo->prepare("SELECT id FROM users WHERE verification_token = ? AND is_verified = FALSE");
        $stmt->execute([$token]);
        $user = $stmt->fetch();

        if ($user) {
            // Mark email as verified
            $updateStmt = $pdo->prepare("UPDATE users SET is_verified = TRUE, verification_token = NULL WHERE id = ?");
            $updateStmt->execute([$user['id']]);
            $message = "Email verified successfully! You can now Login.";
        } else {
            $error = "Invalid or expired verification link.";
        }
    } catch (PDOException $e) {
        $error = "Verification error: " . $e->getMessage();
    }
} else {
    $error = "No verification token provided.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Email Verification</title>
    <link rel="stylesheet" href="/quiz-game/assets/css/verify.css"/>
</head>
<body>
<canvas id="stars-canvas"></canvas>
    <div class="verify-container">
        <h2>Email Verification</h2>
        <?php if (!empty($error)): ?>
            <p class="error-message"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>
        <?php if (!empty($message)): ?>
            <p class="success-message"><?= $message ?></p>
        <?php endif; ?>
        <p>
        <a href="login" class="login-link">Click here to login</a>
    </p>
    </div>
    <script src="/quiz-game/assets/js/main.js?v=<?php echo time(); ?>" defer></script>
</body>
</html>