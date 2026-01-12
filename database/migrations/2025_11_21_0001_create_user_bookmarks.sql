-- إنشاء جدول لحفظ الأخبار المفضّلة للمستخدمين

CREATE TABLE IF NOT EXISTS user_bookmarks (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  news_id INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_user_news (user_id, news_id),
  KEY idx_user_id (user_id),
  KEY idx_news_id (news_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
