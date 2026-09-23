<?php

declare(strict_types=1);

namespace App\Http\Controllers\Manutencao;

use App\Enums\CondicaoVeiculo;
use App\Enums\SituacaoCondicao;
use App\Enums\SituacaoManutencao;
use App\Enums\TipoManutencao;
use App\Http\Controllers\Concerns\FiltrosPersistentes;
use App\Http\Controllers\Controller;
use App\Http\Requests\Manutencao\SalvarManutencaoRequest;
use App\Models\Fornecedor;
use App\Models\Manutencao;
use App\Models\ManutencaoAnexo;
use App\Models\Ocorrencia;
use App\Models\Usuario;
use App\Models\Veiculo;
use App\Services\ManutencaoService;
use App\Support\Numero;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Manutenções. Consulta: admin, gestor, financeiro (`manutencoes.ver`).
 * Abrir, editar e mudar situação: admin e gestor (`manutencoes.gerenciar`).
 * Comentar e anexar (ex.: nota fiscal): também o financeiro (`manutencoes.anotar`).
 */
class ManutencaoController extends Controller
{
    use FiltrosPersistentes;

    public function __construct(private readonly ManutencaoService $servico) {}

    public function index(Request $request): View|RedirectResponse
    {
        if ($redirecionar = $this->filtrosPersistentes($request, ['situacao', 'tipo', 'veiculo_id', 'fornecedor_id', 'de', 'ate', 'busca'])) {
            return $redirecionar;
        }

        $busca = trim((string) $request->input('busca'));

        $filtradas = Manutencao::query()
            ->when($request->input('situacao'), fn ($q, $s) => $s === 'abertas' ? $q->abertas() : $q->where('situacao', $s))
            ->when($request->input('tipo'), fn ($q, $t) => $q->where('tipo', $t))
            ->when($request->integer('veiculo_id'), fn ($q, $id) => $q->where('veiculo_id', $id))
            ->when($request->integer('fornecedor_id'), fn ($q, $id) => $q->where('fornecedor_id', $id))
            ->when($request->input('de'), fn ($q, $de) => $q->whereDate('created_at', '>=', $de))
            ->when($request->input('ate'), fn ($q, $ate) => $q->whereDate('created_at', '<=', $ate))
            ->when($busca !== '', fn ($q) => $q->where(fn ($s) => $s->where('nome', 'like', "%{$busca}%")->orWhere('descricao_problema', 'like', "%{$busca}%")));

        // Cancelada não custa nada: fica fora da soma (mas conta na quantidade).
        $totais = (clone $filtradas)->selectRaw(
            "count(*) as quantidade, sum(case when situacao = 'cancelada' then 0 else coalesce(preco_final, preco_previsto, 0) end) as custo"
        )->first();

        $manutencoes = $filtradas->with(['veiculo:id,nome,placa', 'fornecedor:id,razao_social,nome_fantasia'])
            ->orderByRaw("case situacao when 'em_prestacao' then 0 when 'em_espera' then 1 else 2 end")
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('manutencoes.index', [
            'manutencoes' => $manutencoes,
            'totais' => $totais,
            'busca' => $busca,
            'situacoes' => ['abertas' => 'Abertas (espera + prestação)'] + SituacaoManutencao::paraSelect(),
            'tipos' => TipoManutencao::paraSelect(),
            'veiculos' => Veiculo::orderBy('nome')->get(['id', 'nome', 'placa']),
            'fornecedores' => Fornecedor::orderBy('razao_social')->get(['id', 'razao_social', 'nome_fantasia']),
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('manutencoes.gerenciar');

        $ocorrencia = $request->integer('ocorrencia_id') ? Ocorrencia::with('item')->find($request->integer('ocorrencia_id')) : null;
        $veiculoId = $ocorrencia?->veiculo_id ?? ($request->integer('veiculo_id') ?: null);
        $sistemas = array_filter((array) $request->input('sistemas', []));

        // Veio de "abrir manutenção" num veículo: sugere os sistemas críticos/atenção.
        if ($veiculoId && $sistemas === [] && $request->boolean('sugerir')) {
            $sistemas = Veiculo::find($veiculoId)?->condicoes()->where('situacao', '!=', SituacaoCondicao::Ok->value)->pluck('sistema')->all() ?? [];
        }

        $prefill = [
            'veiculo_id' => $veiculoId,
            'ocorrencia_id' => $ocorrencia?->id,
            'tipo' => $request->input('tipo', $ocorrencia || $sistemas !== [] ? TipoManutencao::Imediata->value : TipoManutencao::Planejada->value),
            'nome' => $ocorrencia ? "Reparo: {$ocorrencia->item->rotulo}" : null,
            'descricao_problema' => $ocorrencia?->descricao,
            'sistemas' => $sistemas,
        ];

        return view('manutencoes.create', ['prefill' => $prefill, 'ocorrencia' => $ocorrencia] + $this->opcoes());
    }

    public function store(SalvarManutencaoRequest $request): RedirectResponse
    {
        try {
            $manutencao = $this->servico->abrir($request->dadosParaGravar(), $request->user());
        } catch (\DomainException $e) {
            return back()->withInput()->with('erro', $e->getMessage());
        }

        return redirect()->route('manutencoes.show', $manutencao)->with('sucesso', 'Manutenção aberta.');
    }

    public function show(Manutencao $manutencao): View
    {
        Gate::authorize('manutencoes.ver');

        $manutencao->load([
            'veiculo', 'fornecedor', 'abertaPor:id,nome', 'responsavel:id,nome', 'plano',
            'ocorrencia.item', 'movimentacoes.usuario:id,nome', 'anexos.enviadoPor:id,nome',
        ]);

        return view('manutencoes.show', [
            'manutencao' => $manutencao,
            'estados' => CondicaoVeiculo::paraSelect(),
        ]);
    }

    public function edit(Manutencao $manutencao): View|RedirectResponse
    {
        Gate::authorize('manutencoes.gerenciar');

        if (! $manutencao->situacao->aberta()) {
            return redirect()->route('manutencoes.show', $manutencao)->with('erro', 'Manutenção encerrada não pode ser editada.');
        }

        return view('manutencoes.edit', ['manutencao' => $manutencao, 'ocorrencia' => null] + $this->opcoes());
    }

    public function update(SalvarManutencaoRequest $request, Manutencao $manutencao): RedirectResponse
    {
        try {
            $this->servico->atualizar($manutencao, $request->dadosParaGravar(), $request->user());
        } catch (\DomainException $e) {
            return back()->withInput()->with('erro', $e->getMessage());
        }

        return redirect()->route('manutencoes.show', $manutencao)->with('sucesso', 'Manutenção atualizada.');
    }

    public function iniciar(Manutencao $manutencao, Request $request): RedirectResponse
    {
        Gate::authorize('manutencoes.gerenciar');

        try {
            $this->servico->iniciarPrestacao($manutencao, $request->user());
        } catch (\DomainException $e) {
            return back()->with('erro', $e->getMessage());
        }

        return back()->with('sucesso', 'Prestação iniciada. O veículo está em manutenção.');
    }

    public function concluir(Manutencao $manutencao, Request $request): RedirectResponse
    {
        Gate::authorize('manutencoes.gerenciar');

        $request->merge(['preco_final' => Numero::dePtBr($request->input('preco_final'))]);
        $dados = $request->validate([
            'preco_final' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'km_conclusao' => ['nullable', 'integer', 'min:0', 'max:9999999'],
            'estado_atual' => ['nullable', 'in:'.implode(',', CondicaoVeiculo::valores())],
            'observacao' => ['nullable', 'string', 'max:500'],
        ], [], ['preco_final' => 'preço final', 'km_conclusao' => 'quilometragem', 'estado_atual' => 'estado do veículo', 'observacao' => 'observação']);

        try {
            $this->servico->concluir($manutencao, $dados, $request->user());
        } catch (\DomainException $e) {
            return back()->with('erro', $e->getMessage());
        }

        return back()->with('sucesso', 'Manutenção concluída.');
    }

    public function cancelar(Manutencao $manutencao, Request $request): RedirectResponse
    {
        Gate::authorize('manutencoes.gerenciar');
        $dados = $request->validate(['motivo' => ['required', 'string', 'max:500']], [], ['motivo' => 'motivo']);

        try {
            $this->servico->cancelar($manutencao, $request->user(), $dados['motivo']);
        } catch (\DomainException $e) {
            return back()->with('erro', $e->getMessage());
        }

        return back()->with('sucesso', 'Manutenção cancelada.');
    }

    public function comentar(Manutencao $manutencao, Request $request): RedirectResponse
    {
        Gate::authorize('manutencoes.anotar');
        $dados = $request->validate(['comentario' => ['required', 'string', 'max:1000']], [], ['comentario' => 'comentário']);

        $this->servico->comentar($manutencao, $request->user(), $dados['comentario']);

        return back()->with('sucesso', 'Comentário registrado.');
    }

    public function anexar(Manutencao $manutencao, Request $request): RedirectResponse
    {
        Gate::authorize('manutencoes.anotar');
        $dados = $request->validate([
            'arquivo' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp,xlsx,xls,docx,doc', 'max:'.(int) config('frota.manutencao.anexo_max_kb', 10240)],
            'titulo' => ['nullable', 'string', 'max:120'],
        ], [], ['arquivo' => 'arquivo', 'titulo' => 'título']);

        $this->servico->anexar($manutencao, $request->file('arquivo'), $dados['titulo'] ?? null, $request->user());

        return back()->with('sucesso', 'Anexo enviado.');
    }

    public function anexo(ManutencaoAnexo $anexo): StreamedResponse
    {
        Gate::authorize('manutencoes.ver');
        abort_unless(Storage::disk(ManutencaoService::DISCO)->exists($anexo->caminho), 404);

        return Storage::disk(ManutencaoService::DISCO)->response($anexo->caminho, $anexo->nome_original);
    }

    public function removerAnexo(ManutencaoAnexo $anexo, Request $request): RedirectResponse
    {
        Gate::authorize('manutencoes.gerenciar');
        $this->servico->removerAnexo($anexo, $request->user());

        return back()->with('sucesso', 'Anexo removido.');
    }

    /** @return array<string, mixed> */
    private function opcoes(): array
    {
        return [
            'tipos' => TipoManutencao::paraSelect(),
            'veiculos' => Veiculo::ativos()->orderBy('nome')->get(['id', 'nome', 'placa', 'situacao']),
            'fornecedores' => Fornecedor::ativos()->orderBy('razao_social')->get(['id', 'razao_social', 'nome_fantasia']),
            'responsaveis' => Usuario::where('ativo', true)->whereHas('perfil', fn ($q) => $q->whereIn('codigo', ['admin', 'gestor']))->orderBy('nome')->get(['id', 'nome']),
            'sistemasMecanicos' => config('frota.sistemas_mecanicos'),
        ];
    }
}
