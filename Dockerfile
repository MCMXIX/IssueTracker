FROM node:24-bookworm-slim AS frontend

WORKDIR /app
COPY package.json package-lock.json .npmrc ./
RUN npm ci
COPY vite.config.js ./
COPY resources/ resources/
RUN npm run build

FROM php:8.4-cli-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends unzip \
    && docker-php-ext-install -j"$(nproc)" pcntl pdo_mysql \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /app

COPY . .
# Compose injects configuration at runtime. The empty file keeps Dotenv reads quiet in tests.
RUN touch .env && composer install --no-interaction --prefer-dist

COPY --from=frontend /app/public/build public/build

EXPOSE 8000
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000", "--no-reload", "--tries=0"]
