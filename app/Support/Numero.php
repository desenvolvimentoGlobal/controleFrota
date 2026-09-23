<?php

declare(strict_types=1);

namespace App\Support;

/** Conversão e formatação de números no padrão pt-BR. */
final class Numero
{
    /** "12.345,67" → "12345.67"; "12345.67" fica; vazio → null. */
    public static function dePtBr(mixed $valor): ?string
    {
        $texto = trim((string) $valor);
        if ($texto === '') {
            return null;
        }
        if (preg_match('/^-?\d+(\.\d+)?$/', $texto)) {
            return $texto;
        }

        return str_replace(',', '.', str_replace('.', '', $texto));
    }

    public static function moeda(float|int|string|null $valor): string
    {
        return $valor === null ? '—' : 'R$ '.number_format((float) $valor, 2, ',', '.');
    }

    /** Valor para o input de edição: 1234.5 → "1.234,50". */
    public static function campo(float|int|string|null $valor): string
    {
        return $valor === null ? '' : number_format((float) $valor, 2, ',', '.');
    }

    public static function km(int|string|null $valor): string
    {
        return $valor === null ? '—' : number_format((int) $valor, 0, ',', '.').' km';
    }
}
