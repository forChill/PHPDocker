# syntax=docker/dockerfile:1

# Stage 1: Composer dependencies cho môi trường production
FROM composer:lts as prod-deps
WORKDIR /app
RUN --mount=type=bind,source=composer.json,target=composer.json \
    --mount=type=bind,source=composer.lock,target=composer.lock \
    --mount=type=cache,target=/tmp/cache \
    composer install --no-dev --no-interaction --prefer-dist

# Stage 2: Composer dependencies cho môi trường development
FROM composer:lts as dev-deps
WORKDIR /app
RUN --mount=type=bind,source=composer.json,target=composer.json \
    --mount=type=bind,source=composer.lock,target=composer.lock \
    --mount=type=cache,target=/tmp/cache \
    composer install --no-interaction --prefer-dist

# Stage 3: Base PHP Apache image
FROM php:8.3.12-apache as base

# Cài đặt các extension cần thiết cho PHP
RUN apt-get update && apt-get install -y \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    libpq-dev \
    unzip \
    curl \
    && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd \
    && docker-php-ext-install pdo_pgsql pgsql pdo_mysql mysqli \
    && pecl install redis-5.3.7 xdebug-3.2.1 \
    && docker-php-ext-enable redis

# Cài đặt Composer vào container
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Cài đặt Elasticsearch PHP client
WORKDIR /var/www/html
COPY composer.json composer.lock /var/www/html/
RUN composer install --no-dev --no-interaction --prefer-dist

# Cấu hình mặc định Apache
COPY ./apache-config/000-default.conf /etc/apache2/sites-available/000-default.conf

# Copy db/password.txt vào container
COPY ./db/password.txt /var/www/html/db/password.txt

# Đảm bảo quyền đọc cho file password.txt
RUN chmod 644 /var/www/html/db/password.txt

# Stage 4: Development environment
FROM base as development
COPY ./src /var/www/html
COPY ./tests /var/www/html/tests
RUN mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"
COPY --from=dev-deps /app/vendor/ /var/www/html/vendor

# Stage 5: Production environment
FROM base as production
COPY ./src /var/www/html
COPY ./tests /var/www/html/tests
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY --from=prod-deps /app/vendor/ /var/www/html/vendor

# Sử dụng user không phải root
USER www-data

# Expose Apache port
EXPOSE 80
