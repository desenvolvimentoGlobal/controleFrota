# Controle de Frota — Planejamento de criação

Data: 22/09/2026. Status: proposta inicial para validação.

Este documento define a base técnica, o modelo de dados, os fluxos e as fases de construção do sistema **Controle de Frota**, a partir do que já existe nos sistemas irmãos `emissaoOS`, `gestaoEmpresarial` e `gestaoPessoas`.

---

## 1. O que aprendemos dos sistemas existentes

| Aspecto | emissaoOS | gestaoEmpresarial | gestaoPessoas |
|---|---|---|---|
| Back-end | Laravel 13 / PHP 8.3 / MySQL 8 | Laravel 13 / PHP 8.3 / MySQL 8 | Laravel 13 / PHP 8.3 / MySQL 8 |
| Front-end | Blade + Tailwind 4 + Alpine.js (Vite, sem CDN) | Blade + Bootstrap 5.3 via CDN, CSS/JS estáticos `gc-` | Idêntico ao gestaoEmpresarial (design system `gc-` copiado verbatim) |
| Camada de negócio | Actions por caso de uso (`app/Domain`) | Controller → Service → Repository | Controller → Service |
| Login | Fortify, campo `usuario` | Manual, `login` ou `email`, coluna `senha` | Manual, `email`, coluna `senha` |
| Perfis | Enum `PerfilUsuario` | Tabela `perfis` + `perfil_id` | Tabela `perfis` + `perfil_id` |
| Permissões | Policies + Gates + middleware `can:` | Middleware `perfil:` + Gates + `temPerfil()` | Igual ao gestaoEmpresarial + recorte por hierarquia (gestor vê equipe) |
| Auditoria | Observer por model → `audit_logs` | `AuditoriaService` + Observers → `logs_auditoria` | Listener global `eloquent.*` → `logs_auditoria` |
| Upload | Disco privado, rota autenticada | Disco privado, rota autenticada | Disco público (`storage:link`) para fotos |
| Integração entre sistemas | Cliente HTTP em `app/Integracoes` | API `/api/integracao/v1`, Bearer SHA-256, envelope `{sucesso, dados}` | Mesma API e envelope |
| Infra | Docker + Traefik | Docker + Traefik, **template de projeto em `docs/template-projeto`** | Docker + Traefik |
| Qualidade | strict_types, PHPStan 6, Pest, Pint | Pint, PHPUnit (216 testes de negócio) | Pint, PHPUnit (~30 testes) |

Pontos comuns aos três: tudo em português (classes, métodos, colunas, rotas), tabelas snake_case no plural, `timestamps()` + `softDeletes()`, dinheiro em `DECIMAL(15,2)`, flash `sucesso`/`erro`/`aviso`, Form Requests, Web Push, scheduler, sem auto-registro de usuário.

## 2. Decisões de base

### 2.1 Stack

- **Laravel 13, PHP 8.3, MySQL 8**, como os três sistemas e como exige o servidor de produção.
- **Design system `gc-` (Bootstrap 5.3 + Bootstrap Icons + Inter via CDN)**, copiando `public/css/app.css`, `public/js/app.js` e `resources/views/layouts/app.blade.php` do `gestaoPessoas` sem alterações. Motivos: dois dos três sistemas já o usam verbatim, não exige build, o `gcAlert`/`gcConfirm`/`data-gc-confirm`/`data-gc-export` já resolvem toasts, confirmações e exportações. O subtítulo da sidebar passa a ser "Controle de Frota".
- **Estrutura de código do gestaoPessoas** (Controllers finos → Services com `DB::transaction` e `\DomainException`, Form Requests, Gates no `AppServiceProvider`, middleware `perfil:`), somada a práticas do emissaoOS que valem a pena: `declare(strict_types=1)`, Enums string-backed com `rotulo()`/`paraSelect()`, Policies para entidades com dono (alocação, checagem), Pest ou PHPUnit para fluxos críticos, PHPStan nível mínimo 5.
- **Infra**: nascer a partir de `gestaoEmpresarial/docs/template-projeto` (`compose.yaml`, `Dockerfile`, `deploy.sh`, `.env.docker.example`, snippet de `trustProxies`/`forceScheme`), com `__PROJETO__ = frota`.
- **Auditoria** pelo listener global do gestaoPessoas (`eloquent.created/updated/deleted/restored: *`), copiando `AuditoriaService`.
- **Notificações**: copiar `NotificacaoService`, sino e Web Push do gestaoPessoas.
- **Uploads de fotos de checagem**: disco **privado** (`storage/app/private/checagens/{checagem_id}/`) servido por rota autenticada, como no gestaoEmpresarial. Fotos de vistoria são evidência e não devem ficar públicas. Foto do veículo (capa) e avatar do usuário podem ficar no disco público.
- **Mobile first nas telas de checagem**: o motorista fotografa pelo celular. Os inputs de foto usam `accept="image/*" capture="environment"` e compressão no navegador antes do envio (canvas → JPEG ~1600px), para não estourar limites de upload.

