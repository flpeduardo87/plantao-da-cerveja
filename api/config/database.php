<?php
// Credentials are defined in local.php (not in version control)
// Copy api/config/local.example.php → api/config/local.php and fill in the values
if (file_exists(__DIR__ . '/local.php')) {
    require_once __DIR__ . '/local.php';
} else {
    define('DB_HOST',    getenv('DB_HOST')    ?: 'localhost');
    define('DB_NAME',    getenv('DB_NAME')    ?: '');
    define('DB_USER',    getenv('DB_USER')    ?: '');
    define('DB_PASS',    getenv('DB_PASS')    ?: '');
    define('DB_CHARSET', 'utf8mb4');
}

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}
