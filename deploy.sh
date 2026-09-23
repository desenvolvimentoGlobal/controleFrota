#!/bin/bash
# =============================================================================
# deploy.sh — atualização da aplicação em PRODUÇÃO (git pull + build + migrate).
# TEMPLATE: troque frota pelo nome do projeto (mesmo do compose.yaml).
#
# Rode NO SERVIDOR, dentro do diretório do projeto (ex.: /opt/projects/frota):
#     ./deploy.sh
#
# Princípios (a ordem dos passos importa):
#   - BACKUP ANTES de tudo. Se o backup falhar, o deploy nem começa.
#   - Aborta em QUALQUER erro (set -e). Nada de "seguir mesmo assim".
#   - Confirmação interativa explícita antes de migrations e de variáveis novas
#     — as duas coisas que causam estrago silencioso.
#   - build separado do up: se o build falhar, os containers antigos continuam
#     no ar e nada cai.
#
# Ver SERVIDOR-SETUP.md (Rollback) se algo der errado depois do migrate.
# =============================================================================
set -euo pipefail

# --- Configuração (ajuste por projeto) --------------------------------------
PROJETO="frota"                  # nome do projeto (subpasta em /opt/backups)
BRANCH="main"                          # branch de produção
DUMP_SH="/opt/backups/dump.sh"         # script de backup do banco
BACKUP_DIR="/opt/backups/${PROJETO}"   # onde o dump.sh grava os .sql.gz

# --- Helpers ----------------------------------------------------------------
c_red()  { printf '\033[1;31m%s\033[0m\n' "$*"; }
c_grn()  { printf '\033[1;32m%s\033[0m\n' "$*"; }
c_ylw()  { printf '\033[1;33m%s\033[0m\n' "$*"; }
titulo() { printf '\n\033[1;36m== %s ==\033[0m\n' "$*"; }
abortar() { c_red "ABORTADO: $*"; exit 1; }

# Confirmação interativa. Lê do terminal mesmo se stdin estiver redirecionado.
# Só prossegue com "sim" (exato) — qualquer outra coisa aborta.
confirmar() {
    local resposta
    read -r -p "$1 [digite: sim] " resposta < /dev/tty || abortar "sem terminal para confirmar"
    [ "$resposta" = "sim" ] || abortar "confirmação negada"
}

# Chaves (NOME=) presentes num arquivo de env, uma por linha. Ignora comentários.
chaves_env() {
    [ -f "$1" ] || return 0
    grep -E '^[A-Za-z_][A-Za-z0-9_]*=' "$1" | sed 's/=.*//' | sort -u
}

# =============================================================================
# 1) BACKUP PRIMEIRO — sem backup válido, sem deploy.
# =============================================================================
titulo "1/11  Backup do banco (antes de qualquer coisa)"
[ -x "$DUMP_SH" ] || abortar "não achei o script de backup executável em $DUMP_SH"
antes=$(date +%s)
"$DUMP_SH"
# Pega o .gz mais novo da pasta do projeto e exige que seja recente e > 0 bytes.
novo_gz=$(find "$BACKUP_DIR" -name '*.sql.gz' -newermt "@${antes}" -type f 2>/dev/null | sort | tail -n1 || true)
[ -n "$novo_gz" ]        || abortar "o backup não gerou nenhum .gz novo em $BACKUP_DIR (o projeto está no for-loop do dump.sh?)"
[ -s "$novo_gz" ]        || abortar "o backup gerou um .gz VAZIO ($novo_gz) — dump falhou"
c_grn "Backup OK: $novo_gz ($(du -h "$novo_gz" | cut -f1))"

# =============================================================================
# 2) O que vem do remoto
# =============================================================================
titulo "2/11  Buscando alterações do remoto"
git fetch origin "$BRANCH"
BASE=$(git rev-parse HEAD)
REMOTO=$(git rev-parse "origin/${BRANCH}")
if [ "$BASE" = "$REMOTO" ]; then
    c_grn "Já está atualizado ($BRANCH em $(git rev-parse --short HEAD)). Nada a fazer."
    exit 0
fi
echo "Commits a aplicar:"
git --no-pager log --oneline "HEAD..origin/${BRANCH}"
echo
git --no-pager diff "HEAD..origin/${BRANCH}" --stat

# =============================================================================
# 3) Migrations novas — o que causa estrago irreversível
# =============================================================================
titulo "3/11  Detecção de migrations novas"
migs_novas=$(git diff --name-status "HEAD..origin/${BRANCH}" -- database/migrations \
    | awk '$1 ~ /^A/ {print $2}' || true)
