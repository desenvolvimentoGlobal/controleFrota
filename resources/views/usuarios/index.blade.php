@extends('layouts.app')

@section('title', 'Usuários')

@section('content')

    <div class="d-flex justify-content-between align-items-center mb-3 gap-2 flex-wrap">
        <form method="GET" class="d-flex gap-2 flex-wrap">
            <input type="text" name="busca" value="{{ $busca }}" class="form-control form-control-sm" style="width: 230px" placeholder="Nome, login, e-mail ou CPF">
            <select name="perfil_id" class="form-select form-select-sm" style="width: 150px">
                <option value="">Todos os perfis</option>
                @foreach($perfis as $perfil)
                    <option value="{{ $perfil->id }}" @selected(request('perfil_id') == $perfil->id)>{{ $perfil->nome }}</option>
                @endforeach
            </select>
            <select name="setor_id" class="form-select form-select-sm" style="width: 160px">
                <option value="">Todos os setores</option>
                @foreach($setores as $setor)
                    <option value="{{ $setor->id }}" @selected(request('setor_id') == $setor->id)>{{ $setor->nome }}</option>
                @endforeach
            </select>
            <select name="pode_dirigir" class="form-select form-select-sm" style="width: 140px">
                <option value="">Dirige: todos</option>
                <option value="1" @selected($podeDirigir === '1')>Pode dirigir</option>
                <option value="0" @selected($podeDirigir === '0')>Não dirige</option>
            </select>
            <select name="ativo" class="form-select form-select-sm" style="width: 120px">
                <option value="">Todos</option>
                <option value="1" @selected($ativo === '1')>Ativos</option>
                <option value="0" @selected($ativo === '0')>Inativos</option>
            </select>
            <button class="btn btn-sm btn-outline-secondary" title="Filtrar"><i class="bi bi-search"></i></button>
            <a href="{{ route('usuarios.index', ['limpar' => 1]) }}" class="btn btn-sm btn-light">Limpar</a>
        </form>
        @can('create', \App\Models\Usuario::class)
            <a href="{{ route('usuarios.create') }}" class="btn btn-gc-primary btn-sm"><i class="bi bi-plus-lg"></i> Novo usuário</a>
        @endcan
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-gc mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Login</th>
                        <th>Perfil</th>
                        <th>Setor / Cargo</th>
                        <th>Gestor</th>
                        <th>CNH</th>
                        <th>Situação</th>
                        <th class="text-end">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($usuarios as $usuario)
                        <tr>
                            <td>
                                <a href="{{ route('usuarios.show', $usuario) }}" class="fw-semibold text-decoration-none">{{ $usuario->nome }}</a>
                                <div class="text-muted small">{{ $usuario->email }}</div>
                            </td>
                            <td>{{ $usuario->login }}</td>
                            <td>{{ $usuario->perfil->nome ?? '—' }}</td>
                            <td class="small">{{ $usuario->setor->nome ?? '—' }}<br><span class="text-muted">{{ $usuario->cargo->nome ?? '' }}</span></td>
                            <td class="small">{{ $usuario->gestor->nome ?? '—' }}</td>
                            <td class="small">
                                @if($usuario->pode_dirigir)
                                    @if($usuario->cnhVencida())
                                        <span class="badge badge-situacao badge-inativo" title="CNH vencida">{{ $usuario->cnh_categoria ?? 'CNH' }} · vencida</span>
                                    @elseif($usuario->cnh_numero)
                                        <span class="badge badge-situacao badge-ativo">{{ $usuario->cnh_categoria }} · {{ $usuario->cnh_validade?->format('m/Y') }}</span>
                                    @else
                                        <span class="badge badge-situacao badge-pendente" title="Sem CNH cadastrada">sem CNH</span>
                                    @endif
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td><x-ativo :value="$usuario->ativo" /></td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    @can('update', $usuario)
                                        <a href="{{ route('usuarios.edit', $usuario) }}" class="btn btn-outline-secondary" title="Editar"><i class="bi bi-pencil"></i></a>
                                        @if($usuario->id !== auth()->id())
                                            <form method="POST" action="{{ route('usuarios.toggle-ativo', $usuario) }}" class="d-inline">
                                                @csrf @method('PATCH')
                                                <button type="submit" class="btn btn-outline-secondary" title="{{ $usuario->ativo ? 'Desativar' : 'Ativar' }}">
                                                    <i class="bi {{ $usuario->ativo ? 'bi-toggle-on' : 'bi-toggle-off' }}"></i>
                                                </button>
                                            </form>
                                        @endif
                                    @endcan
                                    @can('delete', $usuario)
                                        @if($usuario->id !== auth()->id())
                                            <button type="button" class="btn btn-outline-danger" title="Excluir"
                                                data-confirm-delete
                                                data-url="{{ route('usuarios.destroy', $usuario) }}"
                                                data-title="Excluir usuário"
                                                data-message="Excluir o usuário &quot;{{ $usuario->nome }}&quot;? Essa ação não pode ser desfeita.">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        @endif
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8"><x-empty-state icon="bi-people" title="Nenhum usuário encontrado" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($usuarios->hasPages())
            <div class="card-footer bg-white">{{ $usuarios->links() }}</div>
        @endif
    </div>
@endsection
