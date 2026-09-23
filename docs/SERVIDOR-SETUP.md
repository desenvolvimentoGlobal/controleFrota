# Colocar o Controle de Frota no servidor — passo a passo

Este é o **sétimo** projeto do servidor. Já rodam lá, atrás do mesmo Traefik:

| Pasta | Projeto | Nome no Compose | Router do Traefik | Host |
|---|---|---|---|---|
| `gestao` | gestaoEmpresarial | `gestao-empresarial` | `gestao-empresarial` | `gestao.` |
| `criacao-os` | emissaoOS | `criacao-os` | `criacao-os` | `criacao-os.` |
| `gestao-pessoas` | gestaoPessoas | `gestao-pessoas` | `gestao-pessoas` | `pessoas.` |
| `site` | novoSiteEmpresa | `global-site` | `global-site` | `site.` |
| `tarefas` | gestaoTarefas | `gestao-tarefas` | `gestao-tarefas` | `tarefas.` |
| `financeiro` | contasReceber | `financeiro` | `financeiro` | `financeiro.` |

O nosso entra como **`frota`** nos três — pasta, nome do Compose e router — e
responde em **https://frota.18-231-84-211.nip.io**.

> **Este documento é para ser seguido no terminal, de cima para baixo.** Cada
> comando é para copiar e colar. Onde houver decisão a tomar, está dito.
>
> O que ele **não** cobre: como o servidor foi montado (Traefik, rede `edge`,
> backup, fail2ban). Isso está em `gestaoEmpresarial/docs/SERVIDOR-SETUP.md` e
> já está feito. Este roteiro foi escrito a partir do
> `contasReceber/docs/SERVIDOR-SETUP.md`, o deploy mais recente e o que já
> pagou as armadilhas que aparecem aqui como avisos.

---

## Antes de começar: o que você precisa ter em mãos

| O quê | Onde conseguir |
|---|---|
| O IP do servidor | `18.231.84.211` |
| Sua chave SSH | a mesma que você já usa para entrar no servidor |
| O repositório no GitHub | ⚠️ **ainda não existe** — Parte A, abaixo |
| Acesso ao servidor do gestaoPessoas | é o mesmo servidor (`/opt/projects/gestao-pessoas`), para gerar o token do RH |
| O emissaoOS com a integração | só para a Parte 9: o branch `melhorias` do emissaoOS (commit `fb3fdac`) precisa estar em produção |

---

## Parte A — Criar o repositório no GitHub (no seu Windows)

Hoje o projeto só existe na sua máquina: `git remote -v` não mostra nada. O
servidor recebe o código por `git clone`/`git pull`, então o repositório tem de
existir antes de tudo.

1. No navegador, na organização **desenvolvimentoGlobal**: **New repository** →
   nome `controleFrota` → **Private** → **sem** README, `.gitignore` ou licença
   (o projeto já tem os dele) → **Create repository**.
2. No **PowerShell** da sua máquina:

```powershell
cd C:\wamp64\www\controleFrota
git remote add origin git@github.com:desenvolvimentoGlobal/controleFrota.git
git push -u origin main
```

Confira no navegador que os arquivos apareceram. O `.env` **não** deve estar lá
(o `.gitignore` o barra) — se estiver, pare e apague o repositório.

---

## Parte 0 — Entrar no servidor

No **Windows**, abra o **PowerShell** e conecte:

```powershell
ssh ubuntu@18.231.84.211
```

Se sua chave não estiver no lugar padrão: `ssh -i C:\caminho\da\sua\chave ubuntu@18.231.84.211`.

Deu certo quando o prompt muda para algo como `ubuntu@ip-172-...:~$`. **Daqui
para baixo, todo comando é digitado nessa janela.**

> **Como colar no terminal do servidor:** clique com o **botão direito** dentro
> da janela do PowerShell. Não é `Ctrl+V`.

---

## ⚠️ As quatro coisas que derrubariam os outros projetos

Tudo neste deploy é isolado — rede, banco e volumes são próprios. Estes são os
pontos em que um descuido **alcança os vizinhos**:

