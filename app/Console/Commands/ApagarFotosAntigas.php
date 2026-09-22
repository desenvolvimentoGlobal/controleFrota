<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\ChecagemService;
use Illuminate\Console\Command;

class ApagarFotosAntigas extends Command
{
    protected $signature = 'checagens:apagar-fotos-antigas';

    protected $description = 'Retenção (decisão 22/09/2026): apaga o arquivo das fotos de checagens concluídas há mais de 6 meses, exceto as ligadas a ocorrências abertas/confirmadas';

    public function handle(ChecagemService $servico): int
    {
        $total = $servico->apagarFotosAntigas();
        $this->info("{$total} foto(s) apagada(s).");

        return self::SUCCESS;
    }
}
