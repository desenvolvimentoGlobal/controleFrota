<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/** Troca da própria senha (voluntária ou exigida por `deve_trocar_senha`). */
class SenhaController extends Controller
{
    public function edit(): View
    {
        return view('auth.senha');
    }

    public function update(Request $request): RedirectResponse
    {
        $dados = $request->validate([
            'senha_atual' => ['required', 'current_password'],
            'senha' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ], [], [
            'senha_atual' => 'senha atual',
            'senha' => 'nova senha',
        ]);

        $request->user()->update([
            'senha' => $dados['senha'],
            'deve_trocar_senha' => false,
        ]);

        return redirect()->route('painel')->with('sucesso', 'Senha alterada com sucesso.');
    }
}
