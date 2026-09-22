@extends('layouts.app')

@section('title', 'Painel')

@section('content')
    <h6 class="text-muted text-uppercase small mb-2">Frota</h6>
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-lg-3">
            <x-stat-card icon="bi-car-front" label="Veículos na frota" :value="$frota['total']" tone="navy" :href="route('veiculos.index')" />
        </div>
        <div class="col-sm-6 col-lg-3">
            <x-stat-card icon="bi-check2-circle" label="Disponíveis" :value="$frota['disponiveis']" tone="green" :href="route('veiculos.index', ['situacao' => 'disponivel'])" />
        </div>
        <div class="col-sm-6 col-lg-3">
            <x-stat-card icon="bi-arrow-left-right" label="Em uso / reservados" :value="$frota['em_uso']" tone="teal" :href="route('veiculos.index', ['situacao' => 'em_uso'])" />
        </div>
        <div class="col-sm-6 col-lg-3">
            <x-stat-card icon="bi-wrench-adjustable" label="Em manutenção / indisponíveis" :value="$frota['em_manutencao'] + $frota['indisponiveis']" :tone="($frota['em_manutencao'] + $frota['indisponiveis']) > 0 ? 'amber' : 'green'" :href="route('veiculos.index', ['situacao' => 'indisponivel'])" />
        </div>
    </div>

    <h6 class="text-muted text-uppercase small mb-2">Pessoas</h6>
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-lg-3">
            <x-stat-card icon="bi-people" label="Usuários ativos" :value="$pessoas['usuarios_ativos']" tone="navy" :href="auth()->user()->can('usuarios.gerenciar') ? route('usuarios.index') : null" />
        </div>
        <div class="col-sm-6 col-lg-3">
            <x-stat-card icon="bi-person-badge" label="Aptos a dirigir" :value="$pessoas['motoristas']" tone="teal" />
        </div>
        <div class="col-sm-6 col-lg-3">
            <x-stat-card icon="bi-exclamation-octagon" label="Veículos com sistema crítico" :value="$frota['criticos']" :tone="$frota['criticos'] > 0 ? 'red' : 'green'" />
        </div>
        <div class="col-sm-6 col-lg-3">
            <x-stat-card icon="bi-card-checklist" label="CNH vencendo em 30 dias" :value="$pessoas['cnh_vencendo']->count()" :tone="$pessoas['cnh_vencendo']->count() > 0 ? 'amber' : 'green'" />
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header bg-white"><strong><i class="bi bi-calendar-x"></i> Vencimentos de veículos</strong> <span class="text-muted small">(próximos 30 dias)</span></div>
                <ul class="list-group list-group-flush">
                    @forelse($frota['vencimentos'] as $v)
                        <li class="list-group-item small d-flex justify-content-between align-items-center">
                            <a href="{{ route('veiculos.show', $v) }}" class="text-decoration-none fw-semibold">{{ $v->nome }} <span class="text-muted fw-normal">{{ $v->placa_formatada }}</span></a>
                            <span class="text-end">@foreach($v->avisos() as $a)<div class="text-muted">{{ $a }}</div>@endforeach</span>
                        </li>
                    @empty
                        <li class="list-group-item"><x-empty-state icon="bi-calendar-check" title="Nenhum vencimento próximo" /></li>
                    @endforelse
                </ul>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header bg-white"><strong><i class="bi bi-person-vcard"></i> CNH a vencer</strong> <span class="text-muted small">(próximos 30 dias)</span></div>
                <ul class="list-group list-group-flush">
                    @forelse($pessoas['cnh_vencendo'] as $u)
                        <li class="list-group-item small d-flex justify-content-between align-items-center">
                            <span class="fw-semibold">{{ $u->nome }}</span>
                            <span class="{{ $u->cnhVencida() ? 'text-danger' : 'text-muted' }}">{{ $u->cnhVencida() ? 'vencida em' : 'vence em' }} {{ $u->cnh_validade->format('d/m/Y') }}</span>
                        </li>
                    @empty
                        <li class="list-group-item"><x-empty-state icon="bi-check-circle" title="Nenhuma CNH vencendo" /></li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
@endsection
