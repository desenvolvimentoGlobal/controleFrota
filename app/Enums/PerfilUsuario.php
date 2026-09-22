<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Códigos da tabela `perfis`. A tabela existe para o relacionamento e o
 * rótulo; o enum existe para o código não virar string solta pelo sistema.
 */
enum PerfilUsuario: string
{
    case Admin = 'admin';
    case Financeiro = 'financeiro';
    case Gestor = 'gestor';
    case Geral = 'geral';

    public function rotulo(): string
    {
        return match ($this) {
            self::Admin => 'Administrador',
            self::Financeiro => 'Financeiro',
            self::Gestor => 'Gestor',
            self::Geral => 'Geral',
        };
    }

    public function descricao(): string
    {
        return match ($this) {
            self::Admin => 'Acesso total ao sistema.',
            self::Financeiro => 'Visão financeira: custos de manutenção, fornecedores e relatórios.',
            self::Gestor => 'Gerencia a própria equipe, aprova alocações e revisa ocorrências.',
            self::Geral => 'Usuário base: solicita alocações e faz checagens.',
        };
    }

    /** @return array<string, string> codigo => rótulo */
    public static function paraSelect(): array
    {
        $opcoes = [];
        foreach (self::cases() as $caso) {
            $opcoes[$caso->value] = $caso->rotulo();
        }

        return $opcoes;
    }
}
