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
    public function __construct(private readonly WebPushService $webPushService) {}

    public function inscrever(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'endpoint' => ['required', 'string', 'max:500'],
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
}
