-- Migration: relate real photos (uploads/tkya*.png) to collection categories
-- Idempotent: safe to re-run after a DB restore (media + covers are re-created).
-- Run:
--   env -u LD_LIBRARY_PATH php -r "require 'includes/router.php'; db()->exec(file_get_contents('sql/collections-real-photos.sql'));"
-- Or via phpMyAdmin

INSERT INTO media (id, filename, original_name, size, width, height, folder, alt, url, mime, created_at, updated_at)
SELECT 'cat-kukhni',    'tkya5s85118.png',  'tkya5s85118.png',  1896214, 1536, 1024, '', 'Кухни на заказ — фото проекта MEB', '/uploads/tkya5s85118.png', 'image/png', NOW(), NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM media WHERE url = '/uploads/tkya5s85118.png');

INSERT INTO media (id, filename, original_name, size, width, height, folder, alt, url, mime, created_at, updated_at)
SELECT 'cat-garderob',  'tkya5a54122.png',  'tkya5a54122.png',  2047815, 1536, 1024, '', 'Гардеробные на заказ — фото встроенной гардеробной MEB', '/uploads/tkya5a54122.png', 'image/png', NOW(), NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM media WHERE url = '/uploads/tkya5a54122.png');

INSERT INTO media (id, filename, original_name, size, width, height, folder, alt, url, mime, created_at, updated_at)
SELECT 'cat-spalni',    'tkya7y25185.png',  'tkya7y25185.png',  1830876, 1448, 1086, '', 'Спальни на заказ — фото спальни MEB', '/uploads/tkya7y25185.png', 'image/png', NOW(), NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM media WHERE url = '/uploads/tkya7y25185.png');

INSERT INTO media (id, filename, original_name, size, width, height, folder, alt, url, mime, created_at, updated_at)
SELECT 'cat-kabineti',  'tkya7900677.png',  'tkya7900677.png',  2033608, 1448, 1086, '', 'Кабинеты на заказ — фото кабинета MEB', '/uploads/tkya7900677.png', 'image/png', NOW(), NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM media WHERE url = '/uploads/tkya7900677.png');

UPDATE catalog_categories SET cover = '/uploads/tkya5s85118.png', updated_at = NOW() WHERE id = 'c1' AND cover <> '/uploads/tkya5s85118.png';
UPDATE catalog_categories SET cover = '/uploads/tkya5a54122.png', updated_at = NOW() WHERE id = 'c2' AND cover <> '/uploads/tkya5a54122.png';
UPDATE catalog_categories SET cover = '/uploads/tkya7y25185.png', updated_at = NOW() WHERE id = 'c4' AND cover <> '/uploads/tkya7y25185.png';
UPDATE catalog_categories SET cover = '/uploads/tkya7900677.png', updated_at = NOW() WHERE id = 'c5' AND cover <> '/uploads/tkya7900677.png';

INSERT IGNORE INTO migrations (id, applied_at) VALUES ('collections_real_photos', NOW());