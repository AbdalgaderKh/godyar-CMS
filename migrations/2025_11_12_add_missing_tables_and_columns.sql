-- Migration: Add missing tables and useful columns (visits, sessions, feeds) + indexes + SEO fields
-- Date: 2025-11-12
-- NOTE: Foreign keys are intentionally omitted for shared-hosting compatibility (MariaDB/Shared Hosting).

CREATE TABLE IF NOT EXISTS visits (
  id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  page VARCHAR(500) NOT NULL,
  ip_address VARCHAR(45) NOT NULL,
  user_agent VARCHAR(500) NULL,
  referrer VARCHAR(500) NULL,
  visit_time DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_visits_time (visit_time),
  KEY idx_visits_page (page(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sessions (
  id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  session_id VARCHAR(191) NOT NULL,
  user_id INT UNSIGNED NULL,
  ip_address VARCHAR(45) NULL,
  user_agent VARCHAR(500) NULL,
  data MEDIUMTEXT NULL,
  last_activity DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_sessions_session_id (session_id),
  KEY idx_sessions_last_activity (last_activity),
  KEY idx_sessions_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS feeds (
  id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(255) NOT NULL,
  url VARCHAR(500) NOT NULL,
  category_id INT UNSIGNED NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  fetch_interval_minutes INT NOT NULL DEFAULT 60,
  last_fetched_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_feeds_url (url(191)),
  KEY idx_feeds_category_id (category_id),
  KEY idx_feeds_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- News SEO + workflow helpers (safe to re-run; installer ignores duplicate column/index errors)
ALTER TABLE news
  ADD COLUMN excerpt VARCHAR(500) NULL AFTER content,
  ADD COLUMN views INT NOT NULL DEFAULT 0 AFTER featured_image,
  ADD COLUMN is_featured TINYINT(1) NOT NULL DEFAULT 0 AFTER status,
  ADD COLUMN priority TINYINT NOT NULL DEFAULT 0 AFTER is_featured,
  ADD COLUMN seo_title VARCHAR(255) NULL,
  ADD COLUMN seo_description VARCHAR(300) NULL,
  ADD COLUMN seo_keywords VARCHAR(500) NULL;

CREATE INDEX idx_news_status_publish ON news (status, publish_at);
CREATE INDEX idx_news_category ON news (category_id);

CREATE INDEX idx_news_tags_news ON news_tags (news_id);
CREATE INDEX idx_news_tags_tag ON news_tags (tag_id);

ALTER TABLE settings MODIFY `value` LONGTEXT;