### 2.2 Usuário e colaborador: uma única entidade

A descrição pede um cadastro de usuário com nome, CPF, email, senha, login, cargo, setor, contato, endereço e tipo. No gestaoPessoas isso é dividido em `usuarios` (login) e `colaboradores` (ficha de RH). Aqui **não há RH**, então a proposta é **uma única tabela `usuarios`** com todos esses campos, mais `gestor_id` (auto-referência, para o perfil gestor enxergar "quem está abaixo dele") e os dados de habilitação (CNH) necessários para saber se a pessoa **pode dirigir**.

"Colaborador permitido" passa a ser: usuário ativo com a flag `pode_dirigir = true`. Os campos de CNH (número, categoria, validade) existem completos no cadastro, mas **não são obrigatórios** para alocar. CNH vencida ou ausente apenas gera aviso na tela de alocação e no painel. Opcionalmente, o usuário pode carregar `colaborador_externo_id` para sincronizar nome/cargo/setor a partir do gestaoPessoas em uma fase posterior.

### 2.3 Perfis e permissões

| Perfil (`perfis.codigo`) | O que vê / faz |
|---|---|
| `admin` | Tudo: cadastros, aprovações, manutenções, usuários, configurações, auditoria. |
| `financeiro` | Visão financeira: custos de manutenção, fornecedores, relatórios de custo por veículo, exportações. Não aloca nem valida checagens. |
| `gestor` | Vê e gerencia os usuários com `gestor_id` = ele (e a cadeia abaixo). Aprova alocações da equipe, valida checagens da equipe, abre manutenção, vê frota. |
| `geral` | Usuário base: solicita alocação para si, faz checagem de saída/retorno, vê suas alocações e os veículos disponíveis. |

Implementação: middleware `perfil:admin,gestor` nas rotas, Gates nomeados (`frota.gerenciar`, `alocacoes.aprovar`, `checagens.validar`, `manutencoes.gerenciar`, `financeiro.ver`, `usuarios.gerenciar`) e Policies para `Alocacao` e `Checagem` (dono ou gestor da cadeia). Regra da casa: esconder o item do menu **e** recusar a rota.

## 3. Domínio

### 3.1 Estados do veículo

