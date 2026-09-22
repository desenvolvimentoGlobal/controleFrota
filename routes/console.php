<?php

declare(strict_types=1);

// Agendamentos do Controle de Frota. Dependem de `php artisan schedule:work`
// (serviço `worker`/`scheduler` no Docker) ou de um cron chamando schedule:run.
//
// Previstos (docs/PLANEJAMENTO.md, seção 6), entram com as fases:
//   - 07:00 vencimentos (CNH, licenciamento, seguro, planos)   → fase 1/3
//   - a cada 15 min: alocações com retorno atrasado             → fase 2
//   - 02:00 retenção de fotos de checagem (6 meses)             → fase 2
