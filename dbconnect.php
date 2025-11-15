<?php
// DB connection template — reads credentials from environment variables (recommended) or a local .env file.
// IMPORTANT: Never commit a real .env with secrets. Use .env.example as a template and add .env to .gitignore.

// Attempt to load a simple .env file in the project root if present (non-composer fallback).
$dotenvPath = __DIR__ . '/.env';
if (file_exists($dotenvPath) && is_readable($dotenvPath)) {
    $lines = file($dotenvPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0) {
            continue;
        }
        if (strpos($line, '=') === false) {
            continue;
        }
        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        // Remove surrounding quotes if present
        if ((substr($value, 0, 1) === '"' && substr($value, -1) === '"') || (substr($value, 0, 1) === "'" && substr($value, -1) === "'")) {
            $value = substr($value, 1, -1);
        }
        if (getenv($key) === false) {
            putenv(sprintf('%s=%s', $key, $value));
        }
        if (!isset($_ENV[$key])) {
            $_ENV[$key] = $value;
        }
    }
}

$host = getenv('DB_HOST');
$username = getenv('DB_USER');
$password = getenv('DB_PASS');
$database = getenv('DB_NAME');
$charset = getenv('DB_CHARSET') ?: 'utf8mb4';

// Basic validation to give a clear error if env vars are missing.
if (empty($host) || empty($username) || empty($database)) {
    // In production don't leak secrets — this is intentionally generic.
    error_log('Database connection variables are not fully set. Ensure DB_HOST, DB_USER, and DB_NAME are configured.');
    die("Database is not configured. Please set environment variables (DB_HOST, DB_USER, DB_PASS, DB_NAME).
");
}

// Create mysqli connection (keeps backwards compatibility with existing codebase that uses $connection)
$connection = new mysqli($host, $username, $password, $database);
if ($connection->connect_error) {
    error_log('Database connection failed: ' . $connection->connect_error);
    // Generic message to avoid leaking details
    die('Database connection failed.');
}

if (!$connection->set_charset($charset)) {
    error_log('Failed to set DB charset to ' . $charset . ': ' . $connection->error);
    // proceed — many apps will still function, but log the issue
}

// $connection is available to other scripts that include this file
?>