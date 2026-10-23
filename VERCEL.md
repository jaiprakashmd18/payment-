# Deploying Paymenter on Vercel

Paymenter is a Laravel (PHP) app. On Vercel it runs on the community
[`vercel-php`](https://github.com/vercel-community/php) runtime:

| File | Purpose |
| --- | --- |
| `vercel.json` | Runtime, routing, and the daily cron job |
| `api/index.php` | Entry point for every page (wraps `public/index.php`) |
| `api/assets.php` | Serves the theme CSS/JS built during deployment (`/default/*`) |
| `api/cron.php` | Runs the scheduler and the queue (replaces the crontab and queue worker) |
| `api/bootstrap.php` | Serverless defaults: writable storage in `/tmp`, sessions and queue in the database, logs to stderr |
| `vercel-build.sh` | Run by the `vercel` Composer script: builds theme assets, migrates the database, first-time setup |

## 1. Create a MySQL / MariaDB database

Vercel does not host MySQL, so use an external one, e.g. Aiven, TiDB Cloud,
Railway, DigitalOcean, or any MySQL 8 / MariaDB 10.6+ server reachable from the
internet.

## 2. Import the repo in Vercel

Import the repository in Vercel and keep the framework preset as **Other**.
Leave the build, install, and output settings empty: `vercel.json` sets them.

## 3. Set environment variables

In **Project → Settings → Environment Variables** add:

| Variable | Example | Notes |
| --- | --- | --- |
| `APP_KEY` | `base64:...` | **Required.** Generate with `php artisan key:generate --show`, or `echo "base64:$(openssl rand -base64 32)"` |
| `APP_URL` | `https://your-app.vercel.app` | Your production URL |
| `APP_ENV` | `production` | |
| `APP_DEBUG` | `false` | |
| `DB_CONNECTION` | `mariadb` | Use `mysql` for MySQL 8 |
| `DB_HOST` / `DB_PORT` / `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` | | Your database |
| `MYSQL_ATTR_SSL_CA` | `/etc/pki/tls/certs/ca-bundle.crt` | Only if your database requires SSL |
| `CRON_SECRET` | any long random string | Protects `/api/cron.php` |
| `PAYMENTER_ADMIN_EMAIL` / `PAYMENTER_ADMIN_PASSWORD` | | First admin account, created on the first deploy only |
| `PAYMENTER_COMPANY_NAME` | `My Hosting` | Optional, used on first deploy only |
| `PASSPORT_PRIVATE_KEY` / `PASSPORT_PUBLIC_KEY` | | Only if you use the API / OAuth. Generate locally with `php artisan passport:keys` and paste the key contents |

Optional:

| Variable | Notes |
| --- | --- |
| `REDIS_URL` (or `REDIS_HOST`, `REDIS_PASSWORD`, `REDIS_PORT`) | Enables a shared cache (e.g. Upstash, use a `rediss://` URL). Without it the cache is per-request only, which works but is slower |
| `MAIL_*` | Mail settings are configured in the admin panel |
| `VERCEL_SKIP_MIGRATIONS=true` | Stop the build from running database migrations |
| `PAYMENTER_EXTERNAL_SCHEDULER=true` | Set this once you add the every-minute pinger from step 5 |

## 4. Deploy

Every deploy installs Composer packages, builds the theme with Vite, and runs
`php artisan migrate --force --seed` against your database. The first deploy
also sets the company name and URL and creates the admin account. Then log in
at `/login` and open `/admin`.

## 5. Background jobs (billing, emails, suspensions)

Paymenter needs `php artisan schedule:run` every minute and a queue worker.
`/api/cron.php` does both. It only answers requests with
`Authorization: Bearer <CRON_SECRET>`.

- **Vercel Cron**, configured in `vercel.json`, calls it once a day with
  `?daily=1`. That runs the daily billing job (invoices, suspensions,
  terminations) and processes the queue. It works on the Hobby plan.
- **Recommended:** emails, notifications, and server provisioning are queued,
  so they should be processed every minute. Create a free job at
  [cron-job.org](https://cron-job.org) (or similar) that calls
  `https://your-app.vercel.app/api/cron.php` every minute with the header
  `Authorization: Bearer <CRON_SECRET>`. Then set
  `PAYMENTER_EXTERNAL_SCHEDULER=true`, so the daily job runs at the time you set
  in the admin panel instead of twice.

## Limitations on Vercel

Vercel functions have a read-only file system, and `/tmp` is wiped between
requests. Because of that:

- **Uploaded files** (logo, ticket attachments, generated invoice PDFs) do not
  persist. Host the logo elsewhere and use its URL. If you need attachments,
  run Paymenter on a server or Docker instead.
- **Extensions and themes** can't be installed or updated from the admin
  panel. Add them to the repository and redeploy.
- **The built-in updater** doesn't work. Pull upstream changes into the repo and
  redeploy.
- Requests time out after 60 seconds (`maxDuration` in `vercel.json`).

For a fully featured install, use the included `Dockerfile` or
`docker-compose.example.yml` on a VPS or container host.
