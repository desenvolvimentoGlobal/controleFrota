<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\TemRotulo;

/** Situação OPERACIONAL do veículo. Mudada pelo sistema (alocação, manutenção) ou pelo gestor. */
enum SituacaoVeiculo: string
{
    use TemRotulo;

    case Disponivel = 'disponivel';
    case Reservado = 'reservado';
    case EmUso = 'em_uso';
    case EmManutencao = 'em_manutencao';
    case Indisponivel = 'indisponivel';
    case Baixado = 'baixado';

    public function rotulo(): string
    {
        return match ($this) {
            self::Disponivel => 'Disponível',
            self::Reservado => 'Reservado',
            self::EmUso => 'Em uso',
            self::EmManutencao => 'Em manutenção',
            self::Indisponivel => 'Indisponível',
            self::Baixado => 'Baixado',
        };
    }

    /** Classe de badge do design system gc-. */
    public function badge(): string
    {
        return match ($this) {
            self::Disponivel => 'badge-ativo',
            self::Reservado, self::EmUso => 'badge-pendente',
            self::EmManutencao => 'badge-neutro',
            self::Indisponivel, self::Baixado => 'badge-inativo',
        };
    }

    /** Situações que o gestor pode definir à mão (as demais são do fluxo). */
    public static function manuais(): array
    {
        return [self::Disponivel, self::Indisponivel, self::Baixado];
    }

    public function podeSerAlocado(): bool
    {
        return $this === self::Disponivel;
    }
}
