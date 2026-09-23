@extends('layouts.app')

@section('title', 'Manutenções')

@section('content')
    <div class="d-flex justify-content-between align-items-start mb-3 gap-2 flex-wrap">
        <form method="GET" class="d-flex gap-2 flex-wrap">
            <input type="text" name="busca" value="{{ $busca }}" class="form-control form-control-sm" style="width:200px" placeholder="Nome ou problema">
            <select name="situacao" class="form-select form-select-sm" style="width:190px">
                <option value="">Todas as situações</option>
                @foreach($situacoes as $v => $r)<option value="{{ $v }}" @selected(request('situacao') === $v)>{{ $r }}</option>@endforeach
            </select>
            <select name="tipo" class="form-select form-select-sm" style="width:170px">
                <option value="">Todos os tipos</option>
                @foreach($tipos as $v => $r)<option value="{{ $v }}" @selected(request('tipo') === $v)>{{ $r }}</option>@endforeach
            </select>
            <select name="veiculo_id" class="form-select form-select-sm" style="width:170px">
                <option value="">Todos os veículos</option>
                @foreach($veiculos as $v)<option value="{{ $v->id }}" @selected(request('veiculo_id') == $v->id)>{{ $v->nome }}</option>@endforeach
            </select>
            <select name="fornecedor_id" class="form-select form-select-sm" style="width:170px">
                <option value="">Todos os fornecedores</option>
                @foreach($fornecedores as $f)<option value="{{ $f->id }}" @selected(request('fornecedor_id') == $f->id)>{{ $f->nome }}</option>@endforeach
            </select>
            <input type="date" name="de" value="{{ request('de') }}" class="form-control form-control-sm" style="width:145px" title="Aberta a partir de">
            <input type="date" name="ate" value="{{ request('ate') }}" class="form-control form-control-sm" style="width:145px" title="Aberta até">
            <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-search"></i></button>
            <a href="{{ route('manutencoes.index', ['limpar' => 1]) }}" class="btn btn-sm btn-light">Limpar</a>
        </form>
        @can('manutencoes.gerenciar')
            <a href="{{ route('manutencoes.create') }}" class="btn btn-gc-primary btn-sm"><i class="bi bi-plus-lg"></i> Nova manutenção</a>
        @endcan
    </div>

    <div class="card">
        <div class="card-header bg-white d-flex justify-content-between small">
            <span>{{ (int) $totais->quantidade }} manutenção(ões) no filtro</span>
            <span>Custo (final ou previsto): <strong class="gc-valor-sensivel">{{ \App\Support\Numero::moeda($totais->custo ?? 0) }}</strong></span>
        </div>
        <div class="table-responsive">
            <table class="table table-gc mb-0 align-middle">
                <thead><tr><th>#</th><th>Veículo</th><th>Manutenção</th><th>Tipo</th><th>Fornecedor</th><th>Prazo</th><th class="text-end">Valor</th><th>Situação</th><th></th></tr></thead>
                <tbody>
                    @forelse($manutencoes as $m)
                        <tr>
                            <td class="small">{{ $m->id }}<div class="text-muted">{{ $m->created_at->format('d/m/y') }}</div></td>
                            <td class="small fw-semibold">{{ $m->veiculo->nome }}<div class="text-muted fw-normal">{{ $m->veiculo->placa }}</div></td>
                            <td><a href="{{ route('manutencoes.show', $m) }}" class="text-decoration-none fw-semibold">{{ $m->nome }}</a></td>
                            <td><span class="badge badge-situacao {{ $m->tipo->badge() }}">{{ $m->tipo->rotulo() }}</span></td>
                            <td class="small">{{ $m->fornecedor?->nome ?? '—' }}</td>
                            <td class="small {{ $m->atrasada() ? 'text-danger fw-semibold' : '' }}">{{ $m->prazo?->format('d/m/Y') ?? '—' }}@if($m->atrasada())<div>atrasada</div>@endif</td>
                            <td class="text-end small gc-valor-sensivel">
                                @if($m->preco_final !== null){{ \App\Support\Numero::moeda($m->preco_final) }}
                                @elseif($m->preco_previsto !== null)<span class="text-muted" title="previsto">{{ \App\Support\Numero::moeda($m->preco_previsto) }}</span>
                                @else — @endif
                            </td>
                            <td><span class="badge badge-situacao {{ $m->situacao->badge() }}">{{ $m->situacao->rotulo() }}</span></td>
                            <td class="text-end"><a href="{{ route('manutencoes.show', $m) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i></a></td>
                        </tr>
                    @empty
                        <tr><td colspan="9"><x-empty-state icon="bi-wrench-adjustable" title="Nenhuma manutenção" description="Abra uma manutenção pelo botão acima, pela ficha do veículo ou a partir de uma ocorrência." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($manutencoes->hasPages())<div class="card-footer bg-white">{{ $manutencoes->links() }}</div>@endif
    </div>
@endsection
