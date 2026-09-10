-- Migration: catalog_categories — hierarchical categories + publish flag
-- Adds: parent_id (hierarchy), is_active (publish / hide on the public site)
-- Run: env -u LD_LIBRARY_PATH php -r "require 'includes/router.php'; db()->exec(file_get_contents('sql/migration-categories.sql'));"
-- Or via phpMyAdmin

ALTER TABLE catalog_categories ADD COLUMN IF NOT EXISTS parent_id VARCHAR(20) DEFAULT NULL AFTER cover;
ALTER TABLE catalog_categories ADD COLUMN IF NOT EXISTS is_active TINYINT(1)   DEFAULT 1 AFTER parent_id;
ALTER TABLE catalog_categories ADD KEY IF NOT EXISTS idx_cat_parent (parent_id);
ALTER TABLE catalog_categories ADD KEY IF NOT EXISTS idx_cat_active (is_active);

INSERT IGNORE INTO migrations (id, applied_at) VALUES ('categories_hierarchy', NOW());