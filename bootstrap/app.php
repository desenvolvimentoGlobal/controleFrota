<?php

declare(strict_types=1);

use App\Http\Middleware\AutenticarIntegracao;
use App\Http\Middleware\ExigirTrocaDeSenha;
use App\Http\Middleware\GarantirUsuarioAtivo;
use App\Http\Middleware\VerificarPerfil;
use App\Support\RespostaApi;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        // API de integração entre sistemas (somente leitura) — docs/API.md.
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'perfil' => VerificarPerfil::class,
            'trocar-senha' => ExigirTrocaDeSenha::class,
            'ativo' => GarantirUsuarioAtivo::class,
            // Autenticação de SISTEMA (Bearer em tokens_integracao) — não confundir com perfil.
            'integracao.api' => AutenticarIntegracao::class,
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

        // Qualquer erro em api/* sai no envelope {sucesso:false, erro} dos
        // irmãos, sem stack trace nem mensagem interna (mesmo com APP_DEBUG).
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            if ($e instanceof ValidationException) {
                return RespostaApi::erro('validacao', 'Parâmetros inválidos.', 422, $e->errors());
            }

            if ($e instanceof HttpExceptionInterface) {
                $status = $e->getStatusCode();

                return match (true) {
                    $status === 404 => RespostaApi::erro('nao_encontrado', 'Recurso não encontrado.', 404),
                    $status === 405 => RespostaApi::erro('metodo_nao_permitido', 'Método não permitido. A API é somente leitura (GET).', 405),
                    $status === 429 => RespostaApi::erro('limite_excedido', 'Muitas requisições. Aguarde um minuto.', 429),
                    default => RespostaApi::erro('erro_http', 'Não foi possível atender a requisição.', $status),
                };
            }

            // O handler já reporta a exceção antes de renderizar: não repetir.
            return RespostaApi::erro('erro_interno', 'Erro interno no Controle de Frota. Tente novamente.', 500);
        });
    })->create();
