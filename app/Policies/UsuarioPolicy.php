<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Usuario;

/**
 * Admin faz tudo, menos excluir a si mesmo. Gestor vê, edita e cria só
 * dentro da própria cadeia, nunca mexe em admin e nunca exclui. Os demais
 * só veem a si mesmos.
 */
class UsuarioPolicy
{
    public function viewAny(Usuario $atual): bool
    {
        return $atual->ehAdmin() || $atual->ehGestor();
    }

    public function view(Usuario $atual, Usuario $alvo): bool
    {
        return $atual->ehAdmin() || $atual->id === $alvo->id || ($atual->ehGestor() && $atual->gerencia($alvo));
    }

    public function create(Usuario $atual): bool
    {
        return $atual->ehAdmin() || $atual->ehGestor();
    }

    public function update(Usuario $atual, Usuario $alvo): bool
    {
        if ($atual->ehAdmin()) {
            return true;
        }

        return $atual->ehGestor() && ! $alvo->ehAdmin() && $atual->gerencia($alvo);
    }

    public function delete(Usuario $atual, Usuario $alvo): bool
    {
        return $atual->ehAdmin() && $atual->id !== $alvo->id;
    }
}
