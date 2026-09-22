<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SituacaoAlocacao;
use App\Enums\SituacaoVeiculo;
use App\Models\Alocacao;
use App\Models\Usuario;
use App\Models\Veiculo;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Ciclo da alocação (docs/PLANEJAMENTO.md 3.2):
 *   solicitada → aprovada → em_uso → concluida (+ recusada, cancelada)
 * As transições para em_uso e concluida são feitas pela ChecagemService,
 * quando a checagem de saída/retorno é concluída.
 */
class AlocacaoService
{
    public function __construct(
        private readonly VeiculoService $veiculos,
        private readonly NotificacaoService $notificar,
    ) {}

    /**
     * @param  array{veiculo_id: int, motorista_id: int, objetivo: string, destino?: string|null, saida_prevista: string, retorno_previsto: string, observacoes?: string|null}  $dados
     * @return array{0: Alocacao, 1: array<int, string>} alocação e avisos (não bloqueantes)
     */
    public function solicitar(array $dados, Usuario $solicitante): array
    {
        $veiculo = Veiculo::with('condicoes')->findOrFail($dados['veiculo_id']);
        $motorista = Usuario::findOrFail($dados['motorista_id']);
        $saida = CarbonImmutable::parse($dados['saida_prevista']);
        $retorno = CarbonImmutable::parse($dados['retorno_previsto']);

        $this->validar($veiculo, $motorista, $saida, $retorno, $solicitante);

        $avisos = array_filter([$motorista->avisoHabilitacao()]);

        $autoAprovada = $solicitante->temAlgumPerfil(...config('frota.alocacao.perfis_auto_aprovados', []));

        $alocacao = DB::transaction(function () use ($dados, $veiculo, $motorista, $solicitante, $autoAprovada, $saida, $retorno): Alocacao {
            $alocacao = Alocacao::create([
                'veiculo_id' => $veiculo->id,
                'motorista_id' => $motorista->id,
                'solicitante_id' => $solicitante->id,
                'objetivo' => $dados['objetivo'],
                'destino' => $dados['destino'] ?? null,
                'saida_prevista' => $saida,
                'retorno_previsto' => $retorno,
                'observacoes' => $dados['observacoes'] ?? null,
                'situacao' => SituacaoAlocacao::Solicitada->value,
            ]);

            if ($autoAprovada) {
                $this->efetivarAprovacao($alocacao, $solicitante);
            }

            return $alocacao;
        });

        if ($autoAprovada) {
            if ($motorista->id !== $solicitante->id) {
                $this->notificar->enviar([$motorista], 'alocacao_aprovada', 'Veículo alocado para você',
                    "{$solicitante->nome} alocou o veículo {$veiculo->nome} para você em {$saida->format('d/m H:i')}. Objetivo: {$alocacao->objetivo}.",
                    route('alocacoes.show', $alocacao, false));
            }
        } else {
            $this->notificar->enviar($this->notificar->responsaveisPor($motorista, $solicitante->id), 'alocacao_solicitada',
                'Alocação aguardando aprovação',
                "{$solicitante->nome} solicitou o veículo {$veiculo->nome} para {$motorista->nome} em {$saida->format('d/m H:i')}. Objetivo: {$alocacao->objetivo}.",
                route('alocacoes.show', $alocacao, false));
        }

        return [$alocacao, array_values($avisos)];
    }

    public function aprovar(Alocacao $alocacao, Usuario $aprovador): void
    {
        if ($alocacao->situacao !== SituacaoAlocacao::Solicitada) {
            throw new \DomainException('Só alocações aguardando aprovação podem ser aprovadas.');
        }

        $alocacao->load(['veiculo.condicoes', 'motorista']);
        $this->validar($alocacao->veiculo, $alocacao->motorista, $alocacao->saida_prevista->toImmutable(), $alocacao->retorno_previsto->toImmutable(), $aprovador, $alocacao->id);

        DB::transaction(fn () => $this->efetivarAprovacao($alocacao, $aprovador));

        $this->notificar->enviar([$alocacao->motorista], 'alocacao_aprovada', 'Alocação aprovada',
            "{$aprovador->nome} aprovou a alocação do veículo {$alocacao->veiculo->nome} em {$alocacao->saida_prevista->format('d/m H:i')}. Faça a checagem de saída antes de sair.",
            route('alocacoes.show', $alocacao, false));
    }

    public function recusar(Alocacao $alocacao, Usuario $aprovador, string $motivo): void
    {
        if ($alocacao->situacao !== SituacaoAlocacao::Solicitada) {
            throw new \DomainException('Só alocações aguardando aprovação podem ser recusadas.');
        }

        $alocacao->update([
            'situacao' => SituacaoAlocacao::Recusada->value,
            'aprovador_id' => $aprovador->id,
            'motivo_recusa' => $motivo,
        ]);

        $this->notificar->enviar([$alocacao->motorista], 'alocacao_recusada', 'Alocação recusada',
            "{$aprovador->nome} recusou a alocação do veículo {$alocacao->veiculo->nome}: {$motivo}",
            route('alocacoes.show', $alocacao, false));
    }

