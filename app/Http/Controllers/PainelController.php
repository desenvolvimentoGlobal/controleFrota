<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\SituacaoAlocacao;
use App\Enums\SituacaoCondicao;
use App\Enums\SituacaoManutencao;
use App\Enums\SituacaoVeiculo;
use App\Models\Alocacao;
use App\Models\Manutencao;
use App\Models\Ocorrencia;
use App\Models\Usuario;
use App\Models\Veiculo;
use Illuminate\View\View;

/**
 * Painel inicial. Cards de frota e pessoas; alocações, checagens e
 * manutenções entram nas fases seguintes.
 */
class PainelController extends Controller
{
    public function index(): View
    {
        $usuario = auth()->user();
        $limite = now()->addDays((int) config('frota.alertas.dias_antecedencia_vencimento', 30));

        $porSituacao = Veiculo::ativos()->selectRaw('situacao, count(*) as total')->groupBy('situacao')->pluck('total', 'situacao');

        $frota = [
            'total' => (int) $porSituacao->sum(),
            'disponiveis' => (int) ($porSituacao[SituacaoVeiculo::Disponivel->value] ?? 0),
            'em_uso' => (int) (($porSituacao[SituacaoVeiculo::EmUso->value] ?? 0) + ($porSituacao[SituacaoVeiculo::Reservado->value] ?? 0)),
            'em_manutencao' => (int) ($porSituacao[SituacaoVeiculo::EmManutencao->value] ?? 0),
            'indisponiveis' => (int) ($porSituacao[SituacaoVeiculo::Indisponivel->value] ?? 0),
            'criticos' => Veiculo::ativos()->whereHas('condicoes', fn ($q) => $q->where('situacao', SituacaoCondicao::Critico->value))->count(),
            'vencimentos' => Veiculo::ativos()
                ->where(fn ($q) => $q->whereDate('licenciamento_validade', '<=', $limite)->orWhereDate('seguro_validade', '<=', $limite))
                ->orderBy('nome')->get(),
        ];

        $pessoas = [
            'usuarios_ativos' => Usuario::visiveisPara($usuario)->where('ativo', true)->count(),
            'motoristas' => Usuario::visiveisPara($usuario)->where('ativo', true)->where('pode_dirigir', true)->count(),
            'cnh_vencendo' => Usuario::visiveisPara($usuario)->where('ativo', true)
                ->whereNotNull('cnh_validade')->whereDate('cnh_validade', '<=', $limite)->orderBy('cnh_validade')->get(),
        ];

        $operacao = [
            'hoje' => Alocacao::with(['veiculo:id,nome', 'motorista:id,nome'])->visiveisPara($usuario)->abertas()
                ->whereDate('saida_prevista', '<=', today())->whereDate('retorno_previsto', '>=', today())
                ->orderBy('saida_prevista')->get(),
            'aguardando' => $usuario->can('alocacoes.aprovar')
                ? Alocacao::visiveisPara($usuario)->where('situacao', SituacaoAlocacao::Solicitada->value)->count() : 0,
            'atrasadas' => Alocacao::visiveisPara($usuario)->where('situacao', SituacaoAlocacao::EmUso->value)->where('retorno_previsto', '<', now())->count(),
            'ocorrencias_abertas' => Ocorrencia::visiveisPara($usuario)->abertas()->count(),
            'minha_checagem' => Alocacao::with('veiculo:id,nome')->where('motorista_id', $usuario->id)
                ->whereIn('situacao', [SituacaoAlocacao::Aprovada->value, SituacaoAlocacao::EmUso->value])->orderBy('saida_prevista')->first(),
        ];

        $manutencao = $usuario->can('manutencoes.ver') ? [
            'em_espera' => Manutencao::where('situacao', SituacaoManutencao::EmEspera->value)->count(),
            'em_prestacao' => Manutencao::where('situacao', SituacaoManutencao::EmPrestacao->value)->count(),
            'atrasadas' => Manutencao::abertas()->whereDate('prazo', '<', today())->count(),
            'custo_mes' => (float) Manutencao::where('situacao', SituacaoManutencao::Prestada->value)
                ->whereBetween('concluida_em', [now()->startOfMonth(), now()->endOfMonth()])->sum('preco_final'),
        ] : null;

        return view('painel.index', compact('frota', 'pessoas', 'operacao', 'manutencao'));
    }
}
