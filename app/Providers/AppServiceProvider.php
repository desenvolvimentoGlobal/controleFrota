<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Alocacao;
use App\Models\Ocorrencia;
use App\Models\Usuario;
use App\Policies\AlocacaoPolicy;
use App\Policies\OcorrenciaPolicy;
use App\Policies\UsuarioPolicy;
use App\Services\AuditoriaService;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Paginator::useBootstrapFive();
        Model::shouldBeStrict(! $this->app->isProduction());

        // Atrás do Traefik o PHP-FPM enxerga HTTP; o esquema vem do APP_URL,
        // que é a fonte de verdade do endereço público (ver template-projeto).
        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        $this->registrarPolicies();
        $this->registrarGates();
        $this->registrarAuditoria();
    }

    private function registrarPolicies(): void
    {
        Gate::policy(Usuario::class, UsuarioPolicy::class);
        Gate::policy(Alocacao::class, AlocacaoPolicy::class);
        Gate::policy(Ocorrencia::class, OcorrenciaPolicy::class);
    }

    /**
     * Fonte ÚNICA de "quem pode o quê" por área. Liberar mais perfis é só
     * acrescentar o código ao array. O recorte por hierarquia (gestor vê a
     * própria equipe) fica nas Policies e nos scopes `visiveisPara`.
     */
    private function registrarGates(): void
    {
        Gate::define('usuarios.gerenciar', fn (Usuario $u) => $u->temAlgumPerfil('admin', 'gestor'));
        Gate::define('frota.gerenciar', fn (Usuario $u) => $u->temAlgumPerfil('admin', 'gestor'));
        Gate::define('alocacoes.aprovar', fn (Usuario $u) => $u->temAlgumPerfil('admin', 'gestor'));
        Gate::define('ocorrencias.revisar', fn (Usuario $u) => $u->temAlgumPerfil('admin', 'gestor'));
        Gate::define('manutencoes.gerenciar', fn (Usuario $u) => $u->temAlgumPerfil('admin', 'gestor'));
        Gate::define('manutencoes.ver', fn (Usuario $u) => $u->temAlgumPerfil('admin', 'gestor', 'financeiro'));
        Gate::define('manutencoes.anotar', fn (Usuario $u) => $u->temAlgumPerfil('admin', 'gestor', 'financeiro'));
        Gate::define('fornecedores.gerenciar', fn (Usuario $u) => $u->temAlgumPerfil('admin', 'gestor', 'financeiro'));
        Gate::define('cadastros.gerenciar', fn (Usuario $u) => $u->temPerfil('admin'));
        Gate::define('financeiro.ver', fn (Usuario $u) => $u->temAlgumPerfil('admin', 'financeiro'));
        Gate::define('administrar', fn (Usuario $u) => $u->temPerfil('admin'));
    }

    /**
     * Trilha de auditoria pelos eventos GLOBAIS do Eloquent: qualquer
     * criação/alteração/exclusão de qualquer model entra no log, inclusive de
     * telas futuras. Autenticação vem dos eventos do próprio Laravel.
     */
    private function registrarAuditoria(): void
    {
        $acoes = ['created' => 'criou', 'updated' => 'editou', 'deleted' => 'excluiu', 'restored' => 'restaurou'];

        foreach ($acoes as $evento => $acao) {
            Event::listen("eloquent.{$evento}: *", function (string $nome, array $payload) use ($acao): void {
                $modelo = $payload[0] ?? null;

                if ($modelo instanceof Model) {
                    app(AuditoriaService::class)->registrarModelo($acao, $modelo);
                }
            });
        }

        Event::listen(Login::class, fn (Login $e) => app(AuditoriaService::class)->registrar(
            acao: 'login',
            modulo: 'autenticacao',
            descricao: "{$e->user->nome} entrou no sistema",
            tabela: 'usuarios',
            registroId: (int) $e->user->getKey(),
            usuarioId: (int) $e->user->getKey(),
        ));

        Event::listen(Logout::class, function (Logout $e): void {
            if ($e->user) {
                app(AuditoriaService::class)->registrar(
                    acao: 'logout',
                    modulo: 'autenticacao',
                    descricao: "{$e->user->nome} saiu do sistema",
                    tabela: 'usuarios',
                    registroId: (int) $e->user->getKey(),
                    usuarioId: (int) $e->user->getKey(),
                );
            }
        });

        Event::listen(Failed::class, function (Failed $e): void {
            $acesso = $e->credentials['login'] ?? $e->credentials['email'] ?? '(não informado)';
            $usuarioId = $e->user?->getKey() ? (int) $e->user->getKey() : null;

            app(AuditoriaService::class)->registrar(
                acao: 'login_falhou',
                modulo: 'autenticacao',
                descricao: "Tentativa de login sem sucesso para {$acesso}",
                tabela: 'usuarios',
                registroId: $usuarioId,
                usuarioId: $usuarioId,
                exigeUsuario: false,
            );
        });
    }
}
