# MEB — Premium Furniture Platform
Премиальная цифровая платформа: сайт + CMS + admin + API + installer + Android app.

## Run
```
git clone https://github.com/dfyv8410-crypto/Meb.git && cd Meb
node server.js
# → http://localhost:3000/install (веб-инсталлер)
# → http://localhost:3000/admin
```

## Structure
- `core/` — config, database (JsonStore), auth, security, router
- `frontend/` — премиальный сайт (Manrope + Cormorant, editorial grid, 3D parallax)
- `admin/` — SPA без сборки
- `installer/` — веб-установщик
- `scripts/seed.js` — CLI первичной установки
- `storage/` — data, uploads, backups
- `mobile/` — Android Kotlin App

## API
См. API.md
