# SECURITY — MEB CMS

## Аутентификация
- Password: `password_hash(PASSWORD_DEFAULT)` (bcrypt) + `password_verify()`
- Auth: JWT (HMAC-SHA256, random 48-char secret) + httpOnly cookie
- Cookie: Secure + HttpOnly + SameSite=Lax

## CSRF
- Токен на формах (admin), Bearer-header для SPA (csrf免疫)

## Rate Limiting
- Login: 10 попыток/IP за 10 минут
- Reviews: 3/IP за 10 минут

## Security Headers
- Content-Security-Policy (default-src 'self' + Google Fonts)
- X-Frame-Options: DENY
- X-Content-Type-Options: nosniff
- Referrer-Policy: strict-origin-when-cross-origin
- Permissions-Policy: camera=(), microphone=()

## Валидация
- XSS: `e()` (htmlspecialchars) на весь вывод
- SQL: Prepared statements (PDO), safe_ident() для имён таблиц/столбцов
- Email: `strtolower(trim())` нормализация
- Upload: MIME + расширение + размер (15MB), блок PHP в uploads

## RBAC
- super_admin > admin > manager > editor
- can() проверки на каждом маршруте

## Audit Log
- Все мутации логируются: user_id, action, entity, entity_id, ip

## Apache (.htaccess)
- Блок: config/, includes/, sql/, storage/data, storage/backups
- Блок PHP в uploads/
- Security headers (mod_headers)