A descrição cita "estado atual", "estado inicial" e "estados mecânicos". Na prática de gestão de frotas os estados se separam em três eixos ([Frota162](https://www.frota162.com.br/blog/checklist-veiculos/), [Prolog](https://prologapp.com/blog/modelos-de-checklist-de-veiculos/), [MaintainX](https://www.getmaintainx.com/blog/fleet-vehicle-inspection-checklist), [AutoSist](https://autosist.com/blog/vehicle-inspection-checklist-fleet/)):

1. **Situação operacional** (`veiculos.situacao`, enum, mudada pelo sistema):
   `disponivel` → `reservado` (alocação aprovada, ainda não saiu) → `em_uso` → `disponivel`;
   `em_manutencao` (manutenção em prestação); `indisponivel` (bloqueado por checagem reprovada ou decisão do gestor); `baixado` (vendido/sinistro, sai da frota).

2. **Condição física** (`veiculos.estado_inicial` e `veiculos.estado_atual`, enum): `otimo`, `bom`, `regular`, `ruim`, `avariado`. O `estado_inicial` é gravado no cadastro e nunca muda. O `estado_atual` é atualizado no retorno de cada alocação (estado de volta) e ao concluir uma manutenção.

3. **Estado mecânico** (`veiculo_condicoes`, um registro por sistema do veículo, com `situacao` `ok` / `atencao` / `critico` e observação). Sistemas acompanhados: motor, freios, suspensão/direção, pneus, elétrica/iluminação, fluidos (óleo, arrefecimento, freio), transmissão, ar-condicionado, lataria, interior, documentação (licenciamento, seguro). Um item `critico` bloqueia a alocação e sugere abertura de manutenção imediata; `atencao` gera aviso. Esses itens são alimentados pelas checagens de retorno, pelas manutenções e por edição direta do gestor/admin.

Além disso, o veículo guarda `km_atual` (atualizado a cada retorno e manutenção) e o sistema calcula a próxima manutenção preventiva por km ou por data.

### 3.2 Ciclo da alocação

```
solicitada ──aprovar──> aprovada ──checagem saída aprovada──> em_uso ──checagem retorno aprovada──> concluida
    │                       │                                     │
    └──recusar──> recusada  └──cancelar──> cancelada              └──(retorno_previsto passou)──> em_uso + flag atrasada
```

- Quem cria: qualquer usuário `geral` para si; `gestor`/`admin` para qualquer pessoa da sua cadeia.
- Ao criar: veículo, motorista, **objetivo**, `saida_prevista`, `retorno_previsto`. O sistema valida que o veículo está `disponivel`, que não há outra alocação aprovada no mesmo intervalo e que o motorista tem `pode_dirigir`. CNH ausente ou vencida só avisa.
- Aprovação: `gestor` da cadeia ou `admin`. **Alocações de gestor e admin já nascem aprovadas**; usuário `geral` sempre depende do gestor. Fica em `config/frota.php` para poder mudar.
- **Saída**: o motorista faz a checagem de saída (fotos). A checagem é **auto-validada pelo próprio motorista** (ver 3.3) e a alocação vira `em_uso` na hora, gravando `saida_real`, `km_saida`, `estado_saida`; o veículo vira `em_uso`.
- **Retorno**: mesma checagem, agora de retorno. Ao ser enviada grava `retorno_real`, `km_retorno`, `estado_retorno`, atualiza `veiculos.estado_atual`, `km_atual` e volta o veículo para `disponivel` (ou `indisponivel` se o motorista apontou avaria e o gestor confirmar).
- Anomalia apontada em qualquer checagem notifica o gestor, que decide se abre manutenção imediata ou bloqueia o veículo.

### 3.3 Checagem (vistoria por fotos)

Uma `checagem` pertence a uma alocação e é de tipo `saida` ou `retorno`. Ela tem itens fixos por categoria, cada um exigindo ao menos uma foto:

| Categoria | Itens (cada um = 1 foto obrigatória) |
|---|---|
| `rodas` | dianteira esquerda, dianteira direita, traseira esquerda, traseira direita |
| `lataria` | frente, traseira, lateral esquerda, lateral direita, teto (opcional) |
| `interior` | bancos dianteiros, bancos traseiros, porta-malas |
| `painel` | painel ligado (luzes de alerta, nível de combustível) |
| `kilometragem` | odômetro (o motorista também digita o km, e o validador confere com a foto) |

**Validação entre motoristas (decisão de 22/09/2026).** Não há uma fila de validação obrigatória pelo gestor. A validação é feita **pelo próprio motorista, comparando com a checagem anterior do mesmo veículo**:

1. O motorista abre a checagem de saída. Para cada item, a tela mostra **a última foto registrada daquele item** (a checagem de retorno do motorista anterior, ou a de saída dele mesmo se for retorno) ao lado do botão de câmera.
2. Ele tira a foto nova e marca o item como `conforme` (nada mudou) ou `anomalia` (dano, sujeira, item faltando), com observação obrigatória na anomalia.
3. Ao enviar, a checagem já fica `concluida`. Cada item de anomalia gera um registro em `ocorrencias` apontando: veículo, item, foto anterior (checagem e motorista anterior), foto nova, quem apontou e a alocação em que estava. É o "delata": a anomalia fica vinculada à alocação anterior, cujo motorista é o **responsável presumido**.
4. O gestor da cadeia e o admin são notificados das ocorrências e podem, **se quiserem**, revisar a checagem: confirmar a ocorrência (`confirmada`), descartar (`descartada`, ex.: dano já conhecido) ou reatribuir a responsabilidade. A revisão é opcional e não trava o fluxo.
5. O motorista anterior é notificado quando uma ocorrência é aberta contra a sua alocação e pode registrar uma contestação em texto, que o gestor lê na revisão.

Comparação lado a lado: na tela de retorno, cada item mostra a foto de saída do próprio motorista; na tela de saída, a foto de retorno do motorista anterior. Se o veículo nunca teve checagem, a primeira serve de baseline e não gera ocorrência.

A lista de itens fica em `config/frota.php` para poder crescer sem migration. Situações da checagem: `rascunho` (fotos parciais salvas no celular) → `concluida`; `revisada_pelo_gestor` é uma flag, não uma situação.

### 3.4 Manutenção

- Tipos: `planejada` (agendada por km/data), `preventiva` (revisão periódica), `imediata` (mau funcionamento, geralmente aberta a partir de uma checagem ou condição `critico`).
- Campos: veículo, nome, descrição do problema, preço (previsto e final), fornecedor (tabela `fornecedores`), prazo, localização, km na abertura, quem abriu, responsável.
- Situação: `em_espera` → `em_prestacao` → `prestada` (mais `cancelada`). Entrar em `em_prestacao` coloca o veículo em `em_manutencao`; `prestada` devolve o veículo a `disponivel`, atualiza `estado_atual`, `km_atual` e as condições mecânicas tratadas.
- **Histórico**: tabela `manutencao_movimentacoes` grava toda mudança (situação, preço, prazo, fornecedor, observações, anexos de orçamento/nota fiscal), com usuário, data e descrição legível. Fica visível em linha do tempo na tela da manutenção.
- Financeiro vê custos consolidados: por veículo, por fornecedor, por mês, por tipo.

## 4. Modelo de dados

Convenções: snake_case, português, plural, `id` bigint, FKs `<entidade>_id` com `constrained()`, `timestamps()` e `softDeletes()` nas entidades principais, enums como `string` curta com Enum PHP correspondente, dinheiro `DECIMAL(15,2)`, `->comment()` nas colunas de enum.

### Acesso

- **`perfis`**: `nome`, `codigo` (unique), `descricao`.
- **`setores`**: `nome`, `ativo`. **`cargos`**: `nome`, `ativo`.
- **`usuarios`**: `nome`, `cpf` char(11) unique, `email` unique, `login` (50) unique, `senha` (cast `hashed`), `perfil_id`, `cargo_id`, `setor_id`, `gestor_id` (nullable, auto FK), `telefone`, `celular`, `cep`, `logradouro`, `numero`, `complemento`, `bairro`, `cidade`, `uf`, `pode_dirigir` bool, `cnh_numero`, `cnh_categoria`, `cnh_validade`, `foto_path`, `ativo`, `deve_trocar_senha`, `ultimo_login_em`, `remember_token`, `colaborador_externo_id` (nullable, para sincronia futura com gestaoPessoas).

### Frota

- **`veiculos`**: identificação — `nome`, `placa` unique, `chassi` char(17) unique, `renavam`; descrição — `marca`, `modelo`, `versao`, `ano_fabricacao`, `ano_modelo`, `carroceria` (hatch/sedan/suv/picape/minivan/utilitario), `cor`, `tipo_cor` (solida/metalica/perolizada), `portas`, `lugares`; mecânica — `motor` (ex.: 1.0), `potencia_cv`, `combustivel` (flex/gasolina/diesel/hibrido/eletrico), `cambio` (manual/automatico/cvt/automatizado), `tracao` (dianteira/traseira/integral), `direcao` (mecanica/hidraulica/eletrica); conforto — `ar_condicionado` (nenhum/manual/digital), `central_multimidia` bool, `pareamento_smartphone` bool, `painel_digital` bool, `bancos` (tecido/couro), `vidros_eletricos` bool, `travas_eletricas` bool; segurança — `abs` bool, `esc` bool, `sensor_ponto_cego` bool, `airbags` (texto curto ou quantidade), `cinto_tres_pontos` bool, `isofix` bool, `nota_latin_ncap` tinyint; controle — `situacao`, `estado_inicial`, `estado_atual`, `km_inicial`, `km_atual`, `data_aquisicao`, `valor_aquisicao`, `licenciamento_validade`, `seguro_validade`, `observacoes`, `foto_path`, `ativo`.
- **`veiculo_condicoes`**: `veiculo_id`, `sistema` (motor/freios/...), `situacao` (ok/atencao/critico), `observacao`, `atualizado_por_id`. Unique em (`veiculo_id`, `sistema`).
- **`veiculo_historico_estados`**: `veiculo_id`, `campo` (situacao/estado_atual/km_atual), `valor_anterior`, `valor_novo`, `origem` (alocacao/manutencao/manual), `origem_id`, `usuario_id`, `created_at`.

### Alocação e checagem

- **`alocacoes`**: `veiculo_id`, `motorista_id` (usuarios), `solicitante_id`, `aprovador_id` nullable, `objetivo` (texto), `destino` nullable, `saida_prevista`, `retorno_previsto`, `saida_real`, `retorno_real`, `km_saida`, `km_retorno`, `estado_saida`, `estado_retorno`, `situacao`, `motivo_recusa`, `observacoes`. Índices em (`veiculo_id`, `saida_prevista`) e (`motorista_id`, `situacao`).
- **`checagens`**: `alocacao_id`, `veiculo_id` (redundante, para buscar a última do veículo), `tipo` (saida/retorno), `checagem_anterior_id` nullable (a que serviu de comparação), `km_informado`, `nivel_combustivel` (vazio/quarto/meio/tres_quartos/cheio), `situacao` (rascunho/concluida), `concluida_em`, `estado_geral`, `observacao_motorista`, `revisada_por_id` nullable, `revisada_em`, `observacao_revisao`.
- **`checagem_itens`**: `checagem_id`, `categoria`, `item` (chave do config), `situacao` (conforme/anomalia), `observacao`.
- **`checagem_fotos`**: `checagem_item_id`, `caminho`, `nome_original`, `mime`, `tamanho`, `enviada_por_id`, `apagada_em` nullable (retenção). Um item pode ter mais de uma foto.
- **`ocorrencias`**: `veiculo_id`, `checagem_item_id` (onde foi apontada), `checagem_item_anterior_id` nullable (foto de comparação), `alocacao_responsavel_id` nullable (alocação anterior, responsável presumido), `apontada_por_id`, `descricao`, `situacao` (aberta/confirmada/descartada), `contestacao` texto nullable, `contestada_em`, `revisada_por_id`, `revisada_em`, `manutencao_id` nullable (se virou manutenção).

### Manutenção

- **`fornecedores`**: `razao_social`, `nome_fantasia`, `cnpj`, `telefone`, `email`, endereço, `ativo`.
- **`manutencoes`**: `veiculo_id`, `tipo`, `nome`, `descricao_problema`, `fornecedor_id` nullable, `preco_previsto`, `preco_final`, `prazo`, `localizacao`, `km_abertura`, `situacao`, `aberta_por_id`, `responsavel_id`, `checagem_id` nullable (origem), `inicio_prestacao_em`, `concluida_em`, `observacoes`.
- **`manutencao_movimentacoes`**: `manutencao_id`, `usuario_id`, `tipo` (abertura/mudanca_situacao/alteracao/anexo/comentario), `descricao`, `valores_antigos` json, `valores_novos` json, `created_at`.
- **`manutencao_anexos`**: `manutencao_id`, `caminho`, `nome_original`, `mime`, `tamanho`, `enviado_por_id`.
- **`planos_manutencao`** (fase 3): `veiculo_id`, `nome`, `intervalo_km`, `intervalo_dias`, `ultimo_km`, `ultima_data`, `ativo`. O scheduler gera manutenções `planejada` quando o veículo se aproxima do limite.

### Infra (copiadas dos irmãos)

`logs_auditoria`, `notificacoes`, `inscricoes_push`, `tokens_integracao` (fase 4), tabelas padrão do Laravel (`sessions`, `cache`, `jobs`).

## 5. Telas

Sidebar em seções, filtrada por Gate:

- **Painel**: cards `gc-stat-card` (veículos disponíveis / em uso / em manutenção / indisponíveis), alocações do dia, checagens pendentes de validação, manutenções em espera, alertas (CNH vencendo, licenciamento vencendo, atraso de retorno). Cada perfil vê o recorte que lhe cabe.
- **Frota**: Veículos (index com filtros por situação/marca/placa, create/edit com `_form` em seções, show com abas: dados, condição mecânica, histórico de alocações, manutenções, linha do tempo de estados), Condição mecânica (edição inline por sistema).
- **Alocações**: Minhas alocações (`geral`), Todas/da equipe (`gestor`/`admin`), Nova alocação, Aprovações pendentes, Calendário simples por veículo (semana) para enxergar conflitos.
- **Checagens**: Fazer checagem (tela mobile, um card por item com a foto anterior, botão de câmera, miniatura e escolha conforme/anomalia), Histórico de checagens por veículo (galeria em linha do tempo), Ocorrências (lista para gestor/admin com confirmar/descartar/reatribuir; para o usuário geral, as ocorrências contra ele com botão de contestar).
- **Manutenções**: index com filtros por situação/tipo/veículo/fornecedor, create, show com linha do tempo e anexos, mudança de situação com confirmação `gcConfirm`.
- **Financeiro** (`financeiro`/`admin`): custo por veículo, por fornecedor, por período; exportação PDF/Excel via `data-gc-export`.
- **Administração** (`admin`; `gestor` só Usuários da equipe): Usuários, Setores, Cargos, Fornecedores, Perfis (somente leitura), Auditoria, Configurações (auto-aprovação, itens de checagem, lembretes).
- **Autenticação**: login por `login` ou `email` (como gestaoEmpresarial), troca de senha obrigatória no primeiro acesso, sem auto-registro.

## 6. Notificações e agendamentos

- Sino + Web Push (copiados do gestaoPessoas) para: alocação solicitada (gestor), alocação aprovada/recusada (motorista), ocorrência aberta (gestor, admin e motorista responsável presumido), ocorrência contestada/confirmada/descartada (envolvidos), retorno atrasado (motorista e gestor), manutenção mudou de situação (quem abriu e financeiro), CNH/licenciamento/seguro a vencer (admin e dono).
- `routes/console.php`: diariamente às 07h checar vencimentos (CNH, licenciamento, seguro, plano de manutenção); a cada 15 min marcar alocações com retorno atrasado; **diariamente às 02h apagar arquivos de fotos de checagens concluídas há mais de 6 meses** (o registro da checagem, dos itens e das ocorrências permanece; só o arquivo some e `apagada_em` é preenchido; fotos ligadas a ocorrência `aberta` ou `confirmada` são preservadas); reenvio de outbox de integração (fase 5).

## 7. Fases de construção

Cada fase termina com algo usável e testado. Estimativas em dias úteis de um desenvolvedor.

| Fase | Entrega | Conteúdo | Estimativa |
|---|---|---|---|
| **0. Fundação** | Projeto sobe no WAMP e no Docker, login funciona | `laravel new`, copiar template-projeto (`__PROJETO__=frota`), `lang/pt_BR`, layout `gc-`, CSS/JS, login, perfis + seeders, `usuarios` completo, middleware `perfil:`, Gates, `AuditoriaService`, `NotificacaoService`, CLAUDE.md do projeto, CI com Pint + PHPStan + testes | 3 dias |
| **1. Cadastros** | Frota cadastrada | CRUD de veículos (form em seções), condição mecânica, histórico de estados, setores, cargos, fornecedores, usuários com CNH e hierarquia, painel inicial | 4 dias |
| **2. Alocação e checagem** | Núcleo operacional | Solicitação, aprovação (auto para gestor/admin), conflito de agenda, checagem de saída/retorno com fotos (mobile) comparando com a checagem anterior, ocorrências com responsável presumido, contestação e revisão opcional do gestor, transições de situação do veículo, notificações, rotina de retenção de fotos, testes dos fluxos | 7 dias |
| **3. Manutenção** | Ciclo de manutenção completo | CRUD, movimentações com linha do tempo, anexos, abertura a partir de checagem/condição crítica, planos preventivos + scheduler, efeito na situação do veículo | 4 dias |
| **4. Financeiro e relatórios** | Visão de custos | Relatórios por veículo/fornecedor/período, exportação PDF (dompdf) e Excel (maatwebsite), painel do financeiro, alertas de vencimento | 3 dias |
| **5. Integrações e polimento** | Conectado ao ecossistema | API `/api/integracao/v1` (veículos disponíveis para o emissaoOS, que hoje digita veículos à mão no planejamento de instalação), sincronia de colaboradores com o gestaoPessoas, Web Push/PWA, deploy em produção com `deploy.sh` | 3 dias |

Total estimado: **24 dias úteis**, sem contar validações com os usuários entre fases.

## 8. Estrutura de pastas prevista

```
app/
  Enums/            PerfilUsuario, SituacaoVeiculo, CondicaoVeiculo, SituacaoAlocacao,
                    TipoChecagem, SituacaoChecagem, TipoManutencao, SituacaoManutencao, ...
  Http/Controllers/ Auth/, Admin/, Frota/ (Veiculo, CondicaoVeiculo), Alocacao/,
                    Checagem/, Manutencao/, Financeiro/, Api/Integracao/
  Http/Middleware/  VerificarPerfil, AutenticarIntegracao, ExigirTrocaDeSenha
  Http/Requests/    por domínio (Store/Update<Entidade>Request)
  Models/           Usuario, Perfil, Setor, Cargo, Veiculo, VeiculoCondicao, Alocacao,
                    Checagem, ChecagemItem, ChecagemFoto, Fornecedor, Manutencao, ...
  Policies/         AlocacaoPolicy, ChecagemPolicy, UsuarioPolicy
  Services/         AlocacaoService, ChecagemService, ManutencaoService, VeiculoService,
                    AuditoriaService, NotificacaoService, WebPushService, HierarquiaService
  Support/          Formato, Numero, Voltar, Cpf (Rule), Placa (Rule)
config/frota.php    itens de checagem, sistemas mecânicos, auto-aprovação, limites de foto
resources/views/    layouts/, components/, auth/, painel/, veiculos/, alocacoes/,
                    checagens/, manutencoes/, financeiro/, usuarios/, ...
public/css/app.css  public/js/app.js  (copiados do gestaoPessoas) + public/js/checagem.js
```

## 9. Decisões tomadas (22/09/2026)

1. **CNH**: campos completos no cadastro (número, categoria, validade), mas **não obrigatórios** para alocar. Quem pode dirigir é definido por `pode_dirigir`; CNH vencida ou ausente só avisa.
2. **Auto-aprovação**: gestor e admin **não precisam** de aprovação para as próprias alocações. Usuário geral sempre precisa. Configurável em `config/frota.php`.
3. **Validação da checagem**: feita **pelo próprio motorista**, comparando cada item com a última foto do veículo (motorista anterior). Anomalia gera `ocorrencia` contra a alocação anterior (responsável presumido), o motorista anterior pode contestar e o gestor revisa **se quiser**. Detalhes em 3.3.
4. **Fornecedores**: **só cadastro próprio**, sem integração com o gestaoEmpresarial.
5. **Placa**: aceitar Mercosul (`ABC1D23`) e formato antigo (`ABC1234`), gravando em maiúsculas sem hífen.
6. **Retenção das fotos**: apagar o arquivo das fotos de checagens concluídas há **mais de 6 meses**, preservando registros e as fotos ligadas a ocorrências abertas ou confirmadas.
7. **Nome na infra**: `frota` (`frota.<dominio>`, prefixo de containers, volumes e router do Traefik).

**Regras definidas na revisão de 23/09/2026 (a confirmar com o usuário):**

8. **Janela da saída**: a checagem de saída só vale a partir do dia da saída prevista e antes do retorno previsto.
9. **Expiração**: alocação aprovada cujo retorno previsto passou sem saída é cancelada automaticamente ("expirada").
10. **Encerramento pelo admin**: o admin pode encerrar uma alocação em uso sem a checagem de retorno, informando km e motivo.
11. **Motorista com carro**: não pode ser inativado nem excluído enquanto tiver alocação aprovada ou em uso.

## 10. Andamento

| Data | Fase | O que foi feito | Próximo passo |
|---|---|---|---|
| 22/09/2026 | 0 — Fundação | Projeto Laravel 13 criado; template de infra (`frota`); design system `gc-` copiado; migrations de perfis, setores, cargos, usuários (ficha completa + CNH + `gestor_id`), notificações, push e auditoria; login por login/e-mail; troca de senha obrigatória; Gates e `UsuarioPolicy` com recorte por cadeia; CRUD de usuários; painel inicial; `config/frota.php`; testes de autenticação e de usuários; `CLAUDE.md`. | Fase 1. |
| 23/09/2026 | 1 — Cadastros | Tabelas `veiculos`, `veiculo_condicoes`, `veiculo_historico_estados`, `fornecedores`; enums de situação/condição e listas de características; `VeiculoService` (criar com condições iniciais, mudar situação/estado/km com histórico, condições em lote); CRUD de veículos com form em seções, ficha com abas (dados, condição mecânica, histórico) e modais de situação e estado/km; fornecedores (admin, gestor, financeiro) com validação de CNPJ; setores e cargos em tela única (admin); painel com cards de frota, vencimentos e CNH; seeder de veículos; 6 testes novos. | Fase 2. |
| 24/09/2026 | 2 — Alocação e checagem | Tabelas `alocacoes`, `checagens`, `checagem_itens`, `checagem_fotos`, `ocorrencias`; `AlocacaoService` (solicitar com validação de agenda/aptidão/condição crítica, auto-aprovação de gestor/admin, aprovar, recusar, cancelar, marcar atrasadas); `ChecagemService` (rascunho com comparação à última checagem do veículo, foto por item no disco privado, conclusão que move alocação e veículo, abertura de ocorrências contra a alocação anterior, retenção de 6 meses); `OcorrenciaService` (contestação e revisão com reatribuição); Policies; telas de alocações, agenda semanal, checagem mobile com câmera e compressão no navegador, comparação lado a lado, histórico por veículo, ocorrências; comandos agendados `alocacoes:marcar-atrasadas` e `checagens:apagar-fotos-antigas`; painel com operação; 4 testes de fluxo (292 asserções no total). | Fase 3. |
| 23/09/2026 | 3 — Manutenção | Tabelas `manutencoes`, `manutencao_movimentacoes`, `manutencao_anexos`, `planos_manutencao`; `ManutencaoService` (abrir com bloqueio opcional do veículo, editar com movimentação legível, iniciar prestação, concluir com preço final/km/estado e sistemas de volta a OK, cancelar, comentar, anexar, verificar planos); abertura a partir de ocorrência e de condição mecânica com problema; planos preventivos por km e/ou dias na ficha do veículo; telas de lista com total de custo, formulário, ficha com linha do tempo e anexos no disco privado; permissões: consulta admin/gestor/financeiro, gestão admin/gestor, anotação (comentário e anexo) também financeiro; comandos `manutencoes:verificar-planos` (06:30) e `frota:verificar-vencimentos` (07:00); painel com cards de manutenção; fornecedor com manutenção não é excluído; helper `App\Support\Numero`; 6 testes novos (25 no total). | Fase 4. |
| 23/09/2026 | 4 — Financeiro | `RelatorioCustoService` como fonte única: custo realizado = manutenções prestadas com conclusão no período, pelo preço final; comprometido = manutenções abertas pelo previsto, fora do total; custo por km = custo ÷ km das alocações concluídas no período; variação realizado × previsto; visões por veículo, fornecedor, tipo e mês; tela com filtros persistentes, cards, gráfico mensal (Chart.js) e lista de manutenções; PDF (dompdf, subconjunto de fontes) e Excel com abas Resumo e Manutenções (maatwebsite/excel 4); exportações auditadas; bloco financeiro no painel (6 meses, total do ano, custo/km, top 5 veículos); acesso admin e financeiro; 3 testes novos (28 no total). | Revisão. |
| 23/09/2026 | Revisão de bugs (fases 0–4) | Segurança: gestor não promove a admin nem tira gente da cadeia (validação no servidor), não edita admin, admin não exclui a si mesmo, fotos de checagem só para quem pode ver, usuário inativado perde a sessão. Estados: saída só com veículo disponível/reservado, sem sistema crítico e dentro do período; rascunho de alocação cancelada não conclui mais; cancelar não libera veículo em uso; retorno e fim de manutenção voltam a "reservado" se houver saída hoje e a "indisponível" se houver bloqueio; bloqueio pedido com carro na rua vale no retorno; manutenção não ressuscita veículo baixado nem desfaz indisponível manual; mudança manual não mexe em veículo em uso/reservado/manutenção; dupla conclusão da checagem travada. Rotina `alocacoes:sincronizar` (15 min) reserva no dia, expira aprovadas que não saíram e marca atrasos. Admin encerra alocação sem checagem. Fuso America/Sao_Paulo. Relações com `withTrashed` (excluir veículo/usuário não quebra telas). Infra: BOM removido de deploy.sh/Dockerfile/compose, worker roda `schedule:work`, volume de fotos públicas montado no nginx. 15 testes novos (43 no total). | Fase 5: API `/api/integracao/v1` (veículos disponíveis para o emissaoOS), sincronia de colaboradores com o gestaoPessoas, PWA e deploy com `deploy.sh`. |

## 11. Referências usadas

- Sistemas irmãos: `C:\wamp64\www\gestaoPessoas` (layout, CSS/JS `gc-`, login, usuários, auditoria, notificações), `C:\wamp64\www\gestaoEmpresarial` (`docs/template-projeto`, uploads privados, API de integração), `C:\wamp64\www\emissaoOS` (strict_types, Enums, Policies, tabela `veiculos` do planejamento de instalação).
- Checklist e estados de frota: [Frota162](https://www.frota162.com.br/blog/checklist-veiculos/), [Prolog App](https://prologapp.com/blog/modelos-de-checklist-de-veiculos/), [MaintainX](https://www.getmaintainx.com/blog/fleet-vehicle-inspection-checklist), [AutoSist](https://autosist.com/blog/vehicle-inspection-checklist-fleet/), [Fleetworthy](https://fleetworthy.com/resources/blog/fleet-maintenance-checklist/).
