# SECURITY
- Password: PBKDF2 100k iter SHA256 + random salt
- Auth: HMAC-SHA256 signed token, httpOnly cookie, SameSite=Lax
- CSRF: token per form (admin), SameSite + Origin check
- Rate limit: 60/min IP, 10/min login
- Headers: CSP, X-Frame-Options DENY, X-Content-Type-Options nosniff, Referrer-Policy
- Validation: sanitize HTML chars, email regex, required fields, file size 15MB, mime whitelist
- Upload: stored outside web root logic, served via /storage/uploads with controlled name
- RBAC: super_admin>admin>manager>editor, can() checks per route
- Audit log: all mutations + login/backup/restore
