<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 9.5px; color: #16243B; }
        h1 { font-size: 15px; margin: 0 0 2px; }
        h2 { font-size: 11px; margin: 14px 0 4px; }
        .sub { color: #666; font-size: 9px; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 4px 6px; border-bottom: 1px solid #ddd; }
        th { background: #16243B; color: #fff; text-align: left; }
        .num { text-align: right; }
        tr.total td { border-top: 2px solid #16243B; font-weight: bold; }
        .cards td { border: 1px solid #ddd; background: #f7f8fa; width: 25%; vertical-align: top; }
        .cards .rot { color: #666; font-size: 8px; text-transform: uppercase; }
        .cards .val { font-size: 12px; font-weight: bold; }
    </style>
</head>
<body>
    @php($N = \App\Support\Numero::class)

    <h1>Custos de manutenção — {{ $visaoLabel }}</h1>
    <div class="sub">
        Período (conclusão): {{ $periodoLabel }} · {{ $filtrosLabel }} · Gerado em {{ now()->format('d/m/Y H:i') }}<br>
        Base: manutenções prestadas no período, pelo preço final. Manutenções em aberto aparecem apenas como comprometido.
    </div>

    <table class="cards">
        <tr>
            <td><div class="rot">Custo realizado</div><div class="val">{{ $N::moeda($resumo['total']) }}</div></td>
            <td><div class="rot">Manutenções</div><div class="val">{{ $resumo['quantidade'] }}</div>média {{ $N::moeda($resumo['media']) }}</td>
            <td><div class="rot">Custo por km</div><div class="val">{{ $resumo['custo_km'] !== null ? $N::moeda($resumo['custo_km']) : '—' }}</div>{{ number_format($resumo['km'], 0, ',', '.') }} km rodados</td>
            <td><div class="rot">Comprometido em aberto</div><div class="val">{{ $N::moeda($resumo['comprometido']) }}</div>{{ $resumo['comprometido_qtd'] }} manutenção(ões)</td>
        </tr>
    </table>

    <h2>{{ $visaoLabel }}</h2>
    <table>
        <thead>
            <tr>
                <th>{{ \Illuminate\Support\Str::after($visaoLabel, 'Por ') }}</th>
                <th class="num">Qtd.</th><th class="num">Custo</th><th class="num">Média</th><th class="num">%</th>
                @if($visao === 'veiculo')<th class="num">Km</th><th class="num">R$/km</th>@endif
            </tr>
        </thead>
        <tbody>
            @forelse($linhas as $l)
                <tr>
                    <td>{{ $l['rotulo'] }} {{ $l['detalhe'] }}</td>
                    <td class="num">{{ $l['quantidade'] }}</td>
                    <td class="num">{{ $N::moeda($l['custo']) }}</td>
                    <td class="num">{{ $N::moeda($l['media']) }}</td>
                    <td class="num">{{ number_format($l['percentual'], 1, ',', '.') }}%</td>
                    @if($visao === 'veiculo')
                        <td class="num">{{ $l['km'] ? number_format($l['km'], 0, ',', '.') : '—' }}</td>
                        <td class="num">{{ $l['custo_km'] !== null ? $N::moeda($l['custo_km']) : '—' }}</td>
                    @endif
                </tr>
            @empty
                <tr><td colspan="7">Nenhuma manutenção concluída no período.</td></tr>
            @endforelse
            @if(count($linhas))
                <tr class="total">
                    <td>TOTAL</td>
                    <td class="num">{{ $resumo['quantidade'] }}</td>
                    <td class="num">{{ $N::moeda($resumo['total']) }}</td>
                    <td class="num">{{ $N::moeda($resumo['media']) }}</td>
                    <td class="num">100%</td>
                    @if($visao === 'veiculo')
                        <td class="num">{{ number_format($resumo['km'], 0, ',', '.') }}</td>
                        <td class="num">{{ $resumo['custo_km'] !== null ? $N::moeda($resumo['custo_km']) : '—' }}</td>
                    @endif
                </tr>
            @endif
        </tbody>
    </table>

    @if($manutencoes->isNotEmpty())
        <h2>Manutenções do período</h2>
        <table>
            <thead><tr><th>Concluída</th><th>Veículo</th><th>Serviço</th><th>Tipo</th><th>Fornecedor</th><th class="num">Previsto</th><th class="num">Final</th></tr></thead>
            <tbody>
                @foreach($manutencoes as $m)
                    <tr>
                        <td>{{ $m->concluida_em->format('d/m/Y') }}</td>
                        <td>{{ $m->veiculo->nome }}</td>
                        <td>#{{ $m->id }} {{ $m->nome }}</td>
                        <td>{{ $m->tipo->rotulo() }}</td>
                        <td>{{ $m->fornecedor?->nome ?? '—' }}</td>
                        <td class="num">{{ $N::moeda($m->preco_previsto) }}</td>
                        <td class="num">{{ $N::moeda($m->preco_final) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
