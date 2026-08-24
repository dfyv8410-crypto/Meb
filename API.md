# API v1 — `/api/v1`

Все ответы `application/json`. Auth: `Authorization: Bearer <token>` или cookie `token`.

## Auth
- `POST /api/v1/auth/login` {email, password} → {token, user}
- `POST /api/v1/auth/logout`
- `GET  /api/v1/me` → user
- `POST /api/v1/auth/register` (super_admin only) {email,password,name,role}

## Pages (Builder)
- `GET    /api/v1/pages` ?published
- `GET    /api/v1/pages/:slug`
- `POST   /api/v1/pages` (editor+)
- `PUT    /api/v1/pages/:id`
- `DELETE /api/v1/pages/:id`

## Catalog
- `GET /api/v1/categories` / `POST /api/v1/categories`
- `GET /api/v1/catalog` ?category,featured
- `GET /api/v1/catalog/:slug`
- `POST /api/v1/catalog` / `PUT /api/v1/catalog/:id` / `DELETE`

## Projects / Materials / Services / Reviews
Аналогично REST: `GET /api/v1/{projects,materials,services,reviews}` + CRUD

## Leads (CRM)
- `GET  /api/v1/leads`
- `POST /api/v1/leads` (public — создание заявки)
- `PUT  /api/v1/leads/:id` {status, comment, managerId}

## Media
- `POST /api/v1/media/upload` multipart/form-data
- `GET  /api/v1/media` ?folder,q
- `DELETE /api/v1/media/:id`

## System
- `GET  /api/v1/health` → {db, storage, cache...}
- `GET  /api/v1/settings` / `PUT /api/v1/settings`
- `POST /api/v1/backup/create` / `POST /api/v1/backup/restore/:id`
- `GET  /api/v1/backup/list`
- `GET  /api/v1/analytics/summary`
- `GET  /api/v1/seo/sitemap.xml` / `GET /api/v1/seo/audit`

## Errors
`{error: "message", code: "VALIDATION_ERROR"}` HTTP 400/401/403/404/429

## Rate Limit
60 req/min/IP, 10 login/min/IP
