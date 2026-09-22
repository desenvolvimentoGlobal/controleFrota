<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\FiltrosPersistentes;
use App\Models\Notificacao;
use App\Services\AuditoriaService;
use App\Services\NotificacaoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Notificações internas (sino): lista pessoal, marcar lidas, abrir e aviso geral do admin. */
class NotificacaoController extends Controller
{
    use FiltrosPersistentes;

    public function index(Request $request): View|RedirectResponse
    {
        if ($redirecionar = $this->filtrosPersistentes($request, ['filtro'])) {
            return $redirecionar;
        }

        $filtro = $request->input('filtro', 'nao_lidas');

        $notificacoes = auth()->user()->notificacoes()
            ->when($filtro === 'nao_lidas', fn ($q) => $q->naoLidas())
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $totalNaoLidas = auth()->user()->notificacoes()->naoLidas()->count();

        return view('notificacoes.index', compact('notificacoes', 'filtro', 'totalNaoLidas'));
    }

    public function abrir(Notificacao $notificacao): RedirectResponse
    {
        abort_unless($notificacao->usuario_id === auth()->id(), 403);

        if ($notificacao->lida_em === null) {
            $notificacao->update(['lida_em' => now()]);
        }

        return $notificacao->url ? redirect($notificacao->url) : redirect()->route('notificacoes.index');
    }

    public function marcarTodasLidas(): RedirectResponse
    {
        auth()->user()->notificacoes()->naoLidas()->update(['lida_em' => now()]);

        return back()->with('sucesso', 'Todas as notificações foram marcadas como lidas.');
    }

    public function avisoGeral(Request $request, NotificacaoService $notificar, AuditoriaService $auditoria): RedirectResponse
    {
        abort_unless(auth()->user()->ehAdmin(), 403);

        $dados = $request->validate([
            'titulo' => ['required', 'string', 'max:150'],
            'mensagem' => ['required', 'string', 'max:1000'],
        ], [], ['titulo' => 'título', 'mensagem' => 'mensagem']);

        $enviados = $notificar->enviar(
            $notificar->todosAtivos(auth()->id()),
            'aviso_admin',
            $dados['titulo'],
            $dados['mensagem'],
        );

        $auditoria->registrar(
            acao: 'enviou_aviso',
            modulo: 'notificacoes',
            descricao: "Enviou aviso geral para {$enviados} usuário(s): {$dados['titulo']}",
        );

        return back()->with('sucesso', "Aviso enviado para {$enviados} usuário(s).");
    }
}
