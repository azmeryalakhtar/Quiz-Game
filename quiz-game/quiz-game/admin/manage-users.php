<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/quiz-game/includes/db.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/quiz-game/includes/admin_auth.php';
// Prevent caching
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');

// Ensure only admins can access this page
checkAdminAuth($pdo);

// Fetch user activity data
function getUserActivity($pdo) {
    $sql = "
        SELECT 
            u.id AS user_id,
            u.username,
            u.name,
            u.phone,
            u.created_at,
            u.coins AS total_coins,
            COUNT(DISTINCT w.id) AS withdrawal_requests
        FROM users u
        LEFT JOIN withdrawals w ON u.id = w.user_id
        GROUP BY u.id
        ORDER BY u.id DESC
    ";
    try {
        $stmt = $pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        echo "Error fetching user activity: " . htmlspecialchars($e->getMessage());
        return [];
    }
}

// Check withdrawal eligibility
function isEligibleForWithdrawal($user_id, $pdo) {
    $sql = "
        SELECT 
            COUNT(DISTINCT qa.attempt_id) AS total_attempts,
            SUM(ul.coins_awarded) AS total_coins_earned
        FROM quiz_attempts qa
        LEFT JOIN user_level_coins ul ON qa.user_id = ul.user_id
        WHERE qa.user_id = :user_id
        AND qa.status = 'completed'
    ";
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['user_id' => $user_id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        return ($data['total_attempts'] >= 3 && $data['total_coins_earned'] >= 100);
    } catch (PDOException $e) {
        echo "Error checking withdrawal eligibility: " . htmlspecialchars($e->getMessage());
        return false;
    }
}

$users = getUserActivity($pdo);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Manage Users</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/manage-users.css">
</head>
<body>
    <div class="dashboard-container">
        <h1>Manage Users</h1>
        <div class="table-wrapper">
            <table class="activity-table">
                <thead>
                    <tr>
                        <th data-label="User ID">User ID</th>
                        <th data-label="Username">Username</th>
                        <th data-label="Name" class="hide-mobile">Name</th>
                        <th data-label="Phone" class="hide-mobile">Phone</th>
                        <th data-label="Created At" class="hide-mobile">Created At</th>
                        <th data-label="Total Coins">Total Coins</th>
                        <th data-label="Withdrawal Requests">Withdrawal Requests</th>
                        <th data-label="Eligible">Eligible for Withdrawal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td data-label="User ID"><?php echo htmlspecialchars($user['user_id']); ?></td>
                            <td data-label="Username"><?php echo htmlspecialchars($user['username']); ?></td>
                            <td data-label="Name" class="hide-mobile"><?php echo htmlspecialchars($user['name'] ?? 'N/A'); ?></td>
                            <td data-label="Phone" class="hide-mobile"><?php echo htmlspecialchars($user['phone'] ?? 'N/A'); ?></td>
                            <td data-label="Created At" class="hide-mobile"><?php echo htmlspecialchars($user['created_at'] ?? 'N/A'); ?></td>
                            <td data-label="Total Coins"><?php echo htmlspecialchars($user['total_coins']); ?></td>
                            <td data-label="Withdrawal Requests"><?php echo htmlspecialchars($user['withdrawal_requests']); ?></td>
                            <td data-label="Eligible" class="<?php echo isEligibleForWithdrawal($user['user_id'], $pdo) ? 'eligible' : 'not-eligible'; ?>">
                                <?php echo isEligibleForWithdrawal($user['user_id'], $pdo) ? 'Eligible' : 'Not Eligible'; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>