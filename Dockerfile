# composer.json declares "php": "^8.3", but composer.lock actually resolved
# several symfony/* packages that require >=8.4.1 (locked on a machine
# running PHP 8.4) — `composer install` respects the lock file, so the image
# needs 8.4 to match, not the 8.3 the json constraint alone would suggest.
FROM php:8.4-cli-bookworm

# git/unzip: Composer needs them. libpq-dev/libzip-dev/lib{png,jpeg,freetype}-dev:
# build deps for the PHP extensions below (removed from the final layer's apt
# cache, but the .so files they produce stay).
RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip libpq-dev libzip-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev libonig-dev \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql pgsql mbstring exif pcntl bcmath gd zip

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Dependency layer, cached separately from the app code below so editing a
# controller doesn't force a full `composer install` on every build.
# --no-scripts: composer's post-autoload-dump hook calls `artisan
# package:discover`, which needs the full app (not copied in yet) to boot.
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts --no-progress

COPY . .

# Now that the full app is present, finish what --no-scripts skipped, and
# make sure Laravel can write to the paths it needs at runtime.
RUN composer dump-autoload --optimize --no-dev \
    && php artisan package:discover --ansi \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Documents the intended port; Render ignores EXPOSE and injects the real
# $PORT at runtime, which entrypoint.sh binds to.
EXPOSE 8080

ENTRYPOINT ["entrypoint.sh"]
