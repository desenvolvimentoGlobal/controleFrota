<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** Valida um CNPJ (14 dígitos + dígitos verificadores). Aceita com ou sem máscara. */
class Cnpj implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $cnpj = preg_replace('/\D/', '', (string) $value) ?? '';

        if (strlen($cnpj) !== 14 || preg_match('/^(\d)\1{13}$/', $cnpj)) {
            $fail('O :attribute informado não é válido.');

            return;
        }

        foreach ([12, 13] as $posicao) {
            $pesos = $posicao === 12 ? [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2] : [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
            $soma = 0;
            foreach ($pesos as $i => $peso) {
                $soma += (int) $cnpj[$i] * $peso;
            }
            $digito = $soma % 11 < 2 ? 0 : 11 - ($soma % 11);

            if ((int) $cnpj[$posicao] !== $digito) {
                $fail('O :attribute informado não é válido.');

                return;
            }
        }
    }
}
