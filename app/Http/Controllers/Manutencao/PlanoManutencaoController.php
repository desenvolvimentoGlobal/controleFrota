<?php

declare(strict_types=1);

namespace App\Http\Controllers\Manutencao;

use App\Http\Controllers\Controller;
use App\Models\PlanoManutencao;
use App\Models\Veiculo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/** Planos preventivos, gerenciados na ficha do veículo (aba Manutenção). */
class PlanoManutencaoController extends Controller
{
    public function store(Veiculo $veiculo, Request $request): RedirectResponse
    {
        Gate::authorize('manutencoes.gerenciar');
        $dados = $this->validar($request);

        $veiculo->planosManutencao()->create($dados + [
            'ultimo_km' => $dados['ultimo_km'] ?? $veiculo->km_atual,
            'ultima_data' => $dados['ultima_data'] ?? today()->toDateString(),
            'ativo' => true,
        ]);

        return back()->with('sucesso', 'Plano de manutenção criado.');
    }

    public function update(PlanoManutencao $plano, Request $request): RedirectResponse
    {
        Gate::authorize('manutencoes.gerenciar');
        $plano->update($this->validar($request) + ['ativo' => $request->boolean('ativo')]);

        return back()->with('sucesso', 'Plano de manutenção atualizado.');
    }

    public function destroy(PlanoManutencao $plano): RedirectResponse
    {
        Gate::authorize('manutencoes.gerenciar');
        $plano->delete();

        return back()->with('sucesso', 'Plano de manutenção removido.');
    }

    /** @return array<string, mixed> */
    private function validar(Request $request): array
    {
        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:120'],
            'intervalo_km' => ['nullable', 'integer', 'min:100', 'max:1000000', 'required_without:intervalo_dias'],
            'intervalo_dias' => ['nullable', 'integer', 'min:1', 'max:3650', 'required_without:intervalo_km'],
            'ultimo_km' => ['nullable', 'integer', 'min:0', 'max:9999999'],
            'ultima_data' => ['nullable', 'date', 'before_or_equal:today'],
        ], [
            'intervalo_km.required_without' => 'Informe o intervalo em km ou em dias.',
            'intervalo_dias.required_without' => 'Informe o intervalo em km ou em dias.',
        ], [
            'nome' => 'nome', 'intervalo_km' => 'intervalo em km', 'intervalo_dias' => 'intervalo em dias',
            'ultimo_km' => 'km da última execução', 'ultima_data' => 'data da última execução',
        ]);

        return array_filter($dados, fn ($v) => $v !== null && $v !== '') + ['intervalo_km' => null, 'intervalo_dias' => null];
    }
}
