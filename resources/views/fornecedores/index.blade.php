@extends('layouts.app')

@section('title', 'Fornecedores')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3 gap-2 flex-wrap">
        <form method="GET" class="d-flex gap-2 flex-wrap">
            <input type="text" name="busca" value="{{ $busca }}" class="form-control form-control-sm" style="width:240px" placeholder="Razão social, fantasia, CNPJ ou cidade">
            <select name="ativo" class="form-select form-select-sm" style="width:120px">
                <option value="">Todos</option>
                <option value="1" @selected($ativo === '1')>Ativos</option>
                <option value="0" @selected($ativo === '0')>Inativos</option>
            </select>
            <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-search"></i></button>
            <a href="{{ route('fornecedores.index', ['limpar' => 1]) }}" class="btn btn-sm btn-light">Limpar</a>
        </form>
        <a href="{{ route('fornecedores.create') }}" class="btn btn-gc-primary btn-sm"><i class="bi bi-plus-lg"></i> Novo fornecedor</a>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-gc mb-0 align-middle">
                <thead><tr><th>Fornecedor</th><th>CNPJ</th><th>Contato</th><th>Cidade</th><th>Situação</th><th class="text-end">Ações</th></tr></thead>
                <tbody>
                    @forelse($fornecedores as $f)
                        <tr>
                            <td><span class="fw-semibold">{{ $f->nome }}</span>@if($f->nome_fantasia)<div class="text-muted small">{{ $f->razao_social }}</div>@endif</td>
                            <td class="small">{{ $f->cnpj_formatado ?? '—' }}</td>
                            <td class="small">{{ $f->contato ?? '—' }}<br><span class="text-muted">{{ $f->telefone ?? $f->email ?? '' }}</span></td>
                            <td class="small">{{ $f->cidade ? "{$f->cidade}/{$f->uf}" : '—' }}</td>
                            <td><x-ativo :value="$f->ativo" /></td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <a href="{{ route('fornecedores.edit', $f) }}" class="btn btn-outline-secondary" title="Editar"><i class="bi bi-pencil"></i></a>
                                    <form method="POST" action="{{ route('fornecedores.toggle-ativo', $f) }}" class="d-inline">
                                        @csrf @method('PATCH')
                                        <button class="btn btn-outline-secondary" title="{{ $f->ativo ? 'Inativar' : 'Ativar' }}"><i class="bi {{ $f->ativo ? 'bi-toggle-on' : 'bi-toggle-off' }}"></i></button>
                                    </form>
                                    @can('administrar')
                                        <button type="button" class="btn btn-outline-danger" title="Excluir" data-confirm-delete
                                            data-url="{{ route('fornecedores.destroy', $f) }}" data-title="Excluir fornecedor"
                                            data-message="Excluir &quot;{{ $f->nome }}&quot;?"><i class="bi bi-trash"></i></button>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><x-empty-state icon="bi-shop" title="Nenhum fornecedor cadastrado" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($fornecedores->hasPages())
            <div class="card-footer bg-white">{{ $fornecedores->links() }}</div>
        @endif
    </div>
@endsection
