# Going live

A step-by-step for an Ubuntu 22.04/24.04 VPS (DigitalOcean, Vultr, a local Bangladeshi host…). Replace `example.com` with your domain and `/var/www/technest` with your folder. When it's done, **Admin → System Status** should show every item green.

## 1. Server packages

```bash
sudo apt update && sudo apt install -y nginx postgresql supervisor git unzip certbot python3-certbot-nginx \
  php8.3-fpm php8.3-pgsql php8.3-mbstring php8.3-xml php8.3-curl php8.3-zip php8.3-gd php8.3-intl php8.3-bcmath
curl -sS https://getcomposer.org/installer | sudo php -- --install-dir=/usr/local/bin --filename=composer
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo bash - && sudo apt install -y nodejs
```

**Redis (recommended, optional).** With `CACHE_STORE=auto` (the default) the shop uses Redis when it is installed and running, and the file cache when it isn't:

```bash
sudo apt install -y redis-server php8.3-redis
sudo systemctl enable --now redis-server
sudo systemctl restart php8.3-fpm
php artisan cache:driver   # shows which cache is in use
```

Sessions follow the same rule (`SESSION_DRIVER=auto`: Redis, otherwise the database). If Redis stops later, the shop keeps working on the file cache and switches back to Redis within a minute of it returning. Settings → System Status shows the cache in use. For a Redis server on another machine, set `REDIS_HOST`, `REDIS_PORT` and `REDIS_PASSWORD` in `.env`.

## 2. Database

```bash
sudo -u postgres psql -c "CREATE USER technest WITH PASSWORD 'a-long-random-password';"
sudo -u postgres psql -c "CREATE DATABASE technest OWNER technest;"
```

## 3. Code

```bash
sudo mkdir -p /var/www/technest && sudo chown $USER:www-data /var/www/technest
git clone https://github.com/yeapasneo/ecommerce.git /var/www/technest
cd /var/www/technest
cp .env.example .env
```

Edit `.env`:

| Setting | Value |
|---|---|
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_URL` | `https://example.com` |
| `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` | from step 2 |
| `QUEUE_CONNECTION` | `database` |
| `SESSION_SECURE_COOKIE` | `true` |

Mail can be set in `.env` (`MAIL_*`) or later in **Settings → Notifications**.

```bash
composer install --no-dev --optimize-autoloader
php artisan key:generate
npm ci && npm run build
php artisan migrate --seed --force      # first time only; loads the catalog
php artisan storage:link
php artisan optimize
sudo chown -R $USER:www-data storage bootstrap/cache && sudo chmod -R ug+rw storage bootstrap/cache
```

**Change the admin password right away.** The seeded admin is `admin@technest.test` / `password`. Log in, open **My profile**, change the email and password.

## 4. Web server and HTTPS

```bash
sudo cp deploy/nginx.conf /etc/nginx/sites-available/technest     # then edit the domain and paths
sudo ln -s /etc/nginx/sites-available/technest /etc/nginx/sites-enabled/
sudo certbot --nginx -d example.com -d www.example.com
sudo nginx -t && sudo systemctl reload nginx
```

## 5. Background jobs

Queue worker (sends SMS and emails):

```bash
sudo cp deploy/supervisor-queue.conf /etc/supervisor/conf.d/technest-queue.conf
sudo supervisorctl reread && sudo supervisorctl update
```

Scheduler (courier status sync every 30 min, nightly backup at 02:30, clean-ups). Add one line with `sudo crontab -u www-data -e`:

```
* * * * * cd /var/www/technest && php artisan schedule:run >> /dev/null 2>&1
```

Backups are written to `storage/app/backups` (the newest 14 are kept). Download them from **System Status** or copy them off the server regularly, e.g. with `rclone` to Google Drive. Restore with `pg_restore --clean -d technest file.dump`.

## 6. Connect services

In **Settings**:

- **Payments**: live credentials, sandbox off. Add the SSLCommerz IPN URL in their panel.
- **Couriers**: API keys, then paste the webhook URL and token into the Steadfast / Pathao panels.
- **Notifications**: SMS gateway and SMTP. Use **Send test** for both.
- **Fraud check**: optional BD Courier key.
- **SEO**: submit `https://example.com/sitemap.xml` in Google Search Console. Make sure "Hide the whole site" is off.

## Updating

```bash
cd /var/www/technest && bash deploy/deploy.sh
```

## Speed checklist

- `DB_PERSISTENT=true` in `.env` (php-fpm keeps database connections open: about 10 ms faster per page).
- `APP_ENV=production` and `APP_DEBUG=false` (development mode also records every request for Inertia's developer tools, which adds 30–60 ms).
- `php artisan optimize` after every deploy (the deploy script does this).
- OPcache on in php-fpm (Ubuntu's default). Redis installed, so cache and sessions use it.
- Nginx gzip and HTTP/2 (already in `deploy/nginx.conf`).
- Settings → General → Speed: "Fast pages for visitors" on.

Measured on this code with these settings: pages answer in 15–50 ms (about 3–8 ms when served from the guest page cache). On a mid-range phone over 4G, content appears in about 0.8 s and clicking a product opens it in under 0.2 s.

