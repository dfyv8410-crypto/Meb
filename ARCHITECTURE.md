# ARCHITECTURE — Premium Furniture Platform

## 1. Vision
Премиальная цифровая платформа: `Public Site + CMS + Admin + API + Installer + Android App + Backup/Update`

Принцип: **Luxury through restraint** — дорого через сдержанность, пространство, типографику.

## 2. Stack (Zero-Dependency Core)
- **Runtime**: Node.js (http, fs, crypto, path — без внешних зависимостей)
- **Storage**: File JSON DB (`/storage/data/*.json`) + `uploads/` — миграция на SQLite/Postgres через адаптер
- **Frontend**: Vanilla HTML/CSS/JS + CSS variables + IntersectionObserver + WebGL fallback
- **Auth**: httpOnly cookie + HMAC-SHA256 token + bcrypt-like pbkdf2
- **Build**: No build step — чистые ES modules, code splitting через динамический import

> Почему без npm: гарантирует установку на любом хостинге за 30 сек, zero supply-chain риск. Адаптер `core/database.js` позволяет заменить `JsonStore` на `better-sqlite3` одной строкой.

## 3. Layers
```
┌─ Frontend (SSR-lite + Progressive Enhancement)
├─ API (/api/v1/*) → Core Router
├─ Core (config, events, auth, cache, validation, audit)
├─ Modules (pages, catalog, projects, materials, services, reviews, leads, media, seo, analytics, backup)
├─ Storage (data/*.json, uploads/, cache/, backups/)
└─ Mobile (Android native → same API)
```

## 4. Core (`/core`)
- `config.js` — загрузка `.env` + `config.json`, env override
- `database.js` — JsonStore с транзакциями, индексами, миграциями
- `router.js` — легковесный Radix-подобный роутер (method+path → handler + middleware)
- `auth.js` — регистрация, login, сессии, RBAC, brute-force guard
- `events.js` — EventBus (pub/sub) для модулей
- `cache.js` — LRU memory + file cache, инвалидация по тегам
- `validation.js` — схемы + санитайзер (XSS, SQLi guard)
- `security.js` — CSRF, rate-limit, headers (CSP, HSTS), upload filter
- `audit.js` — лог всех мутаций
- `i18n.js` — RU/EN, ключи в `/locales/*.json`

## 5. Modules (`/modules/*`)
Каждый модуль: `routes.js` + `service.js` + `schema.json`
- `pages` — Page Builder (blocks: hero, gallery, features...)
- `catalog` — категории (кухни, гардеробные...) + items
- `projects` — портфолио с masonry
- `materials` — дерево/камень/металл...
- `reviews` — модерация
- `leads` — CRM (статусы: new/in_progress/contacted/done/rejected)
- `media` — upload, WebP/AVIF мета, thumbs
- `seo` — sitemap, robots, OG, schema, audit
- `users` — Admin/Manager/Editor
- `analytics` — pageviews, popular projects
- `backup` — dump data+uploads, restore с pre-restore snapshot
- `notifications` — queue для push/email

## 6. Frontend (`/frontend`)
- `index.html` + `assets/css/premium.css` (design tokens)
- `assets/js/app.js` — reveal, parallax, WebGL hero (Three.js CDN lazy)
- Темы: CSS variables (ink, stone, brass, oak)
- Типографика: Manrope + Cormorant Garamond
- Композиция: editorial grid, asymmetric, cinematic sections

## 7. Admin (`/admin`)
SPA без сборки: hash-router, `/admin/index.html` + `admin.js`
- Dashboard, CRUD каждого модуля, Media Manager, SEO, Users, Backup, System Health

## 8. Installer (`/installer`)
`GET /install` — 7 шагов: check → db → admin → site → demo → install → done
После успеха создаёт `storage/installed.lock` и блокирует повторный вход.

## 9. API (`/api/v1/*`)
REST JSON, `Authorization: Bearer <token>` или cookie
- `POST /auth/login`, `POST /auth/logout`, `GET /me`
- `GET/POST /pages`, `GET/POST /catalog/*`...
- `GET /health`, `GET /seo/sitemap.xml`
- `GET /app/latest` — инфо о последнем APK
- `GET /app/download` — скачивание APK-файла
- `POST /install/run` — первичная установка через веб-мастер

## 10. Android App
- **Kotlin + Jetpack Compose** (`/mobile`) — Retrofit → `/api/v1/*`, DataStore, Biometric, FCM push
- **Java WebView** (`/mobile-java`) — PIN-экран → логин → WebView admin-panel, собирается без Gradle (aapt + javac + dx + jarsigner)
- APK скачивается из админки: Система → 📱 Android Приложение → Скачать APK

## 11. Security / Performance / A11y
- CSP, CSRF token per form, rate-limit 60/min, pbkdf2 100k iter, secure cookie
- Lazy images, WebP, cache-control, preconnect fonts, reduced-motion media query
- Keyboard nav, focus ring, semantic HTML, ARIA

## 12. Deployment
`node server.js` → :3000, `PM2` или systemd, Nginx reverse proxy, Let's Encrypt

## 13. Scalability
Добавить Postgres: заменить `JsonStore` → `PgStore` (интерфейс тот же). Кэш → Redis адаптер. Хранилище → S3 адаптер.
