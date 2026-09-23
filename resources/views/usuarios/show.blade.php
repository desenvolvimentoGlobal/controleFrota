@extends('layouts.app')

@section('title', $usuario->nome)
@section('voltar', route('usuarios.index'))

@section('content')
    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-body text-center">
                    @if($usuario->foto_url)
                        <img src="{{ $usuario->foto_url }}" alt="{{ $usuario->nome }}" class="rounded-circle mb-3" style="width:96px;height:96px;object-fit:cover">
                    @else
                        <div class="rounded-circle d-inline-flex align-items-center justify-content-center mb-3 bg-soft-navy fw-bold fs-3" style="width:96px;height:96px">{{ $usuario->iniciais }}</div>
                    @endif
                    <h5 class="mb-0">{{ $usuario->nome }}</h5>
                    <div class="text-muted small">{{ $usuario->cargo->nome ?? '—' }} · {{ $usuario->setor->nome ?? '—' }}</div>
                    <div class="mt-2"><span class="badge text-bg-light border">{{ $usuario->perfil->nome }}</span> <x-ativo :value="$usuario->ativo" /></div>
                    @can('update', $usuario)
                        <a href="{{ route('usuarios.edit', $usuario) }}" class="btn btn-sm btn-outline-secondary mt-3"><i class="bi bi-pencil"></i> Editar</a>
                    @endcan
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card mb-3">
                <div class="card-header bg-white"><strong>Dados</strong></div>
                <div class="card-body">
                    <dl class="row mb-0 small">
                        <dt class="col-sm-3">Login</dt><dd class="col-sm-9">{{ $usuario->login }}</dd>
                        <dt class="col-sm-3">E-mail</dt><dd class="col-sm-9">{{ $usuario->email }}</dd>
                        <dt class="col-sm-3">CPF</dt><dd class="col-sm-9">{{ $usuario->cpf_formatado ?? '—' }}</dd>
                        <dt class="col-sm-3">Gestor</dt><dd class="col-sm-9">{{ $usuario->gestor->nome ?? '—' }}</dd>
                        <dt class="col-sm-3">Contato</dt><dd class="col-sm-9">{{ $usuario->celular ?: $usuario->telefone ?: '—' }}</dd>
                        <dt class="col-sm-3">Endereço</dt>
                        <dd class="col-sm-9">
                            @if($usuario->logradouro)
                                {{ $usuario->logradouro }}, {{ $usuario->numero }} {{ $usuario->complemento }} — {{ $usuario->bairro }}, {{ $usuario->cidade }}/{{ $usuario->uf }}
                            @else — @endif
                        </dd>
                        <dt class="col-sm-3">Último acesso</dt><dd class="col-sm-9">{{ $usuario->ultimo_login_em?->format('d/m/Y H:i') ?? 'nunca' }}</dd>
                    </dl>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header bg-white"><strong>Habilitação</strong></div>
                <div class="card-body small">
                    @if($usuario->pode_dirigir)
                        <p class="mb-1"><i class="bi bi-check-circle text-success"></i> Apto a dirigir veículos da frota.</p>
                        <p class="mb-1">CNH: {{ $usuario->cnh_numero ?? '—' }} · Categoria {{ $usuario->cnh_categoria ?? '—' }} · Validade {{ $usuario->cnh_validade?->format('d/m/Y') ?? '—' }}</p>
                        @if($aviso = $usuario->avisoHabilitacao())
                            <p class="mb-0 text-warning"><i class="bi bi-exclamation-triangle"></i> {{ $aviso }}</p>
                        @endif
                    @else
                        <p class="mb-0 text-muted">Não está marcado como apto a dirigir.</p>
                    @endif
                </div>
            </div>

            @if($usuario->subordinados->isNotEmpty())
                <div class="card">
                    <div class="card-header bg-white"><strong>Equipe</strong> <span class="text-muted small">({{ $usuario->subordinados->count() }})</span></div>
                    <ul class="list-group list-group-flush">
                        @foreach($usuario->subordinados as $membro)
                            <li class="list-group-item d-flex justify-content-between align-items-center small">
                                <a href="{{ route('usuarios.show', $membro) }}" class="text-decoration-none">{{ $membro->nome }}</a>
                                <x-ativo :value="$membro->ativo" />
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    </div>
@endsection
