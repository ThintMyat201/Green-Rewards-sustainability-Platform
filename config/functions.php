<?php
// Helper Functions

// Get the app base path when the project is served from a subdirectory.
function getBasePath() {
    static $basePath = null;

    if ($basePath !== null) {
        return $basePath;
    }

    $projectRoot = str_replace('\\', '/', dirname(__DIR__));
    $documentRoot = isset($_SERVER['DOCUMENT_ROOT']) ? str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']) : '';

    $projectRoot = rtrim($projectRoot, '/');
    $documentRoot = rtrim($documentRoot, '/');

    if ($documentRoot !== '' && strpos($projectRoot, $documentRoot) === 0) {
        $relativePath = trim(substr($projectRoot, strlen($documentRoot)), '/');
        $basePath = $relativePath === '' ? '' : '/' . $relativePath;
        return $basePath;
    }

    $basePath = '';
    return $basePath;
}

// Build a URL that works whether the app is installed at the web root or in a subdirectory.
function appUrl($path = '/') {
    if ($path === '' || $path === null) {
        $path = '/';
    }

    if (preg_match('#^(?:[a-z][a-z0-9+.-]*:)?//#i', $path)) {
        return $path;
    }

    $basePath = getBasePath();

    if ($path === '/') {
        return $basePath !== '' ? $basePath . '/' : '/';
    }

    return $basePath . '/' . ltrim($path, '/');
}

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Get current user data with static memoization
function getCurrentUser($forceRefresh = false) {
    global $pdo;
    if (!isLoggedIn()) return null;
    
    static $cachedUser = null;
    static $cachedUserId = null;
    
    if (!$forceRefresh && $cachedUser !== null && $cachedUserId === $_SESSION['user_id']) {
        return $cachedUser;
    }
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $cachedUser = $stmt->fetch();
    $cachedUserId = $_SESSION['user_id'];
    return $cachedUser;
}

// Check if user has specific role
function hasRole($role) {
    if (!isLoggedIn()) return false;
    return $_SESSION['role'] === $role;
}

// Check whether a role participates in points earning.
function roleEarnsPoints($role) {
    return in_array($role, ['student', 'staff'], true);
}

// Redirect with message
function redirect($url, $message = '', $type = 'info') {
    if ($message) {
        $_SESSION['flash_message'] = $message;
        $_SESSION['flash_type'] = $type;
    }
    header('Location: ' . appUrl($url));
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

// Only allow APU email accounts for this platform.
function isValidApuEmail($email) {
    $email = trim((string) $email);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    return preg_match('/@mail\.apu\.edu\.my$/i', $email) === 1;
}

// Format date
function formatDate($date) {
    if (empty($date)) {
        return '-';
    }
    return date('M d, Y', strtotime($date));
}

// Get setting value with static memoization
function getSetting($key, $default = '', $forceRefresh = false) {
    global $pdo;
    static $settingsCache = [];
    
    if (!$forceRefresh && isset($settingsCache[$key])) {
        return $settingsCache[$key];
    }
    
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $result = $stmt->fetch();
    $val = $result ? $result['setting_value'] : $default;
    $settingsCache[$key] = $val;
    return $val;
}

// Update setting
function updateSetting($key, $value, $userId = null) {
    global $pdo;
    $stmt = $pdo->prepare(
        "UPDATE settings SET setting_value = ?, updated_by = ?, updated_at = NOW() 
         WHERE setting_key = ?"
    );
    $res = $stmt->execute([$value, $userId, $key]);
    if ($res) {
        getSetting($key, '', true); // invalidate cache
    }
    return $res;
}

function getDefaultDepartmentOptions() {
    return ['Computer Science', 'Engineering', 'Business'];
}

function getDepartmentOptions($activeOnly = true) {
    global $pdo;
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    try {
        $sql = "SELECT name FROM departments ORDER BY name ASC";
        $rows = $pdo->query($sql)->fetchAll();
        if (!$rows) {
            return getDefaultDepartmentOptions();
        }

        $options = [];
        foreach ($rows as $row) {
            $options[] = $row['name'];
        }

        $cache = $options;
        return $options;
    } catch (Throwable $e) {
        return getDefaultDepartmentOptions();
    }
}

function isValidDepartment($department, $activeOnly = true) {
    if ($department === null || $department === '') {
        return false;
    }

    return in_array($department, getDepartmentOptions($activeOnly), true);
}

// Add points to user with database transaction
function addPoints($userId, $points, $source, $description = '') {
    global $pdo;

    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $targetUser = $stmt->fetch();

    if (!$targetUser || !roleEarnsPoints($targetUser['role'])) {
        return false;
    }
    
    try {
        $pdo->beginTransaction();
        
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
        $res = $stmt->execute([$points, $userId]);
        
        $pdo->commit();
        
        if ($res && isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] === (int)$userId) {
            getCurrentUser(true); // invalidate/refresh cached user
        }
        
        return $res;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return false;
    }
}

