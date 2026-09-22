<?php

declare(strict_types=1);

namespace App\Services;

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

    public function revisar(Ocorrencia $ocorrencia, Usuario $revisor, SituacaoOcorrencia $decisao, ?string $observacao, ?int $novaAlocacaoResponsavelId = null): void
    {
        if ($ocorrencia->situacao !== SituacaoOcorrencia::Aberta) {
            throw new \DomainException('Esta ocorrência já foi revisada.');
        }
        if ($decisao === SituacaoOcorrencia::Aberta) {
            throw new \DomainException('Escolha confirmar ou descartar.');
        }

        $dados = [
            'situacao' => $decisao->value,
            'revisada_por_id' => $revisor->id,
            'revisada_em' => now(),
            'observacao_revisao' => $observacao,
        ];

        if ($novaAlocacaoResponsavelId !== null && $novaAlocacaoResponsavelId !== $ocorrencia->alocacao_responsavel_id) {
            $nova = Alocacao::where('veiculo_id', $ocorrencia->veiculo_id)->findOrFail($novaAlocacaoResponsavelId);
            $dados['alocacao_responsavel_id'] = $nova->id;
        }

        $ocorrencia->update($dados);
        $ocorrencia->refresh()->load(['alocacaoResponsavel.motorista', 'apontadaPor', 'veiculo']);

        $envolvidos = collect([$ocorrencia->apontadaPor, $ocorrencia->responsavel()])->filter()->unique('id')->reject(fn ($u) => $u->id === $revisor->id);
        $this->notificar->enviar($envolvidos, 'ocorrencia_revisada', "Ocorrência {$decisao->rotulo()}",
            "{$revisor->nome} marcou a ocorrência #{$ocorrencia->id} do veículo {$ocorrencia->veiculo->nome} como {$decisao->rotulo()}.".($observacao ? " {$observacao}" : ''),
            route('ocorrencias.show', $ocorrencia, false));
    }
}
