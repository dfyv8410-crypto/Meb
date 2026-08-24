# INSTALL

## Требования
- Node.js ≥ 8 (рекомендуется 18+)
- 100 МБ на диск

## Установка за 3 шага
```bash
git clone https://github.com/dfyv8410-crypto/Meb.git
cd Meb
node server.js
```

Откройте `http://localhost:3000/install`:

1. **Проверка** — система проверит окружение; выберите демо-контент или пустую установку
2. **Админ** — email + пароль супер-админа
3. **Сайт** — название, телефон → «Установить»

После установки installer блокируется (`storage/installed.lock`).

## Доступы после демо-установки
- Админка: `/admin` → admin@meb.local / Admin123!

## Продакшн
```bash
PORT=80 node server.js          # или Nginx proxy_pass → 3000
pm2 start server.js --name meb  # автоперезапуск
certbot --nginx                 # HTTPS
```