| Ponto | Situação |
|---|---|
| `name: frota` no compose | Único (conferido contra os seis da tabela). Se dois projetos tivessem o mesmo nome, um adotaria os containers do outro. ✅ |
| Router `frota` no Traefik | Único. Router repetido joga tráfego no container errado **sem erro visível**. ✅ |
| `Host(...)` = `frota.18-231-84-211.nip.io` | Livre. Vem de `TRAEFIK_HOST` no `.env` — **não** se edita o `compose.yaml` no servidor. ✅ |
| **Memória** | ⚠️ Sétimo projeto. A medição de 22/09/2026 dá folga (Parte 11), mas confira depois de subir. |

---

## Parte 1 — A chave de deploy do GitHub

O GitHub **não aceita a mesma chave** em dois repositórios. Cada projeto tem a
sua, e um apelido no `~/.ssh/config` faz o `git clone` usar a chave certa.

### 1.1 Gerar a chave

```bash
ssh-keygen -t ed25519 -f ~/.ssh/deploy_frota -C "deploy frota" -N ""
```

### 1.2 Mostrar a chave pública para copiar

```bash
cat ~/.ssh/deploy_frota.pub
```

Sai uma linha começando com `ssh-ed25519`. **Selecione a linha inteira com o
mouse** — no PowerShell, selecionar já copia.

### 1.3 Cadastrar no GitHub

Página do repositório `controleFrota` → **Settings** → **Deploy keys** → **Add deploy key**.

- **Title**: `servidor producao`
- **Key**: cole a linha
- **Allow write access**: **desmarcado** (o servidor só lê)

### 1.4 Criar o apelido no `~/.ssh/config`

Cole o bloco inteiro de uma vez — ele **acrescenta** ao arquivo (os apelidos
dos outros projetos continuam):

```bash
cat >> ~/.ssh/config <<'FIM'

Host github-frota
    HostName github.com
    User git
    IdentityFile ~/.ssh/deploy_frota
    IdentitiesOnly yes
FIM
```

Confira:

```bash
ssh -T git@github-frota
```

A resposta certa é `Hi desenvolvimentoGlobal/controleFrota! You've successfully
authenticated, but GitHub does not provide shell access.` — **isso é sucesso**.

---

## Parte 2 — Clonar o projeto

O diretório `/opt/projects` **já existe** e é dos outros projetos. **Não o crie
nem mude o dono dele** — só entre e clone ao lado:

```bash
cd /opt/projects
ls
```

Deve listar `criacao-os`, `financeiro`, `gestao`, `gestao-pessoas`, `site` e
`tarefas`. Então:

```bash
git clone git@github-frota:desenvolvimentoGlobal/controleFrota.git frota
cd frota
```

O `frota` no fim é o nome da **pasta**, e precisa ser exatamente esse: o
`deploy.sh` e o `dump.sh` usam `/opt/projects/frota` e `/opt/backups/frota`.

> ⚠️ **`ERROR: Repository not found`** não quer dizer que o repositório não
> existe. São duas causas: a deploy key não foi cadastrada (ou foi no
> repositório errado), ou o apelido `github-frota` não está no `~/.ssh/config`.
> O GitHub responde "não existe" em vez de "sem permissão", por segurança.

> ⚠️ **Clone pelo apelido `github-frota`, não por `git@github.com`.** Com
> `git@github.com` o SSH tenta a primeira chave que achar e o erro é
> `Permission denied (publickey)` — parece permissão, é **chave errada**.

Confira que o script de deploy veio executável:

```bash
ls -l deploy.sh      # deve começar com -rwxr-xr-x
```

> ⚠️ Se vier sem o `x`, **não resolva com `chmod +x` no servidor**: o git
> versiona o modo do arquivo, o chmod vira alteração local, e o próprio
> `deploy.sh` aborta no passo 4 ("alterações locais não commitadas"). A correção
> é no repositório (`git update-index --chmod=+x deploy.sh`, commit, push).
> Neste projeto o bit já está versionado (100755).

---

## Parte 3 — Os dois arquivos de ambiente

### Por que são dois

| | `.env` | `.env.docker` |
|---|---|---|
| **Quem lê** | o **Docker Compose**, no servidor | o **Laravel**, dentro do container |
| **Quando** | ANTES de subir, para resolver os `${...}` do `compose.yaml` | DEPOIS, com o container rodando |
| **Para quê** | senha do MySQL, `APP_URL` e o domínio do Traefik | configurar a aplicação |
| **Exemplos** | `DOCKER_DB_PASSWORD`, `APP_URL`, `TRAEFIK_HOST` | `APP_KEY`, `GESTAO_PESSOAS_TOKEN`, `VAPID_*` |

