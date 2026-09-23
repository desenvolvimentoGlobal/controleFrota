@extends('layouts.app')

@section('title', 'Custos de manutenção')

@php($N = \App\Support\Numero::class)

@section('content')
    <div class="card card-body mb-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label small mb-1">Concluídas de</label>
                <input type="date" name="de" value="{{ $filtros['de']->toDateString() }}" class="form-control form-control-sm">
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Até</label>
                <input type="date" name="ate" value="{{ $filtros['ate']->toDateString() }}" class="form-control form-control-sm">
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Veículo</label>
                <select name="veiculo_id" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    @foreach($veiculos as $v)<option value="{{ $v->id }}" @selected($filtros['veiculo_id'] === $v->id)>{{ $v->nome }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Fornecedor</label>
                <select name="fornecedor_id" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    @foreach($fornecedores as $f)<option value="{{ $f->id }}" @selected($filtros['fornecedor_id'] === $f->id)>{{ $f->nome }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Tipo</label>
                <select name="tipo" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    @foreach($tipos as $v => $r)<option value="{{ $v }}" @selected($filtros['tipo'] === $v)>{{ $r }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Agrupar</label>
                <select name="visao" class="form-select form-select-sm">
                    @foreach($visoes as $v => $r)<option value="{{ $v }}" @selected($visao === $v)>{{ $r }}</option>@endforeach
                </select>
            </div>
            <div class="col-12 d-flex gap-1 justify-content-between flex-wrap">
                <div class="d-flex gap-1">
                    <button class="btn btn-gc-filtro btn-outline-secondary" title="Filtrar"><i class="bi bi-funnel"></i> Filtrar</button>
                    <a href="{{ route('relatorios.custos', ['limpar' => 1]) }}" class="btn btn-gc-filtro btn-light">Limpar</a>
                </div>
                <div class="d-flex gap-1">
                    <a href="{{ route('relatorios.custos.pdf', $filtrosQuery) }}" class="btn btn-sm btn-outline-danger" data-gc-export data-gc-export-tipo="PDF"><i class="bi bi-file-earmark-pdf"></i> PDF</a>
                    <a href="{{ route('relatorios.custos.excel', $filtrosQuery) }}" class="btn btn-sm btn-outline-success" data-gc-export data-gc-export-tipo="Excel"><i class="bi bi-file-earmark-excel"></i> Excel</a>
                </div>
            </div>
        </form>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-sm-6 col-lg-3">
            <x-stat-card icon="bi-cash-coin" label="Custo realizado" :value="$N::moeda($resumo['total'])" tone="navy" />
        </div>
        <div class="col-sm-6 col-lg-3">
            <x-stat-card icon="bi-wrench-adjustable" label="Manutenções concluídas" :value="$resumo['quantidade'].' · média '.$N::moeda($resumo['media'])" tone="teal" />
        </div>
        <div class="col-sm-6 col-lg-3">
            <x-stat-card icon="bi-speedometer2" label="Custo por km rodado"
                         :value="$resumo['custo_km'] !== null ? $N::moeda($resumo['custo_km']).' · '.number_format($resumo['km'], 0, ',', '.').' km' : 'sem km no período'" tone="blue" />
        </div>
        <div class="col-sm-6 col-lg-3">
            <x-stat-card icon="bi-hourglass-split" label="Comprometido em aberto (previsto)"
                         :value="$N::moeda($resumo['comprometido']).' · '.$resumo['comprometido_qtd']" :tone="$resumo['comprometido'] > 0 ? 'amber' : 'green'"
                         :href="route('manutencoes.index', ['situacao' => 'abertas'])" />
        </div>
    </div>

    @if($resumo['variacao'] !== null)
        <p class="small text-muted">
            Previsto das manutenções concluídas: {{ $N::moeda($resumo['previsto']) }}. O realizado ficou
            <strong class="{{ $resumo['variacao'] > 0 ? 'text-danger' : 'text-success' }}">{{ $resumo['variacao'] > 0 ? '+' : '' }}{{ number_format($resumo['variacao'], 1, ',', '.') }}%</strong>
            em relação ao previsto.
        </p>
    @endif

    <div class="row g-3">
        <div class="col-lg-5">
            <div class="card h-100">
                <div class="card-header bg-white"><strong>Custo por mês</strong>
                    <span class="text-muted small">{{ $mensal['truncado'] ? 'últimos 36 meses do período' : $periodoLabel }}</span></div>
                <div class="card-body"><canvas id="grafico-mensal" height="220"></canvas></div>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="card h-100">
                <div class="card-header bg-white"><strong>{{ $visaoLabel }}</strong> <span class="text-muted small">{{ $filtrosLabel }}</span></div>
                <div class="table-responsive">
                    <table class="table table-gc mb-0 align-middle">
                        <thead>
                            <tr>
                                <th>{{ \Illuminate\Support\Str::after($visaoLabel, 'Por ') }}</th>
                                <th class="text-end">Qtd.</th>
                                <th class="text-end">Custo</th>
                                <th class="text-end">Média</th>
                                <th class="text-end">%</th>
                                @if($visao === 'veiculo')<th class="text-end">Km</th><th class="text-end">R$/km</th>@endif
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($linhas as $l)
                                <tr>
                                    <td class="small fw-semibold">
                                        @if($visao === 'veiculo')
                                            <a href="{{ route('relatorios.custos', array_merge($filtrosQuery, ['veiculo_id' => $l['chave'], 'visao' => 'mes'])) }}" class="text-decoration-none">{{ $l['rotulo'] }}</a>
                                            <span class="text-muted fw-normal">{{ $l['detalhe'] }}</span>
                                        @else
                                            {{ $l['rotulo'] }}
                                        @endif
                                    </td>
                                    <td class="text-end small">{{ $l['quantidade'] }}</td>
                                    <td class="text-end small gc-valor-sensivel">{{ $N::moeda($l['custo']) }}</td>
                                    <td class="text-end small gc-valor-sensivel">{{ $N::moeda($l['media']) }}</td>
                                    <td class="text-end small">{{ number_format($l['percentual'], 1, ',', '.') }}%</td>
                                    @if($visao === 'veiculo')
                                        <td class="text-end small">{{ $l['km'] ? number_format($l['km'], 0, ',', '.') : '—' }}</td>
                                        <td class="text-end small gc-valor-sensivel">{{ $l['custo_km'] !== null ? $N::moeda($l['custo_km']) : '—' }}</td>
                                    @endif
                                </tr>
                            @empty
                                <tr><td colspan="7"><x-empty-state icon="bi-cash-coin" title="Nenhuma manutenção concluída no período" description="Ajuste o período ou os filtros." /></td></tr>
                            @endforelse
                        </tbody>
                        @if(count($linhas))
                            <tfoot>
                                <tr class="fw-bold">
                                    <td>Total</td>
                                    <td class="text-end">{{ $resumo['quantidade'] }}</td>
                                    <td class="text-end gc-valor-sensivel">{{ $N::moeda($resumo['total']) }}</td>
                                    <td class="text-end gc-valor-sensivel">{{ $N::moeda($resumo['media']) }}</td>
                                    <td class="text-end">100%</td>
                                    @if($visao === 'veiculo')
                                        <td class="text-end">{{ number_format($resumo['km'], 0, ',', '.') }}</td>
                                        <td class="text-end gc-valor-sensivel">{{ $resumo['custo_km'] !== null ? $N::moeda($resumo['custo_km']) : '—' }}</td>
                                    @endif
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="card mt-3">
        <div class="card-header bg-white"><strong>Manutenções do período</strong> <span class="text-muted small">{{ $manutencoes->count() }}</span></div>
        <div class="table-responsive gc-lista-scroll" style="max-height:420px">
            <table class="table table-gc mb-0 align-middle">
                <thead><tr><th>Concluída</th><th>Veículo</th><th>Serviço</th><th>Tipo</th><th>Fornecedor</th><th class="text-end">Previsto</th><th class="text-end">Final</th></tr></thead>
                <tbody>
                    @forelse($manutencoes->sortByDesc('concluida_em') as $m)
                        <tr>
                            <td class="small">{{ $m->concluida_em->format('d/m/Y') }}</td>
                            <td class="small">{{ $m->veiculo->nome }}</td>
                            <td class="small"><a href="{{ route('manutencoes.show', $m) }}" class="text-decoration-none">#{{ $m->id }} {{ $m->nome }}</a></td>
                            <td class="small">{{ $m->tipo->rotulo() }}</td>
                            <td class="small">{{ $m->fornecedor?->nome ?? '—' }}</td>
                            <td class="text-end small text-muted gc-valor-sensivel">{{ $N::moeda($m->preco_previsto) }}</td>
                            <td class="text-end small gc-valor-sensivel fw-semibold">{{ $N::moeda($m->preco_final) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-muted small">Nenhuma.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    (function () {
        const el = document.getElementById('grafico-mensal');
        if (!el || typeof Chart === 'undefined') return;
        new Chart(el, {
            type: 'bar',
            data: {
                labels: @json($mensal['rotulos']),
                datasets: [{ label: 'Custo (R$)', data: @json($mensal['valores']), backgroundColor: '#27425F', borderRadius: 4 }],
            },
            options: {
                plugins: { legend: { display: false }, tooltip: { callbacks: { label: (c) => c.parsed.y.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' }) } } },
                scales: { y: { beginAtZero: true, ticks: { callback: (v) => v.toLocaleString('pt-BR') } } },
            },
        });
    })();
</script>
@endpush
