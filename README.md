# MEB — Premium Furniture Platform
Премиальная цифровая платформа: сайт + CMS + admin + API + installer + Android app.

## Run
```
node server.js
# → http://localhost:3000
# → /install (первая установка)
# → /admin (admin@meb.local / Admin123! если demo)
```

## Structure
- `core/` — config, database (JsonStore), auth, security, router
- `frontend/` — премиальный сайт (Manrope + Cormorant, editorial grid, 3D parallax)
- `admin/` — SPA без сборки
- `installer/` — 7-шаговый установщик
- `storage/` — data, uploads, backups
- `mobile/` — Android Kotlin App

## API
См. API.md
