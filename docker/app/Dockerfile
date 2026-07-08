FROM php:8.4-fpm

# --- 1) Параметры пользователя (можно переопределять при сборке)
ARG UID=1000
ARG GID=1000
ARG DEBIAN_FRONTEND=noninteractive
ARG SUPERCRONIC_VERSION=v0.2.29

# --- 2) Системные пакеты и PHP-расширения
RUN apt-get update && apt-get install -y \
    git unzip curl ca-certificates libpq-dev libicu-dev libonig-dev libxml2-dev libzip-dev \
    openssh-client sshpass \
    libjpeg62-turbo-dev libpng-dev libfreetype6-dev \
    default-libmysqlclient-dev \
  && docker-php-ext-configure gd --with-freetype --with-jpeg \
  && docker-php-ext-install -j"$(nproc)" intl mbstring pdo pdo_pgsql pdo_mysql gd xml zip opcache \
  && apt-get clean && rm -rf /var/lib/apt/lists/*

RUN curl -fsSLo /usr/local/bin/supercronic \
    "https://github.com/aptible/supercronic/releases/download/${SUPERCRONIC_VERSION}/supercronic-linux-amd64" \
 && chmod +x /usr/local/bin/supercronic

# --- 3) PHP Configuration
COPY php-custom.ini /usr/local/etc/php/conf.d/php-custom.ini

# --- 4) Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# --- 5) Приводим www-data к нужному UID/GID (или создаём при несовпадении)
RUN set -eux; \
    groupmod -o -g "${GID}" www-data || groupadd -o -g "${GID}" www-data; \
    usermod  -o -u "${UID}" -g "${GID}" www-data || useradd -o -u "${UID}" -g "${GID}" -d /var/www www-data

# --- 6) Директории и права для Composer/psysh
ENV COMPOSER_HOME=/var/www/.composer
RUN mkdir -p /var/www/.composer/cache /var/www/.config/psysh \
 && chown -R "${UID}:${GID}" /var/www

# --- 7) Никакого sudo — работаем сразу от нужного пользователя
USER ${UID}:${GID}

# --- 8) Рабочая директория
WORKDIR /var/www/html
