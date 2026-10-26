FROM php:8.5-fpm-bookworm AS php-base

RUN apt-get update \
    && apt-get install -y --no-install-recommends libicu-dev libzip-dev unzip \
    && docker-php-ext-install -j"$(nproc)" intl pdo_mysql pcntl zip \
    && rm -rf /var/lib/apt/lists/*

FROM php-base AS composer-dependencies

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /app
COPY . .
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-progress \
    && composer check-platform-reqs --no-dev

FROM node:22-bookworm-slim AS frontend-assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
RUN npm run build

FROM php-base AS production
WORKDIR /app
COPY --from=composer-dependencies /app /app
COPY --from=frontend-assets /app/public/build /app/public/build
RUN mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwX storage bootstrap/cache

EXPOSE 9000
CMD ["php-fpm"]