**O Compose não lê o `.env.docker`.** Se a senha do MySQL for parar lá, o
Compose cai no default do `compose.yaml` (`dev_password_trocar`), o banco sobe
**com a senha de desenvolvimento, sem erro**, e o app não conecta com uma
mensagem que não fala em senha. É a armadilha nº 1 — e a razão da Parte 5.

> Os dois ficam na pasta do projeto no servidor e **nenhum entra na imagem** (o
> `.dockerignore` barra ambos). Em produção o container não tem `.env`: o
> Laravel roda só com o `environment:` do `compose.yaml` e o `env_file: .env.docker`.

> ⚠️ **Nunca suba com `--env-file .env.docker`.** Essa flag substitui o `.env`
> como fonte de interpolação: `DOCKER_DB_*` e `APP_URL` somem e você cai na
> senha de dev pelo caminho mais difícil de enxergar.

### 3.1 Criar os dois a partir dos modelos

```bash
cp .env.example .env
cp .env.docker.example .env.docker
```

### 3.2 Gerar as senhas do banco e a APP_KEY

Rode e **guarde a saída**:

```bash
echo "DOCKER_DB_PASSWORD=$(openssl rand -base64 24)"
echo "DOCKER_DB_ROOT_PASSWORD=$(openssl rand -base64 24)"
docker run --rm php:8.3-cli php -r 'echo "base64:".base64_encode(random_bytes(32)).PHP_EOL;'
```

A última linha (a que começa com `base64:`) é a `APP_KEY`. O `docker run` usa um
PHP descartável porque o container do projeto ainda não existe.

### 3.3 Editar o `.env`

```bash
nano .env
```

| Para | Aperte |
|---|---|
| apagar a linha inteira sob o cursor | `Ctrl+K` |
| colar | botão direito do mouse |
| **salvar** | `Ctrl+O`, depois `Enter` |
| **sair** | `Ctrl+X` |

**Apague tudo** (segure `Ctrl+K` até esvaziar) e deixe **apenas** isto, com as
senhas que você gerou:

```
APP_URL=https://frota.18-231-84-211.nip.io
TRAEFIK_HOST=frota.18-231-84-211.nip.io

DOCKER_DB_DATABASE=frota_db
DOCKER_DB_USERNAME=frota
DOCKER_DB_PASSWORD=cole-aqui-a-primeira-senha
DOCKER_DB_ROOT_PASSWORD=cole-aqui-a-segunda-senha
```

> O `.env` de produção é curto porque no servidor ele **não é lido pelo
> Laravel** — só pelo Compose.

### 3.4 Editar o `.env.docker`

```bash
nano .env.docker
```

Aqui você **não apaga nada** — o arquivo é comentado. Preencha:

| Linha | O que pôr |
|---|---|
| `APP_KEY=` | a linha `base64:...` do passo 3.2 |
| `GESTAO_PESSOAS_TOKEN=` | o token do RH (Parte 4). Pode ficar vazio agora |
| `VAPID_PUBLIC_KEY=` / `VAPID_PRIVATE_KEY=` | ficam vazias agora; preenchidas na Parte 6 |

Deixe como já vêm: `SESSION_SECURE_COOKIE=true`,
`APP_TIMEZONE=America/Sao_Paulo` e
`GESTAO_PESSOAS_URL=https://pessoas.18-231-84-211.nip.io`.

> ⚠️ **O fuso aqui é Brasília, diferente do Financeiro (UTC).** De propósito: os
> horários do Frota são de agenda (saída às 08:00, rotina das 06:30) e o banco
> grava a hora local. A API sai com o fuso explícito (`-03:00`), então o Emissão
> OS, que roda em UTC, converte sem erro. **Não troque depois de ter dados.**

---

## Parte 4 — O token do Gestão de Pessoas (RH)

É o que permite o botão **"Sincronizar com o RH"** e a rotina das 06:00. Sem
ele o sistema funciona; só não sincroniza (a tela de usuários avisa).

```bash
cd /opt/projects/gestao-pessoas
docker compose exec app php artisan integracao:token criar controle-frota
```

**Copie o token agora** — ele só aparece uma vez. Volte e cole:

