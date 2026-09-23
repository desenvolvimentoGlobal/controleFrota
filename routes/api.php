<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Integracao\VeiculoIntegracaoController;
use Illuminate\Support\Facades\Route;

/*
| API de integração entre sistemas (somente leitura), padrão dos irmãos:
| Bearer token de `tokens_integracao` (php artisan integracao:token criar
| <consumidor>), envelope {sucesso, dados, meta} / {sucesso:false, erro}.
| Contrato completo em docs/API.md.
*/
Route::prefix('integracao/v1')->middleware(['integracao.api', 'throttle:120,1'])->group(function (): void {
    Route::get('veiculos', [VeiculoIntegracaoController::class, 'index']);
    Route::get('veiculos/disponiveis', [VeiculoIntegracaoController::class, 'disponiveis']);
});

Route::prefix('integracao/v1')->middleware(['integracao.api:alocacoes', 'throttle:120,1'])->group(function (): void {
    Route::get('alocacoes', [VeiculoIntegracaoController::class, 'alocacoes']);
});
