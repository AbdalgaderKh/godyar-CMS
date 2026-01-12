-- Adds "members only" flags for categories and news (Option A: show list + lock badge + paywall)
-- Run once on your database.
-- MySQL/MariaDB

-- Note: if your tables don't have `is_active` or `status`, adjust the AFTER clause or remove it.

ALTER TABLE `categories`
  ADD COLUMN `is_members_only` TINYINT(1) NOT NULL DEFAULT 0;

ALTER TABLE `news`
  ADD COLUMN `is_members_only` TINYINT(1) NOT NULL DEFAULT 0;

-- Optional indexes
CREATE INDEX `idx_categories_members_only` ON `categories` (`is_members_only`);
CREATE INDEX `idx_news_members_only` ON `news` (`is_members_only`);