```bash
cd /opt/projects/frota
nano .env.docker          # GESTAO_PESSOAS_TOKEN=<cole>
```

> Se o comando responder que não existe, o gestaoPessoas de produção ainda não
> tem a API de integração (ela nasceu no branch `melhorias` de lá). Siga sem o
> token e volte a esta parte depois do deploy do gestaoPessoas.

---

## Parte 5 — Conferir antes de subir

```bash
docker compose config | grep -iE 'password|APP_URL|Host\('
```

A saída se parece com:

```
      DB_PASSWORD: <a sua senha>
      MYSQL_PASSWORD: <a mesma senha>
      MYSQL_ROOT_PASSWORD: <a senha de root>
      - mysqladmin ping -h 127.0.0.1 -u root -p"$$MYSQL_ROOT_PASSWORD" --silent
      APP_URL: https://frota.18-231-84-211.nip.io
      traefik.http.routers.frota.rule: Host(`frota.18-231-84-211.nip.io`)
```

**Os três sinais de que está certo:**

1. `DB_PASSWORD` e `MYSQL_PASSWORD` mostram **a mesma senha** — a que você gerou.
2. `APP_URL` começa com **`https://`** — é o que liga o `forceScheme`.
3. O `Host(...)` é `frota.18-231-84-211.nip.io`.

> **A linha do `$$` não é erro.** É a verificação de saúde do banco: `$$` diz ao
> Compose "não expanda aqui", e a senha é lida **dentro** do container. Com um
> `$` só, ela iria vazia, o banco nunca ficaria `healthy` e o deploy "travaria".

**Se aparecer `dev_password_trocar`**, as senhas estão no arquivo errado — elas
vão no `.env`, não no `.env.docker`. Volte ao 3.3.

---

## Parte 6 — Subir

```bash
docker compose up -d --build
```

Demora alguns minutos na primeira vez. Confira:

```bash
docker compose ps
```

Os quatro serviços (`app`, `web`, `worker`, `db`) devem estar `running`.

```bash
# Cria as tabelas
docker compose exec app php artisan migrate --force

# Perfis, setores e cargos. Em produção NÃO cria usuário nem veículo de exemplo.
docker compose exec app php artisan db:seed --force
```

### O primeiro usuário — sem ele ninguém entra

```bash
docker compose exec app php artisan usuario:criar
```

Ele pergunta, uma de cada vez: **nome completo**, **login**, **e-mail**,
**CPF** (com ou sem pontos) e **perfil** (escolha `admin`). Depois pergunta
**"Definir a senha agora?"**:

- **`yes`** se a conta é sua: pede a senha duas vezes, **sem mostrar o que você
  digita** (é normal não aparecer nada), e você entra direto;
- **`no`** se é a conta de outra pessoa: sorteia uma senha provisória, imprime
  uma vez — anote — e a pessoa troca no primeiro acesso.

A senha precisa ter ao menos **8 caracteres, com letras e números** (a mesma
régua da tela).

> ⚠️ Existe `--senha=...` para script, mas **evite no terminal**: o valor fica
> no histórico do shell e aparece no `ps` enquanto o comando roda.

> **Por que não há usuário pronto:** os usuários de exemplo (`admin`/`senha123`)
> só nascem fora de produção. Senha conhecida num servidor exposto é porta
> aberta.

### Esqueceu a senha? `usuario:senha`

```bash
docker compose exec app php artisan usuario:senha admin
```

Aceita login ou e-mail. Confirma mostrando o **nome** de quem será afetado,
oferece definir às cegas ou sortear uma provisória, e **derruba as sessões
abertas** daquela pessoa. Para entrar direto, sem a tela de troca:
`usuario:senha admin --sem-troca`. Senha fraca é recusada e **a antiga continua
valendo**; conta inativa é avisada (senha nova ali não faz ninguém entrar).

### As chaves do push (notificações no celular)

```bash
docker compose exec app php artisan webpush:vapid
```

Copie as duas linhas `VAPID_PUBLIC_KEY=` e `VAPID_PRIVATE_KEY=` para o
`nano .env.docker` e recrie os dois serviços que leem o arquivo:

```bash
docker compose up -d --no-deps app worker
docker compose exec app printenv VAPID_PUBLIC_KEY     # confirme que chegou
```

