# Deploy — Controle de Frota

Produção segue o template do servidor (`gestaoEmpresarial/docs/template-projeto`):
Laravel + MySQL 8 + Docker atrás do Traefik, na AWS Lightsail. Leia o
`README.md` daquele template antes do primeiro deploy: ele lista as armadilhas
que já derrubaram outros projetos.

Nome do projeto na infra: **`frota`** (containers, volumes, rede e router do Traefik).

## Serviços (`compose.yaml`)

| Serviço | Papel |
|---|---|
| `app` | PHP-FPM com o Laravel |
| `worker` | `php artisan schedule:work` — rotinas de routes/console.php |
| `web` | Nginx servindo `public/` e as fotos de `storage/app/public` |
| `db` | MySQL 8.0 com volume próprio |

Volumes persistentes:

| Volume | Conteúdo |
|---|---|
| `db-data` | Banco |
| `storage-private` | Fotos de checagem e anexos de manutenção |
| `storage-public` | Fotos de veículos e usuários |

**Não apague volumes**: fotos de checagem são evidência de ocorrências.

## Primeiro deploy

No servidor, em `/opt/projects/frota`:

1. `git clone` do repositório.
2. `cp .env.example .env` e preencher **no host**:
   - `DOCKER_DB_DATABASE`, `DOCKER_DB_USERNAME`, `DOCKER_DB_PASSWORD`, `DOCKER_DB_ROOT_PASSWORD`;
   - `APP_URL=https://frota.<dominio>`.
3. `cp .env.docker.example .env.docker` e preencher **para o container**:
   - `APP_KEY` (`php artisan key:generate --show`);
   - `SESSION_SECURE_COOKIE=true` (só com HTTPS);
   - `VAPID_PUBLIC_KEY` / `VAPID_PRIVATE_KEY` (`php artisan webpush:vapid`), para notificações no celular;
   - `GESTAO_PESSOAS_URL` / `GESTAO_PESSOAS_TOKEN`, se a integração com o RH for usada.
4. Em `compose.yaml`, trocar `frota.SEU-IP.nip.io` pelo host real no label do Traefik.
5. Conferir antes de subir: `docker compose config | grep -iE 'password|APP_URL'`.
6. Subir e preparar:

   ```bash
   docker compose up -d --build
   docker compose exec app php artisan migrate --force
   docker compose exec app php artisan db:seed --force   # perfis, setores, cargos e o admin
   curl -H "Host: frota.<dominio>" http://localhost/up    # 200
   ```

   Em produção o seeder cria **só** o admin (`admin` / `senha123`) com troca de
   senha obrigatória no primeiro acesso. Os usuários e veículos de exemplo não
   são criados.
7. Incluir `frota` no for-loop do `/opt/backups/dump.sh`. Sem isso o `deploy.sh`
   recusa rodar.

## Atualizações

Sempre pelo `./deploy.sh`, nunca `git pull` à mão. Ele faz backup, pede
confirmação para migrations novas e variáveis novas, builda, sobe, migra e
recacheia config, rotas e views.

## Integrações

- **gestaoPessoas → Frota (RH)**: no gestaoPessoas, `php artisan integracao:token criar controle-frota`,
  e o token vai em `GESTAO_PESSOAS_TOKEN` daqui. A sincronização roda às 06:00 e
  também pelo botão "Sincronizar com o RH" na tela de usuários.
- **Frota → emissaoOS e outros**: aqui, `php artisan integracao:token criar emissao-os`
  (com `--escopos=alocacoes` se precisar da agenda). Contrato em `docs/API.md`.

## Rotinas agendadas (serviço `worker`)

| Quando | Comando | O que faz |
|---|---|---|
| a cada 15 min | `alocacoes:sincronizar` | Reserva o veículo no dia, expira aprovadas não iniciadas, avisa atrasos |
| 02:00 | `checagens:apagar-fotos-antigas` | Retenção de 6 meses das fotos de checagem |
| 06:00 | `integracao:sincronizar-colaboradores` | RH → usuários (só se configurado) |
| 06:30 | `manutencoes:verificar-planos` | Abre as manutenções preventivas vencidas |
| 07:00 | `frota:verificar-vencimentos` | Licenciamento, seguro e CNH |

## Limites de upload

- Foto de checagem: 4 MB depois da compressão no celular (`config/frota.php`).
- Anexo de manutenção: 10 MB.
- PHP (`docker/php/app.ini`): `upload_max_filesize=20M`, `post_max_size=60M`.
- Nginx: `client_max_body_size 60m`.
