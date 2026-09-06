FROM php:8.4-fpm-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip curl libpq-dev libzip-dev libicu-dev libpng-dev libjpeg-dev libfreetype6-dev \
        $PHPIZE_DEPS \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) pdo_pgsql pgsql zip intl gd pcntl bcmath opcache \
    && rm -rf /var/lib/apt/lists/*

RUN mkdir -p /usr/src/php/ext \
    && curl -fsSL https://github.com/phpredis/phpredis/archive/refs/tags/6.2.0.tar.gz \
        | tar xz -C /usr/src/php/ext \
    && mv /usr/src/php/ext/phpredis-6.2.0 /usr/src/php/ext/redis \
    && docker-php-ext-install redis \
    && apt-get purge -y --auto-remove $PHPIZE_DEPS \
    && rm -rf /usr/src/php/ext/redis /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

RUN mkdir -p /var/www/html/storage /var/www/html/bootstrap/cache \
    && chown -R www-data:www-data /var/www/html
