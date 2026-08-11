# Deploying QuizPath to cPanel

**Domain:** mediprep.nokkoo.in  
**Doc root:** `/home/uddjzwrz/mediprep.nokkoo.in`  
**Database:** `uddjzwrz_mediprep` / User: `uddjzwrz_mediprep`

---

## Directory Layout on Server

```
/home/uddjzwrz/mediprep.nokkoo.in/
├── index.html              ← React PWA (web/dist/index.html)
├── assets/                 ← Vite build output (web/dist/assets/)
├── manifest.webmanifest    ← PWA manifest
├── service-worker.js       ← Service worker
├── icons/                  ← PWA icons
├── offline.html            ← Offline fallback
├── .htaccess               ← Root routing (API vs SPA)
└── api/                    ← Laravel app
    ├── app/
    ├── bootstrap/
    ├── config/
    ├── database/
    ├── public/
    │   └── .htaccess       ← Laravel routing
    ├── routes/
    ├── storage/
    ├── vendor/
    ├── .env                ← Production environment
    └── artisan
```

---

## Step-by-Step Deployment

### 1. Upload Laravel API

Via SSH (if available) or File Manager:

```bash
# SSH into server
ssh uddjzwrz@server-ip

# Navigate to doc root
cd /home/uddjzwrz/mediprep.nokkoo.in

# Clone the repo (or upload via File Manager)
git clone https://github.com/importerbrocom/Reactquiz.git temp-clone

# Move API into place
mv temp-clone/api ./api
rm -rf temp-clone
```

Or **upload a ZIP** of the `api/` folder via File Manager and extract it.

### 2. Install Composer Dependencies

Via SSH or cPanel Terminal:

```bash
cd /home/uddjzwrz/mediprep.nokkoo.in/api
php -v  # Confirm PHP 8.2+

# Install Composer if not available
curl -sS https://getcomposer.org/installer | php

# Install dependencies
php composer.phar install --no-dev --optimize-autoloader --no-interaction
```

### 3. Configure Environment

```bash
cd /home/uddjzwrz/mediprep.nokkoo.in/api

# Copy the production env
cp ../deploy/cpanel/.env.production .env

# OR create .env manually and paste the contents

# Generate app key
php artisan key:generate

# Set your database password in .env
nano .env  # Edit DB_PASSWORD=your_actual_password
```

### 4. Set Permissions

```bash
chmod -R 775 storage bootstrap/cache
```

### 5. Run Migrations

```bash
php artisan migrate --force
php artisan db:seed --force  # Seeds roles and permissions
```

### 6. Cache Configuration

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
php artisan storage:link
```

### 7. Create Jobs Table (for database queue)

```bash
php artisan queue:table  # If migration doesn't exist
php artisan migrate --force
```

### 8. Build and Upload Frontend

On your **local machine** (or in the Kiro sandbox):

```bash
cd web
cp .env.example .env

# Edit .env:
# VITE_API_BASE_URL=https://mediprep.nokkoo.in/api/v1
# VITE_API_CONTRACT_VERSION=1

npm install
npm run build
```

Then upload the contents of `web/dist/` to the doc root:

```
web/dist/index.html       → /home/uddjzwrz/mediprep.nokkoo.in/index.html
web/dist/assets/          → /home/uddjzwrz/mediprep.nokkoo.in/assets/
web/dist/service-worker.js → /home/uddjzwrz/mediprep.nokkoo.in/service-worker.js
web/public/manifest.webmanifest → /home/uddjzwrz/mediprep.nokkoo.in/manifest.webmanifest
web/public/offline.html   → /home/uddjzwrz/mediprep.nokkoo.in/offline.html
web/public/icons/         → /home/uddjzwrz/mediprep.nokkoo.in/icons/
```

### 9. Place .htaccess Files

Upload from `deploy/cpanel/`:

```
.htaccess-root  → /home/uddjzwrz/mediprep.nokkoo.in/.htaccess
.htaccess-api   → /home/uddjzwrz/mediprep.nokkoo.in/api/public/.htaccess
```

### 10. Set Up Cron Job (Queue Worker)

In cPanel → Cron Jobs, add (every minute):

```
* * * * * cd /home/uddjzwrz/mediprep.nokkoo.in/api && php artisan schedule:run >> /dev/null 2>&1
```

And for processing queued jobs (every minute):

```
* * * * * cd /home/uddjzwrz/mediprep.nokkoo.in/api && php artisan queue:work database --stop-when-empty --max-time=50 >> /dev/null 2>&1
```

### 11. SSL Certificate

In cPanel → SSL/TLS or Let's Encrypt:
- Enable SSL for `mediprep.nokkoo.in`
- Most hosts do this automatically via AutoSSL

### 12. Verify

Visit:
- `https://mediprep.nokkoo.in` — should show the login page
- `https://mediprep.nokkoo.in/api/v1/up` — should return health check JSON

---

## Updating the App

After code changes:

```bash
cd /home/uddjzwrz/mediprep.nokkoo.in/api
git pull origin main
php composer.phar install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

For frontend changes, rebuild locally and re-upload `web/dist/`.

---

## Limitations on cPanel

| Feature | Status | Workaround |
|---------|--------|------------|
| Queue workers | ⚠️ Cron-based | `queue:work --stop-when-empty` every minute |
| Redis | ❌ Not available | Using file/database cache |
| WebSocket | ❌ Not available | Polling instead |
| Long PDF processing | ⚠️ Limited | 120s max execution time |
| Push notifications | ✅ Works | Cron dispatches reminders |

---

## Troubleshooting

| Issue | Fix |
|-------|-----|
| 500 error | Check `api/storage/logs/laravel.log` |
| 403 forbidden | Check `.htaccess` and file permissions (775 on storage) |
| API returns HTML | Ensure `ForceJsonResponse` middleware is working; check PHP version |
| Frontend blank page | Check browser console; verify `assets/` uploaded correctly |
| Queue not processing | Verify cron job is running: check cPanel → Cron Jobs |
