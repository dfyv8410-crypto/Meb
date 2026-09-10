# CHANGELOG

## 2.2.2 — Живые фото коллекции и баннеров закоммичены в репозиторий
- **Причина «двух вертикальных полосок» вместо фото на мобильной главной**: файлы
  изображений, на которые ссылается живой контент (товар `Гардеробная AIR`, баннеры,
  медиатека), хранились в `uploads/`, но не были закоммичены в git. На любом свежем
  локалхосте/деплое `/uploads/tl3tcp06710.png` отдавал `404`, и мобильный webview
  рисовал вместо фотографии broken-image-глиф («две узкие полоски»).
- Доказано HTTP-тестом: при отсутствии файла и оригинал (`/uploads/…`) и все варианты
  (`/img/var/{w}x{h}/…`) отдают `404`; после добавления файла все кандидаты srcset
  отдают `200 image/webp` корректных размеров (320×240…1280×960) плюс оригинал PNG.
- Добавлены в git: `uploads/tl3tcp06710.png` (товар AIR), `tl3siu96107.jpeg`,
  `tl3tf639135.png`, `tl3tgx71646.png`, `tl3thd79473.png` (баннеры/медиатека).
  Исходный снимок — полноценный кадр 1448×1086 (профиль яркости по всем колонкам
  плавный, непрозрачный) — две «полоски» в файле отсутствуют.
- CSS/API/БД/роутинг не менялись; система responsive-изображений (`includes/image.php`,
  `/img/var`) не тронута.

## 2.2.1 — Локальный запуск одной командой
- `scripts/start-dev.sh`: поднимает MariaDB/MySQL (если не запущена) и `php -S` через
  `dev-router.php` (маршрутизация как в `.htaccess`); читает `HOST`/`PORT`, идемпотентен
  (PID-файл), печатает ссылки на сайт/админку/API и способ остановки.
- README: новый раздел «Локальная разработка (localhost)» — команда запуска,
  альтернативные адреса, предупреждение, что `php -S` без `dev-router.php` ломает
  админку/API (это не баг, а особенности локального сервера).

## 2.2.0 — Категории каталога: полное управление в админке
- **Новый раздел «Категории»** в админ-панели (`/admin`, меню → Каталог → Категории):
  список (название, родитель, slug, число товаров, порядок, статус) + создание /
  редактирование / удаление. Поля: название, slug (URL `/catalog/<slug>`), родительская
  категория, описание, обложка (медиа-пикер), порядок сортировки, активность (публикация).
- **БД**: `sql/migration-categories.sql` — добавлены `parent_id` (иерархия) и
  `is_active` (публикация) в `catalog_categories`; `sql/schema.sql` и `DATABASE.md` обновлены.
  Применено на живую БД (MariaDB `ADD COLUMN IF NOT EXISTS` + `INSERT IGNORE` в `migrations`).
- **API `/api/v1/categories`**: валидация slug (автогенерация из кириллицы, уникальность → 409),
  guard родителя (существование, не сам себласть), нормализация `is_active`/`sort`/`cover`.
  Удаление с безопасным каскадом: подкатегории повышаются до родителя, товары переносятся
  в родительскую категорию, а при отсутствии родителя удаление блокируется (`409`) — товары
  никогда не остаются недостижимыми.
- **Публичный сайт**: `public_categories()` (только активные + сортировка) в `catalog-hub`,
  `catalog-item`, `home`, `sitemap`, навигации и `seo`; скрытая категория уходит с сайта,
  её страница отдаёт 404, ссылки на неактивные категории исчезают из sitemap.xml. Хаб и
  главная больше не эмитят мёртвые ссылки на товары с битыми/удалёнными категориями.
- **Админ-форма товара**: поле «Категория» заменено на выпадающий список (с подписью-
  подсказкой); редактор товара показывает актуальный список категорий.
- Версия админ-SPA обновлена (`?v=2026-09-09`). Мобильная вёрстка админки не изменилась
  (sidebar/гамбургер на ≤900px — категории работают на мобильных так же).
- Скрипты: `integration-test` 62/62 PASS; API e2e категорий и публичный тест скрытых
  категорий — ALL PASS.

## 2.1.0 — Premium Visual Redesign
- **Public site**: full quiet-luxury redesign — warm ivory/charcoal/stone palette, Cormorant Garamond +
  Manrope typography, editorial hero slider (no CTA buttons, link-arrow only), numbered sections 01–06,
  frame-based cards, materials h-scroll, CTA dark band, full-height burger menu, expanded footer, skip-link
  + reduced-motion/print/high-contrast support.
