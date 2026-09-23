<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Notificacao;
use App\Models\Usuario;
use Illuminate\Support\Collection;

/**
 * Fonte única de disparo das notificações: cria a notificação interna (sino)
 * e envia o Web Push para os dispositivos inscritos de cada destinatário.
 */
class NotificacaoService
{
    public function __construct(private readonly WebPushService $webPush) {}

    /**
     * @param  iterable<Usuario>  $usuarios
     * @param  bool  $atualizarNaoLida  Anti-spam: se já existe uma NÃO LIDA do
     *                                  mesmo tipo, atualiza em vez de criar outra.
     * @return int Quantidade de usuários notificados.
     */
    public function enviar(
        iterable $usuarios,
        string $tipo,
        string $titulo,
        string $mensagem,
        ?string $url = null,
        bool $atualizarNaoLida = false,
    ): int {
        $enviados = 0;

        foreach ($usuarios as $usuario) {
            $dados = ['titulo' => $titulo, 'mensagem' => $mensagem, 'url' => $url];

            $existente = $atualizarNaoLida
                ? Notificacao::where('usuario_id', $usuario->id)->where('tipo', $tipo)->naoLidas()->latest()->first()
                : null;

            // Aviso recorrente (ex.: vencimento, todo dia) com o mesmo texto:
            // não vibra o celular de novo. Sem mudança, a não lida já basta;
            // se foi lida, repete no máximo uma vez por semana.
            if ($atualizarNaoLida) {
                $igual = $existente !== null && $existente->titulo === $titulo && $existente->mensagem === $mensagem;
                $repetidaNaSemana = $existente === null && Notificacao::where('usuario_id', $usuario->id)->where('tipo', $tipo)
                    ->where('mensagem', $mensagem)->where('updated_at', '>=', now()->subDays(7))->exists();
                if ($igual || $repetidaNaSemana) {
                    continue;
                }
            }

            $existente
                ? $existente->update($dados)
                : Notificacao::create($dados + ['usuario_id' => $usuario->id, 'tipo' => $tipo]);

            $this->webPush->enviarParaUsuario($usuario->id, [
                'title' => $titulo,
                'body' => $mensagem,
                'url' => $url ?? '/notificacoes',
                'tag' => $tipo,
            ]);

            $enviados++;
        }

        return $enviados;
    }

    /**
     * Usuários ativos com um dos perfis informados.
     *
     * @param  array<int, string>  $codigos
     * @return Collection<int, Usuario>
     */
    public function comPerfis(array $codigos, ?int $excetoUsuarioId = null): Collection
    {
        return Usuario::where('ativo', true)
            ->whereHas('perfil', fn ($q) => $q->whereIn('codigo', $codigos))
            ->when($excetoUsuarioId, fn ($q) => $q->whereKeyNot($excetoUsuarioId))
            ->get();
    }

    /** @return Collection<int, Usuario> */
    public function todosAtivos(?int $excetoUsuarioId = null): Collection
    {
        return Usuario::where('ativo', true)
            ->when($excetoUsuarioId, fn ($q) => $q->whereKeyNot($excetoUsuarioId))
            ->get();
    }

    /**
     * Gestor direto do usuário (se ativo) mais todos os admins. É o conjunto
     * padrão para "avisar quem decide" sobre alguém.
     *
     * @return Collection<int, Usuario>
     */
    public function responsaveisPor(Usuario $usuario, ?int $excetoUsuarioId = null): Collection
    {
        $admins = $this->comPerfis(['admin'], $excetoUsuarioId);
        $gestor = $usuario->gestor;

        if ($gestor && $gestor->ativo && $gestor->id !== $excetoUsuarioId) {
            $admins->push($gestor);
        }

        return $admins->unique('id')->values();
    }
}
