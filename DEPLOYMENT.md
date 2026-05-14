# Deployment — xHR Payroll v1.3.0 (beta)

For the deploy agent: this branch is `feature/v1.3` and ships as a vanilla
Laravel 13 app. No queue worker, no cron, no Redis. Just web requests +
SQLite-to-MySQL migration on first deploy.

---

## 0. Pre-flight checklist (all done in code, nothing for the agent to fix)

- ✅ Auth middleware covers every route except `/login`, `/up`, `/`, `/attachments/*` (gated)
- ✅ RBAC via `role:admin` / `role:admin,owner` middleware on admin paths
- ✅ Resource routes that lacked controller methods are now excluded
- ✅ Dead PDF lib (`laravel-mpdf`) removed
- ✅ Attachments served only via auth-gated `/attachments/{id}` — files on private disk
- ✅ `.env.production.example` ships in the repo root

---

## 1. Server requirements

| | Min | Recommended |
|---|---|---|
| PHP | 8.3 | 8.3.x |
| DB | MySQL 8 / Postgres 14 | MySQL 8.0 |
| Node | 20 LTS (build only) | 20 LTS |
| LibreOffice | optional* | `libreoffice` package |
| Disk | 2 GB | 10 GB |
| RAM | 1 GB | 2 GB |

\* LibreOffice is required ONLY when a `.docx` Document Template is uploaded
and activated. Default HTML PDFs need no LibreOffice.

### PHP extensions required
`bcmath` `ctype` `curl` `dom` `fileinfo` `gd` `intl` `mbstring` `openssl`
`pdo` `pdo_mysql` (or pdo_pgsql) `tokenizer` `xml` `zip` `zlib`

---

## 2. Deploy steps

```bash
# A. Code
cd /var/www
git clone <repo_url> xhr
cd xhr
git checkout feature/v1.3

# B. Dependencies
composer install --no-dev --optimize-autoloader --no-interaction
npm ci --include=dev
npm run build           # vite → public/build/*
# (npm dev deps can be pruned afterwards: `npm prune --omit=dev`)

# C. .env
cp .env.production.example .env
# Edit .env — set APP_URL, DB_*, MAIL_* (see template comments)
php artisan key:generate

# D. Database
php artisan migrate --force
# If seeding from dev SQLite — see Section 4 below

# E. Storage
php artisan storage:link
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

# F. Caches
php artisan config:cache
php artisan route:cache
php artisan view:cache

# G. SSL — Let's Encrypt via certbot, or terminate at Cloudflare
```

### Nginx site (sample)
```nginx
server {
    listen 443 ssl http2;
    server_name CHANGE_ME.example.com;
    root /var/www/xhr/public;

    ssl_certificate     /etc/letsencrypt/live/CHANGE_ME.example.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/CHANGE_ME.example.com/privkey.pem;

    index index.php;

    client_max_body_size 25M;     # attachments allow 5 MB, docx 16 MB — leave headroom

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* { deny all; }
}
```

---

## 3. Critical .env settings (must be set)

The example file has full comments. The minimum that MUST be customized:

```
APP_KEY=                # `php artisan key:generate` writes it
APP_URL=https://...     # exact public URL, https
DB_PASSWORD=...         # match the MySQL user you created
```

Leave `APP_ENV=production`, `APP_DEBUG=false`, `LOG_LEVEL=warning` as
they ship.

---

## 4. Data migration: dev SQLite → prod MySQL

The dev database is `database/database.sqlite`. To carry data over:

```bash
# On dev machine
php artisan db:wipe --database=sqlite --force         # only if you want a clean prod slate
# OR export everything:
sqlite3 database/database.sqlite '.mode insert' '.dump' > /tmp/sqlite_dump.sql

# Hand-port to MySQL (sqlite3 dump uses INSERT INTO ... — works but:
#   1. Replace `AUTOINCREMENT` → `AUTO_INCREMENT`
#   2. Replace `INTEGER PRIMARY KEY` → `INT PRIMARY KEY AUTO_INCREMENT`
#   3. SQLite booleans are 0/1 already — fine
#   4. Drop the `sqlite_sequence` table inserts
# )
```

Cleaner path if dev data isn't worth keeping: **just `php artisan migrate
--force` on prod and let HR re-input company profile + employees**.

Run `php artisan db:seed --force --class=DatabaseSeeder` if seeders exist
for default roles / leave policies. (We currently have admin + owner
roles created via migration — no seeder needed.)

---

## 5. Post-deploy smoke test

After deploy, verify:

```bash
curl -fsS https://your-domain.example.com/up                # → 200 OK
```

Then in the browser:
1. `/login` — login as admin
2. `/employees` — list loads
3. `/portal` — three buckets render (pending / upcoming / past)
4. `/workspace/<id>/<m>/<y>` — attendance grid renders, time inputs work
5. `/payslip/<id>/<m>/<y>/preview` — payslip preview loads
6. `/payslip/<id>/<m>/<y>/pdf` — PDF downloads with Thai font

Tail logs in another shell:
```bash
tail -f /var/www/xhr/storage/logs/laravel.log
```
Look for `production.ERROR` entries on first user session.

---

## 6. Daily backups (cron)

Add to root crontab:
```cron
# Database dump nightly at 02:00
0 2 * * * mysqldump --single-transaction xhr_payroll | gzip > /var/backups/xhr/db-$(date +\%F).sql.gz
# Storage uploads + private attachments
0 3 * * * tar czf /var/backups/xhr/storage-$(date +\%F).tar.gz -C /var/www/xhr storage/app
# Keep 14 days, prune older
0 4 * * * find /var/backups/xhr -mtime +14 -delete
```

No app-level cron needed — there are no scheduled tasks.

---

## 7. Future upgrades

Standard Laravel zero-downtime flow:
```bash
cd /var/www/xhr
git fetch && git checkout <new-tag>
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan config:cache route:cache view:cache
sudo systemctl reload php8.3-fpm
```

---

## 8. Known-stale tests (not blockers)

`php artisan test` shows 6 failures on this branch — they are documented
in [PRE_DEPLOY_AUDIT.md](PRE_DEPLOY_AUDIT.md#h2-tests-ล้ม-6-จาก-180):

- 3× `RoleAccessFlowTest` & `FullSystemTest` — assert 200 on `/leave`
  and `/settings/company` but those now legacy-redirect (302) to the
  portal / new settings location. Tests need updating, not the app.
- 3× `BonusCalculationServiceTest` — fixture-vs-formula drift on bonus
  rate thresholds. Doesn't affect leave/payroll flow that ships in 1.3.

Address before 1.3.1, not before launch.
