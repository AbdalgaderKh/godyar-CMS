-- Add default saved filter support
-- If your MariaDB/MySQL doesn't support IF NOT EXISTS for ADD COLUMN, run manually and ignore duplicate errors.
ALTER TABLE admin_saved_filters
  ADD COLUMN is_default TINYINT(1) NOT NULL DEFAULT 0 AFTER querystring;

CREATE INDEX idx_admin_saved_filters_default
  ON admin_saved_filters(user_id, page_key, is_default);
