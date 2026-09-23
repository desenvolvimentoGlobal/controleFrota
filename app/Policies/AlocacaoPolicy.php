<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\SituacaoAlocacao;
use App\Models\Alocacao;
use App\Models\Usuario;

/**
 * Sem `before()` para o admin: as ações dependem do ESTADO da alocação
 * (só aprova o que está aguardando, só o motorista faz checagem). Liberar
 * tudo para o admin mostrava botões que o serviço depois recusava.
 */
class AlocacaoPolicy
{
    public function view(Usuario $atual, Alocacao $alocacao): bool
    {
        return $atual->ehAdmin() || $this->envolvido($atual, $alocacao) || $this->gestorDe($atual, $alocacao);
    }

    public function create(Usuario $atual): bool
    {
        return $atual->ativo;
    }

    public function aprovar(Usuario $atual, Alocacao $alocacao): bool
    {
        return $alocacao->situacao === SituacaoAlocacao::Solicitada
            && ($atual->ehAdmin() || $this->gestorDe($atual, $alocacao));
    }

    public function cancelar(Usuario $atual, Alocacao $alocacao): bool
    {
        return in_array($alocacao->situacao, [SituacaoAlocacao::Solicitada, SituacaoAlocacao::Aprovada], true)
            && ($atual->ehAdmin() || $this->envolvido($atual, $alocacao) || $this->gestorDe($atual, $alocacao));
    }

    /** Saída de emergência: admin encerra alocação em uso sem checagem de retorno. */
    public function encerrar(Usuario $atual, Alocacao $alocacao): bool
    {
        return $atual->ehAdmin() && $alocacao->situacao === SituacaoAlocacao::EmUso;
    }

    /** Checagem é pessoal: só o motorista, nem o admin faz por ele. */
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
