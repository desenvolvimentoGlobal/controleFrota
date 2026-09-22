<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Placa brasileira nos dois formatos (decisão 22/09/2026):
 *  - antigo:   ABC1234
 *  - Mercosul: ABC1D23
 * Aceita hífen e minúsculas na entrada; normalizar para maiúsculas sem hífen
 * antes de gravar.
 */
class Placa implements ValidationRule
{
    public static function normalizar(?string $valor): ?string
    {
        $placa = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $valor) ?? '');

        return $placa === '' ? null : $placa;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $placa = self::normalizar(is_string($value) ? $value : null) ?? '';

        if (! preg_match('/^[A-Z]{3}\d{4}$/', $placa) && ! preg_match('/^[A-Z]{3}\d[A-Z]\d{2}$/', $placa)) {
            $fail('A :attribute deve estar no formato ABC1234 ou ABC1D23.');
        }
    }
}
