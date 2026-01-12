-- 2026_01_10_schema_runtime_compat.sql
-- Adds missing runtime tables/columns required by the UI (clean RAW package).
-- Safe to re-run; duplicates are ignored by installer.

-- ===== users: 2FA + session version =====
ALTER TABLE users ADD COLUMN twofa_enabled TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE users ADD COLUMN twofa_secret VARCHAR(255) NULL;
ALTER TABLE users ADD COLUMN session_version INT UNSIGNED NOT NULL DEFAULT 1;

-- ===== news: dashboard expects `views` =====
ALTER TABLE news ADD COLUMN views INT UNSIGNED NOT NULL DEFAULT 0;

-- ===== visits: ensure columns exist =====
CREATE TABLE IF NOT EXISTS visits (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  page VARCHAR(60) NOT NULL,
  news_id INT NULL,
  source VARCHAR(20) NOT NULL DEFAULT 'direct',
  referrer VARCHAR(255) NULL,
  user_ip VARCHAR(45) NULL,
  user_agent VARCHAR(255) NULL,
  os VARCHAR(40) NULL,
  browser VARCHAR(40) NULL,
  device VARCHAR(20) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_created (created_at),
  KEY idx_news (news_id),
  KEY idx_source (source)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE visits ADD COLUMN news_id INT NULL AFTER page;
ALTER TABLE visits ADD COLUMN source VARCHAR(20) NOT NULL DEFAULT 'direct' AFTER news_id;
ALTER TABLE visits ADD COLUMN referrer VARCHAR(255) NULL AFTER source;
ALTER TABLE visits ADD COLUMN user_ip VARCHAR(45) NULL AFTER referrer;
ALTER TABLE visits ADD COLUMN user_agent VARCHAR(255) NULL AFTER user_ip;
ALTER TABLE visits ADD COLUMN os VARCHAR(40) NULL AFTER user_agent;
ALTER TABLE visits ADD COLUMN browser VARCHAR(40) NULL AFTER os;
ALTER TABLE visits ADD COLUMN device VARCHAR(20) NULL AFTER browser;
ALTER TABLE visits ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP;

-- ===== opinion authors =====
CREATE TABLE IF NOT EXISTS opinion_authors (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  slug VARCHAR(255) NOT NULL UNIQUE,
  page_title VARCHAR(255) NULL,
  bio TEXT NULL,
  specialization VARCHAR(255) NULL,
  social_website VARCHAR(255) NULL,
  social_twitter VARCHAR(255) NULL,
  social_facebook VARCHAR(255) NULL,
  email VARCHAR(190) NULL,
  avatar VARCHAR(255) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT UNSIGNED NOT NULL DEFAULT 0,
  display_order INT NOT NULL DEFAULT 0,
  articles_count INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===== elections module (empty tables to prevent errors when enabled) =====
CREATE TABLE IF NOT EXISTS elections (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  slug VARCHAR(255) NOT NULL UNIQUE,
  description TEXT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  starts_at DATETIME NULL,
  ends_at DATETIME NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS election_regions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  election_id INT UNSIGNED NULL,
  name VARCHAR(255) NOT NULL,
  slug VARCHAR(255) NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_election_id (election_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS election_parties (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  election_id INT UNSIGNED NULL,
  name VARCHAR(255) NOT NULL,
  slug VARCHAR(255) NULL,
  color VARCHAR(32) NULL,
  logo VARCHAR(255) NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_election_id (election_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS election_constituencies (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  election_id INT UNSIGNED NULL,
  region_id INT UNSIGNED NULL,
  name VARCHAR(255) NOT NULL,
  slug VARCHAR(255) NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_election_id (election_id),
  KEY idx_region_id (region_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS election_candidates (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  election_id INT UNSIGNED NULL,
  constituency_id INT UNSIGNED NULL,
  party_id INT UNSIGNED NULL,
  name VARCHAR(255) NOT NULL,
  slug VARCHAR(255) NULL,
  avatar VARCHAR(255) NULL,
  votes INT UNSIGNED NOT NULL DEFAULT 0,
  is_winner TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_election_id (election_id),
  KEY idx_constituency_id (constituency_id),
  KEY idx_party_id (party_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS election_results_summary (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  election_id INT UNSIGNED NULL,
  party_id INT UNSIGNED NULL,
  seats INT UNSIGNED NOT NULL DEFAULT 0,
  votes INT UNSIGNED NOT NULL DEFAULT 0,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_election_id (election_id),
  KEY idx_party_id (party_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS election_results_regions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  election_id INT UNSIGNED NULL,
  region_id INT UNSIGNED NULL,
  party_id INT UNSIGNED NULL,
  seats INT UNSIGNED NOT NULL DEFAULT 0,
  votes INT UNSIGNED NOT NULL DEFAULT 0,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_election_id (election_id),
  KEY idx_region_id (region_id),
  KEY idx_party_id (party_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
