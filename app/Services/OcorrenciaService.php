<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SituacaoAlocacao;
use App\Enums\SituacaoOcorrencia;
use App\Models\Alocacao;
use App\Models\Ocorrencia;
use App\Models\Usuario;

/** Contestação pelo responsável presumido e revisão (opcional) pelo gestor. */
class OcorrenciaService
{
    public function __construct(private readonly NotificacaoService $notificar) {}

    public function contestar(Ocorrencia $ocorrencia, Usuario $quem, string $texto): void
    {
        if (! $ocorrencia->podeSerContestadaPor($quem)) {
            throw new \DomainException('Esta ocorrência não pode ser contestada por você (ou já foi).');
        }

        $ocorrencia->update(['contestacao' => $texto, 'contestada_em' => now()]);

        $revisores = $this->notificar->responsaveisPor($quem, $quem->id)->push($ocorrencia->apontadaPor)->unique('id');
        $this->notificar->enviar($revisores, 'ocorrencia_contestada', 'Ocorrência contestada',
            "{$quem->nome} contestou a ocorrência #{$ocorrencia->id} do veículo {$ocorrencia->veiculo->nome}: ".mb_substr($texto, 0, 120),
            route('ocorrencias.show', $ocorrencia, false));
    }

    public function revisar(Ocorrencia $ocorrencia, Usuario $revisor, SituacaoOcorrencia $decisao, ?string $observacao): void
    {
        if ($ocorrencia->situacao !== SituacaoOcorrencia::Aberta) {
            throw new \DomainException('Esta ocorrência já foi revisada.');
        }
        if ($decisao === SituacaoOcorrencia::Aberta) {
            throw new \DomainException('Escolha confirmar ou descartar.');
        }

        $ocorrencia->update([
            'situacao' => $decisao->value,
            'revisada_por_id' => $revisor->id,
            'revisada_em' => now(),
            'observacao_revisao' => $observacao,
        ]);
        $ocorrencia->refresh()->load(['alocacaoResponsavel.motorista', 'apontadaPor', 'veiculo']);

        $envolvidos = collect([$ocorrencia->apontadaPor, $ocorrencia->responsavel()])->filter()->unique('id')->reject(fn ($u) => $u->id === $revisor->id);
        $this->notificar->enviar($envolvidos, 'ocorrencia_revisada', "Ocorrência {$decisao->rotulo()}",
            "{$revisor->nome} marcou a ocorrência #{$ocorrencia->id} do veículo {$ocorrencia->veiculo->nome} como {$decisao->rotulo()}.".($observacao ? " {$observacao}" : ''),
            route('ocorrencias.show', $ocorrencia, false));
    }

    /**
     * Troca o responsável presumido SEM encerrar a ocorrência: o novo
     * responsável é avisado e ganha o direito de contestar.
     */
    public function reatribuir(Ocorrencia $ocorrencia, Usuario $revisor, int $novaAlocacaoId, ?string $observacao): void
    {
        if ($ocorrencia->situacao !== SituacaoOcorrencia::Aberta) {
            throw new \DomainException('Só ocorrências abertas podem ter o responsável trocado.');
        }
        if ($novaAlocacaoId === $ocorrencia->alocacao_responsavel_id) {
            throw new \DomainException('Esta já é a alocação responsável.');
        }

        $nova = Alocacao::with('motorista')
            ->where('veiculo_id', $ocorrencia->veiculo_id)
            ->where('situacao', SituacaoAlocacao::Concluida->value)
            ->find($novaAlocacaoId);

        if ($nova === null) {
            throw new \DomainException('Escolha uma alocação concluída deste veículo.');
        }
        if (! $revisor->ehAdmin() && ! $revisor->gerencia($nova->motorista)) {
            throw new \DomainException('Você só pode atribuir a responsabilidade a alguém da sua equipe.');
        }

        $ocorrencia->update([
            'alocacao_responsavel_id' => $nova->id,
            'contestacao' => null,
            'contestada_em' => null,
            'observacao_revisao' => $observacao,
        ]);

        $this->notificar->enviar([$nova->motorista], 'ocorrencia_contra_voce', "Ocorrência atribuída a você: {$ocorrencia->veiculo->nome}",
            "{$revisor->nome} indicou a sua alocação #{$nova->id} como responsável pela ocorrência #{$ocorrencia->id}. Você pode contestar.",
            route('ocorrencias.show', $ocorrencia, false));
    }
}
