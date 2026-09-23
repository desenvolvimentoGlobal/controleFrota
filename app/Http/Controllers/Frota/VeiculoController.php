<?php

declare(strict_types=1);

namespace App\Http\Controllers\Frota;

use App\Enums\CaracteristicasVeiculo;
use App\Enums\CondicaoVeiculo;
use App\Enums\SituacaoVeiculo;
use App\Http\Controllers\Concerns\FiltrosPersistentes;
use App\Http\Controllers\Controller;
use App\Http\Requests\Veiculo\SalvarVeiculoRequest;
use App\Models\Veiculo;
use App\Services\VeiculoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * Cadastro de veículos. Todos os perfis consultam; só `frota.gerenciar`
 * (admin, gestor) cria, edita e muda situação/estado à mão.
 */
class VeiculoController extends Controller
{
    use FiltrosPersistentes;

    public function __construct(private readonly VeiculoService $servico) {}

    public function index(Request $request): View|RedirectResponse
    {
        if ($redirecionar = $this->filtrosPersistentes($request, ['busca', 'situacao', 'estado', 'marca', 'baixados'])) {
            return $redirecionar;
        }

        $busca = trim((string) $request->input('busca'));

        $veiculos = Veiculo::with('condicoes')
            ->when(! $request->boolean('baixados'), fn ($q) => $q->ativos())
            ->when($busca !== '', fn ($q) => $q->where(fn ($sub) => $sub
                ->where('nome', 'like', "%{$busca}%")
                ->orWhere('placa', 'like', '%'.strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $busca) ?? '').'%')
                ->orWhere('modelo', 'like', "%{$busca}%")
                ->orWhere('marca', 'like', "%{$busca}%")))
            ->when($request->input('situacao'), fn ($q, $s) => $q->where('situacao', $s))
            ->when($request->input('estado'), fn ($q, $e) => $q->where('estado_atual', $e))
            ->when($request->input('marca'), fn ($q, $m) => $q->where('marca', $m))
            ->orderBy('nome')
            ->paginate(20)
            ->withQueryString();

        return view('veiculos.index', [
            'veiculos' => $veiculos,
            'busca' => $busca,
            'marcas' => Veiculo::ativos()->distinct()->orderBy('marca')->pluck('marca'),
            'situacoes' => SituacaoVeiculo::paraSelect(),
            'estados' => CondicaoVeiculo::paraSelect(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('frota.gerenciar');

        return view('veiculos.create', $this->opcoes());
    }

    public function store(SalvarVeiculoRequest $request): RedirectResponse
    {
        $dados = $request->dadosParaGravar();
        $dados['foto_path'] = $this->salvarFoto($request);

        $veiculo = $this->servico->criar($dados);

        return redirect()->route('veiculos.show', $veiculo)->with('sucesso', "Veículo {$veiculo->nome} cadastrado.");
    }

    public function show(Veiculo $veiculo): View
    {
        $veiculo->load([
            'condicoes.atualizadoPor:id,nome', 'historicoEstados.usuario:id,nome', 'planosManutencao',
            'manutencoes' => fn ($q) => $q->with('fornecedor:id,razao_social,nome_fantasia')->latest('id')->limit(10),
        ]);

        return view('veiculos.show', [
            'veiculo' => $veiculo,
            'situacoesManuais' => SituacaoVeiculo::manuais(),
            'estados' => CondicaoVeiculo::cases(),
        ] + $this->opcoes());
    }

    public function edit(Veiculo $veiculo): View
    {
        Gate::authorize('frota.gerenciar');

        return view('veiculos.edit', ['veiculo' => $veiculo] + $this->opcoes());
    }

    public function update(SalvarVeiculoRequest $request, Veiculo $veiculo): RedirectResponse
    {
        $dados = $request->dadosParaGravar();

        if ($request->boolean('remover_foto') || $request->hasFile('foto')) {
            if ($veiculo->foto_path) {
                Storage::disk('public')->delete($veiculo->foto_path);
            }
            $dados['foto_path'] = $this->salvarFoto($request);
        }

        $this->servico->atualizar($veiculo, $dados);

        return redirect()->route('veiculos.show', $veiculo)->with('sucesso', 'Veículo atualizado.');
    }

    public function destroy(Veiculo $veiculo): RedirectResponse
    {
        Gate::authorize('administrar');

        if ($veiculo->situacao !== SituacaoVeiculo::Baixado) {
            return back()->with('erro', 'Só é possível excluir um veículo já baixado. Prefira baixar: o histórico é preservado.');
        }

        $veiculo->delete();

        return redirect()->route('veiculos.index')->with('sucesso', 'Veículo excluído.');
    }

    /** Mudança manual de situação (disponível, indisponível, baixado). */
    public function situacao(Request $request, Veiculo $veiculo): RedirectResponse
    {
        Gate::authorize('frota.gerenciar');

        $dados = $request->validate([
            'situacao' => ['required', 'in:'.implode(',', array_map(fn ($s) => $s->value, SituacaoVeiculo::manuais()))],
            'observacao' => ['required', 'string', 'max:255'],
        ], [], ['situacao' => 'situação', 'observacao' => 'motivo']);

        try {
            $this->servico->mudarSituacao($veiculo, SituacaoVeiculo::from($dados['situacao']), 'manual', null, $dados['observacao']);
        } catch (\DomainException $e) {
            return back()->with('erro', $e->getMessage());
        }

        return back()->with('sucesso', 'Situação do veículo atualizada.');
    }

    /** Ajuste manual de estado físico e/ou km (gestor corrigindo o cadastro). */
    public function estado(Request $request, Veiculo $veiculo): RedirectResponse
    {
        Gate::authorize('frota.gerenciar');

        $dados = $request->validate([
            'estado_atual' => ['required', 'in:'.implode(',', CondicaoVeiculo::valores())],
            'km_atual' => ['required', 'integer', 'min:0', 'max:9999999'],
            'observacao' => ['required', 'string', 'max:255'],
        ], [], ['estado_atual' => 'estado físico', 'km_atual' => 'quilometragem', 'observacao' => 'motivo']);

        try {
            $this->servico->mudarEstadoFisico($veiculo, CondicaoVeiculo::from($dados['estado_atual']), 'manual', null, $dados['observacao']);
            $this->servico->atualizarKm($veiculo, (int) $dados['km_atual'], 'manual', null, $dados['observacao']);
        } catch (\DomainException $e) {
            return back()->with('erro', $e->getMessage());
        }

        return back()->with('sucesso', 'Estado do veículo atualizado.');
    }

    // ─── Apoio ─────────────────────────────────────────────────────────────────

    /** @return array<string, mixed> */
    private function opcoes(): array
    {
        return [
            'carrocerias' => CaracteristicasVeiculo::CARROCERIAS,
            'tiposCor' => CaracteristicasVeiculo::TIPOS_COR,
            'combustiveis' => CaracteristicasVeiculo::COMBUSTIVEIS,
            'cambios' => CaracteristicasVeiculo::CAMBIOS,
            'tracoes' => CaracteristicasVeiculo::TRACOES,
            'direcoes' => CaracteristicasVeiculo::DIRECOES,
            'arCondicionado' => CaracteristicasVeiculo::AR_CONDICIONADO,
            'bancos' => CaracteristicasVeiculo::BANCOS,
            'conforto' => CaracteristicasVeiculo::CONFORTO,
            'seguranca' => CaracteristicasVeiculo::SEGURANCA,
            'condicoes' => CondicaoVeiculo::paraSelect(),
        ];
    }

    private function salvarFoto(Request $request): ?string
    {
        return $request->hasFile('foto') ? $request->file('foto')->store('veiculos', 'public') : null;
    }
}
