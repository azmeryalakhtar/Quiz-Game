<?php
function getUnlockedLevels($pdo, $userId, $category, $difficulty) {
    // Validate category exists
    $stmt = $pdo->prepare("SELECT 1 FROM categories WHERE id = ?");
    $stmt->execute([$category]);
    if (!$stmt->fetch()) {
        error_log("Invalid category_id=$category in getUnlockedLevels");
        return [1]; // Only level 1 if category is invalid
    }

    // Fetch completed levels from user_progress
    $stmt = $pdo->prepare("
        SELECT level_id
        FROM user_progress
        WHERE user_id = ? AND category_id = ? AND difficulty = ?
        ORDER BY level_id ASC
    ");
    $stmt->execute([$userId, $category, $difficulty]);
    $passedLevels = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);

    $unlockedLevels = [1]; // Always unlock Level 1

    // If passed level 1, unlock 2; if passed 2, unlock 3, etc.
    foreach ($passedLevels as $level) {
        $level = (int)$level;
        if ($level >= 1 && $level <= 3) {
            $unlockedLevels[] = $level + 1;
        }
        $unlockedLevels[] = $level; // Include the passed level
    }

    $unlockedLevels = array_values(array_unique($unlockedLevels));
    error_log("getUnlockedLevels: user_id=$userId, category=$category, difficulty=$difficulty, unlocked_levels=" . json_encode($unlockedLevels));
    return $unlockedLevels;
}

function canAccessLevel($pdo, $userId, $category, $difficulty, $level) {
    // Validate category exists
    $stmt = $pdo->prepare("SELECT 1 FROM categories WHERE id = ?");
    $stmt->execute([$category]);
    if (!$stmt->fetch()) {
        error_log("Invalid category_id=$category in canAccessLevel");
        return false; // Invalid category
    }

    if ($level == 1) {
        error_log("canAccessLevel: user_id=$userId, category=$category, difficulty=$difficulty, level=$level, access=true (Level 1)");
        return true; // Level 1 is always accessible
    }

    // Check if the user has completed the previous level in user_progress
    $prevLevel = $level - 1;
    $stmt = $pdo->prepare("
        SELECT 1
        FROM user_progress
        WHERE user_id = ? AND category_id = ? AND difficulty = ? AND level_id = ?
    ");
    $stmt->execute([$userId, $category, $difficulty, $prevLevel]);
    $canAccess = $stmt->fetch() !== false;
    error_log("canAccessLevel: user_id=$userId, category=$category, difficulty=$difficulty, level=$level, prevLevel=$prevLevel, canAccess=$canAccess");
    return $canAccess;
}

function getAvailableLevels($pdo, $category, $difficulty) {
    $stmt = $pdo->prepare("
        SELECT DISTINCT level 
        FROM questions 
        WHERE category_id = ? AND difficulty = ?
        ORDER BY level ASC
    ");
    $stmt->execute([$category, $difficulty]);
    $levels = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
    error_log("getAvailableLevels: category=$category, difficulty=$difficulty, levels=" . json_encode($levels));
    return $levels;
}

function isAdmin($pdo, $userId) {
    $stmt = $pdo->prepare("SELECT is_admin FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $isAdmin = $user && $user['is_admin'] == 1;
    error_log("isAdmin: user_id=$userId, is_admin=" . ($isAdmin ? 'true' : 'false'));
    return $isAdmin;
}
?>