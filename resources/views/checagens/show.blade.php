@extends('layouts.app')

@section('title', 'Checagem de '.$checagem->tipo->rotulo().' — '.$checagem->alocacao->veiculo->nome)
@section('voltar', route('alocacoes.show', $checagem->alocacao))

@section('content')
    <div class="card mb-3"><div class="card-body small">
        <div class="row">
            <div class="col-md-6">
                <strong>{{ $checagem->motorista->nome }}</strong> · {{ $checagem->concluida_em?->format('d/m/Y H:i') }}<br>
                {{ number_format((int) $checagem->km_informado, 0, ',', '.') }} km · combustível {{ $checagem->rotuloCombustivel() }} · estado geral <span class="badge badge-situacao {{ $checagem->estado_geral?->badge() }}">{{ $checagem->estado_geral?->rotulo() }}</span>
                @if($checagem->observacao_motorista)<div class="text-muted mt-1"><i class="bi bi-chat-left-text"></i> {{ $checagem->observacao_motorista }}</div>@endif
            </div>
            <div class="col-md-6 text-md-end text-muted">
                @if($checagem->anterior)
                    Comparada com a checagem de {{ $checagem->anterior->tipo->rotulo() }} de <strong>{{ $checagem->anterior->motorista->nome }}</strong> em {{ $checagem->anterior->concluida_em->format('d/m/Y H:i') }}
                    @can('view', $checagem->anterior->alocacao)
                        <div><a href="{{ route('checagens.show', $checagem->anterior) }}">ver checagem anterior</a></div>
                    @endcan
                @else
                    Primeira checagem do veículo (referência inicial).
                @endif
            </div>
        </div>
    </div></div>

    @foreach(config('frota.checagem.categorias') as $categoria => $def)
        <h6 class="text-muted text-uppercase small mt-3 mb-2">{{ $def['rotulo'] }}</h6>
        <div class="row g-2">
            @foreach($checagem->itens->where('categoria', $categoria) as $item)
                @php($ant = $anteriores[$item->item] ?? null)
                <div class="col-12 col-md-6 col-xl-4">
                    <div class="card h-100 {{ $item->situacao->value === 'anomalia' ? 'border-danger' : '' }}">
                        <div class="card-body p-2">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <strong class="small">{{ $item->rotulo }}</strong>
                                <span class="badge badge-situacao {{ $item->situacao->badge() }}">{{ $item->situacao->rotulo() }}</span>
                            </div>
                            <div class="row g-1">
                                <div class="col-6 text-center">
                                    <div class="small text-muted">Anterior</div>
                                    @if($ant?->fotoAtual?->disponivel())
                                        <a href="{{ route('checagens.foto', $ant->fotoAtual) }}" target="_blank"><img src="{{ route('checagens.foto', $ant->fotoAtual) }}" class="w-100 rounded" style="height:120px;object-fit:cover" alt=""></a>
                                    @else
                                        <div class="bg-light rounded d-flex align-items-center justify-content-center text-muted small" style="height:120px">{{ $ant?->fotoAtual ? 'foto expirada' : 'sem foto' }}</div>
                                    @endif
                                </div>
                                <div class="col-6 text-center">
                                    <div class="small text-muted">Esta checagem</div>
                                    @if($item->fotoAtual?->disponivel())
                                        <a href="{{ route('checagens.foto', $item->fotoAtual) }}" target="_blank"><img src="{{ route('checagens.foto', $item->fotoAtual) }}" class="w-100 rounded" style="height:120px;object-fit:cover" alt=""></a>
                                    @else
                                        <div class="bg-light rounded d-flex align-items-center justify-content-center text-muted small" style="height:120px">foto expirada</div>
                                    @endif
                                </div>
                            </div>
                            @if($item->observacao)<div class="small mt-2 text-danger"><i class="bi bi-exclamation-triangle"></i> {{ $item->observacao }}</div>@endif
                            @if($item->ocorrencia)<a href="{{ route('ocorrencias.show', $item->ocorrencia) }}" class="small">Ocorrência #{{ $item->ocorrencia->id }} · {{ $item->ocorrencia->situacao->rotulo() }}</a>@endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endforeach
@endsection
