# ARCHITECTURE — MEB CMS

## 1. Vision
Премиальная CMS для мебельного производства: сайт + админка + API + установщик.
Работает на любом shared-хостинге (Sweb.ru и аналоги) без Node.js, SSH, Composer.

## 2. Stack
- **Runtime**: PHP 7.1+ (без внешних зависимостей)
- **Storage**: MySQL 5.7+ (PDO, подготовленные выражения)
- **Frontend**: Vanilla HTML/CSS/JS + CSS variables + IntersectionObserver
- **Auth**: JWT (HMAC-SHA256) + httpOnly cookie + password_hash
- **Admin**: SPA без сборки (hash-router, 14 разделов)
- **Installer**: Чистый PHP, 7 шагов, работает без API

## 3. Layers
```
┌─ Public Pages (PHP SSR + premium CSS)
├─ API (/api/v1/*) → Router (index.php → api/v1/router.php)
├─ Core (includes/) — auth, database, helpers, security, validation
├─ Admin SPA (admin/) — 14 разделов, CRUD, page builder
├─ Installer (installer/) — 7-step wizard
├─ Storage (MySQL + storage/ + uploads/)
└─ Scripts (scripts/) — тесты, утилиты
```

## 4. Core (`includes/`)
- `router.php` — маршрутизация, определение окружения, error handling
- `database.php` — PDO singleton, JSON колонки, safe_ident, new_id
- `helpers.php` — audit_log, get_settings, save_settings, i18n
- `auth.php` — verify_password, sign_token, verify_token, RBAC
- `security.php` — CSRF, rate-limit, headers (CSP, X-Frame), upload validation
- `validation.php` — email validation

## 5. API (`api/v1/`)
REST JSON, 36+ эндпоинтов:
- Auth: `POST /auth/login`, `POST /auth/logout`, `GET /me`
- CRUD: pages, catalog, categories, projects, materials, services, reviews, menu_items
- Leads: `POST /leads-public` (публичный), GET/PUT/DELETE (CRM)
- Reviews: `POST /reviews-public` (модерация)
- Media: upload, list, delete
- Settings: GET/PUT
- Notifications: list, read-all
- SEO: sitemap.xml, audit
- Backup: create, list, restore
- System: health, optimize-db, clear-cache

## 6. Public Pages (`pages/`)
- `home.php` — hero, featured projects, catalog, reviews, contacts
- `catalog-hub.php` — категории + все изделия
- `catalog-item.php` — категория / деталь изделия
- `projects.php` — список проектов
- `project.php` — деталь проекта
- `materials.php` — материалы
- `services.php` — услуги
- `contacts.php` — форма заявки + форма отзыва
- `page.php` — CMS page builder (9 типов блоков)
- `sitemap.php`, `robots.php` — SEO

## 7. Admin SPA (`admin/`)
Hash-router, без сборки: `index.html` + `app.js`
- Dashboard, 14 разделов
- Page Builder (hero, text, features, gallery, statistics, faq, cta, team, contact)
- Media Manager, SEO Audit, Backup/Restore, System Health
- Menu Management (сортировка, вкл/выкл)

## 8. Installer (`installer/`)
Чистый PHP, 7 шагов:
1. Проверка системы (PHP, расширения, права на запись)
2. Подключение к MySQL (с CREATE DATABASE fallback)
3. Создание администратора
4. Настройка сайта
5. Демо-контент (опционально)
6. Установка (схема + данные + lock)
7. Результат

## 9. Security
- Все SQL через PDO prepared statements
- XSS: `e()` (htmlspecialchars) на весь вывод
- CSRF: токен на формах, Bearer-header для SPA
- Upload: валидация MIME + расширения, блок PHP в uploads
- Cookie: Secure + HttpOnly + SameSite=Lax
- .htaccess: блок config/, includes/, sql/, storage/data
- CSP, X-Frame-Options, X-Content-Type-Options, Referrer-Policy

## 10. Deployment (Sweb.ru)
```
1. Создайте БД + пользователя в панели Sweb
2. Загрузите файлы по FTP в public_html/
3. Откройте /installer → заполните форму
4. Вход в /admin
```
