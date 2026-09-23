<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\ManutencaoService;
use Illuminate\Console\Command;

class VerificarPlanosManutencao extends Command
{
    protected $signature = 'manutencoes:verificar-planos';

    protected $description = 'Abre manutenção preventiva para os planos que venceram (por km ou por data)';

    public function handle(ManutencaoService $servico): int
    {
        $total = $servico->verificarPlanos();
        $this->info("{$total} manutenção(ões) preventiva(s) aberta(s).");

        return self::SUCCESS;
    }
}
