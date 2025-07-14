<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once $_SERVER['DOCUMENT_ROOT'] . '/quiz-game/includes/db.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/quiz-game/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/quiz-game/includes/functions.php';

// Get the request URI and method
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$requestMethod = $_SERVER['REQUEST_METHOD'];
$basePath = '/quiz-game/';
$uri = str_replace($basePath, '', $requestUri);
$uri = trim($uri, '/');

// Remove .php extension for routing purposes
$uri = preg_replace('/\.php$/', '', $uri);

// Log the raw request for debugging
error_log("router.php: Raw REQUEST_URI=$requestUri, Normalized URI=$uri, Method=$requestMethod, POST=" . json_encode($_POST));

$routes = [
    '' => 'index.php',
    'login' => 'login.php',
    'register' => 'register.php',
    'forgot_password' => 'forgot_password.php',
    'reset_password' => 'reset_password.php',
    'verify' => 'verify.php',
    'logout' => 'logout.php',
    'check-answer' => 'check-answer.php',
    'dashboard' => 'pages/dashboard.php',
    'withdraw' => 'pages/withdraw.php',
    'quiz' => 'pages/quiz.php',
    'results' => 'pages/results.php',
    'leaderboard' => 'pages/leaderboard.php',
    'privacy-policy' => 'privacy-policy.html',

    // Admin routes
    'admin' => 'admin/index.php',
    'admin/user-activity' => 'admin/user-activity.php',
    'admin/manage-users' => 'admin/manage-users.php',
    'admin/add-question' => 'admin/add-question.php',

    // API routes
    'api/login' => 'api/login.php',
    'api/register' => 'api/register.php',
    'api/get-correct-answer' => 'api/get-correct-answer.php',
    'api/get-questions-answer' => 'api/get-questions-answer.php',
    'api/get-unlocked-levels' => 'api/get-unlocked-levels.php',
    'api/get-questions' => 'api/get-questions.php',
    'api/submit-answer' => 'api/submit-answer.php',
    'api/get-user-coins' => 'api/get-user-coins.php',
    'api/update-progress' => 'api/update-progress.php',
    'api/check-attempt' => 'api/check-attempt.php',
    'api/next-question' => 'api/next-question.php',
    'api/check-timer' => 'api/check-timer.php'
];

// Handle static assets
if (preg_match('/\.(css|js|svg|png|jpg|jpeg|gif|woff|woff2|ttf|ico)$/', $uri)) {
    error_log("router.php: Serving static asset: $uri");
    return false;
}

// Find matching route
$filePath = null;
if (array_key_exists($uri, $routes)) {
    $filePath = $_SERVER['DOCUMENT_ROOT'] . '/quiz-game/' . $routes[$uri];
} else {
    http_response_code(404);
    echo '<h1>404 Not Found</h1>';
    echo '<p>The page you are looking for does not exist.</p>';
    error_log("router.php: 404 Not Found for URI=$requestUri (Normalized: $uri)");
    ob_end_flush();
    exit;
}

// Check if file exists
if (!file_exists($filePath)) {
    http_response_code(404);
    echo '<h1>404 Not Found</h1>';
    echo '<p>The requested resource was not found on the server.</p>';
    error_log("router.php: File not found: $filePath for URI=$requestUri");
    ob_end_flush();
    exit;
}

// Log successful routing
error_log("router.php: Routing $requestUri to $filePath");

// Include the target file
require $filePath;

ob_end_flush();
?>