- `public/assets/css/premium.css` rewritten as a design system; `pages/layout.php`, `pages/home.php`,
  `public/assets/js/app.js` rebuilt; secondary pages aligned to new tokens; 404/error pages restyled.
- No backend/API/DB/route changes; all script gates green (final-verify/public/e2e/integration/health/
  selftest/api/production-audit).

## 2.0.1 — Audit & Repair Pass
- **Починен реальный runtime-crash** в `api/v1/crud.php`: `collection_update()` возвращал
  `null` при исчезнувшей строке (TOCTOU) → PHP `TypeError`. Добавлен guard `fail('Not found', 404)`.
- **Logo/Favicon**: найдена и устранена единственная поломка цепочки — в whitelist загрузок
  `api/v1/media.php` добавлено расширение `.ico` (favicon теперь загружается и отдаётся).
- **Баннеры**: исправлены 3 разрыва цепочки — публичный рендер читал `banner.url`, а не
  `button_url`; биндинг формы (heading→subtitle) был перепутан; round-trip `datetime-local`
  ломал редактор. Добавлены `toggleBanner`, `previewBanner` (overlay 16:6) и кэш.
- **Медиа**: drop-zone (drag&drop), lightbox-предпросмотр, надёжный `copyUrl`, guard ошибок загрузки.
- **Настройки**: секции Основные/Контакты/Брендинг/Соцсети/SEO/Open Graph/**Аналитика (YM/GA4/GTM)**;
  инъекция счётчиков серверно в `<head>`.
- **Диагностика 10 → 13 категорий**: `media`, `banners`, `logs`; классификатор логов
  (severity/WHAT/WHERE/fix), байки `http`-кодов, Error Center с «Где» и «→ Исправление».
- **Тесты**: `scripts/health-test.php` расширен цепочками Медиа/Брендинг/Баннеры +
  регрессия PHP7.1/секретов; статический прогон: 51 PHP-файл сбалансирован, JS валиден.
- **Дизайн (premium furniture brand)**: тёмный sidebar с группировкой (4 секции), палитра
  Deep Charcoal/Soft Ivory/Muted Bronze/Warm Beige в админке и на публичной части, тёмный
  footer, калибровка токенов/оверлеев, responsive 320–1920 без потери разделов.
- **Документация**: `REPAIR_REPORT.md` (FOUND/ROOT CAUSES/REPAIRED/IMPROVED/DESIGN/TESTS/
  SWEB/REMAINING), дополнены `SWEB_DEPLOY.md`, `API.md`, `ADMIN_GUIDE.md`.

### 2.0.1 Hardening (второй проход аудита, исправлены только line-verified дефекты)
- **Безопасность API**: `GET /api/v1/settings` теперь **admin+** (полные настройки содержат
  секреты smtp.pass/fcm.key; публичный сайт читает их серверно через `get_settings()`).
  Аноним → 403; `scripts/production-audit.php` перевыравнен (чтения с токеном, editor GET → 403).
- **API-ошибки**: `includes/router.php` отдаёт структурированный JSON `{success:false,error}`
  для `/api/v1*` (раньше мог утекать внутренний текст), для страниц — фирменный 500; детали — только в error.log.
- **RBAC users**: только super_admin создаёт/назначает/меняет/удаляет admin- и super_admin-записи;
  самоэскалация запрещена.
- **Rate limit отзывов**: `reviews-public.php` читает инкрементируемый `count`
  (было `COUNT(*)`=1 — лимит не срабатывал никогда); схема `rate_limits` уже имела PK — без миграций.
- **Бэкапы**: `backup.php` бросает исключение при неудачной записи файла; restore обёрнут в ОДИН
  транзакционный блок по всем таблицам с rollback.
- **Заявки**: письмо-уведомление ставятся best-effort (невалит заявку); ошибка почты пишется в
  `meta` уведомления (было — у заявки).
- **Установщик**: неудачная запись `config/app.php` или `MEB_LOCK_FILE` → исключение, не тихий провал.
- **System**: CSRF для `optimize-db` и `clear-cache`.
- **Публичный контент**: новый `public_rows()` в `crud.php` (кэш SHOW COLUMNS) отсекает черновики
  (pages/projects/catalog `published`, banners/menu_items `is_active`) — применён на главной,
  /projects, /project/:slug, /catalog(/:cat[/:slug]), /p/:slug (черновик → 404), sitemap.xml.
- **Sitemap/robots/canonical**: абсолютные URL через новый `meb_origin()` (base_url или scheme+host);
  раньше при пустом base_url эмитились относительные `<loc>`.
- **JS-совместимость (Node 8.10)**: убраны `Object.fromEntries` (app.js public, contacts.php);
  в `admin/app.js` все `?.`/`??` заменены на ES2017 (рабочие эквиваленты `&&`/`dv()`).
- **Page Builder**: `savePage` шлёт явное snake_case-тело (было — camelCase из конструктора →
  MySQL 1054 при каждом сохранении); блоки features/statistics/team нормализуют ключи;
  чекбоксы `approved`/`published` используют `Number(...)===1` (было `=== false`/`'0'` — никогда не сходилось).
- **REPAIR_REPORT.md §10** — зафиксирован весь второй проход с честным статусом
  **NOT VERIFIED ON SWEB** (статически: 23 PHP-файла сбалансированы, оба JS проходят `node --check`).
- **Структура проекта / удалён слой `sweb/`**: каталога `sweb/` в проекте нет и никогда не было в
  PHP-версии — приложение живёт непосредственно в корне hosting document root
  (`index.php`, `.htaccess`, `admin/`, `api/`, `config/`, `installer/`, `storage/`, …). Добавлена
  секция **`[Structure (root, no sweb layer)]`** в `scripts/health-test.php`: проверяет корневой
  layout (15 обязательных элементов), отсутствие `sweb/`-каталога (на корне и вложенно), отсутствие
  `sweb/`-путевых ссылок во всех кодовых файлах (regex с границами разделителей — «PHP/Sweb port»
  словом не считается) и отсутствие сегмента `/sweb` в `base_url`. Статический прогон: **PASS**.

### 2.0.1 Hardening (третий проход аудита — верификация + 4 line-verified фикса)
- **Верифицировано без изменений**: `rate_limited()` — per-IP корректен (identity в ключе bucket'а,
  `'lead:'.$ip`; `ip=''` по дизайну; reviews-public — свой IP-scoped upsert по PK `bucket,ip,window_ts`);
  `.htaccess`+uploads — тройная защита PHP-execution в uploads и запрет листинга/бэкапов/config;
  `index.php` static-serve с `realpath()`+root check (нет traversal). Полная матрица endpoint × роль —
  в `REPAIR_REPORT.md §10.8`.
- **Утечка исключений в JSON 500**: `system.php` (optimize-db) и `backup.php` (restore) передавали
  `$e->getMessage()` в ответ — детали ушли в `error.log`, клиенту generic-сообщение (инвариант no-leak).
- **CRLF header-injection в `mail()`**: имя заявителя шло сырым в subject письма
  (`leads.php`) — теперь в name/phone/email/message/source вырезаются control-символы
  (`[\r\n\x00-\x1F\x7F]`) до вставки в БД и формирования письма.
- **DoS via unbounded body**: `read_body()` читал `php://input` без лимита (JSON мимо
  `post_max_size`) — добавлен `Content-Length > 20 MB → 413` (медиа 15 MB работает).
- **Статическая регрессия после фиксов**: delimiter-баланс **ALL BALANCED**, `swebref` **ZERO HITS**,
  `node --check` **PASS** (admin/app.js, public/assets/js/app.js, scripts/seed.js).

### 2.0.1 — Final Master gate (креды, self-gate, финальный отчёт)
- **Креды из исходников**: боевой суперадмин (`<email>`/`<pass>`) жёстко прописан в
  `scripts/production-audit.php` в 3 местах → теперь берётся из `AUDIT_ADMIN_EMAIL` /
  `AUDIT_ADMIN_PASSWORD` (env); без переменных — понятный `SKIPPED / CONFIGURATION REQUIRED`,
  exit code 2, без fallback-пароля в коде. Это также чинит `health-test.php` secrets-гейт
  (который на хосте падал из-за самих кред в scripts/).
- **Self-gate false FAIL**: `health-test.php` сканировал `scripts/` включая сам себя —
  литералы паттернов (`'fn ('`, `'??='`, `'str_contains'`, `'array_is_list'` и секретные строки)
  давали **ложный FAIL на хосте**. Добавлен self-exclusion (как в sweb-скане).
- **Секреты**: в кодовой базе (кроме живого `config/database.php`, который `.gitignore`d вместе
  с `config/app.php`) — 0 вхождений боевых кредов; `admin@meb.local` — только
  в dev-фикстурах и исторических бэкап-данных (web-закрыты).
- **`REPAIR_REPORT.md §10.9 + §11`**: фикс-таблица, финальный acceptance-матрикс и итоговый отчёт
  (TOTAL/severity/decision). Статус честный: **CODE COMPLETE / PRODUCTION VALIDATION PENDING** —
  не READY и не FAILED до реального зелёного Sweb-прогона.

### 2.0.1 — Diagnostics false-alarm overhaul (четвёртый проход аудита, дефектов не найдено)
- **Диагноз**: on-host System Health показывал 81/92 · 88% (7 ERROR) — все ложные, порождены
  самой диагностикой: битый regex `#storage/\(data\|backups\.lock#` (никогда не совпадает с
  реальным `RewriteRule ^storage/(data|backups|installed\.lock)`), агрегация нестандартных строк
  журнала в `error` и чтение CLI-артефактов песочницы как боевых ошибок (все 41 строки
  `error.log` — headers-конфликты от `scripts/*.php` тестов, 2026-09-01).
- **`api/v1/diagnostics.php`**: у каждого результата — уникальный ID (`CORE-001`, `SEC-CONFIG-001`,
  `SEO-ROBOTS-001`…); переписан классификатор журнала `diag_classify_log($line,$cutoffTs)` —
  фреймы стек-трейса гасятся (multiline = одна запись), записи старше 3 суток → исторические
  `ok` с датой, CLI-артефакты headers → `ok`, `collection_update()` null → «ИСПРАВЛЕНА»
  (guard crud.php:91), `[EXCEPTION]/FATAL/PDO/HTTP 500` → error или исторический ok, `[2]` →
  warning, notice → ok, EADDRINUSE → dev-пометка, нестандартное → `notverified` (никогда error).
- **Секция LOGS**: вывод хвоста (80 строк), dedupe по `title|where`, счётчик исторических,
  итоговый пункт.
- **Секция SECURITY** — поведенческие HTTP-probe вместо проверки наличия `.htaccess`:
  config/includes/sql/storage/backups → блок 403/404/405/410, PHP-exec probe в uploads → только
  403 доказывает блок; 2xx на пробах = **критичная реальная утечка**; без HTTP (CLI) → `NOT
  TESTABLE`, warning — только если нет и правила.
- **Без ложных alarm** в других местах: кэш → `notconfigured` (INFO, не роняет статус);
  «нет активных баннеров» → CONTENT WARNING явно «не ошибка безопасности»; robots/sitemap →
  HTTP-проверка `200`+тело (`SEO-ROBOTS-001`/`SEO-SITEMAP-001`); агрегат статусов: ошибка
  только при реальной ошибке, `notverified` = «Внимание».
- **Frontend**: карточки проблем и экспорт отчёта теперь содержат `id`, `date`, `where`.
- **Статическая регрессия**: phpbal `OK` (целевой файл сбалансирован), порт классификатора на
  реальном `error.log` → 3 dedupe-записи, 0 активных ошибок; regex `.htaccess` сверены;
  `node --check` PASS (admin/app.js). Ожидаемо на Sweb: 0 errors / 0 critical, только честные
  warning/notverified.

## 2.0.0 — PHP/MySQL Production Release
- **Полный переход на PHP 7.1+/MySQL** — Node.js полностью удалён
- **Безопасность**: SQL injection fix (install.php), XSS fix (public JS), JWT secret randomization, CSRF hardening
- **Публичные страницы**: редизайн (premium CSS, warm minimalism), contact data из БД, динамическое меню
- **Page Builder**: 9 типов блоков, JSON декодирование на уровне БД
- **Отзывы**: модерация (pending → approved → показ на сайте), публичная форма с рейтингом
- **Sitemap**: changefreq + priority, исправлен categorySlug
- **Тесты**: 282/282 (selftest 33, integration 62, e2e 70, public 30, api 36, final-verify 51)
- **Документация**: обновлены README, INSTALL, ARCHITECTURE, SECURITY, API, DATABASE, ADMIN_GUIDE, DEPLOYMENT
- **Legacy**: удалены server.js, core/, frontend/, modules/

## 1.9.0 — Веб-инсталлер восстановлен + Android APK + Download API
- **Веб-инсталлер восстановлен** (/install): 7-шаговый мастер, сохраняет installed.lock
- **Android APK**: Java-приложение (WebView admin + PIN-блокировка + настройки сервера), собирается без Gradle (aapt+javac+dx+jarsigner)
- **API скачивания**: GET /api/v1/app/latest (инфо) + GET /api/v1/app/download (APK-файл)
- **Admin кнопка**: «Скачать APK» в разделе Система
- seed.js создаёт installed.lock (совместимость с веб-инсталлером)
- VERSION читается через fs.readFileSync (совместимость с Node 8)
- Тесты: 52/52 PASS

## 1.8.0 — Удаление веб-инсталлера
- **Веб-инсталлер полностью удалён** (/install, API install/*): на сервере больше нет открытого установщика
- Вместо него — CLI: `node scripts/seed.js --demo [--email=.. --password=.. --siteName=.. --phone=..]`
- `process.on(uncaughtException)` — краши пишутся в storage/server-error.log вместо молчаливой смерти
- Ссылка «Installer» убрана из футера сайта; robots.txt очищен
- Тесты: smoke 53 ×2 идемпотентно; внешний e2e-прогон 59/59 (страницы, CMS, CRUD, заявки+уведомления, отзывы-модерация, RBAC менеджера, медиа+папки, бэкапы, аналитика, SEO-аудит, audit log, update-check, XSS-санитизация, path traversal, битый токен)

## 1.7.0 — Портфолио, хаб каталога, контакты + быстрые действия
- **/projects** — страница портфолио: все опубликованные проекты в masonry-сетке
- **/catalog** — хаб каталога: все категории с количеством позиций
- **/contacts** — контакты с формой заявки, телефоном/email/адресом, кнопками WhatsApp и Telegram, встраиваемой картой (поле `mapEmbed` в Настройках)
- **Админка: отзывы** — переключатель «опубликован / скрыт» прямо в списке (модерация одним кликом)
- **Админка: заявки** — смена статуса выпадающим списком прямо в таблице
- **Android**: смена статуса заявки из списка (цветовая индикация, офлайн-режим блокирует изменение, кэш обновляется)
- Навигация и sitemap обновлены (6 статических URL); тесты: 55 сценариев ×2 идемпотентных прогона

