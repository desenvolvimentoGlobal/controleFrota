<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\TemRotulo;

/** Cada item fotografado: ainda sem resposta, igual ao anterior, ou com anomalia. */
enum SituacaoItemChecagem: string
{
    use TemRotulo;

    case Pendente = 'pendente';
    case Conforme = 'conforme';
    case Anomalia = 'anomalia';

    public function rotulo(): string
    {
        return match ($this) {
            self::Pendente => 'Pendente',
            self::Conforme => 'Conforme',
            self::Anomalia => 'Anomalia',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Pendente => 'badge-neutro',
            self::Conforme => 'badge-ativo',
            self::Anomalia => 'badge-inativo',
        };
    }
}
