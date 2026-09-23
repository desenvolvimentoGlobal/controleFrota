<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\FiltrosPersistentes;
use App\Http\Controllers\Controller;
use App\Http\Requests\Usuario\SalvarUsuarioRequest;
use App\Integracoes\GestaoPessoas;
use App\Models\Cargo;
use App\Models\Perfil;
use App\Models\Setor;
use App\Models\Usuario;
use App\Services\Integracao\SincronizarColaboradores;
use App\Services\NotificacaoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * Cadastro de usuários. Admin vê e edita todos; gestor vê a própria cadeia e
 * só cria usuários abaixo dele (o recorte fica na Policy).
 */
class UsuarioController extends Controller
{
    use FiltrosPersistentes;

    public function index(Request $request): View|RedirectResponse
    {
        if ($redirecionar = $this->filtrosPersistentes($request, ['busca', 'perfil_id', 'setor_id', 'ativo', 'pode_dirigir'])) {
            return $redirecionar;
        }

        $busca = trim((string) $request->input('busca'));
        $ativo = $request->input('ativo');
        $podeDirigir = $request->input('pode_dirigir');

        // Busca pelo CPF só quando o texto tem dígitos: "maria" viraria
        // `cpf like '%%'` e casaria com todo mundo que tem CPF.
        $digitosBusca = preg_replace('/\D/', '', $busca) ?? '';

        $usuarios = Usuario::with(['perfil', 'setor', 'cargo', 'gestor:id,nome'])
            ->visiveisPara($request->user())
            ->when($busca !== '', fn ($q) => $q->where(fn ($sub) => $sub
                ->where('nome', 'like', "%{$busca}%")
                ->orWhere('email', 'like', "%{$busca}%")
                ->orWhere('login', 'like', "%{$busca}%")
                ->when($digitosBusca !== '', fn ($c) => $c->orWhere('cpf', 'like', "%{$digitosBusca}%"))))
            ->when($request->integer('perfil_id'), fn ($q, $id) => $q->where('perfil_id', $id))
            ->when($request->integer('setor_id'), fn ($q, $id) => $q->where('setor_id', $id))
            ->when(in_array($ativo, ['0', '1'], true), fn ($q) => $q->where('ativo', $ativo === '1'))
            ->when(in_array($podeDirigir, ['0', '1'], true), fn ($q) => $q->where('pode_dirigir', $podeDirigir === '1'))
            ->orderBy('nome')
            ->paginate(20)
            ->withQueryString();

        return view('usuarios.index', [
            'usuarios' => $usuarios,
            'busca' => $busca,
            'ativo' => $ativo,
            'podeDirigir' => $podeDirigir,
            'perfis' => Perfil::orderBy('nome')->get(),
            'setores' => Setor::ativos()->orderBy('nome')->get(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Usuario::class);

        return view('usuarios.create', $this->opcoesFormulario());
    }

    public function store(SalvarUsuarioRequest $request, NotificacaoService $notificar): RedirectResponse
    {
        $this->authorize('create', Usuario::class);

        $dados = $this->dadosParaGravar($request);
        $dados['foto_path'] = $this->salvarFoto($request);

        $novo = Usuario::create($dados);

        $notificar->enviar(
            $notificar->comPerfis(['admin'], auth()->id()),
            'usuario_criado',
            'Novo usuário criado',
            auth()->user()->nome." criou o usuário {$novo->nome} ({$novo->login}).",
            route('usuarios.index', [], false),
        );

        return redirect()->route('usuarios.index')->with('sucesso', 'Usuário cadastrado com sucesso.');
    }

    public function show(Usuario $usuario): View
    {
        $this->authorize('view', $usuario);

        $usuario->load(['perfil', 'setor', 'cargo', 'gestor', 'subordinados' => fn ($q) => $q->orderBy('nome')]);

        return view('usuarios.show', compact('usuario'));
    }

    public function edit(Usuario $usuario): View
    {
        $this->authorize('update', $usuario);

        return view('usuarios.edit', ['usuario' => $usuario] + $this->opcoesFormulario($usuario));
    }

    public function update(SalvarUsuarioRequest $request, Usuario $usuario, NotificacaoService $notificar): RedirectResponse
    {
        $this->authorize('update', $usuario);

        $dados = $this->dadosParaGravar($request, $usuario);

        $senhaAlterada = ! blank($dados['senha'] ?? null);
        if (! $senhaAlterada) {
            unset($dados['senha']);
        }

        if ($usuario->ativo && ! $dados['ativo'] && ($impedimento = $this->impedimentoParaDesligar($usuario))) {
            return back()->withInput()->with('erro', $impedimento);
        }
        if ($usuario->id === auth()->id() && ! $dados['ativo']) {
            return back()->withInput()->with('erro', 'Você não pode inativar o próprio usuário.');
        }

        if ($request->boolean('remover_foto') || $request->hasFile('foto')) {
            // Valida e grava a nova ANTES de apagar a antiga: foto recusada
            // não pode deixar o usuário apontando para um arquivo apagado.
            $novaFoto = $this->salvarFoto($request);
            $this->apagarFoto($usuario);
            $dados['foto_path'] = $novaFoto;
        }

        $usuario->update($dados);

        if ($senhaAlterada) {
            $notificar->enviar(
                $notificar->comPerfis(['admin'], auth()->id()),
                'senha_redefinida',
                'Senha de usuário redefinida',
                auth()->user()->nome." redefiniu a senha de {$usuario->nome} ({$usuario->login}).",
                route('usuarios.index', [], false),
            );
        }

        return redirect()->route('usuarios.index')->with('sucesso', 'Usuário atualizado com sucesso.');
    }

    public function destroy(Usuario $usuario): RedirectResponse
    {
        $this->authorize('delete', $usuario);

        if ($usuario->subordinados()->exists()) {
            return back()->with('erro', 'Este usuário é gestor de outras pessoas. Transfira a equipe antes de excluir.');
        }
        if ($impedimento = $this->impedimentoParaDesligar($usuario)) {
            return back()->with('erro', $impedimento);
        }

        $usuario->delete();

        return redirect()->route('usuarios.index')->with('sucesso', 'Usuário removido com sucesso.');
    }

    public function toggleAtivo(Usuario $usuario): RedirectResponse
    {
        $this->authorize('update', $usuario);

        if ($usuario->id === auth()->id()) {
            return back()->with('erro', 'Você não pode inativar o próprio usuário.');
        }
        if ($usuario->ativo && ($impedimento = $this->impedimentoParaDesligar($usuario))) {
            return back()->with('erro', $impedimento);
        }

        $usuario->update(['ativo' => ! $usuario->ativo]);

        return back()->with('sucesso', 'Situação do usuário atualizada.');
    }

    // ─── Apoio ─────────────────────────────────────────────────────────────────

    /**
     * Quem está com um carro (ou com saída aprovada) não pode sumir do
     * sistema: ninguém mais conseguiria fazer a checagem de retorno.
     */
    private function impedimentoParaDesligar(Usuario $usuario): ?string
    {
        return $usuario->motivoParaNaoDesligar();
    }

    /** @return array<string, mixed> */
    private function opcoesFormulario(?Usuario $editando = null): array
    {
        $atual = auth()->user();

        // Gestor só pode pendurar gente abaixo dele mesmo; admin escolhe qualquer um.
        $gestores = Usuario::where('ativo', true)
            ->whereHas('perfil', fn ($q) => $q->whereIn('codigo', ['admin', 'gestor']))
            ->when(! $atual->ehAdmin(), fn ($q) => $q->whereIn('id', [...$atual->idsDaEquipe(), $atual->id]))
            ->when($editando, fn ($q) => $q->whereKeyNot($editando->id))
            ->orderBy('nome')
            ->get(['id', 'nome']);

        $perfis = Perfil::orderBy('nome')
            // Gestor dá só geral/gestor (mesma regra do SalvarUsuarioRequest),
            // mantendo o perfil que a pessoa editada já tem.
            ->when(! $atual->ehAdmin(), fn ($q) => $q->where(fn ($q2) => $q2->whereIn('codigo', ['geral', 'gestor'])
                ->when($editando?->perfil_id, fn ($q3, $id) => $q3->orWhere('id', $id))))
            ->get();

        return [
            'perfis' => $perfis,
            'setores' => Setor::ativos()->orderBy('nome')->get(),
            'cargos' => Cargo::ativos()->orderBy('nome')->get(),
            'gestores' => $gestores,
            'categoriasCnh' => SalvarUsuarioRequest::CATEGORIAS_CNH,
            'ufs' => SalvarUsuarioRequest::UFS,
            // null = integração desligada ou fora do ar (a tela avisa e segue).
            'rhConfigurado' => app(GestaoPessoas::class)->configurada(),
            'colaboradoresRh' => app(GestaoPessoas::class)->colaboradoresEmCache(),
        ];
    }

    /** Botão "Sincronizar com o RH" (admin): roda a mesma rotina das 06:00. */
    public function sincronizarRh(SincronizarColaboradores $servico): RedirectResponse
    {
        $this->authorize('administrar');

        if (! app(GestaoPessoas::class)->configurada()) {
            return back()->with('erro', 'Integração com o Gestão de Pessoas não configurada (GESTAO_PESSOAS_URL / GESTAO_PESSOAS_TOKEN).');
        }

        $r = $servico->executar();

        if (! $r['ok']) {
            return back()->with('erro', 'Gestão de Pessoas indisponível agora. Nada foi alterado.');
        }

        $mensagem = "{$r['vinculados']} usuário(s) vinculado(s): {$r['atualizados']} atualizado(s), {$r['inativados']} inativado(s).";
        if ($r['bloqueados'] > 0 || $r['sem_ficha'] > 0) {
            return back()->with('aviso', $mensagem." {$r['bloqueados']} desligado(s) no RH ainda com veículo; {$r['sem_ficha']} sem ficha encontrada.");
        }

        return back()->with('sucesso', $mensagem);
    }

    /** @return array<string, mixed> */
    private function dadosParaGravar(SalvarUsuarioRequest $request, ?Usuario $usuario = null): array
    {
        $dados = $request->validated();
        $dados['ativo'] = $usuario ? $request->boolean('ativo') : true;
        $dados['pode_dirigir'] = $request->boolean('pode_dirigir');
        $dados['deve_trocar_senha'] = $request->boolean('deve_trocar_senha');

        // Gestor que cria alguém sem informar gestor vira o gestor da pessoa.
        if (! auth()->user()->ehAdmin() && blank($dados['gestor_id'] ?? null)) {
            $dados['gestor_id'] = auth()->id();
        }

        return $dados;
    }

    private function salvarFoto(Request $request): ?string
    {
        if (! $request->hasFile('foto')) {
            return null;
        }

        $request->validate(['foto' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:2048']], [], ['foto' => 'foto']);

        return $request->file('foto')->store('usuarios', 'public');
    }

    private function apagarFoto(Usuario $usuario): void
    {
        if ($usuario->foto_path) {
            Storage::disk('public')->delete($usuario->foto_path);
        }
    }
}
