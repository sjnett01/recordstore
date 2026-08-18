<?php
declare(strict_types=1);

$configFile = getenv('RECORDSTORE_CONFIG') ?: dirname(__DIR__).'/config.php';
if (!is_readable($configFile)) {
    if (PHP_SAPI !== 'cli') {
        header('Location: install.php', true, 302);
        exit;
    }
    http_response_code(500);
    exit('Application configuration is unavailable.');
}
$config = require $configFile;
date_default_timezone_set($config['app']['timezone'] ?? 'Europe/London');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name($config['app']['session_name'] ?? 'recordstore_session');
    session_set_cookie_params([
        'httponly' => true,
        'secure' => true,
        'samesite' => 'Lax',
        'path' => '/',
    ]);
    session_start();
}

$dsn = sprintf(
    'mysql:host=%s;port=%d;dbname=%s;charset=%s',
    $config['db']['host'],
    (int)$config['db']['port'],
    $config['db']['name'],
    $config['db']['charset'] ?? 'utf8mb4'
);

try {
    $pdo = new PDO($dsn, $config['db']['user'], $config['db']['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    error_log('Application database error: '.$e->getMessage());
    http_response_code(500);
    exit('The application is temporarily unavailable.');
}

// A release package is installable by visiting index.php first. Keep the
// installer state in MySQL rather than relying on a removable lock file.
if (PHP_SAPI !== 'cli') {
    try {
        $installed = (int)$pdo->query("SELECT installed FROM installation_settings WHERE id=1 LIMIT 1")->fetchColumn();
        if ($installed !== 1) {
            header('Location: install.php', true, 302);
            exit;
        }
    } catch (Throwable $e) {
        http_response_code(500);
        exit('The application database has not been installed.');
    }
}

require_once __DIR__.'/functions.php';
