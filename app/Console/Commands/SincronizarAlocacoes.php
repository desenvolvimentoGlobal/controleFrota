<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\AlocacaoService;
use Illuminate\Console\Command;

/**
 * Rotina de 15 em 15 minutos das alocações: reserva o veículo no dia da
 * saída, expira aprovadas que não saíram e avisa retornos atrasados.
 */
class SincronizarAlocacoes extends Command
{
    protected $signature = 'alocacoes:sincronizar';

    protected $description = 'Reserva veículos do dia, expira alocações não iniciadas e marca retornos atrasados';

    public function handle(AlocacaoService $servico): int
    {
        $r = $servico->sincronizar();
        $this->info("{$r['reservadas']} reservada(s), {$r['expiradas']} expirada(s), {$r['atrasadas']} atrasada(s).");

        return self::SUCCESS;
    }
}
