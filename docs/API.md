# API de integração — Controle de Frota

API **somente leitura** para os sistemas irmãos (emissaoOS, gestaoEmpresarial...).
Mesmo padrão do gestaoPessoas e do gestaoEmpresarial: token Bearer, envelope
`{sucesso, dados, meta}` e escopos fechados por padrão.

Base: `https://<host-do-frota>/api/integracao/v1`

## Autenticação

O token é emitido **no Controle de Frota**, uma vez por sistema consumidor:

```bash
php artisan integracao:token criar emissao-os                     # só veículos
php artisan integracao:token criar emissao-os --escopos=alocacoes # + agenda
php artisan integracao:token listar
php artisan integracao:token revogar emissao-os
```

O texto do token aparece **uma única vez**. Recriar com o mesmo nome invalida
o anterior e substitui os escopos. O banco guarda só o hash SHA-256.

Toda requisição leva:

```
Authorization: Bearer <token>
Accept: application/json
```

Limite: 120 requisições por minuto por consumidor.

## Envelope

Sucesso:

```json
{ "sucesso": true, "dados": [ ... ], "meta": { "total": 2 } }
```

Erro:

```json
{ "sucesso": false, "erro": { "codigo": "validacao", "mensagem": "...", "campos": { "ate": ["..."] } } }
```

| Código | HTTP | Quando |
|---|---|---|
| `nao_autenticado` | 401 | Token ausente, inválido ou revogado |
| `permissao_negada` | 403 | Token sem o escopo exigido pela rota |
| `validacao` | 422 | Parâmetro faltando ou inválido (`campos` detalha) |
| `nao_encontrado` | 404 | Rota inexistente |
| `metodo_nao_permitido` | 405 | Método diferente de GET |
| `limite_excedido` | 429 | Mais de 120 requisições por minuto |
| `erro_interno` | 500 | Falha inesperada (sem detalhes internos) |

Datas saem em ISO 8601 com fuso (`2026-09-30T08:00:00-03:00`). Parâmetros de
data aceitam `YYYY-MM-DD` ou `YYYY-MM-DDTHH:MM` (hora de Brasília).

## Endpoints

### `GET /veiculos`

Frota atual (sem os baixados).

| Parâmetro | Opcional | Descrição |
|---|---|---|
| `situacao` | sim | `disponivel`, `reservado`, `em_uso`, `em_manutencao`, `indisponivel` ou `baixado` |
| `incluir_baixados` | sim | `1` para trazer também os baixados |

Cada veículo:

```json
{
  "id": 1, "nome": "Onix branco 01", "placa": "BRA2E19", "placa_formatada": "BRA-2E19",
  "descricao": "Chevrolet Onix LT 1.0 Turbo 2024", "marca": "Chevrolet", "modelo": "Onix",
  "versao": "LT 1.0 Turbo", "ano_modelo": 2024, "cor": "Branco", "carroceria": "hatch", "lugares": 5,
  "situacao": "disponivel", "situacao_rotulo": "Disponível", "estado_atual": "otimo",
  "km_atual": 12500, "sistema_critico": false, "atualizado_em": "2026-09-23T09:00:00-03:00"
}
```

Não saem: chassi, RENAVAM, valor de aquisição, seguradora.

### `GET /veiculos/disponiveis?de=...&ate=...`

Veículos que **podem ser alocados** no intervalo (máximo de 31 dias):
não baixados, não indisponíveis, fora de manutenção, sem sistema mecânico
crítico e sem alocação (aguardando, aprovada ou em uso) que cruze o período.
Um veículo em uso com retorno atrasado conta como ocupado.

`meta` traz `total`, `de` e `ate`. Mesmo formato de veículo acima.

### `GET /alocacoes?de=...&ate=...&veiculo_id=` — escopo `alocacoes`

Agenda da frota no período (máximo de 62 dias): alocações aguardando,
aprovadas, em uso e concluídas que cruzam o intervalo. Traz o **nome do
motorista**, por isso exige o escopo.

```json
{
  "id": 7, "veiculo_id": 1, "placa": "BRA2E19", "motorista": "Maria da Silva",
  "objetivo": "Instalação no cliente X", "destino": "Campinas",
  "saida_prevista": "2026-09-30T08:00:00-03:00", "retorno_previsto": "2026-09-30T18:00:00-03:00",
  "saida_real": null, "retorno_real": null, "situacao": "aprovada", "situacao_rotulo": "Aprovada"
}
```

## Como o emissaoOS pode consumir

Hoje o planejamento de instalação do emissaoOS guarda veículos digitados à mão
(tabela `veiculos`, `Veiculo::catalogo()` e `Veiculo::lembrar()`). O caminho
sugerido, no mesmo molde de `app/Integracoes/GestaoPessoas.php` de lá:

1. Criar `app/Integracoes/ControleFrota.php` com `veiculos()` e
   `disponiveis($de, $ate)`, timeout curto e falha silenciosa (`null`).
2. No planejamento, montar o autocomplete a partir de `disponiveis()` para o
   dia da instalação, guardando descrição e placa como hoje (o "retrato"
   continua valendo se a API cair).
3. Configurar `CONTROLE_FROTA_URL` e o token emitido aqui.

Essa mudança é no repositório do emissaoOS e ainda **não foi feita**.
