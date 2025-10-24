-- Newsletter IA — local/dev bootstrap (MySQL 8)
-- Replace CHANGEME_STRONG_PASSWORD before running, or set at runtime via a client.

CREATE DATABASE IF NOT EXISTS newsletter_ai
	CHARACTER SET utf8mb4
	COLLATE utf8mb4_unicode_ci;

-- Users for local/dev (adjust hosts as needed)
CREATE USER IF NOT EXISTS 'newsletter'@'localhost' IDENTIFIED BY 'CHANGEME_STRONG_PASSWORD';
CREATE USER IF NOT EXISTS 'newsletter'@'127.0.0.1' IDENTIFIED BY 'CHANGEME_STRONG_PASSWORD';
CREATE USER IF NOT EXISTS 'newsletter'@'%' IDENTIFIED BY 'CHANGEME_STRONG_PASSWORD';

GRANT ALL PRIVILEGES ON newsletter_ai.* TO 'newsletter'@'localhost';
GRANT ALL PRIVILEGES ON newsletter_ai.* TO 'newsletter'@'127.0.0.1';
GRANT ALL PRIVILEGES ON newsletter_ai.* TO 'newsletter'@'%';
FLUSH PRIVILEGES;

USE newsletter_ai;

CREATE TABLE IF NOT EXISTS subscribers (
	id INT UNSIGNED NOT NULL AUTO_INCREMENT,
	name VARCHAR(120) NOT NULL,
	email VARCHAR(255) NOT NULL,
	consent TINYINT(1) NOT NULL DEFAULT 1,
	created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (id),
	UNIQUE KEY uq_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
