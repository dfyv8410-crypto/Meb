-- =============================================================
-- MEB Premium Furniture Platform — MySQL Schema
-- Compatible with MySQL 5.7+ / MariaDB 10.1+
-- Import via phpMyAdmin or:  mysql -u USER -p DB < sql/schema.sql
-- =============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- -------------------------------------------------------------
-- USERS
-- roles: super_admin(4) > admin(3) > manager(2) > editor(1)
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
  id         VARCHAR(20)  NOT NULL,
  email      VARCHAR(191) NOT NULL,
  name       VARCHAR(120) DEFAULT '',
  role       ENUM('super_admin','admin','manager','editor') NOT NULL DEFAULT 'editor',
  -- password_hash() output (PHP password_hash / PASSWORD_DEFAULT)
  pass_hash  VARCHAR(255) DEFAULT '',
  -- legacy PBKDF2 fields (kept for JSON migration compatibility)
  salt       VARCHAR(64)  DEFAULT '',
  hash       VARCHAR(128) DEFAULT '',
  created_at DATETIME     DEFAULT NULL,
  updated_at DATETIME     DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------------
-- SESSIONS (PHP fallback; stateless HMAC also supported)
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS sessions (
  id         VARCHAR(64)  NOT NULL,
  user_id    VARCHAR(20)  NOT NULL,
  expires_at DATETIME     NOT NULL,
  created_at DATETIME     DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_sessions_user (user_id),
  KEY idx_sessions_exp (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------------
-- SETTINGS
-- id = 'site' for the single settings row
-- nested: seo {title,desc}, smtp {}, fcm {}, socials {}
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS settings (
  id         VARCHAR(40)  NOT NULL,
  data       LONGTEXT         DEFAULT NULL,
  updated_at DATETIME     DEFAULT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------------
-- CATEGORIES
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS catalog_categories (
  id         VARCHAR(20)  NOT NULL,
  slug       VARCHAR(120) NOT NULL,
  title      VARCHAR(255) DEFAULT '',
  description TEXT        DEFAULT NULL,
  cover      VARCHAR(255) DEFAULT '',
  parent_id  VARCHAR(20)  DEFAULT NULL,
  is_active  TINYINT(1)   DEFAULT 1,
  sort       INT          DEFAULT 0,
  created_at DATETIME     DEFAULT NULL,
  updated_at DATETIME     DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cat_slug (slug),
  KEY idx_cat_parent (parent_id),
  KEY idx_cat_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------------
-- CATALOG (products)
-- nested: images[], specs {}, materials
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS catalog (
  id         VARCHAR(20)  NOT NULL,
  slug       VARCHAR(120) NOT NULL,
  title      VARCHAR(255) DEFAULT '',
  description TEXT        DEFAULT NULL,
  category_id VARCHAR(20) DEFAULT NULL,
  price      BIGINT       DEFAULT 0,
  images     LONGTEXT         DEFAULT NULL,
  cover      VARCHAR(255) DEFAULT '',
  featured   TINYINT(1)   DEFAULT 0,
  published  TINYINT(1)   DEFAULT 1,
  specs      LONGTEXT         DEFAULT NULL,
  seo_title  VARCHAR(255) DEFAULT '',
  seo_desc   VARCHAR(255) DEFAULT '',
  created_at DATETIME     DEFAULT NULL,
  updated_at DATETIME     DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_catalog_slug (slug),
  KEY idx_catalog_cat (category_id),
  KEY idx_catalog_pub (published)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------------
-- PROJECTS
-- nested: images[], materials, features[]
-- category = slug-style string (as in Node original)
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS projects (
  id         VARCHAR(20)  NOT NULL,
  slug       VARCHAR(120) NOT NULL,
  title      VARCHAR(255) DEFAULT '',
  description TEXT        DEFAULT NULL,
  category   VARCHAR(120) DEFAULT '',
  images     LONGTEXT         DEFAULT NULL,
  featured   TINYINT(1)   DEFAULT 0,
  published  TINYINT(1)   DEFAULT 1,
  year       INT          DEFAULT 0,
  video      VARCHAR(255) DEFAULT '',
  size       VARCHAR(120) DEFAULT '',
  size_label VARCHAR(120) DEFAULT '',
  materials  LONGTEXT         DEFAULT NULL,
  features   LONGTEXT         DEFAULT NULL,
  seo_title  VARCHAR(255) DEFAULT '',
  seo_desc   VARCHAR(255) DEFAULT '',
  created_at DATETIME     DEFAULT NULL,
  updated_at DATETIME     DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_proj_slug (slug),
  KEY idx_proj_pub (published)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------------
-- MATERIALS
-- category: wood|stone|metal|glass|facade|hardware|coating|other
-- nested: props {}
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS materials (
  id         VARCHAR(20)  NOT NULL,
  slug       VARCHAR(120) NOT NULL,
  title      VARCHAR(255) DEFAULT '',
  category   VARCHAR(40)  DEFAULT 'wood',
  description TEXT        DEFAULT NULL,
  image      VARCHAR(255) DEFAULT '',
  props      LONGTEXT         DEFAULT NULL,
  created_at DATETIME     DEFAULT NULL,
  updated_at DATETIME     DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_mat_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------------
-- SERVICES
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS services (
  id         VARCHAR(20)  NOT NULL,
  slug       VARCHAR(120) NOT NULL,
  title      VARCHAR(255) DEFAULT '',
  description TEXT        DEFAULT NULL,
  icon       VARCHAR(40)  DEFAULT '',
  price_from BIGINT       DEFAULT 0,
  created_at DATETIME     DEFAULT NULL,
  updated_at DATETIME     DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_serv_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------------
-- REVIEWS
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS reviews (
  id         VARCHAR(20)  NOT NULL,
  author     VARCHAR(120) DEFAULT '',
  role       VARCHAR(120) DEFAULT '',
  text       TEXT         DEFAULT NULL,
  rating     INT          DEFAULT 5,
  approved   TINYINT(1)   DEFAULT 0,
  avatar     VARCHAR(255) DEFAULT '',
  project_id VARCHAR(20)  DEFAULT NULL,
  created_at DATETIME     DEFAULT NULL,
  updated_at DATETIME     DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_rev_appr (approved)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------------
-- LEADS (requests / CRM)
-- status: new|in_progress|contacted|done|rejected
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS requests (
  id         VARCHAR(20)  NOT NULL,
  name       VARCHAR(120) DEFAULT '',
  phone      VARCHAR(40)  DEFAULT '',
  email      VARCHAR(255) DEFAULT '',
  message    TEXT         DEFAULT NULL,
  status     ENUM('new','in_progress','contacted','done','rejected') NOT NULL DEFAULT 'new',
  comment    TEXT         DEFAULT NULL,
  manager_id VARCHAR(20)  DEFAULT NULL,
  source     VARCHAR(60)  DEFAULT '',
  created_at DATETIME     DEFAULT NULL,
  updated_at DATETIME     DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_req_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------------
-- MEDIA
-- filename = server-generated base36 name + ext
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS media (
  id           VARCHAR(20)  NOT NULL,
  filename     VARCHAR(255) NOT NULL,
  original_name VARCHAR(255) DEFAULT '',
  size         BIGINT       DEFAULT 0,
  width        INT          DEFAULT 0,
  height       INT          DEFAULT 0,
  folder       VARCHAR(40)  DEFAULT '',
  alt          VARCHAR(255) DEFAULT '',
  url          VARCHAR(255) DEFAULT '',
  mime         VARCHAR(80)  DEFAULT '',
  created_at   DATETIME     DEFAULT NULL,
  updated_at   DATETIME     DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_media_folder (folder)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------------
-- PAGES (CMS Page Builder)
-- nested: blocks[]
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS pages (
  id         VARCHAR(20)  NOT NULL,
  slug       VARCHAR(120) NOT NULL,
  title      VARCHAR(255) DEFAULT '',
  h1         VARCHAR(255) DEFAULT '',
  seo_title  VARCHAR(255) DEFAULT '',
  seo_desc   VARCHAR(255) DEFAULT '',
  canonical  VARCHAR(255) DEFAULT '',
  blocks     LONGTEXT         DEFAULT NULL,
  published  TINYINT(1)   DEFAULT 1,
  created_at DATETIME     DEFAULT NULL,
  updated_at DATETIME     DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pages_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------------
-- NOTIFICATIONS
-- nested: meta {}
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS notifications (
  id         VARCHAR(20)  NOT NULL,
  type       VARCHAR(40)  DEFAULT '',
  title      VARCHAR(255) DEFAULT '',
  body       TEXT         DEFAULT NULL,
  meta       LONGTEXT         DEFAULT NULL,
  `read`     TINYINT(1)   DEFAULT 0,
  created_at DATETIME     DEFAULT NULL,
  updated_at DATETIME     DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_notif_read (`read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------------
-- ANALYTICS (per-day pageviews)
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS analytics (
  id         VARCHAR(20)  NOT NULL,
  date       DATE         NOT NULL,
  views      BIGINT       DEFAULT 1,
  created_at DATETIME     DEFAULT NULL,
  updated_at DATETIME     DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_an_date (date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------------
-- AUDIT LOG
-- nested: meta {}
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS audit_log (
  id         VARCHAR(20)  NOT NULL,
  user_id    VARCHAR(20)  DEFAULT 'system',
  action     VARCHAR(40)  DEFAULT '',
  entity     VARCHAR(40)  DEFAULT '',
  entity_id  VARCHAR(40)  DEFAULT '',
  meta       LONGTEXT         DEFAULT NULL,
  ip         VARCHAR(46)  DEFAULT '',
  created_at DATETIME     DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_audit_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------------
-- BACKUPS (metadata; snapshots stored in uploads/backups or db)
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS backups (
  id         VARCHAR(20)  NOT NULL,
  filename   VARCHAR(255) DEFAULT '',
  size       BIGINT       DEFAULT 0,
  type       ENUM('manual','auto') NOT NULL DEFAULT 'manual',
  created_at DATETIME     DEFAULT NULL,
  updated_at DATETIME     DEFAULT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------------
-- MIGRATIONS
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS migrations (
  id         VARCHAR(40)  NOT NULL,
  applied_at DATETIME     DEFAULT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Rate-limit bucket (shared-hosting fallback for login throttle)
CREATE TABLE IF NOT EXISTS rate_limits (
  bucket     VARCHAR(60)  NOT NULL,
  ip         VARCHAR(46)  NOT NULL,
  window_ts  INT          NOT NULL,
  count      INT          DEFAULT 1,
  PRIMARY KEY (bucket, ip, window_ts)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;

-- -------------------------------------------------------------
-- MENU ITEMS (dynamic navigation)
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS menu_items (
  id         VARCHAR(20)  NOT NULL,
  title      VARCHAR(120) NOT NULL,
  url        VARCHAR(255) NOT NULL DEFAULT '#',
  sort_order INT          DEFAULT 0,
  is_active  TINYINT(1)   DEFAULT 1,
  created_at DATETIME     DEFAULT NULL,
  updated_at DATETIME     DEFAULT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------------
-- BANNERS (hero slider)
-- -------------------------------------------------------------
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
