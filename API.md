# API v1 — `/api/v1`

Все ответы `application/json`. Auth: `Authorization: Bearer <token>`.

## Auth
- `POST /api/v1/auth/login` {email, password} → {token, user}
- `POST /api/v1/auth/logout` — удаление cookie
- `GET  /api/v1/me` → текущий пользователь
- `POST /api/v1/auth/register` (super_admin) {email, password, name, role}

## Pages (Page Builder)
- `GET    /api/v1/pages` — все страницы
- `POST   /api/v1/pages` (editor+) — создать
- `PUT    /api/v1/pages/:id` — обновить
- `DELETE /api/v1/pages/:id` — удалить

## Catalog
- `GET    /api/v1/categories` — категории
- `POST   /api/v1/categories` (editor+) — создать
- `PUT    /api/v1/categories/:id` — обновить
- `DELETE /api/v1/categories/:id` — удалить
- Поля категории: `title` (обязателен), `slug` (пусто → автогенерация из кириллицы; дубликат → 409),
  `parent_id` (существующий id другой категории, не сам объект; пусто = корневая),
  `description`, `cover`, `sort` (порядок), `is_active` (1/0 — публикация на сайте).
- Удаление с безопасным каскадом: подкатегории и товары переносятся в `parent_id` удаляемой;
  если родителя нет, а товары есть → `409` (удаление заблокировано).
- `GET    /api/v1/catalog` — все изделия
- `POST   /api/v1/catalog` (editor+) — создать
- `PUT    /api/v1/catalog/:id` — обновить
- `DELETE /api/v1/catalog/:id` — удалить

## Projects / Materials / Services / Reviews
REST: `GET /api/v1/{projects,materials,services,reviews}` + CRUD (editor+)

## Leads (CRM)
- `GET  /api/v1/leads` — все заявки (manager+)
- `POST /api/v1/leads` — публичное создание заявки (без auth)
- `PUT  /api/v1/leads/:id` — обновить статус/комментарий

## Reviews (Public)
- `POST /api/v1/reviews-public` — отправить отзыв (модерация)

## Media
- `POST /api/v1/media/upload` — загрузка (base64, auth). Хард-проверки: реальный MIME (finfo),
  для растров — getimagesize + лимиты 12000×12000 / 40 МП, для SVG — запрет скриптов и внешнего
  содержимого. После загрузки автоматически создаются производные 4:3 (480…1280) и 16:9 1280×720.
- `GET  /api/v1/media` — список (auth)
- `DELETE /api/v1/media/:id` — удалить (auth)

## Watermark (предпросмотр, admin-only)
- `GET /api/v1/watermark/preview?opacity=25&size=22&position=br&mode=single[&logo=/uploads/...]`
  — рендерит реальное фото мебели с выбранными настройками (PNG), lives preview для админки.
  Ничего не пишет: ни настроек, ни кэша, ни файлов.

## Menu
- `GET    /api/v1/menu` — пункты меню (сортировка по sort_order)
- `POST   /api/v1/menu` (editor+) — создать
- `PUT    /api/v1/menu/:id` — обновить
- `DELETE /api/v1/menu/:id` — удалить

## Notifications
- `GET  /api/v1/notifications` — список (auth)
- `PUT  /api/v1/notifications/read-all` — пометить все прочитанными

## Settings
- `GET  /api/v1/settings` — настройки (**admin+**, CSRF-safe). Анонимный запрос → 403;
  публичный сайт читает настройки серверно (layout → `get_settings()`), не через этот эндпоинт.
  Обусловлено тем, что настройки содержат секреты (smtp.pass, fcm.key).
- `PUT  /api/v1/settings` — обновить (admin+, CSRF)

## SEO
- `GET /api/v1/seo/sitemap.xml` — XML sitemap
- `GET /api/v1/seo/audit` — аудит (auth)

## Analytics
- `GET /api/v1/analytics` — статистика (admin+)

## Backup
- `POST /api/v1/backup/create` — создать бэкап (admin+)
- `GET  /api/v1/backup/list` — список бэкапов
- `POST /api/v1/backup/restore/:id` — восстановить

## System
- `GET  /api/v1/health` — статус (публичный). Возвращает реальные тесты: БД (SELECT 1), хранилище (запись/чтение/удаление probe-файла), кэш (APCu→файл→нет), бэкап (доступность + число записей). Поля: `status`, `database`, `storage`, `api`, `cache`, `backup` (каждый — `{status, message}`), плюс обратно-совместимые `db`/`storage`/`backups`/`php`/`version`/`runtime`. Секретов и путей не раскрывает.
- `POST /api/v1/system/optimize-db` — оптимизация (super_admin)
- `POST /api/v1/system/clear-cache` — очистка кэша (super_admin)
- `GET  /api/v1/system/update/check` — проверка обновлений (super_admin)
- `GET  /api/v1/system/diagnostics` — полная диагностика (admin+), подробности ниже.

### Diagnostics (`GET /api/v1/system/diagnostics`)
Реальная батарея проверок (без фабрикации «PASS»): `?mode=quick` (только чтение, быстро) и
`?mode=full` (добавляет self-HTTP запросы к главной и публичным API + транзакционный тест
CRUD на временной строке `rate_limits` с откатом). Ответ:
- `overall` — `{status, percent, total, ok, warning, error, notconfigured, notverified, critical, high}`
- `categories` — по группам (Ядро/БД/Хранилище/Кэш/Бэкап/API/Админка/Публичный сайт/Безопасность/Производительность), каждый элемент проверки — `{status, severity, title, message, source, endpoint, fix}`
- `issues` — только не-OK проверки, отсортированы по severity (critical → info), с рекомендациями `fix`
- `checked_at`, `mode`, `version`, `last_run`
Логика статуса: любой `error`/`critical` → общий статус `error`/`critical`; предупреждения →
`warning`; иначе `ok`. Сервер не логирует и не раскрывает: пароли, DSN, абсолютные пути,
содержимое данных. Лёгкий итог сохраняется в `storage/data/last-diagnostics.json`.

## Installer
- `GET  /api/v1/install` — статус установки
- `POST /api/v1/install` — выполнить установку

## Errors
`{success: false, error: "message"}` HTTP 400/401/403/404/429

## Rate Limit
- Login: 10 попыток/IP за 10 минут
- Reviews: 3/IP за 10 минут
