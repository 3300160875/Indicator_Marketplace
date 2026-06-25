FROM php:8.3-fpm-bookworm

RUN apt-get update \
  && apt-get install -y --no-install-recommends \
    ca-certificates \
    libzip-dev \
    unzip \
    zip \
  && docker-php-ext-install -j"$(nproc)" \
    mysqli \
    opcache \
    pdo_mysql \
    zip \
  && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2.7 /usr/bin/composer /usr/local/bin/composer
COPY infra/docker/php/php.ini /usr/local/etc/php/conf.d/99-indicator-marketplace.ini

WORKDIR /var/www/html
