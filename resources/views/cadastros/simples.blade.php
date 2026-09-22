@extends('layouts.app')

@section('title', $titulo)

@section('content')
    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header bg-white"><strong>{{ $editando ? "Editar {$singular}" : "Adicionar {$singular}" }}</strong></div>
                <div class="card-body">
                    <form method="POST" action="{{ $editando ? route("{$rota}.update", $editando->id) : route("{$rota}.store") }}">
                        @csrf
                        @if($editando) @method('PUT') @endif
                        <div class="mb-3">
                            <label class="form-label gc-required">Nome</label>
                            <input type="text" name="nome" value="{{ old('nome', $editando->nome ?? '') }}" maxlength="100" class="form-control @error('nome') is-invalid @enderror" autofocus>
                            @error('nome') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="d-flex justify-content-end gap-2">
                            @if($editando)
                                <a href="{{ route("{$rota}.index") }}" class="btn btn-light">Cancelar</a>
                            @endif
                            <button class="btn btn-gc-primary"><i class="bi bi-check-lg"></i> Salvar</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card">
                <div class="card-header bg-white">
                    <form method="GET" class="d-flex gap-2 flex-wrap">
                        <input type="text" name="busca" value="{{ $busca }}" class="form-control form-control-sm" style="width:220px" placeholder="Buscar por nome">
                        <select name="ativo" class="form-select form-select-sm" style="width:120px">
                            <option value="">Todos</option>
                            <option value="1" @selected($ativo === '1')>Ativos</option>
                            <option value="0" @selected($ativo === '0')>Inativos</option>
                        </select>
                        <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-search"></i></button>
                        <a href="{{ route("{$rota}.index", ['limpar' => 1]) }}" class="btn btn-sm btn-light">Limpar</a>
                    </form>
                </div>
                <div class="table-responsive">
                    <table class="table table-gc mb-0 align-middle">
                        <thead><tr><th>Nome</th><th>Usuários</th><th>Situação</th><th class="text-end">Ações</th></tr></thead>
                        <tbody>
                            @forelse($registros as $r)
                                <tr>
                                    <td class="fw-semibold">{{ $r->nome }}</td>
                                    <td>{{ $r->usuarios_count }}</td>
                                    <td><x-ativo :value="$r->ativo" /></td>
                                    <td class="text-end">
                                        <div class="btn-group btn-group-sm">
                                            <a href="{{ route("{$rota}.index", ['editar' => $r->id]) }}" class="btn btn-outline-secondary" title="Editar"><i class="bi bi-pencil"></i></a>
                                            <form method="POST" action="{{ route("{$rota}.toggle-ativo", $r->id) }}" class="d-inline">
                                                @csrf @method('PATCH')
                                                <button class="btn btn-outline-secondary" title="{{ $r->ativo ? 'Inativar' : 'Ativar' }}"><i class="bi {{ $r->ativo ? 'bi-toggle-on' : 'bi-toggle-off' }}"></i></button>
                                            </form>
                                            <button type="button" class="btn btn-outline-danger" title="Excluir"
                                                data-confirm-delete data-url="{{ route("{$rota}.destroy", $r->id) }}"
                                                data-title="Excluir {{ $singular }}"
                                                data-message="Excluir &quot;{{ $r->nome }}&quot;? Só é possível quando não há usuários vinculados.">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4"><x-empty-state icon="bi-list" title="Nenhum registro" /></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($registros->hasPages())
                    <div class="card-footer bg-white">{{ $registros->links() }}</div>
                @endif
            </div>
        </div>
    </div>
@endsection
