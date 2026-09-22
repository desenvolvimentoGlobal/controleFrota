<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Ocorrencia;
use App\Models\Usuario;

class OcorrenciaPolicy
{
    public function before(Usuario $atual): ?bool
    {
        return $atual->ehAdmin() ? true : null;
    }

    public function view(Usuario $atual, Ocorrencia $ocorrencia): bool
    {
        $responsavel = $ocorrencia->responsavel();

        return $ocorrencia->apontada_por_id === $atual->id
            || $responsavel?->id === $atual->id
            || ($atual->ehGestor() && (
                $atual->gerencia($ocorrencia->apontadaPor) || ($responsavel && $atual->gerencia($responsavel))
            ));
    }

    public function contestar(Usuario $atual, Ocorrencia $ocorrencia): bool
    {
        return $ocorrencia->podeSerContestadaPor($atual);
    }

    public function revisar(Usuario $atual, Ocorrencia $ocorrencia): bool
    {
        $responsavel = $ocorrencia->responsavel();

        return $atual->ehGestor() && (
            ($responsavel && $atual->gerencia($responsavel)) || $atual->gerencia($ocorrencia->apontadaPor)
        );
    }
}
