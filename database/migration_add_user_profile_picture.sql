-- ATZ Fitness Gym Management System
-- Migration: Add profile photo support for Admin & Staff accounts
--
-- The `members` table already had a `profile_picture` column; `users`
-- (Admin/Staff logins) never did. Run this once against your existing
-- atz_fitness_db database to add it.
--
-- How to run (phpMyAdmin): open your atz_fitness_db database -> SQL tab
-- -> paste this file's contents -> Go.

ALTER TABLE `users`
  ADD COLUMN `profile_picture` VARCHAR(255) DEFAULT NULL AFTER `contact_no`;
