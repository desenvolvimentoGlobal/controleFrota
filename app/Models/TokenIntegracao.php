<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Token de um sistema consumidor da API de integração. O texto claro só
 * existe na criação (comando `integracao:token`); o banco guarda o hash.
 */
class TokenIntegracao extends Model
{
    protected $table = 'tokens_integracao';

    /** Agenda de alocações: expõe nomes de motoristas, por isso é escopo à parte. */
    public const ESCOPO_ALOCACOES = 'alocacoes';

    public const ESCOPOS = [self::ESCOPO_ALOCACOES];

    protected $fillable = ['nome', 'token_hash', 'escopos', 'ultimo_uso_em'];

    protected function casts(): array
    {
        return ['escopos' => 'array', 'ultimo_uso_em' => 'datetime'];
    }

    /**
     * Cria (ou recria, invalidando o anterior) o token de um consumidor.
     *
     * @param  array<int, string>  $escopos
     * @return string o token em texto claro — mostrar uma vez e descartar
     */
    public static function criarPara(string $nome, array $escopos = []): string
    {
        $texto = Str::random(48);

        self::updateOrCreate(['nome' => $nome], [
            'token_hash' => hash('sha256', $texto),
            'escopos' => $escopos === [] ? null : array_values($escopos),
            'ultimo_uso_em' => null,
        ]);

        return $texto;
    }

    public static function autenticar(?string $texto): ?self
    {
        if ($texto === null || $texto === '') {
            return null;
        }

        return self::where('token_hash', hash('sha256', $texto))->first();
    }

    public function temEscopo(string $escopo): bool
    {
        return in_array($escopo, $this->escopos ?? [], true);
    }
}
