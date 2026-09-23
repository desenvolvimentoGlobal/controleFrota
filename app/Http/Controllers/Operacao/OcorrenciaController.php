<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operacao;

use App\Enums\SituacaoAlocacao;
use App\Enums\SituacaoOcorrencia;
use App\Http\Controllers\Concerns\FiltrosPersistentes;
use App\Http\Controllers\Controller;
use App\Models\Alocacao;
use App\Models\Ocorrencia;
use App\Models\Veiculo;
use App\Services\OcorrenciaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OcorrenciaController extends Controller
{
    use FiltrosPersistentes;

    public function __construct(private readonly OcorrenciaService $servico) {}

    public function index(Request $request): View|RedirectResponse
    {
        if ($redirecionar = $this->filtrosPersistentes($request, ['situacao', 'veiculo_id'])) {
            return $redirecionar;
        }

        $ocorrencias = Ocorrencia::with(['veiculo:id,nome,placa', 'item.checagem:id,tipo,concluida_em', 'apontadaPor:id,nome', 'alocacaoResponsavel.motorista:id,nome'])
            ->visiveisPara($request->user())
            ->when($request->input('situacao'), fn ($q, $s) => $q->where('situacao', $s))
            ->when($request->integer('veiculo_id'), fn ($q, $id) => $q->where('veiculo_id', $id))
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('ocorrencias.index', [
            'ocorrencias' => $ocorrencias,
            'situacoes' => SituacaoOcorrencia::paraSelect(),
            'veiculos' => Veiculo::orderBy('nome')->get(['id', 'nome', 'placa']),
        ]);
    }

    public function show(Ocorrencia $ocorrencia, Request $request): View
    {
        $this->authorize('view', $ocorrencia);

        $ocorrencia->load([
            'veiculo', 'apontadaPor', 'revisadaPor',
            'item.fotoAtual', 'item.checagem.alocacao.motorista', 'item.checagem.motorista',
            'itemAnterior.fotoAtual', 'itemAnterior.checagem.motorista',
            'alocacaoResponsavel.motorista', 'manutencao',
        ]);

        // Candidatas à reatribuição: concluídas deste veículo cujo motorista o
        // revisor pode responsabilizar (admin: qualquer um; gestor: a equipe).
        $usuario = $request->user();
        $outrasAlocacoes = $usuario->can('revisar', $ocorrencia)
            ? Alocacao::with('motorista:id,nome')->where('veiculo_id', $ocorrencia->veiculo_id)
                ->where('situacao', SituacaoAlocacao::Concluida->value)
                ->whereKeyNot((int) $ocorrencia->alocacao_responsavel_id)
                ->when(! $usuario->ehAdmin(), fn ($q) => $q->whereIn('motorista_id', $usuario->idsDaEquipe()))
                ->orderByDesc('retorno_real')->limit(10)->get()
            : collect();

        return view('ocorrencias.show', compact('ocorrencia', 'outrasAlocacoes'));
    }

    public function contestar(Ocorrencia $ocorrencia, Request $request): RedirectResponse
    {
        $this->authorize('contestar', $ocorrencia);
        $dados = $request->validate(['contestacao' => ['required', 'string', 'max:1000']], [], ['contestacao' => 'contestação']);

        try {
            $this->servico->contestar($ocorrencia, $request->user(), $dados['contestacao']);
        } catch (\DomainException $e) {
            return back()->with('erro', $e->getMessage());
        }

        return back()->with('sucesso', 'Contestação registrada. O gestor foi avisado.');
    }

    public function revisar(Ocorrencia $ocorrencia, Request $request): RedirectResponse
    {
        $this->authorize('revisar', $ocorrencia);
        $dados = $request->validate([
            'decisao' => ['required', 'in:confirmada,descartada,reatribuida'],
            'observacao_revisao' => ['nullable', 'string', 'max:1000'],
            'alocacao_responsavel_id' => ['nullable', 'required_if:decisao,reatribuida', 'integer'],
        ], ['alocacao_responsavel_id.required_if' => 'Escolha a alocação que passa a ser responsável.'],
            ['decisao' => 'decisão', 'observacao_revisao' => 'observação', 'alocacao_responsavel_id' => 'responsável']);

        try {
            if ($dados['decisao'] === 'reatribuida') {
                $this->servico->reatribuir($ocorrencia, $request->user(), (int) $dados['alocacao_responsavel_id'], $dados['observacao_revisao'] ?? null);

                return back()->with('sucesso', 'Responsável alterado. A ocorrência continua aberta e o novo responsável foi avisado.');
            }

            $this->servico->revisar($ocorrencia, $request->user(), SituacaoOcorrencia::from($dados['decisao']), $dados['observacao_revisao'] ?? null);
        } catch (\DomainException $e) {
            return back()->with('erro', $e->getMessage());
        }

        return back()->with('sucesso', 'Ocorrência revisada.');
    }
}
