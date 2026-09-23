<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\SituacaoOcorrencia;
use App\Models\Ocorrencia;
use App\Models\Usuario;

class OcorrenciaPolicy
{
    public function view(Usuario $atual, Ocorrencia $ocorrencia): bool
    {
        if ($atual->ehAdmin()) {
            return true;
        }

        $responsavel = $ocorrencia->responsavel();

        return $ocorrencia->apontada_por_id === $atual->id
            || $responsavel?->id === $atual->id
            || ($atual->ehGestor() && (
                $atual->gerencia($ocorrencia->apontadaPor) || ($responsavel && $atual->gerencia($responsavel))
            ));
    }

    /** Só o responsável presumido contesta — nem o admin fala por ele. */
    public function contestar(Usuario $atual, Ocorrencia $ocorrencia): bool
    {
        return $ocorrencia->podeSerContestadaPor($atual);
    }

    public function revisar(Usuario $atual, Ocorrencia $ocorrencia): bool
    {
        if ($ocorrencia->situacao !== SituacaoOcorrencia::Aberta) {
            return false;
        }
        if ($atual->ehAdmin()) {
            return true;
        }

        $responsavel = $ocorrencia->responsavel();

        // Ninguém revisa ocorrência contra si mesmo.
        if ($responsavel?->id === $atual->id) {
            return false;
        }

        // Revisa quem responde pelo RESPONSÁVEL presumido. Sem responsável
        // (não houve alocação anterior), quem responde por quem apontou.
        return $atual->ehGestor() && ($responsavel
            ? $atual->gerencia($responsavel)
            : $atual->gerencia($ocorrencia->apontadaPor));
    }
}
