FROM php:8.5-fpm-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends libicu-dev \
    && docker-php-ext-install intl pdo_mysql \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

EXPOSE 9000

CMD ["php-fpm"]
