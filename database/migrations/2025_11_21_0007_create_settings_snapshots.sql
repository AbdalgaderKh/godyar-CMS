-- جدول لحفظ نسخ احتياطية من إعدادات الموقع (Snapshots)
CREATE TABLE IF NOT EXISTS settings_snapshots (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_by INT UNSIGNED DEFAULT NULL,
  data_json LONGTEXT NOT NULL,
  PRIMARY KEY (id),
  KEY idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
