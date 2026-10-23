#!/bin/sh
# Runs inside the vercel-php runtime after `composer install` (composer "vercel" script).
set -e

echo "Building theme assets..."
npm ci --no-audit --no-fund
npm run build
rm -rf node_modules

if [ -n "$DB_HOST" ] && [ "$VERCEL_SKIP_MIGRATIONS" != "true" ]; then
    echo "Running database migrations..."
    php artisan migrate --force --seed

    # First deploy only, so later deploys don't overwrite what was changed in the admin panel.
    if [ -n "$APP_URL" ] && [ "$(php artisan tinker --execute='echo (int) ((App\Models\Setting::where("key", "app_url")->value("value") ?? "http://localhost") !== "http://localhost");')" = "0" ]; then
        php artisan app:init "${PAYMENTER_COMPANY_NAME:-Paymenter}" "$APP_URL"
    fi

    if [ -n "$PAYMENTER_ADMIN_EMAIL" ] && [ -n "$PAYMENTER_ADMIN_PASSWORD" ]; then
        php artisan tinker --execute='
            $email = getenv("PAYMENTER_ADMIN_EMAIL");
            if (!App\Models\User::where("email", $email)->exists()) {
                App\Models\User::create([
                    "first_name" => getenv("PAYMENTER_ADMIN_FIRST_NAME") ?: "Admin",
                    "last_name" => getenv("PAYMENTER_ADMIN_LAST_NAME") ?: "User",
                    "email" => $email,
                    "password" => getenv("PAYMENTER_ADMIN_PASSWORD"),
                    "role_id" => App\Models\Role::where("name", "admin")->value("id"),
                ]);
                echo "Created admin user {$email}\n";
            }
        '
    fi
else
    echo "DB_HOST not set (or VERCEL_SKIP_MIGRATIONS=true), skipping migrations."
fi
