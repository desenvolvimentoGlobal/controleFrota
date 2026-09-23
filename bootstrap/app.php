<?php

declare(strict_types=1);

use App\Http\Middleware\ExigirTrocaDeSenha;
use App\Http\Middleware\GarantirUsuarioAtivo;
use App\Http\Middleware\VerificarPerfil;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'perfil' => VerificarPerfil::class,
            'trocar-senha' => ExigirTrocaDeSenha::class,
            'ativo' => GarantirUsuarioAtivo::class,
        ]);

        // Atrás do Traefik (produção Docker) o TLS termina no proxy. Seguro
        // porque o único caminho até o container web é a rede `edge` — o
        // container NÃO publica portas (premissa do template-projeto).
        $middleware->trustProxies(at: '*');

        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn (Request $request) => route($request->user()->rotaDashboard()));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(fn (Request $request) => $request->is('api/*'));
    })->create();
