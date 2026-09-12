<?php
// Opens $pdo against MySQL using the credentials in config.php. DB_DRIVER is
// an optional escape hatch (defaults to mysql) used only for local
// development against SQLite — production never needs to set it.

$driver = defined('DB_DRIVER') ? DB_DRIVER : 'mysql';

try {
    if ($driver === 'sqlite') {
        $pdo = new PDO('sqlite:' . DB_SQLITE_PATH, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA foreign_keys = ON');
    } else {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
    }
} catch (PDOException $e) {
    http_response_code(500);
    die('Database connection failed. Check portal/config.php.');
}
