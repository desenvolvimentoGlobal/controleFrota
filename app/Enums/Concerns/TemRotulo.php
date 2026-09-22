<?php

declare(strict_types=1);

namespace App\Enums\Concerns;

/** Utilitários comuns aos enums string-backed com `rotulo()`. */
trait TemRotulo
{
    /** @return array<string, string> valor => rótulo */
    public static function paraSelect(): array
    {
        $opcoes = [];
        foreach (self::cases() as $caso) {
            $opcoes[$caso->value] = $caso->rotulo();
        }

        return $opcoes;
    }

    /** @return array<int, string> */
    public static function valores(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }

    public static function rotuloDe(?string $valor): string
    {
        return $valor === null ? '—' : (self::tryFrom($valor)?->rotulo() ?? $valor);
    }
}
