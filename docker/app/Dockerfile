# مرحلة بناء الواجهة (React/Vite): تُنقل مخرجاتها إلى public/ في الصورة النهائية.
FROM node:22-alpine AS frontend
WORKDIR /build/frontend
COPY frontend/package.json frontend/package-lock.json ./
RUN npm ci
COPY frontend/ ./
RUN npm run build

FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --ignore-platform-reqs

FROM php:8.4-cli-alpine
RUN apk add --no-cache postgresql-dev icu-dev icu-data-full libzip-dev \
 && docker-php-ext-install pdo_pgsql pcntl bcmath intl zip opcache
WORKDIR /app
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY --from=vendor /app/vendor ./vendor
COPY . .
COPY --from=frontend /build/frontend/dist/assets ./public/assets
COPY --from=frontend /build/frontend/dist/index.html ./public/spa.html
RUN composer dump-autoload --optimize --no-dev --no-scripts \
 && mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache \
 && chown -R www-data:www-data storage bootstrap/cache
USER www-data
