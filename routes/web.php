<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\LogAuditoriaController;
use App\Http\Controllers\Admin\UsuarioController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\SenhaController;
use App\Http\Controllers\NotificacaoController;
use App\Http\Controllers\PainelController;
use App\Http\Controllers\WebPushController;
use Illuminate\Support\Facades\Route;

// ─── Visitantes ───────────────────────────────────────────────────────────────
Route::middleware('guest')->group(function (): void {
    Route::get('/', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.post');
});

// ─── Autenticados ─────────────────────────────────────────────────────────────
Route::middleware(['auth', 'trocar-senha'])->group(function (): void {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/senha', [SenhaController::class, 'edit'])->name('senha.editar');
    Route::put('/senha', [SenhaController::class, 'update'])->name('senha.atualizar');

    Route::get('/painel', [PainelController::class, 'index'])->name('painel');

    // Notificações (todos os perfis)
    Route::get('notificacoes', [NotificacaoController::class, 'index'])->name('notificacoes.index');
    Route::patch('notificacoes/marcar-todas-lidas', [NotificacaoController::class, 'marcarTodasLidas'])->name('notificacoes.todas-lidas');
    Route::get('notificacoes/{notificacao}/abrir', [NotificacaoController::class, 'abrir'])->name('notificacoes.abrir');
    Route::post('notificacoes/aviso-geral', [NotificacaoController::class, 'avisoGeral'])
        ->middleware('perfil:admin')->name('notificacoes.aviso-geral');

    Route::prefix('webpush')->name('webpush.')->group(function (): void {
        Route::post('inscrever', [WebPushController::class, 'inscrever'])->name('inscrever');
        Route::post('desinscrever', [WebPushController::class, 'desinscrever'])->name('desinscrever');
        Route::post('testar', [WebPushController::class, 'testar'])->name('testar');
    });

    // ── Usuários (admin: todos; gestor: a própria cadeia — Policy) ────────────
    Route::middleware('perfil:admin,gestor')->group(function (): void {
        Route::resource('usuarios', UsuarioController::class)->parameters(['usuarios' => 'usuario']);
        Route::patch('usuarios/{usuario}/toggle-ativo', [UsuarioController::class, 'toggleAtivo'])->name('usuarios.toggle-ativo');
    });

    // ── Administração ─────────────────────────────────────────────────────────
    Route::middleware('perfil:admin')->group(function (): void {
        Route::get('logs', [LogAuditoriaController::class, 'index'])->name('logs.index');
    });
});
