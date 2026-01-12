-- Godyar CMS - Core Schema (clean install)
-- NOTE: Foreign keys are intentionally omitted for maximum compatibility on shared hosting.
-- You can add them later after install if needed.

-- Charset: utf8mb4

SET NAMES utf8mb4;
SET time_zone = '+03:00';

-- -------------------------
-- Users & RBAC
-- -------------------------

-- -------------------------
-- Clean re-install guard
-- (Drops existing tables to avoid FK mismatch errors like errno 150)
-- WARNING: This will REMOVE existing data in these tables.
-- -------------------------
SET FOREIGN_KEY_CHECKS=0;
DROP TABLE IF EXISTS news_tags;
DROP TABLE IF EXISTS role_permissions;
DROP TABLE IF EXISTS user_roles;
DROP TABLE IF EXISTS news;
DROP TABLE IF EXISTS tags;
DROP TABLE IF EXISTS pages;
DROP TABLE IF EXISTS settings;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS permissions;
DROP TABLE IF EXISTS roles;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS=1;

CREATE TABLE users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(190) NULL,
  username VARCHAR(60) NOT NULL,
  email VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  -- Legacy compatibility: some parts still reference `password`
  password VARCHAR(255) NULL,
  role VARCHAR(100) NOT NULL DEFAULT 'user',
  is_admin TINYINT(1) NOT NULL DEFAULT 0,
  status VARCHAR(20) NOT NULL DEFAULT 'active',
  avatar VARCHAR(255) NULL,
  last_login_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_users_email (email),
  UNIQUE KEY uniq_users_username (username),
  KEY idx_users_role (role),
  KEY idx_users_status (status),
  KEY idx_users_is_admin (is_admin)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE roles (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(60) NOT NULL,
  label VARCHAR(120) NOT NULL,
  description TEXT NULL,
  is_system TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_roles_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE permissions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(120) NOT NULL,
  label VARCHAR(190) NOT NULL,
  description TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_permissions_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE role_permissions (
  role_id INT UNSIGNED NOT NULL,
  permission_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (role_id, permission_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_roles (
  user_id INT UNSIGNED NOT NULL,
  role_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (user_id, role_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed base roles/permissions (idempotent)
INSERT INTO roles (name,label,description,is_system)
SELECT 'admin','مدير النظام','صلاحيات كاملة',1
WHERE NOT EXISTS (SELECT 1 FROM roles WHERE name='admin');

INSERT INTO roles (name,label,description,is_system)
SELECT 'writer','كاتب','كتابة وتعديل أخبار',1
WHERE NOT EXISTS (SELECT 1 FROM roles WHERE name='writer');

INSERT INTO roles (name,label,description,is_system)
SELECT 'user','مستخدم','حساب مستخدم عادي',1
WHERE NOT EXISTS (SELECT 1 FROM roles WHERE name='user');

INSERT INTO permissions (code,label,description)
SELECT '*','صلاحيات كاملة','جميع الصلاحيات'
WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE code='*');

INSERT INTO permissions (code,label,description)
SELECT 'manage_users','إدارة المستخدمين',NULL
WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE code='manage_users');

INSERT INTO permissions (code,label,description)
SELECT 'manage_roles','إدارة الأدوار والصلاحيات',NULL
WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE code='manage_roles');

INSERT INTO permissions (code,label,description)
SELECT 'manage_security','إعدادات الأمان',NULL
WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE code='manage_security');

INSERT INTO permissions (code,label,description)
SELECT 'manage_plugins','إدارة الإضافات',NULL
WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE code='manage_plugins');

INSERT INTO permissions (code,label,description)
SELECT 'posts.*','إدارة الأخبار',NULL
WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE code='posts.*');

-- Ensure admin role has * permission
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r, permissions p
WHERE r.name='admin' AND p.code='*'
  AND NOT EXISTS (
    SELECT 1 FROM role_permissions rp WHERE rp.role_id=r.id AND rp.permission_id=p.id
  );

-- -------------------------
-- Content
-- -------------------------
CREATE TABLE categories (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(190) NOT NULL,
  slug VARCHAR(190) NOT NULL,
  parent_id INT UNSIGNED NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_categories_slug (slug),
  KEY idx_categories_parent (parent_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE news (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  category_id INT UNSIGNED NULL,
  title VARCHAR(255) NOT NULL,
  slug VARCHAR(255) NOT NULL,
  excerpt TEXT NULL,
  content LONGTEXT NULL,
  status VARCHAR(30) NOT NULL DEFAULT 'published',
  featured_image VARCHAR(255) NULL,
  image_path VARCHAR(255) NULL,
  image VARCHAR(255) NULL,
  is_breaking TINYINT(1) NOT NULL DEFAULT 0,
  view_count INT UNSIGNED NOT NULL DEFAULT 0,
  published_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  UNIQUE KEY uniq_news_slug (slug),
  KEY idx_news_category (category_id),
  KEY idx_news_status (status),
  KEY idx_news_published (published_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tags (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  slug VARCHAR(190) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_tags_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE news_tags (
  news_id INT UNSIGNED NOT NULL,
  tag_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (news_id, tag_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE pages (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  slug VARCHAR(190) NOT NULL,
  content LONGTEXT NULL,
  status VARCHAR(30) NOT NULL DEFAULT 'published',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_pages_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE settings (
  `key` VARCHAR(120) NOT NULL PRIMARY KEY,
  `value` LONGTEXT NULL,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Basic defaults
INSERT INTO settings(`key`,`value`) VALUES
('site_name','Godyar'),
('site_lang','ar'),
('site_dir','rtl')
ON DUPLICATE KEY UPDATE `value`=VALUES(`value`);
