<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operacao;

use App\Enums\CondicaoVeiculo;
use App\Enums\SituacaoItemChecagem;
use App\Http\Controllers\Controller;
use App\Models\Alocacao;
use App\Models\Checagem;
use App\Models\ChecagemFoto;
use App\Models\ChecagemItem;
use App\Models\Ocorrencia;
use App\Models\Usuario;
use App\Models\Veiculo;
use App\Services\ChecagemService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChecagemController extends Controller
{
    public function __construct(private readonly ChecagemService $servico) {}

    /** Abre (ou cria) o rascunho da checagem devida e vai para a tela mobile. */
    public function iniciar(Alocacao $alocacao, Request $request): RedirectResponse
    {
        $this->authorize('checar', $alocacao);

        try {
            $checagem = $this->servico->obterOuIniciar($alocacao, $request->user());
        } catch (\DomainException $e) {
            return back()->with('erro', $e->getMessage());
        }

        return redirect()->route('checagens.editar', $checagem);
    }

    /** Tela mobile: um card por item com a foto anterior ao lado. */
    public function editar(Checagem $checagem, Request $request): View|RedirectResponse
    {
        abort_unless($checagem->motorista_id === $request->user()->id, 403);

        if ($checagem->concluida()) {
            return redirect()->route('checagens.show', $checagem);
        }

        $checagem->load(['alocacao.veiculo', 'itens.fotoAtual', 'anterior.itens.fotoAtual', 'anterior.motorista:id,nome']);
        $anteriores = $checagem->anterior?->itens->keyBy('item') ?? collect();

        $kmMinimo = $checagem->tipo->value === 'retorno'
            ? ($checagem->alocacao->km_saida ?? $checagem->alocacao->veiculo->km_atual)
            : $checagem->alocacao->veiculo->km_atual;

        return view('checagens.editar', [
            'checagem' => $checagem,
            'anteriores' => $anteriores,
            'kmMinimo' => $kmMinimo,
            'niveis' => Checagem::NIVEIS_COMBUSTIVEL,
            'estados' => CondicaoVeiculo::paraSelect(),
            'fotoMaxKb' => (int) config('frota.checagem.foto_max_kb', 4096),
        ]);
    }

    /** AJAX: foto + resposta de um item. */
    public function item(Checagem $checagem, ChecagemItem $item, Request $request): JsonResponse
    {
        abort_unless($item->checagem_id === $checagem->id, 404);

        $dados = $request->validate([
            // Opcional quando o item já tem foto: trocar só a resposta.
            'foto' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.(int) config('frota.checagem.foto_max_kb', 4096)],
            'situacao' => ['required', 'in:conforme,anomalia'],
            'observacao' => ['nullable', 'string', 'max:500'],
        ], [], ['foto' => 'foto', 'situacao' => 'resposta', 'observacao' => 'observação']);

        try {
            $item = $this->servico->registrarItem(
                $checagem, $item, $request->file('foto'),
                SituacaoItemChecagem::from($dados['situacao']), $dados['observacao'] ?? null, $request->user(),
            );
        } catch (\DomainException $e) {
            return response()->json(['ok' => false, 'erro' => $e->getMessage()], 422);
        }

        $checagem->load('itens.fotos');

        return response()->json([
            'ok' => true,
            'foto_url' => route('checagens.foto', $item->fotoAtual),
            'situacao' => $item->situacao->value,
            'pendentes' => $checagem->itensPendentes(),
        ]);
    }

    public function concluir(Checagem $checagem, Request $request): RedirectResponse
    {
        $dados = $request->validate([
            'km_informado' => ['required', 'integer', 'min:0', 'max:9999999'],
            'nivel_combustivel' => ['required', 'in:'.implode(',', array_keys(Checagem::NIVEIS_COMBUSTIVEL))],
            'estado_geral' => ['required', 'in:'.implode(',', CondicaoVeiculo::valores())],
            'observacao_motorista' => ['nullable', 'string', 'max:1000'],
        ], [], ['km_informado' => 'quilometragem', 'nivel_combustivel' => 'nível de combustível', 'estado_geral' => 'estado geral', 'observacao_motorista' => 'observação']);

        try {
            $checagem = $this->servico->concluir($checagem, $dados, $request->user());
        } catch (\DomainException $e) {
            return back()->withInput()->with('erro', $e->getMessage());
        }

        $mensagem = $checagem->tipo->value === 'saida'
            ? 'Checagem de saída concluída. Boa viagem!'
            : 'Checagem de retorno concluída. Veículo devolvido.';

        $anomalias = $checagem->load('itens')->itensComAnomalia();

        return redirect()->route('alocacoes.show', $checagem->alocacao_id)
            ->with('sucesso', $mensagem)
            ->with('aviso', $anomalias > 0 ? "{$anomalias} anomalia(s) registrada(s) como ocorrência." : null);
    }

    /** Checagem concluída, com comparação lado a lado. */
    public function show(Checagem $checagem, Request $request): View
    {
        $this->authorize('view', $checagem->alocacao);

        $checagem->load(['alocacao.veiculo', 'motorista', 'itens.fotoAtual', 'itens.ocorrencia', 'anterior.itens.fotoAtual', 'anterior.motorista:id,nome', 'anterior.alocacao']);
        $anteriores = $checagem->anterior?->itens->keyBy('item') ?? collect();

        return view('checagens.show', compact('checagem', 'anteriores'));
    }

    /** Histórico de checagens de um veículo. */
    public function historico(Veiculo $veiculo): View
    {
        $checagens = Checagem::with(['motorista:id,nome', 'alocacao:id,objetivo'])
            ->where('veiculo_id', $veiculo->id)
            ->concluidas()
            ->withCount(['itens as anomalias_count' => fn ($q) => $q->where('situacao', SituacaoItemChecagem::Anomalia->value)])
            ->orderByDesc('concluida_em')
            ->paginate(20);

        return view('checagens.historico', compact('veiculo', 'checagens'));
    }

    /**
     * Serve a foto do disco privado. Os ids são sequenciais, então a rota
     * confere quem pode ver: admin/gestor (cuidam da frota), quem vê a
     * alocação, o motorista que usa a foto como comparação e os envolvidos
     * numa ocorrência que a cita.
     */
    public function foto(ChecagemFoto $foto, Request $request): StreamedResponse
    {
        abort_unless($this->podeVerFoto($foto, $request->user()), 403);
        abort_if($foto->apagada_em !== null, 404, 'Foto removida pela política de retenção.');
        abort_unless(Storage::disk(ChecagemService::DISCO)->exists($foto->caminho), 404);

        return Storage::disk(ChecagemService::DISCO)->response($foto->caminho, $foto->nome_original, [
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }

    private function podeVerFoto(ChecagemFoto $foto, Usuario $usuario): bool
    {
        if ($usuario->temAlgumPerfil('admin', 'gestor')) {
            return true;
        }

        $item = $foto->item()->with('checagem.alocacao')->firstOrFail();
        $checagem = $item->checagem;

        if ($usuario->can('view', $checagem->alocacao)) {
            return true;
        }

        // Foto de referência da checagem que este usuário está fazendo/fez.
        $usadaComoComparacao = Checagem::where('checagem_anterior_id', $checagem->id)
            ->where('motorista_id', $usuario->id)->exists();

        if ($usadaComoComparacao) {
            return true;
        }

        $ocorrencias = Ocorrencia::where('checagem_item_id', $item->id)->orWhere('checagem_item_anterior_id', $item->id)->get();

        return $ocorrencias->contains(fn (Ocorrencia $o) => $usuario->can('view', $o));
    }
}
