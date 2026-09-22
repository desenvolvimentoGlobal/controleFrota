<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\TemRotulo;

/** Condição FÍSICA geral (estado inicial / estado atual / estado de saída e retorno). */
enum CondicaoVeiculo: string
{
    use TemRotulo;

    case Otimo = 'otimo';
    case Bom = 'bom';
    case Regular = 'regular';
    case Ruim = 'ruim';
    case Avariado = 'avariado';

    public function rotulo(): string
    {
        return match ($this) {
            self::Otimo => 'Ótimo',
            self::Bom => 'Bom',
            self::Regular => 'Regular',
            self::Ruim => 'Ruim',
            self::Avariado => 'Avariado',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Otimo, self::Bom => 'badge-ativo',
            self::Regular => 'badge-pendente',
            self::Ruim, self::Avariado => 'badge-inativo',
        };
    }
}
