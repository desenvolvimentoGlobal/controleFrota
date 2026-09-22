@extends('layouts.app')

@section('title', 'Alocações')

@section('content')
    @if($pendentes > 0)
        <div class="alert alert-warning py-2 small d-flex justify-content-between align-items-center">
            <span><i class="bi bi-hourglass-split me-1"></i> {{ $pendentes }} alocação(ões) aguardando a sua aprovação.</span>
            <a href="{{ route('alocacoes.index', ['situacao' => 'solicitada']) }}" class="btn btn-sm btn-outline-secondary">Ver pendentes</a>
        </div>
    @endif

    <div class="d-flex justify-content-between align-items-center mb-3 gap-2 flex-wrap">
        <form method="GET" class="d-flex gap-2 flex-wrap">
            <select name="situacao" class="form-select form-select-sm" style="width:180px">
                <option value="">Todas as situações</option>
                @foreach($situacoes as $v => $r)<option value="{{ $v }}" @selected(request('situacao') === $v)>{{ $r }}</option>@endforeach
            </select>
            <select name="veiculo_id" class="form-select form-select-sm" style="width:180px">
                <option value="">Todos os veículos</option>
                @foreach($veiculos as $v)<option value="{{ $v->id }}" @selected(request('veiculo_id') == $v->id)>{{ $v->nome }}</option>@endforeach
            </select>
            <select name="motorista_id" class="form-select form-select-sm" style="width:180px">
                <option value="">Todos os motoristas</option>
                @foreach($motoristas as $m)<option value="{{ $m->id }}" @selected(request('motorista_id') == $m->id)>{{ $m->nome }}</option>@endforeach
            </select>
            <input type="date" name="de" value="{{ request('de') }}" class="form-control form-control-sm" style="width:150px" title="Saída a partir de">
            <input type="date" name="ate" value="{{ request('ate') }}" class="form-control form-control-sm" style="width:150px" title="Saída até">
            <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-search"></i></button>
            <a href="{{ route('alocacoes.index', ['limpar' => 1]) }}" class="btn btn-sm btn-light">Limpar</a>
        </form>
        <a href="{{ route('alocacoes.create') }}" class="btn btn-gc-primary btn-sm"><i class="bi bi-plus-lg"></i> Nova alocação</a>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-gc mb-0 align-middle">
                <thead><tr><th>Veículo</th><th>Motorista</th><th>Objetivo</th><th>Saída</th><th>Retorno</th><th>Situação</th><th class="text-end">Ações</th></tr></thead>
                <tbody>
                    @forelse($alocacoes as $a)
                        <tr>
                            <td><a href="{{ route('alocacoes.show', $a) }}" class="fw-semibold text-decoration-none">{{ $a->veiculo->nome }}</a><div class="text-muted small">{{ $a->veiculo->placa }}</div></td>
                            <td class="small">{{ $a->motorista->nome }}@if($a->solicitante_id !== $a->motorista_id)<div class="text-muted">por {{ $a->solicitante->nome }}</div>@endif</td>
                            <td class="small">{{ \Illuminate\Support\Str::limit($a->objetivo, 50) }}</td>
                            <td class="small">{{ $a->saida_prevista->format('d/m H:i') }}@if($a->saida_real)<div class="text-muted">real {{ $a->saida_real->format('d/m H:i') }}</div>@endif</td>
                            <td class="small {{ $a->atrasada() ? 'text-danger fw-semibold' : '' }}">{{ $a->retorno_previsto->format('d/m H:i') }}@if($a->retorno_real)<div class="text-muted fw-normal">real {{ $a->retorno_real->format('d/m H:i') }}</div>@elseif($a->atrasada())<div>atrasado</div>@endif</td>
                            <td><span class="badge badge-situacao {{ $a->situacao->badge() }}">{{ $a->situacao->rotulo() }}</span></td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <a href="{{ route('alocacoes.show', $a) }}" class="btn btn-outline-secondary" title="Ver"><i class="bi bi-eye"></i></a>
                                    @can('checar', $a)
                                        <form method="POST" action="{{ route('alocacoes.checagem', $a) }}" class="d-inline">@csrf
                                            <button class="btn btn-gc-primary" title="Fazer checagem de {{ $a->proximaChecagem()->rotulo() }}"><i class="bi bi-camera"></i> {{ $a->proximaChecagem()->rotulo() }}</button>
                                        </form>
                                    @endcan
                                    @can('aprovar', $a)
                                        <form method="POST" action="{{ route('alocacoes.aprovar', $a) }}" class="d-inline" data-gc-confirm="Aprovar a alocação do veículo {{ $a->veiculo->nome }} para {{ $a->motorista->nome }}?">@csrf @method('PATCH')
                                            <button class="btn btn-outline-success" title="Aprovar"><i class="bi bi-check-lg"></i></button>
                                        </form>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><x-empty-state icon="bi-calendar-check" title="Nenhuma alocação" description="Solicite um veículo pelo botão Nova alocação." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($alocacoes->hasPages())<div class="card-footer bg-white">{{ $alocacoes->links() }}</div>@endif
    </div>
@endsection
