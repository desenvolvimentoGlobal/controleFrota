<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\TemRotulo;

/** Anomalia apontada numa checagem: aberta até o gestor revisar (se quiser). */
enum SituacaoOcorrencia: string
{
    use TemRotulo;

    case Aberta = 'aberta';
    case Confirmada = 'confirmada';
    case Descartada = 'descartada';

    public function rotulo(): string
    {
        return match ($this) {
            self::Aberta => 'Aberta',
            self::Confirmada => 'Confirmada',
            self::Descartada => 'Descartada',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Aberta => 'badge-pendente',
            self::Confirmada => 'badge-inativo',
            self::Descartada => 'badge-neutro',
        };
    }
}
