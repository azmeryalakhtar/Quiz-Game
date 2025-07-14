<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/quiz-game/includes/db.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/quiz-game/includes/admin_auth.php';

// Prevent caching
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');

// Ensure only admins can access this page
checkAdminAuth();

// Fetch user activity data
function getUserActivity($pdo) {
    $sql = "
        SELECT 
            u.id AS user_id,
            u.username,
            u.email,
            u.coins AS total_coins,
            COUNT(DISTINCT qa.attempt_id) AS total_attempts,
            SUM(CASE WHEN qa.status = 'completed' THEN 1 ELSE 0 END) AS completed_attempts,
            SUM(qa.correct_count) AS total_correct_answers,
            COUNT(DISTINCT w.id) AS withdrawal_requests
        FROM users u
        LEFT JOIN quiz_attempts qa ON u.id = qa.user_id
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

// Fetch user_level_coins data for a specific user
function getUserLevelCoins($pdo, $user_id) {
    $sql = "
        SELECT 
            ul.user_id,
            u.username,
            ul.category_id,
            c.name AS category_name,
            ul.difficulty,
            ul.level_id,
            ul.coins_awarded,
            ul.awarded_at
        FROM user_level_coins ul
        JOIN users u ON ul.user_id = u.id
        JOIN categories c ON ul.category_id = c.id
        WHERE ul.user_id = :user_id
        ORDER BY ul.level_id, ul.awarded_at
    ";
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['user_id' => $user_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        echo "Error fetching user level coins for user $user_id: " . htmlspecialchars($e->getMessage());
        return [];
    }
}

// Fetch user_progress data for a specific user
function getUserProgress($pdo, $user_id) {
    $sql = "
        SELECT 
            up.user_id,
            u.username,
            up.category_id,
            c.name AS category_name,
            up.difficulty,
            up.level_id,
            up.completed_at
        FROM user_progress up
        JOIN users u ON up.user_id = u.id
        JOIN categories c ON up.category_id = c.id
        WHERE up.user_id = :user_id
        ORDER BY up.level_id, up.completed_at
    ";
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['user_id' => $user_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        echo "Error fetching user progress for user $user_id: " . htmlspecialchars($e->getMessage());
        return [];
    }
}

// Fetch detailed question attempt data for a specific user with total time spent
function getUserQuestionDetails($pdo, $user_id, $filter = 'all') {
    $sql = "
        WITH QuestionTimes AS (
            SELECT 
                qa.user_id,
                qa.attempt_id,
                q.id AS question_id,
                q.question_text,
                q.correct_answer,
                qat.selected_answer,
                qat.is_correct,
                qat.status AS question_status,
                qa.level_id,
                q.difficulty,
                c.name AS category_name,
                TIMESTAMPDIFF(MICROSECOND, qat.start_time, qat.end_time) / 1000000 AS time_taken_seconds,
                TIMESTAMPDIFF(MICROSECOND, qat.start_time, 
                    LEAD(qat.start_time) OVER (PARTITION BY qa.attempt_id ORDER BY qat.start_time)) / 1000000 AS total_time_to_next_question
            FROM question_attempts qat
            JOIN quiz_attempts qa ON qat.attempt_id = qa.attempt_id
            JOIN questions q ON qat.question_id = q.id
            JOIN categories c ON q.category_id = c.id
            WHERE qa.user_id = :user_id
        ),
        TotalTime AS (
            SELECT 
                user_id,
                SUM(CASE WHEN total_time_to_next_question IS NOT NULL 
                         THEN total_time_to_next_question ELSE 0 END) AS total_time_spent_seconds
            FROM QuestionTimes
            GROUP BY user_id
        )
        SELECT 
            qt.*,
            tt.total_time_spent_seconds
        FROM QuestionTimes qt
        LEFT JOIN TotalTime tt ON qt.user_id = tt.user_id
    ";
    if ($filter === 'suspicious') {
        $sql .= " WHERE qt.total_time_to_next_question < 10 AND qt.total_time_to_next_question IS NOT NULL";
    }
    $sql .= " ORDER BY qt.attempt_id, qt.question_id";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['user_id' => $user_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        echo "Error fetching question details for user $user_id: " . htmlspecialchars($e->getMessage());
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

// Flag suspicious activity
function isSuspicious($total_time_to_next_question) {
    return ($total_time_to_next_question !== null && $total_time_to_next_question < 10);
}

// Fetch withdrawal details for a specific user
function getWithdrawalDetails($pdo, $user_id) {
    $sql = "
        SELECT id, amount, status, created_at
        FROM withdrawals
        WHERE user_id = :user_id AND status = 'pending'
        ORDER BY created_at
    ";
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['user_id' => $user_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        echo "Error fetching withdrawal details for user $user_id: " . htmlspecialchars($e->getMessage());
        return [];
    }
}

$users = getUserActivity($pdo);
$filter = isset($_GET['filter']) && $_GET['filter'] === 'suspicious' ? 'suspicious' : 'all';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - User Activity</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/manage-users.css">
</head>
<body>
    <div class="dashboard-container">
        <h1>User Activity and Withdrawal Eligibility</h1>
        <div class="filter-container">
            <form method="GET">
                <label for="filter">Filter: </label>
                <select name="filter" onchange="this.form.submit()">
                    <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>All Activities</option>
                    <option value="suspicious" <?php echo $filter === 'suspicious' ? 'selected' : ''; ?>>Suspicious Activities</option>
                </select>
            </form>
        </div>
        <div class="table-wrapper">
            <table class="activity-table">
                <thead>
                    <tr>
                        <th data-label="User ID">User ID</th>
                        <th data-label="Username">Username</th>
                        <th data-label="Email" class="hide-mobile">Email</th>
                        <th data-label="Total Coins">Total Coins</th>
                        <th data-label="Total Attempts">Total Attempts</th>
                        <th data-label="Completed Attempts">Completed Attempts</th>
                        <th data-label="Correct Answers" class="hide-mobile">Correct Answers</th>
                        <th data-label="Withdrawal Requests">Withdrawal Requests</th>
                        <th data-label="Eligible">Eligible for Withdrawal</th>
                        <th data-label="Details">Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td data-label="User ID"><?php echo htmlspecialchars($user['user_id']); ?></td>
                            <td data-label="Username"><?php echo htmlspecialchars($user['username']); ?></td>
                            <td data-label="Email" class="hide-mobile"><?php echo htmlspecialchars($user['email']); ?></td>
                            <td data-label="Total Coins"><?php echo htmlspecialchars($user['total_coins']); ?></td>
                            <td data-label="Total Attempts"><?php echo htmlspecialchars($user['total_attempts']); ?></td>
                            <td data-label="Completed Attempts"><?php echo htmlspecialchars($user['completed_attempts']); ?></td>
                            <td data-label="Correct Answers" class="hide-mobile"><?php echo htmlspecialchars($user['total_correct_answers'] ?? 0); ?></td>
                            <td data-label="Withdrawal Requests"><?php echo htmlspecialchars($user['withdrawal_requests']); ?></td>
                            <td data-label="Eligible" class="<?php echo isEligibleForWithdrawal($user['user_id'], $pdo) ? 'eligible' : 'not-eligible'; ?>">
                                <?php echo isEligibleForWithdrawal($user['user_id'], $pdo) ? 'Eligible' : 'Not Eligible'; ?>
                            </td>
                            <td data-label="Details"><span class="toggle-details" onclick="toggleDetails(<?php echo $user['user_id']; ?>)">View Details</span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php foreach ($users as $user): ?>
            <div class="details-section" id="details-section-<?php echo $user['user_id']; ?>" style="display: none;">
                <h2>Details for <?php echo htmlspecialchars($user['username']); ?> (User ID: <?php echo $user['user_id']; ?>)</h2>
                <button class="close-details" onclick="toggleDetails(<?php echo $user['user_id']; ?>)">Close Details</button>
                
                <?php if ($user['user_id'] == 2): ?>
                    <h3>Pending Withdrawals</h3>
                    <?php
                    $withdrawals = getWithdrawalDetails($pdo, 2);
                    if (empty($withdrawals)) {
                        echo '<p class="error">No pending withdrawals found for User ID 2.</p>';
                    } else {
                        echo '<div class="table-wrapper">';
                        echo '<table class="withdrawal-table">';
                        echo '<thead><tr><th data-label="ID">ID</th><th data-label="Amount">Amount</th><th data-label="Status">Status</th><th data-label="Created At">Created At</th></tr></thead>';
                        echo '<tbody>';
                        $total_withdrawal = 0;
                        foreach ($withdrawals as $withdrawal) {
                            echo '<tr>';
                            echo '<td data-label="ID">' . htmlspecialchars($withdrawal['id']) . '</td>';
                            echo '<td data-label="Amount">' . number_format($withdrawal['amount'], 2) . ' PKR</td>';
                            echo '<td data-label="Status">' . htmlspecialchars($withdrawal['status']) . '</td>';
                            echo '<td data-label="Created At">' . htmlspecialchars($withdrawal['created_at']) . '</td>';
                            echo '</tr>';
                            $total_withdrawal += $withdrawal['amount'];
                        }
                        echo '<tr class="summary-row"><td colspan="3" data-label="Total">Total</td><td data-label="Total Amount">' . number_format($total_withdrawal, 2) . ' PKR</td></tr>';
                        echo '</tbody>';
                        echo '</table>';
                        echo '</div>';
                    }
                    ?>
                <?php endif; ?>

                <h3>User Level Coins</h3>
                <?php
                $user_level_coins = getUserLevelCoins($pdo, $user['user_id']);
                if (empty($user_level_coins)) {
                    echo '<p class="error">No user level coins data available for this user.</p>';
                } else {
                    $total_coins = array_sum(array_column($user_level_coins, 'coins_awarded'));
                ?>
                    <div class="table-wrapper">
                        <table class="coins-table">
                            <thead>
                                <tr>
                                    <th data-label="User ID">User ID</th>
                                    <th data-label="Username">Username</th>
                                    <th data-label="Category">Category</th>
                                    <th data-label="Difficulty" class="hide-mobile">Difficulty</th>
                                    <th data-label="Level ID">Level ID</th>
                                    <th data-label="Coins Awarded">Coins Awarded</th>
                                    <th data-label="Awarded At" class="hide-mobile">Awarded At</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr class="summary-row">
                                    <td data-label="User ID">N/A</td>
                                    <td data-label="Username">N/A</td>
                                    <td data-label="Category">N/A</td>
                                    <td data-label="Difficulty" class="hide-mobile">N/A</td>
                                    <td data-label="Level ID">N/A</td>
                                    <td data-label="Coins Awarded"><?php echo htmlspecialchars($total_coins); ?></td>
                                    <td data-label="Awarded At" class="hide-mobile">N/A</td>
                                </tr>
                                <?php foreach ($user_level_coins as $coin): ?>
                                    <tr>
                                        <td data-label="User ID"><?php echo htmlspecialchars($coin['user_id']); ?></td>
                                        <td data-label="Username"><?php echo htmlspecialchars($coin['username']); ?></td>
                                        <td data-label="Category"><?php echo htmlspecialchars($coin['category_name']); ?></td>
                                        <td data-label="Difficulty" class="hide-mobile"><?php echo htmlspecialchars($coin['difficulty']); ?></td>
                                        <td data-label="Level ID"><?php echo htmlspecialchars($coin['level_id']); ?></td>
                                        <td data-label="Coins Awarded"><?php echo htmlspecialchars($coin['coins_awarded']); ?></td>
                                        <td data-label="Awarded At" class="hide-mobile"><?php echo htmlspecialchars($coin['awarded_at']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php } ?>

                <h3>User Progress</h3>
                <?php
                $user_progress = getUserProgress($pdo, $user['user_id']);
                if (empty($user_progress)) {
                    echo '<p class="error">No user progress data available for this user.</p>';
                } else {
                ?>
                    <div class="table-wrapper">
                        <table class="progress-table">
                            <thead>
                                <tr>
                                    <th data-label="User ID">User ID</th>
                                    <th data-label="Username">Username</th>
                                    <th data-label="Category">Category</th>
                                    <th data-label="Difficulty" class="hide-mobile">Difficulty</th>
                                    <th data-label="Level ID">Level ID</th>
                                    <th data-label="Completed At" class="hide-mobile">Completed At</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($user_progress as $progress): ?>
                                    <tr>
                                        <td data-label="User ID"><?php echo htmlspecialchars($progress['user_id']); ?></td>
                                        <td data-label="Username"><?php echo htmlspecialchars($progress['username']); ?></td>
                                        <td data-label="Category"><?php echo htmlspecialchars($progress['category_name']); ?></td>
                                        <td data-label="Difficulty" class="hide-mobile"><?php echo htmlspecialchars($progress['difficulty']); ?></td>
                                        <td data-label="Level ID"><?php echo htmlspecialchars($progress['level_id']); ?></td>
                                        <td data-label="Completed At" class="hide-mobile"><?php echo htmlspecialchars($progress['completed_at']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php } ?>

                <h3>Question Attempts</h3>
                <?php
                $details = getUserQuestionDetails($pdo, $user['user_id'], $filter);
                if (empty($details)) {
                    echo '<p class="error">No question attempt details available for this user' . ($filter === 'suspicious' ? ' (with suspicious activity)' : '') . '.</p>';
                } else {
                    $total_time_spent = $details[0]['total_time_spent_seconds'] ?? 0;
                ?>
                    <p class="total-time">Total Time Spent: <?php echo number_format($total_time_spent / 60, 5) . 'M'; ?></p>
                    <div class="table-wrapper">
                        <table class="details-table">
                            <thead>
                                <tr>
                                    <th data-label="Attempt ID">Attempt ID</th>
                                    <th data-label="Level ID">Level ID</th>
                                    <th data-label="Difficulty" class="hide-mobile">Difficulty</th>
                                    <th data-label="Category">Category</th>
                                    <th data-label="Question ID">Question ID</th>
                                    <th data-label="Question Text" class="hide-mobile">Question Text</th>
                                    <th data-label="Correct Answer" class="hide-mobile">Correct Answer</th>
                                    <th data-label="Selected Answer" class="hide-mobile">Selected Answer</th>
                                    <th data-label="Correct?">Correct?</th>
                                    <th data-label="Time Taken" class="hide-mobile">Time Taken (Seconds)</th>
                                    <th data-label="Time to Next">Time to Next Question (Seconds)</th>
                                    <th data-label="Status">Status</th>
                                    <th data-label="Suspicious?">Suspicious?</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($details as $detail): ?>
                                    <tr>
                                        <td data-label="Attempt ID"><?php echo htmlspecialchars($detail['attempt_id']); ?></td>
                                        <td data-label="Level ID"><?php echo htmlspecialchars($detail['level_id']); ?></td>
                                        <td data-label="Difficulty" class="hide-mobile"><?php echo htmlspecialchars($detail['difficulty'] ?? 'N/A'); ?></td>
                                        <td data-label="Category"><?php echo htmlspecialchars($detail['category_name']); ?></td>
                                        <td data-label="Question ID"><?php echo htmlspecialchars($detail['question_id']); ?></td>
                                        <td data-label="Question Text" class="hide-mobile"><?php echo htmlspecialchars($detail['question_text'] ?? 'N/A'); ?></td>
                                        <td data-label="Correct Answer" class="hide-mobile"><?php echo htmlspecialchars($detail['correct_answer'] ?? 'N/A'); ?></td>
                                        <td data-label="Selected Answer" class="hide-mobile"><?php echo htmlspecialchars($detail['selected_answer'] ?? 'N/A'); ?></td>
                                        <td data-label="Correct?" class="<?php echo $detail['is_correct'] ? 'correct' : 'incorrect'; ?>">
                                            <?php echo $detail['is_correct'] ? 'Yes' : 'No'; ?>
                                        </td>
                                        <td data-label="Time Taken" class="hide-mobile"><?php echo number_format($detail['time_taken_seconds'] ?? 0, 2); ?></td>
                                        <td data-label="Time to Next" class="<?php echo ($detail['total_time_to_next_question'] !== null && $detail['total_time_to_next_question'] < 10) ? 'quick-transition' : ''; ?>">
                                            <?php echo $detail['total_time_to_next_question'] !== null ? number_format($detail['total_time_to_next_question'], 2) : 'N/A'; ?>
                                        </td>
                                        <td data-label="Status"><?php echo htmlspecialchars($detail['question_status']); ?></td>
                                        <td data-label="Suspicious?" class="<?php echo isSuspicious($detail['total_time_to_next_question']) ? 'suspicious' : ''; ?>">
                                            <?php echo isSuspicious($detail['total_time_to_next_question']) ? 'Yes' : 'No'; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php } ?>
            </div>
        <?php endforeach; ?>
    </div>
    <script>
        function toggleDetails(userId) {
            const details = document.getElementById('details-section-' + userId);
            if (details) {
                const isHidden = details.style.display === 'none' || details.style.display === '';
                details.style.display = isHidden ? 'block' : 'none';
                const action = isHidden ? 'Opened' : 'Closed';
                console.log(action + ' details for user ' + userId);
            } else {
                console.error('Details section not found for user ' + userId);
            }
        }
    </script>
    <style>
        .close-details {
            background-color: #dc3545;
            color: white;
            border: none;
            padding: 8px 16px;
            cursor: pointer;
            border-radius: 4px;
            margin-bottom: 10px;
        }
        .close-details:hover {
            background-color: #c82333;
        }
    </style>
</body>
</html>