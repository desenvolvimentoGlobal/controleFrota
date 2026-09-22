<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Usuario;

/**
 * Admin faz tudo. Gestor vê/edita/cria só dentro da própria cadeia e nunca
 * exclui. Os demais só veem a si mesmos.
 */
class UsuarioPolicy
{
    public function before(Usuario $atual): ?bool
    {
        return $atual->ehAdmin() ? true : null;
    }

    public function viewAny(Usuario $atual): bool
    {
        return $atual->ehGestor();
    }

    public function view(Usuario $atual, Usuario $alvo): bool
    {
        return $atual->id === $alvo->id || ($atual->ehGestor() && $atual->gerencia($alvo));
    }

    public function create(Usuario $atual): bool
    {
        return $atual->ehGestor();
    }

    public function update(Usuario $atual, Usuario $alvo): bool
    {
        return $atual->ehGestor() && $atual->gerencia($alvo);
    }

    public function delete(Usuario $atual, Usuario $alvo): bool
    {
        return false; // só admin (via before)
    }
}
