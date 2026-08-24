# DATABASE

## Engine
`JsonStore` — файлы `/storage/data/*.json` (atomic write via tmp+rename). Интерфейс совместим с SQL адаптером.

## Collections
- `users` {id, email, name, role(super_admin|admin|manager|editor), passHash, salt, createdAt}
- `pages` {id, slug, title, h1, seoTitle, seoDesc, canonical, blocks:[{type, data, hidden}], published, createdAt, updatedAt}
- `categories` {id, slug, title, desc, cover, sort}
- `catalog_items` {id, categoryId, slug, title, desc, price, materials[], images[], specs{size, material, finish}, featured, published}
- `projects` {id, slug, title, desc, category, images[], video, materials[], size, features[], year, published, featured}
- `materials` {id, slug, title, category(wood|stone|metal|glass|facade|hardware|coating), desc, image, props{}}
- `services` {id, slug, title, desc, icon, priceFrom, blocks[]}
- `reviews` {id, author, role, text, rating, avatar, projectId, approved, createdAt}
- `leads` {id, name, phone, email, message, projectId, source, status(new|in_progress|contacted|done|rejected), managerId, comment, createdAt}
- `media` {id, filename, originalName, mime, size, folder, alt, width, height, createdAt}
- `settings` {siteName, tagline, phone, email, address, mapEmbed, socials{}, seo{title, desc}, theme}
- `audit_log` {id, userId, action, entity, entityId, meta, ip, createdAt}
- `backups` {id, filename, size, type(manual|auto), createdAt}

## Indexes
В памяти: Map by id + Map by slug (если есть). Поиск — линейный (до 100k записей мгновенно на Node).

## Migrations
`/core/migrations.js` — версионирование `storage/data/_meta.json` {version}, накатываются при старте.

## Demo Seed
`storage/demo-seed.json` → при install с флагом demo=true разворачивается в коллекции.
