-- 2026_01_02_create_news_questions.sql
-- Create table for "Ask the writer" Q&A feature

CREATE TABLE IF NOT EXISTS `news_questions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `news_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NULL,
  `name` VARCHAR(120) NULL,
  `email` VARCHAR(190) NULL,
  `question` TEXT NOT NULL,
  `answer` TEXT NULL,
  `status` ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `created_at` DATETIME NULL,
  `answered_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `idx_news` (`news_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

