<?php
// Helper: ensure required env variables exist
function envOrFail($key) {
    $value = getenv($key);
    if ($value === false) {
        die("Missing environment variable: $key");
    }
    return $value;
}

// Required configs
define('DB_HOST', envOrFail('DB_HOST'));
define('DB_PORT', envOrFail('DB_PORT'));
define('DB_USER', envOrFail('DB_USER'));
define('DB_PASS', envOrFail('DB_PASS'));
define('DB_NAME', envOrFail('DB_NAME'));

// Optional (only if you really use socket)
define('DB_SOCKET', getenv('DB_SOCKET') ?: null);

try {
    $pdoOptions = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ];

    // Use socket if provided, otherwise host/port
    if (DB_SOCKET) {
        $dsn = "mysql:unix_socket=" . DB_SOCKET . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    } else {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    }

    $pdo = new PDO($dsn, DB_USER, DB_PASS, $pdoOptions);

} catch (PDOException $e) {
    // Do not expose sensitive error details
    die("Database connection failed.");
}
?>