    public function cancelar(Alocacao $alocacao, Usuario $quem, string $motivo): void
    {
        if (! in_array($alocacao->situacao, [SituacaoAlocacao::Solicitada, SituacaoAlocacao::Aprovada], true)) {
            throw new \DomainException('Só alocações ainda não iniciadas podem ser canceladas.');
        }

        DB::transaction(function () use ($alocacao, $motivo): void {
            $eraAprovada = $alocacao->situacao === SituacaoAlocacao::Aprovada;

            $alocacao->update(['situacao' => SituacaoAlocacao::Cancelada->value, 'motivo_recusa' => $motivo]);

            if ($eraAprovada) {
                $this->liberarVeiculo($alocacao, 'Alocação cancelada');
            }
        });

        $destinatarios = collect([$alocacao->motorista, $alocacao->solicitante])->unique('id')->reject(fn ($u) => $u->id === $quem->id);
        $this->notificar->enviar($destinatarios, 'alocacao_cancelada', 'Alocação cancelada',
            "{$quem->nome} cancelou a alocação do veículo {$alocacao->veiculo->nome} de {$alocacao->saida_prevista->format('d/m H:i')}: {$motivo}",
            route('alocacoes.show', $alocacao, false));
    }

    /** Scheduler: marca e avisa alocações em uso cujo retorno previsto passou. */
    public function marcarAtrasadas(): int
    {
        $atrasadas = Alocacao::with(['motorista', 'veiculo'])
            ->where('situacao', SituacaoAlocacao::EmUso->value)
            ->whereNull('atrasada_em')
            ->where('retorno_previsto', '<', now())
            ->get();

        foreach ($atrasadas as $alocacao) {
            $alocacao->update(['atrasada_em' => now()]);

            $destinatarios = $this->notificar->responsaveisPor($alocacao->motorista)->push($alocacao->motorista)->unique('id');
            $this->notificar->enviar($destinatarios, 'alocacao_atrasada', 'Retorno atrasado',
                "O veículo {$alocacao->veiculo->nome} com {$alocacao->motorista->nome} deveria ter voltado em {$alocacao->retorno_previsto->format('d/m H:i')}.",
                route('alocacoes.show', $alocacao, false));
        }

        return $atrasadas->count();
    }

    // ─── Apoio ─────────────────────────────────────────────────────────────────

    private function efetivarAprovacao(Alocacao $alocacao, Usuario $aprovador): void
    {
        $alocacao->update([
            'situacao' => SituacaoAlocacao::Aprovada->value,
            'aprovador_id' => $aprovador->id,
            'aprovada_em' => now(),
        ]);

        // Reserva o veículo só se a saída for hoje: reservar com dias de
        // antecedência esconderia o veículo de todo mundo.
        if ($alocacao->saida_prevista->isToday() || $alocacao->saida_prevista->isPast()) {
            $veiculo = $alocacao->veiculo;
            if ($veiculo->situacao === SituacaoVeiculo::Disponivel) {
                $this->veiculos->mudarSituacao($veiculo, SituacaoVeiculo::Reservado, 'alocacao', $alocacao->id, "Alocação #{$alocacao->id} aprovada");
            }
        }
    }

    public function liberarVeiculo(Alocacao $alocacao, string $motivo): void
    {
        $veiculo = $alocacao->veiculo;
        if (in_array($veiculo->situacao, [SituacaoVeiculo::Reservado, SituacaoVeiculo::EmUso], true)) {
            $this->veiculos->mudarSituacao($veiculo, SituacaoVeiculo::Disponivel, 'alocacao', $alocacao->id, $motivo);
        }
    }

    private function validar(Veiculo $veiculo, Usuario $motorista, CarbonImmutable $saida, CarbonImmutable $retorno, Usuario $quem, ?int $ignorarId = null): void
    {
        if ($retorno->lte($saida)) {
            throw new \DomainException('O retorno previsto precisa ser depois da saída.');
        }

        if (! $motorista->ativo || ! $motorista->pode_dirigir) {
            throw new \DomainException("{$motorista->nome} não está apto a dirigir veículos da frota.");
        }

        if (! $quem->ehAdmin() && $motorista->id !== $quem->id && ! $quem->gerencia($motorista)) {
            throw new \DomainException('Você só pode alocar veículos para você ou para a sua equipe.');
        }

        if ($veiculo->situacao === SituacaoVeiculo::Baixado || $veiculo->situacao === SituacaoVeiculo::Indisponivel || $veiculo->situacao === SituacaoVeiculo::EmManutencao) {
            throw new \DomainException("O veículo está {$veiculo->situacao->rotulo()}.");
        }

        if ($veiculo->temCondicaoCritica()) {
            throw new \DomainException('O veículo tem sistema mecânico em estado crítico e não pode ser alocado.');
        }

        $conflito = Alocacao::conflitantes($veiculo->id, $saida, $retorno, $ignorarId)->with('motorista:id,nome')->first();
        if ($conflito) {
            throw new \DomainException(
                "O veículo já está alocado para {$conflito->motorista->nome} de {$conflito->saida_prevista->format('d/m H:i')} a {$conflito->retorno_previsto->format('d/m H:i')}."
            );
        }
    }
}
