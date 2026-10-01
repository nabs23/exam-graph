# ==========================================
# Stage 1: Install Composer Dependencies
# ==========================================
FROM composer:2 AS vendor
WORKDIR /app

COPY composer.json composer.lock ./

RUN --mount=type=cache,target=/tmp/composer-cache \
    COMPOSER_CACHE_DIR=/tmp/composer-cache \
    composer install --no-dev --no-interaction --prefer-dist --ignore-platform-reqs --no-scripts

COPY . .
RUN composer dump-autoload --optimize --no-dev

# ==========================================
# Stage 2: Build Frontend Assets
# ==========================================
FROM php:8.4-cli AS frontend
WORKDIR /app

ARG VITE_APP_NAME=ExamGraph
ENV VITE_APP_NAME=${VITE_APP_NAME}

RUN apt-get update && apt-get install -y \
    ca-certificates \
    curl \
    gnupg \
    && mkdir -p /etc/apt/keyrings \
    && curl -fsSL https://deb.nodesource.com/gpgkey/nodesource-repo.gpg.key \
        | gpg --dearmor -o /etc/apt/keyrings/nodesource.gpg \
    && echo "deb [signed-by=/etc/apt/keyrings/nodesource.gpg] https://deb.nodesource.com/node_22.x nodistro main" \
        > /etc/apt/sources.list.d/nodesource.list \
    && apt-get update \
    && apt-get install -y nodejs \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=vendor /app/vendor ./vendor
COPY composer.json composer.lock package.json package-lock.json vite.config.ts tsconfig.json .npmrc ./
COPY artisan ./artisan
COPY app ./app
COPY bootstrap ./bootstrap
COPY config ./config
COPY public ./public
COPY resources ./resources
COPY routes ./routes

RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache

RUN php artisan wayfinder:generate --with-form

RUN --mount=type=cache,target=/root/.npm \
    npm ci && npm run build

# ==========================================
# Stage 3: Production Image
# ==========================================
FROM php:8.4-fpm

WORKDIR /var/www/html

RUN apt-get update && apt-get install -y \
    nginx \
    supervisor \
    unzip \
    curl \
    poppler-utils \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

RUN --mount=type=cache,target=/tmp/install-php-extensions-cache \
    curl -fsSL https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions \
        -o /usr/local/bin/install-php-extensions \
    && chmod +x /usr/local/bin/install-php-extensions \
    && install-php-extensions \
        bcmath \
        exif \
        gd \
        intl \
        mbstring \
        opcache \
        pdo_mysql \
        pdo_pgsql \
        redis \
        zip

COPY . .
COPY --from=frontend /app/public/build ./public/build
COPY --from=vendor /app/vendor ./vendor

COPY docker/nginx.conf /etc/nginx/sites-available/default
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh

RUN chmod +x /usr/local/bin/entrypoint.sh \
    && chown -R www-data:www-data storage bootstrap/cache

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
