@extends('layouts.app')

@section('title', 'Ocorrências')

@section('content')
    <form method="GET" class="d-flex gap-2 flex-wrap mb-3">
        <select name="situacao" class="form-select form-select-sm" style="width:160px">
            <option value="">Todas</option>
            @foreach($situacoes as $v => $r)<option value="{{ $v }}" @selected(request('situacao') === $v)>{{ $r }}</option>@endforeach
        </select>
        <select name="veiculo_id" class="form-select form-select-sm" style="width:200px">
            <option value="">Todos os veículos</option>
            @foreach($veiculos as $v)<option value="{{ $v->id }}" @selected(request('veiculo_id') == $v->id)>{{ $v->nome }}</option>@endforeach
        </select>
        <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-search"></i></button>
        <a href="{{ route('ocorrencias.index', ['limpar' => 1]) }}" class="btn btn-sm btn-light">Limpar</a>
    </form>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-gc mb-0 align-middle">
                <thead><tr><th>#</th><th>Veículo</th><th>Item</th><th>Descrição</th><th>Apontada por</th><th>Responsável presumido</th><th>Situação</th><th></th></tr></thead>
                <tbody>
                    @forelse($ocorrencias as $o)
                        <tr>
                            <td class="small">{{ $o->id }}<div class="text-muted">{{ $o->created_at->format('d/m H:i') }}</div></td>
                            <td class="small fw-semibold">{{ $o->veiculo->nome }}</td>
                            <td class="small">{{ $o->item->rotulo }}<div class="text-muted">checagem de {{ $o->item->checagem->tipo->rotulo() }}</div></td>
                            <td class="small">{{ \Illuminate\Support\Str::limit($o->descricao, 60) }}@if($o->contestacao)<div class="text-muted"><i class="bi bi-reply"></i> contestada</div>@endif</td>
                            <td class="small">{{ $o->apontadaPor->nome }}</td>
                            <td class="small">{{ $o->alocacaoResponsavel?->motorista?->nome ?? '—' }}</td>
                            <td><span class="badge badge-situacao {{ $o->situacao->badge() }}">{{ $o->situacao->rotulo() }}</span></td>
                            <td class="text-end"><a href="{{ route('ocorrencias.show', $o) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i></a></td>
                        </tr>
                    @empty
                        <tr><td colspan="8"><x-empty-state icon="bi-exclamation-diamond" title="Nenhuma ocorrência" description="Anomalias apontadas nas checagens aparecem aqui." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($ocorrencias->hasPages())<div class="card-footer bg-white">{{ $ocorrencias->links() }}</div>@endif
    </div>
@endsection
