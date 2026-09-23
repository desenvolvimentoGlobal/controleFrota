<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Integracao;

use App\Enums\SituacaoAlocacao;
use App\Enums\SituacaoVeiculo;
use App\Http\Controllers\Controller;
use App\Models\Alocacao;
use App\Models\Veiculo;
use App\Services\Integracao\VeiculoParaIntegracao;
use App\Support\RespostaApi;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * API somente-leitura da frota para os sistemas irmãos (docs/API.md).
 * A frota é pequena: sem paginação, tudo numa resposta com meta.total.
 */
class VeiculoIntegracaoController extends Controller
{
    /** GET /api/integracao/v1/veiculos?situacao=&incluir_baixados=1 */
    public function index(Request $request): JsonResponse
    {
        $validador = Validator::make($request->query(), [
            'situacao' => ['nullable', 'in:'.implode(',', SituacaoVeiculo::valores())],
            'incluir_baixados' => ['nullable', 'boolean'],
        ]);
        if ($validador->fails()) {
            return RespostaApi::erro('validacao', 'Parâmetros inválidos.', 422, $validador->errors()->toArray());
        }

        $veiculos = Veiculo::with('condicoes')
            ->when(! $request->boolean('incluir_baixados') && ! $request->filled('situacao'), fn ($q) => $q->ativos())
            ->when($request->query('situacao'), fn ($q, $s) => $q->where('situacao', $s))
            ->orderBy('nome')
            ->get();

        return RespostaApi::sucesso(
            $veiculos->map(fn (Veiculo $v) => VeiculoParaIntegracao::veiculo($v))->values(),
            ['total' => $veiculos->count()],
        );
    }

    /**
     * GET /api/integracao/v1/veiculos/disponiveis?de=2026-09-30T08:00&ate=2026-09-30T18:00
     *
     * Veículos que PODEM ser alocados no intervalo: não baixados, não
     * indisponíveis nem em manutenção, sem sistema crítico e sem alocação
     * (aguardando, aprovada ou em uso) que cruze o período.
     */
    public function disponiveis(Request $request): JsonResponse
    {
        $validador = Validator::make($request->query(), [
            'de' => ['required', 'date'],
            'ate' => ['required', 'date', 'after:de'],
        ], [], ['de' => 'início', 'ate' => 'fim']);
        if ($validador->fails()) {
            return RespostaApi::erro('validacao', 'Informe de e ate (data e hora), com ate depois de de.', 422, $validador->errors()->toArray());
        }

        $de = CarbonImmutable::parse((string) $request->query('de'));
        $ate = CarbonImmutable::parse((string) $request->query('ate'));
        if ($de->diffInDays($ate) > 31) {
            return RespostaApi::erro('validacao', 'O intervalo máximo é de 31 dias.', 422, ['ate' => ['Intervalo maior que 31 dias.']]);
        }

        $ocupados = Alocacao::whereIn('situacao', SituacaoAlocacao::ocupamAgenda())
            ->where('saida_prevista', '<', $ate)
            ->where(fn ($q) => $q->where('retorno_previsto', '>', $de)->orWhere('situacao', SituacaoAlocacao::EmUso->value))
            ->pluck('veiculo_id')->unique()->all();

        $veiculos = Veiculo::with('condicoes')
            ->whereNotIn('situacao', [SituacaoVeiculo::Baixado->value, SituacaoVeiculo::Indisponivel->value, SituacaoVeiculo::EmManutencao->value])
            ->whereNotIn('id', $ocupados)
            ->orderBy('nome')
            ->get()
            ->reject(fn (Veiculo $v) => $v->temCondicaoCritica())
            ->values();

        return RespostaApi::sucesso(
            $veiculos->map(fn (Veiculo $v) => VeiculoParaIntegracao::veiculo($v)),
            ['total' => $veiculos->count(), 'de' => $de->toIso8601String(), 'ate' => $ate->toIso8601String()],
        );
    }

    /** GET /api/integracao/v1/alocacoes?de=2026-09-01&ate=2026-09-30&veiculo_id= (escopo "alocacoes") */
    public function alocacoes(Request $request): JsonResponse
    {
        $validador = Validator::make($request->query(), [
            'de' => ['required', 'date'],
            'ate' => ['required', 'date', 'after_or_equal:de'],
            'veiculo_id' => ['nullable', 'integer'],
        ]);
        if ($validador->fails()) {
            return RespostaApi::erro('validacao', 'Informe de e ate (datas).', 422, $validador->errors()->toArray());
        }

        $de = CarbonImmutable::parse((string) $request->query('de'))->startOfDay();
        $ate = CarbonImmutable::parse((string) $request->query('ate'))->endOfDay();
        if ($de->diffInDays($ate) > 62) {
            return RespostaApi::erro('validacao', 'O intervalo máximo é de 62 dias.', 422, ['ate' => ['Intervalo maior que 62 dias.']]);
        }

        $alocacoes = Alocacao::with(['veiculo:id,placa', 'motorista:id,nome'])
            ->whereIn('situacao', [...SituacaoAlocacao::ocupamAgenda(), SituacaoAlocacao::Concluida->value])
            ->where('saida_prevista', '<=', $ate)
            ->where('retorno_previsto', '>=', $de)
            ->when($request->integer('veiculo_id'), fn ($q, $id) => $q->where('veiculo_id', $id))
            ->orderBy('saida_prevista')
            ->get();

        return RespostaApi::sucesso(
            $alocacoes->map(fn (Alocacao $a) => VeiculoParaIntegracao::alocacao($a))->values(),
            ['total' => $alocacoes->count()],
        );
    }
}
