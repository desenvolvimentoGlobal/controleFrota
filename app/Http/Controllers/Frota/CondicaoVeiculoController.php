<?php

declare(strict_types=1);

namespace App\Http\Controllers\Frota;

use App\Enums\SituacaoCondicao;
use App\Http\Controllers\Controller;
use App\Models\Veiculo;
use App\Services\VeiculoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/** Atualização em lote da condição mecânica (aba do veículo). */
class CondicaoVeiculoController extends Controller
{
    public function update(Request $request, Veiculo $veiculo, VeiculoService $servico): RedirectResponse
    {
        Gate::authorize('frota.gerenciar');

        $dados = $request->validate([
            'condicoes' => ['required', 'array'],
            'condicoes.*.situacao' => ['required', 'in:'.implode(',', SituacaoCondicao::valores())],
            'condicoes.*.observacao' => ['nullable', 'string', 'max:500'],
        ], [], ['condicoes.*.situacao' => 'situação', 'condicoes.*.observacao' => 'observação']);

        $alteradas = $servico->atualizarCondicoes($veiculo, $dados['condicoes'], (int) auth()->id());

        $mensagem = $alteradas === 0 ? 'Nenhuma condição foi alterada.' : "{$alteradas} condição(ões) atualizada(s).";

        if ($veiculo->fresh('condicoes')->temCondicaoCritica()) {
            return back()->with('aviso', $mensagem.' Há sistema em estado CRÍTICO: o veículo não pode ser alocado até a correção.');
        }

        return back()->with('sucesso', $mensagem);
    }
}
