<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\InscricaoPush;
use Composer\CaBundle\CaBundle;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

/**
 * Envio de notificações Web Push (padrão dos sistemas irmãos). Remove
 * inscrições inválidas/expiradas sem interromper o envio para os demais
 * dispositivos. No-op silencioso sem chaves VAPID.
 */
class WebPushService
{
    private const TIMEOUT_SEGUNDOS = 8;

    private const CONNECT_TIMEOUT_SEGUNDOS = 4;

    public function configurado(): bool
    {
        $config = config('services.webpush');

        return ! empty($config['public_key']) && ! empty($config['private_key']);
    }

    /**
     * @param  array{title: string, body?: string, url?: string, tag?: string}  $conteudo
     * @return array{enviados: int, falhas: int, removidas: int}
     */
    public function enviarParaUsuario(int $usuarioId, array $conteudo): array
    {
        $resumo = ['enviados' => 0, 'falhas' => 0, 'removidas' => 0];

        if (! $this->configurado()) {
            return $resumo;
        }

        $inscricoes = InscricaoPush::where('usuario_id', $usuarioId)->get();
        if ($inscricoes->isEmpty()) {
            return $resumo;
        }

        $config = config('services.webpush');

        // web-push ^11 recebe um cliente PSR-18 em vez de timeout/opções.
        // Timeout curto e obrigatório: o envio roda dentro da requisição de
        // quem disparou a notificação. Sem isso, um endpoint mudo prende a
        // tela até o PHP matar a requisição.
        $cliente = new Client([
            'timeout' => self::TIMEOUT_SEGUNDOS,
            'connect_timeout' => self::CONNECT_TIMEOUT_SEGUNDOS,
            // No Windows/WAMP o PHP não tem curl.cainfo; o ca-bundle resolve.
            'verify' => CaBundle::getSystemCaRootBundlePath(),
        ]);

        $webPush = new WebPush(
            ['VAPID' => [
                'subject' => $config['subject'],
                'publicKey' => $config['public_key'],
                'privateKey' => $config['private_key'],
            ]],
            [],
            $cliente,
        );

        $payload = json_encode([
            'title' => $conteudo['title'] ?? 'Notificação',
            'body' => $conteudo['body'] ?? '',
            'url' => $conteudo['url'] ?? route('notificacoes.index'),
            'tag' => $conteudo['tag'] ?? 'geral',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        foreach ($inscricoes as $inscricao) {
            try {
                $assinatura = Subscription::create([
                    'endpoint' => $inscricao->endpoint,
                    'keys' => ['p256dh' => $inscricao->chave_p256dh, 'auth' => $inscricao->chave_auth],
                ]);

                $relatorio = $webPush->sendOneNotification($assinatura, (string) $payload);

                if ($relatorio->isSuccess()) {
                    $resumo['enviados']++;

                    continue;
                }

                $resumo['falhas']++;

                // 404/410: expirada. 403: chave VAPID trocada; nunca mais funciona.
                if ($relatorio->isSubscriptionExpired() || $relatorio->getResponse()?->getStatusCode() === 403) {
                    $inscricao->delete();
                    $resumo['removidas']++;

                    continue;
                }

                Log::warning('Falha ao enviar Web Push', ['usuario_id' => $usuarioId, 'motivo' => $relatorio->getReason()]);
            } catch (\Throwable $e) {
                $resumo['falhas']++;
                $inscricao->delete();
                $resumo['removidas']++;

                Log::warning('Inscrição push inválida removida', ['usuario_id' => $usuarioId, 'motivo' => $e->getMessage()]);
            }
        }

        return $resumo;
    }
}
