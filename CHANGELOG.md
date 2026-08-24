# CHANGELOG

## 1.1.0 — CMS page rendering
- `/p/:slug` — рендеринг страниц из CMS с блоками (hero, features, gallery, faq, cta, statistics, team, contact, text)
- SEO per-page: seoTitle, seoDesc, canonical
- 404-страница для несуществующих slug

## 1.0.0 — Первый релиз
- Core: config / JsonStore / router / auth (pbkdf2 + HMAC token) / RBAC / rate-limit buckets / audit log / security headers (CSP)
- API v1: CRUD pages, catalog, projects, materials, services, reviews, leads; public lead form; media upload; settings; analytics; backup create/list/restore (pre-restore snapshot); sitemap.xml
- Users: хеширование пароля, запрет удаления последнего админа, salt/hash не покидают сервер
- Installer: 7 шагов, demo-seed, блокировка после установки
- Premium frontend: editorial hero, masonry, reveal-анимации, reduced-motion
- Admin SPA: dashboard, быстрые действия, CRUD, медиа, бэкапы
- Android: Kotlin + Compose каркас
- Smoke-тесты: 34 сценария, идемпотентные прогоны
