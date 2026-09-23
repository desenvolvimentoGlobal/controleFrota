<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

// Agendamentos do Controle de Frota. Dependem de `php artisan schedule:work`
// (serviço `worker` no Docker) ou de um cron chamando schedule:run.
// O fuso é obrigatório: config('app.timezone') é UTC.

// Retorno atrasado: motorista e gestor são avisados uma vez por alocação.
Schedule::command('alocacoes:marcar-atrasadas')
    ->everyFifteenMinutes()
    ->withoutOverlapping();

// Retenção das fotos de checagem (6 meses; preserva as de ocorrências relevantes).
Schedule::command('checagens:apagar-fotos-antigas')
    ->dailyAt('02:00')
    ->timezone('America/Sao_Paulo')
    ->withoutOverlapping();

// Planos preventivos: abre a manutenção quando o veículo chega perto do km
// ou da data (antecedência em config/frota.php). Depois da noite, antes do
// expediente, para o gestor já ver de manhã.
Schedule::command('manutencoes:verificar-planos')
    ->dailyAt('06:30')
    ->timezone('America/Sao_Paulo')
    ->withoutOverlapping();

// Licenciamento, seguro e CNH vencendo nos próximos 30 dias.
Schedule::command('frota:verificar-vencimentos')
    ->dailyAt('07:00')
    ->timezone('America/Sao_Paulo')
    ->withoutOverlapping();