> ⚠️ **Gere uma vez só.** Chaves novas invalidam as inscrições de todos os
> celulares. Guarde as duas junto com as senhas do banco.

### Conferir se respondeu — em duas etapas

**1. A aplicação está de pé?** (sem passar pelo Traefik)

```bash
docker compose exec web wget -qO- http://localhost/up
```

A resposta certa é uma página HTML começando com `<!DOCTYPE html>`. Se vier,
Nginx e PHP estão conversando, e qualquer problema daqui em diante é de
roteamento.

**2. O Traefik está entregando?**

```bash
curl -I https://frota.18-231-84-211.nip.io/up
```

Espere `HTTP/2 200`.

> ### ⚠️ Não teste em `http://localhost/up` do servidor — dá 404 e não quer dizer nada
>
> O router escuta **só no `websecure`** (443). Na porta 80 o Traefik não acha
> router para este host e responde `404 page not found` em texto puro — é o
> Traefik dizendo "não roteio isto", não a aplicação.

| Sintoma no passo 2 | O que investigar |
|---|---|
| `404 page not found` | O Traefik não enxerga o container: `docker network inspect edge \| grep frota` |
| Erro de certificado | O Let's Encrypt leva alguns segundos na primeira vez. Espere um minuto. Se insistir: `docker logs traefik --tail 50 \| grep -i acme` |
| `Connection refused` | O Traefik não está no ar: `docker ps \| grep traefik` |
| 200, mas a tela dá erro 500 | Aí sim é a aplicação: `docker compose logs --tail 50 app` |

Com o passo 2 respondendo, abra **https://frota.18-231-84-211.nip.io** e entre.

---

## Parte 7 — O agendador (aqui NÃO é cron)

Diferente do Financeiro, **não há linha no crontab**. O agendador roda dentro do
serviço `worker` (`php artisan schedule:work`), que sobe junto com o resto.

Confira que ele está vivo e com as rotinas certas:

```bash
docker compose ps worker
docker compose exec app php artisan schedule:list
```

Devem aparecer:

| Quando | Comando | O que faz |
|---|---|---|
| a cada 15 min | `alocacoes:sincronizar` | Reserva o carro no dia, expira aprovadas e pedidos não aprovados, avisa atrasos |
| 02:00 | `checagens:apagar-fotos-antigas` | Retenção de 6 meses das fotos de checagem |
| 06:00 | `integracao:sincronizar-colaboradores` | RH → usuários (só com o token da Parte 4) |
| 06:30 | `manutencoes:verificar-planos` | Abre as preventivas vencidas |
| 07:00 | `frota:verificar-vencimentos` | Licenciamento, seguro e CNH |

Horários de **Brasília**.

> ⚠️ **Não acrescente também uma linha de `schedule:run` no cron.** As rotinas
> rodariam duas vezes — duas preventivas iguais, avisos em dobro.

> ⚠️ Sem o `worker` a falha é **muda**: alocação aprovada não expira, atraso não
> avisa, preventiva não abre. O `deploy.sh` confere o `worker` no fim de todo
> deploy.

---

## Parte 8 — O backup

```bash
sudo nano /opt/backups/dump.sh
```

Ache a linha que começa com `for P in` e **acrescente `frota` ao fim da lista**,
antes do `;`. Salve e saia. Teste — **sem `sudo`**:

```bash
/opt/backups/dump.sh
ls -lh /opt/backups/frota/
```

Tem de aparecer um `.sql.gz` com tamanho maior que zero.

> ### ⚠️ Não rode o `dump.sh` com `sudo`
>
> Na primeira vez ele cria `/opt/backups/frota`. Com `sudo`, a pasta nasce **do
> root**, e o `deploy.sh` (que roda como `ubuntu`) não consegue gravar nela:
> `Permission denied`, e o deploy **aborta** — de propósito, porque sem backup
> não há deploy. Se já aconteceu: `sudo chown -R ubuntu:ubuntu /opt/backups/frota`.
>
> O `sudo` do `nano` acima continua certo: o **arquivo** `dump.sh` é do root.
> Executá-lo, não.

> ⚠️ **As fotos não estão no dump.** O `mysqldump` leva o banco; as fotos de
> checagem (evidência das ocorrências) e os anexos de manutenção vivem no volume
> `storage-private`, e as fotos de veículos e usuários no `storage-public`. Eles
> dependem do **snapshot do Lightsail**.

