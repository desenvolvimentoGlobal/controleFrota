<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cadastros;

use App\Http\Controllers\Concerns\FiltrosPersistentes;
use App\Http\Controllers\Controller;
use App\Http\Requests\Fornecedor\SalvarFornecedorRequest;
use App\Http\Requests\Usuario\SalvarUsuarioRequest;
use App\Models\Fornecedor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Fornecedores de manutenção (cadastro próprio, decisão 22/09/2026). */
class FornecedorController extends Controller
{
    use FiltrosPersistentes;

    public function index(Request $request): View|RedirectResponse
    {
        if ($redirecionar = $this->filtrosPersistentes($request, ['busca', 'ativo'])) {
            return $redirecionar;
        }

        $busca = trim((string) $request->input('busca'));
        $ativo = $request->input('ativo');

        $fornecedores = Fornecedor::query()
            ->when($busca !== '', fn ($q) => $q->where(fn ($sub) => $sub
                ->where('razao_social', 'like', "%{$busca}%")
                ->orWhere('nome_fantasia', 'like', "%{$busca}%")
                ->orWhere('cnpj', 'like', '%'.preg_replace('/\D/', '', $busca).'%')
                ->orWhere('cidade', 'like', "%{$busca}%")))
            ->when(in_array($ativo, ['0', '1'], true), fn ($q) => $q->where('ativo', $ativo === '1'))
            ->orderBy('razao_social')
            ->paginate(20)
            ->withQueryString();

        return view('fornecedores.index', compact('fornecedores', 'busca', 'ativo'));
    }

    public function create(): View
    {
        return view('fornecedores.create', ['ufs' => SalvarUsuarioRequest::UFS]);
    }

    public function store(SalvarFornecedorRequest $request): RedirectResponse
    {
        Fornecedor::create($request->validated() + ['ativo' => true]);

        return redirect()->route('fornecedores.index')->with('sucesso', 'Fornecedor cadastrado.');
    }

    public function edit(Fornecedor $fornecedor): View
    {
        return view('fornecedores.edit', ['fornecedor' => $fornecedor, 'ufs' => SalvarUsuarioRequest::UFS]);
    }

    public function update(SalvarFornecedorRequest $request, Fornecedor $fornecedor): RedirectResponse
    {
        $fornecedor->update($request->validated() + ['ativo' => $request->boolean('ativo')]);

        return redirect()->route('fornecedores.index')->with('sucesso', 'Fornecedor atualizado.');
    }

    public function toggleAtivo(Fornecedor $fornecedor): RedirectResponse
    {
        $fornecedor->update(['ativo' => ! $fornecedor->ativo]);

        return back()->with('sucesso', 'Situação do fornecedor atualizada.');
    }

    public function destroy(Fornecedor $fornecedor): RedirectResponse
    {
        // Fase 3: bloquear quando houver manutenções vinculadas.
        $fornecedor->delete();

        return redirect()->route('fornecedores.index')->with('sucesso', 'Fornecedor excluído.');
    }
}
