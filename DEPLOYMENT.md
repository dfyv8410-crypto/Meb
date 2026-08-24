# DEPLOYMENT
1. `node scripts/seed.js --demo --email=... --password=...` — первичное наполнение
2. `node server.js` — http://localhost:3000
3. Nginx: proxy_pass 3000, certbot --nginx
4. PM2: `pm2 start server.js --name meb`
5. Env: PORT=3000
6. Backup: /admin → Backup → Create
