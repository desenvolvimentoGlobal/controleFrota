<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Usuario;
use App\Models\Veiculo;
use App\Services\NotificacaoService;
use Illuminate\Console\Command;

/**
 * Avisos diários de vencimento: licenciamento e seguro dos veículos (admins)
 * e CNH dos motoristas (o próprio motorista + gestor e admins). Usa o
 * anti-spam do NotificacaoService: uma notificação não lida por tipo é
 * atualizada em vez de empilhar.
 */
class VerificarVencimentos extends Command
{
    protected $signature = 'frota:verificar-vencimentos';

    protected $description = 'Avisa sobre licenciamento, seguro e CNH vencidos ou a vencer';

    public function handle(NotificacaoService $notificar): int
    {
        $limite = now()->addDays((int) config('frota.alertas.dias_antecedencia_vencimento', 30));

        $veiculos = Veiculo::ativos()
            ->where(fn ($q) => $q->whereDate('licenciamento_validade', '<=', $limite)->orWhereDate('seguro_validade', '<=', $limite))
            ->orderBy('nome')->get();

        if ($veiculos->isNotEmpty()) {
            $linhas = $veiculos->map(fn (Veiculo $v) => "{$v->nome}: ".implode(' ', $v->avisos()))->implode(' | ');
            $notificar->enviar(
                $notificar->comPerfis(['admin']),
                'vencimento_veiculos',
                "{$veiculos->count()} veículo(s) com documento vencendo",
                mb_substr($linhas, 0, 900),
                route('painel', [], false),
                atualizarNaoLida: true,
            );
        }

        $motoristas = Usuario::with('gestor')->where('ativo', true)->where('pode_dirigir', true)
            ->whereNotNull('cnh_validade')->whereDate('cnh_validade', '<=', $limite)->get();

        foreach ($motoristas as $motorista) {
            $texto = $motorista->cnhVencida()
                ? "A CNH de {$motorista->nome} venceu em {$motorista->cnh_validade->format('d/m/Y')}."
                : "A CNH de {$motorista->nome} vence em {$motorista->cnh_validade->format('d/m/Y')}.";

            $notificar->enviar(
                $notificar->responsaveisPor($motorista)->push($motorista)->unique('id'),
                "cnh_vencimento_{$motorista->id}",
                'CNH vencendo',
                $texto,
                null,
                atualizarNaoLida: true,
            );
        }

        $this->info("{$veiculos->count()} veículo(s) e {$motoristas->count()} CNH(s) com vencimento próximo.");

        return self::SUCCESS;
    }
}
