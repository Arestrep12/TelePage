<?php
declare(strict_types=1);

$env = static function (string $key, ?string $default = null): ?string {
	$value = getenv($key);
	if ($value === false) {
		return $default;
	}
	return (string) $value;
};

define('DB_HOST', $env('DB_HOST', '127.0.0.1'));

define('DB_NAME', $env('DB_NAME', 'newsletter_ai'));

define('DB_USER', $env('DB_USER', 'newsletter'));

define('DB_PASS', $env('DB_PASS', ''));

define('APP_ENV', $env('APP_ENV', 'production'));

define('APP_DEBUG', (static function (): bool {
	$raw = getenv('APP_DEBUG');
	if ($raw === false) return false;
	$raw = strtolower(trim((string) $raw));
	return $raw === '1' || $raw === 'true' || $raw === 'yes' || $raw === 'on';
})());
