<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\TemRotulo;

/** solicitada → aprovada → em_uso → concluida (+ recusada, cancelada). */
enum SituacaoAlocacao: string
{
    use TemRotulo;

    case Solicitada = 'solicitada';
    case Aprovada = 'aprovada';
    case EmUso = 'em_uso';
    case Concluida = 'concluida';
    case Recusada = 'recusada';
    case Cancelada = 'cancelada';

    public function rotulo(): string
    {
        return match ($this) {
            self::Solicitada => 'Aguardando aprovação',
            self::Aprovada => 'Aprovada',
            self::EmUso => 'Em uso',
            self::Concluida => 'Concluída',
            self::Recusada => 'Recusada',
            self::Cancelada => 'Cancelada',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Solicitada => 'badge-pendente',
            self::Aprovada, self::EmUso => 'badge-ativo',
            self::Concluida => 'badge-neutro',
            self::Recusada, self::Cancelada => 'badge-inativo',
        };
    }

    /** Situações que ocupam o veículo na agenda (conflito de horário). */
    public static function ocupamAgenda(): array
    {
        return [self::Solicitada->value, self::Aprovada->value, self::EmUso->value];
    }

    public function encerrada(): bool
    {
        return in_array($this, [self::Concluida, self::Recusada, self::Cancelada], true);
    }
}
