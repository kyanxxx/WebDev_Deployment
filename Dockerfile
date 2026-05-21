FROM composer:2 AS composer_deps
WORKDIR /app
COPY composer.json composer.lock symfony.lock ./
RUN composer install \
    --no-dev \
    --optimize-autoloader \
    --no-interaction \
    --no-scripts \
    --prefer-dist
FROM node:20-bookworm-slim AS frontend
WORKDIR /app
COPY --from=composer_deps /app/vendor ./vendor
COPY package.json package-lock.json webpack.config.js postcss.config.mjs ./
COPY assets ./assets
RUN npm ci && npm run build

FROM php:8.3-apache-bookworm AS runtime

RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libicu-dev \
        libzip-dev \
    && docker-php-ext-configure intl \
    && docker-php-ext-install -j"$(nproc)" intl opcache pdo_mysql zip \
    && for mpm in mpm_event mpm_worker; do \
        a2dismod "$mpm" 2>/dev/null || rm -f "/etc/apache2/mods-enabled/${mpm}.load" "/etc/apache2/mods-enabled/${mpm}.conf"; \
    done \
    && a2enmod mpm_prefork rewrite headers \
    && apache2ctl configtest \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

WORKDIR /var/www/html

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY --from=composer_deps /app/vendor ./vendor
COPY --from=frontend /app/public/build ./public/build
COPY . .
# Symfony requires a .env file at boot; real secrets come from Railway/Docker env vars
COPY .env.dist .env
RUN composer dump-autoload --optimize --no-dev --no-interaction

RUN mkdir -p var/cache var/log config/jwt public/bundles \
    && chown -R www-data:www-data var public config/jwt

COPY docker/docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"]
