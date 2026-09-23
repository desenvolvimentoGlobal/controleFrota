<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\InscricaoPush;
use App\Services\WebPushService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Inscrição de dispositivos Web Push + teste. */
class WebPushController extends Controller
{
    /**
     * Serviços de push dos navegadores. O servidor faz POST no endpoint
     * informado: sem esta lista, qualquer usuário apontaria para a rede
     * interna (SSRF) — banco, metadados da nuvem, outros containers.
     */
    private const HOSTS_DE_PUSH = [
        'fcm.googleapis.com',                   // Chrome, Edge (Android), Opera, Samsung
        'updates.push.services.mozilla.com',    // Firefox
        'web.push.apple.com',                   // Safari / iOS
        '.notify.windows.com',                  // Edge no Windows (subdomínios)
    ];

    public function __construct(private readonly WebPushService $webPushService) {}

    public function inscrever(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'endpoint' => ['required', 'string', 'max:500', 'url:https', function (string $atributo, mixed $valor, \Closure $falhar): void {
                if (! self::hostDePush((string) parse_url((string) $valor, PHP_URL_HOST))) {
                    $falhar('Endereço de notificação não reconhecido.');
                }
            }],
            'keys.p256dh' => ['required', 'string'],
            'keys.auth' => ['required', 'string'],
        ]);

        InscricaoPush::updateOrCreate(
            ['endpoint' => $dados['endpoint']],
            [
                'usuario_id' => auth()->id(),
                'chave_p256dh' => $dados['keys']['p256dh'],
                'chave_auth' => $dados['keys']['auth'],
                'user_agent' => substr((string) $request->userAgent(), 0, 255),
            ],
        );

        return response()->json(['ok' => true]);
    }

    public function desinscrever(Request $request): JsonResponse
    {
        $endpoint = (string) $request->input('endpoint', '');

        if ($endpoint !== '') {
            InscricaoPush::where('usuario_id', auth()->id())->where('endpoint', $endpoint)->delete();
        }

        return response()->json(['ok' => true]);
    }

    public function testar(): JsonResponse
    {
        if (! $this->webPushService->configurado()) {
            return response()->json(['ok' => false, 'motivo' => 'Web Push não está configurado no servidor.'], 422);
        }

        $resumo = $this->webPushService->enviarParaUsuario((int) auth()->id(), [
            'title' => 'Notificação de teste',
            'body' => 'Tudo certo! As notificações do Controle de Frota estão ativas neste dispositivo.',
            'url' => route('notificacoes.index'),
            'tag' => 'teste',
        ]);

        return response()->json(['ok' => $resumo['enviados'] > 0, 'resumo' => $resumo]);
    }

    private static function hostDePush(string $host): bool
    {
        $host = strtolower($host);

        foreach (self::HOSTS_DE_PUSH as $permitido) {
            if (str_starts_with($permitido, '.') ? str_ends_with($host, $permitido) : $host === $permitido) {
                return true;
            }
        }

        return false;
    }
}
