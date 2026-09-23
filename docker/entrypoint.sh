#!/bin/sh
# =============================================================================
# Entrypoint da imagem app/scheduler. Simples e idempotente.
#
# Faz APENAS:
#   - garante a existência dos diretórios graváveis;
#   - remove artefatos compilados que possam ter vindo do host Windows
#     (views Blade compiladas e caches de bootstrap são recriados em runtime);
#   - ajusta permissões quando executado como root;
#   - executa o comando recebido (php-fpm, schedule:work, artisan, etc.).
#
# NÃO faz (por decisão de escopo/segurança):
#   - migrate, db:seed, key:generate;
#   - qualquer alteração de dados ou espera infinita pelo banco.
# =============================================================================
set -e

APP_DIR="/var/www/html"
cd "$APP_DIR"

# 1) Diretórios necessários (idempotente). Tolerante a falha: o scheduler roda
#    como www-data e um mkdir sem privilégio não pode derrubar o container.
mkdir -p \
    storage/app/private \
    storage/app/public \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache \
    2>/dev/null || true

# 1b) Link público das fotos de veículos e usuários (disco `public`), como o
#     `storage:link`. Idempotente: só cria se não existir. Quem SERVE as fotos
#     é o `web` (link próprio no Dockerfile); este é para o Laravel.
if [ ! -e public/storage ]; then
    ln -s "$APP_DIR/storage/app/public" public/storage 2>/dev/null || true
fi

# 2) Limpa artefatos compilados incompatíveis (ex.: caminhos absolutos do
#    Windows vindos do bind mount). Regenerados pelo Laravel no primeiro boot.
rm -f storage/framework/views/*.php 2>/dev/null || true
rm -f bootstrap/cache/*.php 2>/dev/null || true

# 3) Ajuste de propriedade — APENAS quando root (serviço "app": o master do
#    php-fpm sobe como root e baixa privilégio para www-data). O "scheduler"
#    roda direto como www-data e pula este bloco de propósito.
if [ "$(id -u)" = "0" ]; then
    chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true
fi

# 4) Executa o comando do container.
exec "$@"
