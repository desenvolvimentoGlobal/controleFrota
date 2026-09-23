# Controle de Frota — guia do projeto

Sistema de controle da frota de veículos da GLOBAL. Irmão de `gestaoPessoas`,
`gestaoEmpresarial` e `emissaoOS` (todos em `C:\wamp64\www`). O planejamento
completo, o modelo de dados, os fluxos e as **decisões de negócio** estão em
`docs/PLANEJAMENTO.md` — leia antes de mexer em regra de negócio.

## Stack

- Laravel 13 · PHP 8.3 · MySQL 8 (dev no WAMP, banco `controle_frota`; produção em Docker + Traefik).
- Blade + Bootstrap 5.3 + Bootstrap Icons + Chart.js + fonte Inter, **via CDN**. Sem Vite, sem Tailwind, sem jQuery, sem DataTables, sem SweetAlert.
- Design system `gc-`: `public/css/app.css`, `public/js/app.js` e `resources/views/layouts/app.blade.php` foram **copiados do gestaoPessoas** e devem ficar sincronizados com ele. Não invente classes novas quando já existir uma `gc-`.
- `declare(strict_types=1)` em todo arquivo PHP.

## Rodar

```powershell
composer install
php artisan migrate --seed          # cria perfis, setores, cargos e usuários de dev
php artisan serve                   # http://localhost:8000
php artisan test                    # sqlite em memória
vendor\bin\pint                     # PSR-12
```

Usuários de dev (senha `senha123`): `admin`, `gestor`, `motorista`, `financeiro`.
Login aceita **login ou e-mail**.

Docker: ver `compose.yaml`, `compose.dev.yaml`, `deploy.sh` e
`gestaoEmpresarial/docs/template-projeto/README.md` (armadilhas do deploy).
Nome do projeto na infra: `frota`.

## Arquitetura

- **Controller fino → Service (regra, `DB::transaction`, lança `\DomainException`) → Eloquent.** O controller captura `\DomainException` e devolve `back()->with('erro', ...)`.
- Validação sempre em **Form Request** (`app/Http/Requests/<Entidade>/Salvar<Entidade>Request.php`), com `prepareForValidation()` normalizando máscaras e `attributes()` em pt-BR.
- Autorização em três camadas: middleware `perfil:admin,gestor` na rota, **Gates** nomeados em `AppServiceProvider` (`usuarios.gerenciar`, `frota.gerenciar`, `alocacoes.aprovar`, `ocorrencias.revisar`, `manutencoes.gerenciar`, `financeiro.ver`, `administrar`) e **Policies** para entidades com dono/cadeia. Regra da casa: esconder o item do menu **e** recusar a rota.
- Recorte do gestor: `Usuario::idsDaEquipe()` (recursivo) e scope `visiveisPara($usuario)`.
- Enums string-backed em `app/Enums` com `rotulo()` e `paraSelect()`.
- Auditoria automática: `AppServiceProvider::registrarAuditoria()` escuta `eloquent.*: *` e grava `logs_auditoria` via `AuditoriaService`. Fluxos sem model chamam `registrar()` explicitamente. Tabelas derivadas ficam em `TABELAS_IGNORADAS`.
- Notificações: `NotificacaoService::enviar($usuarios, tipo, titulo, mensagem, url)` cria sino + Web Push. `comPerfis([...])`, `todosAtivos()`, `responsaveisPor($usuario)`.
- Listagens: trait `FiltrosPersistentes` no `index()`; link "Limpar" com `?limpar=1`; `paginate(20)->withQueryString()`.
- Flash: `sucesso`, `erro`, `aviso` viram toasts no layout. Confirmações com `gcConfirm` / `data-gc-confirm` / `data-confirm-delete`. **Nunca `confirm()` nativo.**
- Uploads de evidência (fotos de checagem, anexos de manutenção): disco **privado** + rota autenticada. Foto de perfil e capa do veículo: disco `public` (`php artisan storage:link`).

## Convenções

- Tudo em português: classes, métodos, colunas, rotas, mensagens. Só o vocabulário do framework fica em inglês.
- Tabelas snake_case no plural, FKs `<entidade>_id`, `timestamps()` + `softDeletes()` nas entidades principais, dinheiro `DECIMAL(15,2)`, enums como string curta com `->comment()`.
- Views em `resources/views/<modulo>/{index,create,edit,show,_form}.blade.php`. Página com `@section('title')`, opcional `@section('voltar', route(...))`.
- Rotas nomeadas `<recurso>.<acao>`; `Route::resource(...)->parameters(['veiculos' => 'veiculo'])`.
- Commits em português, Conventional Commits (`feat:`, `fix:`, `docs:`...).
- Migrations só para frente depois de ir para produção (nunca `migrate:fresh` lá).

## Domínio (resumo — detalhes em docs/PLANEJAMENTO.md)

