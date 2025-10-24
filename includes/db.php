<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

$dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
$options = [
	PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
	PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
	PDO::ATTR_EMULATE_PREPARES => false,
];

try {
	$pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (Throwable $e) {
	http_response_code(500);
	if (defined('APP_DEBUG') && APP_DEBUG) {
		echo 'Database connection error: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
	} else {
		echo 'Internal server error';
	}
	exit;
}