## 1.6.0 — Performance + медиа-папки + страницы материалов/услуг
- **Gzip** сжатие текстовых ответов (HTML/CSS/JS/SVG) >1KB
- **ETag + 304**: браузерный кэш статики, immutable для uploads (max-age=31536000)
- `/materials` — страница материалов, группировка по типу (дерево/камень/металл…)
- `/services` — страница услуг с ценами «от»
- **Папки в медиа**: загрузка в подпапку, фильтры-табы в админке
- **Видео в проектах**: YouTube/Vimeo iframe или прямой mp4 на странице проекта
- Навигация сайта ведёт на реальные страницы (/catalog/:cat, /materials, /services)
- Sitemap: 15 URL; тесты: 49 сценариев

## 1.5.0 — Аналитика, авто-бэкапы, уведомления
- **Pageviews**: подсчёт просмотров публичных страниц (без API/ассетов), график за 14 дней на дашборде
- **Авто-бэкапы по расписанию**: daily/weekly в Настройках; уведомление о создании
- **Уведомления**: очередь в админке с колокольчиком непрочитанных; новая заявка → уведомление
- **Email**: встроенный SMTP-клиент без зависимостей (core/mailer.js), настройка в Настройках
- **Android push**: FCM legacy API — укажите Server Key + topic, заявки прилетают в приложение
- **Медиа**: автоматическое определение размеров PNG/JPEG/GIF/WebP при загрузке
- **Тёмная тема админки** 🌓 с сохранением выбора
- Тесты: 43 сценария

## 1.4.0 — Детальные страницы + i18n
- `/project/:slug` — страница проекта: галерея, материалы, площадь, особенности, CTA
- `/catalog/:cat` — категория каталога с ценами и карточками
- `/catalog/:cat/:slug` — карточка мебели с характеристиками (размеры, фасады, фурнитура)
- Карточки на главной стали кликабельными → детальные страницы
- **i18n**: core/i18n.js + RU/EN локали; переключение `?lang=en` или Accept-Language
- Sitemap: страницы + проекты + категории + товары
- Мобильное меню-бургер на главной

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
