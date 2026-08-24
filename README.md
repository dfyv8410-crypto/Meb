# MEB — Premium Furniture Platform
Премиальная цифровая платформа: сайт + CMS + admin + API + Android app.

## Run
```
node scripts/seed.js --demo   # первичная установка (админ + демо)
node server.js
# → http://localhost:3000
# → /admin (admin@meb.local / Admin123! если demo)
```

## Structure
- `core/` — config, database (JsonStore), auth, security, router
- `frontend/` — премиальный сайт (Manrope + Cormorant, editorial grid, 3D parallax)
- `admin/` — SPA без сборки
- `scripts/seed.js` — CLI первичной установки (веб-инсталлер удалён в v1.8)
- `storage/` — data, uploads, backups
- `mobile/` — Android Kotlin App

## API
См. API.md