---

## Parte 9 — Ligar o Emissão OS ao Frota

O planejamento de instalação do Emissão OS **puxa** daqui os veículos e a agenda
(avisa carro em manutenção ou já alocado no mesmo dia). Pré-requisito: o branch
`melhorias` do emissaoOS, com essa integração, **já em produção**.

```bash
# 1) Gerar o token aqui (em /opt/projects/frota)
docker compose exec app php artisan integracao:token criar emissao-os --escopos=alocacoes
```

Copie o token — ele só aparece uma vez.

```bash
# 2) Registrar lá (o token vive no BANCO do Emissão OS, não no .env)
cd /opt/projects/criacao-os
docker compose exec app php artisan integracao:token COLE-O-TOKEN-AQUI --sistema=controle-frota

# 3) O endereço daqui, no .env.docker de lá
nano .env.docker            # acrescente: CONTROLE_FROTA_URL=https://frota.18-231-84-211.nip.io
```

⚠️ **Editar o arquivo não basta** — e `restart` também não: ele não relê o
`env_file`. Recrie o `app` de lá, deixando `web` e `db` em paz:

```bash
docker compose up -d --no-deps app
docker compose exec app printenv CONTROLE_FROTA_URL     # confirme que chegou
docker compose restart web
```

> ⚠️ **No `criacao-os` é só `app`** — ele não tem `worker` (pedir devolve
> `no such service: worker`). E o **`restart web` é obrigatório lá**: aquele
> projeto ainda tem o `fastcgi_pass` estático, e sem o restart o Nginx fica no
> IP antigo do `app` e devolve 502. (O Frota já tem a correção; ver "Se der 502".)

### Testar os dois sentidos

```bash
# Emissão OS → Frota: quantos veículos ele enxerga (null = não conectou)
cd /opt/projects/criacao-os
docker compose exec app php artisan tinker --execute="var_dump(app(App\Integracoes\ControleFrota::class)->veiculos() === null ? null : count(app(App\Integracoes\ControleFrota::class)->veiculos()))"

# Frota → RH
cd /opt/projects/frota
docker compose exec app php artisan integracao:sincronizar-colaboradores
docker compose exec app php artisan integracao:token listar     # "último uso" do emissao-os preenchido
```

> **`null` no primeiro teste** = uma de duas causas, e a mensagem não distingue:
> a URL não chegou ao container (`printenv CONTROLE_FROTA_URL` vazio → recrie o
> `app`) ou o token não está no banco de lá (refaça o passo 2).

---

## Parte 10 — Antes de liberar para os motoristas

O Frota nasce **vazio** — não há carga de planilha. Pela tela, como admin:

1. **Setores e cargos** (Cadastros) — o seeder criou uma lista básica; ajuste.
2. **Fornecedores** (oficinas locais).
3. **Veículos**, com km atual, estado e condição mecânica de cada sistema.
4. **Usuários**, com gestor direto (a cadeia de aprovação). Com o RH ligado,
   vincule cada usuário à ficha do Gestão de Pessoas no cadastro e rode
   "Sincronizar com o RH".
5. **Planos preventivos** na ficha de cada veículo.

A **primeira checagem de cada veículo** é a referência inicial: ela não gera
ocorrência. Oriente os motoristas a fotografar com cuidado nessa primeira vez.

---

## Parte 11 — Conferir a memória

O servidor tem **16 GB**. O Frota reserva de teto ~3 GB (`db 1g + app 1g +
worker 512m + web 512m`); com sete projetos o teto somado passa de 22 GB — mais
que a RAM. **Isso é esperado**: `mem_limit` é teto, não reserva. A medição de
22/09/2026 (no `contasReceber/docs/SERVIDOR-SETUP.md`) deu **~3,1 GB de uso real
com seis projetos**, com 11 GB `available`. O Frota deve somar ~0,5 GB.

Rode **depois** de subir:

```bash
free -h
docker stats --no-stream
```

Olhe a coluna **`available`** do `free -h` (não a `free`, que mente por
desenho: o Linux usa a RAM ociosa como cache). Abaixo de ~1,5 GB, o servidor
está no limite.

> Se um dia apertar, **o que pesa é o MySQL** (84% do uso real na medição), não
> o `worker`. O caminho é baixar o `innodb-buffer-pool-size` no `compose.yaml` —
> **no repositório**, com commit e `deploy.sh`.

