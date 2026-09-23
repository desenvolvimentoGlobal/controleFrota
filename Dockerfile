# =============================================================================
# Controle de Frota — imagem Docker (multi-stage)
#
# Stages:
#   1) app -> PHP 8.3-FPM com o código + vendor (imagem de runtime)
#   2) web -> Nginx servindo public/ (estáticos) e proxy para o app (FPM)
#
# Este projeto NÃO tem pipeline de front-end (Bootstrap/Chart.js via CDN;
# public/css e public/js são estáticos versionados) — não há stage Node.
# A mesma imagem "app" é usada pelos serviços app e scheduler.
# =============================================================================


# -----------------------------------------------------------------------------
# Stage 1 — aplicação PHP 8.3-FPM (runtime)
# -----------------------------------------------------------------------------
FROM php:8.3-fpm-bookworm AS app

# Composer (copiado da imagem oficial).
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Bibliotecas de sistema para compilar as extensões PHP:
#   - gd   -> libpng / libjpeg / libfreetype (DomPDF e PhpSpreadsheet)
#   - zip  -> libzip (PhpSpreadsheet lê/gera .xlsx)
#   - intl -> libicu (formatação pt-BR)
#   - gmp  -> libgmp (web-push: criptografia das chaves VAPID)
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libicu-dev \
        libzip-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libgmp-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql \
        gd \
        zip \
        intl \
        gmp \
        bcmath \
        opcache \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

ENV COMPOSER_ALLOW_SUPERUSER=1

# INSTALL_DEV=true  -> inclui dependências de dev (padrão p/ ambiente local)
# INSTALL_DEV=false -> imagem enxuta de produção (--no-dev)
ARG INSTALL_DEV=true

# 1) Dependências PHP primeiro (cache de camada por composer.lock).
COPY composer.json composer.lock ./
RUN if [ "$INSTALL_DEV" = "true" ]; then \
        composer install --no-scripts --no-autoloader --prefer-dist --no-interaction; \
    else \
        composer install --no-scripts --no-autoloader --prefer-dist --no-interaction --no-dev; \
    fi

# 2) Código da aplicação (respeitando .dockerignore).
COPY . .

# 3) Autoloader otimizado + árvore de diretórios graváveis.
RUN if [ "$INSTALL_DEV" = "true" ]; then \
        composer dump-autoload --optimize --no-interaction --no-scripts; \
    else \
        composer dump-autoload --optimize --no-interaction --no-scripts --no-dev; \
    fi \
    && mkdir -p \
        storage/app/private \
        storage/app/public \
        storage/framework/cache \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwX storage bootstrap/cache

# Configurações de PHP e PHP-FPM.
COPY docker/php/app.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/www.conf

# Entrypoint (permissões + limpeza de artefatos; NÃO roda migrations/seeders).
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 9000
ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]


# -----------------------------------------------------------------------------
# Stage 2 — Nginx (serve estáticos de public/ e faz proxy do PHP para o app)
# -----------------------------------------------------------------------------
FROM nginx:1.27-alpine AS web

WORKDIR /var/www/html

COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf

# Estáticos do repositório (css/js/imagens versionados em public/).
COPY public ./public

# Fotos de veículos e usuários (disco `public` do Laravel): o volume
# storage-public é montado em storage/app/public e este link o expõe em
# /storage, como o `php artisan storage:link` faz no app.
RUN mkdir -p /var/www/html/storage/app/public \
    && rm -rf /var/www/html/public/storage \
    && ln -s /var/www/html/storage/app/public /var/www/html/public/storage

EXPOSE 80
