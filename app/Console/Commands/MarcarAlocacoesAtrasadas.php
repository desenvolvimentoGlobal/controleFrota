<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\AlocacaoService;
use Illuminate\Console\Command;

class MarcarAlocacoesAtrasadas extends Command
{
    protected $signature = 'alocacoes:marcar-atrasadas';

    protected $description = 'Marca e notifica alocações em uso cujo retorno previsto já passou';

    public function handle(AlocacaoService $servico): int
    {
        $total = $servico->marcarAtrasadas();
        $this->info("{$total} alocação(ões) marcada(s) como atrasada(s).");

        return self::SUCCESS;
    }
}
