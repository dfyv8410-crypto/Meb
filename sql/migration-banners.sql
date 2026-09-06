-- Migration: Add banners table
-- Run: env -u LD_LIBRARY_PATH php -r "require 'includes/router.php'; db()->exec(file_get_contents('sql/migration-banners.sql'));"
-- Or via phpMyAdmin

CREATE TABLE IF NOT EXISTS banners (
  id                  VARCHAR(20)  NOT NULL,
  title               VARCHAR(255) DEFAULT '',
  subtitle            VARCHAR(500) DEFAULT '',
  image_url           VARCHAR(255) DEFAULT '',
  mobile_image_url    VARCHAR(255) DEFAULT '',
  button_text         VARCHAR(120) DEFAULT '',
  button_url          VARCHAR(255) DEFAULT '',
  text_position       VARCHAR(20)  DEFAULT 'left',
  overlay_opacity     INT          DEFAULT 40,
  sort_order          INT          DEFAULT 0,
  is_active           TINYINT(1)   DEFAULT 1,
  starts_at           DATETIME     DEFAULT NULL,
  ends_at             DATETIME     DEFAULT NULL,
  created_at          DATETIME     DEFAULT NULL,
  updated_at          DATETIME     DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_banners_active (is_active),
  KEY idx_banners_sort (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO migrations (id, applied_at) VALUES ('banners_table', NOW());
