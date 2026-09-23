<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SituacaoAlocacao;
use App\Enums\SituacaoVeiculo;
use App\Models\Alocacao;
use App\Models\Manutencao;
use App\Models\Usuario;
use App\Models\Veiculo;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

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
            $this->descartarRascunhos($alocacao);

            if ($eraAprovada) {
                $this->liberarVeiculo($alocacao, 'Alocação cancelada');
            }
        });

        $destinatarios = collect([$alocacao->motorista, $alocacao->solicitante])->unique('id')->reject(fn ($u) => $u->id === $quem->id);
        $this->notificar->enviar($destinatarios, 'alocacao_cancelada', 'Alocação cancelada',
            "{$quem->nome} cancelou a alocação do veículo {$alocacao->veiculo->nome} de {$alocacao->saida_prevista->format('d/m H:i')}: {$motivo}",
            route('alocacoes.show', $alocacao, false));
    }

    /**
     * Saída de emergência do admin: encerra uma alocação EM USO sem a
     * checagem de retorno (motorista desligado, celular perdido, acidente).
     * Fica registrado quem encerrou, com qual km e por quê.
     */
    public function encerrarPeloAdmin(Alocacao $alocacao, Usuario $admin, int $km, string $motivo): void
    {
        if (! $admin->ehAdmin()) {
            throw new \DomainException('Só o administrador pode encerrar uma alocação sem checagem.');
        }
        if ($alocacao->situacao !== SituacaoAlocacao::EmUso) {
            throw new \DomainException('Só alocações em uso podem ser encerradas assim.');
        }

        $veiculo = $alocacao->veiculo;
        if ($km < (int) ($alocacao->km_saida ?? $veiculo->km_atual)) {
            throw new \DomainException("A quilometragem informada ({$km}) é menor que a da saída ({$alocacao->km_saida}).");
        }

        DB::transaction(function () use ($alocacao, $veiculo, $admin, $km, $motivo): void {
            $alocacao->update([
                'situacao' => SituacaoAlocacao::Concluida->value,
                'retorno_real' => now(),
                'km_retorno' => $km,
                'observacoes' => trim(($alocacao->observacoes ? $alocacao->observacoes."\n" : '')."Encerrada por {$admin->nome} sem checagem de retorno: {$motivo}"),
            ]);
            $this->descartarRascunhos($alocacao);

            $this->veiculos->atualizarKm($veiculo, $km, 'alocacao', $alocacao->id, "Encerramento sem checagem: {$motivo}");
            if ($veiculo->fresh()->situacao === SituacaoVeiculo::EmUso) {
                $this->veiculos->mudarSituacao($veiculo->fresh(), $this->situacaoLivre($veiculo, $alocacao->id), 'alocacao', $alocacao->id, "Encerramento sem checagem: {$motivo}");
            }
        });

        $this->notificar->enviar(
            $this->notificar->responsaveisPor($alocacao->motorista, $admin->id)->push($alocacao->motorista)->unique('id'),
            'alocacao_encerrada', 'Alocação encerrada pelo administrador',
            "{$admin->nome} encerrou a alocação do veículo {$alocacao->veiculo->nome} sem checagem de retorno: {$motivo}",
            route('alocacoes.show', $alocacao, false));
    }

    /**
     * Rotina de 15 em 15 minutos:
     *  - reserva o veículo das alocações aprovadas que saem hoje (aprovadas
     *    com antecedência não reservam na hora da aprovação);
     *  - expira aprovadas cujo retorno previsto passou sem saída;
     *  - marca e avisa os retornos atrasados.
     *
     * @return array{reservadas: int, expiradas: int, atrasadas: int}
     */
    public function sincronizar(): array
    {
        $expiradas = 0;
        $vencidas = Alocacao::with(['veiculo', 'motorista', 'solicitante'])
            ->where('situacao', SituacaoAlocacao::Aprovada->value)
            ->where('retorno_previsto', '<', now())
            ->get();

        foreach ($vencidas as $alocacao) {
            DB::transaction(function () use ($alocacao): void {
                $alocacao->update(['situacao' => SituacaoAlocacao::Cancelada->value, 'motivo_recusa' => 'Expirada: o veículo não saiu no período previsto.']);
                $this->descartarRascunhos($alocacao);
                $this->liberarVeiculo($alocacao, 'Alocação expirada sem saída');
            });
            $this->notificar->enviar(collect([$alocacao->motorista, $alocacao->solicitante])->unique('id'), 'alocacao_expirada', 'Alocação expirada',
                "A alocação do veículo {$alocacao->veiculo->nome} de {$alocacao->saida_prevista->format('d/m H:i')} expirou sem a checagem de saída.",
                route('alocacoes.show', $alocacao, false));
            $expiradas++;
        }

        $reservadas = 0;
        $doDia = Alocacao::with('veiculo')
            ->where('situacao', SituacaoAlocacao::Aprovada->value)
            ->where('saida_prevista', '<=', now()->endOfDay())
            ->where('retorno_previsto', '>', now())
            ->get();

        foreach ($doDia as $alocacao) {
            $veiculo = $alocacao->veiculo->fresh();
            if ($veiculo->situacao === SituacaoVeiculo::Disponivel) {
                $this->veiculos->mudarSituacao($veiculo, SituacaoVeiculo::Reservado, 'alocacao', $alocacao->id, "Alocação #{$alocacao->id} sai hoje");
                $reservadas++;
            }
        }

        return ['reservadas' => $reservadas, 'expiradas' => $expiradas, 'atrasadas' => $this->marcarAtrasadas()];
    }

    /** Marca e avisa alocações em uso cujo retorno previsto passou. */
    public function marcarAtrasadas(): int
    {
        $atrasadas = Alocacao::with(['motorista.gestor', 'veiculo'])
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

    /**
     * Desfaz a RESERVA feita por esta alocação. Nunca mexe em veículo em uso:
     * cancelar uma alocação de amanhã enquanto outra pessoa está com o carro
     * não pode marcá-lo como disponível.
     */
    public function liberarVeiculo(Alocacao $alocacao, string $motivo): void
    {
        $veiculo = $alocacao->veiculo->fresh();
        if ($veiculo->situacao !== SituacaoVeiculo::Reservado) {
            return;
        }

        $destino = $this->situacaoLivre($veiculo, $alocacao->id);
        if ($destino !== $veiculo->situacao) {
            $this->veiculos->mudarSituacao($veiculo, $destino, 'alocacao', $alocacao->id, $motivo);
        }
    }

    /**
     * Para onde o veículo vai quando fica livre (retorno, cancelamento,
     * fim de manutenção):
     *  - indisponível, se houver manutenção aberta pedindo bloqueio;
     *  - reservado, se houver outra alocação aprovada saindo hoje e ainda
     *    dentro do período (aprovadas vencidas não prendem o carro);
     *  - disponível, caso contrário.
     */
    public function situacaoLivre(Veiculo $veiculo, ?int $ignorarAlocacaoId = null, ?int $ignorarManutencaoId = null): SituacaoVeiculo
    {
        $bloqueada = Manutencao::where('veiculo_id', $veiculo->id)
            ->abertas()->where('bloqueou_veiculo', true)
            ->when($ignorarManutencaoId, fn ($q) => $q->whereKeyNot($ignorarManutencaoId))
            ->exists();

        if ($bloqueada) {
            return SituacaoVeiculo::Indisponivel;
        }

        $outraHoje = Alocacao::where('veiculo_id', $veiculo->id)
            ->where('situacao', SituacaoAlocacao::Aprovada->value)
            ->when($ignorarAlocacaoId, fn ($q) => $q->whereKeyNot($ignorarAlocacaoId))
            ->where('saida_prevista', '<=', now()->endOfDay())
            ->where('retorno_previsto', '>', now())
            ->exists();

        return $outraHoje ? SituacaoVeiculo::Reservado : SituacaoVeiculo::Disponivel;
    }

    /** Apaga rascunhos de checagem (e as fotos) de uma alocação que não vai mais sair/voltar por checagem. */
    private function descartarRascunhos(Alocacao $alocacao): void
    {
        $rascunhos = $alocacao->checagens()->where('situacao', 'rascunho')->with('itens.fotos')->get();

        foreach ($rascunhos as $rascunho) {
            foreach ($rascunho->itens as $item) {
                foreach ($item->fotos as $foto) {
                    Storage::disk(ChecagemService::DISCO)->delete($foto->caminho);
                }
            }
            $rascunho->delete(); // itens e fotos em cascata
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