if [ -n "$migs_novas" ]; then
    c_ylw "Migrations NOVAS neste deploy:"
    echo "$migs_novas" | sed 's/^/  + /'
    # Destaque redobrado para operações destrutivas.
    destrutivas=$(git diff "HEAD..origin/${BRANCH}" -- database/migrations \
        | grep -E '^\+' | grep -Ei 'dropColumn|renameColumn|dropIfExists|->drop\(' || true)
    if [ -n "$destrutivas" ]; then
        c_red "⚠️  Estas migrations contêm DROP/RENAME — risco de perda IRREVERSÍVEL de dados:"
        echo "$destrutivas" | sed 's/^/     /'
        c_red "    O backup do passo 1 é a sua única volta (ver SERVIDOR-SETUP.md > Rollback)."
    fi
    confirmar "Aplicar estas migrations em PRODUÇÃO?"
else
    c_grn "Nenhuma migration nova."
fi

# =============================================================================
# 4) Árvore de trabalho limpa — senão é o sintoma do "editaram no servidor"
# =============================================================================
titulo "4/11  Verificando árvore de trabalho"
if [ -n "$(git status --porcelain)" ]; then
    c_red "Há alterações locais NÃO commitadas no servidor:"
    git --no-pager status --short
    abortar "o servidor divergiu do Git (alguém editou aqui?). Resolva antes — o servidor só recebe git pull. Ver SERVIDOR-SETUP.md > REGRA PERMANENTE."
fi
c_grn "Árvore limpa."

# =============================================================================
# 5) git pull
# =============================================================================
titulo "5/11  git pull"
git pull --ff-only origin "$BRANCH"

# =============================================================================
# 6) Variável de ambiente nova — ${VAR:-default} usaria o default em silêncio
# =============================================================================
titulo "6/11  Checando variáveis de ambiente novas"
# Só as chaves ADICIONADAS por este pull (evita falso positivo com chaves que
# moram no OUTRO arquivo). Compara .env.example -> .env e .env.docker.example -> .env.docker.
checar_novas() {
    local exemplo="$1" real="$2"
    [ -f "$exemplo" ] || return 0
    local adicionadas
    adicionadas=$(git diff "${BASE}..HEAD" -- "$exemplo" \
        | grep -E '^\+[A-Za-z_][A-Za-z0-9_]*=' | sed 's/^+//; s/=.*//' | sort -u || true)
    [ -n "$adicionadas" ] || return 0
    local presentes; presentes=$(chaves_env "$real")
    local faltando=""
    local k
    for k in $adicionadas; do
        echo "$presentes" | grep -qx "$k" || faltando="${faltando} ${k}"
    done
    if [ -n "$(echo "$faltando" | tr -d ' ')" ]; then
        c_red "⚠️  Este deploy adicionou variáveis em $exemplo que NÃO estão em $real:"
        for k in $faltando; do echo "     - $k"; done
        c_red "    Sem defini-las, o ${real} cai no default de dev (a armadilha central deste projeto)."
        confirmar "Prosseguir MESMO ASSIM (você vai preencher $real antes de usar)?"
    fi
}
checar_novas ".env.example" ".env"
checar_novas ".env.docker.example" ".env.docker"
c_grn "Checagem de variáveis concluída."

# =============================================================================
# 7) build (separado do up: build quebrado NÃO derruba o que está no ar)
# =============================================================================
titulo "7/11  docker compose build"
docker compose build

# =============================================================================
# 8) up
# =============================================================================
titulo "8/11  docker compose up -d"
docker compose up -d

# =============================================================================
# 9) migrations (forward-only; já confirmadas no passo 3)
# =============================================================================
titulo "9/11  php artisan migrate --force"
docker compose exec -T app php artisan migrate --force

# =============================================================================
# 10) caches (limpa e recacheia com o ambiente de RUNTIME já presente)
# =============================================================================
titulo "10/11  Recache de config/rotas/views"
docker compose exec -T app php artisan config:clear
docker compose exec -T app php artisan config:cache
docker compose exec -T app php artisan route:cache
docker compose exec -T app php artisan view:cache

# =============================================================================
# 11) Verificação pós-deploy
# =============================================================================
titulo "11/11  Verificação"
docker compose ps
APP_URL=$(grep -E '^APP_URL=' .env 2>/dev/null | head -n1 | cut -d= -f2- || true)
if [ -n "${APP_URL:-}" ]; then
    echo "curl -I ${APP_URL}"
    curl -sSI "$APP_URL" | head -n1 || c_ylw "curl não respondeu como esperado — verifique manualmente."
fi
echo
echo "Últimos logs (2 min):"
docker compose logs --since 2m app web worker || true

c_grn "Deploy concluído: $(git rev-parse --short HEAD)"