- **Perfis**: `admin` (tudo), `financeiro` (custos), `gestor` (própria cadeia via `gestor_id`), `geral` (base).
- **Usuário = colaborador**: uma tabela `usuarios` com CPF, cargo, setor, contato, endereço, CNH e `pode_dirigir`. CNH **não** é obrigatória para alocar; só avisa.
- **Veículo**: `situacao` operacional (disponivel, reservado, em_uso, em_manutencao, indisponivel, baixado), `estado_inicial`/`estado_atual` (condição física) e `veiculo_condicoes` por sistema mecânico (ok/atencao/critico).
- **Alocação**: solicitada → aprovada → em_uso → concluida (+ recusada/cancelada). Gestor e admin nascem aprovados (`config/frota.php`).
- **Checagem**: saída e retorno, itens de `config/frota.php`, uma foto por item. Validação é do **próprio motorista comparando com a última foto do veículo**; anomalia gera `ocorrencia` contra a alocação anterior; o motorista anterior contesta; gestor revisa se quiser.
- **Manutenção**: planejada/preventiva/imediata; em_espera → em_prestacao → prestada; histórico em `manutencao_movimentacoes`; fornecedores só locais.
- **Fotos**: arquivo apagado após 6 meses, exceto ligadas a ocorrência aberta/confirmada.

## Fases

0 Fundação ✔ · 1 Cadastros ✔ · 2 Alocação e checagem ✔ · 3 Manutenção ✔ · 4 Financeiro ✔ · Revisão ✔ · 5 Integrações e deploy ✔ (código; deploy real pendente).

Integrações locais (dev): gestaoEmpresarial 8000, emissaoOS 8001, gestaoPessoas 8002, Financeiro 8004, **Frota 8005**. O Frota lê o RH (`GESTAO_PESSOAS_URL`/`_TOKEN` no `.env`); o emissaoOS (branch `melhorias`) lê a API daqui com token `emissao-os` de escopo `alocacoes`. Contrato em `docs/API.md` — mudar campo da API exige mudar junto `app/Integracoes/ControleFrota.php` e `app/Domain/Instalacao/Actions/VeiculosDaFrota.php` do emissaoOS.

Regras da fase 5: API em `routes/api.php` (`/api/integracao/v1`), só GET, autenticada por `integracao.api[:escopo]` (tokens em `tokens_integracao`, comando `integracao:token`); tudo que sai para outro sistema passa por `App\Services\Integracao\VeiculoParaIntegracao` (serialização explícita, nunca `toArray()`); respostas por `App\Support\RespostaApi` e erros de `api/*` no mesmo envelope (bootstrap/app.php). Consumo do RH por `App\Integracoes\GestaoPessoas` (timeout curto, falha silenciosa, `null` = falhou); vínculo manual por `usuarios.colaborador_externo_id` porque a API do RH não expõe CPF/e-mail. PWA: `public/manifest.json`, ícones `images/icone-pwa-*.png`, `public/sw.js` sem cache de páginas. Contrato em `docs/API.md`, deploy em `docs/DEPLOY.md`.

Regras da revisão (23/09/2026): fuso `America/Sao_Paulo`; destino do veículo "livre" sempre por `AlocacaoService::situacaoLivre()` (bloqueio de manutenção → indisponível; aprovada saindo hoje → reservado; senão disponível); relações para `Veiculo`/`Usuario` em models de histórico usam `->withTrashed()`; `value()` do Eloquent devolve o campo **com cast** (enum), não a string; arquivos gerados pelo PowerShell precisam ser gravados **sem BOM** (`New-Object System.Text.UTF8Encoding($false)`), senão `deploy.sh`/`Dockerfile` quebram no Linux; rotina `alocacoes:sincronizar` a cada 15 min.

Regras da fase 4: números de custo saem **só** do `RelatorioCustoService` (tela, PDF, Excel e painel); realizado conta `prestada` por `concluida_em` e `preco_final`; aberto é "comprometido" e nunca soma no realizado. Exportação usa `<a data-gc-export data-gc-export-tipo="PDF|Excel">` e é auditada. PDF com `isFontSubsettingEnabled`. `maatwebsite/excel` está na **v4**: planilha com abas implementa `Export` + `WithMultipleSheets`, e `styles()` retorna `?array`.

Regras da fase 3: toda ação na manutenção passa pelo `ManutencaoService`, que grava `manutencao_movimentacoes`; o veículo volta a disponível na conclusão/cancelamento só se nenhuma outra manutenção o mantiver parado (`bloqueou_veiculo` ou `em_prestacao`); custo de uma manutenção = `preco_final` ou, sem ele, `preco_previsto` (`Manutencao::custo()`); valores pt-BR entram por `App\Support\Numero::dePtBr()`; `aberta_por_id` nulo = aberta pelo scheduler.

Regras da fase 2: transições `em_uso`/`concluida` da alocação só acontecem em `ChecagemService::concluir`; fotos de checagem ficam no disco `local` em `checagens/{id}/` e são servidas por `checagens.foto`; a foto de comparação é sempre a última checagem **concluída** do veículo (`ChecagemService::ultimaChecagemConcluida`); `config/frota.php` define itens de checagem e retenção.

Regra da fase 1 que vale para as próximas: **toda mudança de `situacao`, `estado_atual` ou `km_atual` do veículo passa pelo `VeiculoService`** (`mudarSituacao`, `mudarEstadoFisico`, `atualizarKm`), que grava `veiculo_historico_estados` com a origem (alocacao, checagem, manutencao). Nunca `update()` direto nessas colunas.
Estado atual e próximo passo: ver `docs/PLANEJAMENTO.md`, seção "Andamento".
