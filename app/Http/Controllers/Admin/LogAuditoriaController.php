<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\FiltrosPersistentes;
use App\Http\Controllers\Controller;
use App\Models\LogAuditoria;
use App\Models\Usuario;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Consulta da trilha de auditoria (somente admin). Log é imutável: a tela só lê. */
class LogAuditoriaController extends Controller
{
    use FiltrosPersistentes;

    public function index(Request $request): View|RedirectResponse
    {
        if ($redirecionar = $this->filtrosPersistentes($request, ['de', 'ate', 'usuario_id', 'acao', 'modulo', 'busca'])) {
            return $redirecionar;
        }

        $de = $request->input('de');
        $ate = $request->input('ate');
        $busca = trim((string) $request->input('busca'));

        $logs = LogAuditoria::with('usuario:id,nome')
            ->when($request->integer('usuario_id'), fn ($q, $id) => $q->where('usuario_id', $id))
            ->when($request->input('acao'), fn ($q, $acao) => $q->where('acao', $acao))
            ->when($request->input('modulo'), fn ($q, $modulo) => $q->where('modulo', $modulo))
            ->when($de, fn ($q) => $q->whereDate('created_at', '>=', $de))
            ->when($ate, fn ($q) => $q->whereDate('created_at', '<=', $ate))
            ->when($busca !== '', fn ($q) => $q->where(fn ($sub) => $sub
                ->where('descricao', 'like', "%{$busca}%")
                ->orWhere('tabela', 'like', "%{$busca}%")
                ->orWhere('ip', 'like', "%{$busca}%")))
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();

        $acoes = LogAuditoria::distinct()->orderBy('acao')->pluck('acao');
        $modulos = LogAuditoria::distinct()->orderBy('modulo')->pluck('modulo')->filter()->values();
        $usuarios = Usuario::whereIn('id', LogAuditoria::distinct()->pluck('usuario_id')->filter())
            ->orderBy('nome')->get(['id', 'nome']);

        return view('logs.index', compact('logs', 'acoes', 'modulos', 'usuarios', 'de', 'ate', 'busca'));
    }
}
