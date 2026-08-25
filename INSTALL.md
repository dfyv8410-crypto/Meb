# INSTALL

## Требования
- Node.js ≥ 8 (рекомендуется 18+)
- 100 МБ на диск

## Установка: 2 способа

### Веб-инсталлер (рекомендуется)
```bash
git clone https://github.com/dfyv8410-crypto/Meb.git
cd Meb
node server.js
```
Откройте `http://localhost:3000/install` — мастер проведёт за 4 шага.

### CLI (для серверов без браузера)
```bash
node scripts/seed.js --demo --email=you@site.ru --password=Secret
node server.js
```


## Доступы после демо-установки
- Админка: `/admin` → admin@meb.local / Admin123!

## Продакшн
```bash
PORT=80 node server.js          # или Nginx proxy_pass → 3000
pm2 start server.js --name meb  # автоперезапуск
certbot --nginx                 # HTTPS
```
