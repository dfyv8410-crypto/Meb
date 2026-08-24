# CHANGELOG

## 1.3.0 — Page Builder UI + System Health + SEO Audit
- **Конструктор страниц в админке**: drag&drop сортировка блоков, стрелки, скрытие/дублирование/удаление, редакторы полей для всех 9 типов блоков, автосохранение, предпросмотр ↗
- **Система (админка)**: System Health таблица, обновления с авто-бэкапом и откатом, Audit Log
- **SEO Audit**: скоринг 0–100, детальные проблемы по страницам/изображениям (ALT, Title, Description)
- **Update system**: VERSION файл, проверка свежей версии через GitHub raw, миграции (core/migrations.js), pre-update snapshot, rollback при ошибке health-check
- **robots.txt**: Disallow /admin /install + Sitemap
- API: `/api/v1/seo/audit`, `/api/v1/system/update/check`, `/api/v1/system/update/run`, `/api/v1/audit_log`, `DELETE /api/v1/media/:id`
- Android: PIN-код (SHA-256), биометрия (BiometricPrompt), offline-кэш заявок
- Тесты: 39 сценариев, включая полный E2E page builder

## 1.2.0 — Внутренняя версия (миграции)

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
