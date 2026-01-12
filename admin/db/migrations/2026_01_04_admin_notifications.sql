-- 2026_01_04_admin_notifications.sql
-- Admin Notifications table

CREATE TABLE IF NOT EXISTS admin_notifications (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NULL DEFAULT NULL,
  title VARCHAR(255) NOT NULL,
  body TEXT NULL,
  link VARCHAR(500) NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  INDEX idx_admin_notifications_user (user_id),
  INDEX idx_admin_notifications_read (is_read)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
