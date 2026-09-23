<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\TemRotulo;

enum TipoManutencao: string
{
    use TemRotulo;

    case Planejada = 'planejada';
    case Preventiva = 'preventiva';
    case Imediata = 'imediata';

    public function rotulo(): string
    {
        return match ($this) {
            self::Planejada => 'Planejada',
            self::Preventiva => 'Preventiva',
            self::Imediata => 'Imediata (mau funcionamento)',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Planejada => 'badge-neutro',
            self::Preventiva => 'badge-ativo',
            self::Imediata => 'badge-inativo',
        };
    }
}
