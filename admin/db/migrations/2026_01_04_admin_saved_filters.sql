CREATE TABLE IF NOT EXISTS `admin_saved_filters` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `page_key` VARCHAR(64) NOT NULL,
  `name` VARCHAR(120) NOT NULL,
  `querystring` VARCHAR(1000) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_saved_filters_user_page` (`user_id`, `page_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
