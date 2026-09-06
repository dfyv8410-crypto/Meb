# DEPLOYMENT — MEB CMS

PHP/MySQL version for shared hosting (Sweb.ru). No Node.js, npm, or SSH required.

## Quick Start

1. Create MySQL database + user in Sweb panel
2. Upload project root to `public_html/` via FTP (ensure `.htaccess` is uploaded)
3. Set permissions: `config/`, `storage/`, `uploads/` writable (755/775)
4. Visit `https://your-domain/installer/` — 7-step wizard
5. Login at `/admin` with credentials from step 4

## What Gets Installed

- 18 MySQL tables (catalog, projects, materials, services, reviews, pages, menu_items, etc.)
- Admin user (super_admin)
- Settings (site name, phone, email)
- Optional demo content (catalog, projects, materials, services, reviews, pages, menu items)

## Post-Install

- Delete `installer/index.php` or rename it for security
- Configure settings in `/admin` → Settings (logo, favicon, OG, social links, email notifications)
- Add content through admin panel (catalog items, projects, services, etc.)
- Submit a test review from `/contacts` to verify moderation flow

## Backup / Restore

- **Admin panel:** `/admin` → System → Backup → Create
- **Manual:** FTP download `config/`, `storage/`, `uploads/` + MySQL dump
- **Restore:** Upload files, restore DB dump, delete `storage/installed.lock` if needed

## Health Check

`GET /api/v1/health` — returns JSON with DB status, storage, PHP version, backup count

## Full Deployment Guide

See `SWEB_DEPLOY.md` for detailed step-by-step instructions.
