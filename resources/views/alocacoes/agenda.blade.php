@extends('layouts.app')

@section('title', 'Agenda da frota')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div class="btn-group btn-group-sm">
            <a href="{{ route('alocacoes.agenda', ['inicio' => $inicio->copy()->subDays(7)->toDateString()]) }}" class="btn btn-outline-secondary"><i class="bi bi-chevron-left"></i></a>
            <a href="{{ route('alocacoes.agenda') }}" class="btn btn-outline-secondary">Hoje</a>
            <a href="{{ route('alocacoes.agenda', ['inicio' => $inicio->copy()->addDays(7)->toDateString()]) }}" class="btn btn-outline-secondary"><i class="bi bi-chevron-right"></i></a>
        </div>
        <span class="text-muted small">{{ $inicio->format('d/m') }} a {{ $dias->last()->format('d/m/Y') }}</span>
        <a href="{{ route('alocacoes.create') }}" class="btn btn-gc-primary btn-sm"><i class="bi bi-plus-lg"></i> Nova alocação</a>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-gc mb-0 align-middle" style="min-width:900px">
                <thead>
                    <tr>
                        <th style="width:180px">Veículo</th>
                        @foreach($dias as $dia)
                            <th class="text-center {{ $dia->isToday() ? 'bg-soft-teal' : '' }}">{{ $dia->translatedFormat('D') }}<br><span class="fw-normal">{{ $dia->format('d/m') }}</span></th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse($veiculos as $v)
                        <tr>
                            <td>
                                <a href="{{ route('veiculos.show', $v) }}" class="fw-semibold text-decoration-none">{{ $v->nome }}</a>
                                <div><span class="badge badge-situacao {{ $v->situacao->badge() }}">{{ $v->situacao->rotulo() }}</span></div>
                            </td>
                            @foreach($dias as $dia)
                                {{-- Em uso atrasada ocupa até agora (o carro ainda não voltou). --}}
                                @php($doDia = ($alocacoes[$v->id] ?? collect())->filter(fn ($a) => $a->saida_prevista->lte($dia->copy()->endOfDay()) && ($a->atrasada() ? now() : $a->retorno_previsto)->gte($dia)))
                                <td class="small p-1 align-top {{ $dia->isToday() ? 'bg-soft-teal' : '' }}">
                                    @foreach($doDia as $a)
                                        <a href="{{ route('alocacoes.show', $a) }}" class="d-block rounded px-1 py-0 mb-1 text-decoration-none text-truncate
                                            {{ $a->situacao->value === 'solicitada' ? 'bg-soft-amber' : ($a->situacao->value === 'em_uso' ? 'bg-soft-navy' : 'bg-soft-green') }}"
                                           title="{{ $a->motorista->nome }} · {{ $a->saida_prevista->format('d/m H:i') }} → {{ $a->retorno_previsto->format('d/m H:i') }} · {{ $a->objetivo }}">
                                            {{ $a->saida_prevista->isSameDay($dia) ? $a->saida_prevista->format('H:i') : '…' }} {{ \Illuminate\Support\Str::limit($a->motorista->nome, 14, '') }}
                                        </a>
                                    @endforeach
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <tr><td colspan="8"><x-empty-state icon="bi-car-front" title="Nenhum veículo ativo" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white small text-muted">
            <span class="badge bg-soft-amber text-dark">aguardando</span> <span class="badge bg-soft-green text-dark">aprovada</span> <span class="badge bg-soft-navy text-dark">em uso</span>
        </div>
    </div>
@endsection
