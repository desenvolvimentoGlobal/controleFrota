<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\TemRotulo;

/** Estado de um SISTEMA mecânico em `veiculo_condicoes`. */
enum SituacaoCondicao: string
{
    use TemRotulo;

    case Ok = 'ok';
    case Atencao = 'atencao';
    case Critico = 'critico';

    public function rotulo(): string
    {
        return match ($this) {
            self::Ok => 'OK',
            self::Atencao => 'Atenção',
            self::Critico => 'Crítico',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Ok => 'badge-ativo',
            self::Atencao => 'badge-pendente',
            self::Critico => 'badge-inativo',
        };
    }
}
