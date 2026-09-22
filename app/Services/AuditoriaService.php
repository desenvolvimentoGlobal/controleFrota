<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\LogAuditoria;
use Illuminate\Database\Eloquent\Model;

/**
 * Ponto único de gravação da trilha de auditoria (`logs_auditoria`).
 *
 * Regras (herdadas dos sistemas irmãos):
 *  - registra APENAS ações de pessoas: sem usuário autenticado nada é gravado
 *    (exceto login com falha, que é justamente o que denuncia ataque);
 *  - tabelas de infraestrutura e de efeito colateral ficam de fora;
 *  - campos sensíveis viram [PROTEGIDO];
 *  - falha ao auditar NUNCA derruba a operação do usuário.
 */
class AuditoriaService
{
    private const CAMPOS_SENSIVEIS = [
        'senha', 'password', 'password_confirmation', 'senha_confirmation',
        'remember_token', 'token', 'api_token', 'secret', 'chave_p256dh', 'chave_auth',
    ];

    private const CAMPOS_IGNORADOS = ['created_at', 'updated_at'];

    private const TABELAS_IGNORADAS = [
        'logs_auditoria', 'notificacoes', 'inscricoes_push', 'sessions', 'cache', 'cache_locks',
        'jobs', 'job_batches', 'failed_jobs', 'password_reset_tokens', 'migrations',
        // Derivadas: alimentadas por ações que já entram na trilha.
        'veiculo_historico_estados', 'manutencao_movimentacoes', 'checagem_fotos', 'checagem_itens',
    ];

    /** Rótulos em português para as descrições legíveis. */
    private const ENTIDADES = [
        'usuarios' => 'o usuário',
        'perfis' => 'o perfil',
        'setores' => 'o setor',
        'cargos' => 'o cargo',
        'veiculos' => 'o veículo',
        'veiculo_condicoes' => 'a condição mecânica de',
        'alocacoes' => 'a alocação',
        'checagens' => 'a checagem',
        'checagem_itens' => 'o item de checagem',
        'checagem_fotos' => 'a foto de checagem',
        'ocorrencias' => 'a ocorrência',
        'fornecedores' => 'o fornecedor',
        'manutencoes' => 'a manutenção',
        'manutencao_anexos' => 'o anexo da manutenção',
        'planos_manutencao' => 'o plano de manutenção',
    ];

    /**
     * Registro cru (fluxos que não alteram um model: exportar, aprovar em lote...).
     *
     * @param  array<string, mixed>|null  $valoresAntigos
     * @param  array<string, mixed>|null  $valoresNovos
     */
    public function registrar(
        string $acao,
        ?string $modulo = null,
        ?string $descricao = null,
        ?string $tabela = null,
        ?int $registroId = null,
        ?array $valoresAntigos = null,
        ?array $valoresNovos = null,
        ?int $usuarioId = null,
        bool $exigeUsuario = true,
    ): ?LogAuditoria {
        $usuarioId ??= auth()->id();

        if ($usuarioId === null && $exigeUsuario) {
            return null;
        }

        try {
            return LogAuditoria::create([
                'usuario_id' => $usuarioId,
                'acao' => $acao,
                'modulo' => $modulo ?? $tabela,
                'tabela' => $tabela,
                'registro_id' => $registroId,
                'descricao' => $descricao !== null ? mb_substr($descricao, 0, 500) : null,
                'valores_antigos' => $this->sanitizar($valoresAntigos),
                'valores_novos' => $this->sanitizar($valoresNovos),
                'ip' => request()?->ip(),
                'user_agent' => substr((string) request()?->userAgent(), 0, 255) ?: null,
            ]);
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    /** Registro automático a partir de um evento do Eloquent. */
    public function registrarModelo(string $acao, Model $modelo): ?LogAuditoria
    {
        $tabela = $modelo->getTable();

        if (in_array($tabela, self::TABELAS_IGNORADAS, true) || auth()->id() === null) {
            return null;
        }

        [$antigos, $novos] = match ($acao) {
            'criou' => [null, $this->relevantes($modelo->getAttributes())],
            'excluiu' => [$this->relevantes($modelo->getAttributes()), null],
            default => $this->diffDaAtualizacao($modelo),
        };

        if ($acao === 'editou' && $antigos === []) {
            return null;
        }

        return $this->registrar(
            acao: $acao,
            modulo: $tabela,
            descricao: $this->descricao($acao, $modelo, is_array($novos) ? array_keys($novos) : []),
            tabela: $tabela,
            registroId: is_numeric($modelo->getKey()) ? (int) $modelo->getKey() : null,
            valoresAntigos: $antigos,
            valoresNovos: $novos,
        );
    }

    // ─── Apoio ─────────────────────────────────────────────────────────────────

    /** @return array{0: array<string, mixed>, 1: array<string, mixed>} */
    private function diffDaAtualizacao(Model $modelo): array
    {
        $antigos = [];
        $novos = [];

        foreach ($modelo->getChanges() as $campo => $valorNovo) {
            if (in_array($campo, self::CAMPOS_IGNORADOS, true)) {
                continue;
            }
            $antigos[$campo] = $modelo->getOriginal($campo);
            $novos[$campo] = $valorNovo;
        }

        return [$antigos, $novos];
    }

    /**
     * @param  array<string, mixed>  $atributos
     * @return array<string, mixed>
     */
    private function relevantes(array $atributos): array
    {
        return array_diff_key($atributos, array_flip(self::CAMPOS_IGNORADOS));
    }

    /**
     * @param  array<string, mixed>|null  $dados
     * @return array<string, mixed>|null
     */
    private function sanitizar(?array $dados): ?array
    {
        if ($dados === null) {
            return null;
        }

        foreach ($dados as $campo => $valor) {
            if (in_array(strtolower((string) $campo), self::CAMPOS_SENSIVEIS, true)) {
                $dados[$campo] = '[PROTEGIDO]';
            }
        }

        return $dados;
    }

    /** @param  array<int, string>  $camposAlterados */
    private function descricao(string $acao, Model $modelo, array $camposAlterados): string
    {
        $verbo = ucfirst($acao);
        $entidade = self::ENTIDADES[$modelo->getTable()] ?? "o registro de {$modelo->getTable()}";

        // getAttributes() e não getAttribute(): com Model::shouldBeStrict() um
        // atributo inexistente lança exceção, e nem todo model tem `nome`.
        $atributos = $modelo->getAttributes();
        $identificacao = $atributos['nome']
            ?? $atributos['placa']
            ?? $atributos['razao_social']
            ?? $atributos['titulo']
            ?? $atributos['codigo']
            ?? $atributos['sistema']
            ?? ('#'.$modelo->getKey());

        $texto = "{$verbo} {$entidade} {$identificacao}";

        if ($camposAlterados !== []) {
            $texto .= ' (campos: '.implode(', ', $camposAlterados).')';
        }

        return $texto;
    }
}
