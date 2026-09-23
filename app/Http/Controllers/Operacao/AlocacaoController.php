<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operacao;

use App\Enums\SituacaoAlocacao;
use App\Http\Controllers\Concerns\FiltrosPersistentes;
use App\Http\Controllers\Controller;
use App\Models\Alocacao;
use App\Models\Usuario;
use App\Models\Veiculo;
use App\Services\AlocacaoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AlocacaoController extends Controller
{
    use FiltrosPersistentes;

    public function __construct(private readonly AlocacaoService $servico) {}

    public function index(Request $request): View|RedirectResponse
    {
        if ($redirecionar = $this->filtrosPersistentes($request, ['situacao', 'veiculo_id', 'motorista_id', 'de', 'ate'])) {
            return $redirecionar;
        }

        $usuario = $request->user();

        $alocacoes = Alocacao::with(['veiculo:id,nome,placa', 'motorista:id,nome', 'solicitante:id,nome'])
            ->visiveisPara($usuario)
            ->when($request->input('situacao'), fn ($q, $s) => $q->where('situacao', $s))
            ->when($request->integer('veiculo_id'), fn ($q, $id) => $q->where('veiculo_id', $id))
            ->when($request->integer('motorista_id'), fn ($q, $id) => $q->where('motorista_id', $id))
            ->when($request->input('de'), fn ($q, $de) => $q->whereDate('saida_prevista', '>=', $de))
            ->when($request->input('ate'), fn ($q, $ate) => $q->whereDate('saida_prevista', '<=', $ate))
            ->orderByDesc('saida_prevista')
            ->paginate(20)
            ->withQueryString();

        $pendentes = $usuario->can('alocacoes.aprovar')
            ? Alocacao::visiveisPara($usuario)->where('situacao', SituacaoAlocacao::Solicitada->value)->count()
            : 0;

        return view('alocacoes.index', [
            'alocacoes' => $alocacoes,
            'pendentes' => $pendentes,
            'situacoes' => SituacaoAlocacao::paraSelect(),
            'veiculos' => Veiculo::ativos()->orderBy('nome')->get(['id', 'nome', 'placa']),
            'motoristas' => Usuario::visiveisPara($usuario)->where('pode_dirigir', true)->orderBy('nome')->get(['id', 'nome']),
        ]);
    }

    /** Agenda: próximos 7 dias por veículo. */
    public function agenda(Request $request): View
    {
        try {
            $inicio = $request->date('inicio')?->startOfDay() ?? now()->startOfDay();
        } catch (\Throwable) {
            $inicio = now()->startOfDay(); // ?inicio= inválido na URL
        }
        $fim = $inicio->copy()->addDays(6)->endOfDay();

        $veiculos = Veiculo::ativos()->orderBy('nome')->get(['id', 'nome', 'placa', 'situacao']);
        // Em uso com retorno atrasado continua ocupando o carro até voltar.
        $alocacoes = Alocacao::with(['motorista:id,nome'])
            ->abertas()
            ->where('saida_prevista', '<=', $fim)
            ->where(fn ($q) => $q->where('retorno_previsto', '>=', $inicio)->orWhere('situacao', SituacaoAlocacao::EmUso->value))
            ->get()
            ->groupBy('veiculo_id');

        $dias = collect(range(0, 6))->map(fn (int $i) => $inicio->copy()->addDays($i));

        return view('alocacoes.agenda', compact('veiculos', 'alocacoes', 'dias', 'inicio'));
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Alocacao::class);
        $usuario = $request->user();

        return view('alocacoes.create', [
            'veiculos' => Veiculo::with('condicoes')->ativos()->orderBy('nome')->get(),
            'motoristas' => $usuario->can('alocacoes.aprovar')
                ? Usuario::visiveisPara($usuario)->where('ativo', true)->where('pode_dirigir', true)->orderBy('nome')->get()
                : collect([$usuario]),
            'veiculoId' => $request->integer('veiculo_id') ?: null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Alocacao::class);

        $dados = $request->validate([
            'veiculo_id' => ['required', 'exists:veiculos,id'],
            'motorista_id' => ['required', 'exists:usuarios,id'],
            'objetivo' => ['required', 'string', 'max:255'],
            'destino' => ['nullable', 'string', 'max:255'],
            'saida_prevista' => ['required', 'date'],
            'retorno_previsto' => ['required', 'date', 'after:saida_prevista'],
            'observacoes' => ['nullable', 'string', 'max:2000'],
        ], [], [
            'veiculo_id' => 'veículo', 'motorista_id' => 'motorista', 'objetivo' => 'objetivo', 'destino' => 'destino',
            'saida_prevista' => 'saída prevista', 'retorno_previsto' => 'retorno previsto', 'observacoes' => 'observações',
        ]);

        try {
            [$alocacao, $avisos] = $this->servico->solicitar($dados, $request->user());
        } catch (\DomainException $e) {
            return back()->withInput()->with('erro', $e->getMessage());
        }

        $mensagem = $alocacao->situacao === SituacaoAlocacao::Aprovada
            ? 'Alocação registrada e aprovada.'
            : 'Solicitação enviada para aprovação do gestor.';

        $resposta = redirect()->route('alocacoes.show', $alocacao)->with('sucesso', $mensagem);

        return $avisos !== [] ? $resposta->with('aviso', implode(' ', $avisos)) : $resposta;
    }

    public function show(Alocacao $alocacao): View
    {
        $this->authorize('view', $alocacao);

        $alocacao->load([
            'veiculo', 'motorista', 'solicitante', 'aprovador',
            'checagens.itens.fotoAtual', 'checagens.itens.ocorrencia', 'motorista.gestor',
            'ocorrenciasComoResponsavel.apontadaPor',
        ]);

        return view('alocacoes.show', compact('alocacao'));
    }

    public function aprovar(Alocacao $alocacao, Request $request): RedirectResponse
    {
        $this->authorize('aprovar', $alocacao);

        try {
            $this->servico->aprovar($alocacao, $request->user());
        } catch (\DomainException $e) {
            return back()->with('erro', $e->getMessage());
        }

        return back()->with('sucesso', 'Alocação aprovada.');
    }

    public function recusar(Alocacao $alocacao, Request $request): RedirectResponse
    {
        $this->authorize('aprovar', $alocacao);
        $dados = $request->validate(['motivo' => ['required', 'string', 'max:500']], [], ['motivo' => 'motivo']);

        try {
            $this->servico->recusar($alocacao, $request->user(), $dados['motivo']);
        } catch (\DomainException $e) {
            return back()->with('erro', $e->getMessage());
        }

        return back()->with('sucesso', 'Alocação recusada.');
    }

    public function encerrar(Alocacao $alocacao, Request $request): RedirectResponse
    {
        $this->authorize('encerrar', $alocacao);
        $dados = $request->validate([
            'km_retorno' => ['required', 'integer', 'min:0', 'max:9999999'],
            'motivo' => ['required', 'string', 'max:500'],
        ], [], ['km_retorno' => 'quilometragem', 'motivo' => 'motivo']);

        try {
            $this->servico->encerrarPeloAdmin($alocacao, $request->user(), (int) $dados['km_retorno'], $dados['motivo']);
        } catch (\DomainException $e) {
            return back()->with('erro', $e->getMessage());
        }

        return back()->with('sucesso', 'Alocação encerrada sem checagem de retorno.');
    }

    public function cancelar(Alocacao $alocacao, Request $request): RedirectResponse
    {
        $this->authorize('cancelar', $alocacao);
        $dados = $request->validate(['motivo' => ['required', 'string', 'max:500']], [], ['motivo' => 'motivo']);

        try {
            $this->servico->cancelar($alocacao, $request->user(), $dados['motivo']);
        } catch (\DomainException $e) {
            return back()->with('erro', $e->getMessage());
        }

        return back()->with('sucesso', 'Alocação cancelada.');
    }
}
