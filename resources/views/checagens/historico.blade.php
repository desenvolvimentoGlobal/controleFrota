@extends('layouts.app')

@section('title', 'Checagens — '.$veiculo->nome)
@section('voltar', route('veiculos.show', $veiculo))

@section('content')
    <div class="card">
        <div class="table-responsive">
            <table class="table table-gc mb-0 align-middle">
                <thead><tr><th>Data</th><th>Tipo</th><th>Motorista</th><th>Objetivo</th><th class="text-end">Km</th><th>Combustível</th><th>Estado</th><th>Anomalias</th><th></th></tr></thead>
                <tbody>
                    @forelse($checagens as $c)
                        <tr>
                            <td class="small">{{ $c->concluida_em->format('d/m/Y H:i') }}</td>
                            <td>{{ $c->tipo->rotulo() }}</td>
                            <td class="small">{{ $c->motorista->nome }}</td>
                            <td class="small">{{ \Illuminate\Support\Str::limit($c->alocacao->objetivo, 40) }}</td>
                            <td class="text-end">{{ number_format((int) $c->km_informado, 0, ',', '.') }}</td>
                            <td class="small">{{ $c->rotuloCombustivel() }}</td>
                            <td><span class="badge badge-situacao {{ $c->estado_geral?->badge() }}">{{ $c->estado_geral?->rotulo() }}</span></td>
                            <td>@if($c->anomalias_count > 0)<span class="badge badge-situacao badge-inativo">{{ $c->anomalias_count }}</span>@else<span class="text-muted">—</span>@endif</td>
                            <td class="text-end"><a href="{{ route('checagens.show', $c) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i></a></td>
                        </tr>
                    @empty
                        <tr><td colspan="9"><x-empty-state icon="bi-camera" title="Nenhuma checagem concluída" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($checagens->hasPages())<div class="card-footer bg-white">{{ $checagens->links() }}</div>@endif
    </div>
@endsection
