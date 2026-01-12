-- Optional foreign keys (run manually after install if your MySQL/MariaDB supports it)
-- IMPORTANT: Make sure all tables are ENGINE=InnoDB and id columns are INT UNSIGNED.

ALTER TABLE user_roles
  ADD CONSTRAINT fk_ur_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  ADD CONSTRAINT fk_ur_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE;

ALTER TABLE role_permissions
  ADD CONSTRAINT fk_rp_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
  ADD CONSTRAINT fk_rp_perm FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE;

ALTER TABLE news
  ADD CONSTRAINT fk_news_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL;

ALTER TABLE news_tags
  ADD CONSTRAINT fk_nt_news FOREIGN KEY (news_id) REFERENCES news(id) ON DELETE CASCADE,
  ADD CONSTRAINT fk_nt_tag FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE;

-- Additional optional foreign keys for admin migrations
ALTER TABLE `admin_saved_filters`
  ADD CONSTRAINT `fk_saved_filters_user`
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE;

ALTER TABLE `admin_notifications`
  ADD CONSTRAINT `fk_admin_notifications_user`
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL;

ALTER TABLE `comments`
  ADD CONSTRAINT `fk_comments_news`
  FOREIGN KEY (`news_id`) REFERENCES `news`(`id`) ON DELETE CASCADE;
