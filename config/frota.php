<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Configuração própria do Controle de Frota
|--------------------------------------------------------------------------
| Tudo que é regra de negócio parametrizável e que não merece migration.
| Decisões de 22/09/2026 estão em docs/PLANEJAMENTO.md, seção 9.
*/
return [

    'nome' => 'Controle de Frota',

    /*
    | Alocação: perfis cuja solicitação já nasce aprovada. Usuário `geral`
    | sempre depende do gestor.
    */
    'alocacao' => [
        'perfis_auto_aprovados' => ['admin', 'gestor'],
    ],

    /*
    | Checagem: itens fotografados na saída e no retorno, por categoria.
    | Chave = identificador gravado em `checagem_itens.item`. Acrescentar item
    | aqui basta; nenhuma migration é necessária.
    */
    'checagem' => [
        'categorias' => [
            'rodas' => [
                'rotulo' => 'Rodas e pneus',
                'itens' => [
                    'roda_dianteira_esquerda' => 'Roda dianteira esquerda',
                    'roda_dianteira_direita' => 'Roda dianteira direita',
                    'roda_traseira_esquerda' => 'Roda traseira esquerda',
                    'roda_traseira_direita' => 'Roda traseira direita',
                ],
            ],
            'lataria' => [
                'rotulo' => 'Lataria',
                'itens' => [
                    'lataria_frente' => 'Frente',
                    'lataria_traseira' => 'Traseira',
                    'lataria_lateral_esquerda' => 'Lateral esquerda',
                    'lataria_lateral_direita' => 'Lateral direita',
                ],
            ],
            'interior' => [
                'rotulo' => 'Interior',
                'itens' => [
                    'interior_bancos_dianteiros' => 'Bancos dianteiros',
                    'interior_bancos_traseiros' => 'Bancos traseiros',
                    'interior_porta_malas' => 'Porta-malas',
                ],
            ],
            'painel' => [
                'rotulo' => 'Painel',
                'itens' => [
                    'painel_ligado' => 'Painel ligado (luzes de alerta e combustível)',
                ],
            ],
            'kilometragem' => [
                'rotulo' => 'Quilometragem',
                'itens' => [
                    'odometro' => 'Odômetro',
                ],
            ],
        ],

        // Fotos: limites de upload e retenção (decisão: apagar após 6 meses,
        // preservando as ligadas a ocorrências abertas/confirmadas).
        'foto_max_kb' => 4096,
        'retencao_meses' => 6,
    ],

    /*
    | Sistemas mecânicos acompanhados em `veiculo_condicoes`.
    */
    'sistemas_mecanicos' => [
        'motor' => 'Motor',
        'freios' => 'Freios',
        'suspensao_direcao' => 'Suspensão e direção',
        'pneus' => 'Pneus',
        'eletrica_iluminacao' => 'Elétrica e iluminação',
        'fluidos' => 'Fluidos (óleo, arrefecimento, freio)',
        'transmissao' => 'Transmissão',
        'ar_condicionado' => 'Ar-condicionado',
        'lataria' => 'Lataria',
        'interior' => 'Interior',
        'documentacao' => 'Documentação (licenciamento, seguro)',
    ],

    /*
    | Manutenção: antecedência com que um plano preventivo abre a manutenção
    | (o que vier primeiro: km ou dias) e limites de anexo.
    */
    'manutencao' => [
        'antecedencia_km' => 500,
        'antecedencia_dias' => 7,
        'anexo_max_kb' => 10240,
    ],

    /*
    | Alertas do painel e do scheduler.
    */
    'alertas' => [
        'dias_antecedencia_vencimento' => 30,
    ],
];
