-- Add normalized translations table for news (ar base + en/fr)
-- NOTE: Foreign keys are intentionally omitted for shared-hosting compatibility.

CREATE TABLE IF NOT EXISTS news_translations (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  news_id INT UNSIGNED NOT NULL,
  lang CHAR(2) NOT NULL,
  title VARCHAR(255) NULL,
  excerpt VARCHAR(700) NULL,
  content MEDIUMTEXT NULL,
  seo_title VARCHAR(255) NULL,
  seo_description VARCHAR(400) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_news_lang (news_id, lang),
  KEY idx_news_trans_news (news_id),
  KEY idx_lang (lang)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
