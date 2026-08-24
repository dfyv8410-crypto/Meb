# INSTALL

## Требования
- Node.js ≥ 8 (рекомендуется 18+)
- 100 МБ на диск

## Установка за 3 шага
```bash
git clone https://github.com/dfyv8410-crypto/Meb.git
cd Meb
node scripts/seed.js --demo        # админ + демо-контент
node server.js                     # → http://localhost:3000
```

Опции seed (все необязательны):
```
--email=you@site.ru --password=Secret123 --name=Имя --siteName=MEB --phone=+7...
```
Без `--demo` будет создан только админ и настройки.

Веб-инсталлер удалён (v1.8): первичное наполнение — только через CLI, чтобы на сервере не было открытого установщика. Чтобы начать с нуля — очистите `storage/data/*.json` и запустите seed снова.


## Доступы после демо-установки
- Админка: `/admin` → admin@meb.local / Admin123!

## Продакшн
```bash
PORT=80 node server.js          # или Nginx proxy_pass → 3000
pm2 start server.js --name meb  # автоперезапуск
certbot --nginx                 # HTTPS
```
