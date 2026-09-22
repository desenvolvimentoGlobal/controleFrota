<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cadastros;

use App\Http\Controllers\Concerns\FiltrosPersistentes;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Base dos cadastros auxiliares de uma coluna (`nome` + `ativo`): setores e
 * cargos. Tela única: formulário à esquerda, listagem à direita.
 */
abstract class CadastroSimplesController extends Controller
{
    use FiltrosPersistentes;

    /** @var class-string<Model> */
    protected string $model;

    protected string $rota;      // ex.: setores

    protected string $singular;  // ex.: Setor

    protected string $artigo;    // o | a

    protected string $relacao = 'usuarios'; // relação que impede exclusão

    public function index(Request $request): View|RedirectResponse
    {
        if ($redirecionar = $this->filtrosPersistentes($request, ['busca', 'ativo'])) {
            return $redirecionar;
        }

        $busca = trim((string) $request->input('busca'));
        $ativo = $request->input('ativo');

        $registros = $this->model::query()
            ->withCount($this->relacao)
            ->when($busca !== '', fn ($q) => $q->where('nome', 'like', "%{$busca}%"))
            ->when(in_array($ativo, ['0', '1'], true), fn ($q) => $q->where('ativo', $ativo === '1'))
            ->orderBy('nome')
            ->paginate(20)
            ->withQueryString();

        $editando = $request->integer('editar') ? $this->model::find($request->integer('editar')) : null;

        return view('cadastros.simples', [
            'registros' => $registros,
            'busca' => $busca,
            'ativo' => $ativo,
            'editando' => $editando,
            'rota' => $this->rota,
            'singular' => $this->singular,
            'artigo' => $this->artigo,
            'titulo' => $this->titulo(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $dados = $request->validate($this->regras(), [], ['nome' => 'nome']);
        $this->model::create($dados + ['ativo' => true]);

        return redirect()->route("{$this->rota}.index")->with('sucesso', "{$this->singular} cadastrad{$this->artigo}.");
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $registro = $this->model::findOrFail($id);
        $dados = $request->validate($this->regras($registro), [], ['nome' => 'nome']);
        $registro->update($dados);

        return redirect()->route("{$this->rota}.index")->with('sucesso', "{$this->singular} atualizad{$this->artigo}.");
    }

    public function toggleAtivo(int $id): RedirectResponse
    {
        $registro = $this->model::findOrFail($id);
        $registro->update(['ativo' => ! $registro->ativo]);

        return back()->with('sucesso', 'Situação atualizada.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $registro = $this->model::withCount($this->relacao)->findOrFail($id);

        if ($registro->{"{$this->relacao}_count"} > 0) {
            return back()->with('erro', "{$this->singular} em uso não pode ser excluíd{$this->artigo}. Inative-{$this->artigo}.");
        }

        $registro->delete();

        return redirect()->route("{$this->rota}.index")->with('sucesso', "{$this->singular} excluíd{$this->artigo}.");
    }

    /** @return array<string, array<int, mixed>> */
    protected function regras(?Model $registro = null): array
    {
        return [
            'nome' => ['required', 'string', 'max:100', Rule::unique($this->model::query()->getModel()->getTable(), 'nome')->ignore($registro)],
        ];
    }

    abstract protected function titulo(): string;
}
