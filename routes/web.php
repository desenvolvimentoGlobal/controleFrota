<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\LogAuditoriaController;
use App\Http\Controllers\Admin\UsuarioController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\SenhaController;
use App\Http\Controllers\Cadastros\CargoController;
use App\Http\Controllers\Cadastros\FornecedorController;
use App\Http\Controllers\Cadastros\SetorController;
use App\Http\Controllers\Frota\CondicaoVeiculoController;
use App\Http\Controllers\Frota\VeiculoController;
use App\Http\Controllers\Manutencao\ManutencaoController;
use App\Http\Controllers\Manutencao\PlanoManutencaoController;
use App\Http\Controllers\NotificacaoController;
use App\Http\Controllers\Operacao\AlocacaoController;
use App\Http\Controllers\Operacao\ChecagemController;
use App\Http\Controllers\Operacao\OcorrenciaController;
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

    // ── Frota: consulta para todos; escrita exige frota.gerenciar (checado no controller) ──
    Route::resource('veiculos', VeiculoController::class)->parameters(['veiculos' => 'veiculo']);
    Route::patch('veiculos/{veiculo}/situacao', [VeiculoController::class, 'situacao'])->name('veiculos.situacao');
    Route::patch('veiculos/{veiculo}/estado', [VeiculoController::class, 'estado'])->name('veiculos.estado');
    Route::put('veiculos/{veiculo}/condicoes', [CondicaoVeiculoController::class, 'update'])->name('veiculos.condicoes');

    // ── Operação: alocações, checagens e ocorrências (todos; recorte nas Policies) ──
    Route::get('alocacoes/agenda', [AlocacaoController::class, 'agenda'])->name('alocacoes.agenda');
    Route::resource('alocacoes', AlocacaoController::class)->parameters(['alocacoes' => 'alocacao'])->only(['index', 'create', 'store', 'show']);
    Route::patch('alocacoes/{alocacao}/aprovar', [AlocacaoController::class, 'aprovar'])->name('alocacoes.aprovar');
    Route::patch('alocacoes/{alocacao}/recusar', [AlocacaoController::class, 'recusar'])->name('alocacoes.recusar');
    Route::patch('alocacoes/{alocacao}/cancelar', [AlocacaoController::class, 'cancelar'])->name('alocacoes.cancelar');
    Route::post('alocacoes/{alocacao}/checagem', [ChecagemController::class, 'iniciar'])->name('alocacoes.checagem');

    Route::get('checagens/foto/{foto}', [ChecagemController::class, 'foto'])->name('checagens.foto');
    Route::get('checagens/veiculo/{veiculo}', [ChecagemController::class, 'historico'])->name('checagens.historico');
    Route::get('checagens/{checagem}', [ChecagemController::class, 'show'])->name('checagens.show');
    Route::get('checagens/{checagem}/editar', [ChecagemController::class, 'editar'])->name('checagens.editar');
    Route::post('checagens/{checagem}/itens/{item}', [ChecagemController::class, 'item'])->name('checagens.item');
    Route::post('checagens/{checagem}/concluir', [ChecagemController::class, 'concluir'])->name('checagens.concluir');

    Route::get('ocorrencias', [OcorrenciaController::class, 'index'])->name('ocorrencias.index');
    Route::get('ocorrencias/{ocorrencia}', [OcorrenciaController::class, 'show'])->name('ocorrencias.show');
    Route::patch('ocorrencias/{ocorrencia}/contestar', [OcorrenciaController::class, 'contestar'])->name('ocorrencias.contestar');
    Route::patch('ocorrencias/{ocorrencia}/revisar', [OcorrenciaController::class, 'revisar'])->name('ocorrencias.revisar');

    // ── Manutenção: consulta admin/gestor/financeiro; escrita checada no controller ──
    Route::middleware('can:manutencoes.ver')->group(function (): void {
        // Anexos ANTES do resource: senão manutencoes/anexos/{id} cai no show.
        Route::get('manutencoes/anexos/{anexo}', [ManutencaoController::class, 'anexo'])->name('manutencoes.anexo');
        Route::delete('manutencoes/anexos/{anexo}', [ManutencaoController::class, 'removerAnexo'])->name('manutencoes.anexo.remover');

        Route::resource('manutencoes', ManutencaoController::class)->parameters(['manutencoes' => 'manutencao'])->except(['destroy']);
        Route::patch('manutencoes/{manutencao}/iniciar', [ManutencaoController::class, 'iniciar'])->name('manutencoes.iniciar');
        Route::patch('manutencoes/{manutencao}/concluir', [ManutencaoController::class, 'concluir'])->name('manutencoes.concluir');
        Route::patch('manutencoes/{manutencao}/cancelar', [ManutencaoController::class, 'cancelar'])->name('manutencoes.cancelar');
        Route::post('manutencoes/{manutencao}/comentar', [ManutencaoController::class, 'comentar'])->name('manutencoes.comentar');
        Route::post('manutencoes/{manutencao}/anexos', [ManutencaoController::class, 'anexar'])->name('manutencoes.anexar');

        Route::post('veiculos/{veiculo}/planos', [PlanoManutencaoController::class, 'store'])->name('planos.store');
        Route::put('planos/{plano}', [PlanoManutencaoController::class, 'update'])->name('planos.update');
        Route::delete('planos/{plano}', [PlanoManutencaoController::class, 'destroy'])->name('planos.destroy');
    });

    // ── Fornecedores (admin, gestor, financeiro) ──────────────────────────────
    Route::middleware('can:fornecedores.gerenciar')->group(function (): void {
        Route::resource('fornecedores', FornecedorController::class)->parameters(['fornecedores' => 'fornecedor'])->except(['show']);
        Route::patch('fornecedores/{fornecedor}/toggle-ativo', [FornecedorController::class, 'toggleAtivo'])->name('fornecedores.toggle-ativo');
    });

    // ── Cadastros auxiliares (admin) ──────────────────────────────────────────
    Route::middleware('can:cadastros.gerenciar')->group(function (): void {
        foreach (['setores' => SetorController::class, 'cargos' => CargoController::class] as $rota => $controller) {
            Route::get($rota, [$controller, 'index'])->name("{$rota}.index");
            Route::post($rota, [$controller, 'store'])->name("{$rota}.store");
            Route::put("{$rota}/{id}", [$controller, 'update'])->name("{$rota}.update");
            Route::patch("{$rota}/{id}/toggle-ativo", [$controller, 'toggleAtivo'])->name("{$rota}.toggle-ativo");
            Route::delete("{$rota}/{id}", [$controller, 'destroy'])->name("{$rota}.destroy");
        }
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
