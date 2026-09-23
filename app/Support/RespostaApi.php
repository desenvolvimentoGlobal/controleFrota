<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\JsonResponse;

/**
 * Envelope da API de integração, igual ao dos sistemas irmãos:
 *   sucesso: {sucesso: true, dados: ..., meta: {...}}
 *   erro:    {sucesso: false, erro: {codigo, mensagem, campos?}}
 */
final class RespostaApi
{
    /** @param  array<string, mixed>  $meta */
    public static function sucesso(mixed $dados, array $meta = [], int $status = 200): JsonResponse
    {
        $corpo = ['sucesso' => true, 'dados' => $dados];
        if ($meta !== []) {
            $corpo['meta'] = $meta;
        }

        return response()->json($corpo, $status, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** @param  array<string, array<int, string>>|null  $campos */
    public static function erro(string $codigo, string $mensagem, int $status, ?array $campos = null): JsonResponse
    {
        $erro = ['codigo' => $codigo, 'mensagem' => $mensagem];
        if ($campos !== null) {
            $erro['campos'] = $campos;
        }

        return response()->json(['sucesso' => false, 'erro' => $erro], $status, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
