<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/quiz-game/includes/db.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/quiz-game/includes/admin_auth.php';

// Ensure only admins can access this page
checkAdminAuth();

// Fetch summary data
function getGameSummary($pdo) {
    $summary = [];

    // Total users
    $sql = "SELECT COUNT(*) AS total_users FROM users";
    $stmt = $pdo->query($sql);
    $summary['total_users'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_users'];

    // Total quiz attempts
    $sql = "SELECT COUNT(*) AS total_attempts FROM quiz_attempts";
    $stmt = $pdo->query($sql);
    $summary['total_attempts'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_attempts'];

    // Completed quiz attempts
    $sql = "SELECT COUNT(*) AS completed_attempts FROM quiz_attempts WHERE status = 'completed'";
    $stmt = $pdo->query($sql);
    $summary['completed_attempts'] = $stmt->fetch(PDO::FETCH_ASSOC)['completed_attempts'];

    // Total coins awarded
    $sql = "SELECT SUM(coins_awarded) AS total_coins FROM user_level_coins";
    $stmt = $pdo->query($sql);
    $summary['total_coins'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_coins'] ?? 0;

    // Total withdrawal requests
    $sql = "SELECT COUNT(*) AS total_withdrawals FROM withdrawals";
    $stmt = $pdo->query($sql);
    $summary['total_withdrawals'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_withdrawals'];

    // Pending withdrawal requests
    $sql = "SELECT COUNT(*) AS pending_withdrawals FROM withdrawals WHERE status = 'pending'";
    $stmt = $pdo->query($sql);
    $summary['pending_withdrawals'] = $stmt->fetch(PDO::FETCH_ASSOC)['pending_withdrawals'];

    // Average time spent per attempt (in hours)
    $sql = "SELECT AVG(TIMESTAMPDIFF(SECOND, start_time, end_time)) AS avg_time_spent FROM quiz_attempts WHERE end_time IS NOT NULL";
    $stmt = $pdo->query($sql);
    $summary['avg_time_spent'] = number_format($stmt->fetch(PDO::FETCH_ASSOC)['avg_time_spent'] / 3600, 2);

    return $summary;
}

$summary = getGameSummary($pdo);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="../assets/css/admin-dashboard.css">

</head>
<body>
    <div class="dashboard-container">
        <h1>Admin Dashboard</h1>
        <div class="summary-cards">
            <div class="card">
                <h3>Total Users</h3>
                <p><?php echo htmlspecialchars($summary['total_users']); ?></p>
            </div>
            <div class="card">
                <h3>Total Quiz Attempts</h3>
                <p><?php echo htmlspecialchars($summary['total_attempts']); ?></p>
            </div>
            <div class="card">
                <h3>Completed Attempts</h3>
                <p><?php echo htmlspecialchars($summary['completed_attempts']); ?></p>
            </div>
            <div class="card">
                <h3>Total Coins Awarded</h3>
                <p><?php echo htmlspecialchars($summary['total_coins']); ?></p>
            </div>
            <div class="card">
                <h3>Total Withdrawal Requests</h3>
                <p><?php echo htmlspecialchars($summary['total_withdrawals']); ?></p>
            </div>
            <div class="card">
                <h3>Pending Withdrawals</h3>
                <p><?php echo htmlspecialchars($summary['pending_withdrawals']); ?></p>
            </div>
            <div class="card">
                <h3>Avg Time Spent (Hours)</h3>
                <p><?php echo htmlspecialchars($summary['avg_time_spent']); ?></p>
            </div>
        </div>
        <div class="nav-links">
            <a href="user-activity">View User Activity</a>
            <a href="add-question.php">Add Questions</a>
            <a href="manage-users">Manage Users</a>
            <a href="../logout.php">Logout</a>
        </div>
    </div>
</body>
</html>