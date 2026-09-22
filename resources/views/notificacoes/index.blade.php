@extends('layouts.app')

@section('title', 'Notificações')

@section('content')

    @if(config('services.webpush.public_key'))
        <div class="card mb-3" id="push-config">
            <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <strong><i class="bi bi-bell"></i> Notificações no navegador</strong>
                    <div id="push-status" class="small text-muted">Verificando…</div>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" id="push-ativar" class="btn btn-sm btn-gc-primary d-none"><i class="bi bi-bell"></i> Ativar notificações</button>
                    <button type="button" id="push-testar" class="btn btn-sm btn-outline-secondary d-none"><i class="bi bi-send"></i> Enviar teste</button>
                    <button type="button" id="push-desativar" class="btn btn-sm btn-outline-danger d-none"><i class="bi bi-bell-slash"></i> Desativar</button>
                </div>
            </div>
        </div>
    @endif

    @can('administrar')
        <div class="card mb-3">
            <div class="card-header bg-white"><strong><i class="bi bi-megaphone"></i> Aviso geral</strong>
                <span class="text-muted small ms-2">Envia para todos os usuários ativos (sino + notificação do navegador).</span>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('notificacoes.aviso-geral') }}" class="row g-2"
                      data-gc-confirm="Enviar este aviso para TODOS os usuários ativos do sistema?">
                    @csrf
                    <div class="col-md-4">
                        <input type="text" name="titulo" maxlength="150" class="form-control form-control-sm @error('titulo') is-invalid @enderror" placeholder="Título do aviso" value="{{ old('titulo') }}" required>
                        @error('titulo') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-6">
                        <input type="text" name="mensagem" maxlength="1000" class="form-control form-control-sm @error('mensagem') is-invalid @enderror" placeholder="Mensagem" value="{{ old('mensagem') }}" required>
                        @error('mensagem') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-2 d-grid">
                        <button class="btn btn-sm btn-gc-primary"><i class="bi bi-send"></i> Enviar aviso</button>
                    </div>
                </form>
            </div>
        </div>
    @endcan

    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h1 class="h4 mb-0">Notificações</h1>
        <div class="d-flex gap-2 align-items-center">
            <div class="btn-group btn-group-sm" role="group">
                <a href="{{ route('notificacoes.index', ['filtro' => 'nao_lidas']) }}" class="btn {{ $filtro === 'nao_lidas' ? 'btn-secondary' : 'btn-outline-secondary' }}">
                    Não lidas @if($totalNaoLidas > 0)<span class="badge text-bg-light border ms-1">{{ $totalNaoLidas }}</span>@endif
                </a>
                <a href="{{ route('notificacoes.index', ['filtro' => 'todas']) }}" class="btn {{ $filtro === 'todas' ? 'btn-secondary' : 'btn-outline-secondary' }}">Todas</a>
            </div>
            @if($totalNaoLidas > 0)
                <button type="submit" form="formTodasLidas" class="btn btn-sm btn-light border"><i class="bi bi-check2-all"></i> Marcar todas como lidas</button>
            @endif
        </div>
    </div>

    <div class="card">
        <div class="list-group list-group-flush">
            @forelse($notificacoes as $notificacao)
                <a href="{{ route('notificacoes.abrir', $notificacao) }}" class="list-group-item list-group-item-action d-flex gap-3 align-items-start {{ $notificacao->lida_em ? '' : 'bg-light-subtle' }}">
                    <span class="mt-1 text-muted"><i class="bi {{ $notificacao->lida_em ? 'bi-envelope-open' : 'bi-envelope-fill' }}"></i></span>
                    <span class="flex-grow-1">
                        <span class="d-flex justify-content-between gap-2">
                            <strong class="{{ $notificacao->lida_em ? 'fw-normal' : '' }}">{{ $notificacao->titulo }}</strong>
                            <span class="text-muted small text-nowrap" title="{{ $notificacao->created_at->format('d/m/Y H:i') }}">{{ $notificacao->created_at->diffForHumans() }}</span>
                        </span>
                        @if($notificacao->mensagem)
                            <span class="d-block text-muted small">{{ $notificacao->mensagem }}</span>
                        @endif
                    </span>
                    @unless($notificacao->lida_em)
                        <span class="badge text-bg-light border align-self-center">nova</span>
                    @endunless
                </a>
            @empty
                <div class="list-group-item">
                    <x-empty-state icon="bi-bell" title="{{ $filtro === 'nao_lidas' ? 'Nenhuma notificação não lida' : 'Nenhuma notificação' }}"
                                   description="Avisos de alocações, checagens, ocorrências e manutenções aparecerão aqui." />
                </div>
            @endforelse
        </div>
        @if($notificacoes->hasPages())
            <div class="card-footer bg-white d-flex justify-content-end">{{ $notificacoes->links() }}</div>
        @endif
    </div>

    <form id="formTodasLidas" method="POST" action="{{ route('notificacoes.todas-lidas') }}" class="d-none">@csrf @method('PATCH')</form>
@endsection
