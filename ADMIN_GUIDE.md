# ADMIN GUIDE — Руководство администратора

Вход: `/admin`. Меню сверху, всё в 2–3 клика.

## 🏠 Главная (Dashboard)
Счётчики (заявки, проекты, каталог), статус системы, быстрые действия:
**+ Проект · + Каталог · Заявки · Создать бэкап**

## 📄 Страницы
Конструктор: у страницы есть блоки (hero, text, features, gallery, statistics, faq, cta, team, contact).
Поле `blocks` принимает JSON-массив:
```json
[
 {"type":"hero","data":{"title":"Точность","subtitle":"Подзаголовок","ctaLabel":"Обсудить","ctaUrl":"#contacts"}},
 {"type":"features","data":{"items":[{"title":"Замер","desc":"Бесплатно","kicker":"01"}]}},
 {"type":"cta","data":{"title":"Готовы начать?"}}
]
```
Страница доступна на `/p/<slug>`. Скрытие блока: `"hidden": true`.

## 🪑 Каталог / 🏗️ Проекты
Добавить → title + slug + описание. Доп. поля через JSON (`images`, `price`, `featured`, `published`, `categoryId`).

## 📩 Заявки
Публичная форма пишет сюда автоматически. Статусы: new → in_progress → contacted → done / rejected.

## 🖼️ Медиа
Загрузка файла + ALT (важно для SEO). Файлы получают постоянный URL `/storage/uploads/...`.

## 👥 Пользователи
Роли: **super_admin > admin > manager > editor**. Manager не видит backup, users, настройки. Последнего админа удалить нельзя.

## ⚙️ Настройки
Название сайта, телефон, email — сразу на сайте и в формах.

## 💾 Бэкап
«Создать» → снапшот всех данных. «Restore» перед восстановлением сам делает страховочную копию `pre-restore-*`.
