<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\TemRotulo;

/** em_espera → em_prestacao → prestada (+ cancelada). */
enum SituacaoManutencao: string
{
    use TemRotulo;

    case EmEspera = 'em_espera';
    case EmPrestacao = 'em_prestacao';
    case Prestada = 'prestada';
    case Cancelada = 'cancelada';

    public function rotulo(): string
    {
        return match ($this) {
            self::EmEspera => 'Em espera',
            self::EmPrestacao => 'Em prestação',
            self::Prestada => 'Prestada',
            self::Cancelada => 'Cancelada',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::EmEspera => 'badge-pendente',
            self::EmPrestacao => 'badge-ativo',
            self::Prestada => 'badge-neutro',
            self::Cancelada => 'badge-inativo',
        };
    }

    public function aberta(): bool
    {
        return in_array($this, [self::EmEspera, self::EmPrestacao], true);
    }

    /** @return array<int, string> */
    public static function abertas(): array
    {
        return [self::EmEspera->value, self::EmPrestacao->value];
    }
}
