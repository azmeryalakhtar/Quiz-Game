<?php
require_once __DIR__ . '/includes/db.php';
session_start();

$name = trim($_POST['name']);
$phone = trim($_POST['phone']);
$username = trim($_POST['username']);
$email = trim($_POST['email']);
$password = password_hash($_POST['password'], PASSWORD_DEFAULT);
$verification_token = bin2hex(random_bytes(16));

// Server-side phone validation
if (!preg_match('/^03[0-9]{9}$/', $phone)) {
    $error = "Phone number must start with 03 and be 11 digits long.";
} else {
    try {
        // Check if email already exists
        $checkStmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $checkStmt->execute([$email]);

        if ($checkStmt->rowCount() > 0) {
            $error = "Email already registered.";
        } else {
            // Register the new user with verification token
            $stmt = $pdo->prepare("INSERT INTO users (name, phone, username, email, password, verification_token) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $phone, $username, $email, $password, $verification_token]);

            // Send verification email
            $subject = "Verify Your Email Address";
            $verify_link = "https://games.phonesdukan.com/quiz-game/verify?token=" . $verification_token;
            $message = "Hello $username,\n\nPlease verify your email by clicking the link below:\n$verify_link\n\nThank you!";
            $headers = "From: no-reply@games.phonesdukan.com\r\n";

            if (mail($email, $subject, $message, $headers)) {
                $success = "Registration successful! Please check your email 📩 to verify your account.";
            } else {
                $error = "Failed to send verification email. Please try again later.";
            }
        }
    } catch (PDOException $e) {
        $error = "Registration error: " . $e->getMessage();
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Register</title>
    <link rel="stylesheet" href="/quiz-game/assets/css/register.css"/>
</head>
<body>
<canvas id="stars-canvas"></canvas>
    <div class="register-container">
        <h2>Register</h2>
        <?php if (!empty($error)): ?>
            <p class="error-message"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>
        <?php if (!empty($success)): ?>
            <p class="success-message"><?= htmlspecialchars($success) ?></p>
        <?php endif; ?>
        <form method="post">
    <input type="text" name="name" placeholder="Full Name" required><br><br>
    <input type="text" name="phone" placeholder="03XXXXXXXXX" maxlength="11" pattern="03[0-9]{9}" required>
    <br><br>
    <input type="text" name="username" placeholder="Username" required><br><br>
    <input type="email" name="email" placeholder="Email" required><br><br>
    <input type="password" name="password" placeholder="Password" required><br><br>
    <button type="submit">Register</button>
</form>

        <div class="login-link">
            <p>Already have an account? <a href="login">Login</a></p>
        </div>
    </div>
    <script src="/quiz-game/assets/js/main.js?v=<?php echo time(); ?>" defer></script>
</body>
</html>