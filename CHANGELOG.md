# CHANGELOG

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
