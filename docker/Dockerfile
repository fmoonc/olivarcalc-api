# Development image for the OlivarCalc API (spec §13.2).
#
# Serves the app with `php artisan serve` on port 8000 so the web repo can point
# API_URL at http://localhost:8000. The production image (PHP-FPM + Nginx or
# FrankenPHP for Coolify) is a Fase 7 concern and deliberately not built here.
FROM php:8.5-fpm-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        libzip-dev \
        libicu-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        unzip \
    && rm -rf /var/lib/apt/lists/*

# Extensions Laravel 13 + Filament + the spec's MySQL utf8mb4 and decimal needs.
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        exif \
        gd \
        intl \
        opcache \
        pcntl \
        pdo_mysql \
        zip

# phpredis for the Redis cache/queue backend.
RUN pecl install redis \
    && docker-php-ext-enable redis

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY docker/php.ini /usr/local/etc/php/conf.d/olivarcalc.ini

# The app source is bind-mounted by docker-compose.yml, so dependencies are
# installed at `make setup` time rather than baked into the image.
EXPOSE 8000

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
