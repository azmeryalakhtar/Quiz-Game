<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$basePath = '/quiz-game/';
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Log session details for debugging
error_log("auth.php: session_id=" . session_id() . ", user_id=" . ($_SESSION['user_id'] ?? 'null') . ", request_uri=$requestUri");

// Skip authentication for login, register, forgot_password, reset_password, verify, and admin routes
$publicRoutes = [
    $basePath . 'login',
    $basePath . 'register',
    $basePath . 'forgot_password',
    $basePath . 'reset_password',
    $basePath . 'verify'
];

$normalizedRequestUri = rtrim($requestUri, '/');

if (!isset($_SESSION['user_id']) && !in_array($normalizedRequestUri, $publicRoutes) && strpos($requestUri, $basePath . 'admin/') !== 0) {
    if (strpos($requestUri, $basePath . 'api/') === 0) {
        // For API requests, return JSON error
        header('Content-Type: application/json');
        http_response_code(401);
        echo json_encode(["status" => "error", "message" => "Unauthorized: Please log in."]);
        error_log("auth.php: Unauthorized API request, returning 401 JSON response");
        exit;
    } else {
        // For page requests, redirect to login
        header("Location: {$basePath}login");
        error_log("auth.php: Redirecting to {$basePath}login");
        exit;
    }
}
?>