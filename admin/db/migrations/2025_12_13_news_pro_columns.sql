-- Adds optional professional columns to `news` table used by the admin panel (safe to run once).
-- NOTE: If some columns already exist, remove those lines before running.

ALTER TABLE news
  ADD COLUMN seo_title VARCHAR(255) NULL,
  ADD COLUMN seo_description VARCHAR(300) NULL,
  ADD COLUMN seo_keywords VARCHAR(255) NULL,
  ADD COLUMN publish_at DATETIME NULL,
  ADD COLUMN unpublish_at DATETIME NULL;
