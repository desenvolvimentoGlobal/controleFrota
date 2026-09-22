<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Painel') · Controle de Frota</title>
    <link rel="icon" type="image/png" href="{{ asset('images/logo_apenas_bola.jpg') }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    {{-- Versionado pelo mtime: alterações no CSS invalidam o cache do navegador. --}}
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">

    @auth
        <meta name="csrf-token" content="{{ csrf_token() }}">
        @if(config('services.webpush.public_key'))
            <meta name="vapid-public-key" content="{{ config('services.webpush.public_key') }}">
            <meta name="webpush-inscrever-url" content="{{ route('webpush.inscrever') }}">
            <meta name="webpush-desinscrever-url" content="{{ route('webpush.desinscrever') }}">
            <meta name="webpush-testar-url" content="{{ route('webpush.testar') }}">
        @endif
    @endauth

    @stack('styles')
</head>
<body>
<div class="gc-wrapper">

    {{-- ===================== SIDEBAR (recolhível — ver public/js/app.js) ===================== --}}
    <aside class="gc-sidebar">
        <button type="button" class="gc-sidebar-fechar" id="gc-sidebar-fechar" aria-label="Fechar menu" title="Fechar menu">
            <i class="bi bi-x-lg" aria-hidden="true"></i>
        </button>

        <div class="gc-brand">
            <div class="gc-brand-logo-wrap">
                <img src="{{ asset('images/logo_completa.jpg') }}" alt="GLOBAL" class="gc-brand-logo gc-brand-logo-full">
                <img src="{{ asset('images/logo_apenas_bola.jpg') }}" alt="GLOBAL" class="gc-brand-logo gc-brand-logo-icon">
            </div>
            <small class="gc-brand-subtitle">Controle de Frota</small>
        </div>

        <div class="gc-nav-scroll">
        <button type="button" class="gc-nav-scroll-btn gc-nav-scroll-up" aria-label="Rolar menu para cima" aria-hidden="true" hidden>
            <i class="bi bi-chevron-up" aria-hidden="true"></i>
        </button>

        <nav class="gc-nav">
            @auth
            <a href="{{ route('painel') }}" class="gc-nav-link {{ request()->routeIs('painel') ? 'active' : '' }}">
                <i class="bi bi-grid-1x2"></i> Painel
            </a>
            <a href="{{ route('notificacoes.index') }}" class="gc-nav-link {{ request()->routeIs('notificacoes.*') ? 'active' : '' }}">
                <i class="bi bi-bell"></i> Notificações
            </a>

            {{-- ===== Frota (fase 1) ===== --}}
            <div class="gc-nav-section">Frota</div>
            <span class="gc-nav-link disabled" style="opacity:.55;cursor:default" aria-disabled="true" title="Fase 1">
                <i class="bi bi-car-front"></i> Veículos
            </span>

            {{-- ===== Operação (fase 2) ===== --}}
            <div class="gc-nav-section">Operação</div>
            <span class="gc-nav-link disabled" style="opacity:.55;cursor:default" aria-disabled="true" title="Fase 2">
                <i class="bi bi-calendar-check"></i> Alocações
            </span>
            <span class="gc-nav-link disabled" style="opacity:.55;cursor:default" aria-disabled="true" title="Fase 2">
                <i class="bi bi-camera"></i> Checagens
            </span>

            {{-- ===== Manutenção (fase 3) ===== --}}
            @can('manutencoes.gerenciar')
                <div class="gc-nav-section">Manutenção</div>
                <span class="gc-nav-link disabled" style="opacity:.55;cursor:default" aria-disabled="true" title="Fase 3">
                    <i class="bi bi-wrench-adjustable"></i> Manutenções
                </span>
            @endcan

            {{-- ===== Financeiro (fase 4) ===== --}}
            @can('financeiro.ver')
                <div class="gc-nav-section">Financeiro</div>
                <span class="gc-nav-link disabled" style="opacity:.55;cursor:default" aria-disabled="true" title="Fase 4">
                    <i class="bi bi-cash-coin"></i> Custos
                </span>
            @endcan

            {{-- ===== Administração ===== --}}
            @can('usuarios.gerenciar')
                <div class="gc-nav-section">Administração</div>
                <a href="{{ route('usuarios.index') }}" class="gc-nav-link {{ request()->routeIs('usuarios.*') ? 'active' : '' }}">
                    <i class="bi bi-person-gear"></i> Usuários
                </a>
            @endcan
            @can('administrar')
                <a href="{{ route('logs.index') }}" class="gc-nav-link {{ request()->routeIs('logs.*') ? 'active' : '' }}">
                    <i class="bi bi-journal-text"></i> Logs
                </a>
            @endcan
            @endauth
        </nav>

        <button type="button" class="gc-nav-scroll-btn gc-nav-scroll-down" aria-label="Rolar menu para baixo" aria-hidden="true" hidden>
            <i class="bi bi-chevron-down" aria-hidden="true"></i>
        </button>
        </div>

        @auth
        <div class="gc-sidebar-footer">
            Perfil: <strong class="text-white-50">{{ auth()->user()->perfil->nome ?? '—' }}</strong>
        </div>
        @endauth
    </aside>

    {{-- ===================== CONTEÚDO ===================== --}}
    <div class="gc-main">
        <header class="gc-topbar">
            <div class="d-flex align-items-center gap-3">
                <button id="gc-sidebar-toggle" class="btn btn-sm gc-btn-menu" title="Abrir/fechar menu" aria-label="Abrir ou fechar menu">
                    <i class="bi bi-list"></i>
                </button>
                @hasSection('voltar')
                    <a href="@yield('voltar')" class="btn btn-sm btn-light" title="Voltar"><i class="bi bi-arrow-left"></i></a>
                @endif
                <div>
                    <p class="gc-page-title">@yield('title', 'Painel')</p>
                    @hasSection('breadcrumbs')
                        <div class="gc-breadcrumb">@yield('breadcrumbs')</div>
                    @endif
                </div>
            </div>

            @auth
            <div class="d-flex align-items-center gap-2">
                @php($gcNaoLidas = auth()->user()->notificacoes()->naoLidas()->count())
                <a href="{{ route('notificacoes.index') }}" class="btn btn-light btn-sm gc-bell-btn"
                   title="Notificações{{ $gcNaoLidas > 0 ? " ({$gcNaoLidas} não lida".($gcNaoLidas > 1 ? 's' : '').')' : '' }}">
                    <i class="bi bi-bell"></i>
                    @if($gcNaoLidas > 0)
                        <span class="gc-bell-dot"></span>
                    @endif
                </a>

                <div class="dropdown">
                    <button class="btn btn-light btn-sm dropdown-toggle d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle"></i>
                        <span class="d-none d-md-inline">{{ auth()->user()->nome }}</span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><span class="dropdown-item-text text-muted small">{{ auth()->user()->email }}</span></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="{{ route('senha.editar') }}"><i class="bi bi-key"></i> Alterar senha</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="dropdown-item text-danger">
                                    <i class="bi bi-box-arrow-right"></i> Sair
                                </button>
                            </form>
                        </li>
                    </ul>
                </div>
            </div>
            @endauth
        </header>

        <main class="gc-content">
            @yield('content')
        </main>
    </div>
</div>

{{-- ===================== TOASTS (flash) ===================== --}}
<div class="toast-container position-fixed top-0 end-0 p-3">
    @if(session('sucesso'))
        <div class="toast text-bg-success border-0" role="alert">
            <div class="d-flex">
                <div class="toast-body"><i class="bi bi-check-circle me-1"></i> {{ session('sucesso') }}</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    @endif
    @if(session('aviso'))
        <div class="toast text-bg-warning border-0" role="alert" data-bs-autohide="false">
            <div class="d-flex">
                <div class="toast-body"><i class="bi bi-exclamation-triangle me-1"></i> {{ session('aviso') }}</div>
                <button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    @endif
    @if(session('erro'))
        <div class="toast text-bg-danger border-0" role="alert">
            <div class="d-flex">
                <div class="toast-body"><i class="bi bi-x-circle me-1"></i> {{ session('erro') }}</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    @endif
    @if($errors->has('erro'))
        <div class="toast text-bg-danger border-0" role="alert">
            <div class="d-flex">
                <div class="toast-body"><i class="bi bi-x-circle me-1"></i> {{ $errors->first('erro') }}</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    @endif
</div>

{{-- ===================== gcAlert / gcConfirm ===================== --}}
<div class="modal fade gc-alert-modal" id="gc-alert-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content gc-alert-content gc-alert-info">
            <div class="modal-header border-0">
                <span class="gc-alert-icon"><i class="bi" data-gc-alert-icon></i></span>
                <h5 class="modal-title" data-gc-alert-title>Aviso</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body"><p class="mb-0" data-gc-alert-message></p></div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-light" data-gc-alert-cancel data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-gc-primary" data-gc-alert-confirm>OK</button>
            </div>
        </div>
    </div>
</div>

{{-- ===================== Confirmação de exclusão (data-confirm-delete) ===================== --}}
<div class="modal fade" id="gc-confirm-modal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="#">
                @csrf
                <input type="hidden" name="_method" value="DELETE">
                <div class="modal-header">
                    <h5 class="modal-title" data-confirm-title>Confirmar ação</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body"><p class="mb-0" data-confirm-message>Tem certeza que deseja confirmar esta ação?</p></div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger">Confirmar</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ===================== Loading de exportação ===================== --}}
<div class="modal fade" id="gc-export-modal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false" aria-labelledby="gc-export-titulo">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content text-center">
            <div class="modal-body p-4">
                <div class="spinner-border text-primary mb-3" role="status" style="width:2.5rem;height:2.5rem;">
                    <span class="visually-hidden">Gerando arquivo...</span>
                </div>
                <h6 class="mb-1" id="gc-export-titulo" data-gc-export-titulo>Gerando arquivo...</h6>
                <p class="text-muted small mb-3">Arquivos com muitos dados podem levar alguns instantes.</p>
                <button type="button" class="btn btn-outline-secondary btn-sm" data-gc-export-cancelar>
                    <i class="bi bi-x-lg me-1"></i> Cancelar
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script src="{{ asset('js/app.js') }}?v={{ filemtime(public_path('js/app.js')) }}"></script>
@auth
    @if(config('services.webpush.public_key'))
        <script src="{{ asset('js/webpush.js') }}?v={{ filemtime(public_path('js/webpush.js')) }}"></script>
    @endif
@endauth
@stack('scripts')
</body>
</html>
