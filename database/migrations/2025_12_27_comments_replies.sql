-- Add replies support to comments table
ALTER TABLE `comments`
  ADD COLUMN `parent_id` INT NOT NULL DEFAULT 0 AFTER `news_id`,
  ADD INDEX `idx_parent_id` (`parent_id`);
