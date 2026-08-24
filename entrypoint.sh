#!/usr/bin/env sh
set -e

# Render (like most PaaS) injects real secrets as process environment
# variables, not a .env file. This is just a fallback so a genuinely missing
# .env doesn't make phpdotenv/config calls behave unexpectedly.
if [ ! -f .env ]; then
    cp .env.example .env
fi

# Set a real APP_KEY once via Render's dashboard so it stays stable across
# deploys/restarts (anything encrypted — sessions, cookies — depends on it
# not changing under you). This only fills in a fallback if it's genuinely
# unset anywhere.
if [ -z "$APP_KEY" ]; then
    php artisan key:generate --force
fi

php artisan storage:link --force || true

php artisan migrate --force

# Config/route caching bakes in whatever environment variables are present
# at the moment it runs. Doing this during `docker build` would bake in
# build-time placeholders instead of Render's real runtime secrets, so it
# has to happen here, after those are actually injected into the container.
php artisan config:cache
php artisan route:cache

# --no-reload: required for PHP_CLI_SERVER_WORKERS to actually take effect —
# without it, Laravel silently falls back to a single-threaded server.
exec php artisan serve --host=0.0.0.0 --port="${PORT:-8080}" --no-reload
