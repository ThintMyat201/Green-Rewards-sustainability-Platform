<?php
// Helper Functions

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Get current user data
function getCurrentUser() {
    global $pdo;
    if (!isLoggedIn()) return null;
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

// Check if user has specific role
function hasRole($role) {
    if (!isLoggedIn()) return false;
    return $_SESSION['role'] === $role;
}

// Redirect with message
function redirect($url, $message = '', $type = 'info') {
    if ($message) {
        $_SESSION['flash_message'] = $message;
        $_SESSION['flash_type'] = $type;
    }
    header("Location: $url");
    exit;
}

// Display flash message
function getFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        $type = $_SESSION['flash_type'] ?? 'info';
        unset($_SESSION['flash_message'], $_SESSION['flash_type']);
        return ['message' => $message, 'type' => $type];
    }
    return null;
}

// Sanitize input
function clean($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

// Format date
function formatDate($date) {
    return date('M d, Y', strtotime($date));
}

// Get setting value
function getSetting($key, $default = '') {
    global $pdo;
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $result = $stmt->fetch();
    return $result ? $result['setting_value'] : $default;
}

// Update setting
function updateSetting($key, $value, $userId = null) {
    global $pdo;
    $stmt = $pdo->prepare(
        "UPDATE settings SET setting_value = ?, updated_by = ?, updated_at = NOW() 
         WHERE setting_key = ?"
    );
    return $stmt->execute([$value, $userId, $key]);
}

// Add points to user
function addPoints($userId, $points, $source, $description = '') {
    global $pdo;
    
    // Add to points_log
    $stmt = $pdo->prepare(
        "INSERT INTO points_log (user_id, source, points_earned, description) 
         VALUES (?, ?, ?, ?)"
    );
    $stmt->execute([$userId, $source, $points, $description]);
    
    // Update user's total points
    $stmt = $pdo->prepare(
        "UPDATE users SET points_total = points_total + ? WHERE id = ?"
    );
    return $stmt->execute([$points, $userId]);
}

// Check and unlock achievements
function checkAchievements($userId) {
    global $pdo;
    
    // Get user data
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    // Get unlockable achievements
    $achievements = $pdo->query(
        "SELECT a.* FROM achievements a 
         WHERE a.id NOT IN (
             SELECT achievement_id FROM user_achievements WHERE user_id = $userId
         )"
    )->fetchAll();
    
    foreach ($achievements as $achievement) {
        $unlock = false;
        
        switch ($achievement['condition_type']) {
            case 'points_total':
                $unlock = $user['points_total'] >= $achievement['condition_value'];
                break;
            case 'quiz_streak':
                $count = $pdo->query(
                    "SELECT COUNT(*) as cnt FROM quiz_attempts 
                     WHERE user_id = $userId AND is_correct = 1"
                )->fetch()['cnt'];
                $unlock = $count >= $achievement['condition_value'];
                break;
            case 'challenges_completed':
                $count = $pdo->query(
                    "SELECT COUNT(*) as cnt FROM challenge_submissions 
                     WHERE user_id = $userId AND status = 'approved'"
                )->fetch()['cnt'];
                $unlock = $count >= $achievement['condition_value'];
                break;
            case 'electricity_saves':
                $count = $pdo->query(
                    "SELECT COUNT(*) as cnt FROM electricity_logs 
                     WHERE user_id = $userId AND units_used < benchmark"
                )->fetch()['cnt'];
                $unlock = $count >= $achievement['condition_value'];
                break;
        }
        
        if ($unlock) {
            $stmt = $pdo->prepare(
                "INSERT INTO user_achievements (user_id, achievement_id) VALUES (?, ?)"
            );
            $stmt->execute([$userId, $achievement['id']]);
        }
    }
}

// Upload file helper
function uploadFile($file, $prefix = 'upload') {
    $allowed = ['jpg', 'jpeg', 'png', 'gif'];
    $filename = $file['name'];
    $fileTmp = $file['tmp_name'];
    $fileSize = $file['size'];
    $fileExt = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    
    if (!in_array($fileExt, $allowed)) {
        return ['success' => false, 'error' => 'Invalid file type'];
    }
    
    if ($fileSize > 5000000) { // 5MB
        return ['success' => false, 'error' => 'File too large'];
    }
    
    $newFilename = $prefix . '_' . uniqid() . '.' . $fileExt;
    $uploadPath = __DIR__ . '/../uploads/' . $newFilename;
    
    if (move_uploaded_file($fileTmp, $uploadPath)) {
        return ['success' => true, 'filename' => 'uploads/' . $newFilename];
    }
    
    return ['success' => false, 'error' => 'Upload failed'];
}
?>