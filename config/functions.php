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

function getDefaultDepartmentOptions() {
    return ['Computer Science', 'Engineering', 'Business'];
}

function migrateUsersDepartmentToVarchar() {
    global $pdo;
    static $done = false;

    if ($done) {
        return true;
    }

    try {
        $stmt = $pdo->query("SELECT DATABASE() AS db_name");
        $dbName = (string) ($stmt->fetch()['db_name'] ?? '');

        if ($dbName === '') {
            return false;
        }

        $stmt = $pdo->prepare(
            "SELECT DATA_TYPE
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'users' AND COLUMN_NAME = 'department'
             LIMIT 1"
        );
        $stmt->execute([$dbName]);
        $column = $stmt->fetch();

        if (!$column) {
            return false;
        }

        if (strtolower((string) ($column['DATA_TYPE'] ?? '')) === 'enum') {
            $pdo->exec("ALTER TABLE users MODIFY department VARCHAR(100) NULL");
        }

        $done = true;
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

function ensureDepartmentsTable() {
    global $pdo;
    static $ready = null;

    if ($ready === true) {
        return true;
    }

    try {
        // Create table if it doesn't exist
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS departments (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL UNIQUE,
                code VARCHAR(20),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_name (name)
            ) ENGINE=InnoDB"
        );

        // Check if code column exists; if not, add it
        $stmt = $pdo->prepare(
            "SELECT COLUMN_NAME FROM information_schema.COLUMNS 
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'departments' AND COLUMN_NAME = 'code'"
        );
        $stmt->execute();
        
        if (!$stmt->fetch()) {
            // Code column doesn't exist, add it
            $pdo->exec("ALTER TABLE departments ADD COLUMN code VARCHAR(20) UNIQUE AFTER name");
        }

        // Make code column NOT NULL with unique constraint if it's not already
        $stmt = $pdo->prepare(
            "SELECT IS_NULLABLE FROM information_schema.COLUMNS 
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'departments' AND COLUMN_NAME = 'code'"
        );
        $stmt->execute();
        $column = $stmt->fetch();
        
        if ($column && $column['IS_NULLABLE'] === 'YES') {
            $pdo->exec("UPDATE departments SET code = CONCAT('DEPT_', id) WHERE code IS NULL");
            $pdo->exec("ALTER TABLE departments MODIFY code VARCHAR(20) NOT NULL UNIQUE");
        }

        // Seed default departments if they don't exist
        $insert = $pdo->prepare("INSERT IGNORE INTO departments (name, code) VALUES (?, ?)");
        $defaultDepts = [
            'Computer Science' => 'CS',
            'Engineering' => 'ENG',
            'Business' => 'BUS'
        ];
        foreach ($defaultDepts as $deptName => $deptCode) {
            $insert->execute([$deptName, $deptCode]);
        }

        $ready = true;
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

function getDepartmentOptions($activeOnly = true) {
    global $pdo;

    if (!ensureDepartmentsTable()) {
        return getDefaultDepartmentOptions();
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

// Add points to user
function addPoints($userId, $points, $source, $description = '') {
    global $pdo;

    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $targetUser = $stmt->fetch();

    if (!$targetUser || !roleEarnsPoints($targetUser['role'])) {
        return false;
    }
    
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
