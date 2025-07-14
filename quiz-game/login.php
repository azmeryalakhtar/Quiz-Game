<?php
require_once __DIR__ . '/includes/db.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    try {
        $stmt = $pdo->prepare("SELECT id, password, is_verified FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            if ($user['is_verified']) {
                $_SESSION['user_id'] = $user['id'];
                header("Location: /quiz-game");
                exit;
            } else {
                $error = "Please verify your email before logging in.";
            }
        } else {
            $error = "Invalid email or password.";
        }
    } catch (PDOException $e) {
        $error = "Login error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login</title>
    <link rel="stylesheet" href="/quiz-game/assets/css/login.css"/>
</head>
<body>
<canvas id="stars-canvas"></canvas>
    <div class="login-container">
        <h2>Login</h2>
        <?php if (!empty($error)): ?>
            <p class="error-message"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>
        <form method="post">
            <input type="email" name="email" placeholder="Email" required><br>
            <input type="password" name="password" placeholder="Password" required><br>
            <button type="submit">Login</button>
        </form>
        <div class="forgot-password-link">
            <p>Forgot your password? <a href="forgot_password">Reset Password</a></p>
        </div>
        <div class="register-link">
            <p>Don't have an account? <a href="register">Register</a></p>
        </div>
    </div>
    <script src="/quiz-game/assets/js/main.js?v=<?php echo time(); ?>" defer></script>
</body>
</html>