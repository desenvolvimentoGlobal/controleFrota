<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Usuario;
use Illuminate\View\View;

/**
 * Painel inicial. Na fase 0 mostra só os números de usuários; os cards de
 * frota, alocações, checagens e manutenções entram nas fases seguintes.
 */
class PainelController extends Controller
{
    public function index(): View
    {
        $usuario = auth()->user();

        $indicadores = [
            'usuarios_ativos' => Usuario::visiveisPara($usuario)->where('ativo', true)->count(),
            'motoristas' => Usuario::visiveisPara($usuario)->where('ativo', true)->where('pode_dirigir', true)->count(),
            'cnh_vencendo' => Usuario::visiveisPara($usuario)
                ->where('ativo', true)
                ->whereNotNull('cnh_validade')
                ->whereDate('cnh_validade', '<=', now()->addDays(30))
                ->count(),
        ];

        return view('painel.index', compact('indicadores'));
    }
}