---

# Depois: como atualizar

**Nunca repita as partes acima.** Toda atualização é:

```bash
cd /opt/projects/frota
./deploy.sh
```

Ele faz backup antes de tudo, mostra o que vai aplicar, pede confirmação
explícita para migrations e para variáveis novas, aborta em qualquer erro,
recacheia config/rotas/views e, no fim, confere o `worker`.

### Mudei o `.env.docker`. E agora?

O `.env.docker` é lido **quando o container sobe**. Editar não muda o que está
rodando, e `docker compose restart` **também não basta** (reinicia o processo,
não relê o `env_file`). Recrie os dois serviços que o leem:

```bash
docker compose up -d --no-deps app worker
docker compose exec app printenv NOME_DA_VARIAVEL
```

### Se der 502 depois de recriar o `app`

**Não deve acontecer neste projeto**: o `docker/nginx/default.conf` põe o
endereço do `app` numa variável e consulta o DNS do Docker a cada requisição, e
o IP novo do container é achado em até 10 segundos. Se acontecer (alguém mexeu
no Nginx), a saída é `docker compose restart web` — e corrigir o arquivo no
repositório.

### ⚠️ Regra permanente: o servidor só recebe `git pull`

Nenhum arquivo versionado — `compose.yaml`, `Dockerfile`, código — é editado no
servidor. O que varia por ambiente vive no `.env`/`.env.docker`. O domínio vem
de `TRAEFIK_HOST` justamente para isso. Mudou algo versionado? Edite no
repositório, commit, push, e só então `./deploy.sh`.

---

# Se der errado

## Ver o que aconteceu

```bash
cd /opt/projects/frota
docker compose logs --tail 100 app
docker compose logs --tail 50 web
docker compose logs --tail 50 worker
```

## A) Código quebrado, sem migration destrutiva

```bash
git log --oneline -5          # escolha o hash anterior
git checkout <hash_anterior>
docker compose build && docker compose up -d
```

## B) Migration destruiu dado — restaurar do backup

```bash
cd /opt/projects/frota
ls -lh /opt/backups/frota/        # escolha o dump

docker compose exec -T db sh -c \
  'exec mysql -uroot -p"$MYSQL_ROOT_PASSWORD" -e "DROP DATABASE frota_db; CREATE DATABASE frota_db"'

zcat /opt/backups/frota/AAAA-MM-DD_HHMM.sql.gz \
  | docker compose exec -T db sh -c 'exec mysql -uroot -p"$MYSQL_ROOT_PASSWORD" frota_db'
```

> ⚠️ **`migrate:rollback` não é confiável** — as migrations em produção só
> andam para frente. **O dump é a verdade.**

> ⚠️ **As fotos não voltam com o dump** (Parte 8). Restaurar só o SQL deixa
> checagens apontando para fotos do volume como ele está agora.

---

# Particularidades deste projeto

- **Fuso de Brasília**, ao contrário do Financeiro (Parte 3.4).
- **O agendador é o `worker`**, não o cron (Parte 7).
- **Não apague os volumes.** `storage-private` guarda as fotos de checagem, que
  são evidência das ocorrências; a retenção de 6 meses já é automática e
  preserva as fotos de ocorrência aberta e a última checagem de cada veículo.
- **O primeiro usuário nasce no console** (`usuario:criar`); os demais, pela
  tela. Gestor e admin têm as alocações aprovadas na hora.
- **Serviços e volumes:**

| Serviço | Papel |
|---|---|
| `app` | PHP-FPM com o Laravel |
| `worker` | `schedule:work` — as rotinas da Parte 7 |
| `web` | Nginx servindo `public/` e as fotos de `storage/app/public` |
| `db` | MySQL 8.0 com volume próprio |

| Volume | Conteúdo |
|---|---|
| `db-data` | Banco |
| `storage-private` | Fotos de checagem e anexos de manutenção |
| `storage-public` | Fotos de veículos e usuários |

- **Limites de upload:** foto de checagem 4 MB (depois da compressão no
  celular), anexo de manutenção 10 MB; PHP `upload_max_filesize=20M` e
  `post_max_size=60M`; Nginx `client_max_body_size 60m`.
- **API para os irmãos:** contrato em `docs/API.md`.
