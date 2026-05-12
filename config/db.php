<?php
// Load .env file
function loadEnv($filePath) {
    if (!file_exists($filePath)) {
        die("Error: .env file not found at " . $filePath);
    }
    
    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Skip comments
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        
        // Parse key=value
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            
            // Remove quotes if present
            if ((strpos($value, '"') === 0 && strpos($value, '"', 1) === strlen($value) - 1) ||
                (strpos($value, "'") === 0 && strpos($value, "'", 1) === strlen($value) - 1)) {
                $value = substr($value, 1, -1);
            }
            
            if (!empty($key)) {
                putenv("$key=$value");
            }
        }
    }
}

// Load environment variables from .env file
$envPath = dirname(__DIR__) . '/.env';
loadEnv($envPath);

// Helper: ensure required env variables exist
function envOrFail($key) {
    $value = getenv($key);
    if ($value === false) {
        die("Missing environment variable: $key");
    }
    return $value;
}

// Database configuration from .env
define('DB_HOST', envOrFail('DB_HOST'));
define('DB_PORT', envOrFail('DB_PORT'));
define('DB_USER', envOrFail('DB_USER'));
define('DB_PASS', envOrFail('DB_PASS'));
define('DB_NAME', envOrFail('DB_NAME'));

try {
    $pdoOptions = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ];

    // Connect using host and port (no socket)
    $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $pdoOptions);

} catch (PDOException $e) {
    // Do not expose sensitive error details
    die("Database connection failed.");
}
?>
