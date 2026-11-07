# syntax=docker/dockerfile:1

# PHP 8.3 : composer.json autorise ^8.2, mais le composer.lock existant
# exige PHP 8.3 via les paquets Firebase (beste/clock, beste/in-memory-cache-php).
# L'image ne change ni composer.json ni composer.lock.

FROM php:8.3-fpm-bookworm AS php-base

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_NO_INTERACTION=1 \
    PORT=8080

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        nginx \
        supervisor \
        curl \
        ca-certificates \
        unzip \
        gettext-base \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libzip-dev \
        libicu-dev \
        libonig-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        zip \
        intl \
        opcache \
    && rm -rf /var/lib/apt/lists/* \
    && rm -f /etc/nginx/sites-enabled/default /etc/nginx/conf.d/default.conf \
    && rm -f /usr/local/etc/php-fpm.d/zz-docker.conf

COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-crm.ini
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/www.conf
COPY docker/nginx/default.conf.template /etc/nginx/templates/default.conf.template
COPY docker/supervisor/supervisord.conf /etc/supervisor/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh

RUN sed -i 's/\r$//' /usr/local/bin/entrypoint.sh \
    && chmod +x /usr/local/bin/entrypoint.sh

WORKDIR /var/www/html

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["supervisord", "-n", "-c", "/etc/supervisor/supervisord.conf"]

FROM php-base AS development

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY composer.json composer.lock ./
RUN composer install \
        --prefer-dist \
        --no-progress \
        --no-scripts \
        --no-autoloader

COPY . .
RUN mkdir -p \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        storage/app/public \
        storage/app/private \
        bootstrap/cache \
    && composer dump-autoload --optimize --no-scripts \
    && APP_KEY=base64:MDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDA= \
        php artisan package:discover --ansi \
    && php bin/check-report-dependencies.php \
    && chown -R www-data:www-data storage bootstrap/cache

FROM php-base AS production

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY composer.json composer.lock ./
RUN composer install \
        --no-dev \
        --prefer-dist \
        --no-progress \
        --no-scripts \
        --no-autoloader

COPY . .
RUN mkdir -p \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        storage/app/public \
        storage/app/private \
        bootstrap/cache \
    && composer dump-autoload --optimize --no-dev --no-scripts \
    && APP_KEY=base64:MDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDA= \
        php artisan package:discover --ansi \
    && php bin/check-report-dependencies.php \
    && rm -rf tests \
    && rm -f /usr/bin/composer \
    && chown -R www-data:www-data storage bootstrap/cache
