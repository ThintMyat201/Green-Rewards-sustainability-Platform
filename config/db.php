<?php
// Database Configuration
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : 'root');
define('DB_NAME', getenv('DB_NAME') ?: 'green_rewards');
define('DB_SOCKET', getenv('DB_SOCKET') ?: '/Applications/MAMP/tmp/mysql/mysql.sock');

// Create PDO connection
// Prefer MAMP socket first (if available), then fallback to host/port.
try {
    $pdoOptions = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ];

    $passwordCandidates = array_values(array_unique([DB_PASS, '']));
    $connectionAttempts = [];

    if (DB_SOCKET && file_exists(DB_SOCKET)) {
        foreach ($passwordCandidates as $password) {
            $connectionAttempts[] = [
                'dsn' => "mysql:unix_socket=" . DB_SOCKET . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                'user' => DB_USER,
                'pass' => $password
            ];
        }
    }

    foreach ($passwordCandidates as $password) {
        $connectionAttempts[] = [
            'dsn' => "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            'user' => DB_USER,
            'pass' => $password
        ];
    }

    foreach ($connectionAttempts as $attempt) {
        try {
            $pdo = new PDO($attempt['dsn'], $attempt['user'], $attempt['pass'], $pdoOptions);
            break;
        } catch (PDOException $e) {
            $pdo = null;
        }
    }

    if (!$pdo) {
        throw new PDOException('Unable to connect with configured MySQL connection attempts.');
    }
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>