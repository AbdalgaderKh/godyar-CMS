-- إنشاء جدول لمنع تكرار استيراد عناصر RSS
CREATE TABLE IF NOT EXISTS news_imports (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  news_id INT UNSIGNED NOT NULL,
  feed_id INT UNSIGNED NOT NULL,
  item_hash CHAR(40) NOT NULL,
  item_link VARCHAR(1000) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_item_hash (item_hash),
  KEY idx_feed (feed_id),
  KEY idx_news (news_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
