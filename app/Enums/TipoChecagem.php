<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\TemRotulo;

enum TipoChecagem: string
{
    use TemRotulo;

    case Saida = 'saida';
    case Retorno = 'retorno';

    public function rotulo(): string
    {
        return match ($this) {
            self::Saida => 'Saída',
            self::Retorno => 'Retorno',
        };
    }
}
