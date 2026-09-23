<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Integracoes\GestaoPessoas;
use App\Services\Integracao\SincronizarColaboradores;
use Illuminate\Console\Command;

class SincronizarColaboradoresCommand extends Command
{
    protected $signature = 'integracao:sincronizar-colaboradores';

    protected $description = 'Atualiza nome, setor e cargo dos usuários vinculados ao Gestão de Pessoas e inativa os desligados';

    public function handle(GestaoPessoas $rh, SincronizarColaboradores $servico): int
    {
        if (! $rh->configurada()) {
            $this->warn('Integração com o Gestão de Pessoas não configurada (GESTAO_PESSOAS_URL / GESTAO_PESSOAS_TOKEN).');

            return self::SUCCESS;
        }

        $r = $servico->executar();

        if (! $r['ok']) {
            $this->error('Gestão de Pessoas indisponível. Nada foi alterado.');

            return self::FAILURE;
        }

        $this->info("{$r['vinculados']} vinculado(s): {$r['atualizados']} atualizado(s), {$r['inativados']} inativado(s), "
            ."{$r['bloqueados']} desligado(s) com veículo, {$r['sem_ficha']} sem ficha no RH.");

        return self::SUCCESS;
    }
}
