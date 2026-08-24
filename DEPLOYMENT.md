# DEPLOYMENT
1. `node server.js` — http://localhost:3000
2. Nginx: proxy_pass 3000, certbot --nginx
3. PM2: `pm2 start server.js --name meb`
4. Env: PORT=3000
5. First visit: /install → create admin → demo → done
6. Backup: /admin → Backup → Create
