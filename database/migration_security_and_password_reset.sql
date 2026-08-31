-- ATZ Fitness Gym Management System
-- Migration: Brute-force protection + Forgot Password support
--
-- Adds:
--   1. Account lockout / failed-login tracking columns on `users`
--      (used by login.php for brute-force protection).
--   2. `password_reset_tokens` table for the Forgot Password flow
--      (used by forgot_password.php / reset_password.php), shared by
--      both Administrator and Staff accounts since they live in the
--      same `users` table.
--
-- How to run (phpMyAdmin): open your atz_fitness_db database -> SQL tab
-- -> paste this file's contents -> Go.

ALTER TABLE `users`
  ADD COLUMN `failed_login_attempts` INT(11) NOT NULL DEFAULT 0 AFTER `force_password_change`,
  ADD COLUMN `locked_until` DATETIME DEFAULT NULL AFTER `failed_login_attempts`;

CREATE TABLE `password_reset_tokens` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `token_hash` VARCHAR(255) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `used` TINYINT(1) NOT NULL DEFAULT 0,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `token_hash` (`token_hash`),
  CONSTRAINT `password_reset_tokens_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
