<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\SituacaoAlocacao;
use App\Models\Alocacao;
use App\Models\Usuario;

class AlocacaoPolicy
{
    public function before(Usuario $atual): ?bool
    {
        return $atual->ehAdmin() ? true : null;
    }

    public function view(Usuario $atual, Alocacao $alocacao): bool
    {
        return $this->envolvido($atual, $alocacao) || $this->gestorDe($atual, $alocacao);
    }

    public function create(Usuario $atual): bool
    {
        return $atual->ativo;
    }

    public function aprovar(Usuario $atual, Alocacao $alocacao): bool
    {
        return $atual->ehGestor()
            && $alocacao->situacao === SituacaoAlocacao::Solicitada
            && $atual->gerencia($alocacao->motorista);
    }

    public function cancelar(Usuario $atual, Alocacao $alocacao): bool
    {
        return in_array($alocacao->situacao, [SituacaoAlocacao::Solicitada, SituacaoAlocacao::Aprovada], true)
            && ($this->envolvido($atual, $alocacao) || $this->gestorDe($atual, $alocacao));
    }

    public function checar(Usuario $atual, Alocacao $alocacao): bool
    {
        return $alocacao->motorista_id === $atual->id && $alocacao->proximaChecagem() !== null;
    }

    private function envolvido(Usuario $atual, Alocacao $alocacao): bool
    {
        return $alocacao->motorista_id === $atual->id || $alocacao->solicitante_id === $atual->id;
    }

    private function gestorDe(Usuario $atual, Alocacao $alocacao): bool
    {
        return $atual->ehGestor() && $atual->gerencia($alocacao->motorista);
    }
}