// Check and unlock achievements without N+1 queries
function checkAchievements($userId) {
    global $pdo;
    
    // Get user data
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if (!$user) {
        return;
    }
    
    // Get unlockable achievements using prepared statement
    $stmt = $pdo->prepare(
        "SELECT a.* FROM achievements a 
         WHERE a.id NOT IN (
             SELECT achievement_id FROM user_achievements WHERE user_id = ?
         )"
    );
    $stmt->execute([$userId]);
    $achievements = $stmt->fetchAll();
    
    if (!$achievements) {
        return;
    }
    
    // Pre-calculate user activity summaries once ($O(1) database trips)
    $stmtQuiz = $pdo->prepare("SELECT COUNT(*) as cnt FROM quiz_attempts WHERE user_id = ? AND is_correct = 1");
    $stmtQuiz->execute([$userId]);
    $quizCount = (int) $stmtQuiz->fetch()['cnt'];
    
    $stmtChallenge = $pdo->prepare("SELECT COUNT(*) as cnt FROM challenge_submissions WHERE user_id = ? AND status = 'approved'");
    $stmtChallenge->execute([$userId]);
    $challengeCount = (int) $stmtChallenge->fetch()['cnt'];
    
    $stmtElec = $pdo->prepare("SELECT COUNT(*) as cnt FROM electricity_logs WHERE user_id = ? AND units_used < benchmark");
    $stmtElec->execute([$userId]);
    $elecCount = (int) $stmtElec->fetch()['cnt'];
    
    $insertUnlock = $pdo->prepare("INSERT IGNORE INTO user_achievements (user_id, achievement_id) VALUES (?, ?)");
    
    foreach ($achievements as $achievement) {
        $unlock = false;
        
        switch ($achievement['condition_type']) {
            case 'points_total':
                $unlock = $user['points_total'] >= $achievement['condition_value'];
                break;
            case 'quiz_streak':
                $unlock = $quizCount >= $achievement['condition_value'];
                break;
            case 'challenges_completed':
                $unlock = $challengeCount >= $achievement['condition_value'];
                break;
            case 'electricity_saves':
                $unlock = $elecCount >= $achievement['condition_value'];
                break;
            case 'first_action':
                $unlock = true;
                break;
        }
        
        if ($unlock) {
            $insertUnlock->execute([$userId, $achievement['id']]);
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
    
    $uploadDir = __DIR__ . '/../uploads';
    
    // Create uploads directory if it doesn't exist
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            return ['success' => false, 'error' => 'Failed to create uploads directory'];
        }
    }
    
    $newFilename = $prefix . '_' . uniqid() . '.' . $fileExt;
    $uploadPath = $uploadDir . '/' . $newFilename;
    
    if (move_uploaded_file($fileTmp, $uploadPath)) {
        return ['success' => true, 'filename' => 'uploads/' . $newFilename];
    }
    
    return ['success' => false, 'error' => 'Upload failed'];
}
?>
