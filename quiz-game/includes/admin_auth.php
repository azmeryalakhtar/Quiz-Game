<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect to admin login if not authenticated
function checkAdminAuth() {
    if (!isset($_SESSION['admin_id'])) {
        header('Location: /quiz-game/admin/admin_login.php');
        exit();
    }
}
?>