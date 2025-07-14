<?php
require_once __DIR__ . '/includes/db.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);

    try {
        // Check if email exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND is_verified = TRUE");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            // Generate reset token and expiry (1 hour from now)
            $reset_token = bin2hex(random_bytes(16));
            $reset_token_expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));

            // Store reset token and expiry
            $updateStmt = $pdo->prepare("UPDATE users SET reset_token = ?, reset_token_expiry = ? WHERE id = ?");
            $updateStmt->execute([$reset_token, $reset_token_expiry, $user['id']]);

            // Send reset email
            $subject = "Password Reset Request";
            $reset_link = "https://games.phonesdukan.com/quiz-game/reset_password?token=" . $reset_token;
            $message = "Hello,\n\nYou requested a password reset. Click the link below to reset your password:\n$reset_link\n\nThis link will expire in 1 hour.\n\nThank you!";
            $headers = "From: no-reply@games.phonesdukan.com\r\n";

            if (mail($email, $subject, $message, $headers)) {
                $success = "A password reset link has been sent to your email 📩.";
            } else {
                $error = "Failed to send reset email. Please try again later.";
            }
        } else {
            $error = "No verified account found with this email.";
        }
    } catch (PDOException $e) {
        $error = "Error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Forgot Password</title>
    <link rel="stylesheet" href="/quiz-game/assets/css/forgot_password.css"/>
</head>
<body>
<canvas id="stars-canvas"></canvas>
    <div class="forgot-password-container">
        <h2>Forgot Password</h2>
        <?php if (!empty($error)): ?>
            <p class="error-message"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>
        <?php if (!empty($success)): ?>
            <p class="success-message"><?= htmlspecialchars($success) ?></p>
        <?php endif; ?>
        <form method="post">
            <input type="email" name="email" placeholder="Enter your email" required><br>
            <button type="submit">Send Reset Link</button>
        </form>
        <div class="login-link">
            <p>Remembered your password? <a href="login.php">Login</a></p>
        </div>
    </div>
    <script src="/quiz-game/assets/js/main.js?v=<?php echo time(); ?>" defer></script>
</body>
</html>