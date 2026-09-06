# DATABASE — MEB CMS

## Engine
MySQL 5.7+ через PDO. Подготовленные выражения, JSON колонки, auto-increment-free IDs.

## Tables (18)
- `users` — id, email, name, role, pass_hash, created_at, updated_at
- `pages` — id, slug, title, h1, seo_title, seo_desc, canonical, blocks (JSON), published, created_at, updated_at
- `catalog_categories` — id, slug, title, description, cover, sort_order
- `catalog` — id, slug, title, description, category_id, price, images (JSON), specs (JSON), featured, published, created_at, updated_at
- `projects` — id, slug, title, description, category, images (JSON), features (JSON), materials (JSON), year, published, created_at, updated_at
- `materials` — id, slug, title, type, description, image, props (JSON), created_at, updated_at
- `services` — id, slug, title, description, category, icon, price_from, created_at, updated_at
- `reviews` — id, author, text, rating, approved, created_at, updated_at
- `requests` — id, name, phone, email, message, status, source, comment, created_at, updated_at
- `notifications` — id, type, title, body, meta (JSON), read, created_at, updated_at
- `menu_items` — id, title, url, sort_order, is_active, created_at, updated_at
- `media` — id, filename, original_name, mime, size, folder, alt, url, created_at
- `settings` — id='site', data (JSON)
- `audit_log` — id, user_id, action, entity, entity_id, meta (JSON), ip, created_at
- `backups` — id, filename, size, type, created_at
- `analytics` — id, page, views, date
- `rate_limits` — bucket, ip, window_ts, count

## Indexes
- catalog: slug, category_id, published
- projects: slug, published
- materials: slug
- services: slug
- reviews: approved
- pages: slug
- requests: status
- users: email
- analytics: date

## JSON Columns
Автоматически декодируются при чтении: images, specs, features, materials, props, blocks, meta, data.

## Demo Seed
`storage/demo-seed.json` → при установке с demo=true импортируется в коллекции.
