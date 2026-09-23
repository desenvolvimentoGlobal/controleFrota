@extends('layouts.app')

@section('title', 'Veículos')

@section('content')

    <div class="d-flex justify-content-between align-items-center mb-3 gap-2 flex-wrap">
        <form method="GET" class="d-flex gap-2 flex-wrap align-items-center">
            <input type="text" name="busca" value="{{ $busca }}" class="form-control form-control-sm" style="width: 220px" placeholder="Nome, placa, marca ou modelo">
            <select name="situacao" class="form-select form-select-sm" style="width: 160px">
                <option value="">Todas as situações</option>
                @foreach($situacoes as $valor => $rotulo)
                    <option value="{{ $valor }}" @selected(request('situacao') === $valor)>{{ $rotulo }}</option>
                @endforeach
            </select>
            <select name="estado" class="form-select form-select-sm" style="width: 150px">
                <option value="">Todos os estados</option>
                @foreach($estados as $valor => $rotulo)
                    <option value="{{ $valor }}" @selected(request('estado') === $valor)>{{ $rotulo }}</option>
                @endforeach
            </select>
            <select name="marca" class="form-select form-select-sm" style="width: 140px">
                <option value="">Todas as marcas</option>
                @foreach($marcas as $marca)
                    <option value="{{ $marca }}" @selected(request('marca') === $marca)>{{ $marca }}</option>
                @endforeach
            </select>
            <div class="form-check form-check-sm small">
                {{-- Desmarcado não é enviado: o 0 faz o filtro lembrado ser desligado. --}}
                <input type="hidden" name="baixados" value="0">
                <input class="form-check-input" type="checkbox" name="baixados" id="baixados" value="1" @checked(request()->boolean('baixados'))>
                <label class="form-check-label" for="baixados">Incluir baixados</label>
            </div>
            <button class="btn btn-sm btn-outline-secondary" title="Filtrar"><i class="bi bi-search"></i></button>
            <a href="{{ route('veiculos.index', ['limpar' => 1]) }}" class="btn btn-sm btn-light">Limpar</a>
        </form>
        @can('frota.gerenciar')
            <a href="{{ route('veiculos.create') }}" class="btn btn-gc-primary btn-sm"><i class="bi bi-plus-lg"></i> Novo veículo</a>
        @endcan
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-gc mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Veículo</th>
                        <th>Placa</th>
                        <th>Situação</th>
                        <th>Estado</th>
                        <th class="text-end">Km</th>
                        <th>Alertas</th>
                        <th class="text-end">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($veiculos as $veiculo)
                        <tr>
                            <td>
                                <a href="{{ route('veiculos.show', $veiculo) }}" class="fw-semibold text-decoration-none">{{ $veiculo->nome }}</a>
                                <div class="text-muted small">{{ $veiculo->descricao }} · {{ $veiculo->cor ?? '—' }}</div>
                            </td>
                            <td><code>{{ $veiculo->placa_formatada }}</code></td>
                            <td><span class="badge badge-situacao {{ $veiculo->situacao->badge() }}">{{ $veiculo->situacao->rotulo() }}</span></td>
                            <td><span class="badge badge-situacao {{ $veiculo->estado_atual->badge() }}">{{ $veiculo->estado_atual->rotulo() }}</span></td>
                            <td class="text-end">{{ number_format($veiculo->km_atual, 0, ',', '.') }}</td>
                            <td class="small">
                                @if($veiculo->temCondicaoCritica())
                                    <span class="badge badge-situacao badge-inativo" title="Sistema mecânico crítico"><i class="bi bi-exclamation-octagon"></i> crítico</span>
                                @endif
                                @foreach($veiculo->avisos() as $aviso)
                                    <span class="badge badge-situacao badge-pendente" title="{{ $aviso }}"><i class="bi bi-exclamation-triangle"></i> {{ \Illuminate\Support\Str::before($aviso, ' ') }}</span>
                                @endforeach
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <a href="{{ route('veiculos.show', $veiculo) }}" class="btn btn-outline-secondary" title="Ver"><i class="bi bi-eye"></i></a>
                                    @can('frota.gerenciar')
                                        <a href="{{ route('veiculos.edit', $veiculo) }}" class="btn btn-outline-secondary" title="Editar"><i class="bi bi-pencil"></i></a>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><x-empty-state icon="bi-car-front" title="Nenhum veículo encontrado" description="Cadastre o primeiro veículo da frota ou ajuste os filtros." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($veiculos->hasPages())
            <div class="card-footer bg-white">{{ $veiculos->links() }}</div>
        @endif
    </div>
@endsection
